<?php
/**
 * Represents a type of taxonomy, which can be configured in the CMS. This can be used to group similar
 * taxonomy terms together.
 */
class TaxonomyType extends DataObject
{
    private static $db = array(
        'Name' => 'Varchar(255)'
    );
}
