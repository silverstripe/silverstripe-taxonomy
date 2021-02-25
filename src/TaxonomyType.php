<?php

namespace SilverStripe\Taxonomy;

use SilverStripe\ORM\DataObject;

/**
 * Represents a type of taxonomy, which can be configured in the CMS. This can be used to group similar
 * taxonomy terms together.
 *
 * @property string $Name
 */
class TaxonomyType extends DataObject
{
    private static $table_name = 'TaxonomyType';

    private static $db = array(
        'Name' => 'Varchar(255)'
    );
}
