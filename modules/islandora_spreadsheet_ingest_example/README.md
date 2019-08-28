# Islandora Spreadsheet Ingest Example

## Introduction

Enabling this module will provide islandora_spreadsheet_ingest with a sample
implementation to serve as a launching off point for developing custom XSLT
templates and providing those templates through a hook implementation.

Module contains:
* a sample template file /includes/example_template.xslt
* a sample dataset to be used with the template /includes/example_data.csv

## Requirements

This module requires the following modules/libraries:

* [Islandora Spreadsheet
Ingest](https://github.com/discoverygarden/islandora_spreadsheet_ingest)

## Installation

Install as usual, see
[this](https://drupal.org/documentation/install/modules-themes/modules-7) for
further information.

## Usage

In order to facilitate the ingest of the attached files, the
includes/example_files directory should be directly referenced as the base
binaries folder when using the included example_data.csv. Provide the
Spreadsheet Ingest form a full path to this example files folder on the server.

## Troubleshooting/Issues

Having problems or solved a problem? Contact
[discoverygarden](http://support.discoverygarden.ca).

## Maintainers/Sponsors

Current maintainers:

* [discoverygarden](http://www.discoverygarden.ca)

## Development

If you would like to contribute to this module, please check out our helpful
[Documentation](https://github.com/Islandora/islandora/wiki#wiki-documentation-for-developers)
info, [Developers](http://islandora.ca/developers) section on Islandora.ca and
contact [discoverygarden](http://support.discoverygarden.ca).

## License

[GPLv3](http://www.gnu.org/licenses/gpl-3.0.txt)
