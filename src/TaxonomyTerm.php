<?php

namespace SilverStripe\Taxonomy;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\HasManyList;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows;
use SilverStripe\ORM\Hierarchy\Hierarchy;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\SearchableMultiDropdownField;
use SilverStripe\Security\Permission;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBForeignKey;
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

    private static string $sort_field = 'Sort';

    private static $summary_fields = array(
        'Name' => 'Name',
        'Type.Name' => 'Type'
    );

    private static $type_inheritance_enabled = true;

    /**
     * Css class attached to icons in a CMSMain tree. Also supports font-icon set.
     * Overrides cms_icon for most purposes if set on the same class
     */
    private static string $cms_icon_class = 'font-icon-tag';

    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            // For now moving taxonomy terms is not supported. We also want to entirely rebuild the Children field.
            $fields->removeByName(['ParentID', 'Sort', 'Children']);

            // Child taxonomy terms don't need to choose a type, it is inherited
            if ($this->config()->get('type_inheritance_enabled') && $this->getTaxonomy() !== $this) {
                $fields->removeByName('TypeID');
            }

            $childrenConfig = GridFieldConfig_RelationEditor::create();
            $childrenGrid = GridField::create(
                'Children',
                $this->fieldLabel('Children'),
                $this->Children(),
                $childrenConfig
            );
            $deleteAction = $childrenConfig->getComponentByType(GridFieldDeleteAction::class);
            $addExistingAutocompleter = $childrenConfig->getComponentByType(GridFieldAddExistingAutocompleter::class);
            $childrenConfig->removeComponent($addExistingAutocompleter);
            $childrenConfig->removeComponent($deleteAction);
            $childrenConfig->addComponent(GridFieldDeleteAction::create(false));

            // Setup sorting of TaxonomyTerm siblings, and fall back to a manual NumericField if no sorting is possible
            if (class_exists(GridFieldOrderableRows::class)) {
                $childrenConfig->addComponent(GridFieldOrderableRows::create('Sort'));
            } elseif (class_exists(GridFieldSortableRows::class)) {
                $childrenConfig->addComponent(GridFieldSortableRows::create('Sort'));
            } else {
                $fields->addFieldToTab(
                    'Root.Main',
                    NumericField::create('Sort', 'Sort Order')
                        ->setDescription(
                            'Enter a whole number to sort this term among siblings (0 is first in the list)'
                        )
                );
            }
            $fields->addFieldToTab('Root.Children', $childrenGrid);
        });

        return parent::getCMSFields();
    }

    public function scaffoldFormFieldForHasMany(
        string $relationName,
        ?string $fieldTitle,
        DataObject $ownerRecord,
        bool &$includeInOwnTab
    ): FormField {
        $includeInOwnTab = false;
        return $this->scaffoldFormFieldForManyRelation($relationName, $fieldTitle);
    }

    public function scaffoldFormFieldForManyMany(
        string $relationName,
        ?string $fieldTitle,
        DataObject $ownerRecord,
        bool &$includeInOwnTab
    ): FormField {
        $includeInOwnTab = false;
        return $this->scaffoldFormFieldForManyRelation($relationName, $fieldTitle);
    }

    private function scaffoldFormFieldForManyRelation(string $relationName, ?string $fieldTitle): FormField
    {
        $list = static::get();
        $field = SearchableMultiDropdownField::create($relationName, $fieldTitle, $list, labelField: 'Name');
        // Use the same lazyload threshold has_one relations use
        $threshold = DBForeignKey::config()->get('dropdown_field_threshold');
        $overThreshold = $list->count() > $threshold;
        $field->setIsLazyLoaded($overThreshold)->setLazyLoadLimit($threshold);
        return $field;
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
    protected function onBeforeDelete()
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
    protected function onAfterWrite()
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
