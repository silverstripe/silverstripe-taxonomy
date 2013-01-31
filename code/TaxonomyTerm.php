<?php

class TaxonomyTerm extends DataObject {
	public static $db = array(
		'Name' => 'Varchar(255)'
	);

	public static $has_many = array(
		'Children' => 'TaxonomyTerm'
	);

	public static $has_one = array(
		'Parent' => 'TaxonomyTerm'
	);
}
