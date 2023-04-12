# Quick start

We demonstrate in this "quick start" section how a survey dataset and a document are documented using the Metadata Editor. This quick start only shows some of the  core features of the application. The documentation of a survey dataset is somewhat more complex than the documentation of resources like documents, indicators, tables, and others, which do not require importing datasets.

The following two resources are used in the quick start:
- A Book, available on-line and in PDF format.
- A synthetic micro-dataset that contains two data files, provided in Stata 15 format. The dataset and some related materials are available in the folder "demo" of the Metadata Editor installation package, and also available on-line from the [Metadata Editor GitHub repository]().  

We show how the resources are documented and published in an on-line NADA catalog. NADA is an open source cataloguing application compatible with all metadata standards covered by the Metadata Editor.  

We assume that you have successfully installed the Metadata Editor and have obtained a user account from the administrator. 

Login using your username and password.

![image](https://user-images.githubusercontent.com/35276300/231554433-0e14708b-8208-4881-bd36-c0d8fe065d41.png)

Go to the "Projects" page of the Metadata Editor. If you are using it for the first time, you will see an empty page. The project page is where all projects you created and projects that have been shared by others with you will be displayed.

![image](https://user-images.githubusercontent.com/35276300/231555203-fb3184c0-d93d-4417-8411-4d21afe655a1.png)


## Example 1: Documenting and cataloguing a working paper

The book we will document is titled "The Analysis of Household Surveys: A Microeconometric Approach to Development Policy" by Angus Deaton (2019), openly available from the World Bank's Open Knowledge Repository at http://hdl.handle.net/10986/30394.

Click on "Create project" and select "Document".

![image](https://user-images.githubusercontent.com/35276300/231555537-3b7d3770-45a5-404b-be5c-0996eb44bfac.png)
  
  
The new project page will be displayed in a new tab.

![image](https://user-images.githubusercontent.com/35276300/231556271-faf830ff-8144-4b70-a7cf-a8428e464ce2.png)


In "Project thumbnail", select "Change image" and upload an image of the cover page (a jpg file is provided in the demo files; you may of course create such a file by taking a screenshot of the coverpage of a document, or through other means).

![image](https://user-images.githubusercontent.com/35276300/231557017-955ebfa4-6ad1-4058-8477-42209c0fa7d7.png)


We will use the default Template, so there is no need to Switch template.

A description of each metadata element is available in the Metadata Editor itself, and in our [Schema Guide](). To display the description of an element in the Metadata Editor, click on the "?" icon next to the element's label.

![image](https://user-images.githubusercontent.com/35276300/231560970-7d844b03-8e5e-49ed-bbdf-6e66a9ad6b39.png)


In the left bar navigation tree, select "Metadata information". This section of the metadata standard is only used to provide information on who documented the Publication and when. All these fields are optional. We recommend to enter the name of the person who generate the metadata, and the date the metadata was created (dates should always be entered in ISO format YYYY-MM-DD).

![image](https://user-images.githubusercontent.com/35276300/231557524-ab9a2faa-902b-4956-a344-f9d6745f91e3.png)


Note that the information you enter or edit is automatically saved.

Now we can start entering the metadata related to the document itself. The metadata elements used for this purpose are found in the "Document Description" section of the metadata standard. In the navigation tree, select "Title statement" and enter a unique identifier, possibly an alternate identifier, and the title. Note that most elements are optional, but the main unique identifier and the title are required.

![image](https://user-images.githubusercontent.com/35276300/231562599-fc4a27cf-689d-467b-abab-9395eb5d851a.png)


Proceed with the other sections. You will find most of the necessary information in the World Bank Open Knowledge Repository page:
- **Title**: 
- **Author**: Deaton, Angus
- **Date issued** (in ISO format YYYY-MM-DD): 2019-01-16 
- **Abstract**: Two decades after its original publication, The Analysis of Household Surveys is reissued with a new preface by its author, Sir Angus Deaton, recipient of the 2015 Nobel Prize in Economic Sciences. This classic work remains relevant to anyone with a serious interest in using household survey data to shed light on policy issues. This book reviews the analysis of household survey data, including the construction of household surveys, the econometric tools useful for such analysis, and a range of problems in development policy for which this survey analysis can be applied. The author's approach remains close to the data, using transparent econometric and graphical techniques to present data in a way that can clearly inform policy and academic debates. Chapter 1 describes the features of survey design that need to be understood in order to undertake appropriate analysis. Chapter 2 discusses the general econometric and statistical issues that arise when using survey data for estimation and inference. Chapter 3 covers the use of survey data to measure welfare, poverty, and distribution. Chapter 4 focuses on the use of household budget data to explore patterns of household demand. Chapter 5 discusses price reform, its effects on equity and efficiency, and how to measure them. Chapter 6 addresses the role of household consumption and saving in economic development. The book includes an appendix providing code and programs using STATA, which can serve as a template for the users' own analysis.
- **Language**: English (EN)
- **Publisher**: World Bank, Washington, DC
- **Rights**: CC BY 3.0 IGO
- **Keywords**: Household surveys; Survey design; Data collection; Developing Countries; Economic development; development policy
- **Topics**: Development Patterns and Poverty; Living Standards; Poverty Assessment; Poverty and Policy; Statistical & Mathematical Sciences
- **Type**: book

![image](https://user-images.githubusercontent.com/35276300/231564684-1e2720c7-47c3-4fa0-b06b-11fcb7a5da19.png)


Note that other metadata elements can be filled out: table of content, and other. Always try and produce the most comprehensive metadata.

So far, we have only generated metadata. When we publish the metadata, we also want to provide users with either a link to the book, or with an option to download the book from the catalog. The last step before we publish the document in our on-line catalog is thus to provide information on the location (file or URL) of the book (and any other related files and links we may want to attach to the book's metadata.) These related materials are called "External resources".

Select "External resources" in the navigation tree, then click on "Create resource". A new resource page will open, where you can describe the resource. Most elements are optional. We will just enter the title, type, and link which are the required elements, the author and date (optional), and the link to the book. When published in an on-line catalog, the user interested in the book will be provided with this link to the World Bank website. Should we enter a PDF file, the application will upload the PDF to the web server and the book will be available directly from the catalog (an option we do not take, as we are not mandated to redistribute the book).


![image](https://user-images.githubusercontent.com/35276300/231566591-5c82b41d-782a-4a8b-bce0-0418874b8204.png)


![image](https://user-images.githubusercontent.com/35276300/231567718-dd93c6dc-d4ca-480d-8d7e-42bd00c37f26.png)


Note that contrary to the metadata on the document, the information on external resources is NOT saved automatically. Click on **Save*** to save the information you just entered.

You have now completed the documentation of the book. The Projects page will now list one entry. You may at any time go back and edit or complete the documentation of the book.

![image](https://user-images.githubusercontent.com/35276300/231568157-ca5555e4-acba-441d-8c9f-c6f931697233.png)


If you have a NADA catalog and the credentials to publish content in it, you can do so directly from the Metadata Editor. If you are not authorized to administer the catalog, an administrator can publish the data from the Metadata Editor, or thourg other means (e.g., by uploading the saved metadata using the NADA catalog import features).

To publish the metadata in the NADA catalog, click on "Publish to NADA" in the "Project" main menu item.

![image](https://user-images.githubusercontent.com/35276300/231568894-e364c4f4-49a3-4168-8cda-eba631152a5f.png)


Fill out the information on the catalog where the book will be published and other options (see NADA documentation). Then click on **"Publish"**.

![image](https://user-images.githubusercontent.com/35276300/231570809-52503f70-60bc-4e13-b352-97746a74ead9.png)


If you are successful, you will be notified.

![image](https://user-images.githubusercontent.com/35276300/231570928-16efaa90-4d65-4855-a99a-82cec3134de1.png)


The book is now available in your catalog.


![image](https://user-images.githubusercontent.com/35276300/231571821-ffd10899-5dd9-4318-ac84-2fd13986eb24.png)



## Example 2: Documenting and cataloguing a survey dataset

The dataset we will document and publish in a NADA catalog 
files included in this small demo package are the data and metadata related to the 2010 Population Census of an imaginary country named Popstan. This very basic package includes the following files:

- popstan_2010_hld.dta (household-level data file, in Stata 17 format, with 13 variables and 37,000 observations)
- popstan_2010_ind.dta (individual-level data file, in Stata 17 format, with 14 variables and 151,728 observations)
- Popstan_2010_questionnaire.pdf (a very simplified census questionnaire, in PDF format)
- Popstan_2010_Interviewer_Manual.pdf (a very simplified interviewer manual, in PDF format)
- popstan_2010_census_logo.JPG (a logo, in JPG format)

![image](https://user-images.githubusercontent.com/35276300/215505821-7833f0a5-8c60-45ce-aa69-f08d65fef6f3.png)

All variables and values in the Stata files are labeled. The variable *serial* found in both data files provides a unique identifier of each household and is the key variable to be used to merge the data files. The questionnaire and the interviewer manual are very basic, but contain information that will be used to demonstrate how information on literal questions and universe can be extracted to document variables.

To start documenting the Popstan Census dataset, open the Metadata Editor and login. In the Metadata Editor Home page, select "Create new project" and when prompted select "Microdata".

![image](https://user-images.githubusercontent.com/35276300/215510952-f91ec49d-8c4e-451e-8242-12d192000e80.png)

A new project home page will open. The navigation bar shown on the left will reflect the content of the default metadata template for "Microdata" (default templates can be changed in the Template Manager).

![image](https://user-images.githubusercontent.com/35276300/215838912-357f1791-406c-4f6c-96c4-da828b9f029a.png)

As we have a logo for the Census, you may change the image that will be used as thumbnail for the project (this is optional). Click on "change image" and select the file "census_logo.JPG". The logo will be displayed.

![image](https://user-images.githubusercontent.com/35276300/215513036-95c7d60a-d5b0-44fe-8f76-5a5623185f08.png)

We will use the default metadata template to document the dataset. There is thus no need to "switch template". To use another template, you would simply click on "Swith template" and select an available template from the list.

![image](https://user-images.githubusercontent.com/35276300/215835375-203514e6-9f01-4773-a07e-ba9a2ee55c74.png)

You are now ready to start documenting the dataset.

In the section **Document description**, provide information on the metadata.

![image](https://user-images.githubusercontent.com/35276300/215514475-5cb765e9-6b81-4830-a62d-49bb28fb7f90.png)

In the section **Study description**, enter the relevant information on the Census. As not all information is provided in the demo files, feel free to create information. The *Title* should be ... The *dates of data collection* should have 2010 as year. The *Country* should be "Popstan". 

![image](https://user-images.githubusercontent.com/35276300/215514878-988856fc-ef26-42c0-9b4e-02ba6c12d8f7.png)

When the Study information is entered, click on **Data files** in the navigation bar. You will first import the two data files.

![image](https://user-images.githubusercontent.com/35276300/215515050-6ba3a072-cb37-45c2-9136-27618121ea5d.png)

![image](https://user-images.githubusercontent.com/35276300/215515365-18c932ef-d45f-40ff-8732-368f94cf0ec1.png)

You can preview the data by clicking on "Data". The data cannot be edited in the Metadata Editor, but can be displayed.
![image](https://user-images.githubusercontent.com/35276300/215519930-04363e7a-eb09-423b-abd5-5dc98ee1197a.png)

For each data file, add a brief description (and Save the information entered in the page).

![image](https://user-images.githubusercontent.com/35276300/215515702-fb3448ae-81e4-4c34-a27e-8c32eb3dcc49.png)

You may now add or edit the information available on each variable, for each data file. The list of variables is provided in the form of a table. The variable labels imported from Stata can be edited directly in the table. 

![image](https://user-images.githubusercontent.com/35276300/215516149-43bb069f-5db8-42cc-8915-b5f653a5b7ec.png)

Summary statistics are displayed. You may control the statistics to be included in the metadata.

![image](https://user-images.githubusercontent.com/35276300/215844294-82c6d847-d126-44a3-bd46-67120992549a.png)

More important, metadata can be added. We will add the formulation of the question, the universe, and the interviewer instructions. This can be copy/pasted from the PDF files.

![image](https://user-images.githubusercontent.com/35276300/215517055-b5df50ac-e647-4c09-a46a-3f510430cfed.png)

Note that you can enter common metadata to more than one variable by selecting multiple variables (using the SHft or Ctrl key) and entering information for the relevant element(s). For example, the three variables related to education in the individual dataset have the same universe ("Population aged 6 and above"). The three variables can be selected and the information entered in "Universe" will be automatically applied to the 3 variables. 

After entering the variable-level metadata, you can add the *external resources*. Here, we will create 3 external resources: the questionnaire, the interviewer manual, and the dataset (the two data files compressed as a zip file).

![image](https://user-images.githubusercontent.com/35276300/215517537-c1d921bc-f75f-4990-83a6-fa150c21f1dc.png)

![image](https://user-images.githubusercontent.com/35276300/215517712-0daf6bed-84cc-452c-9210-b43a57537c3a.png)

![image](https://user-images.githubusercontent.com/35276300/215517972-0ecf6611-f555-4594-b0d2-d5f2cbf51ee6.png)

![image](https://user-images.githubusercontent.com/35276300/215518111-d469812d-13c0-477d-8fb8-55aa2a9ba35a.png)

![image](https://user-images.githubusercontent.com/35276300/215518729-e5253055-3485-4ffd-80ae-74de48590475.png)

The documentation of your dataset is now complete. You can export the DDI and the RDF metadata, and save the package.

![image](https://user-images.githubusercontent.com/35276300/215518944-1b817abb-9b8c-4862-9e85-17a3df30ca19.png)

If you have a NADA catalog: ready to be published. In NADA:


