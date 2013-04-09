<?php
/**
* Management interface for Taxonomies and TaxonomyTerms
*
* @package taxonomy
*/

class TaxonomyAdmin extends ModelAdmin {

	private static $url_segment = 'taxonomy';

	private static $managed_models = array('TaxonomyTerm');

	private static $menu_title = 'Taxonomies';

	public function getList() {
		$list = parent::getList();
		return $list->filter('ParentID', '0');
	}

}
