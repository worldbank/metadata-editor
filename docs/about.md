# Rationale

The volume and the diversity of data made available to the research community are growing fast. But too many valuable datasets remain largely under-exploited. Finding, understanding, and using the most relevant data can be a challenging task for researchers and other data users. To be used more extensively, data must be made easier to find, understand, access, and analyze. The production and exploitation of **rich and structured metadata** is a core requirement to meet these objectives.

Producing quality metadata is not a trivial exercise. The adoption of metadata standards and schemas, designed by a community of professionals for the optimal documentation of different data types, fosters the quality of metadata. Standards and schemas help data curators produce detailed, comprehensive, and structured metadata in machine and human readable formats (such as JSON or XML) that facilitate their use. Structured metadata can be generated in multiple ways, programatically or using specialized software applications. The Metadata Editor presented in this document is an open-source solution provided by the International Household Survey Network (IHSN) for the documentation of multiple data types. The data types covered by the IHSN Metadata Editor are:

- **STRUCTURED DATA**
   - **Microdata**: the unit-level data on a population of individuals, households, dwellings, facilities, establishments, or other. Microdata can be generated from surveys, censuses, administrative recording systems, or sensors.
   - **Statistical tables**: aggregated statistical information provided in the form of cross-tables, e.g., as published in statistics yearbooks or census reports. Statistical tables are often derived from microdata.
   - **Indicators, time series, and databases of indicators**: Indicators are summary measures derived from observed facts (often from microdata). When repeated over time at a regular frequency, the indicators form a time series. Time series are typically stored in databases.
   - **Geographic datasets and geographic data services**: Geographic (or geospatial) data identify and depict geographic locations, boundaries, and characteristics of the surface of the earth. They can be provided in the form of datasets (raster or vector data) or data services (web applications).

- **UNSTRUCTURED DATA**
   - **Text**: A collection of documents (bibliographic resources of any type, such as books, papers, reports, manuals, and other resources consisting of text) form a corpus. Using natural language processing (NLP) techniques, corpora can be converted into structured information. 
   - **Images**: Digital images can be processed using machine learning algorithms (of object detection, classification, or other).
   - **Audio and video recordings**: Speech-to-text algorithms can transform audio and video recordings as text files. They can thus be considered as data in the same way we consider text as data.
   - **Research projects and scripts**: Although they are not data per se, we treat research projects and the related programs and scripts used to edit, transform, tabulate, analyze, model, and visualize data as data-related resources that need to be documented, catalogued, and disseminated in pursuit of transparency and reproducibility of data use.

The Metadata Editor is one application among others developed or supported by the IHSN. It is a natural companion of the NADA cataloguing application, used to build and maintain on-line data catalogs that exploit the rich and structured metadata generated using the Metadata Editor. The IHSN also provides tools for the programmatic production and publishing of metadata (including the R package NADAR and the Python library PyNADA), guidelines for data documentation and dissemination, and tools and guidelines for statistical disclosure control. Some of these tools are briefly introduced in this document. All are listed in the section References and Links.  


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

