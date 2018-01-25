<?php

namespace SilverStripe\Taxonomy;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridField;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use SilverStripe\ORM\SS_List;
use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows;

/**
* Management interface for Taxonomies, TaxonomyTerms and TaxonomyTypes
*
* @package taxonomy
*/
class TaxonomyAdmin extends ModelAdmin
{
    private static $url_segment = 'taxonomy';

    private static $managed_models = array(TaxonomyTerm::class, TaxonomyType::class);

    private static $menu_title = 'Taxonomies';

    private static $menu_icon_class = 'font-icon-tags';
    /**
     * If terms are the models being managed, filter for only top-level terms - no children
     *
     * @return SS_List
     */
    public function getList()
    {
        if ($this->modelClass === TaxonomyTerm::class) {
            $list = parent::getList();
            return $list->filter('ParentID', '0');
        }
        return parent::getList();
    }

    public function getEditForm($id = null, $fields = null)
    {
        if ($this->modelClass !== TaxonomyTerm::class) {
            return parent::getEditForm($id, $fields);
        }

        $form = parent::getEditForm($id, $fields);

        /** @var GridField $gf */
        $gf = $form->Fields()->dataFieldByName($this->sanitiseClassName($this->modelClass));

        // Setup sorting of TaxonomyTerm siblings, if a suitable module is included
        if (class_exists(GridFieldOrderableRows::class)) {
            $gf->getConfig()->addComponent(GridFieldOrderableRows::create('Sort'));
        } elseif (class_exists(GridFieldSortableRows::class)) {
            $gf->getConfig()->addComponent(new GridFieldSortableRows('Sort'));
        }

        return $form;
    }
}
