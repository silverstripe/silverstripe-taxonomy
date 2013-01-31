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

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$controller = Controller::curr();

		// Do not show parent selection when adding new items - populated automatically.
		if ($controller && $controller->request->param('ID')==='new') {
			$fields->removeByName('ParentID');
		}

		return $fields;
	}
}
