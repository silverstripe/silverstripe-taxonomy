<?php

class Taxonomy extends DataObject implements PermissionProvider {
	public static $db = array(
		'Name' => 'Varchar(255)'
	);

	public static $has_many = array(
		'Terms' => 'TaxonomyTerm'
	);

	public function onBeforeDelete() {
		parent::onBeforeDelete();

		foreach($this->Terms() as $term) {
			$term->delete();
		}
	}

	public function canView($record = false) {
		return true;
	}

	public function canEdit($record = false) {
		return Permission::check('TAXONOMY_EDIT');
 	}

	public function canDelete() {
		return Permission::check('TAXONOMY_DELETE');
	}

	public function canCreate() {
		return Permission::check('TAXONOMY_CREATE');
	}

	public function providePermissions() {
		return array(
			'TAXONOMY_EDIT' => 'Edit a taxonomy',
			'TAXONOMY_DELETE' => 'Delete a taxonomy and all terms within',
			'TAXONOMY_CREATE' => 'Create a taxonomy',
		);
	}

}
