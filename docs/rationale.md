# Objectives

The volume and diversity of data made available to the research community are growing fast. But too many valuable datasets remain largely under-exploited. Finding, understanding, and using the most relevant data can be a challenging task for researchers and other data users. To be used more extensively, data must be made easier to find, understand, access, and use. The production and exploitation of **rich and structured metadata** is a core requirement to meet these objectives.

Producing quality metadata is not a trivial exercise. The adoption of metadata standards and schemas fosters the quality of metadata. Standards and schemas, designed for different data types by communities of professionals, help data curators to produce detailed, comprehensive, and structured metadata. These metadata, saved in machine readable formats like JSON or XML, provide considerable flexibility and efficiency.

Structured metadata can be generated programmatically or using specialized software applications. The ***IHSN Metadata Editor*** presented in this document is an open-source solution provided by the International Household Survey Network (IHSN) for the documentation of multiple data types using different metadata standards and schemas. 

![image](https://user-images.githubusercontent.com/35276300/217036876-e3c18188-a28d-49fd-8437-6cb14c1f25a0.png)


The data types covered by the IHSN Metadata Editor, and the related standards and schemas, are the following:

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

**IMPORTANT NOTE**

Metadata compliant with standards and schemas can be generated using the Metadata Editor as described in this Guide. They can also be generated  programmatically using a programming language like R or Python. The latter option provides a high degree of flexibility and efficiency, as it  offers multiple opportunities to automate part of the metadata generation process, and to exploit advanced machine learning solutions to enhance metadata. Metadata generated using R or Python can also be published in a NADA catalog using the NADA API and the R package NADAR or the Python library PyNADA. The programmatic option may thus be the preferred option for organizations that have strong expertise in R or Python. This may be the case particularly for the documentation of publications, indicators, or images. For microdata, the use of a Metadata Editor with capability to extract metadata from data files may provide significant advantage. 

Users interested in the programmatic option will need to be falimiar with the API description of the standard and schemas. More information is available in the Schema Guide (https://mah0001.github.io/schema-guide/), and in the API documentation (https://ihsn.github.io/nada-api-redoc/catalog-admin/#). 

