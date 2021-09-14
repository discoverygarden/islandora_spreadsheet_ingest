# Islandora Spreadsheet Ingest Serial

## Introduction

Allows for the ingest of Serial objects.

## Requirements

This module requires the following modules/libraries:

* [Islandora Spreadsheet
Ingest](https://github.com/discoverygarden/islandora_spreadsheet_ingest)
* [Islandora Serial Object SP](https://github.com/islandora/islandora_solution_pack_newspaper)

## Installation

Install as usual, see
[this](https://drupal.org/documentation/install/modules-themes/modules-7) for
further information.

## Usage

Only one line is required per serial root object. To ingest a serial object, add the appropriate fields for the object.
For the object_location, a directory is expected. This directory should have the following structure:
```bash
/path/to/Serial Root Object/
  ├── SERIAL-LEVELS.json
  ├── MODS.xml
  ├── intermediate_1_Intermediate_1/
  │   ├── section_1_Section_1/
  │   ├── pdf_2_PDF_1/
  │   │   ├── OBJ.pdf
  │   │   └── MODS.xml
  │   ├── pdf_3_PDF_2/
  │   │   ├── OBJ.pdf
  │   │   └── MODS.xml
  │   ├── section_4_Section_2/
  │   ├── pdf_1_PDF 1/
  │   │   └── OBJ.pdf
  │   └── pdf_2_PDF 2/
  │       └── OBJ.pdf
  └── intermediate_2_Intermediate 2/
      ├── pdf_1_PDF 1/
      │   ├── MODS.xml
      │   └── OBJ.pdf
      └── pdf_2_PDF 2/
          ├── MODS.xml
          └── OBJ.pdf
```
Note the following:
| Path                         | File/Directories representation |
|------------------------------|---------------------------------|
| /path/to/Serial Root Object/ | Top level directory signifying a islandora:rootSerialCModel object. |
| SERIAL-LEVELS.json | A json file used to indicate the serial levels on the root serial object. |
| MODS.xml                     | - An xml file to be used as the MODS datastream. - If no MODS is provided the directory name will be used as the object label. |
| intermediate_{sequence_position}_{Intermediate_label}/  | <ul><li>A subdirectory signifying an `islandora:intermediateCModel` object.</li> <li>Naming convention: <ul><li>"intermediate_" indicates an intermediate object <li>sequence_position is used as the sequence on the root serial object. <li>Characters after sequence_position and underscore will be used as a label.</li></ul> <li>Parent predicate of `isMemberOf`</li><li>Parent URI of default</li></ul> |
| section_{sequence_position}_{section_label}/   | <ul><li>A subdirectory signifying an `islandora:intermediateSerialCModelStub` object.</li><li>Naming convention: <ul><li>"section_\" indicates a stub object.</li><li>sequence_position indicates the sequence on the intermediate object.</li><li>Characters after sequence_position and underscore will be used as a label.</li></ul> <li>We will not expect additional datastreams for this.</li><li>Parent predicate of `isComponentOf`</li><li>Parent URI of `info:fedora/fedora-system:def/relations-external#`</li></ul> |
| pdf_{sequence_position}_{pdf_label}/                   | <ul><li>A subdirectory signifying a pdf object.</li> <li>Naming convention:</li><ul><li>pdf indicates a pdf object</li><li>sequence_number indicates the sequence on the intermediate object.</li><li>Chracters after sequence_position and the underscore will be used as a label, if no MODS datastream is provided.</li></ul> <li>It's expected that a PDF file will be contained but also any MODS files, and potential additional datastreams will be added. <li>Parent predicate of `isComponentOf` <li> Parent uri of `info:fedora/fedora-system:def/relations-external#`</ul> |

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
