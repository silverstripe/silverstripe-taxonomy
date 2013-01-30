<?php

class Taxonomy extends DataObject {
	static $db = array(
		'Name' => 'Varchar(255)'
	);

	static $has_many = array(
		'Terms' => 'TaxonomyTerm'
	);
}