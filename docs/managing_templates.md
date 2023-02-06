# Template manager 

## Use of templates

The metadata standards and schemas embedded in the Metadata Editor contain a large number of metadata elements. These standards and schemas have been designed to accommodate the needs of different categories of users. Few users will find all elements relevant. **Templates** are created to subset and tailor the elements available in a standard or schema to the specific needs of an organization. By creating templates, users of the Metadata Editor have the possibility to: 
- select, among all elements available in a standard or schema, those that they consider relevant; 
- set some elements as "required" or "recommended";
- organize the selected elements into groups;
- control the order in which the elements will appear in metadata entry forms;
- customize the labels and instructions associated with each metadata element;
- set controlled vocabularies and default values where appropriate;
- set custom validation rules for the content of the selected elements.

Templates are used by the Metadata Editor to generate the metadata entry forms.

![image](https://user-images.githubusercontent.com/35276300/214431708-b2eab31b-c32c-4483-9953-b086bbf63f7d.png)

You will rarely need to use the Template Manager. Once a template has been designed or imported, and set as default, there is not much reason to change them. Spend some time reviewing and customizing the template to your specific needs. Using stable templates will guarantee consistency.

## Core templates vs custom templates

The standard or schema for each data type is provided in the Metadata Editor as a *core* template. Core templates contain all elements of the standard or schema, with their default label and description. They contain a very small number of validation rules and controlled vocabularies. These system templates cannot be deleted or edited. But they can be duplicated then customized, allowing users to generate their own *Custom templates*. Custom templates can be exported and imported, i.e. shared with other users. Multiple custom templates can be created for a given data type.

![image](https://user-images.githubusercontent.com/35276300/214394808-d752cb8e-b007-4642-9e16-e2cb26b16e91.png)

The Metadata Editor application comes with a collection of pre-designed custom templates (the *IHSN templates*), covering all data types. Generating custom templates is thus not a requirement to make use of the Metadata Editor. 

NOTE ON CORE TEMPLATES: 

New core templates (new version, new standards): import from GitHub or IHSN.

NOTES ON THE USE OF CUSTOM TEMPLATES:

1. When you document a dataset, the template is saved in the Project.
2. Metadata generated using a template can be edited using another template. The selection /switching of the template impacts what is shown in the metadata entry forms; it does not change the content.
3. If you read metadata that contain element X using a template that does not include element X and save, the element will NOT be deleted from the metadata. So you do not have to worry about the template you use. The selection of a template will never result in automatic deletion of existing content.

## Creating or editing a custom template

To build a custom template:
-	Duplicate and edit a core template. You will then build your template from the original standard or schema.
-	Modify a custom template. You will then build your template from a subset of the original standard or schema. 
-	Import a custom template.

You will need at least one user template per data type you curate, and one to be used for external resources. One custom template per data type will be selected as the default one.

![image](https://user-images.githubusercontent.com/35276300/214386279-82d6df69-4cf2-4694-bdfc-a8291a5c519a.png)

To generate a template:

Click “Duplicate”. The "Template description" page of new template will be displayed. 

![image](https://user-images.githubusercontent.com/35276300/214677926-79ad0824-3678-4f23-999f-390ed63e248a.png)
 
First, change the name of the template (by default, the name will be the name of the template you duplicated followed by "- copy"). Give a clear and unique ame to the template. The Metadata Editor does not enforce templates to have a unique name (but it automatically generated an underlying unique system identifier), so it is important that the name you chose distinguish the template you are creating from other custom templates. It is good practice to create a name that will include the data type, the organization that developed the template, and the version of the custom template. Other fields should be filled as appropriate (although not required, no field should be left empty). For the "language", we suggest you enter the language code. When done, SAVE this page. You can now start customizing the template you duplicated. 

All standards and schemas start with a first section named either **Metadata information** or **Document description**. This section contains the metadata elements used to document the metadata (not the data themselves). This information will be useful to keep track of who documented the dataset, and when. This first section is followed by other "hard-coded" sections specific to each standard and schema, within which metadata elements will be listed and grouped.

### Description of a metadata element

This section describes the types of *metadata elements* found in the standards and schemas used in the Metadata Editor, and how they are selected and customized. 

- Each metadata standard belongs to a specific section of a standard/schema. For example, in the DDI Codebook standard used for microdata, the elements used to describe each variable will be found in the "Variable description" section, while the elements used to describe overall aspects of a survey like the geographic coverage of abstract would be part of the "Study description" section.

- A metadata element can be *repeatable* or *non repeatable*. The title of a book for example must be unique, so the element "Title" will be non-repeatable. But the section "keywords" element may contain multiple entries, and is thus a repeatable element.

- A metadata element can be simple or contain multiple sub-elements. For example, the "title" element in the DDI standard is a simple element, while the "author" element in the *document* schema contains multiple sub-elements that describe an author including first name, last name, affiliation, and more.

- A metadata element has a default label, which can be changed in custom templates.
![image](https://user-images.githubusercontent.com/35276300/214671331-30aefe93-0053-4710-a88b-de101810170b.png)

- A metadata element has a "name" in the standard/schema. This corresponds to the names shown in ReDoc. When the metadata are exported, this is the name that will be used, no matter what custom label you give to the elements. This guarantees that the standard is "standard" and can be validated.
(show where it is displayed)

- A metadata element has a default description, which can be edited in custom templates. When you duplicate a standard, the original description is shown.
![image](https://user-images.githubusercontent.com/35276300/214671674-326b5de0-b7af-42de-baa1-ce82fe3b50a6.png)

- A metadata element or sub-element will contain information of a specific format: numeric, string, array, or boolean.  

- An element can be declared as *required* or *recommended* in the template.
![image](https://user-images.githubusercontent.com/35276300/214671469-ce5a006c-5cca-4ff8-b816-eab44c948439.png)
How this will be used?
Do not make many elements mandatory.

- The way each element will be displayed in the Metadata Editor entry pages is controlled in the Template Manager. The "DISPLAY" parameter provides the main customization options. For a specific case described below, the "Type" will also be used.
![image](https://user-images.githubusercontent.com/35276300/217053424-45e59e6b-c476-46af-b6c7-678360d3ab61.png)

An single element (or sub-element) can have the following types, controled by selecting an option in the *Display* drop-down :
  - **Text**: the element will be displayed as a single, on-line text box. The box can accept entries with up to N characters. For example, in the metadata entry page: 
  ![image](https://user-images.githubusercontent.com/35276300/217052523-219836b5-6cd2-40d3-9df5-2b9c63d58e6e.png)

  - **Text area**: the element will be displayed as a multi-line text box. The box can accept entries with up to N characters. For example, in the metadata entry page:
  ![image](https://user-images.githubusercontent.com/35276300/217052997-dee39f62-13a5-424c-a880-19cbb8f2e056.png)

  -  **Drop down**: the element will appear as a single text box, but with a controlled vocabulary. Two options are possible: mandating the entry to be taken from the drop down, or allowing users to either select an entry from the drop down or typing a free text.
  ![image](https://user-images.githubusercontent.com/35276300/217054205-b7ff2678-5d23-4d44-b85f-bc4216cc2ad2.png)
 
 - Elements that contain sub-elements will be displayed either as arrays or as nested -arrays. This is controlled by the "Type" setting in the Template Manager. An "array" and a "nested array" will be displayed respecyively as follows:
 ARRAY (all elements appear as one row, with limited space for each sub-element):
 ![image](https://user-images.githubusercontent.com/35276300/217054957-a88b126c-16f1-48d4-8f5a-96cc79283c68.png)

 NESTED ARRAY (each element appear as a separate row, providing adequate space for long content):
 ![image](https://user-images.githubusercontent.com/35276300/217055225-9c4dee87-c80d-4b27-9aa4-9092e0732c0d.png)

- An element may have a default value. 
![image](https://user-images.githubusercontent.com/35276300/214395605-c5ec98d9-23db-48d5-badf-a37014273e9d.png)
How this will be used?

- A controlled vocabulary can be provided.
![image](https://user-images.githubusercontent.com/35276300/214395449-bb8967db-8115-4ea4-8946-17313f3fb840.png)
How this shows in ME.
Can still enter info from outside the CV.

- Validation rules can be set.
![image](https://user-images.githubusercontent.com/35276300/214395548-ef25e59a-18bb-4efa-a339-52c124c209e5.png)
How this is used in ME.

This works for repeatable and complex elements as well.
![image](https://user-images.githubusercontent.com/35276300/214395688-f0805e52-7794-40fa-80f1-b5e98a1632e0.png)


### Navigation bar and groupings

Elements can be grouped in custom folders and sub-folders. This is for convenience only.
![image](https://user-images.githubusercontent.com/35276300/214395275-46bb01e0-6a43-4973-a456-4e68693001ea.png)
They are grouped by main section of the standard or schema. This is constrained. 
When you see:
![image](https://user-images.githubusercontent.com/35276300/214672976-1fd6d1b0-8f23-4f5c-b8e5-63c60529c781.png)

![image](https://user-images.githubusercontent.com/35276300/214672841-630f0984-f771-4ebc-9d5d-c63e9935e1dc.png)

But you can also organize elements of a section into folders and sub-folders. This is just for convenience. It will not impact how the metadata is stored in the metadata file. And if the metadata are read using another template, they will be visible as long as the element is somewhere in the new template.

![image](https://user-images.githubusercontent.com/35276300/214673218-cdd0a030-61c0-4740-b824-61d21114d75d.png)

Moving elements in the navigation bar:
- Order within a folder
![image](https://user-images.githubusercontent.com/35276300/214673611-d7941cc8-6bd3-4334-a4f8-9dca1cfe3762.png)

- Move from a folder to another
Copy
![image](https://user-images.githubusercontent.com/35276300/214673837-4d0709a9-a8f2-4914-9c8c-71d3597f55f2.png)
Then select the destination subfolder and paste
![image](https://user-images.githubusercontent.com/35276300/214673959-573b2c99-21cc-48d0-995b-235b7b2b4f46.png)

For complex elements, you also control the grouping of sub-elements.
[screenshot]

### Available vs selected elements

When you duplicate a core template, ALL elements are in the list. 
When you edit a custom templates, it is likely that some elements have been dropped.

You can drop elements using the > button. When you drop an element, it is sent back to the list of available elements. All elements from a standard/schema that are not selected in the template are available for selection. They can only be added to the section of the standard they belong to. For example, in DDI, you cannot include element from file or variable description in Study description, or from Study description in Variable description. Within a Standard section, you are free to group and sequence the elements as you want.

NOTE: When you send an element back to the repository of unused elements, the customization of label, description, etc. are lost.

![image](https://user-images.githubusercontent.com/35276300/214675136-f756baf6-597a-40d3-b0be-0f00631df3d1.png)

To see all elements including the ones already selected: 
![image](https://user-images.githubusercontent.com/35276300/214675037-42382140-6333-48e7-8c81-3cbed5d6d00f.png)

For complex elements, you also control the sub-elements that you include, and their sequence and description and status.
[screenshot]

### Activating the default templates

At least one template for each data type must be declared as being the "default" one. When a new project is created, this is the template that will be used. You can change the default using the radio button.

Remember (see NOTES)

## Sharing custom templates

Exporting templates:

Importing templates:
-	From a saved template
-	From the IHSN website
-	From a project

Note: saved as JSON files. Stored in ...

## Deleting templates

Click "delete".



