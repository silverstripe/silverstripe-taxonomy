<?php

class TaxonomyTermTest extends SapphireTest {
	
	protected static $fixture_file = 'TaxonomyTermTest.yml';
	
	public function testGetTaxonomy() {
		// Top level
		$this->assertEquals($this->objFromFixture('TaxonomyTerm', 'Plant')->getTaxonomy()->Name, 'Plant');
		// Second level
		$this->assertEquals($this->objFromFixture('TaxonomyTerm', 'Vegetable')->getTaxonomy()->Name, 'Plant');
		// Third level
		$this->assertEquals($this->objFromFixture('TaxonomyTerm', 'Carrot')->getTaxonomy()->Name, 'Plant');
	}

	public function testRecursiveDeleteFromTopLevel() {
		$this->objFromFixture('TaxonomyTerm', 'Plant')->delete();

		$this->assertEquals(
			TaxonomyTerm::get()->filter(array('Name'=>'Carrot'))->Count(),
			0,
			"Removing top level term removes all children"
		);
	}

	public function testHierarchy() {
		$plant = $this->objFromFixture('TaxonomyTerm', 'Plant');
		$vegetable = $this->objFromFixture('TaxonomyTerm', 'Vegetable');
		$carrot = $this->objFromFixture('TaxonomyTerm', 'Carrot');
		
		$this->assertEquals(2, $plant->Children()->Count());
		$this->assertEquals($plant->ID, $vegetable->Parent()->ID);
		$this->assertEquals(1, $vegetable->Children()->Count());
		$this->assertEquals($vegetable->ID, $carrot->Parent()->ID);
		$this->assertEquals(0, $carrot->Children()->Count());
	}

	public function testSorting() {
		$plant = $this->objFromFixture('TaxonomyTerm', 'Plant');
		$vegetable = $this->objFromFixture('TaxonomyTerm', 'Vegetable');
		$fruit = $this->objFromFixture('TaxonomyTerm', 'Fruit');

		$this->assertEquals($fruit->ID, $plant->Children()->first()->ID);
		$this->assertEquals($vegetable->ID, $plant->Children()->last()->ID);
	}
}
