# Document

By document, we mean a bibliographic resource of any type such as a book, a working paper or a paper published in a scientific journal, a report, a presentation, a manual, or any another resource consisting mainly of text and available in physical and/or electronic format.

## The metadata schema

Librarians have developed specific standards to describe and catalog documents. The MARC 21 (MAchine-Readable Cataloging) standard used by the United States Library of Congress is one of them. It provides a detailed structure for documenting bibliographic resources, and is the recommended standard for well-resourced document libraries. For the purpose of cataloguing documents in a less-specialized repository intended to accommodate data of multiple types, we built our schema on a simpler but also highly popular standard, the Dublin Core Metadata Element Set. MARC 21 and the Dublin Core are used to document a resource (typically, the electronic file containing the document) and its content. Another schema, BibTex, has been developed for the specific purpose of recording bibliographic citations. BibTex is a list of fields that may be used to generate bibliographic citations compliant with different bibliography styles. It applies to documents of multiple types: books, articles, reports, etc. The metadata schema we propose to document publications and reports is a combination of Dublin Core, MARC 21, and BibTex elements. The technical documentation of the schema and its API is available at https://ihsn.github.io/nada-api-redoc/catalog-admin/#tag/Documents.

![image](https://user-images.githubusercontent.com/35276300/216790862-b8b968a4-42fb-4841-8854-230722892fd0.png)

The Metadata Editor also makes use of the Dublin Core metadata standard to document **external resources**. External resources are files or links that provide content other than the metadata stored in the DDI. This may consist of PDF questionnaires or manuals, scripts, images, or any other resource available in digital format.

## Recommendations

- Including a screenshot of a document cover page in a data catalog adds value.
- Documents should be categorized by type, and the type metadata element should have a controlled vocabulary. If a document can have more than one type, use the tags element (with a tag_group = type) instead of the non-repeatable type element to store this information. Use this information to activate a facet in the catalog user interface. Many users will find it useful to be able to filter documents by type.
- It is highly recommended to obtain a globally unique identifier for each document, such as a DOI, an ISBN, or other.
- Pay attention to capitalization of title and sub-title.
- For bibliographic elements: The elements that are required to form a complete bibliographic citation depend on the type of document. The table below, adapted from the BibTex templates, provides a list of required and optional fields by type of document:


   | Document type                      | Required fields                   | Optional fields                      |
   |------------------------------------|-----------------------------------|--------------------------------------|
   | Article from a journal or magazine | author, title, journal, year  | volume, number, pages, month, note, key  |
   | Book with an explicit publisher    | author or editor, title, publisher, year | volume, series, address, edition, month, note, key  |
   | Printed and bound document without a named publisher or sponsoring institution | title  | author, howpublished, address, month, year, note, key  |
   | Part of a book (chapter and/or range of pages) | author or editor, title, chapter and/or pages, publisher, year | volume, series, address, edition, month, note, key  |
   | Part of a book with its own title | author, title, book title, publisher, year | editor, pages, organization, publisher, address, month, note, key  |
   | Article in a conference proceedings | author, title, book title, year | editor, pages, organization, publisher, address, month, note, key  |
   | Technical documentation | title | author, organization, address, edition, month, year, key  |
   | Master's thesis | author, title, school, year | address, month, note, key  |
   | Ph.D. thesis | author, title, school, year | address, month, note, key  |
   | Proceedings of a conference | title, year | editor, publisher, organization, address, month, note, key  |
   | Report published by a school or other institution, usually numbered within a series | author, title, institution, year | type, number, address, month, note, key |  
   | Document with an author and title, but not <br>formally published | author, title, note | month, year, key |

## Creating a new project

From the Project page, click on "Create new project". When prompted, select "Document".
![image](https://user-images.githubusercontent.com/35276300/216628250-5427e25d-6064-4b27-9c32-ac5edca22f50.png)

The default template is activated. If you need to change the template, select "Switch template".
![image](https://user-images.githubusercontent.com/35276300/216628394-6ddaae2d-3a08-4f4b-b0f5-75560386ebab.png)

We describe the documentation process with an example. This document is the World Bank Policy Working Paper No 9412, titled “Predicting Food Crises” published in September 2020 under a CC-By 4.0 license. The list of authors is provided on the cover page; an abstract, a list of acknowledgments, and a list of keywords are also provided.

![image](https://user-images.githubusercontent.com/35276300/216627559-efab57c2-2cfa-4303-b706-fd5f0ce3d44a.png)
![image](https://user-images.githubusercontent.com/35276300/216627610-f335de54-6370-4fe3-b96b-9e18aa249b82.png)
![image](https://user-images.githubusercontent.com/35276300/216627674-fef8734b-d237-4d81-a680-5878137104dd.png)

If you have the document available as a PDF file, you can create a thumbnail by taking a screenshot of the document cover page. This image will then be used as thumbnail (in a NADA catalog). If you have another image in JPG or PNG format, you may select it using the "...". The thumbnail does not have to be a copy of the cover page, but that is the recommended option.

![image](https://user-images.githubusercontent.com/35276300/216628789-77578460-06bc-40cc-ba29-3026935b5cce.png)
![image](https://user-images.githubusercontent.com/35276300/216628976-bda5e5cb-87c0-4c36-ad47-bde881f6a583.png)

Enter the metadata. Be as complete as you can.

![image](https://user-images.githubusercontent.com/35276300/216636200-68c564e6-3bab-47b5-8bf4-3535f9ed664b.png)

Add the document or a link to the document as an external resource. SAVE.

All complete. No validation error.
![image](https://user-images.githubusercontent.com/35276300/216639994-104e6fb9-676b-4dd8-8074-2f5e095ed26d.png)

Saving the metadata:
Options: RDF and JSON.

![image](https://user-images.githubusercontent.com/35276300/216640188-399fe827-ed26-4184-8969-52d036429b83.png)


The metadata can be published in a NADA catalog. 
![image](https://user-images.githubusercontent.com/35276300/216627755-eab6373f-eaf1-430c-b891-2a591863dfe4.png)





## Editing a project

