# Geographic data and services

## The metadata standard

The Metadata Editor makes use of the ISO 19115 and ISO 19110 metadata standards (and of their ISO 19139 XML specification) to document geographic datasets and services. This standard is the most complex of the standards and schemas supported by the Metadata Editor. The use of a template instead of the full standard is highly recommended. The Metadata Editor is provided with a user template that combines elements recommended in the INSPIRE (Infrastructure for Spatial Information in Europe) guidelines of the European Commission, and by the GOST (geospatial operations support team) of the World Bank. The template includes the core elements needed to document geographic raster or vector datasets, and geographic data services. 

## Documenting geographic data or services

Version 1.0 of the Metadata Editor does not provide tools to automatically extract metadata from data files. In a future version, tools will be embedded to extract information such as the bounding boxes, reference system, list and description of features, or count of geometric objects (for vector datasets), from data files.     

Documenting a geographic dataset requires expertise in geographic data management/analysis, and familiarity with the specialized terms used by the ISO standard. 

Some metadata elements like the title, abstract, description, purpose, and keywords will be very useful for data discoverability. Also, if the dataset contains features ("variables"), a detailed feature catalog should be provided where all features should have a label or description. In a NADA catalog, these elements will be indexed and searchable.

> Note 1: The metadata standard includes elements to document some "visualizations", which will be displayed in a NADA catalog. It is recommended to produce a few preview files, and save them as JPG.

> Note 2: A future version of the NADA catalog will embed an advanced geographic indexing and search tool.  
> 
The geographic data files can be published (as files or links) as external resources.

If the project home page does not show validation errors, the (meta)data can be published in a NADA catalog, where they will appear under tag "Geospatial".

![image](https://user-images.githubusercontent.com/35276300/234398222-242e6163-5012-4db2-b510-48b39af6e494.png)



