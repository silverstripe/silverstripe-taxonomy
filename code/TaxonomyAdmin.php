<?php

/**
* Management interface for Taxonomies and TaxonomyTerms
*
* @package taxonomy
*/
class TaxonomyAdmin extends ModelAdmin
{
    private static $url_segment = 'taxonomy';

    private static $managed_models = array('TaxonomyTerm');

    private static $menu_title = 'Taxonomies';
    
    private static $menu_icon = "taxonomy/images/tag.png";

    public function getList()
    {
        $list = parent::getList();
        return $list->filter('ParentID', '0');
    }

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        /** @var GridField $gf */
        $gf = $form->Fields()->dataFieldByName($this->sanitiseClassName($this->modelClass));

        // Setup sorting of TaxonomyTerm siblings, if a suitable module is included
        if (class_exists('GridFieldOrderableRows')) {
            $gf->getConfig()->addComponent(new GridFieldOrderableRows('Sort'));
        } elseif (class_exists('GridFieldSortableRows')) {
            $gf->getConfig()->addComponent(new GridFieldSortableRows('Sort'));
        }

        return $form;
    }
}
