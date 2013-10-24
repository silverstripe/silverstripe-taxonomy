<?php

class TaxonomyTerm extends DataObject implements PermissionProvider {
	private static $db = array(
		'Name' => 'Varchar(255)'
	);

	private static $has_many = array(
		'Children' => 'TaxonomyTerm'
	);

	private static $has_one = array(
		'Parent' => 'TaxonomyTerm'
	);

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		// For now moving taxonomy terms is not supported.
		$fields->removeByName('ParentID');

		$childrenGrid = $fields->dataFieldByName('Children');
		if($childrenGrid) {
			$deleteAction = $childrenGrid->getConfig()->getComponentByType('GridFieldDeleteAction');
			$addExistingAutocompleter = $childrenGrid->getConfig()->getComponentByType('GridFieldAddExistingAutocompleter');

			$childrenGrid->getConfig()->removeComponent($addExistingAutocompleter);
			$childrenGrid->getConfig()->removeComponent($deleteAction);
			$childrenGrid->getConfig()->addComponent(new GridFieldDeleteAction(false));
		}

		return $fields;
	}

	/**
	 * Get the top-level ancestor which doubles as the taxonomy.
	 */
	public function getTaxonomy() {
		$object = $this;
		
		while($object->Parent() && $object->Parent()->exists()) {
			$object = $object->Parent();
		}
		
		return $object;
	}

	public function getTaxonomyName() {
		return $this->getTaxonomy()->Name;
	}

	public function onBeforeDelete() {
		parent::onBeforeDelete();

		foreach($this->Children() as $term) {
			$term->delete();
		}
	}

	public function canView($member = null) {
		return true;
	}

	public function canEdit($member = null) {
		return Permission::check('TAXONOMYTERM_EDIT');
	}

	public function canDelete($member = null) {
		return Permission::check('TAXONOMYTERM_DELETE');
	}

	public function canCreate($member = null) {
		return Permission::check('TAXONOMYTERM_CREATE');
	}

	public function providePermissions() {
		return array(
			'TAXONOMYTERM_EDIT' => array(
				'name' => _t(
					'TaxonomyTerm.EditPermissionLabel',
					'Edit a taxonomy term'
				),
				'category' => _t(
					'TaxonomyTerm.Category',
					'Taxonomy terms'
				),
			),
			'TAXONOMYTERM_DELETE' => array(
				'name' => _t(
					'TaxonomyTerm.DeletePermissionLabel',
					'Delete a taxonomy term and all nested terms'
				),
				'category' => _t(
					'TaxonomyTerm.Category',
					'Taxonomy terms'
				),
			),
			'TAXONOMYTERM_CREATE' => array(
				'name' => _t(
					'TaxonomyTerm.CreatePermissionLabel',
					'Create a taxonomy term'
				),
				'category' => _t(
					'TaxonomyTerm.Category',
					'Taxonomy terms'
				),
			)
		);
	}

}
