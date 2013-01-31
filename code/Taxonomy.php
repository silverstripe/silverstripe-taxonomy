<?php

class Taxonomy extends DataObject {
	public static $db = array(
		'Name' => 'Varchar(255)'
	);

	public static $has_many = array(
		'Terms' => 'TaxonomyTerm'
	);
}
