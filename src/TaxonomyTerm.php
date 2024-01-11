<?php

namespace SilverStripe\Taxonomy;

use SilverStripe\ORM\HasManyList;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows;
use SilverStripe\ORM\Hierarchy\Hierarchy;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\NumericField;
use SilverStripe\Security\Permission;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\PermissionProvider;

/**
 * Represents a single taxonomy term. Can be re-ordered in the CMS, and the default sorting is to use the order as
 * specified in the CMS.
 *
 * @property string $Name
 * @property int $ParentID
 * @property int $Sort
 * @property int $TypeID
 * @package taxonomy
 * @method HasManyList<TaxonomyTerm> Children()
 * @method TaxonomyTerm Parent()
 * @method TaxonomyType Type()
 */
class TaxonomyTerm extends DataObject implements PermissionProvider
{
    private static $table_name = 'TaxonomyTerm';

    private static $db = array(
        'Name' => 'Varchar(255)',
        'Sort' => 'Int'
    );

    private static $has_many = array(
        'Children' => TaxonomyTerm::class
    );

    private static $has_one = array(
        'Parent' => TaxonomyTerm::class,
        'Type' => TaxonomyType::class
    );

    private static $extensions = array(
        Hierarchy::class
    );

    private static $casting = array(
        'TaxonomyName' => 'Text'
    );

    private static $default_sort = 'Sort';

    private static $summary_fields = array(
        'Name' => 'Name',
        'Type.Name' => 'Type'
    );

    private static $type_inheritance_enabled = true;

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // For now moving taxonomy terms is not supported.
        $fields->removeByName('ParentID');
        $fields->removeByName('Sort');

        // Child taxonomy terms don't need to choose a type, it is inherited
        if ($this->config()->get('type_inheritance_enabled') && $this->getTaxonomy() !== $this) {
            $fields->removeByName('TypeID');
        }

        $childrenGrid = $fields->dataFieldByName('Children');
        if ($childrenGrid) {
            $deleteAction = $childrenGrid->getConfig()->getComponentByType(GridFieldDeleteAction::class);
            $addExistingAutocompleter = $childrenGrid
                ->getConfig()
                ->getComponentByType(GridFieldAddExistingAutocompleter::class);

            $childrenGrid->getConfig()->removeComponent($addExistingAutocompleter);
            $childrenGrid->getConfig()->removeComponent($deleteAction);
            $childrenGrid->getConfig()->addComponent(new GridFieldDeleteAction(false));

            // Setup sorting of TaxonomyTerm siblings, and fall back to a manual NumericField if no sorting is possible
            if (class_exists(GridFieldOrderableRows::class)) {
                $childrenGrid->getConfig()->addComponent(GridFieldOrderableRows::create('Sort'));
            } elseif (class_exists(GridFieldSortableRows::class)) {
                $childrenGrid->getConfig()->addComponent(new GridFieldSortableRows('Sort'));
            } else {
                $fields->addFieldToTab(
                    'Root.Main',
                    NumericField::create('Sort', 'Sort Order')
                        ->setDescription(
                            'Enter a whole number to sort this term among siblings (0 is first in the list)'
                        )
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
        if (!$this->config()->get('type_inheritance_enabled')) {
            if ($this->Type() && $this->Type()->exists()) {
                return $this->Type()->Name;
            }

            return '';
        }

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
            $term->delete();
        }
    }

    /**
     * Set the "type" relationship for this item
     *
     * {@inheritDoc}
     */
    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // Write the parent's type to the current term
        if (
            $this->config()->get('type_inheritance_enabled')
            && $this->Parent()->exists()
            && $this->Parent()->Type()->exists()
        ) {
            $this->TypeID = $this->Parent()->Type()->ID;
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

        if (!$this->config()->get('type_inheritance_enabled')) {
            return;
        }

        // Write the current term's type to all children
        foreach ($this->Children() as $term) {
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
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        return Permission::check('TAXONOMYTERM_EDIT');
    }

    public function canDelete($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        return Permission::check('TAXONOMYTERM_DELETE');
    }

    public function canCreate($member = null, $context = array())
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        return Permission::check('TAXONOMYTERM_CREATE');
    }

    public function providePermissions()
    {
        return array(
            'TAXONOMYTERM_EDIT' => array(
                'name' => _t(
                    __CLASS__ . '.EditPermissionLabel',
                    'Edit a taxonomy term'
                ),
                'category' => _t(
                    __CLASS__ . '.Category',
                    'Taxonomy terms'
                ),
            ),
            'TAXONOMYTERM_DELETE' => array(
                'name' => _t(
                    __CLASS__ . '.DeletePermissionLabel',
                    'Delete a taxonomy term and all nested terms'
                ),
                'category' => _t(
                    __CLASS__ . '.Category',
                    'Taxonomy terms'
                ),
            ),
            'TAXONOMYTERM_CREATE' => array(
                'name' => _t(
                    __CLASS__ . '.CreatePermissionLabel',
                    'Create a taxonomy term'
                ),
                'category' => _t(
                    __CLASS__ . '.Category',
                    'Taxonomy terms'
                ),
            )
        );
    }
}
