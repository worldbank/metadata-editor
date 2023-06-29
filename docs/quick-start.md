# Quick start

In this "quick start" section, we demonstrate how to use the Metadata Editor to document a publication and a survey dataset. While documenting a survey dataset is more complex than documenting resources such as documents, indicators, tables, etc., which do not require importing datasets, the core principles remain the same for all data types. This quick start covers only some of the core features of the application.

We will be using the following two resources in this quick start:

- A book, available in both online and PDF format.
- A synthetic micro-dataset consisting of two data files provided in Stata 15 format. The dataset, along with some related materials, can be found in the "demo" folder of the Metadata Editor installation package or online in the Metadata Editor GitHub repository.
- We will demonstrate how to document and publish these resources in an online NADA catalog. NADA is an open-source cataloging application compatible with all metadata standards supported by the Metadata Editor.

We assume that you have successfully installed the Metadata Editor. If you want to pilot the publishing of a project in an online catalog, we also assume that you have administrator access to a NADA catalog.

To begin, log in with your username and password, and navigate to the "My projects" page of the Metadata Editor. This page displays all the projects you have created and those that have been shared with you by others. 

![image](https://user-images.githubusercontent.com/35276300/231554433-0e14708b-8208-4881-bd36-c0d8fe065d41.png)

If you are using the application for the first time, the project list will be empty.

![image](https://github.com/ihsn/editor/assets/35276300/2ff6b678-6e5f-4aee-87d2-a73dd75c9fc9)


## Example 1: Documenting and cataloguing a book

The book we will be documenting is titled "The Analysis of Household Surveys: A Microeconometric Approach to Development Policy" by Angus Deaton (2019). This book is freely available from the World Bank's Open Knowledge Repository at http://hdl.handle.net/10986/30394.

To get started, click on "Create project" and select "Document" when prompted.

![image](https://github.com/ihsn/editor/assets/35276300/f52bcdc3-c677-4a72-90f4-a3d1da3b65ac)
  
A new project page will open in a new tab.

![image](https://user-images.githubusercontent.com/35276300/231556271-faf830ff-8144-4b70-a7cf-a8428e464ce2.png)

In the "Project thumbnail" section, you can optionally upload an image of the book's cover page (or another image) as a thumbnail image for the NADA catalog. Select "Change image" and upload a .jpg file (a demo file is provided, but you may provide your own image).

![image](https://user-images.githubusercontent.com/35276300/231557017-955ebfa4-6ad1-4058-8477-42209c0fa7d7.png)

For documenting the book, we will use the default metadata template provided, so there is no need to "Switch template". A template is a customized subset of metadata elements used to document a resource.

Each metadata element is described in the Metadata Editor, and you can click on the "?" icon next to the element's label to view the description.

![image](https://user-images.githubusercontent.com/35276300/231560970-7d844b03-8e5e-49ed-bbdf-6e66a9ad6b39.png)

On the left navigation tree, select "Metadata information" to enter optional elements used to capture information on who documented the publication and when. We recommend entering the name of the person who generated the metadata, and the date the metadata was created, using the ISO format YYYY-MM-DD.

![image](https://user-images.githubusercontent.com/35276300/231557524-ab9a2faa-902b-4956-a344-f9d6745f91e3.png)

> Any information you enter or edit is automatically saved.

You can now start entering the metadata related to the book itself in the "Document Description" section. In the navigation tree, first select "Title statement" and enter a unique identifier, an alternate identifier (if available), and the title of the book. Note that while most elements are optional, the unique identifier and the title are required.

![image](https://user-images.githubusercontent.com/35276300/231562599-fc4a27cf-689d-467b-abab-9395eb5d851a.png)


Then proceed with the other sections in the navigation tree and fill out elements for which you have content. You will find most of the necessary information in the World Bank Open Knowledge Repository page:

- **Title**: The Analysis of Household Surveys: A Microeconometric Approach to Development Policy
- **Author**: Angus Deaton
- **Date issued** (in ISO format YYYY-MM-DD): 2019-01-16 
- **Abstract**: Two decades after its original publication, The Analysis of Household Surveys is reissued with a new preface by its author, Sir Angus Deaton, recipient of the 2015 Nobel Prize in Economic Sciences. This classic work remains relevant to anyone with a serious interest in using household survey data to shed light on policy issues. This book reviews the analysis of household survey data, including the construction of household surveys, the econometric tools useful for such analysis, and a range of problems in development policy for which this survey analysis can be applied. The author's approach remains close to the data, using transparent econometric and graphical techniques to present data in a way that can clearly inform policy and academic debates. Chapter 1 describes the features of survey design that need to be understood in order to undertake appropriate analysis. Chapter 2 discusses the general econometric and statistical issues that arise when using survey data for estimation and inference. Chapter 3 covers the use of survey data to measure welfare, poverty, and distribution. Chapter 4 focuses on the use of household budget data to explore patterns of household demand. Chapter 5 discusses price reform, its effects on equity and efficiency, and how to measure them. Chapter 6 addresses the role of household consumption and saving in economic development. The book includes an appendix providing code and programs using STATA, which can serve as a template for the users' own analysis.
- **Language**: English (EN)
- **Publisher**: World Bank, Washington, DC
- **Rights**: CC BY 3.0 IGO
- **Keywords**: Household surveys; Survey design; Data collection; Developing Countries; Economic development; development policy
- **Topics**: Development Patterns and Poverty; Living Standards; Poverty Assessment; Poverty and Policy; Statistical & Mathematical Sciences
- **Type**: book

![image](https://user-images.githubusercontent.com/35276300/231564684-1e2720c7-47c3-4fa0-b06b-11fcb7a5da19.png)


To produce the most comprehensive metadata, it is important to fill out all relevant sections of the "Document description," including any additional elements like the table of contents that can be extracted from the book itself.

After entering all available information, the next step is to provide users with a link to the book or an option to download the PDF version directly from your catalog. This information on the location of the book, along with any related files and links, is referred to as "External resources."

To add external resources, select "External resources" in the navigation tree and click on "Create resource." This will open a new resource page where you can describe the resource. Most elements are optional, but at a minimum, you should enter the title, type, author, and date. To provide users with access to the book, you will add an external link to it. Since you are not mandated to redistribute the book, the option to upload the book on yur own website and to provide direct access to it is not relevant.

Note that unlike the metadata on the document, information on external resources is NOT saved automatically. Be sure to click on **Save** to save the information you just entered.

![image](https://user-images.githubusercontent.com/35276300/231566591-5c82b41d-782a-4a8b-bce0-0418874b8204.png)

![image](https://user-images.githubusercontent.com/35276300/231567718-dd93c6dc-d4ca-480d-8d7e-42bd00c37f26.png)

You have now completed the documentation of the book. The "Projects" page will show this new entry. You may at any time go back to it to edit or complete the metadata.

![image](https://github.com/ihsn/editor/assets/35276300/b3a72952-73e0-491e-aa03-483a1875ac82)

If you have a NADA catalog and the credentials to publish content in it, you can now upload your metadata in the NADA catalog using the "Publish to NADA" command in the "Project" main menu. See the section "Publishing a project" of this User Guide.

![image](https://user-images.githubusercontent.com/35276300/231568894-e364c4f4-49a3-4168-8cda-eba631152a5f.png)

The book will now be listed and discoverable in your NADA catalog.
![image](https://user-images.githubusercontent.com/35276300/231571821-ffd10899-5dd9-4318-ac84-2fd13986eb24.png)


## Example 2: Documenting and cataloguing a survey dataset

In this second example, we will be documenting a survey dataset (microdata) using the DDI Codebook metadata standard. We will only cover some of the core features of the Metadata Editor. For a complete overview of the software's features, please refer to the "Microdata" section in the "Documenting Data" chapter.

The dataset we will be documenting, and publishing in a NADA catalog, is a synthetic dataset representing a sample household survey for a fictional country. The dataset, along with its related resources, can be found in the "demo" folder of the installation package, as well as on the Metadata Editor GitHub repository. The materials include the following files:
- Stata (version 17) data files with all variables and values labeled:
  - training_survey_data_hh.dta (household-level data file, in Stata 17 format, with 47 variables and 7,975 observations). 
  - training_survey_data_ind.dta (individual-level data file, in Stata 17 format, with 26 variables and 30,986 observations). All variables and values are labeled.
 - Survey questionnaire and documentation in MS-Excel file "synthetic_survey_questionnaire_info.xlsx". This file contains:
  - A simplified survey questionnaire (sheets "Household form EN" for variables collected at the household level, and "Individual form EN" for variables collected at the individual level.)
  - A simplified survey report, with information on the sampling design (sheet "Survey info")
- Other:
  - survey_logo.JPG (a logo for the survey, in JPG format)

![image](https://user-images.githubusercontent.com/35276300/233700458-8e1526d5-8e02-433d-af6a-086b6219cc65.png)
 

To begin documenting the dataset, open the Metadata Editor and log in. Then, on the **My projects** page, click "Create new project" and select "Microdata". A new project homepage will open, and the navigation bar shown on the left will reflect the content of the default metadata template for "Microdata" (default templates can be changed in the Template Manager).

![image](https://github.com/ihsn/editor/assets/35276300/c9beb3aa-fad8-4228-aa2e-d43fd74e316b)

We can change the image that will be used as a thumbnail for the project (this is optional). Click on "Change image" and select the file "survey_logo.JPG".

![image](https://github.com/ihsn/editor/assets/35276300/4d2fe874-c569-4515-a36a-44bdd23efc5a)

We will use the default metadata template to document the dataset, so there is no need to "Switch template". You are now ready to start documenting the dataset.

In the section "Document description", enter information on the metadata. This information is optional. Enter your name and the date the metadata was created.

![image](https://user-images.githubusercontent.com/35276300/231762296-ada64cb1-7ffd-43df-9242-036be6b5f05b.png)


In the section "Study description", enter the relevant information on the survey. First, create a unique identifier for the dataset, e.g., "DEMO_SVY_001". Then use information found in sheet "Survey info" of the Excel file. All information contained in this sheet should be entered in the corresponding metadata elements. Browse the navigation tree and find the most appropriate elements for each piece of information. Use the "?" next to each element label to view a description if necessary.

![image](https://user-images.githubusercontent.com/35276300/233703241-b06145e0-e4e3-4486-93ca-37296a0e04c6.png)

When all available "Study information" is entered, click on "Data files" in the navigation bar. On the Data files page, click "Import data", select the two Stata data files to be imported, then click "Import files".

![image](https://user-images.githubusercontent.com/35276300/231763763-c4d8a6d3-789b-4cb6-94f3-77680f15cfbf.png)

The import will extract all available metadata (variable list, names, variable labels, value labels), and also generate summary statistics.

![image](https://user-images.githubusercontent.com/35276300/231763936-aac0aea2-eb90-4969-bd37-06a313418816.png)

The "Data files" page will now display your two files, which will also be listed in the navigation bar.

![image](https://user-images.githubusercontent.com/35276300/233703124-0c4c098d-7cfb-4389-8d65-abc27e1d7d8e.png)

You can preview the data by clicking "Data", but note that the data cannot be edited in the Metadata Editor.

![image](https://user-images.githubusercontent.com/35276300/233704435-4c4f6229-6b80-46b9-8b51-7755776dd8df.png)

You will now document the data files and the variables they contain.

First, click on the filename of a data file in the navigation tree and add a brief description of the file. Save the information entered on the page, which is not automatically saved, and repeat the process for the other data file.

![image](https://user-images.githubusercontent.com/35276300/231770783-00000083-5ede-4588-87b4-a5f5cf58fb74.png)

Next, add or edit the information available on each variable for each data file. In the navigation bar, select "Variables" in the "Data files / *name of the data file*" section.

![image](https://user-images.githubusercontent.com/35276300/233704533-62d1362a-cfe3-46eb-a77f-cb4a1a69bf8e.png)

The page will display a list of variables for the selected file, along with multiple options to edit and complete the metadata related to the variables. On this page, you can:

- Edit the variables labels.
- Edit the value labels (for discrete/categorical variables only).
- If necessary, delete variables.
- Identify a variable as being a sample weight.
- Add metadata related to the variable (literal question, interviewer instructions, derivation and imputation, and more) in the "DOCUMENTATION" tab.
- Identify values to be considered as "missing." The system missing values in Stata or SPSS will be automatically identified as "missing." However, in some cases, one (or multiple) values may be used to represent missing values (e.g., "99" representing missing or unknown for a variable such as age).
- Set the weighting coefficient (if relevant) to be applied to generate summary statistics.
- Select the summary statistics to be included in the metadata (in tab "STATISTICS").

Note that you cannot rename variables in the Metadata Editor. This considered as a change of data. If you need to change your data (renaming variables, creating new ones, deleting observations, or editing the data themselves), you will have to do that outside the Metadata Editor and re-import the modified data files.  

![image](https://user-images.githubusercontent.com/35276300/233704709-a211367a-53e2-42fe-b472-b07fff4e6d99.png)

Assuming all variable labels and value labels are good as imported, browse the list of variables to check that their type has been properly detected. For instance, the variable "hhsize" (household size) in the file training_survey_data_hh.dta has been imported as a discrete variable. This may be fine, but let's assume you want it to be imported as a continuous variable. Change the type from "Discrete" to "Continuous."  

![image](https://user-images.githubusercontent.com/35276300/231877710-515e9a3e-3304-4a99-a8e4-c6f492432ac9.png)


Now, add metadata specific to each variable. Most of the metadata at the variable level that were not extracted from the imported data files will typically be found in the survey questionnaire and the interviewer manual. The "DOCUMENTATION" tab displays the metadata for the variable(s) selected in the list of variables. Add the following information:

- Universe of the variable
- Pre-question, literal question, and post-question (for collected variables, not for derived variables)
- Derivation or imputation methods (for derived variables)

![image](https://user-images.githubusercontent.com/35276300/233705597-9b6a4e8b-c65b-4f65-8994-1d029cc60900.png)

Note that you can enter common metadata for more than one variable by selecting multiple variables (using the Shift or Ctrl key) and entering information for the relevant element(s). For example, the three variables related to education in the individual dataset have the same universe ("Population aged 6 and above"). The three variables can be selected, and the information entered in "Universe" will be automatically applied to all three variables.

Set the "weight" variable (and also the "popweight" variable in the household-level data file) as weighting variables.

![image](https://user-images.githubusercontent.com/35276300/231873892-4a2d595a-e9e3-42d0-8d04-3d355e3ae89c.png)


To display and store weighted summary statistics, you need to apply weights to the relevant variables. This can be done in the WEIGHTS tab.

![image](https://user-images.githubusercontent.com/35276300/233706439-577b3451-9470-4104-ae2d-5bd8e880a94c.png)

First, select the variables to which you want to apply a weighting coefficient. Then, select the weight variable. For the household-level file, you may first select all variables except the quintile variables, then select the "hhweight" variable (only variables that have been tagged as weight will be listed in the selection).

![image](https://user-images.githubusercontent.com/35276300/233706693-b4193d98-8cc6-41aa-af6f-39448a3f7272.png)

Next, repeat the process for the three quintile variables, but use the "popweight" variable as weight.

![image](https://user-images.githubusercontent.com/35276300/233706968-8f314f5f-8c8a-4fa1-bb2c-d9ea4b5da48a.png)

The summary statistics will now display both the unweighted and weighted values.

As a last step in documenting variables, browse the variables and verify that the selected summary statistics for each variable correspond to what you want to store in your metadata. Note that means or standard deviations should not be included for categorical variables.

![image](https://user-images.githubusercontent.com/35276300/234379255-45de7402-3955-40ad-b68c-d750c7f00631.png)


Once you have entered the variable-level metadata for both files, you can finalize the documentation of the dataset by documenting and attaching external resources to the survey metadata. External resources include all materials you want to make accessible to users when you publish the dataset in a catalog. This includes the microdata files, if you want to disseminate them openly or under restrictions. For our survey metadata, we will add two external resources: the Excel file that contains the questionnaire and the dataset (the two Stata files compressed as one zip file). Note that you could provide the data in more than one format, for example, you could also share a version of the files in CSV and SPSS formats for user convenience.

To create external resources, click on "External resources" in the navigation tree and then click on "Create resource". Select the file type ("questionnaire" and "microdata" for our two external resources) and give each a short title.


Provide a link to the resource or select the file you want to upload in your online catalog, then click "SAVE."

![image](https://user-images.githubusercontent.com/35276300/233708637-2432c485-6ac4-472f-a2f4-2e5f010ef195.png)


Your dataset documentation is now complete. You can export the DDI and the RDF metadata and save the full package as a project zip file. If you have a NADA catalog and the credentials, you can also publish your data and metadata in the catalog.


The dataset will be accessible and discoverable in your NADA catalog with detailed metadata, including the data dictionary.

![image](https://user-images.githubusercontent.com/35276300/233710003-ae1dcaf6-f744-4380-b177-a707bd06300b.png)



