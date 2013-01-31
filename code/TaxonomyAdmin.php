<?php
/**
* Management interface for Taxonomies and TaxonomyTerms
*
* @package taxonomy
*/

class TaxonomyAdmin extends ModelAdmin {

	public static $url_segment = 'taxonomy';

	public static $managed_models = array('TaxonomyTerm');

	public static $menu_title = 'Taxonomies';

	public function getList() {
		$list = parent::getList();
		return $list->filter('ParentID', '0');
	}

}
