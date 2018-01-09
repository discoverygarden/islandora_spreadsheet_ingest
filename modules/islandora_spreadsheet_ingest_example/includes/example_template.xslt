<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="2.0">
  <xsl:param name="title"/>
  <xsl:param name="names"/>
  <xsl:param name="abstract"/>
  <xsl:param name="identifier"/>
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
