# Managing projects

## The project page

A project corresponds (the documentation of) a dataset of any type.
All projects are listed in the project page.
The projects you see in the project page are the projects you are authorized to see, based on your credentials. 
If the Metadata Editor is installed on a stand-alone PC, you will see and have access to all projects as you are the owner/administrator of the list. If the application is installed on a server, each user with access to the application will have some roles and permissions. Based on the login/profile, you will see a list of projects that may differ from the list of projects seen by another person with access to the same site. Administrator of the application have access and see all.

![image](https://user-images.githubusercontent.com/35276300/231586542-5818c1a2-1c71-4105-ad3e-1984ecd9a2a2.png)

The permissions/roles can limit what you can do with a project. Add, edit, delete, publish.

The project page allows projects to be organized by collection. You can filter projects by collection. You can also filter projects by type of data. And you can search by keyword.

The project page will show who created the project and when, and who last updated the project and when .
You can see who has access.
You can share your project.
Only one person at a time can edit the content of a project. If the project is open by someone else, it will be shown in the page.
If a project has warnings, it is shown by an icon.
Different projects can possibly have the same ID. But catalogs cannot. So you must be careful. Duplicated IDs are shown.

Tags can be used to ...

## Creating new projects

Click on Create project, select type of data
Only you (and admin) have access to the project. To share with others, move to a shared collection or share the project individually.

## Importing / loading projects

If you already have a project, you can import the metadata.

If you have already documented a number of datasets ("projects") using one of the compatible metadata standards, you can import them all in the Metadata Editor using the API and R or Python.

If you have a NADA catalog, there is another option: import from NADA. 
Warning: This will not create project files with data etc.

What we recommend:
...


## Sharing projects

By default, when a project is created, it is only seen by its creator who is then also an administrator of the project.
Projects can be shared individually with selected users, or by being attached to a collection (in which case all users with access to the collection will view your project).

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

