<?php

namespace SilverStripe\Taxonomy\Tests;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Taxonomy\TaxonomyTerm;
use SilverStripe\Taxonomy\TaxonomyType;

class TaxonomyTermTest extends SapphireTest
{
    protected static $fixture_file = 'TaxonomyTermTest.yml';

    protected function setUp()
    {
        // Type inheritance needs to be disabled while we generate our objects from our fixtures. Otherwise, all of
        // the Types will be written to our child Terms, and this will completely invalidate some of our test
        // coverage. EG: You can't test that a child inherits its Parents Type if that child has had a Type written
        // to it as part of our fixture generation
        // Cannot use Config::withState() here as it messes with parent::setUp()
        TaxonomyTerm::config()->set('type_inheritance_enabled', false);

        parent::setUp();

        // Set our config back to the default
        TaxonomyTerm::config()->set('type_inheritance_enabled', true);
    }

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

    public function testTypeIsNotInheritedByChildren()
    {
        Config::withConfig(function () {
            TaxonomyTerm::config()->set('type_inheritance_enabled', false);

            $term = $this->objFromFixture(TaxonomyTerm::class, 'lemonade');

            $this->assertSame('Beverage', $this->objFromFixture(TaxonomyTerm::class, 'fizzy')->getTaxonomyType());

            $this->assertSame('', (string) $this->objFromFixture(TaxonomyTerm::class, 'lemonade')->getTaxonomyType());
        });
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

    public function testTypeIsNotWrittenToChildren()
    {
        Config::withConfig(function () {
            TaxonomyTerm::config()->set('type_inheritance_enabled', false);

            $plant = $this->objFromFixture(TaxonomyTerm::class, 'plant');
            $beverageType = $this->objFromFixture(TaxonomyType::class, 'beverage');

            $plant->TypeID = $beverageType->ID;
            $plant->write();

            // Direct child
            $this->assertSame('', (string) $this->objFromFixture(TaxonomyTerm::class, 'vegetable')->Type()->Name);

            // Grand child
            $this->assertSame('', (string) $this->objFromFixture(TaxonomyTerm::class, 'carrot')->Type()->Name);
        });
    }

    public function testTypeIsTakenFromParent()
    {
        $plant = $this->objFromFixture(TaxonomyTerm::class, 'plant');

        $tree = TaxonomyTerm::create(['Name' => 'Tree']);
        $tree->ParentID = $plant->ID;
        $tree->write();

        //reload item from DB to see if the change was actually written to DB
        $tree2 = TaxonomyTerm::get()->byID($tree->ID);

        $this->assertEquals(
            'Food',
            $tree2->Type()->Name,
            'A new child term should automatically get the parent\'s type'
        );
    }

    public function testTypeIsNotTakenFromParent()
    {
        Config::withConfig(function () {
            TaxonomyTerm::config()->set('type_inheritance_enabled', false);

            $plant = $this->objFromFixture(TaxonomyTerm::class, 'plant');

            $tree = TaxonomyTerm::create(['Name' => 'Tree']);
            $tree->ParentID = $plant->ID;
            $tree->write();

            //reload item from DB to see if the change was actually written to DB
            $tree2 = TaxonomyTerm::get()->byID($tree->ID);

            $this->assertEquals(
                '',
                (string) $tree2->Type()->Name,
                'A new child term should not automatically get the parent\'s type'
            );
        });
    }
}
