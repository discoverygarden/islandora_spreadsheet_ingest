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
  <xsl:param name="title"/>
  <xsl:param name="names"/>
  <xsl:param name="abstract"/>
  <xsl:param name="identifier"/>
  <!-- Any CSV headers to be used by the template should be defined globally
       as xsl:param nodes with the 'name' attribute matching the header. -->
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
      <!-- An example of how delimiting within a single cell could be
           accomplished; it is left up to template creators to define
           delimiting, and up to CSV creators to implement it. -->
      <xsl:if test="string-length($names)">
        <xsl:for-each select="tokenize($names, ' ; ')">
          <name>
            <namePart><xsl:value-of select="normalize-space(.)"/></namePart>
          </name>
        </xsl:for-each>
      </xsl:if>
      <xsl:if test="string-length($abstract)">
        <abstract><xsl:value-of select="normalize-space($abstract)"/></abstract>
      </xsl:if>
      <xsl:if test="string-length($identifier)">
        <identifier>
          <xsl:value-of select="normalize-space($identifier)"/>
        </identifier>
      </xsl:if>
    </mods>
  </xsl:template>
</xsl:stylesheet>
```

An example of a 
[.csv](/modules/islandora_spreadsheet_ingest_example/includes/example_data.csv) 
file that would work with the above sample template.

Column headers represent variables that will be passed into the selected XSLT
and must only contain characters valid in XSLT qualified names.
Due to the nature of XSLT, all variables defined by the template are required
spreadsheet column headers. The following spreadsheet column headers are
reserved and may be required:

<table>
  <tr>
    <th>Column</th>
    <th>Description</th>
    <th>Required</th>
  </tr>
  <tr>
    <td>pid</td>
    <td>A PID to assign this object.</td>
    <td>No; if one is not given, a PID will be assigned in the given namespace.</td>
  </tr>
  <tr>
    <td>parent_object</td>
    <td>The parent of this object.</td>
    <td>Required when creating a paged content child object.<br/> Not required when creating a general object, omitting will generate an object with no parent.</td>
  </tr>
  <tr>
    <td>parent_predicate</td>
    <td>The predicate relationship between this object
      and its given parent_object.</td>
    <td>Not used when ingesting a paged content child object.<br/> For general objects it is also not required; defaults to "isMemberOfCollection".</td>
  </tr>
  <tr>
    <td>parent_uri</td>
    <td>The URI of the predicate relationship between this object
      and its given parent object.</td>
    <td>Not used when ingesting a paged content child object.<br/> For general objects it is also not required; defaults to "isMemberOfCollection".</td>
  </tr>
  <tr>
    <td>cmodel</td>
    <td>A PID representing the content model to be applied to this object.</td>
    <td>Yes</td>
  </tr>
  <tr>
    <td>binary_file</td>
    <td>The relative path from the Base Binaries Folder to the file
      to use as the entry's OBJ datastream.</td>
    <td>No</td>
  </tr>
  <tr>
    <td>label</td>
    <td>The label to give the object.</td>
    <td>No, but omitting may generate objects with no labels.</td>
  </tr>
</table>

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
* Disabling the so-called "poor man's cron" at `admin/config/system/cron`, and
  using a crontab or otherwise command-line-based configuration for cron, as
  some processes (such as Islandora IP Embargo's embargo-lifting cron job) can
  override the global batch, preventing PHP from being refreshed and exposing
  the batch process to the potential for timeouts.

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
