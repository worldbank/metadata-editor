## General instructions

The approach to document a resource using the Metadata Editor is very much standard across data types. The process consists of creating a new project, selecting a metadata template (a customized selection of the metadata elements to be used to document the resource), entering all available information in metadata entry forms, then saving the metadata and/or publishing it in a catalog. Although the templates are specific to each data type (as each data type makes use of a different metadata standard), their looka nd feel will be similar across data types. Some features of the Metadata Editor are however specific to some data types. Microdata in particular requires some additional functionalities, as much of the metadata to be generated is imported from data files. We present here the core components of the Metadata Editor that are common to all data types. The following sections of the User Guide will provide instructions and recommendations that are specific to each data type.

## Creating a new project

A *project* consists of a catalog entry of any type supported by the Metadata Editor. It can be a microdataset (from a census, as survey, or other), a database, an indicator or time series, a geographic daatset or service, a document, a statistical table, an image, a video, or a research project. To create a new project, click on "Create new project" in the **Projects** and, when prompted, select the project type. A new project home page will be displayed. This new project page will be the same for all data types, except for the Template being shown, which will be specific to the selected data type.  

![image](https://user-images.githubusercontent.com/35276300/234024941-e6221556-2cc6-493d-96f0-87a276dcca25.png)

The page contains 6 main elements:

1. The project title 
2. The navigation bar 
3. The project thumbnail
4. The metadata template identification and selection
5. A summary of the project validation, where warnings and validation errors will be reported
6. A block (empty when the project is created) where additional information on the project will be displayed.

The **project title** will only be shown after you enter a title for your project in the metadata entry form.

The **navigation bar** list the sections and elements included in the selected metadata template. You will use this bar to navigate the varioous pages of the metadata entry forms.

The **project thumbnail** is an image that you may (optional) select to be used as thumbnail in a NADA catalog. It can for example be the logo of a survey (for microdata), the cover page of a publication (for documents), a low-resolution version of a photo (for an image), a screenshot of an image of a video, etc. The thumbnail must be an image file in JPG or PNG format. To add or replace a thumbnail, click on "Change image" and select an image file. Pay attention to the size (proportions) of the image; keep in mind that it will be used as a thumbnail in a catalog. You may for example chose to make all thumbnail square. 

![image](https://user-images.githubusercontent.com/35276300/233796909-1d465b5d-2a63-4171-a35a-a42c595f2268.png)

![image](https://user-images.githubusercontent.com/35276300/233796889-bdcd8d1e-ff14-4d2a-be09-1ab5c99cd68d.png)

The **metadata template** in a new project page will be the default template for the selected data type (see the *Template manager* section for information on how to create, edit, and import templates, and how to set a template as the default one). If you want to use a template other than the default one, click on "Switch template" and select the one to be used. The navigation bar will immediately reflect the new selection. You can only select among available templates for the selected data type. Note that you may change the template of a project at any time (it does not have to be done at the time the project is created). When you change the template of an existing project, the information already entered using a different template will not be deleted if the new template excludes a metadata element that had bee used previously. The templates are only used for the purpose of generating the metadata entry forms. 

![image](https://user-images.githubusercontent.com/35276300/214939822-f513121c-b659-45d1-bb7b-45a0243d471b.png)


## Entering metadata

Once the project has been created, you will use the forms (generated automatically based on the selected template) to capture all relevant metadata. The form istypically  made of multiple sections/pages, which you access by selecting a section of metadata element in the navigation bar. Note that the "Home" element in the navigation bar will take you back to the project Home page.

![image](https://user-images.githubusercontent.com/35276300/234029126-d0fadae0-5aab-480a-b1af-6c2ee700f71b.png)

If you select a section in the navigation bar, all elements of the section will be displayed.

![image](https://user-images.githubusercontent.com/35276300/234029729-fbfe43f3-1f38-40af-89f3-d0b8892b51d9.png)

If you select a specific element, only that element will be shown.

![image](https://user-images.githubusercontent.com/35276300/234029935-e93809d5-2f8e-4171-b887-c94c9950c4d5.png)

A description of the expected content of each element is available in the metadata entry form by clicking on the "?". Note that you may find more information on the metadata elements in the Schema Guide (https://ihsn.github.io/editor/#/template-manager/microdata). Some metadata elements may have been declared as *Required* in the metadata template you have selected. Such elements will be identified by a red asterisk next to their label.

![image](https://user-images.githubusercontent.com/35276300/234031109-3112da09-4dff-46ce-bb14-8c9d4a566d0f.png)
 
The metadata template may also include some validation rules. If the content you enter in a field violates a validation rule, an error message will be displayed in red. All violation of rules will also be displayed in the project Home page (*Project validation*). 

To facilitate the navigation, the navigation bar can be expanded/collapsed, and filtered. Filters can be applied to only show mandatory elements, recommended elements, and/or elements with/without content.  

![image](https://user-images.githubusercontent.com/35276300/234031438-da04ec28-80dc-40bf-b3c9-4a549a5fb702.png)

A metadata template may include default values. If you want to apply the default values, click on @@@@. You will have the option to apply the default values from the templates either to all fields for which default values are available (i.e. overwriting existing content), or only to fields where no content has already been entered (i.e. not overwriting existing content). Fields for which no default values has been entered in the template will not be impacted. 

Some metadata elements are "repeatable". In such case, an option to "Add rows" is provided.

![image](https://user-images.githubusercontent.com/35276300/214942382-a69a9dab-2410-4493-8b1e-8d2469b14868.png)

When a controlled vocabulary has been entered in the template, a drop down menu will appear for the corresponding field. Depending on how the field has been defined in the template, you will be forced to select an entry from the drop down. In other cases, you will either be forcedto select a value from the drop down, or allowd to select the content from the drop down or enter your own content. 

![image](https://user-images.githubusercontent.com/35276300/214942534-d47df5a3-93f0-4d61-b956-46bbc89f0632.png)

Some elements are "composite elements", i.e. they are made of more than one field. Such elements can be copy/pasted. The 3 dots button will open a copy/paste menu that allows you to copy then paste the content, with option to append or replace.

![image](https://user-images.githubusercontent.com/35276300/234033852-ee6537f4-0c2a-4099-ad43-b86dbe79fe85.png)
![image](https://user-images.githubusercontent.com/35276300/234034226-6e71146e-8bab-4136-8bd4-ec5d9c401352.png)

This functionality may be very handy for some elements, like *keywords*. Here is an example of how it could be used in combination with an external application (chatGPT in this case). Suppose you are documenting the indicator "child mortality" and want to add a series of closely related keywords to the metadata to improve the discoverability of the indicator in your on-line catalog. We can ask chatGPT to suggest a list of keywords.  

![image](https://user-images.githubusercontent.com/35276300/234036928-53450137-f83a-4699-b9be-9f3e2f1f43d8.png)

From the chatGPT page, you may copy the list (do not copy the whole answer - just the list of 20 keywords). Then you can paste it using the "Paste (Append)" or "Paste (Replace)" option in the Metadata Editor, and delete the keywords you may find non-relevant.

![image](https://user-images.githubusercontent.com/35276300/234038095-07977207-4c6d-4083-b4b4-e811c8b998d1.png)


In some cases, you will want to import metadata from another project. You may do so by ...

![image](https://user-images.githubusercontent.com/35276300/234041042-299c7717-488f-4fc7-9661-7c5d9f993a9e.png)

The Metadata Editor saves all your metadata in a JSON format. You may export the JSON file from the main menu.


The information you enter in metadata entry pages are saved automatically (with a few exceptions, like external resources) in the Metadata Editor database. If you want to save your project as a (collection of) file(s), you may generate a project package. A project package is a zip file that will contain all data, metadata, template, and external resources related to your project. This package can then be stored, shared, and reimported in the Metadata Editor. To generate such a package, click on "Export project" in the **Project** main menu item.

![image](https://user-images.githubusercontent.com/35276300/234041377-0705c7a0-e5ca-45f8-9e3e-dadc5ba0a5dd.png)

The **Project** main menu item also provides you with an option to generate a PDF copy of the metadata for your project, and to publish your metadata in a NADA catalog (if you have such a catalog and have credentials as catalog administrator; see section "Publishing data and metadata" for more information).

