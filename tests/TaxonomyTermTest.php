<?php

class TaxonomyTermTest extends SapphireTest
{
    protected static $fixture_file = 'TaxonomyTermTest.yml';

    public function testGetTaxonomy()
    {
        // Top level
        $this->assertEquals($this->objFromFixture('TaxonomyTerm', 'plant')->getTaxonomy()->Name, 'Plant');
        // Second level
        $this->assertEquals($this->objFromFixture('TaxonomyTerm', 'vegetable')->getTaxonomy()->Name, 'Plant');
        // Third level
        $this->assertEquals($this->objFromFixture('TaxonomyTerm', 'carrot')->getTaxonomy()->Name, 'Plant');
    }

    public function testRecursiveDeleteFromTopLevel()
    {
        $this->objFromFixture('TaxonomyTerm', 'plant')->delete();

        $this->assertEquals(
            TaxonomyTerm::get()->filter(array('Name' => 'Carrot'))->Count(),
            0,
            "Removing top level term removes all children"
        );
    }

    public function testHierarchy()
    {
        $plant = $this->objFromFixture('TaxonomyTerm', 'plant');
        $vegetable = $this->objFromFixture('TaxonomyTerm', 'vegetable');
        $carrot = $this->objFromFixture('TaxonomyTerm', 'carrot');

        $this->assertEquals(2, $plant->Children()->Count());
        $this->assertEquals($plant->ID, $vegetable->Parent()->ID);
        $this->assertEquals(1, $vegetable->Children()->Count());
        $this->assertEquals($vegetable->ID, $carrot->Parent()->ID);
        $this->assertEquals(0, $carrot->Children()->Count());
    }

    public function testSorting()
    {
        $plant = $this->objFromFixture('TaxonomyTerm', 'plant');
        $vegetable = $this->objFromFixture('TaxonomyTerm', 'vegetable');
        $fruit = $this->objFromFixture('TaxonomyTerm', 'fruit');

        $this->assertEquals($fruit->ID, $plant->Children()->first()->ID);
        $this->assertEquals($vegetable->ID, $plant->Children()->last()->ID);
    }

    public function testTypeIsInheritedByChildren()
    {
        $this->assertSame('Beverage', $this->objFromFixture('TaxonomyTerm', 'fizzy')->getTaxonomyType());

        $this->assertSame('Beverage', $this->objFromFixture('TaxonomyTerm', 'lemonade')->getTaxonomyType());
    }

    public function testTypeIsWrittenToChildren()
    {
        $plant = $this->objFromFixture('TaxonomyTerm', 'plant');
        $beverageType = $this->objFromFixture('TaxonomyType', 'beverage');

        $plant->TypeID = $beverageType->ID;
        $plant->write();

        // Direct child
        $this->assertSame('Beverage', $this->objFromFixture('TaxonomyTerm', 'vegetable')->Type()->Name);

        // Grand child
        $this->assertSame('Beverage', $this->objFromFixture('TaxonomyTerm', 'carrot')->Type()->Name);
    }
}
