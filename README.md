# Islandora Spreadsheet Ingest

## Introduction

A module to facilitate the ingest of data using a spreadsheet.
It makes csv migrations re-usable by allowing the upload of migrations to be
used as templates to be associated with source CSVs.
It is based on Drupal's migrate framework and is compatible with its
tooling.

## Requirements

This module requires the following modules/libraries:

* [dgi_migrate](https://github.com/discoverygarden/dgi_migrate)
* [islandora](https://github.com/Islandora/islandora/tree/8.x-1.x)
* [migrate_plus](https://www.drupal.org/project/migrate_plus)
* [Spout](https://github.com/box/spout)

## Usage

Template migrations can be implemented in either modules or config. An example
migration is implemented in the
[`islandora_spreadsheet_ingest` submodule](modules/islandora_spreadsheet_ingest_example).

To make use of templates to ingest, you can go to your site's
`admin/content/islandora_spreadsheet_ingest` endpoint and hitting the "Add
request" endpoint, and:

1. Naming your request.
2. Uploading your CSV/Spreadsheet file
3. Entering the name of the worksheet (if applicable)
4. Selecting the template to use; and,
5. Submitting the form.

The ingest proper can be kicked off in various ways from the given request's
"Process" task page. Most users should submit as "Deferred", which submits the
request to be processed in by a daemon process. "Immediate" runs as a batch
directly in the browser. "Manual" is intended more for developer use (or those
with CLI access), to run the requests by other means.

### Building migration templates

Templates are expected to be built by developers, as they can get rather complex when taking advantage of the many customizations made available throughout the Drupal Migrate infrastructure.

#### Useful Resources
* [List of core Migrate process plugins](
https://www.drupal.org/docs/8/api/migrate-api/migrate-process-plugins/list-of-core-migrate-process-plugins)
* [List of process plugins provided by Migrate Plus](
https://www.drupal.org/docs/8/api/migrate-api/migrate-process-plugins/list-of-process-plugins-provided-by-migrate-plus)

## Installation

Install as usual, see
[this](https://drupal.org/documentation/install/modules-themes/modules-8) for
further information.
Configure allowed binary paths at `/admin/config/islandora_spreadsheet_ingest`.

## Troubleshooting/Issues

Having problems or solved a problem? Contact
[discoverygarden](http://support.discoverygarden.ca).

## Maintainers/Sponsors

Current maintainers:

* [discoverygarden](http://www.discoverygarden.ca)

## Development

An example migration and cmd that can help with development is provided.
If you would like to contribute to this module create an issue, pull request
and or contact
[discoverygarden](http://support.discoverygarden.ca).

## License

[GPLv3](http://www.gnu.org/licenses/gpl-3.0.txt)
