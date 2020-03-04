<?php

namespace SilverStripe\Taxonomy\Controllers;

use Page;
use PageController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;

if (!class_exists(PageController::class)) {
    return;
}

/**
 * Class TaxonomyDirectoryController
 *
 * Controller for returning a list of pages tagged with a specific Taxonomy Term
 */
class TaxonomyDirectoryController extends PageController
{
    /**
     * The class (e.g. Page) that has a relation to TaxonomyTerms
     * The name of the relation on this class should be defined in $lookup_relation_field
     * @config
     */
    private static $directory_class = 'Page';

    /**
     * The name of the TaxonomyTerm relation and field to expect in the URL
     * e.g. Terms.Name or Tags.URLSegment
     * @config
     */
    private static $lookup_relation_field = 'Terms.Name';

    private static $allowed_actions = array(
        'index'
    );

    public function index(HTTPRequest $request)
    {
        $termString = $request->param('ID');

        $field = $this->config()->get('lookup_relation_field');
        $class = $this->config()->get('directory_class');
        $pages = DataObject::get($class)->filter([$field => $termString]);

        return $this->customise(new ArrayData(array(
            'Title' => $termString,
            'Term' => $termString,
            'Pages' => $pages,
            'Breadcrumbs' => $this->renderBreadcrumb($termString)
        )))->renderWith(array(__CLASS__, "Page"));
    }

    protected function renderBreadcrumb($termString)
    {
        $page = new Page();
        $page->Title = $termString;

        $template = new SSViewer('BreadcrumbsTemplate');
        return $template->process($this->customise(new ArrayData(array(
            "Pages" => new ArrayList(array($page))
        ))));
    }
}
