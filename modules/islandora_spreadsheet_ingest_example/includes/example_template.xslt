<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="2.0">
  <!-- Spreadsheet ingest will pass in the contents of each cell, using column
       headers as parameter names. Note the use of the same label column used as
       a reserved field by spreadsheet ingest. It's being reused here to place
       in the MODS. Note also that not every column header is present here, as
       this is not necessary. -->
  <xsl:param name="label"/>
  <xsl:param name="names"/>
  <xsl:param name="abstract"/>
  <xsl:param name="identifier"/>
  <!-- Spreadsheet ingest calls the template named "root" to get the resultant
       XML document to be attached to each object; without a "root" template,
       metadata generation will fail. -->
  <xsl:template name="root">
    <mods xmlns="http://www.loc.gov/mods/v3"
          xmlns:mods="http://www.loc.gov/mods/v3"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xmlns:xlink="http://www.w3.org/1999/xlink">
      <!-- Best practices typically involve testing the contents of a cell
           before adding it to the resultant document, and using normalize-space
           to trim whitespace from the edges of the cell contents. -->
      <xsl:if test="string-length($label)">
        <titleInfo>
          <title><xsl:value-of select="normalize-space($label)"/></title>
        </titleInfo>
      </xsl:if>
      <!-- Spreadsheet ingest intentionally contains no mechanism for creating
           multi-valued fields; instead, a structure similar to this is expected
           where the implementation prescribes and documents a method of
           tokenizing the contents of a cell. Note in the accompanying CSV the
           use of names like "Name A ; Name B", matching the expected tokenize
           parameter in use here. -->
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
        <identifier><xsl:value-of select="normalize-space($identifier)"/></identifier>
      </xsl:if>
    </mods>
  </xsl:template>
</xsl:stylesheet>
