<?php

namespace SilverStripe\Taxonomy\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\ResourceURLGenerator;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Taxonomy\TaxonomyAdmin;

// The composer test namespace maps to tests/, so the autoloader cannot find this helper class
require_once __DIR__ . '/StubResourceURLGenerator.php';

class TaxonomyAdminTest extends FunctionalTest
{
    protected function setUp(): void
    {
        parent::setUp();
        // The bundle in client/dist exists only after a build, and a missing file raises a notice
        Injector::inst()->registerService(new StubResourceURLGenerator(), ResourceURLGenerator::class);
    }

    public static function provideClientRouteRendersShell(): array
    {
        return [
            'section root' => ['path' => ''],
            'terms list' => ['path' => 'terms'],
            'new term' => ['path' => 'terms/new'],
            'term' => ['path' => 'terms/12'],
            'types list' => ['path' => 'types'],
            'new type' => ['path' => 'types/new'],
            'type' => ['path' => 'types/3'],
        ];
    }

    #[DataProvider('provideClientRouteRendersShell')]
    public function testClientRouteRendersShell(string $path): void
    {
        $this->logInWithPermission(TaxonomyAdmin::getRequiredPermissions());
        $response = $this->get(rtrim('admin/taxonomy/' . $path, '/'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('data-pjax-fragment="Content"', $response->getBody());
        $this->assertStringContainsString('silverstripe/taxonomy/client/dist/js/bundle.js', $response->getBody());
    }

    public static function provideUnknownRouteReturnsNotFound(): array
    {
        return [
            'text ID' => ['path' => 'terms/abc'],
            'custom method suffix' => ['path' => 'terms/12:move'],
            'extra segment after ID' => ['path' => 'terms/12/children'],
            'old API URL' => ['path' => 'api/terms'],
            'unknown path' => ['path' => 'unknown'],
            'model admin tab' => ['path' => 'SilverStripe-Taxonomy-TaxonomyTerm'],
            'model admin grid field' => [
                'path' => 'SilverStripe-Taxonomy-TaxonomyTerm/EditForm/field/SilverStripe-Taxonomy-TaxonomyTerm/item/1',
            ],
            'inherited edit form' => ['path' => 'EditForm'],
            'inherited save' => ['path' => 'save'],
        ];
    }

    #[DataProvider('provideUnknownRouteReturnsNotFound')]
    public function testUnknownRouteReturnsNotFound(string $path): void
    {
        $this->logInWithPermission('ADMIN');
        $response = $this->get('admin/taxonomy/' . $path);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testClientConfig(): void
    {
        $this->logInWithPermission(TaxonomyAdmin::getRequiredPermissions());
        $config = TaxonomyAdmin::create()->getClientConfig();
        $this->assertTrue($config['reactRouter']);
        $this->assertSame('admin/api/v1/taxonomy', $config['apiUrl']);
        $this->assertSame('taxonomy', $config['reactRoutePath']);
    }

    public function testAnonymousCannotLoadShell(): void
    {
        $response = $this->get('admin/taxonomy/terms');
        $this->assertStringNotContainsString('silverstripe/taxonomy/client/dist/js/bundle.js', $response->getBody());
    }

    public function testMemberWithoutAccessCannotLoadShell(): void
    {
        $this->logInWithPermission('CMS_ACCESS_CMSMain');
        $response = $this->get('admin/taxonomy/terms');
        $this->assertStringNotContainsString('silverstripe/taxonomy/client/dist/js/bundle.js', $response->getBody());
    }
}
