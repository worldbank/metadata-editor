# Managing templates 

Templates define the metadata entry forms. They provide a way to control what elements are included, in what order and grouping, with what instructions, and labels, with CVs and default values, and validation rules.

NOTES: 
The application comes with pre-loaded Templates (for all data types). Customizing templates is not a requirement to use the ME. But there is probably some value in adapting the provided templates.
You can export and share the templates you produce. You can also import templates from others. 
When you document a dataset, the template is saved in the Project.
Metadata generated using a template can be edited using another template. The selection /switching of the template impacts what is shown in the metadata entry screens; it does not change the content.


![image](https://user-images.githubusercontent.com/35276300/214350230-d3c3bc73-3cb1-4652-918a-3517c1add1ce.png)

For each data type, System templates are provided that contain ALL elements available in the metadata standard or schema. These are hard coded. They cannot be deleted or edited.

![image](https://user-images.githubusercontent.com/35276300/214386239-cb18dd2e-7184-4824-a02f-c2dbf0ab8ffe.png)

To build and select the templates to be used:
-	Duplicate and customize a System template
-	Import a user template
-	Modify a user template 
Need at least one user template per data type you curate, and one to be used for external resources. Then identify the user template you want to use by default for each data type.
Simplest way: duplicate, give a name. You have a user template. But you probably want to customize it. 

![image](https://user-images.githubusercontent.com/35276300/214386279-82d6df69-4cf2-4694-bdfc-a8291a5c519a.png)

To generate a template from the System schema: 
Click “Duplicate”
 
Change the name, and other fields described. A system ID will be given. But make sure the name is unique and provide info in other fields.
All standards and schemas have a section “Metadata information” or “Document description”. It contains a few fields used to document the metadata.
 
What you see:

A description of the metadata element. In System, ALL elements are included.
 
The label of the block and of the element can be changed. Information on the original label and name of the metadata element in the schema is always provided.
 
Status:
 
Description:
 
Display options:

 
[how this is reflected in the metadata entry forms]

Controlled vocabularies:
 
Default values:
 
Validation rules:
 

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

