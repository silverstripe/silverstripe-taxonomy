<?php

/**
 * Class TaxonomyDirectoryController
 *
 * Controller for returning a list of pages tagged with a specific Taxonomy Term
 */
class TaxonomyDirectoryController extends Page_Controller
{

    private static $allowed_actions = array(
        'index'
    );

    public function index(SS_HTTPRequest $request)
    {
        $termString = $request->param('ID');

        $pages = Page::get()->filter(['Terms.Name' => $termString]);

        return $this->customise(new ArrayData(array(
            'Title' => $termString,
            'Term' => $termString,
            'Pages' => $pages,
            'Breadcrumbs' => $this->renderBreadcrumb($termString)
        )))->renderWith(array("TaxonomyDirectory", "Page"));
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

