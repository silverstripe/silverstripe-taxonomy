<?php

/**
 * Represents a single taxonomy term. Can be re-ordered in the CMS, and the default sorting is to use the order as
 * specified in the CMS.
 *
 * @method TaxonomyTerm Parent()
 * @package taxonomy
 */
class TaxonomyTerm extends DataObject implements PermissionProvider
{
    private static $db = array(
        'Name' => 'Varchar(255)',
        'Sort' => 'Int'
    );

    private static $has_many = array(
        'Children' => 'TaxonomyTerm'
    );

    private static $has_one = array(
        'Parent' => 'TaxonomyTerm',
        'Type' => 'TaxonomyType'
    );

    private static $extensions = array(
        'Hierarchy'
    );

    private static $casting = array(
        'TaxonomyName' => 'Text'
    );

    private static $default_sort = 'Sort';

    private static $summary_fields = array(
        'Name' => 'Name',
        'Type.Name' => 'Type'
    );

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // For now moving taxonomy terms is not supported.
        $fields->removeByName('ParentID');
        $fields->removeByName('Sort');

        // Child taxonomy terms don't need to choose a type, it is inherited
        if ($this->getTaxonomy() !== $this) {
            $fields->removeByName('TypeID');
        }

        $childrenGrid = $fields->dataFieldByName('Children');
        if ($childrenGrid) {
            $deleteAction = $childrenGrid->getConfig()->getComponentByType('GridFieldDeleteAction');
            $addExistingAutocompleter = $childrenGrid->getConfig()->getComponentByType('GridFieldAddExistingAutocompleter');

            $childrenGrid->getConfig()->removeComponent($addExistingAutocompleter);
            $childrenGrid->getConfig()->removeComponent($deleteAction);
            $childrenGrid->getConfig()->addComponent(new GridFieldDeleteAction(false));

            // Setup sorting of TaxonomyTerm siblings, and fall back to a manual NumericField if no sorting is possible
            if (class_exists('GridFieldOrderableRows')) {
                $childrenGrid->getConfig()->addComponent(new GridFieldOrderableRows('Sort'));
            } elseif (class_exists('GridFieldSortableRows')) {
                $childrenGrid->getConfig()->addComponent(new GridFieldSortableRows('Sort'));
            } else {
                $fields->addFieldToTab('Root.Main', NumericField::create('Sort', 'Sort Order')
                    ->setDescription('Enter a whole number to sort this term among siblings (0 is first in the list)')
                );
            }
        }

        return $fields;
    }

    /**
     * Get the top-level ancestor which doubles as the taxonomy.
     *
     * @return TaxonomyTerm
     */
    public function getTaxonomy()
    {
        return ($parent = $this->Parent()) && $parent->exists()
            ? $parent->getTaxonomy()
            : $this;
    }

    /**
     * Gets the name of the top-level ancestor
     *
     * @return string
     */
    public function getTaxonomyName()
    {
        return $this->getTaxonomy()->Name;
    }

    /**
     * Get the type of the top-level ancestor if it is set
     *
     * @return string
     */
    public function getTaxonomyType()
    {
        $taxonomy = $this->getTaxonomy();
        if ($taxonomy->Type() && $taxonomy->Type()->exists()) {
            return $taxonomy->Type()->Name;
        }
        return '';
    }

    /**
     * Delete all associated children when a taxonomy term is deleted
     *
     * {@inheritDoc}
     */
    public function onBeforeDelete()
    {
        parent::onBeforeDelete();

        foreach ($this->Children() as $term) {
            /** @var TaxonomyTerm $term */
            $term->delete();
        }
    }

    /**
     * Set the "type" relationship for children to that of the parent (recursively)
     *
     * {@inheritDoc}
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        // Write the parent's type to the current term
        if ($this->Parent()->exists() && $this->Parent()->Type()->exists()) {
            $this->TypeID = $this->Parent()->Type()->ID;
        }

        // Write the current term's type to all children
        foreach ($this->Children() as $term) {
            /** @var TaxonomyTerm $term */
            $term->TypeID = $this->Type()->ID;
            $term->write();
        }
    }

    public function canView($member = null)
    {
        return true;
    }

    public function canEdit($member = null)
    {
        return Permission::check('TAXONOMYTERM_EDIT');
    }

    public function canDelete($member = null)
    {
        return Permission::check('TAXONOMYTERM_DELETE');
    }

    public function canCreate($member = null)
    {
        return Permission::check('TAXONOMYTERM_CREATE');
    }

    public function providePermissions()
    {
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
