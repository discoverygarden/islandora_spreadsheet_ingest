# Islandora Spreadsheet Ingest

## Introduction

A module to facilitate the ingest of data using a spreadsheet.
It makes csv migrations re-usable by allowing the upload of migrations to be
used as templates to be associated with source CSVs.
It is based on Drupal's migrate framework and is compatible with its
tooling.

## Requirements

This module requires the following modules/libraries:

* [migrate_plus](https://www.drupal.org/project/migrate_plus)
* [dgi_migrate](https://github.com/discoverygarden/dgi_migrate)
* [migrate_spreadsheet](https://www.drupal.org/project/migrate_spreadsheet)
* [islandora](https://github.com/Islandora/islandora/tree/8.x-1.x)
* [Spout](https://github.com/box/spout)

## Usage

Migration templates can be uploaded at `admin/structure/migrate_templates`.
Migrate source CSVs can be uploaded and associated with templates at
`admin/structure/islandora_spreadsheet_ingest`.

An example migration that can be used as a starting point is part of this
module's config.

Use short migraiton names as generated names over 63 bytes will be truncated.
A tag `isimd` is added to all derived migrations so they can be operated on
with a single command.

Automatic scheduling of ingests is recommended:
`sudo -u www-data drush migrate:batch-import -u 1 -v --uri=http://localhost --execute-dependencies --tag=isimd`

A helper command for generating CSV headers and partial migration template yaml
for a given bundle is provided.
`islandora_spreadsheet_ingest:generate-bundle-info`

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
