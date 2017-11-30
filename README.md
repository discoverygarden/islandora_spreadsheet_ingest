# Islandora Spreadsheet Ingest

## Introduction

A module to facilitate the ingest of data using a spreadsheet.

## Requirements

This module requires the following modules/libraries:

* [Islandora](https://github.com/islandora/islandora)
* [Tuque](https://github.com/islandora/tuque)
* [Islandora Batch](https://github.com/Islandora/islandora_batch)
* [Saxon-B XSLT](http://saxon.sourceforge.net/)

## Installation

Install as usual, see
[this](https://drupal.org/documentation/install/modules-themes/modules-7) for
further information.

## Configuration

Users with the "Administer Islandora Spreadsheet Ingest" permission can view,
upload, and modify XSLT templates at
'/admin/islandora/tools/islandora_spreadsheet_ingest'. Global defaults can also
be set, including the default CSV parameters and a default secure location to
source binaries from. The path to the saxonb-xslt executable can also be set.

Uploaded templates should define CSV header parameters globally as XSL 'param'
nodes, and place the root of the output document in a template node named
"root"; for example:

```xml
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="2.0">
  <!-- Any CSV headers to be used by the template should be defined globally
       as xsl:param nodes with the 'name' attribute matching the header. -->
  <xsl:param name="title"/>
  <xsl:param name="names"/>
  <!-- The root of the output document should go in an xsl:template node with a
       'name' attribute of root. -->
  <xsl:template name="root">
    <mods xmlns="http://www.loc.gov/mods/v3"
          xmlns:mods="http://www.loc.gov/mods/v3"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xmlns:xlink="http://www.w3.org/1999/xlink">
      <xsl:if test="string-length($title)">
        <titleInfo>
          <title><xsl:value-of select="normalize-space($title)"/></title>
        </titleInfo>
      </xsl:if>
      <xsl:if test="string-length($names)">
        <!-- An example of doing delimiting within a single cell; it is left up
             to template creators to define delimiting, and up to CSV creators
             to implement it. -->
        <xsl:for-each select="tokenize($names, ' ; ')">
          <name>
            <namePart><xsl:value-of select="normalize-space(.)"/></namePart>
          </name>
        </xsl:for-each>
      </xsl:if>
    </mods>
  </xsl:template>
</xsl:stylesheet>
```

An example of a .csv file that would work with the above sample template,
assuming a file called test.jpg in the binary path given to the importer:

```csv
label,binary_file,parent_object,cmodel,title,names
Test 1,test.jpg,islandora:sp_basic_image_collection,islandora:sp_basic_image,Test 1,Name
Test 2,test.jpg,islandora:sp_basic_image_collection,islandora:sp_basic_image,Test 2,Name 1 ; Name 2
```

## Usage

Users with the "Use Islandora Spreadsheet Ingest" permission have access to a
batch ingest page at '/islandora_spreadsheet_ingest'. From here, a spreadsheet
can be uploaded paired with an existing template, and batch ingested. Some
instructions exist on this page regarding required fields.

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
