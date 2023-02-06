# Database

## The Database schema

Time series are often contained in multi-indicators databases, like the World Bank’s World Development Indicators - WDI, whose on-line version contains series for 1,430 indicators (as of 2021). To document not only the series but also the databases they belong to, we propose two metadata schemas: one to document the series/indicators, the other one to document the databases they belong to.

In the NADA application, a series can be documented and published without an associated database, but information on a database will only be published in association with a series. The information on a database is thus treated as an “attachment” to the information on a series. A SERIES DESCRIPTION tab will display all metadata related to the series, i.e. all content entered in the series schema.

![image](https://user-images.githubusercontent.com/35276300/217082799-d2cd213c-7c0f-4503-9917-b4a3f09a6796.png)

The (optional) SOURCE DATABASE tab will display the metadata related to the database, i.e. all content entered in the series database schema. This information is displayed for information, but not indexed in the NADA catalog (i.e. not searchable).

![image](https://user-images.githubusercontent.com/35276300/217082831-f29fd693-3980-4407-97d4-f6c4741ef245.png)

The database schema is used to document the database that contains the time series, not to document the indicators or /series.

For the documentation of databases, the Metadata Editor also makes use of the Dublin Core metadata standard to document **external resources**. External resources are files or links that provide content other than the database metadata itself. This may consist of any resource available in digital format.

## Creating a new project

A *project* here corresponds to a database of indicators. To create a new project, click on "Create new project" in the Project page and, when prompted, select "Database" as data type. A new project page will open.

The project home page provides an option to select a project thumbnail. The thumbnail will be used by the NADA cataloguing application. It will also be displayed in the Metadata Home page. The thumbnail is an image in JPG or PNG format.

The template identified in the Template Manager as the default template for Database will be used. You can select a different template by clicking on "Switch template". The navigation bar on the right of the page will reflect the content of the template you selected. Note that you may change the template at any time without losing any information. The templates are only "masks" used to generate the metadata entry forms in the Metadata Editor.

After changing the Thumbnail and selecting the IHSN template (if it was not selected by default), the Home page will be as follows:

![image](https://user-images.githubusercontent.com/35276300/217083307-d39ac23a-d3d4-4b74-8616-fe46c8f32268.png)

A description of the content of each element is available by clicking on the "?". You will find more information on the metadata elements in the Schema Guide at https://mah0001.github.io/schema-guide/chapter07.html#indicators-time-series-database-and-scope-of-the-schema

If the content you enter violates a validation rule entered in the template, an error message will be displayed in red. All violation of rules will also be displayed in the project Home page.

The Required elements are indicated by a red asterisk.

Enter metadata with as much relevant information as possible. Remember that the metadata is the input that will be indexed by catalogs, and is critical to ensure data discovery. In the example, below, we use the World Bank's World Development Indicator (WDI) database as an example. 

![image](https://user-images.githubusercontent.com/35276300/217087648-14c1b83b-f9e4-4784-a4ea-17b5133d4484.png)


## Editing a project


## Publishing in NADA

Note: NADA offers option to publish data and add visualizations using widgets. This is not part of the metadata.

![image](https://user-images.githubusercontent.com/35276300/217015089-c28370c9-1650-4292-8af9-c82962c944f3.png)

