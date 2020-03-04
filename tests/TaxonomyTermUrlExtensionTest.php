<?php

namespace SilverStripe\Taxonomy\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Taxonomy\TaxonomyTerm;
use SilverStripe\Taxonomy\Extensions\TaxonomyTermUrlExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Extensible;

class TaxonomyTermUrlExtensionTest extends SapphireTest
{
    protected $usesDatabase = true;
    protected static $required_extensions = [TaxonomyTerm::class => [TaxonomyTermUrlExtension::class]];

    public function testGeneratesUrl()
    {
        $term = new TaxonomyTerm();
        $term->Name = 'Test 1';
        $term->write();

        // Reload the model
        $term = DataObject::get_by_id(TaxonomyTerm::class, $term->ID);
        $this->assertNotNull($term);
        $this->assertEquals('Test 1', $term->Name);
        $this->assertEquals('test-1', $term->URLSegment);
    }

    public function testGeneratesUniqueUrls()
    {
        $term1 = new TaxonomyTerm();
        $term1->Name = 'Testing';
        $term1->write();

        $term2 = new TaxonomyTerm();
        $term2->Name = 'Testing'; // intentionally the same
        $term2->write();

        // Reload the model
        $term2 = DataObject::get_by_id(TaxonomyTerm::class, $term2->ID);
        $this->assertNotNull($term2);
        $this->assertEquals('Testing', $term2->Name);
        $this->assertEquals('testing-2', $term2->URLSegment);
    }

    public function testRespectsSuppliedUrl()
    {
        $term = new TaxonomyTerm();
        $term->Name = 'Test 1';
        $term->URLSegment = 'something supplied';
        $term->write();

        // Reload the model
        $term = DataObject::get_by_id(TaxonomyTerm::class, $term->ID);
        $this->assertNotNull($term);
        $this->assertEquals('Test 1', $term->Name);
        $this->assertEquals('something supplied', $term->URLSegment);

        // Check it doesn't overwrite if saved multiple times
        $term->Name = 'Test changed';
        $term->write();

        $term = DataObject::get_by_id(TaxonomyTerm::class, $term->ID);
        $this->assertNotNull($term);
        $this->assertEquals('Test changed', $term->Name);
        $this->assertEquals('something supplied', $term->URLSegment);
    }

    public function testGeneratesUniqueUrlIfSuppliedIsUsed()
    {
        $term1 = new TaxonomyTerm();
        $term1->Name = 'Test 1';
        $term1->URLSegment = 'supplied-test';
        $term1->write();

        $term2 = new TaxonomyTerm();
        $term2->Name = 'Test 2';
        $term2->URLSegment = 'supplied-test'; // intentionally the same
        $term2->write();

        // Reload the model
        $term2 = DataObject::get_by_id(TaxonomyTerm::class, $term2->ID);
        $this->assertNotNull($term2);
        $this->assertEquals('Test 2', $term2->Name);
        $this->assertEquals('supplied-test-2', $term2->URLSegment);
    }

    public function testGeneratesUrlWhenNameIsInvalid()
    {
        $term = new TaxonomyTerm();
        $term->Name = '##';
        $term->write();

        // Reload the model
        $term = DataObject::get_by_id(TaxonomyTerm::class, $term->ID);
        $this->assertNotNull($term);
        $this->assertEquals('##', $term->Name);
        $this->assertEquals('term-1', $term->URLSegment);

        $term = new TaxonomyTerm();
        $term->Name = ' ';
        $term->write();

        // Reload the model
        $term = DataObject::get_by_id(TaxonomyTerm::class, $term->ID);
        $this->assertNotNull($term);
        $this->assertEquals(' ', $term->Name);
        $this->assertEquals('term-2', $term->URLSegment);
    }
}
