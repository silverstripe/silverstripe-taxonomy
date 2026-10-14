<?php

namespace SilverStripe\Taxonomy\Tests\Api;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\FieldType\DBForeignKey;
use SilverStripe\Taxonomy\TaxonomyType;

// The composer test namespace maps to tests/, so the autoloader cannot find this base class
require_once __DIR__ . '/TaxonomyApiTestCase.php';

class TaxonomyApiFieldTest extends TaxonomyApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::modify()->set(DBForeignKey::class, 'dropdown_field_threshold', 2);
        TaxonomyType::create(['Name' => HideRecordExtension::HIDDEN_NAME])->write();
        TaxonomyType::create(['Name' => 'Zoology'])->write();
    }

    public function testSchemaLinksToFlatFieldRoutes(): void
    {
        $this->logInAsTaxonomyUser(['TAXONOMYTERM_EDIT', 'TAXONOMYTERM_CREATE']);
        $plantID = $this->plant->ID;
        $field = $this->findField($this->getJson("terms/$plantID/schema")['schema']['fields'], 'TypeID');
        $this->assertTrue($field['lazyLoad']);
        $expected = TaxonomyApiTestCase::API . "/terms/$plantID/field/TypeID/search";
        $this->assertStringEndsWith($expected, $field['optionUrl']);
        $body = $this->getJson('terms/new/schema');
        $field = $this->findField($body['schema']['fields'], 'TypeID');
        $this->assertStringEndsWith(TaxonomyApiTestCase::API . '/terms/new/field/TypeID/search', $field['optionUrl']);
        $this->assertStringEndsWith(TaxonomyApiTestCase::API . '/terms', $body['schema']['action']);
    }

    public function testSearchFiltersByCanViewAndNeedsAToken(): void
    {
        $this->logInAsTaxonomyUser(['TAXONOMYTERM_EDIT']);
        $url = 'terms/' . $this->plant->ID . '/field/TypeID/search?term=';
        $headers = ['X-SecurityID' => $this->getSecurityToken()];
        $biology = $this->getJson($url . 'Bio', 200, $headers);
        $this->assertSame([['value' => $this->type->ID, 'label' => 'Biology']], $biology);
        $this->assertSame(['Zoology'], array_column($this->getJson($url . 'Zoo', 200, $headers), 'label'));
        $this->assertSame([], $this->getJson($url . 'Hid', 200, $headers));
        $this->getJson($url . 'Bio', 400);
        $this->getJson($url . 'Bio', 400, ['X-SecurityID' => 'wrong']);
    }
}
