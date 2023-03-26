# About

## Specifications of the current version

The Metadata Editor is a multi-platform application developed for the production of metadata compliant with multiple metadata standards and schemas. The application is highly flexible, and may in the future accommodate additional standards and schemas, as well as new versions of standards an schemas already supported.

The application works as a stand-alone application or as a server application. Operating the application on a server allows curation teams to collaborate on the documentation of datasets in the context of a data archive or data library. 

The software makes use of the following technologies:
- For the user interface: Bootstrap version ...
- For the back-end: PHP 8
- For the storage of metadata: JSON (with option to export metadata in XML)
- For the import and export of microdata: R and the Haven package. The application uses R but users do not need any expertise in R to use the Metadata Editor. 

The application was in part inspired by the Nesstar Publisher software application (by the Norwegian Social Science Data ARchive). The Nesstar Publisher, designed for the production of DDI-compliant metadata for microdata, is not maintained anymore. But the Metadata Editor interface is largely inspired from Nesstar. A large community of data curators have relied on Nesstar, and will be familiar with the interface. We express our gratitude to the Nesstar developers for our past collaboration and for allowing us to replicate much of their user interface. Note that there are major differences in the way we store and manage metadata. Nesstar was built on a specific format to store data and metadata. Our application is web-based and operates differently. We package data and related documentation in ZIP files, with metadata in JSON, and store the metadata in a database.

Note to Nesstar users: You can convert your Nesstar files by ... (only Windows).

## Specifications considered for future versions

- Multi-lingual DDIs
- Plugins for AI
- CKAN export
- 

## Acknowledgments

The Metadata Editor application was developed by Mehmood Asghar (sotware developer / data engineer) with Olivier Dupriez (statistician / data scientist). Other contributors include:
- ...
- ...
- ...

