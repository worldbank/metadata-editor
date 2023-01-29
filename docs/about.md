# Rationale

The volume and diversity of data made available to the research community are growing fast. But too many valuable datasets remain largely under-exploited. Finding, understanding, and using the most relevant data can be a challenging task for researchers and other data users. To be used more extensively, data must be made easier to find, understand, access, and use. The production and exploitation of **rich and structured metadata** is a core requirement to meet these objectives.

Producing quality metadata is not a trivial exercise. The adoption of metadata standards and schemas fosters the quality of metadata. Standards and schemas, designed for different data types by communities of professionals, help data curators to produce detailed, comprehensive, and structured metadata. These metadata, saved in machine readable formats like JSON or XML, provide considerable flexibility and efficiency.

Structured metadata can be generated programmatically or using specialized software applications. The ***IHSN Metadata Editor*** presented in this document is an open-source solution provided by the International Household Survey Network (IHSN) for the documentation of multiple data types using different metadata standards and schemas. The data types covered by the IHSN Metadata Editor, and the related standards and schemas, are the following:

- **STRUCTURED DATA**
   - **Microdata**: the unit-level data on a population of individuals, households, dwellings, facilities, establishments, or other. Microdata can be generated from surveys, censuses, administrative recording systems, or sensors. The Metadata Editor uses the Data Documentation Initiative (DDI) Codebook metadata standard for documenting microdata.
   - **Statistical tables**: aggregated statistical information provided in the form of cross-tables, e.g., as published in statistics yearbooks or census reports. Statistical tables are often derived from microdata. The IHSN developed a specific metadata schema for the documentation of tables.
   - **Indicators, time series, and databases of indicators**: Indicators are summary measures derived from observed facts (often from microdata). When repeated over time at a regular frequency, the indicators form a time series. Time series are typically stored in databases.  The IHSN developed a specific metadata schema for the documentation of tables. This schema is a compilation of metadata elements found in lead indicators databases.
   - **Geographic datasets and geographic data services**: Geographic (or geospatial) data identify and depict geographic locations, boundaries, and characteristics of the surface of the earth. They can be provided in the form of datasets (raster or vector data) or data services (web applications). The Metadata Editor uses the ISO 19139 (and the related ISO 19110/19115) for documenting geographic datasets and services.

- **UNSTRUCTURED DATA**
   - **Text**: A collection of documents (bibliographic resources of any type, such as books, papers, reports, manuals, and other resources consisting of text) form a corpus. Using natural language processing (NLP) techniques, corpora can be converted into structured information. The Metadata Editor uses a specific schema, made of elements from the Dublin Core, the MARC21 from the US Library of Congress, and the BibTex metadata standards.
   - **Images**: Digital images can be processed using machine learning algorithms (of object detection, classification, or other). The Metadata Editor provides two options: the Dublin Core augmented by elements from the imageObject standard from schema.org, and the IPTC standard.
   - **Audio and video recordings**: Speech-to-text algorithms can transform audio and video recordings as text files. They can thus be considered as data in the same way we consider text as data. The Metadata Editor uses a specific schema made of elements from the Dublin Core and from the videoObject standard from schema.org.
   - **Research projects and scripts**: Although they are not data per se, we treat research projects and the related programs and scripts used to edit, transform, tabulate, analyze, model, and visualize data as data-related resources that need to be documented, catalogued, and disseminated in pursuit of transparency and reproducibility of data use. The Metadata Editor uses a specific schema for documenting research projects and scripts.

The Metadata Editor is one application among others developed or supported by the IHSN. It is a companion of the NADA cataloguing application, used to build and maintain on-line data catalogs that exploit the rich and structured metadata generated using the Metadata Editor.

# About the Metadata Editor

A multi-platform open source software.
Technology: ...
Embedded standards and schemas:
Use: Select a template, then enter metadata. For microdata, R for data import/export.
Output: JSON, XML
Saves packages
Can share. Web or stand-alone.
Inspired by Nesstar but different approach and multiple standards.
Can be adapted to different standards and schemas. Much flexibility.
Related tools and materials:

