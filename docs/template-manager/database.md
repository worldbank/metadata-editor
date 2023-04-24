# Database

## The Database schema

Time series are often contained in multi-indicators databases, like the World Bank’s World Development Indicators (WDI), whose on-line version contains series for 1,430 indicators (as of 2023). The Metadata Editor propose two different metadata schemas for documenting databases like the WDI: one to document each series/indicators individually, and the other one to document the database they belong to. In this chapter, we describe the metadata schema used to describe the database (i.e. the container of indicators).


## Creating or editing a database project

You create a new database project by clicking on "Create new project" in the Projects page and, when prompted, select "Database" as data type. Enter metadata with as much relevant information as possible. In the example, below, we use the World Bank's World Development Indicator (WDI) database as an example. 

![image](https://user-images.githubusercontent.com/35276300/217087648-14c1b83b-f9e4-4784-a4ea-17b5133d4484.png)


## Publishing in NADA

In a NADA catalog, the information on a database can be published as an entry or as a complement to the information on a time series (or both). 

If the metadata on the database is published as an entry *per se* (to build a catalog of databases), a tab "Databases" will be displayed in the catalog results page and the metadata will be indexed and searchable in the catalog.
@@@ put an image with a "Database" tab
![image](https://user-images.githubusercontent.com/35276300/234088388-0b20ce06-f750-49ac-9172-a79dd5641dc2.png)

If the database metadata is published as a complement of information to an indicator/time series entry (in which case the database metadata will only be displayed in a tab -see screenshot below- and will not be indexed and searchable). 

![image](https://user-images.githubusercontent.com/35276300/217082831-f29fd693-3980-4407-97d4-f6c4741ef245.png)

For the database to be displayed as a complement of information to an indicator page, the database must be published in NADA, and its unique identifier must be entered in the indicator's metadata.

In the database metadata:
![image](https://user-images.githubusercontent.com/35276300/234084921-7ad19a28-210f-4e11-a33e-61b21cce0aba.png)

In the indicator/time series metadata:
![image](https://user-images.githubusercontent.com/35276300/234085195-80b9ede8-fdd8-438a-947d-8898d12e8fc1.png)

