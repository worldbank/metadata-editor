# Indicators

Indicators vs time series.
Metadata schema developed by compiling elements from multiple databases like WDI and SDGs.

Indicators are summary measures related to key issues or phenomena, derived from observed facts. Indicators form time series when they are provided with a temporal ordering, i.e. when their values are provided with an ordered annual, quarterly, monthly, daily, or other time reference.

Indicators are typically organized in a database. WDI, ADB KI, etc. We document the indicator/series, but also have a template for database.

In catalog, entries are series. But attach metadata of database for information.
In NADA, can publish metadata and data that become available via API. Metadata Editor for metadata only. Future versions may provide tools for publishing data.

Documenting time series is easy. Only requires selection of a template, then fill out metadata entry form. If you have many indicators from a same database/organization, consider entering default values in the template. 

The Metadata Editor makes use of the ... metadata standard for the documentation of indicators. 

An indicator or time series is documented using the time series /indicators schema. The database schema is optional, and used to document the database, if any, that the indicator belongs to. When multiple series of a same database are documented, the metadata related to the database only needs to be generated once, then applied to all series. One metadata element in the time series /indicators schema is used to link an indicator to the corresponding database.

...

## The ... metadata schema

For the documentation of indicators, the Metadata Editor also makes use of the Dublin Core metadata standard to document **external resources**. External resources are files or links that provide content other than the metadata stored in the DDI. This may consist of PDF questionnaires or manuals, scripts, images, or any other resource available in digital format.

## Preparing your indicators

Rich metadata. Definition, keywords, topics, relevance are critical elements. Think of discoverability in keyword-based search engines.
Example: "economic growth" or "child malnutrition"
Metadata augmentation: ...

Indicators and time series often come with metadata limited to the indicators/series name and a brief definition. This significantly reduces the discoverability of the indicators, and the possibility to implement semantic searchability and recommender systems. It is therefore highly recommended to generate more detailed metadata for each time series, including information on the purpose and typical use of the indicators, of its relevancy to different audiences, of its limitations, and more.

When documenting an indicator or time series, attention should be paid to include keywords and phrases in the metadata that reflect how data users are likely to formulate their queries when searching data catalogs. Subject-matter expertise, combined with an analysis of queries submitted to data catalogs, can help to identify such keywords. For example, the metadata related to an indicator “Prevalence of stunting” should contain the keyword “malnutrition”, and the metadata related to “GDP per capita” should include keywords like “economic growth” or “national income”. By doing so, data curators will provide richer input to search engines and recommender systems, and will have a significant and direct impact on the discoverability of the data.


## Creating a new project

A *project* here corresponds to an indicator or series. To create a new project, click on "Create new project" in the Project page and, when prompted, select "Indicator/time series" as data type. A new project page will open.

The project home page provides an option to select a project thumbnail. The thumbnail will be used by the NADA cataloguing application. It will also be displayed in the Metadata Home page. The thumbnail is an image in JPG or PNG format.

The template identified the Template Manager as the default template for Microdata in will be used. You can select a different template by clicking on "Switch template". The navigation bar on the right of the page will reflect the content of the template you selected. Note that you may change the template at any time without losing any information. The templates are only "masks" used to generate the metadata entry forms in the Metadata Editor.

After changing the Thumbnail and selecting the IHSN template (if it was not selected by default), the Home page will be as follows:

![image](https://user-images.githubusercontent.com/35276300/217011384-99583063-37f4-4b38-bd9c-4dbc968e7303.png)

A description of the content of each element is available by clicking on the "?". You will find more information on the metadata elements in the Schema Guide ([https://ihsn.github.io/editor/#/template-manager/](https://mah0001.github.io/schema-guide/chapter07.html).

If the content you enter violates a validation rule entered in the template, an error message will be displayed in red. All violation of rules will also be displayed in the project Home page.

The Required elements are indicated by a red asterisk.

Enter metadata with as much relevant information as possible. Remember that the metadata is the input that will be indexed by catalogs, and is critical to ensure data discovery. In the example, below, we document an indicator from the World Bank World Development Indicator. The indicator is SP.POP.DPND - Age dependency ratio (% of working-age population). 

![image](https://user-images.githubusercontent.com/35276300/217014831-136d57c2-b700-451c-901e-44cca5096d00.png)

External resources

## Editing a project


## Publishing in NADA

![image](https://user-images.githubusercontent.com/35276300/217015089-c28370c9-1650-4292-8af9-c82962c944f3.png)

