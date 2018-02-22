# Taxonomy usage

## Applying Taxonomy

A taxonomy is of extremely limited use by itself. To make use of it, you need to associate it with `Page` or other
`DataObject` elements in your site.

To add the the ability to associate `Page` with `TaxonomyTerm`, you need to add the many-many relation to `Page`:

```php
private static $many_many = array(
    'Terms' => 'TaxonomyTerm'
);
```

And also add the reverse of the relation in an extension:

```php
class TaxonomyTermExtension extends DataExtension
{
    private static $belongs_many_many = array(
        'Pages' => 'Page'
    );
}
```

Then add the extension by including this in your `mysite/_config.php`:

```php
TaxonomyTerm::add_extension('TaxonomyTermExtension');
```

or with YAML:

```yaml
TaxonomyTerm:
  extensions:
    - TaxonomyTermExtension
```

Run a `dev/build?flush=all` and you should see the table created. But you still can't do anything with it! You can fix
that by using a `GridField` to edit the associated terms. The sample code below will let your content editors add
existing terms with an autocomplete tool, unlink linked terms, but not edit them or add new ones.

```php
public function getCMSFields()
{
    $fields = parent::getCMSFields();

    $components = GridFieldConfig_RelationEditor::create();
    $components->removeComponentsByType('GridFieldAddNewButton');
    $components->removeComponentsByType('GridFieldEditButton');

    $autoCompleter = $components->getComponentByType('GridFieldAddExistingAutocompleter');
    $autoCompleter->setResultsFormat('$Name ($TaxonomyName)');

    $dataColumns = $components->getComponentByType('GridFieldDataColumns');
    $dataColumns->setDisplayFields(array(
        'Name' => 'Term',
        'TaxonomyName' => 'Taxonomy'
    ));

    $fields->addFieldToTab(
        'Root.Tags',
        new GridField(
            'Terms',
            'Terms',
            $this->Terms(),
            $components
        )
    );

    return $fields;
}
```

You can apply that code to a `DataObject` to make use of it in the `ModelAdmin` area.

## Filtering by type

If you have implemented taxonomy types, you can filter them for the GridField autocompleter to ensure that certain
areas of your CMS will only autocomplete certain types of taxonomy terms. This can be useful, for example, if you want
to separate your terms between files/images/documents and CMS pages.

To implement, add an extra line to your `getCMSFields` after the `$autoCompleter` has been created:

```php
$autoCompleter->setSearchList(TaxonomyTerm::get()->filter(array('Type.Name:ExactMatch' => 'CMS Page')));
```

## Showing Taxonomy Terms

So you've got a set of terms associated with a page, and you want to show them on your site. You can loop through them
like any other relation:

```
<% loop $Terms %>
    <span class="tag">$Name</span>
<% end_loop %>
```

See the two sections below for suggestions on how to create intelligent links from those tags.

## Filtering with Taxonomy Terms

You might have a news section in which you want to show only news items that have been tagged with a particular term.

To start, it might be useful to get a list of all relevant terms. That is, all the terms that have been used in a list
of pages or dataobjects. This is one example that pulls a list of all tags that are used by children of this page:

```php
public function ChildTags()
{
    $tags = TaxonomyTerm::get()
        ->innerJoin(
            'Page_Terms',
            '"TaxonomyTerm"."ID"="Page_Terms"."TaxonomyTermID"'
        )->innerJoin(
            'SiteTree',
            "\"SiteTree\".\"ID\"=\"Page_Terms\".\"PageID\" AND \"SiteTree\".\"ParentID\"='$this->ID'"
        )->sort('Name');

    return $tags;
}
```

You could use the output of this to display a list of tags that the user could filter by.

You can then filter a result set by performing an inner join on it to restrict it to only those that have the required
term in their many-many relation (referenced by "$tagID"):

```php
$pages = $this->Children();
$pages = $pages->innerJoin(
        'BasePage_Terms',
        '"Page"."ID"="Page_Terms"."PageID"'
    )->innerJoin(
        'TaxonomyTerm',
        "\"Page_Terms\".\"TaxonomyTermID\"=\"TaxonomyTerm\".\"ID\" AND \"TaxonomyTerm\".\"ID\"='$tagID'"
    );
```

This will work for a single term. You could add more complex SQL to get any from a set of terms or to exclude terms.

## Taxonomy Navigation

You might want a page that acts as a parent to all pages with a certain tag. This way it can act as a dynamic directory
that will always display content about a certain topic, automatically adding pages as they get created.

### Using a custom page type
To do so, you'll need to create a new page type (called `TaxonomyDirectory` here) and specify the tag or tags that
you'd like the page to display. In this example we'll just re-use the existing many-many relationship on `Page` but if
it's necessary for you to have two then you can create an extra many-many relationship.

The only two functions that this page type needs to define are `stageChildren` and `liveChildren`. Instead of getting
children from the usual parent-child relationship, they look up children based on their taxonomy:

```php
class TaxonomyDirectory extends Page
{
    public function stageChildren($showAll = false)
    {
        $termIDString = implode(',', $this->Terms()->map()->keys());

        return Page::get()
            ->where("\"Page\".\"ID\" <> $this->ID")
            ->innerJoin(
                'BasePage_Terms',
                '"Page"."ID"="BasePage_Terms"."BasePageID"')
            ->innerJoin(
                'TaxonomyTerm',
                "\"BasePage_Terms\".\"TaxonomyTermID\"=\"TaxonomyTerm\".\"ID\" AND \"TaxonomyTerm\".\"ID\" IN ($termIDString)");
    }

    public function liveChildren($showAll = false, $onlyDeletedFromStage = false)
    {
        return $this->stageChildren($showAll);
    }
}

class TaxonomyDirectory_Controller extends Page_Controller
{

}
```

### Using the provided default controller implementation
If you want to use the provided directory implementation named `TaxonomyDirectoryController`, more or less based on the previous example, the only thing
you need to do is enable access to the controller functions using a custom `routes.yml` file

```yaml

---
Name: directoryroutes
After: framework/routes#coreroutes
---
Director:
  rules:
    'tag/$ID!': 'TaxonomyDirectoryController'

```

In this example, any request made to `/node/<TAG>` would render a page which contains a list of pages that uses the
Taxonomy Term specified by `<TAG>`. You can set the path 'node/' to any thing you want e.g. 'tag', 'taxonomy' etc.

You can override the provided template `TaxonomyDirectory.ss` in your own theme.

Currently the following variables are available to the template;
1. `Title` - the Taxonomy Term you are searching for
2. `Pages` - a list of `Page` objects

An example for a template;
```html

<h1>Taxonomy directory</h1>

<h2>Results for '$Title'</h2>

<ul>
<% loop $Pages %>
    <li><a href="$Link">$Title</a></li>
<% end_loop %>
</ul>


```


Note: the default directory implementation assumes that you've setup the reverse relation specified by `BasePage`.  
