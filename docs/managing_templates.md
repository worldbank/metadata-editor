# Managing templates 

The metadata standards and schemas embedded in the Metadata Editor contain a large number of metadata elements. For each data type, hard-coded core templates are provided that contain all elements of the standard or schema.These templates cannot be deleted or edited. But they can be duplicated and customized, allowing users to generate their own templates.

![image](https://user-images.githubusercontent.com/35276300/214394808-d752cb8e-b007-4642-9e16-e2cb26b16e91.png)

Few users will find them all elements of a standard or schema relevant. Templates are created to subset and customize the elements to the specific needs of the organization that uses the Metadata Editor. By creating user templates, users of the Metadata Editor have the possibility to: 
- select the elements from the available standards and schemas that they consider relevant; only these elements will be displayed in the metadata entry forms.
- organize the selected elements into groups
- control the order in which the elements appear in the metadata entry forms
- customize the labels and instructions associated with each element
- enter controlled vocabularies and default values for the content of the element, when appropriate
- set validation rules for the content of elements.

The Metadata Editor application comes with a collection of pre-loaded templates (the "IHSN templates"), covering all data types. Generating custom templates is thus not a requirement. But most users will find it useful to adapt the proposed templates.

User templates can be exported and imported, allowing users to share their templates. When you document a dataset, the template is saved in the Project.

NOTE: Metadata generated using a template can be edited using another template. The selection /switching of the template impacts what is shown in the metadata entry forms; it does not change the content.

![image](https://user-images.githubusercontent.com/35276300/214350230-d3c3bc73-3cb1-4652-918a-3517c1add1ce.png)


![image](https://user-images.githubusercontent.com/35276300/214386239-cb18dd2e-7184-4824-a02f-c2dbf0ab8ffe.png)

To build and select the templates to be used:
-	Duplicate a core template and build your template from the standard or schema
-	Import a user template
-	Modify a user template 

You will need at least one user template per data type you curate, and one to be used for external resources. One user template per data type will be selected as the default one.

![image](https://user-images.githubusercontent.com/35276300/214386279-82d6df69-4cf2-4694-bdfc-a8291a5c519a.png)

To generate a template:

Click “Duplicate”
 
Change the name, and other fields described. A system ID will be given. But make sure the name is unique and provide info in other fields.
All standards and schemas have a section “Metadata information” or “Document description”. It contains a few fields used to document the metadata.

![image](https://user-images.githubusercontent.com/35276300/214395038-cc7ac3e9-70f4-49b7-9b6c-18001bce3c9e.png)

What you see:

A description of the metadata element. 

![image](https://user-images.githubusercontent.com/35276300/214395275-46bb01e0-6a43-4973-a456-4e68693001ea.png)

The label of the block and of the element can be changed. Information on the original label and name of the metadata element in the schema is always provided.
 
Status:
 
Description:
 
Display options:
 
[how this is reflected in the metadata entry forms]


Controlled vocabularies:

![image](https://user-images.githubusercontent.com/35276300/214395449-bb8967db-8115-4ea4-8946-17313f3fb840.png)

 
Default values:

![image](https://user-images.githubusercontent.com/35276300/214395605-c5ec98d9-23db-48d5-badf-a37014273e9d.png)

This works for repeatable and complex elements as well.

![image](https://user-images.githubusercontent.com/35276300/214395688-f0805e52-7794-40fa-80f1-b5e98a1632e0.png)
 
Validation rules:
 
![image](https://user-images.githubusercontent.com/35276300/214395548-ef25e59a-18bb-4efa-a339-52c124c209e5.png)


Some elements are more complex and have sub-elements. But they can be controlled and customized the same way. Example:
[...]

Elements may be grouped. They are grouped by main section of the standard or schema. This is constrained. But you can also organize elements of a section into folders and sub-folders. This is just for convenience. It will not impact how the metadata is stored in the metadata file. And if the metadata are read using another template, they will be visible as long as the element is somewhere in the new template.
NOTE: If you read metadata that contain element X using a template that does not include element X and save, the element will NOT be deleted from the metadata. So you do not have to worry about the template you use. The selection of a template will never result in automatic deletion of existing content.
You may want to exclude or move an element. 
To drop one: >
It is then sent back to a repository of “Available elements” (list of available but not selected elements). The System templates have no “Available elements” because all are included. But a user template will typically have some.
You can see the available elements here:
NOTE: When you send an element back to the repository of unused elements, the customization of label, description, etc. are lost.
To add an element from that list: <
To move an element up and down within a group:
To move from one group to another without losing your customizations: 
NOTE: You can only add an element in the standard section it belongs to. For example, in DDI, you cannot include element from file or variable description in Study description, or from Study description in Variable description. Within a Standard section, you are free to group and sequence the elements as you want.
To create a new group:
 
For complex elements, you also control the sub-elements that you include, and their sequence and description and status.
[screenshot]

Activating the default templates:

Exporting templates:
Importing templates:
-	From a saved template
-	From a project

Deleting templates:

