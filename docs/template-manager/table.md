# Statistical tables

## The metadata schema

A statistical table, also known as a cross tabulation or contingency table, is a summary presentation of data. According to the OECD Glossary of Statistical Terms, it is "observation data gained by a purposeful aggregation of statistical microdata conforming to statistical methodology [organized in] groups or aggregates, such as counts, means, or frequencies."

Tables are created as an array of rows and columns that display numeric aggregates in a clearly labeled fashion. They may have a complex structure and become quite elaborate. Typically, they are found in publications such as statistical yearbooks, census and survey reports, research papers, or published online.

Statistical tables can be understood by a broad audience. In some cases, they may be the only publicly available output of a data collection activity. Even when other outputs are available, such as microdata, dashboards, or databases accessible via user interfaces or APIs, statistical tables remain an important component of data dissemination. It is therefore crucial to make tables as discoverable as possible.

The schema used in the Metadata Editor is designed to structure and foster the comprehensiveness of information on tables by rendering the pertinent metadata into a structured, machine-readable format. Its purpose is to improve data discoverability. The schema is not intended to store information to programmatically recreate tables.

The schema description is available at http://dev.ihsn.org/nada/api-documentation/catalog-admin/index.html#tag/Tables

Information on the metadata elements can be found in the Schema Guide at https://mah0001.github.io/schema-guide/chapter08.html.

## Anatomy of a table

The figure below, adapted from LabWrite Resources (https://labwrite.ncsu.edu/res/gh/gh-tables.html), provides an illustration of what statistical tables typically look like. The main parts of a table are highlighted. They provide a content structure for the metadata schema we use in the Metadata Editor.

![image](https://user-images.githubusercontent.com/35276300/216686104-25d21426-28f8-43c3-8830-e2b8a466b8cd.png)


## Documenting a table

The table we use as an example is a table presenting the evolution of the number of households by size and of the average household size in the United States since 1960, published by the US Census Bureau. This table, published in MS-Excel format, was downloaded on 20 February 2021 from https://www.census.gov/data/tables/time-series/demo/families/households.html.

![image](https://user-images.githubusercontent.com/35276300/216686978-a6f2317e-42dc-4d0f-b078-24cfdfa208f0.png)

The metadata that will typically be available for a table include the title, number, series, source, producer, data source(s), geographic and temporal coverage of the data, a description of rows and columns, possibly footnotes, keywords describing the content, and information on the type of statistics shown in the table. Filling out the metadata fields is straightforward, and does not require any functionality that is specific to this type of project. The table itself, or a link to it, should be added as an external resource.

![image](https://user-images.githubusercontent.com/35276300/217113319-a638c701-6592-4467-ad92-4e2eefe6b254.png)

If the project home page shows no validation error, the table can be published in a NADA catalog where it will appear under the tab "Tables".

![image](https://user-images.githubusercontent.com/35276300/234390492-11459d1a-7b3b-47a5-9dcb-ee33f807158b.png)

> Note: The NADA application provides an option to store the data contained in tables (after reshaping them as a long-format data file, where each row represents a cell of the table) in a mongoDB database, and to make the data accessible via API. This option is not provided in the Metadata Editor. It requires the use of the NADA API and of a programming tool like R or Python.


