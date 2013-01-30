<?php

class TaxonomyTerm extends DataObject {
	static $db = array(
		'Name' => 'Varchar(255)'
	);

	static $has_many = array(
		'Terms' => 'TaxonomyTerm'
	);

	static $has_one = array(
		'Parent' => 'TaxonomyTerm',
		'Taxonomy' => 'Taxonomy'
	);
}