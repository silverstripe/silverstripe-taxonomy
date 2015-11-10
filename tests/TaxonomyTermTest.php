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
	
	/**
	 * Tests correct arguments, get and create a taxonomy
	 */
	public function testGetOrCreate() {
		try {
			$taxonomy = TaxonomyTerm::get_or_create(1, 'string');
			$this->fail('An expected exception has not been raised.');
		} catch (InvalidArgumentException $expected) {}
		
		$fruit = TaxonomyTerm::get_or_create(array('Name' => 'Fruit'),
											 array('Name' => 'Fruit', 'ParentID' => 0));
		$this->assertEquals($fruit->Sort, 1);
		
		$broccoli = TaxonomyTerm::get_or_create(array('Name' => 'Broccoli'),
												array('Name' => 'Broccoli', 'ParentID' => 0));
		$this->assertEquals($broccoli->Name, 'Broccoli');
	}
}
