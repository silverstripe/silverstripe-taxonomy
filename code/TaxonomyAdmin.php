<?php

/**
* Management interface for Taxonomies, TaxonomyTerms and TaxonomyTypes
*
* @package taxonomy
*/
class TaxonomyAdmin extends ModelAdmin
{
    private static $url_segment = 'taxonomy';

    private static $managed_models = array('TaxonomyTerm', 'TaxonomyType');

    private static $menu_title = 'Taxonomies';

    private static $menu_icon = "taxonomy/images/tag.png";

    /**
     * If terms are the models being managed, filter for only top-level terms - no children
     *
     * @return SS_List
     */
    public function getList()
    {
        if ($this->modelClass === 'TaxonomyTerm') {
            $list = parent::getList();
            return $list->filter('ParentID', '0');
        }
        return parent::getList();
    }

    public function getEditForm($id = null, $fields = null)
    {
        if ($this->modelClass !== 'TaxonomyTerm') {
            return parent::getEditForm($id, $fields);
        }

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
