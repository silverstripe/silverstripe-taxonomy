<?php

class TaxonomyTermTest extends SapphireTest {
	static $fixture_file = 'taxonomy/tests/TaxonomyTermTest.yml';
	
	function testGetTaxonomy() {
		// Top level
		$this->assertEquals($this->objFromFixture('TaxonomyTerm', 'Plant')->getTaxonomy()->Name, 'Plant');
		// Second level
		$this->assertEquals($this->objFromFixture('TaxonomyTerm', 'Vegetable')->getTaxonomy()->Name, 'Plant');
		// Third level
		$this->assertEquals($this->objFromFixture('TaxonomyTerm', 'Carrot')->getTaxonomy()->Name, 'Plant');
	}

	function testRecursiveDeleteFromTopLevel() {
		$this->objFromFixture('TaxonomyTerm', 'Plant')->delete();

		$this->assertEquals(
			TaxonomyTerm::get()->filter(array('Name'=>'Carrot'))->Count(),
			0,
			"Removing top level term removes all children"
		);
	}
}
