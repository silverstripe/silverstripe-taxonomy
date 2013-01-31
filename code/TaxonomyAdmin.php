<?php
/**
* Management interface for Taxonomies and TaxonomyTerms
*
* @package taxonomy
*/

class TaxonomyAdmin extends ModelAdmin {

	static $url_segment = 'taxonomy';

	public static $managed_models = array('TaxonomyTerm');

	static $menu_title = 'Taxonomies';
}
