# Specifications

## Specifications of the current version

The Metadata Editor is a multi-platform application developed for the production of metadata compliant with multiple metadata standards and schemas. The application is highly flexible, and may in the future accommodate additional standards and schemas, as well as new versions of standards an schemas already supported.

The application works as a stand-alone application or as a server application. Operating the application on a server allows curation teams to collaborate on the documentation of datasets in the context of a data archive or data library. 

The Metadata Editor is built on APIs. It makes use of Python (Pandas) for importing microdata from different formats and to generate summary statistics. 

The Metadata Editor was developed in part to replace the Nesstar Publisher application, created in 2000 by the former Norwegian Social Science Data Services. The Nesstar Publisher ceased to be maintained in 2022. Although the Metadata Editor presents similarities with the Nesstar Publisher, there are major differences between the two applications. First, the Metadata Editor is multi-standard (while the Nesstar Publisher was designed to be a DDI 1.n editor). Second, the Nesstar Publisher was a strictly stand-alone application that made use of its own specific format to store the data and metadata, while the Metadata Editor is a web-based application that relies on a database, converts data into CSV for internal storage, and packages the data, metadata, and related resources in ZIP files with metadata stored in JSON format.

