# Indicators

Indicators vs time series.
Metadata schema developed by compiling elements from multiple databases like WDI and SDGs.
Indicators are typically organized in a database. WDI, ADB KI, etc. We document the indicator/series, but also have a template for database.
In catalog, entries are series. But attach metadata of database for information.
In NADA, can publish metadata and data that become available via API. Metadata Editor for metadata only. Future versions may provide tools for publishing data.

Documenting time series is easy. Only requires selection of a template, then fill out metadata entry form. If you have many indicators from a same database/organization, consider entering default values in the template. 

The Metadata Editor makes use of the ... metadata standard for the documentation of indicators. 
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
A *project* here corresponds to an indicator or series.

Create New 
Switch template if default is not the right one
Enter metadata.

Recommendations for improved discoverability: keywords

Tags

External resources

## Editing


