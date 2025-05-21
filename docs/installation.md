# Installation

## Requirements

Editor requires PHP, MySQL database and a web server. For Data import and export, it requires a Python Application built using FASTAPI, see details below.

### PHP

PHP 8 with following PHP extensions enabled:
- xsl
- xml
- mbstring
- mysqli

### Web server

- Apache 2.4 or later
- IIS 6/7.x or later
- NGINX


### Supported databases

- MySQL 5.x or MySQL 8 (recommended)
 

### Python backend service (for data import/export)
A Python/FastApi backend REST API service for data transformation. The API is required for data import and export from/to Stata, SPSS. It is a wrapper REST API for the  pyreadstat (https://github.com/Roche/pyreadstat) package.

Installation requirements:

- Install Python 3.9 or later - https://www.python.org/downloads/
- Download project from Github - https://github.com/mah0001/pydatatools






## Installation as a server application

The editor installation requires the following:
- editor (Frontend PHP application with MySQL database)
- Python backend app


### Download packages

Follow the steps to download and install editor frontend and backend app. The resulting folder structure will look like this:

```
metadata_editor
│
+--editor
+--pydatatools
```

Navigate to the web server folder where you want to setup the project and run the following commands:

```
$ mkdir metadata_editor
$ cd metadata_editor

$ git clone https://github.com/ihsn/editor
$ git clone https://github.com/mah0001/pydatatools
```

### Configure database

Editor comes with a web based installer. Before you can run the installer, configure the database connection. 

1. Open `editor/application/config` folder and rename/copy `database.sample.php` to `database.php`
2. Open `database.php` file in a text editor like Notepad or Notepad++, and fill in the following informaiton:

`hostname` - ip address or the machine name where database is hosted

`username` - database user name 

`password` - database password

`database` - database name


```php

$db['default'] = array(
	'dsn'	=> '',
	'hostname' => 'localhost',
	'username' => 'nada_user',
	'password' => '<db-pass-here>',
	'database' => 'nada',
	'dbdriver' => 'mysqli',
	'dbprefix' => '',
	'pconnect' => FALSE,
	'db_debug' => FALSE,
	'cache_on' => FALSE,
	'cachedir' => '',
	'char_set' => 'utf8',
	'dbcollat' => 'utf8_general_ci',
	'swap_pre' => '',
	'encrypt' => FALSE,
	'compress' => FALSE,
	'stricton' => FALSE,
	'failover' => array(),
	'save_queries' => TRUE,
	'prefix_short_words'=>TRUE
);

```

3. Save the file

### Set folder permissions

Run the following commands to set read/write permissions for the folders where the data will be stored:

```
$ chmod -R 775 datafiles files logs
```


### Running the installer

- Open web browser to the location of the Editor installation. For example: http://your-domain/editor-folder-name, or http://localhost/editor-folder-name.

- Check that all settings are marked with a green tick and fix any that are not on your webserver before running the installer.

- Click on the Install Database button and complete the form to create an initial Site Administrator account.


**Note:** Create a complex password at least 12 characters long with some uppercase, punctuation and numbers to aid security of your site. 


### Run PyDataTools app

Open the folder for pydatatools application (e.g. metadata_editor/pydatatools) and run the following command to start the web service:

```
$ nohup python3.9 -m uvicorn main:app --reload --host 0.0.0.0 --port 8000 &
```



## Installation as a standalone application

Editor is a web application and requires running PHP under a web server or with the built-in web server provided by PHP. For standalone application running on a desktop, the steps below use the built-in PHP web server. 

### Requirements

- PHP: PHP version 8
- MySQL database
- Python 3.9 or later

### steps

Create a folder structure as below:

```
metadata_editor
│
+--editor
+--pydatatools
+--php
```

- Editor: Download editor zip from Github (https://github.com/ihsn/editor) and unzip to the folder `/metadata_editor/editor` folder
- PyDataTools: Download from github (https://github.com/mah0001/pydatatools) and unzip content to the folder `/metadata_editor/pydatatools`
- PHP: Download https://windows.php.net/downloads/releases/php-8.2.8-Win32-vs16-x64.zip and unzip to the folder `/metadata_editor/php`
- Python: Download https://www.python.org/downloads/ and install

**Instructions**



## Upgrading the schemas and core templates

Instructions
