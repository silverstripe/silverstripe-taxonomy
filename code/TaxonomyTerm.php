<?php

class TaxonomyTerm extends DataObject implements PermissionProvider {
	public static $db = array(
		'Name' => 'Varchar(255)'
	);

	public static $has_many = array(
		'Children' => 'TaxonomyTerm'
	);

	public static $has_one = array(
		'Parent' => 'TaxonomyTerm'
	);

	/**
	 * Validate the owner object - check for existence of infinite loops.
	 * Copied mostly from Hierarchy.php.
	 */
	protected function validate() {
		$validationResult = parent::validate();

		// The object is new, won't be looping.
		if (!$this->ID) return $validationResult;
		// The object has no parent, won't be looping.
		if (!$this->ParentID) return $validationResult;
		// The parent has not changed, skip the check for performance reasons.
		if (!$this->isChanged('ParentID')) return $validationResult;

		// Walk the hierarchy upwards until we reach the top, or until we reach the originating node again.
		$node = $this;
		while($node) {
			if ($node->ParentID==$this->ID) {
				// Hierarchy is looping.
				$validationResult->error(
					_t(
						'Hierarchy.InfiniteLoopNotAllowed',
						'Infinite loop found within the "{type}" hierarchy. Please change the parent to resolve this',
						'First argument is the class that makes up the hierarchy.',
						array('type' => $this->class)
					),
					'INFINITE_LOOP'
				);
				break;
			}
			$node = $node->ParentID ? $node->Parent() : null;
		}

		return $validationResult;
	}

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$controller = Controller::curr();

		if (!$controller) user_error('Something went wrong, controller is unavailable.', E_USER_ERROR);

		if ($controller->request->param('ID')==='new') {
			// Hide parent selection when adding new items - populated automatically.
			$fields->removeByName('ParentID');
		} else if ($this->ParentID==0) {
			// Hide parent selection on the top level term (Taxonomy cannot become a leaf node)
			$fields->removeByName('ParentID');
		} else {
			// Make the Parent field nicer by pre-filtering and adding descriptions.
			$fields->removeByName('ParentID');

			$currentTaxonomy = $this->getTaxonomy();
			$termArray = array();
			$terms = TaxonomyTerm::get()->sort('Name');
			foreach ($terms as $term) {
				// Disallow making the term a parent of itself.
				if ($this->ID == $term->ID) continue;

				$termTaxonomy = $term->getTaxonomy();

				// Disallow moving between taxonomies.
				if ($currentTaxonomy->ID != $termTaxonomy->ID) continue;

				// Augment the name with addiontional information for top level node.
				if ($term->ParentID) {
					$termArray[$term->ID] = "$term->Name";
				} else {
					$termArray[$term->ID] = "$term->Name (top level term)";
				}
			}

			$fields->addFieldToTab('Root.Main', new DropdownField('ParentID', 'Parent', $termArray));
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
				'name' => 'Edit a taxonomy term',
				'category' => 'Taxonomy terms',
			),
			'TAXONOMYTERM_DELETE' => array(
				'name' => 'Delete a taxonomy term and all nested terms',
				'category' => 'Taxonomy terms',
			),
			'TAXONOMYTERM_CREATE' => array(
				'name' => 'Create a taxonomy term',
				'category' => 'Taxonomy terms'
			)
		);
	}

}
