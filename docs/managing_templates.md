# Template manager 

## Use of templates

The metadata standards and schemas embedded in the Metadata Editor contain a large number of metadata elements. These standards and schemas have been designed to accommodate the needs of different categories of users. Few users will find all elements relevant. **Templates** are created to subset and tailor the elements available in a standard or schema to the specific needs of an organization. Templates determine how the metadata entry forms that will be used by data curators will contain and look like.

![image](https://user-images.githubusercontent.com/35276300/214431708-b2eab31b-c32c-4483-9953-b086bbf63f7d.png)

By creating a template, you have the possibility to: 
- Select, among all elements available in a standard or schema, those that are relevant to you or to your organization. This will result in lighter metadata entry forms. 
- Declare some elements as "required" or "recommended".
- Control the order in which the elements will appear in metadata entry forms, and organize them into customized groups. 
- Customize the labels and instructions associated with each metadata element. Each community has its own jargon. If the terms used in the default templates are not sufficiently clear to you and your team of data curators, the template manager allows you to solve that.
- Set controlled vocabularies and default values specific to your organization, where appropriate.
- Set your own validation rules for the content of the metadata elements.

Note that you will probably not use the Template Manager very often. Once a template suitable to your organization has been designed or imported, and set as the default one, there will not be much reason to change it. The recommendation is thus to spend some time reviewing and customizing templates that fits the needs of your organization, then to share them across the organization and use them in a consistent manner.

## Core templates vs custom templates

For each data type supported by the Metadata Editor, the full list of elements available in the standard or schema is provided as a *core* template. The core templates cannot be deleted or edited. They serve as a repository of all available elements, with their default label and description, from which you can build your own *custom* templates.

Custom templates are created by duplicating then modifying an existing template (core of custom). The custom templates you create can be exported, and you can import custom templates created by others. Multiple custom templates can be created for a same data type.

A template can easily be translated into any language. Just duplicate the English template, and tranlate each element label and descriptions.    

![image](https://user-images.githubusercontent.com/35276300/214394808-d752cb8e-b007-4642-9e16-e2cb26b16e91.png)

The Metadata Editor comes with pre-designed custom templates (the *IHSN templates*), covering all data types. Generating custom templates is thus not a requirement to make use of the Metadata Editor. 

NOTE ON CORE TEMPLATES: 

The IHSN may occasionally produce new core templates, either because a new version of a metadata standard becomes available, to cover new data types, or to provide core templates in languages other than English. In such case, the new core templates will be made available on GitHub and from the IHSN website, with an option to upgrade them in your version of the Metadata Editor without having to upgrade the application itself. Custom templates may also be made available publicly on the IHSN website. 

NOTES ON THE USE OF CUSTOM TEMPLATES:

Different organizations may use different templates. But because all templates are based on common standards and schemas, the metadata generated using different templates will always remain compatible. 

1. When you document a dataset, the template is saved in the Project.
2. Metadata generated using a template can be edited using another template. The selection /switching of the template impacts what is shown in the metadata entry forms; it does not change the content.
3. If you read metadata that contain element X using a template that does not include element X and save, the element will NOT be deleted from the metadata. So you do not have to worry about the template you use. The selection of a template will never result in automatic deletion of existing content.

## Creating or editing a custom template

To build a custom template, you will duplicate then edit the content of a core template or an existing custom template. 
![image](https://user-images.githubusercontent.com/35276300/214386279-82d6df69-4cf2-4694-bdfc-a8291a5c519a.png)

Select the template from which you want to build your own, then click “Duplicate”. The "Template description" page of new template will be displayed. 

![image](https://user-images.githubusercontent.com/35276300/214677926-79ad0824-3678-4f23-999f-390ed63e248a.png)
 
First, change the name of the template (by default, the name will be the name of the template you duplicated followed by "- copy"). Give a clear and unique name to your new template. The Metadata Editor does not force templates to have a unique name (but it automatically generated an underlying unique system identifier), so it is important that the name you chose distinguish the template you are creating from other custom templates. It is good practice to create a name that will include the data type, the organization that developed the template, and the version of the custom template. Other fields in the "Template description" page can be filled as appropriate. Although not required, no field should be left empty. When done, SAVE this page (changes are not saved automatically). You can now start customizing the content of this new template. 

All standards and schemas start with a first section named either **Metadata information** or **Document description**. This section contains the metadata elements used to document the metadata (not the data themselves). This information will be useful to keep track of who documented the dataset, and when. This first section is followed by a few "hard-coded" sections ("section containers") specific to each standard and schema, within which metadata elements will be listed and grouped. Hard-coded main groups are displayed with the icon:
![image](https://user-images.githubusercontent.com/35276300/224107510-136d7837-fcaa-4e23-9303-1d77de0794d1.png)

These section containers are hard-coded as they represent core divisions of the metadata standards, each one of which containing elements that cannot be moved to another group. 
![image](https://user-images.githubusercontent.com/35276300/224107317-8fb883f8-0bb6-44e1-90bf-7d4b2d84f9e3.png)


### Description of a metadata element

This section describes the types of *metadata elements* found in the standards and schemas used in the Metadata Editor, and how they are selected and customized. 

- Each metadata standard belongs to a specific section of a standard/schema. For example, in the DDI Codebook standard used for microdata, the elements used to describe each variable will be found in the "Variable description" section, while the elements used to describe overall aspects of a survey like the geographic coverage of abstract would be part of the "Study description" section.

- A metadata element can be *repeatable* or *non repeatable*. The title of a book for example must be unique, so the element "Title" will be non-repeatable. But the section "keywords" element may contain multiple entries, and is thus a repeatable element.

- A metadata element can be simple or contain multiple sub-elements. For example, the "title" element in the DDI standard is a simple element, while the "author" element in the *document* schema contains multiple sub-elements that describe an author including first name, last name, affiliation, and more.
In the Template Manager, different icons indicate the type of the element. The types are the following:
   - String
    ![image](https://user-images.githubusercontent.com/35276300/224108908-16a31b84-420a-493e-b72b-cef7573c2e64.png)
   - Array
     ![image](https://user-images.githubusercontent.com/35276300/224109056-54de49b2-ba4a-44b1-a2ff-047d9e87410a.png)
   - Simple array
   
   - Nested array
     ![image](https://user-images.githubusercontent.com/35276300/224109195-f62d1e58-2533-49d9-8dff-daecb0a97a5c.png)

- Nested elements

- A metadata element has a default *label*, which can be changed in custom templates.
![image](https://user-images.githubusercontent.com/35276300/214671331-30aefe93-0053-4710-a88b-de101810170b.png)

- A metadata element has a *name* in the standard/schema. This corresponds to the names shown in ReDoc. When the metadata are exported, this is the name that will be used, no matter what custom label you give to the elements. This guarantees that the standard is "standard" and can be validated.
(show where it is displayed)

- A metadata element has a default *description*, which can be edited in custom templates. When you duplicate a standard, the original description is shown.
![image](https://user-images.githubusercontent.com/35276300/214671674-326b5de0-b7af-42de-baa1-ce82fe3b50a6.png)

- A metadata element or sub-element will contain information of a specific *format*: numeric, string, array, or boolean.  

- An element can be declared as *required* or *recommended* in the template. A 'diagnostic" tool is embedded in the Metadata Editor, which flags metadata elements that contain invalid content (based on validation rules) and elelements declared as *required* but for which no content has been entered. Also, the navigation bar provides an option to only display the *required* and/or *recommended* elements. This option is provided to allow data curators to focus on the most important metadata elements.

 ![image](https://user-images.githubusercontent.com/35276300/214671469-ce5a006c-5cca-4ff8-b816-eab44c948439.png)

- The way each element will be displayed in the Metadata Editor entry pages is controlled in the Template Manager. The "DISPLAY" parameter provides the main customization options. For a specific case described below, the "Type" will also be used.
![image](https://user-images.githubusercontent.com/35276300/217053424-45e59e6b-c476-46af-b6c7-678360d3ab61.png)

An single element (or sub-element) can have the following types, controlled by selecting an option in the *Display* drop-down :
  - **Text**: the element will be displayed as a single, on-line text box. The box can accept entries with up to N characters. For example, in the metadata entry page: 
  ![image](https://user-images.githubusercontent.com/35276300/217052523-219836b5-6cd2-40d3-9df5-2b9c63d58e6e.png)
  
  - **Repeatable text**: the element will be displayed as a single, on-line text box, but this box is repeatable. To implement this option, select "type" = "simple_array".
  
  ![image](https://user-images.githubusercontent.com/35276300/217288316-4dbd03f6-3aba-49e1-8454-d223b12bab1e.png)
  ![image](https://user-images.githubusercontent.com/35276300/217289419-b21eb75a-b6f1-4603-beb0-18aca6f81123.png)

  - **Text area**: the element will be displayed as a multi-line text box. The box can accept entries with up to N characters. For example, in the metadata entry page:
  
  ![image](https://user-images.githubusercontent.com/35276300/217052997-dee39f62-13a5-424c-a880-19cbb8f2e056.png)

  -  **Drop down**: the element will appear as a single text box, but with a controlled vocabulary. Two options are possible: mandating the entry to be taken from the drop down, or allowing users to either select an entry from the drop down or typing a free text. The values that appear in the drop down must be entered in the "CONTROLLED VOCABULARY" tab.
  
  ![image](https://user-images.githubusercontent.com/35276300/217054205-b7ff2678-5d23-4d44-b85f-bc4216cc2ad2.png)
 
 - Elements that contain sub-elements will be displayed either as arrays or as nested -arrays. This is controlled by the "Type" setting in the Template Manager. An "array" and a "nested array" will be displayed respecyively as follows:
 
     - ARRAY (all elements appear as one row, with limited space for each sub-element):
 
     ![image](https://user-images.githubusercontent.com/35276300/217054957-a88b126c-16f1-48d4-8f5a-96cc79283c68.png)

     - NESTED ARRAY (each element appear as a separate row, providing adequate space for long content):
 
     ![image](https://user-images.githubusercontent.com/35276300/217055225-9c4dee87-c80d-4b27-9aa4-9092e0732c0d.png)

- An element may have a default value. A tool is provided in the Metadata Editor to "Load default values". When you document a dataset, this option will provide you with a way to automatically fill out the fields for which a default value is available.

![image](https://user-images.githubusercontent.com/35276300/214395605-c5ec98d9-23db-48d5-badf-a37014273e9d.png)

- Controlled vocabularies

- Validation rules. One or multiploe validation rule(s) can be set for each element or sub-element. Multiple options are provided, including the option to enter a regular expression. When content is entered in a metadata field, the compliance with the validation rules will automatically be checked and a warning message will be displayed in teh element and in the project home page. When more than one validation rule is set for a same element, the entry must comply with all of them to be valid.
  
![image](https://user-images.githubusercontent.com/35276300/214395548-ef25e59a-18bb-4efa-a339-52c124c209e5.png)


### Navigation bar and groupings

Elements can be grouped in custom folders and sub-folders ("sections"). This grouping is for convenience only.
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



