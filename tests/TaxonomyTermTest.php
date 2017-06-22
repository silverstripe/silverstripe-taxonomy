<?php

namespace SilverStripe\Taxonomy\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Taxonomy\TaxonomyTerm;
use SilverStripe\Taxonomy\TaxonomyType;

class TaxonomyTermTest extends SapphireTest
{
    protected static $fixture_file = 'TaxonomyTermTest.yml';

    public function testGetTaxonomy()
    {
        // Top level
        $this->assertEquals($this->objFromFixture(TaxonomyTerm::class, 'plant')->getTaxonomy()->Name, 'Plant');
        // Second level
        $this->assertEquals($this->objFromFixture(TaxonomyTerm::class, 'vegetable')->getTaxonomy()->Name, 'Plant');
        // Third level
        $this->assertEquals($this->objFromFixture(TaxonomyTerm::class, 'carrot')->getTaxonomy()->Name, 'Plant');
    }

    public function testRecursiveDeleteFromTopLevel()
    {
        $this->objFromFixture(TaxonomyTerm::class, 'plant')->delete();

        $this->assertEquals(
            TaxonomyTerm::get()->filter(array('Name' => 'Carrot'))->Count(),
            0,
            "Removing top level term removes all children"
        );
    }

    public function testHierarchy()
    {
        $plant = $this->objFromFixture(TaxonomyTerm::class, 'plant');
        $vegetable = $this->objFromFixture(TaxonomyTerm::class, 'vegetable');
        $carrot = $this->objFromFixture(TaxonomyTerm::class, 'carrot');

        $this->assertEquals(2, $plant->Children()->Count());
        $this->assertEquals($plant->ID, $vegetable->Parent()->ID);
        $this->assertEquals(1, $vegetable->Children()->Count());
        $this->assertEquals($vegetable->ID, $carrot->Parent()->ID);
        $this->assertEquals(0, $carrot->Children()->Count());
    }

    public function testSorting()
    {
        $plant = $this->objFromFixture(TaxonomyTerm::class, 'plant');
        $vegetable = $this->objFromFixture(TaxonomyTerm::class, 'vegetable');
        $fruit = $this->objFromFixture(TaxonomyTerm::class, 'fruit');

        $this->assertEquals($fruit->ID, $plant->Children()->first()->ID);
        $this->assertEquals($vegetable->ID, $plant->Children()->last()->ID);
    }

    public function testTypeIsInheritedByChildren()
    {
        $this->assertSame('Beverage', $this->objFromFixture(TaxonomyTerm::class, 'fizzy')->getTaxonomyType());

        $this->assertSame('Beverage', $this->objFromFixture(TaxonomyTerm::class, 'lemonade')->getTaxonomyType());
    }

    public function testTypeIsWrittenToChildren()
    {
        $plant = $this->objFromFixture(TaxonomyTerm::class, 'plant');
        $beverageType = $this->objFromFixture(TaxonomyType::class, 'beverage');

        $plant->TypeID = $beverageType->ID;
        $plant->write();

        // Direct child
        $this->assertSame('Beverage', $this->objFromFixture(TaxonomyTerm::class, 'vegetable')->Type()->Name);

        // Grand child
        $this->assertSame('Beverage', $this->objFromFixture(TaxonomyTerm::class, 'carrot')->Type()->Name);
    }
}
