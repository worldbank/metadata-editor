<?xml version="1.0" encoding="UTF-8"?>
<!--
Ths tarnsform converts the RDF to custom CSV format for database import

Author:	 IHSN
Version: MAY 2010
Platform: XSL 1.0

License: 
	Copyright 2010 The World Bank

    This program is free software; you can redistribute it and/or modify it under the terms of the
    GNU Lesser General Public License as published by the Free Software Foundation; either version
    2.1 of the License, or (at your option) any later version.
  
    This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
    without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
    See the GNU Lesser General Public License for more details.
  
    The full text of the license is available at http://www.gnu.org/copyleft/lesser.html
-->
<xsl:stylesheet 
				version="1.0" 
				exclude-result-prefixes="rdf dc dcterms"
				xmlns:xsl="http://www.w3.org/1999/XSL/Transform" 
				xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" 
				xmlns:dc="http://purl.org/dc/elements/1.1/"
				xmlns:dcterms="http://purl.org/dc/terms/"> 
<xsl:output method="xml" version="1.0" encoding="UTF-8" indent="yes" />

<xsl:template match="/">
	<xsl:apply-templates select="rdf:RDF"/>
</xsl:template> 
<xsl:template match="rdf:RDF">
	<xsl:apply-templates select="rdf:Description"/>
</xsl:template>
<xsl:template match="rdf:Description">
			<xsl:variable name="line-brk">{LN}</xsl:variable>
			<xsl:variable name="record-brk">{RCRD}</xsl:variable>
			<xsl:variable name="title" select="dc:title"/>
            <xsl:variable name="subtitle" select="dcterms:alternative"/>
							<xsl:variable name="author">
									<xsl:for-each select="dc:creator">
											<xsl:if test="position() = 1"><xsl:value-of select="."/></xsl:if>											
											<xsl:if test="position() &gt; 1">, <xsl:value-of select="."/></xsl:if>											
									</xsl:for-each>
							</xsl:variable>
							<xsl:variable name="dcdate">
									<xsl:value-of select="dcterms:created"/>
							</xsl:variable>
							<xsl:variable name="country">
									<xsl:value-of select="dcterms:spatial"/>
							</xsl:variable>
							<xsl:variable name="language">
									<xsl:for-each select="dc:language">
											<xsl:if test="position() = 1"><xsl:value-of select="."/></xsl:if>											
											<xsl:if test="position() &gt; 1">, <xsl:value-of select="."/></xsl:if>											
									</xsl:for-each>
							</xsl:variable>
							<xsl:variable name="contributor">
									<xsl:for-each select="dc:contributor">
											<xsl:if test="position() = 1"><xsl:value-of select="."/></xsl:if>											
											<xsl:if test="position() &gt; 1">, <xsl:value-of select="."/></xsl:if>											
									</xsl:for-each>
							</xsl:variable>
							<xsl:variable name="publisher">
										<xsl:for-each select="dc:publisher">
											<xsl:if test="position() = 1"><xsl:value-of select="."/></xsl:if>											
											<xsl:if test="position() &gt; 1">, <xsl:value-of select="."/></xsl:if>											
									</xsl:for-each>
							</xsl:variable>
							<xsl:variable name="description">
									<xsl:value-of select="dc:description"/>
							</xsl:variable>
							<xsl:variable name="abstract">
									<xsl:value-of select="dcterms:abstract"/>
							</xsl:variable>
							<xsl:variable name="toc">
									<xsl:value-of select="dcterms:tableOfContents"/>
							</xsl:variable>
							<xsl:variable name="filename">
										<xsl:value-of select="@rdf:about"/>
							</xsl:variable>
							<xsl:variable name="format">
									<xsl:value-of select="dc:format"/>
							</xsl:variable>
							<xsl:variable name="type">
									<xsl:value-of select="dc:type"/>
							</xsl:variable>
						<!--
								title
								author
								dcdate
								country
								language
								contributor
								publisher
								description
								abstract
								toc
								filename
								format
								type
                                subtitle
						-->						
		<xsl:value-of select="normalize-space($title)"/><xsl:value-of select="$line-brk"/>
		<xsl:value-of select="substring(normalize-space($author),0,254)"/><xsl:value-of select="$line-brk"/>
		<xsl:value-of select="substring(normalize-space($dcdate),0,25)"/><xsl:value-of select="$line-brk"/>
		<xsl:value-of select="substring(normalize-space($country),0,100)"/><xsl:value-of select="$line-brk"/>
		<xsl:value-of select="substring(normalize-space($language),0,50)"/><xsl:value-of select="$line-brk"/>
		<xsl:value-of select="substring(normalize-space($contributor),0,254)"/><xsl:value-of select="$line-brk"/>
		<xsl:value-of select="substring(normalize-space($publisher),0,254)"/><xsl:value-of select="$line-brk"/>
		<xsl:value-of select="($description)" disable-output-escaping="yes"/><xsl:value-of select="$line-brk"/>
		<xsl:value-of select="($abstract)" disable-output-escaping="yes"/><xsl:value-of select="$line-brk"/>
		<xsl:value-of select="$toc" disable-output-escaping="yes"/><xsl:value-of select="$line-brk"/>
		<xsl:value-of select="substring(normalize-space($filename),0,255)"/><xsl:value-of select="$line-brk"/>
		<xsl:value-of select="substring(normalize-space($format),0,255)"/><xsl:value-of select="$line-brk"/>
		<xsl:value-of select="substring(normalize-space($type),0,255)"/><xsl:value-of select="$line-brk"/>
        <xsl:value-of select="normalize-space($subtitle)"/><xsl:value-of select="$line-brk"/>
		<xsl:value-of select="$record-brk"/>
</xsl:template>

<xsl:template match="dc:creator">
	<xsl:value-of select="."/>,
</xsl:template>

<xsl:template match="dc:contributor">
	<xsl:value-of select="."/>, 
</xsl:template>

<xsl:template match="dc:language">
	<xsl:value-of select="."/>,
</xsl:template>

<xsl:template match="Description">
descrip
</xsl:template>

<xsl:template name="dateformat">
date
</xsl:template>

<!-- Function/template: converts line feed to break line <BR> for html display -->
	<xsl:template name="lf2br">
		<xsl:param name="text"/>
		<xsl:choose>
			<xsl:when test="contains($text,'&#10;')">
				{br}<xsl:value-of select="substring-before($text,'&#10;')"/>
				<xsl:call-template name="lf2br">
					<xsl:with-param name="text">
						<xsl:value-of select="substring-after($text,'&#10;')"/>
					</xsl:with-param>
				</xsl:call-template>
			</xsl:when>
			<xsl:otherwise>
				<xsl:value-of select="$text"/>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>	
<xsl:template name="escape-apos">
   <xsl:param name="string" />
   <xsl:choose>
      <xsl:when test='contains($string, "&apos;")'>
         <xsl:value-of select='substring-before($string, "&apos;")' />
         <xsl:text>''</xsl:text>
         <xsl:call-template name="escape-apos">
            <xsl:with-param name="string"
               select='substring-after($string, "&apos;")' />
         </xsl:call-template>
      </xsl:when>
      <xsl:otherwise>
         <xsl:value-of select="$string" />
      </xsl:otherwise>
   </xsl:choose>
</xsl:template>
</xsl:stylesheet>
