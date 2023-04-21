# Managing projects

## The project page

A "project" corresponds to a dataset of any type covered by the Metadata Editor. A project can thus be a microdataset, a geographic dataset (or servive), a document, a statistical table, an indicator, a database, a video, an image, or a research project with its associated scripts. The projects are listed in the "Project page". The projects you see in that page are all projects you have access to (as owner, editor, or viewer). The list is thus based on your credentials. When the Metadata Editor is installed on a stand-alone personal computer, the credentials will not impact the list of projects you can see; all projects will be accessible as you are the owner" of all projects created on your PC. But when the application is installed on a server accessible to a group of users, each user will have some roles and permissions and the list will be specific to the profile of each user. The projects in the Project page can be filtered by data type, and searched by keyword. The projects can also be organized by collection. 

![image](https://user-images.githubusercontent.com/35276300/233735017-3f2f6642-1fd5-47f5-9f69-e49496a6cd17.png)

The Project page allows you to:
- Create a new project from scratch
- Create a new project by importing metadata
- Load an existing project from a zip package
- Duplicate a project
- Open a project for editing
- Share a project
- Delete a project
- Archive projects


> WARNING: The Project page is not a catalog. You may have multiple projects with the same title and/or the same identifier in your list of projects (which you cannot have in a catalog, where the project identifier has to be unique). Duplicated identifiers are flagged in the project page.


## Creating a new project from scratch

To create a new project, click on "Create project", and select a data type when prompted. This will open a new project page specific to the selected type of data, with the default metadata template for the data type. When you create a new project, only you (and the system administrator) will have access to it. You may share your project with others (when the Metadata Editor runs on a server). See section "Sharing projects".


## Creating a new project by importing metadata

If you have already documented a dataset using one of the metadata standards or schemas supported by the Metadata Editor, you can import it in the Metadata Editor. 
You may also import metadata directly from a NADA catalog, by providing the URL of the catalog entry in NADA. 

> NOTE: The Metadata Editor is an API-based system, and so is NADA. Using Python or R, you may programatically import projects in a batch.


## Loading a project (zip package)

The Metadata Editor provides an option to package projects into a zip file that contains all data, metadata, and related resources. If you receive such a package, you may import it from the Project page and add it to your list of projects. Click on "Load project from zip", and select the zip file. 


## Duplicating a project

There may be situations where you want to duplicate a project. For example, if you have a series of datasets that have most of the metadata in common, duplicating a project then editing a few elements may be an efficient solution. When you duplicate a project, the title and identifier are not replicated as is. The prefix "Copy of" is automatically added to prevent the risk of accidentally generating entries that would be considered as the same when published in a catalog. To duplicate a project, click on the "Duplicate" icon. 


## Opening projects

By clicking on the title of a project, you will open the project page where you have all options to edit the metadata.


## Sharing projects

By default, when a project is created, it is only seen by its creator (and by the administrtaor of the Metadata Editor server when installed on a server). To share a project with other users of the Metadata Editor (when installed on a server):
- Click on the "Share" icon, then select the user(s) you want to share your project with, and set their role (editor, reviewer).
- Attach your project to a collection. All users who have access to the collection will then have access to your project (as editor or as reviewer, depending on the other user's credentials for the collection). Note that a same project can be attached to multiple collections. To add one or multiple project(s) to a collection, check the projects you want to attach to a collection, then press the "Add to collection" button and select the collection. See section "Managing collections" below for information on how to create a collection. 


## Deleting projects

To delete a project, click on the "Delete" icon. Note that if you have not previously exported your project as a package (or your metadata as a JSON or XML file), the information will not be recoverable.


## Archiving projects

Backup


### Managing collections

When a project is created, it belongs only to its creator and is only visible in the creator's home page. A project can be added to one or multiple collections.
Collections are virtual containers (they are like tags). They provide a way to organize entries by theme, team, or other criteria. Also important, they allow projects to be shared with a groups of collaborators.

Creating and deleting collections:
- Who can create? Who can delete?
- Setting permissions at the collection level
- Deleting a collection does not delete the entries it contains. It is like removing a tag.
- Adding indicators to a collection
- Removing an indicator from a collection

Programatically (using the API):


### Managing permissions

Different roles:
- ME administrator (super admin)
- At project level:
  - Administrator: create, edit, delete, lock, share, add to collection
  - Editor
  - Reviewer
  - View
  - Publisher

