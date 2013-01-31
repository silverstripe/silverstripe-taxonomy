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

	public function canView($record = null) {
		return true;
	}

	public function canEdit($record = null) {
		return Permission::check('TAXONOMY_EDIT');
 	}

	public function canDelete($record = null) {
		return Permission::check('TAXONOMY_DELETE');
	}

	public function canCreate($record = null) {
		return Permission::check('TAXONOMY_CREATE');
	}

	public function providePermissions() {
		return array(
			'TAXONOMY_EDIT' => array(
				'name' => 'Edit a taxonomy',
				'category' => 'Taxonomy'
			),
			'TAXONOMY_DELETE' => array(
				'name' => 'Delete a taxonomy and all terms within',
				'category' => 'Taxonomy'
			),
			'TAXONOMY_CREATE' => array(
				'name' => 'Create a taxonomy',
				'category' => 'Taxonomy'
			)
		);
	}

}
