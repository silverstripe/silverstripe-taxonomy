# Taxonomy

[![CI](https://github.com/silverstripe/silverstripe-taxonomy/actions/workflows/ci.yml/badge.svg)](https://github.com/silverstripe/silverstripe-taxonomy/actions/workflows/ci.yml)
[![Silverstripe supported module](https://img.shields.io/badge/silverstripe-supported-0071C4.svg)](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/)

## Introduction

The Taxonomy module add the capability to add and edit simple taxonomies within SilverStripe.

## Requirements

 * Silverstripe 4.0+
 * Silverstripe Admin Module 1.0.2+
 
 **Note:** this version is compatible with Silverstripe 4. For Silverstripe 3, please see [the 1.x release line](https://github.com/silverstripe/silverstripe-taxonomy/tree/1.2).

## Features

Create multiple taxonomies with any number of nested terms.

## Installation

```
$ composer require silverstripe/taxonomy
```
Afterwards run `/dev/build?flush=all` to rebuild your database.

For usage instructions see [user manual](docs/en/userguide/index.md).

## Contributing

### Translations

Translations of the natural language strings are managed through a third party translation interface, transifex.com. Newly added strings will be periodically uploaded there for translation, and any new translations will be merged back to the project source code.

Please use [https://www.transifex.com/projects/p/silverstripe-taxonomy](https://www.transifex.com/projects/p/silverstripe-taxonomy) to contribute translations, rather than sending pull requests with YAML files.

## Reporting Issues

Please [create an issue](http://github.com/silverstripe/silverstripe-taxonomy/issues) for any bugs you've found, or features you're missing.
