# Quick start

Demonstrate the documentation of a dataset. Use microdata, as it is the most complex one (import data). Only basic; not the multiple functions that are available to make the process more effective. See the full documentation for that. For other types of data, much simpler as no data needs to be imported.

Use demo files for documentation of microdata. Included in package (folder "demo"). Or download zip file from [url]. 

The files included in this small demo package are the data and metadata related to the 2010 Population Census of an imaginary country named Popstan. This very basic package includes the following files:

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


