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

IMPORTANT NOTES: 
1. When you document a dataset, the template is saved in the Project.
2. Metadata generated using a template can be edited using another template. The selection /switching of the template impacts what is shown in the metadata entry forms; it does not change the content.
3. Typically, you will rarely visit the Template Manager. Once a template has been designed or imported, and set as default, there is not much reason to change them. Spend some time reviewing and customizing the template to your specific needs. Using stable templates will guarantee consistency.
4. Do not use an element for what it is not designed to be. If you think elements are missing, and no generic field can suit your needs, contact us. A standard or schema is expected to provide predictability across organizations. Customize the templates, but not the purpose of the elements they contain. 
5. If you read metadata that contain element X using a template that does not include element X and save, the element will NOT be deleted from the metadata. So you do not have to worry about the template you use. The selection of a template will never result in automatic deletion of existing content.

## System vs user templates

The standard or schema for each data type is provided in the Metadata Editor as a *system* template. System templates contain all elements of the standard or schema, with their default label and description. They contain a very small number of validation rules and controlled vocabularies. These system templates cannot be deleted or edited. But they can be duplicated then customized, allowing users to generate their own *user templates*. User templates can be exported and imported, i.e. shared with other users. Multiple user templates can be created for a given data type.

![image](https://user-images.githubusercontent.com/35276300/214394808-d752cb8e-b007-4642-9e16-e2cb26b16e91.png)

The Metadata Editor application comes with a collection of pre-designed user templates (the *IHSN templates*), covering all data types. Generating new user templates is thus not a requirement to make use of the Metadata Editor. 

## Creating or customizing a user template

To build and select the templates to be used:
-	Duplicate a core template and build your template from the standard or schema
-	Import a user template
-	Modify a user template 

You will need at least one user template per data type you curate, and one to be used for external resources. One user template per data type will be selected as the default one.

![image](https://user-images.githubusercontent.com/35276300/214386279-82d6df69-4cf2-4694-bdfc-a8291a5c519a.png)

To generate a template:

Click “Duplicate”. The page will show a new template. Edit the content of the template description.

![image](https://user-images.githubusercontent.com/35276300/214677926-79ad0824-3678-4f23-999f-390ed63e248a.png)
 
Change the name, and other fields described. A system ID will be given. But make sure the name is unique and provide info in other fields.
All standards and schemas have a section “Metadata information” or “Document description”. It contains a few fields used to document the metadata.


### Description of a metadata element

A description of the metadata element. 

![image](https://user-images.githubusercontent.com/35276300/214395275-46bb01e0-6a43-4973-a456-4e68693001ea.png)

The label of the block and of the element can be changed. Information on the original label and name of the metadata element in the schema is always provided.

Label:
![image](https://user-images.githubusercontent.com/35276300/214671331-30aefe93-0053-4710-a88b-de101810170b.png)

Status:
![image](https://user-images.githubusercontent.com/35276300/214671469-ce5a006c-5cca-4ff8-b816-eab44c948439.png)
How this will be used?
Do not make many elements mandatory.
 
Description:
![image](https://user-images.githubusercontent.com/35276300/214671674-326b5de0-b7af-42de-baa1-ce82fe3b50a6.png)
 
Display options:
![image](https://user-images.githubusercontent.com/35276300/214672121-1d6ca75a-8cf4-4590-8673-fed61cf204ab.png)
How this is reflected in the metadata entry forms

Controlled vocabularies
![image](https://user-images.githubusercontent.com/35276300/214395449-bb8967db-8115-4ea4-8946-17313f3fb840.png)

 
Default values
![image](https://user-images.githubusercontent.com/35276300/214395605-c5ec98d9-23db-48d5-badf-a37014273e9d.png)

This works for repeatable and complex elements as well.
![image](https://user-images.githubusercontent.com/35276300/214395688-f0805e52-7794-40fa-80f1-b5e98a1632e0.png)
 
Validation rules
![image](https://user-images.githubusercontent.com/35276300/214395548-ef25e59a-18bb-4efa-a339-52c124c209e5.png)

### Navigation bar and groupings

Elements may be grouped. They are grouped by main section of the standard or schema. This is constrained. 
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

### Available vs selected elements

One element of a metadata standard or schema can only appear once in a template. When you duplicate a core template, ALL elements are in the list. 
You can drop elements using the > button. When you drop an element, it is sent back to the list of available elements. All elements from a standard/schema that are not selected in the template are available for selection. They can only be added to the section of the standard they belong to. For example, in DDI, you cannot include element from file or variable description in Study description, or from Study description in Variable description. Within a Standard section, you are free to group and sequence the elements as you want.

NOTE: When you send an element back to the repository of unused elements, the customization of label, description, etc. are lost.

![image](https://user-images.githubusercontent.com/35276300/214675136-f756baf6-597a-40d3-b0be-0f00631df3d1.png)

To see all elements including the ones already selected: 
![image](https://user-images.githubusercontent.com/35276300/214675037-42382140-6333-48e7-8c81-3cbed5d6d00f.png)

### Available vs selected sub-elements
 
For complex elements, you also control the sub-elements that you include, and their sequence and description and status.
[screenshot]

### Activating the default templates


## Sharing templates

Exporting templates:

Importing templates:
-	From a saved template
-	From the IHSN website
-	From a project


## Deleting templates




