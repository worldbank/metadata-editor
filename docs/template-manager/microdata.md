# Microdata

The Metadata Editor makes use of the Data Documentation Initiative (DDI) metadata standard for the documentation of microdata. The DDI version implemented in the Metadata Editor is the DDI Codebook, version 2.5.

## DDI Codebook standard

The DDI Codebook metadata standard is developed and maintained by the DDI Alliance. It contains the following main sections:
- **Document description**: contains a small number of elements used to document the metadata (not the data) 
- **Study description**: contains all elements related to document the study (survey, census or other) itself, including the title, producers, geographic and temporal coverage, sampling, etc.
- **File description**: contains a few elements used to provide information on each data file composing the dataset.
- **Variable description**: contains elements used to describe in detail each variable in the dataset. This includes variable names and labels, value labels, literal questions and interviewer instructions, summary statistics, and more. This section of the DDI Codebook provides a detailed data dictionary.
- **Variable groups**: contains elements to organize the variables by groups (other than the data file they belong to). This section is optional.

For the documentation of microdata, the Metadata Editor also makes use of the Dublin Core metadata standard to document **external resources**. External resources are files or links that provide content other than the metadata stored in the DDI. This may consist of PDF questionnaires or manuals, scripts, images, or any other resource available in digital format.

## Creating a new project

A *project* consists of a survey, a census, or another type of activity that generates microdata). 

To create a new project, click on "Create new project" in the Project page and select "Microdata" as type.

![image](https://user-images.githubusercontent.com/35276300/214939118-1c290c3b-52c2-4f05-88ac-31952c04e668.png)

![image](https://user-images.githubusercontent.com/35276300/214939280-12042a85-4fc1-4f9e-acdf-261553b202cb.png)

A new project home page will be displayed. 

![image](https://user-images.githubusercontent.com/35276300/214939548-3cc62a96-c7f4-4c6b-a29d-cfd914c79abd.png)

By default, the template identified as default in the Template Manager will be used. You can select a different template by clicking on "Switch template".

![image](https://user-images.githubusercontent.com/35276300/214939822-f513121c-b659-45d1-bb7b-45a0243d471b.png)

The navigation bar on the right of the page reflects the content of the template you selected.

![image](https://user-images.githubusercontent.com/35276300/214939873-1a6bfb5f-da4f-4824-94cc-9677e2066f49.png)

You have the option to select a project thumbnail. The thumbnail can for example be the logo of your survey or census. It will be used in the NADA catalog, should you publish the metadata in NADA. The thumbnail is an image in JPG or PNG format. 

![image](https://user-images.githubusercontent.com/35276300/214940035-d99d65fe-7764-45b7-b498-d367d52c98c5.png)

To select a thumbnail, click on "Change image" and select an image file.

![image](https://user-images.githubusercontent.com/35276300/214941199-bb5fd48d-47dc-4638-a355-9e068da77279.png)

![image](https://user-images.githubusercontent.com/35276300/214941280-2493b610-1c2b-4c25-b8e5-37c07d1c6dde.png)

Use the navigation tree to fill out information in the **Document description** and **Study description** sections.

![image](https://user-images.githubusercontent.com/35276300/214941497-887f698d-e763-48fe-8297-0e45bf6d2f73.png)

A description of the content of each element is available by clicking on the "?". If the content you enter violates a validation rule entered in the template, an error message will be displayed in red. Required elements are indicated by a red asterisk. 

![image](https://user-images.githubusercontent.com/35276300/214941921-3e765962-8573-486c-af2c-a2168e79ebf2.png)

When an element is "repeatable", an option to "Add rows" is provided.

![image](https://user-images.githubusercontent.com/35276300/214942382-a69a9dab-2410-4493-8b1e-8d2469b14868.png)

When a controlled vocabulary has been entered in the template, a drop down menu will appear.

![image](https://user-images.githubusercontent.com/35276300/214942534-d47df5a3-93f0-4d61-b956-46bbc89f0632.png)
Try and provide as much and as relevant information in all relevant metadata elements. 

### Import data files

If you have data files available in CSV, Stata, SPSS, or other format compatible with the Metadata Editor, importing the data files will usually be the first step  (automatically generate data dictionary) then add metadata. 
If you do not have data, can still document the study and even create a data dictionary.
Last, add external resources.
Use R
Supported formats
What's imported
Key variables and relationships
Weight variables

### Fill document description 
Default values
Spread metadata

### Fill study description

### Data file description

### Variable description and statistics
Description
Summary statistics

### Variable groups

### Tags

### Adding external resources

### Saving and exporting metadata

### Exporting data

### Diagnostic


## Editing an existing project

Open and edit
Replacing data files



