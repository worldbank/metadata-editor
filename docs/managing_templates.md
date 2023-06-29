# Template manager 

## Use of templates

The Metadata Editor provides a large number of metadata elements embedded in the metadata standards and schemas for each data type supported by the Editor. These standards and schemas are designed to cater to the needs of different categories of users. However, few users may find all elements relevant. To address this, templates are created to subset and tailor the available elements in a standard or schema to the specific needs of an organization. Templates determine how the metadata entry forms will appear and what they will contain.

![image](https://user-images.githubusercontent.com/35276300/214431708-b2eab31b-c32c-4483-9953-b086bbf63f7d.png)

Creating a template in the Metadata Editor provides you with several benefits, such as:

- Selecting relevant metadata elements from the large number available in a standard or schema, resulting in lighter metadata entry forms.
- Declaring some elements as "required" or "recommended."
- Controlling the order of appearance of elements in metadata entry forms and organizing them into customized groups.
- Customizing labels and instructions associated with each metadata element to match the jargon used by your community and team of data curators.
- Setting controlled vocabularies and default values specific to your organization where appropriate.
- Setting your own validation rules for metadata element content.

Note that you may not need to use the Template Manager frequently. Once you have designed or imported a template suitable for your organization and set it as the default, there may be little reason to change it. Therefore, it is recommended that you spend some time reviewing and customizing templates that suit your organization's needs, share them across the organization, and use them consistently.

## Core templates vs custom templates

The full list of available elements in the standard or schema is provided as a core template for each data type supported by the Metadata Editor. The core templates serve as a repository of all available elements, with their default label and description, from which users can build their own custom templates. However, the core templates cannot be deleted or edited.

Custom templates are created by duplicating and modifying an existing template, either a core or custom template. The custom templates created can be exported, and users can import custom templates created by others. Multiple custom templates can be created for the same data type. Additionally, a template can be easily translated into any language. Users can duplicate the English template and translate each element label and description.

![image](https://github.com/ihsn/editor/assets/35276300/131e90c9-f748-4708-bba7-26ad2114cb7f)

The Metadata Editor provides pre-designed custom templates, known as the IHSN templates, covering all data types. Users can make use of these templates without generating their own custom templates. 

> The IHSN may occasionally produce new core templates, either to cover new data types, to provide core templates in languages other than English, or because a new version of a metadata standard becomes available. In such cases, the new core templates will be made available on GitHub and from the IHSN website. Users will have an option to upgrade them in their version of the Metadata Editor without having to upgrade the application itself. Custom templates may also be made available publicly on the IHSN website.


on common standards and schemas, metadata generated using different templates will always remain compatible.

Here are a few important points to note:

1. When you document a dataset, the template used is saved in the Project.
2. Metadata generated using a template can be edited using another template. Switching the template impacts only what is shown in the metadata entry forms, and not the content itself.
3. If you read metadata that contain element X using a template that does not include element X, and then save the metadata, element X will not be deleted from the metadata. So, you do not have to worry about the template you use. The selection of a template will never result in automatic deletion of existing content.


## Creating or editing a custom template

To build a custom template, you will need to duplicate and edit the content of a core template or an existing custom template.

![image](https://github.com/ihsn/editor/assets/35276300/f8e69561-cf7f-4843-90d1-b4e8ccc7d847)


First, select the template that you want to use as a starting point and click on “Duplicate”. This will display the "Template description" page for the new template.

![image](https://github.com/ihsn/editor/assets/35276300/014c40d8-e020-46fb-98b1-d191d3304792)

Next, change the name of the template. By default, the name will be the name of the template you duplicated followed by "- copy". Give a clear and unique name to your new template. Although the Metadata Editor does not require templates to have a unique name, it is good practice to create a name that includes the data type, the organization that developed the template, and the version of the custom template. Other fields in the "Template description" page can be filled out as appropriate. Although not required, no field should be left empty. Once you have filled out the page, remember to click "SAVE" (changes are not saved automatically). You can now start customizing the content of this new template.

All standards and schemas begin with a first section named either **Metadata information** or **Document description**. This section contains the metadata elements used to document the metadata itself (not the data). This information will be useful in keeping track of who documented the dataset and when. This first section is followed by a few "hard-coded" sections ("section containers") specific to each standard and schema, within which metadata elements will be listed and grouped. Hard-coded main groups are displayed with the icon:
![image](https://user-images.githubusercontent.com/35276300/224107510-136d7837-fcaa-4e23-9303-1d77de0794d1.png)

These section containers are hard-coded since they represent core divisions of the metadata standards, each containing elements that cannot be moved to another group. 
![image](https://user-images.githubusercontent.com/35276300/224107317-8fb883f8-0bb6-44e1-90bf-7d4b2d84f9e3.png)


### Description of a metadata element

This section describes the types of metadata elements found in the standards and schemas used in the Metadata Editor and how they are selected and customized.

- Each metadata standard belongs to a specific section of a standard/schema. For example, in the DDI Codebook standard used for microdata, the elements used to describe each variable will be found in the "Variable description" section, while the elements used to describe overall aspects of a survey like the geographic coverage of the abstract would be part of the "Study description" section.

- A metadata element can be repeatable or non-repeatable. For example, the title of a book must be unique, so the element "Title" will be non-repeatable, but the "Keywords" element may contain multiple entries, and is thus a repeatable element.

- A metadata element can be simple or contain multiple sub-elements. For example, the "Title" element in the DDI standard is a simple element, while the "Author" element in the document schema contains multiple sub-elements that describe an author, including first name, last name, affiliation, and more.

- In the Template Manager, different icons indicate the type of element. The following types are available:
   - String
    ![image](https://user-images.githubusercontent.com/35276300/224108908-16a31b84-420a-493e-b72b-cef7573c2e64.png)
   - Array
     ![image](https://user-images.githubusercontent.com/35276300/224109056-54de49b2-ba4a-44b1-a2ff-047d9e87410a.png)
   - Simple array or nested array
     ![image](https://user-images.githubusercontent.com/35276300/224109195-f62d1e58-2533-49d9-8dff-daecb0a97a5c.png)

- Nested elements

- A metadata element has a default *label*, which can be changed in custom templates.
  
![image](https://user-images.githubusercontent.com/35276300/214671331-30aefe93-0053-4710-a88b-de101810170b.png)

- A metadata element has a *name* in the standard/schema. This corresponds to the names shown in *ReDoc*. When the metadata are exported, this is the name that will be used, no matter what custom label you give to the elements. This guarantees that the standard is "standard" and can be validated.
(show where it is displayed)

- A metadata element has a default *description*, which can be edited in custom templates. When you duplicate a standard, the original description is shown.
![image](https://user-images.githubusercontent.com/35276300/214671674-326b5de0-b7af-42de-baa1-ce82fe3b50a6.png)

- A metadata element or sub-element will contain information of a specific *format*: numeric, string, array, or boolean.  

- An element can be declared as *required* or *recommended* in the template. A 'diagnostic" tool is embedded in the Metadata Editor, which flags metadata elements that contain invalid content (based on validation rules) and elelements declared as *required* but for which no content has been entered. Also, the navigation bar provides an option to only display the *required* and/or *recommended* elements. This option is provided to allow data curators to focus on the most important metadata elements.

 ![image](https://user-images.githubusercontent.com/35276300/214671469-ce5a006c-5cca-4ff8-b816-eab44c948439.png)

- The way each element will be displayed in the Metadata Editor entry pages is controlled in the Template Manager. The "DISPLAY" parameter provides the main customization options.
  
![image](https://github.com/ihsn/editor/assets/35276300/b2904802-fc8f-404e-aa66-a9a0842e6430)

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

- Controlled vocabularies

Enter your controlled vocabularies in the tab "CONTROLLLED VOCABULARY". Each element in a controlled vocabulary has a code (which should be used consistently across templates, including templates in different languages), and a label for the item (or multiple labels for multi-field elements). When an element has a controlled vocabulary, a drop-down menu or a list of options will be displayed for the element in the metadata entry form. You may define in the template whether the user will be forced to make a selection from the vocabulary  (Display = "dropdown"), or have the option to enter a value that is not in the vocabulary (Display = "dropdown-custom"). 

![image](https://github.com/ihsn/editor/assets/35276300/d0ac8d9d-22bb-4df9-9067-efaea0364201)

In the Metadata Entry form:
![image](https://github.com/ihsn/editor/assets/35276300/b01f9ef7-5e7f-49b3-a8fc-57bd9da2965e)

![image](https://github.com/ihsn/editor/assets/35276300/92e49795-7ab9-4fe8-ae9b-b05a6b229bb0)

- An element may have a default value. A tool is provided in the Metadata Editor to "Load default values". When you document a dataset, this option will provide you with a way to automatically fill out the fields for which a default value is available.

![image](https://user-images.githubusercontent.com/35276300/214395605-c5ec98d9-23db-48d5-badf-a37014273e9d.png)

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

## Sharing custom templates

You can export your templates by clicking on "Export". This will generate a JSON file that contathat you can save and share.

![image](https://github.com/ihsn/editor/assets/35276300/789443ec-4f24-42f5-9e8b-68cb4b89d74d)

Note that the web plugin may add two lines to the JSON file, which are not part of the template itself. Using a text editor, delete these two lines. The JSON file will also contain the "id" and "uid" of the template, which are specific to your instance of the Metadata Editor. We suggest you also delete these two lines if you share your template. When the template is imported by another user of the Metadata Editor, a new id and uid will be automatically generated, which will be specific to the instance of the user. Note also that if you export a template to edit it manually, you must delete the id and uid lines if you want to be able to re-import it in your Metadata Edotor (otherwise, the Metadata Editor will consider it as an existing template and will refuse to overwrite it).

![image](https://github.com/ihsn/editor/assets/35276300/faba3bac-5fdb-44d3-8ad3-4059d81a1579)

Templates in JSON format can be imported in the Metadata Editor using the "Import template" button in the Template Manager page.

![image](https://github.com/ihsn/editor/assets/35276300/140db1a8-39aa-4725-9977-1a5886bcd07f)


## Deleting templates

You can delete a template by clicking on "delete" in the Template list.

![image](https://github.com/ihsn/editor/assets/35276300/f0d31c7a-6411-435f-a5e3-874c7f0ae357)


