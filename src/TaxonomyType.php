<?php

namespace SilverStripe\Taxonomy;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Validation\CompositeValidator;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Security\Permission;

/**
 * Represents a type of taxonomy, which can be configured in the CMS. This can be used to group similar
 * taxonomy terms together.
 *
 * @property string $Name
 * @method HasManyList<TaxonomyTerm> Terms()
 */
class TaxonomyType extends DataObject
{
    private static $table_name = 'TaxonomyType';

    private static $db = array(
        'Name' => 'Varchar(255)'
    );

    private static array $has_many = [
        'Terms' => TaxonomyTerm::class . '.Type',
    ];

    private static array $indexes = [
        'Name' => true,
    ];

    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            // The terms are listed through the API, and the served form refuses a GridField
            $fields->removeByName('Terms');
        });
        return parent::getCMSFields();
    }

    public function canView($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        return Permission::check(['CMS_ACCESS_LeftAndMain', TaxonomyAdmin::getRequiredPermissions()], 'any', $member);
    }

    public function getCMSCompositeValidator(): CompositeValidator
    {
        $validator = parent::getCMSCompositeValidator();
        $requiredFields = RequiredFieldsValidator::create('Name');
        $requiredFields->setAllowWhitespaceOnly(false);
        $validator->addValidator($requiredFields);
        return $validator;
    }
}
