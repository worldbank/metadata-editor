# Managing projects, collections, and permissions

## The "My projects" page

A "project" corresponds to a dataset of any type covered by the Metadata Editor. A project can thus be a microdataset, a geographic dataset (or servive), a document, a statistical table, an indicator, a database, a video, an image, or a research project with its associated scripts. The projects are listed in the "Project page". The projects you see in that page are all projects you have access to (as owner, editor, or viewer). The list is thus based on your credentials. When the Metadata Editor is installed on a stand-alone personal computer, the credentials will not impact the list of projects you can see; all projects will be accessible as you are the owner" of all projects created on your PC. But when the application is installed on a server accessible to a group of users, each user will have some roles and permissions and the list will be specific to the profile of each user. The projects in the Project page can be filtered by data type, and searched by keyword. The projects can also be organized by collection. 

![image](https://user-images.githubusercontent.com/35276300/233735017-3f2f6642-1fd5-47f5-9f69-e49496a6cd17.png)

The **My projects** page allows you to:
- Create a project from scratch
- Create a project by importing metadata
- Load an existing project from a zip package
- Duplicate a project
- Open a project for editing
- Share a project, with a specific user or by adding it to a shared collection 
- Delete a project
- Create collections to organize and share projects

> WARNING: The **My project** page is not a catalog. You may have multiple projects with the same title and/or the same identifier in your list of projects (which you cannot have in a catalog, where the project identifier has to be unique). 


## Creating a project from scratch

To create a new project, click on "Create project", and select a data type when prompted. This will open a new project page specific to the selected type of data, with the default metadata template for the data type. When you create a new project, only you (and the system administrator) will have access to it. You may share your project with others (when the Metadata Editor runs on a server). See section "Sharing projects".


## Creating a project by importing metadata

If you have already documented a dataset using one of the metadata standards or schemas supported by the Metadata Editor, you can import it in the Metadata Editor. 
You may also import metadata directly from a NADA catalog, by providing the URL of the catalog entry in NADA. 

> NOTE: The Metadata Editor is an API-based system, and so is NADA. Using Python or R, you may programatically import projects in a batch.


## Loading a project (zip package)

The Metadata Editor provides an option to package projects into a zip file that contains all data, metadata, and related resources. If you receive such a package, you may import it from the Project page and add it to your list of projects. Click on "Load project from zip", and select the zip file. 


## Duplicating a project [to do]

There may be situations where you want to duplicate a project. For example, if you have a series of datasets that have most of the metadata in common, duplicating a project then editing a few elements may be an efficient solution. When you duplicate a project, the title and identifier are not replicated as is. The prefix "Copy of" is automatically added to prevent the risk of accidentally generating entries that would be considered as the same when published in a catalog. To duplicate a project, click on the "Duplicate" icon. 


## Opening a project

By clicking on the title of a project, you will open the project page where you have all options to edit the metadata.


## Sharing a project

By default, when a project is created, it is only seen by its creator (and by the administrtaor of the Metadata Editor server when installed on a server). To share a project with other users of the Metadata Editor (when installed on a server):
- Click on the "Share" icon, then select the user(s) you want to share your project with, and set their role (editor, reviewer).
- Attach your project to a collection. All users who have access to the collection will then have access to your project (as editor or as reviewer, depending on the other user's credentials for the collection). Note that a same project can be attached to multiple collections. To add one or multiple project(s) to a collection, check the projects you want to attach to a collection, then press the "Add to collection" button and select the collection. See section "Managing collections" below for information on how to create a collection. 


## Locking a project [to do]

Use case: team of curators in charge of generating metadata; when done, submit to supervisor. Supervisor reviews and approves, then locks the project to prevent further change. Can unlock / request unlocking.


## Deleting a project

To delete a project, click on the "Delete" icon. Note that if you have not previously exported your project as a package (or your metadata as a JSON or XML file), the information will not be recoverable.


## Managing collections

When a project is created, it belongs only to its creator and is only visible in the creator's home page. A project can be added to one or multiple **collections**. Collections are virtual containers. They provide a way to organize projects by theme, team, or other criteria. Also important, they allow projects to be shared with a groups of collaborators.

To create a collection, click on "Collections" in the My projects page.

![image](https://github.com/ihsn/editor/assets/35276300/f4d28fd5-4806-49d5-a707-eb026dd97d45)

A list of existing collections will be displayed. Click on "Create new collection" to create a new one. Give your collection a short title, and (optional) a description (by clicking on the Edit button next to the collection name).

![image](https://github.com/ihsn/editor/assets/35276300/efcd6731-fe17-4a7c-89c2-953244844df8)

Use the "Manage access" option to add/edit the list of users you want to share the collection with, and their role. All users listed in this list will be able to view, and possibly edit, all entries listed in this collection.

![image](https://github.com/ihsn/editor/assets/35276300/762aec63-6e0c-4722-8db3-6c25ba780395)

The collections to which an entry belongs will be listed in the My projects page. To remove an entry from a collection, click on the "X" next to the collection name.

![image](https://github.com/ihsn/editor/assets/35276300/19dacd6c-35d0-4598-9a86-9f016bc987ff)

Entries in the My projects page can be filterd by collection.

![image](https://github.com/ihsn/editor/assets/35276300/c01525c5-f2e6-438b-b7fa-20925ec00e42)



