<?php

namespace SilverStripe\Taxonomy\Tests\Api;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\SearchableMultiDropdownField;
use SilverStripe\Taxonomy\TaxonomyTerm;
use SilverStripe\Taxonomy\TaxonomyType;

/**
 * Adds a field that holds many values to the term form
 *
 * @extends Extension<TaxonomyTerm>
 */
class RelatedTypesExtension extends Extension implements TestOnly
{
    private static array $many_many = [
        'RelatedTypes' => TaxonomyType::class,
    ];

    protected function updateCMSFields(FieldList $fields): void
    {
        $fields->push(SearchableMultiDropdownField::create('RelatedTypes', 'Related types', TaxonomyType::get()));
    }
}
