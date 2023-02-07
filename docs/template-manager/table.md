# Statistical tables

## The metadata schema

A statistical table (cross tabulation or contingency table) is a summary presentation of data. The OECD Glossary of Statistical Terms defines it as “observation data gained by a purposeful aggregation of statistical microdata conforming to statistical methodology [organized in] groups or aggregates, such as counts, means, or frequencies.”

Tables are produced as an array of rows and columns that display numeric aggregates in a clearly labeled fashion. They may have a complex structure and become quite elaborate. They are typically found in publications such as statistical yearbooks, census and survey reports, research papers, or published on-line.

Statistical tables can be understood by a broad audience. In some cases, they may be the only publicly-available output of a data collection activity. Even when other output is available –such as microdata, dashboards, or databases accessible via user interfaces or APIs– statistical tables are an important component of data dissemination. It is thus important to make tables as discoverable as possible. The schema used in the Metadata Editor was designed to structure and foster the comprehensiveness of information on tables by rendering the pertinent metadata into a structured, machine-readable format. It is intended for the purpose of improving data discoverability. The schema is not intended to store information to programmatically re-create tables.

The schema description is available at http://dev.ihsn.org/nada/api-documentation/catalog-admin/index.html#tag/Tables

Information on the metadata elements can be found in the SChema Guide at https://mah0001.github.io/schema-guide/chapter08.html.

## Anatomy of a table

The figure below, adapted from LabWrite Resources (https://labwrite.ncsu.edu/res/gh/gh-tables.html), provides an illustration of what statistical tables typically look like. The main parts of a table are highlighted. They provide a content structure for the metadata schema we use in the Metadata Editor.

![image](https://user-images.githubusercontent.com/35276300/216686104-25d21426-28f8-43c3-8830-e2b8a466b8cd.png)

## Creating a new project

A project here corresponds to an indicator or series. To create a new project, click on "Create new project" in the Project page and, when prompted, select "Table" as data type. A new project page will open.

The project home page provides an option to select a project thumbnail. The thumbnail will be used by the NADA cataloguing application. It will also be displayed in the Metadata Home page. The thumbnail is an image in JPG or PNG format.

The template identified in the Template Manager as the default template for statistical tables will be used. You can select a different template by clicking on "Switch template". The navigation bar on the right of the page will reflect the content of the template you select. Note that you may change the template at any time without losing any information. The templates are only "masks" used to generate the metadata entry forms in the Metadata Editor.

After changing the Thumbnail and selecting the IHSN template (if it was not selected by default), the Home page will be as follows:

![image](https://user-images.githubusercontent.com/35276300/217111890-491edd50-982f-4f55-bbc8-b711a44d3feb.png)

The table we use as an example to describe the addition of metadata is a table presenting the evolution since 1960 of the number of households by size and of the average household size in the United States, published by the US Census Bureau. This table, published in MS-Excel format, was downloaded on 20 February 2021 from https://www.census.gov/data/tables/time-series/demo/families/households.html.

![image](https://user-images.githubusercontent.com/35276300/216686978-a6f2317e-42dc-4d0f-b078-24cfdfa208f0.png)

Add all available metadata.

![image](https://user-images.githubusercontent.com/35276300/217113319-a638c701-6592-4467-ad92-4e2eefe6b254.png)

Add external resource if relevant.

SAVE.

All complete. No validation error.
[image]

Saving the metadata:
JSON

The metadata can now be published in a NADA catalog. 

![image](https://user-images.githubusercontent.com/35276300/216687168-a662678f-4162-4faa-ba20-8dd512ed2bb0.png)

