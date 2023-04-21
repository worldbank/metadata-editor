# Quick start

We demonstrate in this "quick start" section how a book and a survey dataset are documented using the Metadata Editor. The documentation of a survey dataset is somewhat more complex than the documentation of resources like documents, indicators, tables, and others, which do not require importing datasets. But the core principles are the same for all data types. This quick start only shows some of the core features of the application. 

The following two resources are used in the quick start:
- A Book, available on-line and in PDF format.
- A synthetic micro-dataset that contains two data files, provided in Stata 15 format. The dataset and some related materials are available in the folder "demo" of the Metadata Editor installation package, and also available on-line from the [Metadata Editor GitHub repository]().  

We show how the resources are documented and published in an on-line NADA catalog. NADA is an open source cataloguing application compatible with all metadata standards covered by the Metadata Editor.  

We assume that you have successfully installed the Metadata Editor. For publishing in NADA (optional), we assume that you have administrator access to a NADA catalog. 

To start, login with your username and password.

![image](https://user-images.githubusercontent.com/35276300/231554433-0e14708b-8208-4881-bd36-c0d8fe065d41.png)

Then go to the "Projects" page of the Metadata Editor. The project page is where all projects you created and projects that have been shared by others with you will be displayed. If you are using the application for the first time, you will see an empty Project list. 

![image](https://user-images.githubusercontent.com/35276300/231555203-fb3184c0-d93d-4417-8411-4d21afe655a1.png)


## Example 1: Documenting and cataloguing a book

The book we will document is titled "The Analysis of Household Surveys: A Microeconometric Approach to Development Policy" by Angus Deaton (2019), a book freely available from the World Bank's Open Knowledge Repository at http://hdl.handle.net/10986/30394.

Click on "Create project" and, when prompted, select "Document".

![image](https://user-images.githubusercontent.com/35276300/231555537-3b7d3770-45a5-404b-be5c-0996eb44bfac.png)
  
  
A project page will be displayed in a new tab.

![image](https://user-images.githubusercontent.com/35276300/231556271-faf830ff-8144-4b70-a7cf-a8428e464ce2.png)


In "Project thumbnail", select "Change image" and upload an image of the cover page (a jpg file is provided in the demo files; you may of course create such a file by taking a screenshot of the coverpage of a document, or through other means). This is optional. The image will be used a sthumbnail in the NADA catalog.

![image](https://user-images.githubusercontent.com/35276300/231557017-955ebfa4-6ad1-4058-8477-42209c0fa7d7.png)


To document the book, we will use the default metadata template, so there is no need to "Switch template" (a template is a customized subset of metadata elements used to document a resource).

Each metadata element is described in the Metadata Editor. To display the description of an element, click on the "?" icon next to the element's label.

![image](https://user-images.githubusercontent.com/35276300/231560970-7d844b03-8e5e-49ed-bbdf-6e66a9ad6b39.png)


In the left navigation tree, select "Metadata information". This section contains optional elements used to capture information on who documented the Publication and when. We recommend to enter the name of the person who generate the metadata, and the date the metadata was created (dates should always be entered in ISO format YYYY-MM-DD).

![image](https://user-images.githubusercontent.com/35276300/231557524-ab9a2faa-902b-4956-a344-f9d6745f91e3.png)


> Note that the information you enter or edit is automatically saved.


You can now start entering the metadata related to the book itself. The metadata elements used for this purpose are found in the "Document Description" section. In the navigation tree, first select "Title statement" and enter a unique identifier, possibly an alternate identifier, and the title of the book. Note that most elements are optional, but the unique identifier and the title are required.

![image](https://user-images.githubusercontent.com/35276300/231562599-fc4a27cf-689d-467b-abab-9395eb5d851a.png)


Then proceed with the other sections in the navigation tree and fill out elements for which you have content. You will find most of the necessary information in the World Bank Open Knowledge Repository page:
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


Other metadata elements can be filled out: for example, you could extract the table of content from the book itself (available in PDF). Always try and produce the most comprehensive metadata.

Once you have entered the available information in all relevant sections of the "Document description", you have completed the metadata production. But when you publish these metadata in your datalog, you probably also want to provide users with either a link to the book, or with an option to download the PDF version of the book directly from your catalog. The last step before we publish the entry in the catalog is thus to provide information on the location (file or URL) of the book (and any other related files and links we may want to attach to the book's metadata.) These related materials are called "External resources".

Select "External resources" in the navigation tree, then click on "Create resource". A new resource page will open, where you can describe the resource. Most elements are optional. We will just enter the title, type, author, and date, and an external link to the book (as you are not mandated to redistribute the book, you should provide a link to its originating repository, not an option to downlaod it from your catalog). Note that contrary to the metadata on the document, the information on external resources is NOT saved automatically. You must click on **Save*** to save the information you just entered.

![image](https://user-images.githubusercontent.com/35276300/231566591-5c82b41d-782a-4a8b-bce0-0418874b8204.png)


![image](https://user-images.githubusercontent.com/35276300/231567718-dd93c6dc-d4ca-480d-8d7e-42bd00c37f26.png)


You have now completed the documentation of the book. The "Projects" page will show this new entry. You may at any time go back to it to edit or complete the metadata.

![image](https://user-images.githubusercontent.com/35276300/231568157-ca5555e4-acba-441d-8c9f-c6f931697233.png)


If you have a NADA catalog and the credentials to publish content in it, you can do so directly from the Metadata Editor (note that this is not the only option to add and manage content in NADA). To publish the metadata in the NADA catalog, click on "Publish to NADA" in the "Project" main menu item.

![image](https://user-images.githubusercontent.com/35276300/231568894-e364c4f4-49a3-4168-8cda-eba631152a5f.png)


Fill out the information on the catalog where the book will be published and other options. Then click on **"Publish"**. For this demo, only change the following option:
@@@

![image](https://user-images.githubusercontent.com/35276300/231570809-52503f70-60bc-4e13-b352-97746a74ead9.png)

The book is now available in your NADA catalog.

![image](https://user-images.githubusercontent.com/35276300/231571821-ffd10899-5dd9-4318-ac84-2fd13986eb24.png)


## Example 2: Documenting and cataloguing a survey dataset

In this second example, we will document a survey dataset (microdata) using the DDI Codebook metadata standard. We only show some of the core features of the Metadata Editor. See the next sections for a complete overview of the many features provided by the software.

The dataset we will document --and publish in a NADA catalog-- is a synthetic data representative of a sample household survey for an imaginary country. The dataset and related resources are available in the "demo" folder of the installation package and from the Metadata Editor GitHub repository. The materials include the following files:
- Stata (version 17) data files with all variables and values labeled:
  - training_survey_data_hh.dta (household-level data file, in Stata 17 format, with 47 variables and 7,975 observations). 
  - training_survey_data_ind.dta (individual-level data file, in Stata 17 format, with 26 variables and 30,986 observations). All variables and values are labeled.
 - Survey questionnaire and documentation in MS-Excel file "synthetic_survey_questionnaire_info.xlsx". This file contains:
  - A simplified survey questionnaire (sheets "Household form EN" for variables collected at the household level, and "Individual form EN" for variables collected at the individual level.)
  - A simplified survey report, with information on the sampling design (sheet "Survey info")
- Other:
  - survey_logo.JPG (a logo for the survey, in JPG format)

![image](https://user-images.githubusercontent.com/35276300/233700458-8e1526d5-8e02-433d-af6a-086b6219cc65.png)
 

To start documenting the dataset, open the Metadata Editor and login. Then in the Project page, click on "Create new project" and select "Microdata". A new project home page will open. The navigation bar shown on the left will reflect the content of the default metadata template for "Microdata" (default templates can be changed in the Template Manager).

![image](https://user-images.githubusercontent.com/35276300/231760101-17cceff8-fdc4-4364-ad21-ba189435f3b3.png)


We can change the image that will be used as thumbnail for the project (this is optional). Click on "Change image" and select the file "survey_logo.JPG". 

![image](https://user-images.githubusercontent.com/35276300/215513036-95c7d60a-d5b0-44fe-8f76-5a5623185f08.png)

We will use the default metadata template to document the dataset. There is thus no need to "Switch template". You are now ready to start documenting the dataset. 

In the section **Document description**, enter information on the metadata. This information is optional. Enter your name and the date the metadata was created.

![image](https://user-images.githubusercontent.com/35276300/231762296-ada64cb1-7ffd-43df-9242-036be6b5f05b.png)


In the section **Study description**, enter the relevant information on the survey. First, create a unique identifier for the dataset, e.g., "DEMO_SVY_001". Then use information you find in sheet "Survey info" of the Excel file. All information contained in this sheet should be entered in the corresponding metadata elements. Browse the navigation tree and find the most appropriate elements for each piece of information. Use the "?" next to each element label to view a description if necessary.

![image](https://user-images.githubusercontent.com/35276300/233703241-b06145e0-e4e3-4486-93ca-37296a0e04c6.png)

When all available "Study information" is entered, click on **Data files** in the navigation bar. In the Data files page, click on "Import data". Select the two Stata data files to be imported, then click on "Import files".

![image](https://user-images.githubusercontent.com/35276300/231763763-c4d8a6d3-789b-4cb6-94f3-77680f15cfbf.png)

The import will extract all available metadata (variable list, names, variable labels, value labels), and also generate summary statistics. 

![image](https://user-images.githubusercontent.com/35276300/231763936-aac0aea2-eb90-4969-bd37-06a313418816.png)

The "Data files" page will now display your two files. The two files will also be listed in the navigation bar.

![image](https://user-images.githubusercontent.com/35276300/233703124-0c4c098d-7cfb-4389-8d65-abc27e1d7d8e.png)

You can preview the data by clicking on "Data". Note that the data cannot be edited in the Metadata Editor.

![image](https://user-images.githubusercontent.com/35276300/233704435-4c4f6229-6b80-46b9-8b51-7755776dd8df.png)

You will now document the data files and the variables they contain. 

First, click on the filename of a data file in the navigation tree, and add a brief description of the file (and save the information entered in the page, which is not automatically saved). Then do the same for the other data file.

![image](https://user-images.githubusercontent.com/35276300/231770783-00000083-5ede-4588-87b4-a5f5cf58fb74.png)

You will then add or edit the information available on each variable, for each data file. In the navigation bar, select "Variables" in the "Data files / *name of the data file*"

![image](https://user-images.githubusercontent.com/35276300/233704533-62d1362a-cfe3-46eb-a77f-cb4a1a69bf8e.png)

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

![image](https://user-images.githubusercontent.com/35276300/233704709-a211367a-53e2-42fe-b472-b07fff4e6d99.png)

We assume that all variable labels and value labels are good as imported.  

Browse the list of variables to check that their type has been properly detected. The variable hhsize (Household size) in file *training_survey_data_hh.dta* has been imported as a deiscrete variable. This may be fine, but let's assume you want it to be imported as a continuous variable. Change the type from Discrete to Continuous.  

![image](https://user-images.githubusercontent.com/35276300/231877710-515e9a3e-3304-4a99-a8e4-c6f492432ac9.png)

Now, add the metadata specific to each variable. Most of the metadata at variable level will typically be found in the survey questionnaire and in the interviewer manual. The tab "DOCUMENTATION" shows the metadata for the variable(s) selected in the list of variables. Add the following information: 
- Universe of the variable
- Pre-question, literal question, and post-question (for collected variables, not for derived variables)
- Skip instructions if any
- Derivation or imputation methods (for derived variables)

![image](https://user-images.githubusercontent.com/35276300/233705597-9b6a4e8b-c65b-4f65-8994-1d029cc60900.png)

Note that you can enter common metadata to more than one variable by selecting multiple variables (using the SHft or Ctrl key) and entering information for the relevant element(s). For example, the three variables related to education in the individual dataset have the same universe ("Population aged 6 and above"). The three variables can be selected and the information entered in "Universe" will be automatically applied to the 3 variables. 

Set variable "weight" (and also "popweight" in the household-level data file) as weighting variables. 

![image](https://user-images.githubusercontent.com/35276300/231873892-4a2d595a-e9e3-42d0-8d04-3d355e3ae89c.png)

If you want to display and store weighted summary statistics, apply the weights to the variables as relevant. You do this in the tab WEIGHTS.

![image](https://user-images.githubusercontent.com/35276300/233706439-577b3451-9470-4104-ae2d-5bd8e880a94c.png)

First, select the variables to which you want to apply a weighting coefficient, then selecting the weight variable. For the household-level file, you may first select all variables except the quintile variables, then select weigh variable "hhweight" (only variables that have been tagged as weight will be listed in the selection).

![image](https://user-images.githubusercontent.com/35276300/233706693-b4193d98-8cc6-41aa-af6f-39448a3f7272.png)

Then do the same for the 3 quintile variables, but using the "popweight" variable.

![image](https://user-images.githubusercontent.com/35276300/233706968-8f314f5f-8c8a-4fa1-bb2c-d9ea4b5da48a.png)

The summary statistics will now display both the unweighted and weighted values.

As a last step in documenting variables, browse the variables and check that the summary statistics that have been selected for each variable correspond to what you want to store in your metadata. Do not include means or standard deviation for categorical variables.

![image](https://user-images.githubusercontent.com/35276300/233707524-17819c81-00fc-49d0-b464-6206526b3fbc.png)


After entering the variable-level metadata for both files, you will finalize the documentation of the dataset by documenting and attaching the *external resources* to the survey metadata. The external resources are all materials that you want to make accessible to the users when you publish the dataset in a catalog. This includes the microdata files, if you want to disseminate them (openly or under restrictions). We will add 2 external resources to our survey metadata: the Excel file that contains the questionnaire, and the dataset (the two Stata files compressed as one zip file; note that you could provide the data in more than one format, e.g., also share a version of the files in CSV and SPSS formats for user convenience).

Click on "External resources" in the navigation tree, then click on "Create resource". Select the file type (respectively "questionnaire" and "microdata" for our two external resources, and give each a short Title).

![image](https://user-images.githubusercontent.com/35276300/233708077-2830f860-5720-4ccd-b1a6-ad937eb4fff4.png)

Provide a link to the resource, or select the file you want to upload in your online catalog. Then click SAVE.

![image](https://user-images.githubusercontent.com/35276300/233708637-2432c485-6ac4-472f-a2f4-2e5f010ef195.png)

The documentation of your dataset is now complete. You can export the DDI and the RDF metadata, and save the package.

![image](https://user-images.githubusercontent.com/35276300/215518944-1b817abb-9b8c-4862-9e85-17a3df30ca19.png)

If you have a NADA catalog and the credentials, you can publish your data and metadata in the catalog.

![image](https://user-images.githubusercontent.com/35276300/233709774-c049244a-fea6-45f4-93fc-8f3057bf7ec3.png)

The dataset will now be accessible and discoverable in your NADA catalog, with detailed metadata including the data dictionary.  

![image](https://user-images.githubusercontent.com/35276300/233710003-ae1dcaf6-f744-4380-b177-a707bd06300b.png)



