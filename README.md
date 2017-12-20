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

An example of a .csv file that would work with the above sample template:

```csv
parent_object,cmodel,title,names
islandora:sp_basic_image_collection,islandora:sp_basic_image,Test 1,Kevin
islandora:sp_basic_image_collection,islandora:sp_basic_image,Test 2,Bob ; Jill
```

## Usage

Users with the "Use Islandora Spreadsheet Ingest" permission have access to a
batch ingest page at '/islandora_spreadsheet_ingest'. From here, a spreadsheet
can be uploaded paired with an existing template, and batch ingested. Some
instructions exist on this page regarding required fields.

There are a host of conditions that make ingesting large CSV files (over 5000
rows, as a general rule) risky when done entirely through your browser.
Preprocessing the batch is generally safe, but processing for long periods of
time may need intervention to prevent timeouts or other issues; consider, for
example:

* Turning on deferred derivative generation and using something like
  [islandora_job](https://github.com/discoverygarden/islandora_job) to offset
  the responsibility of derivative generation
* Increasing timeout tolerance levels using the site-wide PHP configuration
  settings in the `php.ini` your webserver uses (e.g., max execution time)
* Using friendlier browser and operating system combinations that are safer
  towards long-running processes; for example, disabling App Nap in macOS.
* Splitting up your CSV into multiple smaller ones
* Unchecking "Ingest immediately" and performing the processing step under
  safer conditions, such as directly against the server using the `drush
  islandora-batch-process` command
* Temporarily turning off Drupal cron, as some processes (such as Islandora IP
  Embargo's embargo-lifting job) override the global batch, preventing PHP from
  refreshing and exposing the batch process to the potential for timeouts.

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
