# Islandora Spreadsheet Ingest Compounds

## Introduction

Adds compound ordering to objects from the spreadsheet with `isConstituentOf`
relationships.

## Requirements

This module requires the following modules/libraries:

* [Islandora Spreadsheet Ingest](https://github.com/discoverygarden/islandora_spreadsheet_ingest)
* [Islandora Compound Objects](https://github.com/islandora/islandora_solution_pack_compound)

## Installation

Install as usual, see
[this](https://drupal.org/documentation/install/modules-themes/modules-7) for
further information.

## Usage

Sort ordering will be added to any objects that have been given an
`isConstituentOf` relationship to another object.

*NOTE*: The constituent order will be directly based on the order in which the
constituents are ingested. No other consideration is given to constituent order.
The spreadsheet ingest preprocessor will base the batch set order on the order
of rows in the original CSV, and in the case of most batch processing, ingests
will occur in that same order. Be aware that manually ingesting constituents or
using other methods to batch process may alter the natural sort order.

Sequence numbering will not be added to objects that already have sequence
numbering; as a result, you can bypass the functionality of this module by
prepping constituents with these relationships.

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
