<?php

namespace SilverStripe\Taxonomy\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Taxonomy\TaxonomyTerm;
use SilverStripe\View\Parsers\URLSegmentFilter;

/**
* Adds a URLSegment to TaxonomyTerm that gets
* generated from the name if not specified.
*
* @package taxonomy
*/
class TaxonomyTermUrlExtension extends DataExtension
{
    private static $db = array(
        'URLSegment' => 'Varchar(255)',
    );

    public function updateCMSFields(FieldList $fields)
    {
        $field = $fields->dataFieldByName('URLSegment');
        if ($field) {
            $field->setDescription(_t(
                __CLASS__ . '.LeaveBlankLabel',
                'Leave blank to generate from name'
            ));
            $fields->insertAfter('Name', $field);
        }
    }

    /**
     *  Set the URL segment allowing filtering by url slug
     *
     *  {@inheritDoc}
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        // Write the URLSegment to allow for Taxonomy navigation only if one is not supplied
        if ($this->owner->Name && $this->owner->URLSegment == null) {
            $filter = URLSegmentFilter::create();
            $filteredTitle = $filter->filter($this->owner->Name);

            // Fallback to generic name if path is empty (= no valid, convertable characters)
            if (!$filteredTitle || $filteredTitle == '-' || $filteredTitle == '-1') {
                $id = $this->owner->ID ? $this->owner->ID : 1;
                $filteredTitle = "term-$id";
            }

            $this->owner->setField('URLSegment', $filteredTitle);
        }
        // Ensure that this object has a non-conflicting URLSegment value.
        $count = 2;
        while (TaxonomyTerm::get()->filter(['URLSegment' => $this->owner->URLSegment])->exclude(array('ID' => $this->owner->ID))->exists()) {
            $this->owner->setField('URLSegment', preg_replace('/-[0-9]+$/', null, $this->owner->URLSegment) . '-' . $count);
            $count++;
        }
    }
}
