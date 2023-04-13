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


## Example 1: Documenting and cataloguing a book

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


> Note that the information you enter or edit is automatically saved.

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

In this second example, we will document a survey dataset (microdata) using the DDI Codebook 2.5. metadata standard. The example will only show some of the core features of the Metadata Editor. See the next sections for a complete overview of the many features of the software.

The dataset we will document --and publish in a NADA catalog-- is a synthetic data representative of a sample household survey for an imaginary country. The dataset and related resources are available in the "demo" folder of the installation package and from the Metadata Editor GitHub repository. The materials include the following files:
- training_survey_data_hh.dta (household-level data file, in Stata 17 format, with 47 variables and 7,975 observations)
- training_survey_data_ind.dta (individual-level data file, in Stata 17 format, with 26 variables and 30,986 observations)
- questionnaire.pdf (a very simplified census questionnaire, in PDF format)
- interviewer_manual.pdf (a very simplified interviewer manual, in PDF format)
- survey_report.pdf (a simplified survey report, with information on the sampling design)
- survey_logo.JPG (a logo, in JPG format)

![image](https://user-images.githubusercontent.com/35276300/215505821-7833f0a5-8c60-45ce-aa69-f08d65fef6f3.png)

All variables and values in the Stata files are labeled. The variable *hid* found in both data files provides a unique identifier of each household and is the key variable to be used to merge the data files. The questionnaire and the interviewer manual are very basic, but contain information that will be used to demonstrate how information on literal questions and universe can be extracted to document variables.

To start documenting the dataset, open the Metadata Editor and login. Then in the Project page, click on "Create new project" and select "Microdata".

A new project home page will open. The navigation bar shown on the left will reflect the content of the default metadata template for "Microdata" (default templates can be changed in the Template Manager).

![image](https://user-images.githubusercontent.com/35276300/231760101-17cceff8-fdc4-4364-ad21-ba189435f3b3.png)


As we have a survey logo, we can change the image that will be used as thumbnail for the project (this is optional). Click on "Change image" and select the file "survey_logo.JPG". The logo will be displayed.

![image](https://user-images.githubusercontent.com/35276300/215513036-95c7d60a-d5b0-44fe-8f76-5a5623185f08.png)

We will use the default metadata template to document the dataset. There is thus no need to "Switch template". 

You are now ready to start documenting the dataset. A description of the metadata elements is available in the Metadata Editor (click on the "?" button next to each metadata element label).

In the section **Document description**, provide information on the metadata. This information is optional. Enter your name and the date the metadata was created.

![image](https://user-images.githubusercontent.com/35276300/231762296-ada64cb1-7ffd-43df-9242-036be6b5f05b.png)


In the section **Study description**, enter the relevant information on the survey. Create a unique identifier, e.g., "DEMO_SVY_001". Then use information you find in the *Survey report* PDF document. It contains the following elements:
- **Title**  
- **Alternate title** (acronym)
- **Abstract**
- **Producer**
- **Type of data**
- **Country**
- **Dates of data collection**
- **Topics**
- **Universe**
- **Sampling**
- ...

![image](https://user-images.githubusercontent.com/35276300/231866451-6a274792-b279-4b83-b43c-8e4479b15824.png)


![image](https://user-images.githubusercontent.com/35276300/231866637-0070b16d-e3af-4239-8581-c32b4cb48020.png)


When the Study information is entered, click on **Data files** in the navigation bar. In the Data files page, click on "Import data". Select the two Stata data files to be imported, then click on "Import files".

![image](https://user-images.githubusercontent.com/35276300/231763763-c4d8a6d3-789b-4cb6-94f3-77680f15cfbf.png)

If the import has been successful, you will be notified. The import will extract all available metadata (variable list, names, variable labels, value labels), and also generate summary statistics. 

![image](https://user-images.githubusercontent.com/35276300/231763936-aac0aea2-eb90-4969-bd37-06a313418816.png)

The Data files page will now display your two files, with some information (number of variables and observations). The two files will be listed in the navigation bar, where you have the option to open a page to view data and to open the variable page for the selected data file.

![image](https://user-images.githubusercontent.com/35276300/231764424-665e13d0-10dd-4cbd-8cc2-2c5af74206fe.png)

> Note: You can preview the data by clicking on "Data". The data cannot be edited in the Metadata Editor, but can be displayed.

![image](https://user-images.githubusercontent.com/35276300/215519930-04363e7a-eb09-423b-abd5-5dc98ee1197a.png)


You will now document the files and variables. 

First, click on the filename of a data file in the navigation tree, and add a brief description of the file (and Save the information entered in the page; this is not automatically saved). Then do the same for the other data file.

![image](https://user-images.githubusercontent.com/35276300/231770783-00000083-5ede-4588-87b4-a5f5cf58fb74.png)


You will then add or edit the information available on each variable, for each data file. In the navigation bar, select "Variables" in the "Data files / *name of the data file*"

![image](https://user-images.githubusercontent.com/35276300/231869134-90b80436-fd54-414e-a74f-99ef86086626.png)

The page will display a list of variables for the selected file, with multiple options to edit and complete the metadata related to the variables. What you can do in this page:

- Edit the variable labels, directly in the variable labels that have been imported from the Stata files can be edited here. What you can do in this page:
- Edit the variables and value labels directly in the variable list
- Edit the value labes for the variable (for Discrete/categorical variables only)
- If necessary, rename, re-order, and delete variables 
- Change the variable type if the type was not correctly identified when the file was imported
- Identify a variable as being a sample weight
- Add metadata related to the variable (literal question, interviewer instructions, derivation and imputation, and more) in the "DOCUMENTATION" tab.
- Identify values to be considered as "missing". The system missing values in Stata or SPSS will be automatically identified as "missing". But in some cases, one (or multiple) values may be used to represent missing values (e.g., "99" representing missing or unknown for a variable *age*)
- Set the weighting coefficient (if relevant) to be applied to generate summary statistics.
- Select the summary statistics you want to be included in the metadata (in tab "STATISTICS")

![image](https://user-images.githubusercontent.com/35276300/231868627-0b5a3fd1-d6c1-49e9-a46f-f6b87d9b17a2.png)

We assume that all variable labels and value labels are good as imported.  

Browse the list of variables to check that their type has been properly detected. The variable hhsize (Household size) in file *training_survey_data_hh.dta* has been imported as a deiscrete variable. This may be fine, but let's assume you want it to be imported as a continuous variable. Change the type from Discrete to Continuous. In that case, you may want to include the mean and standard deviation in the summary statistics.  

![image](https://user-images.githubusercontent.com/35276300/231877710-515e9a3e-3304-4a99-a8e4-c6f492432ac9.png)


You can now add the metadata at the variable level. The tab "DOCUMENTATION" shows the metadata for the variable(s) selected in the list of variables. As we have the questionnaire and a description of the derived variables, we can add the following information: 
- Universe of the variable
- Literal question (for collected variables, not for derived variables)
- Skip instructions
- Interviewer's instructions
- Derivation or imputation methods (for derived variables)

![image](https://user-images.githubusercontent.com/35276300/231875036-f3634de3-2775-423b-909b-80f03b0ad33a.png)

Note that you can enter common metadata to more than one variable by selecting multiple variables (using the SHft or Ctrl key) and entering information for the relevant element(s). For example, the three variables related to education in the individual dataset have the same universe ("Population aged 6 and above"). The three variables can be selected and the information entered in "Universe" will be automatically applied to the 3 variables. 


Set variable "weight" and "popweight" as weighting variables (in both data files). A weight variable should be a continuous variable.

![image](https://user-images.githubusercontent.com/35276300/231873892-4a2d595a-e9e3-42d0-8d04-3d355e3ae89c.png)

Apply the weights to the variables as relevant.

@@@


After entering the variable-level metadata for both files, you will finalize the documentation of the dataset by adding the *external resources*. The external resources are all materials that you want to make accessible to the users of your data catalog when you publish the dataset in a catalog. This includes the microdata, if you want to disseminate them (openly or under restrictions). We will add 4 external resources to our survey metadata: the questionnaire, the interviewer manual, the report, and the dataset (the two Stata files compressed as one zip file; note that you could provide the data in more than one format, e.g., also share a version of the files in CSV and SPSS formats for user convenience).

![image](https://user-images.githubusercontent.com/35276300/215517537-c1d921bc-f75f-4990-83a6-fa150c21f1dc.png)

![image](https://user-images.githubusercontent.com/35276300/215517712-0daf6bed-84cc-452c-9210-b43a57537c3a.png)

![image](https://user-images.githubusercontent.com/35276300/215517972-0ecf6611-f555-4594-b0d2-d5f2cbf51ee6.png)

![image](https://user-images.githubusercontent.com/35276300/215518111-d469812d-13c0-477d-8fb8-55aa2a9ba35a.png)

![image](https://user-images.githubusercontent.com/35276300/215518729-e5253055-3485-4ffd-80ae-74de48590475.png)

The documentation of your dataset is now complete. You can export the DDI and the RDF metadata, and save the package.

![image](https://user-images.githubusercontent.com/35276300/215518944-1b817abb-9b8c-4862-9e85-17a3df30ca19.png)

If you have a NADA catalog: ready to be published. In NADA:

![image](https://user-images.githubusercontent.com/35276300/231880085-adbebea7-0f98-412b-93b2-5318b061dca1.png)

![image](https://user-images.githubusercontent.com/35276300/231879990-7cfa6408-7f5f-4c2c-ab74-1cdf39d97a9d.png)

The dataset will now be accessible and discoverable in your NADA catalog, with detailed metadata including the data dictionary.  

@@@



