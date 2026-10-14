<?php

namespace SilverStripe\Taxonomy\Tests\Api;

use SilverStripe\Taxonomy\TaxonomyTerm;
use SilverStripe\Taxonomy\TaxonomyType;

// The composer test namespace maps to tests/, so the autoloader cannot find this base class
require_once __DIR__ . '/TaxonomyApiTestCase.php';

class TaxonomyApiMultiValueTest extends TaxonomyApiTestCase
{
    protected static $required_extensions = [
        TaxonomyTerm::class => [HideRecordExtension::class, RelatedTypesExtension::class],
        TaxonomyType::class => [HideRecordExtension::class],
    ];

    public function testPatchSavesEveryValueOfAMultiValueField(): void
    {
        $this->logInWithPermission('ADMIN');
        $geology = TaxonomyType::create(['Name' => 'Geology']);
        $geology->write();
        $zoology = TaxonomyType::create(['Name' => 'Zoology']);
        $zoology->write();
        $ids = [$this->type->ID, $geology->ID, $zoology->ID];
        $response = $this->sendJson('PATCH', 'terms/' . $this->plant->ID, ['RelatedTypes' => $ids], 200);
        $this->assertEqualsCanonicalizing($ids, $response['RelatedTypes']);
        $saved = TaxonomyTerm::get_by_id($this->plant->ID)->RelatedTypes()->column('ID');
        $this->assertEqualsCanonicalizing($ids, $saved);
        $this->sendJson('PATCH', 'terms/' . $this->plant->ID, ['RelatedTypes' => [$geology->ID]], 200);
        $this->assertSame([$geology->ID], TaxonomyTerm::get_by_id($this->plant->ID)->RelatedTypes()->column('ID'));
        $this->sendJson('PATCH', 'terms/' . $this->plant->ID, ['RelatedTypes' => []], 200);
        $this->assertSame([], TaxonomyTerm::get_by_id($this->plant->ID)->RelatedTypes()->column('ID'));
    }
}
