# Changelog

All notable changes to this project will be documented in this file.

This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

* Add Playwright tests in `tests/playwright/` that run the admin screen in a real browser against a `silverstripe/testsession` database. `silverstripe/testsession` is a new `require-dev` dependency. See `docs/en/03_testing.md`.
* When `silverstripe/testsession` is installed, the module adds an extension to `TestSessionEnvironment` that keeps the database of a test session after the request that starts it. It only acts when the start request has the parameter `taxonomyKeepTestDatabase=1`, which the Playwright set-up sends.
* Add `extra.expose` for `client/dist` and `client/lang` to `composer.json`, so the built bundle and the language file are served from `_resources/`.
* Rewrite the user guide for the React screen and the developer documentation for the API.
* Breaking: `TaxonomyAdmin` is now a `LeftAndMain` section with a React screen that only uses the API at `/admin/api/v1/taxonomy`. It no longer extends `ModelAdmin`, so the nested GridField URLs such as `/admin/taxonomy/SilverStripe-Taxonomy-TaxonomyTerm/EditForm/...` return `404`. Projects that subclass `TaxonomyAdmin` or call its `ModelAdmin` methods must change.
* Breaking: the admin no longer offers CSV import, CSV export, print, previous and next record links, or search filters other than the name.
* Breaking: the module no longer suggests `symbiote/silverstripe-gridfieldextensions` or `undefinedoffset/sortablegridfield`, and the Behat tests and `behat.yml` are gone.
* Add a JavaScript toolchain to the module. The terms list screen at `/admin/taxonomy` supports search by name, paging and "Add term".
* Add the term screens to the admin. `/admin/taxonomy/terms/new` and `/admin/taxonomy/terms/{id}` create and edit a term, and the edit screen lists the child terms below the form, with "Add child term" and delete. Deleting a term asks for confirmation. The breadcrumbs come from the ancestors that the API returns for the term.
* Add reordering to the lists of top-level terms and child terms. Drag a row, use the keyboard on the drag handle, or use the "Move up" and "Move down" buttons. Each move calls `PATCH terms/{id}` with `{"Sort": n}` and shows the new order at once. The list goes back to the old order and shows an error toast when the API refuses the move. The top-level list cannot be reordered while a search filters it.
* Add the types screens to the admin. `/admin/taxonomy/types` lists the types, and `/admin/taxonomy/types/new` and `/admin/taxonomy/types/{id}` create and edit a type, with delete after a confirmation. The "Add type" and delete buttons only show to members that the API allows.
* Add the "Terms" and "Types" tabs to the admin. They replace the tabs of the managed models in `ModelAdmin`. A tab only shows when the API root says that the member can view the collection.
* Add `@dnd-kit/core`, `@dnd-kit/sortable`, `@dnd-kit/modifiers` and `@dnd-kit/utilities` as JavaScript dependencies of the module.
* Breaking: `TaxonomyTerm::getCMSFields()` no longer adds the `Children` GridField or the `Sort` fallback field. Child terms and their order are managed through the API.
* Add a JSON API for terms and types at `/admin/api/v1/taxonomy`. It reads, creates, changes, deletes and reorders records, and answers the requests that form fields make. See `docs/en/02_api.md`.
* In the API, `Sort` is the 1-based position of a term among its siblings. `PATCH terms/{id}` with a `Sort` key reorders the term. `GET types/{id}/terms` lists the terms of a type.
* Add the `TaxonomyType::Terms()` relation, the reverse of the `Type` relation of `TaxonomyTerm`. `TaxonomyType::getCMSFields()` removes its field.
* Add `TaxonomyTerm::getSiblingsInOrder()`.
* `TaxonomyTerm` and `TaxonomyType` forms now need a `Name` that is not empty. The models add a `RequiredFieldsValidator` through `getCMSCompositeValidator()`.
* Behaviour change: `TaxonomyType::canView()` returns `true` for members with access to the "Taxonomies" section. Editing, deleting and creating types still needs the ADMIN permission.
* `TaxonomyTerm::canView()` now calls `extendedCan()`, and `TaxonomyTerm::canCreate()` passes its `$context` argument to extensions.
* Add `TaxonomyTerm::canCreateChild()`.

## [1.1.2]

* Update userhelp documentation
* Added standard Travis config
* Added standard license
* Added standard git attributes
* Added standard code of conduct
* Updated license year

## [1.1.1]

* Changelog added.
* FIX Updated dependencies
* Add tests for 3.2 and php 5.6
* Added CWP keyword