---
title: Basic usage
summary: How to set up your taxonomy relations and fields
---

# Basic usage

The main model you'll be interacting with is [`TaxonomyTerm`](api:SilverStripe\Taxonomy\TaxonomyTerm). This class represents the actual taxonomy terms you will apply to your data.

A taxonomy is of extremely limited use by itself. To make use of it, you need to associate it with
`DataObject` models in your site.

To add the the ability to associate a model with `TaxonomyTerm`, you need to add the many-many relation:

```php
namespace App\Model;

use SilverStripe\Forms\TreeMultiselectField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Taxonomy\TaxonomyTerm;

class MyModel extends DataObject
{
    // ...
    private static $many_many = [
        'Terms' => TaxonomyTerm::class,
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        // ...
        $fields->addFieldToTab('Root.Main', TreeMultiselectField::create(
            'Terms',
            $this->fieldLabel('Terms'),
            TaxonomyTerm::class
        ));
        return $fields;
    }
}
```

Run `sake db:build --flush` and you'll be able to add taxonomy terms to your record - but now you need to create some terms! See [the userhelp documentation](https://userhelp.silverstripe.org/en/optional_features/taxonomies/) for information about functionality from a content author perspective.

## Filtering by type

If you have implemented taxonomy types, you can filter them to ensure that only taxonomy terms of a given type or types can be selected.
This can be useful, for example, if you want to separate your terms between files/images/documents and CMS pages.

> [!WARNING]
> This relies on `TaxonomyType` records which have specific names being set up in the CMS.
> You will need to coordinate with content authors to ensure these are always available, or else
> ensure they are created by default by [populating defaults](https://docs.silverstripe.org/en/developer_guides/model/how_tos/dynamic_default_fields/)
> and ensure they cannot be deleted by [implementing an Extension](https://docs.silverstripe.org/en/developer_guides/extending/extensions/)
> with the `canDelete()` method.

To implement this filtering, call [`TreeMultiselectField::setFilterFunction()`](api:SilverStripe\Forms\TreeMultiselectField::setFilterFunction()) with the filtering logic:

```php
use SilverStripe\Forms\TreeMultiselectField;
use SilverStripe\Taxonomy\TaxonomyTerm;
use SilverStripe\Taxonomy\TaxonomyType;

/** @var TreeMultiselectField $treeField */
$typeID = TaxonomyType::get()->find('Name', 'CMS Page')?->ID;
$treeField->setFilterFunction(fn (TaxonomyTerm $term) => $term->TypeID === $typeID);
```

## Showing taxonomy terms

So you've got a set of terms associated with a page, and you want to show them on your site. You can loop through them
like any other relation:

```ss
<% loop $Terms %>
    <span class="tag">$Name</span>
<% end_loop %>
```
