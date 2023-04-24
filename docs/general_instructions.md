# General instructions

The process of documenting a resource using the Metadata Editor follows a standard approach across data types. It involves creating a new project, selecting a metadata template (a customized selection of metadata elements used to document the resource), entering all available information in metadata entry forms, and then saving or publishing the metadata in a catalog. Although the templates are specific to each data type (as each data type uses a different metadata standard), their appearance will be similar across data types. However, some features of the Metadata Editor are specific to certain data types. Microdata, in particular, requires additional functionalities, as much of the metadata to be generated is imported from data files. This section presents the core components of the Metadata Editor that are common to all data types. The User Guide's following sections will provide specific instructions and recommendations for each data type.

## Creating, importing, and editing a project

A *project* in the Metadata Editor is a catalog entry that can represent any type of data. It can be a microdataset (from a census or survey), a database, an indicator or time series, a geographic dataset or service, a document, a statistical table, an image, a video, or a research project.

To **create** a new project, click on "Create new project" in the Projects section, and then select the project type when prompted. 

![image](https://user-images.githubusercontent.com/35276300/234115577-1623cf4b-8fb5-4b7a-8a5e-cbf5acd497a0.png)

A project homepage will be displayed, which is the same for all data types except for the specific template displayed based on the selected data type.

To **edit** an existing project, click on the project title in the "Projects" page, and you will have access to the same page as when you create a new project. If the project is not in your list but is available as a Metadata Editor zip package or as a metadata file (XML or JSON) compliant with one the the metadata standards supported by the Editor, click on **Import** and select the project file (the type of project will automatically be detected by the application).

![image](https://user-images.githubusercontent.com/35276300/234115238-5a1819e7-3ab1-4246-aa78-243ccd2ed484.png)

The Project homepage contains 8 main elements:

1. The Metadata Editor home page 
2. The project title
3. The application's main menu
4. The navigation bar
5. The project thumbnail
6. The metadata template identification and selection
7. A summary of the project validation, where warnings and validation errors will be reported
8. A block (empty when a new project is created) where additional information about the project will be displayed.


### Metadata Editor home page

The application's home page is also referred to as the "Projects page". This page will list all projects you have access to.


### Project title

The **project title** will be displayed only after you enter a title for your project in the metadata entry form.


### Main menu

The main menu provides access to the commands that are common to all data types. It also includes the user login/logout page, where you have the option to edit your profile. 


### Navigation bar

The **navigation bar** lists the sections and elements included in the selected metadata template. You will use this bar to navigate the various pages of the metadata entry forms.


### Project thumbnail

The **project thumbnail** is an optional image that you may select to be used as a thumbnail in a NADA catalog. It can, for example, be the logo of a survey (for microdata), the cover page of a publication (for documents), a low-resolution version of a photo (for an image), or a screenshot of a video. The thumbnail must be an image file in JPG or PNG format. To add or replace a thumbnail, click on "Change image" and select an image file. Pay attention to the size and proportions of the image as it will be used as a thumbnail in a catalog. For example, you may choose to make all thumbnails square.

![image](https://user-images.githubusercontent.com/35276300/233796909-1d465b5d-2a63-4171-a35a-a42c595f2268.png)

![image](https://user-images.githubusercontent.com/35276300/233796889-bdcd8d1e-ff14-4d2a-be09-1ab5c99cd68d.png)


### Metadata template

The **metadata template** in a new project page is the default template for the selected data type (refer to the Template Manager section for information on creating, editing, and importing templates, and setting a template as the default one). If you wish to use a template other than the default one, click on "Switch template" and choose the one you want to use. The navigation bar will promptly reflect the new selection. You can only choose among available templates for the selected data type. Note that you may modify the template of a project at any time (it does not have to be done when the project is created). When you change the template of an existing project, the information already entered using a different template will not be erased if the new template excludes a metadata element that was used earlier. The templates are solely used to create the metadata entry forms. 

![image](https://user-images.githubusercontent.com/35276300/214939822-f513121c-b659-45d1-bb7b-45a0243d471b.png)


## Entering metadata

Once you create a project, you will use the forms to capture all relevant metadata. The forms are generated automatically based on the selected template and usually consist of multiple sections/pages that you can access by selecting a metadata element in the navigation bar. Note that selecting the "Home" element in the navigation bar will take you back to the project Home page.

![image](https://user-images.githubusercontent.com/35276300/234029126-d0fadae0-5aab-480a-b1af-6c2ee700f71b.png)

If you select a section in the navigation bar, all elements of the section will be displayed.

![image](https://user-images.githubusercontent.com/35276300/234029729-fbfe43f3-1f38-40af-89f3-d0b8892b51d9.png)

If you select a specific element, only that element will be shown.

![image](https://user-images.githubusercontent.com/35276300/234029935-e93809d5-2f8e-4171-b887-c94c9950c4d5.png)

You can find a description of the expected content of each element in the metadata entry form by clicking on the "?" icon. For more information on the metadata elements, you may refer to the Schema Guide (https://ihsn.github.io/editor/#/template-manager/microdata). Some metadata elements may be declared as "Required" in the metadata template you have selected. Such elements will be identified by a red asterisk next to their label.

![image](https://user-images.githubusercontent.com/35276300/234031109-3112da09-4dff-46ce-bb14-8c9d4a566d0f.png)
 
The metadata template may include some validation rules. If the content you enter in a field violates a validation rule, an error message will be displayed in red. All violations of rules will also be displayed in the project Home page under "Project validation".

To facilitate navigation, the navigation bar can be expanded or collapsed and filtered. You can apply filters to show only mandatory elements, recommended elements, and/or elements with or without content.

![image](https://user-images.githubusercontent.com/35276300/234031438-da04ec28-80dc-40bf-b3c9-4a549a5fb702.png)

A metadata template may include default values. If you want to apply the default values, click on "@@@@." You will have the option to apply the default values from the templates either to all fields for which default values are available (i.e., overwriting existing content) or only to fields where no content has already been entered (i.e., not overwriting existing content). Fields for which no default value has been entered in the template will not be impacted.

Some metadata elements are "repeatable". In such case, an option to "Add rows" is provided.

![image](https://user-images.githubusercontent.com/35276300/214942382-a69a9dab-2410-4493-8b1e-8d2469b14868.png)

When a controlled vocabulary is entered into the template, a dropdown menu appears for the corresponding field. Depending on how the field is defined in the template, you may be forced to select an entry from the dropdown. In other cases, you may either be forced to select a value from the dropdown or allowed to select the content from the dropdown or enter your own content.

![image](https://user-images.githubusercontent.com/35276300/214942534-d47df5a3-93f0-4d61-b956-46bbc89f0632.png)

Some elements are "composite elements," meaning they are made up of more than one field. Such elements can be copy-pasted. The three dots button will open a copy/paste menu that allows you to copy and paste the content, with options to append or replace.

![image](https://user-images.githubusercontent.com/35276300/234033852-ee6537f4-0c2a-4099-ad43-b86dbe79fe85.png)
![image](https://user-images.githubusercontent.com/35276300/234034226-6e71146e-8bab-4136-8bd4-ec5d9c401352.png)

This functionality may be very useful for some elements, like keywords. Here is an example of how it could be used in combination with an external application (ChatGPT in this case). Suppose you are documenting the indicator "child mortality" and want to add a series of closely related keywords to the metadata to improve the discoverability of the indicator in your online catalog. We can ask ChatGPT to suggest a list of keywords.

![image](https://user-images.githubusercontent.com/35276300/234036928-53450137-f83a-4699-b9be-9f3e2f1f43d8.png)

From the ChatGPT page, you may copy the list (do not copy the whole answer - just the list of 20 keywords). Then you can paste it using the "Paste (Append)" or "Paste (Replace)" option in the Metadata Editor and delete the keywords you may find non-relevant.

![image](https://user-images.githubusercontent.com/35276300/234038095-07977207-4c6d-4083-b4b4-e811c8b998d1.png)


In some cases, you may want to import metadata from another project. You may do so by clicking on ...
@@@@ 

![image](https://user-images.githubusercontent.com/35276300/234041042-299c7717-488f-4fc7-9661-7c5d9f993a9e.png)

The Metadata Editor saves all your metadata in a JSON format. You may export the JSON file from the main menu.


The information you enter in metadata entry pages is saved automatically (with a few exceptions, like external resources) in the Metadata Editor database. If you want to save your project as a (collection of) file(s), you may generate a project package. A project package is a zip file that will contain all data, metadata, template, and external resources related to your project. This package can then be stored, shared, and reimported in the Metadata Editor. To generate such a package, click on "Export project" in the **Project** main menu item.

![image](https://user-images.githubusercontent.com/35276300/234041377-0705c7a0-e5ca-45f8-9e3e-dadc5ba0a5dd.png)


The **Project** main menu item also provides you with an option to generate a PDF copy of the metadata for your project and to publish your metadata in

