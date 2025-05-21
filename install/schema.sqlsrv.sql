--
-- Table structure for table repositories
--

CREATE TABLE repositories (
  id int NOT NULL IDENTITY(1,1),
  pid int DEFAULT NULL,
  repositoryid varchar(255) NOT NULL,
  title varchar(100) NOT NULL,
  url varchar(255) DEFAULT NULL,
  organization varchar(45) DEFAULT NULL,
  email varchar(45) DEFAULT NULL,
  country varchar(45) DEFAULT NULL,
  status varchar(255) DEFAULT NULL,
  surveys_found int DEFAULT NULL,
  changed int DEFAULT NULL,
  type int  DEFAULT NULL,
  short_text varchar(1000) DEFAULT NULL,
  long_text text,
  thumbnail varchar(255) DEFAULT NULL,
  weight int  DEFAULT NULL,
  ispublished tinyint  DEFAULT NULL,
  section int DEFAULT NULL,
  group_da_public  int DEFAULT '0',
  group_da_licensed  int DEFAULT '0',
  PRIMARY KEY (id)
);

CREATE UNIQUE NONCLUSTERED INDEX IX_repositories on [dbo].[repositories]
(
	[repositoryid] ASC
);


--
-- Table structure for table lic_files
--

CREATE TABLE lic_files (
  id int NOT NULL IDENTITY(1,1),
  surveyid int NOT NULL,
  file_name varchar(100) NOT NULL,
  file_path varchar(255) NOT NULL,
  changed int NOT NULL,
  PRIMARY KEY (id)
);



--
-- Table structure for table site_menu
--

CREATE TABLE site_menu (
  id int NOT NULL IDENTITY(1,1),
  pid int DEFAULT NULL,
  title varchar(100) DEFAULT NULL,
  url varchar(255) DEFAULT NULL,
  weight int DEFAULT NULL,
  depth int DEFAULT NULL,
  module varchar(45) DEFAULT NULL,
  PRIMARY KEY (id)
);


--
-- Dumping data for table site_menu
--

set IDENTITY_INSERT site_menu ON;
INSERT INTO site_menu(id,pid,title,url,weight,depth,module)
 VALUES (1,0,'Dashboard','admin',0,0,'admin'),(2,0,'Studies','admin/catalog',1,0,'catalog'),(4,0,'Citations','admin/citations',3,0,'citations'),(5,0,'Users','admin/users',4,0,'users'),(6,0,'Menu','admin/menu',5,0,'menu'),(7,0,'Reports','admin/reports',6,0,'reports'),(8,0,'Settings','admin/configurations',7,0,'configurations'),(12,2,'-','-',70,1,'catalog'),(13,2,'Licensed requests','admin/licensed_requests',80,1,'catalog'),(14,2,'-','-',90,1,'catalog'),(15,2,'Manage collections','admin/repositories',60,1,'repositories'),(17,4,'All citations','admin/citations',100,1,'citations'),(18,4,'Import citations','admin/citations/import',90,1,'citations'),(19,4,'Export citations','admin/citations/export',80,1,'citations'),(20,5,'All users','admin/users',100,1,'users'),(21,5,'Add user','admin/users/add',99,1,'users'),(22,5,'-','-',65,1,'users'),(27,6,'All pages','admin/menu',0,1,'menu'),(28,7,'All reports','admin/reports',0,1,'reports'),(29,8,'Settings','admin/configurations',0,1,'configurations'),(30,8,'Countries','admin/countries',0,1,'vocabularies'),(31,8,'Regions','admin/regions',0,1,'vocabularies'),(32,8,'-','-',0,1,'vocabularies'),(33,8,'Vocabularies','admin/vocabularies',-9,1,'vocabularies'),(34,2,'Manage studies','admin/catalog',100,1,'catalog'),(35,5,'Impersonate user','admin/users/impersonate',50,1,'users');
set IDENTITY_INSERT site_menu OFF;

insert into site_menu(pid,title,url,weight,depth,module) 
	values (2,'-', '-',50,1,'catalog');
	
insert into site_menu(pid,title,url,weight,depth,module) 
	values (2,'Bulk access collections', 'admin/da_collections',40,1,'catalog');



--
-- Table structure for table vocabularies
--

CREATE TABLE vocabularies (
  vid int NOT NULL IDENTITY(1,1),
  title varchar(255) NOT NULL,
  PRIMARY KEY (vid)
) ;

CREATE UNIQUE NONCLUSTERED INDEX IX_vocabularies on [dbo].[vocabularies](
	[title] ASC
);


--
-- Table structure for table variables
--



CREATE TABLE variables (
  uid int NOT NULL IDENTITY(1,1),
  sid int NOT NULL,
  fid varchar(45) DEFAULT '',
  vid varchar(45) DEFAULT '',
  name varchar(100) DEFAULT '',
  labl varchar(255) DEFAULT '',
  qstn text,
  catgry text,
  metadata text,
  keywords text,
  PRIMARY KEY (uid)
) ;

CREATE UNIQUE NONCLUSTERED INDEX IX_variables on [dbo].[variables](
	[vid] ASC,
	[sid] ASC
);

CREATE INDEX IX_var_sidfk on [dbo].[variables](
	[sid] ASC
);



--
-- Table structure for table variable_groups
--

CREATE TABLE variable_groups (
  id int NOT NULL IdENTITY(1,1),
  sid int DEFAULT NULL,
  vgid varchar(45) DEFAULT NULL,
  variables varchar(5000) DEFAULT NULL,
  variable_groups varchar(500) DEFAULT NULL,
  group_type varchar(45) DEFAULT NULL,
  label varchar(255) DEFAULT NULL,
  universe varchar(255) DEFAULT NULL,
  notes varchar(500) DEFAULT NULL,
  txt varchar(500) DEFAULT NULL,
  definition varchar(500) DEFAULT NULL,
  PRIMARY KEY (id)
);



--
-- Table structure for table users_groups
--

CREATE TABLE users_groups (
  id int NOT NULL IDENTITY(1,1),
  user_id int DEFAULT NULL,
  group_id int DEFAULT NULL,
  PRIMARY KEY (id),
  --UNIQUE KEY user_group_UNQ (user_id,group_id)
);

CREATE UNIQUE NONCLUSTERED INDEX IX_users_groups on [dbo].[users_groups](
	[user_id] ASC,
	[group_id] ASC
);



--
-- Table structure for table survey_relationships
--

CREATE TABLE survey_relationships (
  id int NOT NULL IDENTITY(1,1),
  sid_1 int DEFAULT NULL,
  sid_2 int DEFAULT NULL,
  relationship_id int DEFAULT NULL,
  pair_id varchar(45) DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE INDEX IX_s_relationships on [dbo].[survey_relationships](
	[pair_id] ASC
);



--
-- Table structure for table survey_tags
--

CREATE TABLE survey_tags (
  id int NOT NULL IDENTITY(1,1),
  sid int NOT NULL,
  tag varchar(100) NOT NULL,
  PRIMARY KEY (id)
) ;

CREATE UNIQUE NONCLUSTERED INDEX IX_survey_tags on [dbo].[survey_tags](
	[sid] ASC,
	[tag] ASC
);


--
-- Table structure for table meta
--

CREATE TABLE meta (
  id int NOT NULL IDENTITY(1,1),
  user_id int DEFAULT NULL,
  first_name varchar(50) DEFAULT NULL,
  last_name varchar(50) DEFAULT NULL,
  company varchar(100) DEFAULT NULL,
  phone varchar(20) DEFAULT NULL,
  country varchar(100) DEFAULT NULL,
  PRIMARY KEY (id)
);


--
-- Table structure for table login_attempts
--

CREATE TABLE login_attempts (
  id int  NOT NULL IDENTITY(1,1),
  ip_address varchar(30) NOT NULL,
  login varchar(100) NOT NULL,
  time int  DEFAULT NULL,
  PRIMARY KEY (id)
) ;


--
-- Table structure for table repository_sections
--

CREATE TABLE repository_sections (
  id int  NOT NULL IDENTITY(1,1),
  title varchar(100) NOT NULL,
  weight int NOT NULL,
  PRIMARY KEY (id)
);

set IDENTITY_INSERT repository_sections ON;
INSERT INTO repository_sections (id,title,[weight])
VALUES (2,'Regional Collections',5),(3,'Specialized Collections',10);
set IDENTITY_INSERT repository_sections OFF;




--
-- Table structure for table survey_topics
--

CREATE TABLE survey_topics (
  sid int NOT NULL,
  tid int NOT NULL,
  uid int NOT NULL IDENTITY(1,1),
  PRIMARY KEY (uid)
) ;

CREATE UNIQUE NONCLUSTERED INDEX IX_survey_topics on [dbo].[survey_topics](
	[tid] ASC,
	[sid] ASC
);



--
-- Table structure for table survey_citations
--

CREATE TABLE survey_citations (
  id int NOT NULL IDENTITY(1,1),
  sid int DEFAULT NULL,
  citationid int DEFAULT NULL,  
  PRIMARY KEY (id)
) ;

CREATE UNIQUE NONCLUSTERED INDEX IX_survey_cit on [dbo].[survey_citations](
	[sid] ASC,
	[citationid] ASC
);



--
-- Table structure for table dcformats
--

CREATE TABLE dcformats (
  id int NOT NULL IDENTITY(1,1),
  title varchar(255) NOT NULL,
  PRIMARY KEY (id)
);


--
-- Dumping data for table dcformats
--

set IDENTITY_INSERT dcformats ON;
INSERT INTO dcformats (id,title)
VALUES (1,'Compressed, Generic [application/x-compressed]'),(2,'Compressed, ZIP [application/zip]'),(3,'Data, CSPro [application/x-cspro]'),(4,'Data, dBase [application/dbase]'),(5,'Data, Microsoft Access [application/msaccess]'),(6,'Data, SAS [application/x-sas]'),(7,'Data, SPSS [application/x-spss]'),(8,'Data, Stata [application/x-stata]'),(9,'Document, Generic [text]'),(10,'Document, HTML [text/html]'),(11,'Document, Microsoft Excel [application/msexcel]'),(12,'Document, Microsoft PowerPoint [application/mspowerpoint'),(13,'Document, Microsoft Word [application/msword]'),(14,'Document, PDF [application/pdf]'),(15,'Document, Postscript [application/postscript]'),(16,'Document, Plain [text/plain]'),(17,'Document, WordPerfect [text/wordperfect]'),(18,'Image, GIF [image/gif]'),(19,'Image, JPEG [image/jpeg]'),(20,'Image, PNG [image/png]'),(21,'Image, TIFF [image/tiff]');
set IDENTITY_INSERT dcformats OFF;


--
-- Table structure for table group_repo_access
--

CREATE TABLE group_repo_access (
  id int NOT NULL IDENTITY(1,1),
  group_id int DEFAULT NULL,
  repo_id int DEFAULT NULL,
  PRIMARY KEY (id)
) ;

CREATE UNIQUE NONCLUSTERED INDEX IX_grp_repo_access on [dbo].[group_repo_access](
	[group_id] ASC,
	[repo_id] ASC
);




--
-- Table structure for table surveys
--

CREATE TABLE surveys (
  id int NOT NULL IDENTITY(1,1),
  idno varchar(200) NOT NULL,
  doi varchar(200) DEFAULT NULL,
  type varchar(15) DEFAULT NULL,
  repositoryid varchar(128) NOT NULL,
  title varchar(255) DEFAULT '',
  subtitle varchar(255) DEFAULT '',
  abbreviation varchar(45) DEFAULT NULL,
  authoring_entity varchar(max) DEFAULT NULL,
  nation varchar(150) DEFAULT '',
  year_start int DEFAULT '0',
  year_end int DEFAULT '0',
  metafile varchar(255) DEFAULT NULL,
  dirpath varchar(255) DEFAULT NULL,
  varcount int DEFAULT '0',
  link_technical varchar(255) DEFAULT NULL,
  link_study varchar(255) DEFAULT NULL,
  link_report varchar(255) DEFAULT NULL,
  link_indicator varchar(255) DEFAULT NULL,
  link_questionnaire varchar(255) DEFAULT NULL,
  formid int DEFAULT NULL,
  data_class_id int DEFAULT NULL,
  link_da varchar(255) DEFAULT NULL,
  published tinyint DEFAULT NULL,  
  total_views int DEFAULT '0',
  total_downloads int DEFAULT '0',
  stats_last_updated int DEFAULT NULL,
  changed int DEFAULT NULL,
  created int DEFAULT NULL,
  created_by int DEFAULT NULL,
  changed_by int DEFAULT NULL,
  thumbnail varchar(300) DEFAULT NULL,
  metadata text,
  var_keywords text,
  keywords text,  
  PRIMARY KEY (id)
);

CREATE UNIQUE NONCLUSTERED INDEX IX_surveys on [dbo].[surveys](
	[id] ASC,
	[repositoryid] ASC
);


--
-- Table structure for table dctypes
--

CREATE TABLE dctypes (
  id int NOT NULL IDENTITY(1,1),
  title varchar(255) NOT NULL,
  PRIMARY KEY (id)
);


--
-- Dumping data for table dctypes
--
set IDENTITY_INSERT dctypes ON;
INSERT INTO dctypes (id,title)
VALUES (1,'Document, Administrative [doc/adm]'),(2,'Document, Analytical [doc/anl]'),(3,'Document, Other [doc/oth]'),(4,'Document, Questionnaire [doc/qst]'),(5,'Document, Reference [doc/ref]'),(6,'Document, Report [doc/rep]'),(7,'Document, Technical [doc/tec]'),(8,'Audio [aud]'),(9,'Database [dat]'),(10,'Map [map]'),(11,'Microdata File [dat/micro]'),(12,'Photo [pic]'),(13,'Program [prg]'),(14,'Table [tbl]'),(15,'Video [vid]'),(16,'Web Site [web]');
set IDENTITY_INSERT dctypes OFF;

-- additional types
INSERT INTO dctypes (title) VALUES ('Data, Geospatial [dat/geo]');
INSERT INTO dctypes (title) VALUES ('Data, Table [dat/table]');
INSERT INTO dctypes (title) VALUES ('Data, Document [dat/doc]');

--
-- Table structure for table da_collections
--

CREATE TABLE da_collections (
  id int NOT NULL IDENTITY(1,1),
  title varchar(255) DEFAULT NULL,
  description varchar(1000) DEFAULT NULL,
  PRIMARY KEY (id)
);


--
-- Table structure for table cache
--

CREATE TABLE cache (
  id bigint NOT NULL IDENTITY(1,1),
  uid varchar(100) NOT NULL,
  data text,
  created int DEFAULT NULL,
  expiry int DEFAULT NULL,
  PRIMARY KEY (id)
);


CREATE UNIQUE NONCLUSTERED INDEX IX_cache on [dbo].[cache](
	[uid] ASC
);


--
-- Table structure for table forms
--
CREATE TABLE forms (
  formid int NOT NULL IDENTITY(1,1),
  fname varchar(255) DEFAULT '',
  model varchar(255) DEFAULT '',
  path varchar(255) DEFAULT '',
  iscustom char(2) DEFAULT '0',
  PRIMARY KEY (formid)
);


--
-- Dumping data for table forms
--
set IDENTITY_INSERT forms ON;
INSERT INTO forms (formid,fname,model,path,iscustom) VALUES 
(2,'Public use files','public','orderform.php','1'),
(1,'Direct access','direct','direct.php','1'),
(3,'Licensed data files','licensed','licensed.php','1'),
(4,'Data accessible only in data enclave','data_enclave','Application for Access to a Data Enclave.pdf','0'),
(5,'Data available from external repository','remote','remote','1'),
(6,'Data not available','data_na','data_na','1'),
(7,'Open access','open','open','1');
set IDENTITY_INSERT forms OFF;


--
-- Table structure for table lic_requests
--

CREATE TABLE lic_requests (
  id int NOT NULL IDENTITY(1,1),
  userid int NOT NULL,
  request_title varchar(300),
  org_rec varchar(200) DEFAULT NULL,
  org_type varchar(45) DEFAULT NULL,
  address varchar(255) DEFAULT NULL,
  tel varchar(150) DEFAULT NULL,
  fax varchar(100) DEFAULT NULL,
  datause text,
  outputs text,
  compdate varchar(45) DEFAULT NULL,
  datamatching int DEFAULT NULL,
  mergedatasets text,
  team text,
  dataset_access varchar(20) DEFAULT 'whole',
  created int DEFAULT NULL,
  status varchar(45) DEFAULT NULL,
  comments text,
  locked tinyint DEFAULT NULL,
  orgtype_other varchar(145) DEFAULT NULL,
  updated int DEFAULT NULL,
  updatedby varchar(45) DEFAULT NULL,
  ip_limit varchar(255) DEFAULT NULL,
  expiry_date int DEFAULT NULL,
  additional_info text,
  PRIMARY KEY (id)
);



--
-- Table structure for table citations
--

CREATE TABLE citations (
  id int NOT NULL IDENTITY(1,1),
  uuid varchar(50) NOT NULL,
  title varchar(255) NOT NULL,
  subtitle varchar(255) DEFAULT NULL,
  alt_title varchar(255) DEFAULT NULL,
  authors varchar(600),
  editors varchar(600),
  translators varchar(600),
  changed int DEFAULT NULL,
  created int DEFAULT NULL,
  published tinyint DEFAULT '1',
  volume varchar(45) DEFAULT NULL,
  issue varchar(45) DEFAULT NULL,
  idnumber varchar(45) DEFAULT NULL,
  edition varchar(45) DEFAULT NULL,
  place_publication varchar(255) DEFAULT NULL,
  place_state varchar(255) DEFAULT NULL,
  publisher varchar(255) DEFAULT NULL,
  publication_medium tinyint DEFAULT '0',
  url varchar(255) DEFAULT NULL,
  page_from varchar(25) DEFAULT NULL,
  page_to varchar(25) DEFAULT NULL,
  data_accessed varchar(45) DEFAULT NULL,
  organization varchar(255) DEFAULT NULL,
  ctype varchar(45) NOT NULL,
  pub_day varchar(15) DEFAULT NULL,
  pub_month varchar(45) DEFAULT NULL,
  pub_year int DEFAULT NULL,
  abstract varchar(max),
  keywords varchar(max),
  notes varchar(max),
  doi varchar(255) DEFAULT NULL,
  flag varchar(45) DEFAULT NULL,
  owner varchar(255) DEFAULT NULL,
  country varchar(100) DEFAULT NULL,
  url_status varchar(50) DEFAULT NULL,
  created_by int DEFAULT NULL,
  changed_by int DEFAULT NULL,
  attachment varchar(300) DEFAULT NULL,
  lang varchar(50) DEFAULT NULL,
  PRIMARY KEY (id)
);


--
-- Table structure for table permission_urls
--

CREATE TABLE permission_urls (
  id int NOT NULL IDENTITY(1,1),
  url varchar(255) DEFAULT NULL,
  permission_id int NOT NULL,
  PRIMARY KEY (id)
);

CREATE UNIQUE NONCLUSTERED INDEX IX_perms_url on [dbo].[permission_urls](
	[url] ASC
);



--
-- Dumping data for table permission_urls
--
set IDENTITY_INSERT permission_urls ON;
INSERT INTO permission_urls (id,url,permission_id)
VALUES (1,'admin/catalog/upload',1),(4,'admin/menu',4),(5,'admin/menu/add',5),(6,'admin/menu/edit/*',6),(7,'admin/menu/add_link',7),(8,'admin/menu/menu_sort',8),(9,'admin/vocabularies',9),(10,'admin/terms/*',10),(12,'admin/users/*',12),(14,'ddibrowser',14),(16,'page/*',16),(18,'citations',18),(22,'backup*',22),(23,'access_licensed*',23),(25,'switch_language*',25),(27,'translate/*',27),(34,'admin/catalog/do_upload',1),(48,'admin/datadeposit*',40),(51,'admin/catalog/delete',42),(52,'admin/catalog/export-ddi',43),(53,'admin/catalog/import-rdf',44),(54,'admin/repositories/*',45),(55,'admin/repositories',45),(88,'admin/catalog/replace_ddi/*',46),(100,'admin/catalog/edit/*',49),(101,'admin/catalog/update/*',49),(102,'admin/catalog/update',49),(103,'admin/managefiles/*',49),(104,'admin/resources/*',49),(112,'admin/catalog',2),(113,'admin/catalog/survey/*',2),(114,'admin/catalog/search',2),(116,'access_public/*',30),(119,'admin/catalog/copy_ddi',62),(124,'admin/repositories/select',61),(125,'admin/repositories/active/*',61),(126,'admin/catalog/publish',41),(127,'admin/catalog/publish/*',41),(131,'admin/catalog/copy_study',63),(132,'admin/catalog/do_copy_study/*',63),(133,'admin/citations',64),(134,'admin/citations/edit',65),(135,'admin/citations/edit/*',65),(136,'admin/citations/delete/*',66),(137,'admin/citations/import',67),(138,'admin/citations/export',68),(141,'admin',3),(142,'admin/users/exit_impersonate',3),(143,'admin/licensed_requests',69),(145,'admin/licensed_requests/*',70),(147,'admin/users',11),(148,'admin/reports/*',71),(149,'admin/reports',71);
set IDENTITY_INSERT permission_urls OFF;

--
-- Table structure for table survey_aliases
--

CREATE TABLE survey_aliases (
  id int  NOT NULL IDENTITY(1,1),
  sid int  NOT NULL,
  alternate_id varchar(255) NOT NULL,
  PRIMARY KEY (id)
);


CREATE UNIQUE NONCLUSTERED INDEX IX_survey_alias on [dbo].[survey_aliases](
	[alternate_id] ASC
);



--
-- Table structure for table resources
--

CREATE TABLE resources (
  resource_id int NOT NULL IDENTITY(1,1),
  survey_id int NOT NULL,
  dctype varchar(255) DEFAULT NULL,
  title varchar(255) NOT NULL,
  subtitle varchar(255) DEFAULT NULL,
  author varchar(255) DEFAULT NULL,
  dcdate varchar(45) DEFAULT NULL,
  country varchar(45) DEFAULT NULL,
  language varchar(255) DEFAULT NULL,
  id_number varchar(255) DEFAULT NULL,
  contributor varchar(255) DEFAULT NULL,
  publisher varchar(255) DEFAULT NULL,
  rights varchar(255) DEFAULT NULL,
  description text,
  abstract text,
  toc text,
  subjects varchar(45) DEFAULT NULL,
  filename varchar(255) DEFAULT NULL,
  dcformat varchar(255) DEFAULT NULL,
  changed int DEFAULT NULL,
  PRIMARY KEY (resource_id)
);


--
-- Table structure for table lic_files_log
--

CREATE TABLE lic_files_log (
  id int NOT NULL IDENTITY(1,1),
  requestid int NOT NULL,
  fileid int NOT NULL,
  ip varchar(20) NOT NULL,
  created int NOT NULL,
  username varchar(255) DEFAULT NULL,
  PRIMARY KEY (id)
);


--
-- Table structure for table terms
--

CREATE TABLE terms (
  tid int NOT NULL IDENTITY(1,1),
  vid int NOT NULL,
  pid int DEFAULT NULL,
  title varchar(255) NOT NULL,
  PRIMARY KEY (tid)
);


--
-- Table structure for table users
--

CREATE TABLE users (
  id int NOT NULL IDENTITY(1,1),
  ip_address char(16) NOT NULL,
  username varchar(100) NOT NULL,
  password varchar(1000) NOT NULL,
  salt varchar(40) DEFAULT NULL,
  email varchar(100) NOT NULL,
  activation_code varchar(40) DEFAULT NULL,
  forgotten_password_code varchar(40) DEFAULT NULL,
  remember_code varchar(40) DEFAULT NULL,
  created_on int NOT NULL,
  last_login int NOT NULL,
  active tinyint DEFAULT NULL,
  authtype varchar(40) DEFAULT NULL,
  otp_code varchar(45) DEFAULT NULL,
  otp_expiry int DEFAULT NULL,
  PRIMARY KEY (id)
);


--
-- Table structure for table survey_countries
--

CREATE TABLE survey_countries (
  id int NOT NULL IDENTITY(1,1),
  sid int DEFAULT NULL,
  cid int DEFAULT NULL,
  country_name varchar(100) DEFAULT NULL,
  PRIMARY KEY (id)
) ;

CREATE UNIQUE NONCLUSTERED INDEX IX_surv_countries on [dbo].[survey_countries](
	[sid] ASC,
	[country_name] ASC 
);


--
-- Table structure for table country_aliases
--

CREATE TABLE country_aliases (
  id int NOT NULL IDENTITY(1,1),
  countryid int NOT NULL,
  alias varchar(100) NOT NULL,
  PRIMARY KEY (id)
--  UNIQUE KEY ix_alias_uniq (countryid,alias)
) ;

CREATE UNIQUE NONCLUSTERED INDEX IX_country_alias on [dbo].[country_aliases](
	[countryid] ASC,
	[alias] ASC 
);



--
-- Table structure for table group_permissions
--

CREATE TABLE group_permissions (
  id int NOT NULL IDENTITY(1,1),
  group_id int NOT NULL,
  permission_id int NOT NULL,
  PRIMARY KEY (id)
--  UNIQUE KEY grp_perms_UNIQUE (group_id,permission_id)
);

CREATE UNIQUE NONCLUSTERED INDEX IX_grp_perms on [dbo].[group_permissions](
	[group_id] ASC,
	[permission_id] ASC 
);



--
-- Dumping data for table group_permissions
--

set IDENTITY_INSERT group_permissions ON;
INSERT INTO group_permissions (id,group_id,permission_id)
VALUES (5,1,2),(6,1,14),(292,3,1),(289,3,2),(301,3,3),(299,3,14),(293,3,41),(295,3,42),(296,3,43),(297,3,44),(291,3,46),(294,3,49),(300,3,61),(290,3,62),(298,3,63),(334,4,2),(339,4,3),(335,4,16),(338,4,61),(336,4,69),(337,4,70),(313,5,3),(312,5,71),(287,9,2),(288,9,63),(227,10,2),(229,10,3),(228,10,45);
set IDENTITY_INSERT group_permissions OFF;

--
-- Table structure for table survey_repos
--

CREATE TABLE survey_repos (
  id int  NOT NULL IDENTITY(1,1),
  sid int  NOT NULL,
  repositoryid varchar(255) NOT NULL,
  isadmin tinyint  NOT NULL,
  PRIMARY KEY (id)
);


--
-- Table structure for table repo_perms_urls
--

CREATE TABLE repo_perms_urls (
  id int NOT NULL IDENTITY(1,1),
  repo_pg_id int DEFAULT NULL,
  url varchar(100) DEFAULT NULL,
  PRIMARY KEY (id)
);


--
-- Dumping data for table repo_perms_urls
--

set IDENTITY_INSERT repo_perms_urls ON;
INSERT INTO repo_perms_urls(id,repo_pg_id,url)
VALUES (5,2,'admin/catalog/copy_ddi'),(6,2,'admin/catalog/copy_study'),(7,2,'admin/catalog/delete'),(8,2,'admin/catalog/do_copy_study/*'),(9,2,'admin/catalog/do_upload'),(10,2,'admin/catalog/edit/*'),(11,2,'admin/catalog/export-ddi'),(12,2,'admin/catalog/import-rdf'),(15,2,'admin/catalog/repladce_ddi/*'),(16,2,'admin/catalog/search'),(17,2,'admin/catalog/survey/*'),(18,2,'admin/catalog/update'),(19,2,'admin/catalog/update/*'),(20,2,'admin/catalog/upload'),(28,3,'admin/licensed_requests'),(29,3,'admin/licensed_requests/*'),(30,2,'admin/managefiles/*'),(41,2,'admin/resources/*'),(64,1,'admin/catalog/*'),(67,2,'admin/pdf_generator/*'),
(68,1,'admin/pdf_generator/*'),
(69,1,'admin/catalog/add_study'),
(70,1,'admin/catalog/batch_import'),
(71,1,'admin/catalog/refresh/*');
set IDENTITY_INSERT repo_perms_urls OFF;




--
-- Table structure for table menus
--

CREATE TABLE menus (
  id int NOT NULL IDENTITY(1,1),
  url varchar(255) NOT NULL,
  title varchar(255) NOT NULL,
  body text,
  published tinyint DEFAULT NULL,
  target varchar(45) DEFAULT NULL,
  changed int DEFAULT NULL,
  linktype tinyint DEFAULT NULL,
  weight int DEFAULT NULL,
  pid int DEFAULT '0',
  PRIMARY KEY (id)
);


CREATE UNIQUE NONCLUSTERED INDEX IX_menus on [dbo].[menus](
	[url] ASC
);



--
-- Dumping data for table menus
--

set IDENTITY_INSERT menus ON;
INSERT INTO menus (id,url,title,body,published,target,changed,linktype,weight,pid) VALUES
(53,'catalog','Microdata  Catalog','',1,'0',1300807037,1,1,0),
(55,'citations','Citations',NULL,1,'0',1281460217,1,2,0),
(56,'home','Home',NULL,1,'0',1281460217,1,0,0);
set IDENTITY_INSERT menus OFF;



--
-- Table structure for table url_mappings
--

CREATE TABLE url_mappings (
  id int NOT NULL IDENTITY(1,1),
  source varchar(255) DEFAULT NULL,
  target varchar(255) DEFAULT NULL,
  PRIMARY KEY (id)
) ;


--
-- Table structure for table groups
--

CREATE TABLE groups (
  id tinyint NOT NULL IDENTITY(1,1),
  name varchar(100) NOT NULL,
  description varchar(255) NOT NULL,
  group_type varchar(40) DEFAULT NULL,
  access_type varchar(45) DEFAULT NULL,
  weight int DEFAULT '0',
  is_collection_group tinyint DEFAULT '0',
  PRIMARY KEY (id)
);


--
-- Dumping data for table groups
--

set IDENTITY_INSERT groups ON;
INSERT INTO groups (id,name,description,group_type,access_type,weight,is_collection_group)
VALUES 
(1,'admin','It is the site administrator and has access to all site content','admin','unlimited',0,0),
(2,'user','General user account with no access to site administration','user','none',-99,0),
(3,'Collection administrators','Users can manage and review studies for collections they are assigned to','admin','limited',0,1),
(5,'Report viewer','Can only generate/view reports','admin','limited',0,0),
(11,'Citation manager','has full control over the citations','admin','limited',0,0),
(12,'Global Licensed Reviewer','This account can review licensed data requests from all collections','admin','limited',0,0);
set IDENTITY_INSERT groups OFF;



--
-- Table structure for table survey_relationship_types
--

CREATE TABLE survey_relationship_types (
  id int NOT NULL IDENTITY(1,1),
  rel_group_id int DEFAULT NULL,
  rel_name varchar(45) DEFAULT NULL,
  rel_dir tinyint DEFAULT NULL,
  rel_cordinality varchar(10) DEFAULT NULL,
  PRIMARY KEY (id)
);


--
-- Dumping data for table survey_relationship_types
--

set IDENTITY_INSERT survey_relationship_types ON;
INSERT INTO survey_relationship_types (id,rel_group_id,rel_name, rel_dir, rel_cordinality)
VALUES (0,0,'isRelatedTo',0,'1:1'),(1,1,'isHarmonized',0,'N:1'),(2,1,'isMasterOf',1,'1:N'),(3,3,'isParentOf ',0,'1:N'),(4,3,'isChildOf',1,'N:1'),(5,5,'isAnnoynimizedVersionOf ',0,'N:1'),(6,5,'isMasterOf',1,NULL),(7,7,'isSubsetOf ',0,NULL),(8,7,'isMasterOf',1,NULL),(9,9,'containsStandardizedVersion ',0,NULL),(10,9,'isOriginalVersion',1,NULL),(11,11,'isWaveOf',2,'1:1'),(13,13,'isRevisedVersionOf',0,NULL),(14,13,'isOlderVersionOf',1,NULL);
set IDENTITY_INSERT survey_relationship_types OFF;


--
-- Table structure for table lic_requests_history
--

CREATE TABLE lic_requests_history (
  id int NOT NULL IDENTITY(1,1),
  lic_req_id int DEFAULT NULL,
  user_id varchar(100) DEFAULT NULL,
  logtype varchar(45) DEFAULT NULL,
  request_status varchar(45) DEFAULT NULL,
  description text,
  created int DEFAULT NULL,
  PRIMARY KEY (id)
) ;


--
-- Table structure for table da_collection_surveys
--

CREATE TABLE da_collection_surveys (
  id int NOT NULL IDENTITY(1,1),
  cid int DEFAULT NULL,
  sid int DEFAULT NULL,
  PRIMARY KEY (id)
--  UNIQUE KEY unq_coll_sid (cid,sid)
) ;

CREATE UNIQUE NONCLUSTERED INDEX IX_da_coll_surveys on [dbo].[da_collection_surveys](
	[cid] ASC,
	[sid] ASC
);


--
-- Table structure for table tags
--

CREATE TABLE tags (
  id int NOT NULL IDENTITY(1,1),
  tag varchar(100) NOT NULL,
  PRIMARY KEY (id)
--  UNIQUE KEY tag_UNIQUE (tag)
) ;

CREATE UNIQUE NONCLUSTERED INDEX IX_tags on [dbo].[tags](
	[tag] ASC
);



--
-- Table structure for table permissions
--

CREATE TABLE permissions (
  id int NOT NULL IDENTITY(1,1),
  label varchar(45) DEFAULT NULL,
  description varchar(255) DEFAULT NULL,
  section varchar(45) DEFAULT NULL,
  weight int DEFAULT NULL,
  PRIMARY KEY (id)
);


--
-- Dumping data for table permissions
--
set IDENTITY_INSERT permissions ON;
INSERT INTO permissions (id,label,description,section,weight)
VALUES (1,'Upload DDI file','this is a test description','catalog',3),(2,'View catalog','this is a test description','catalog',0),(3,'Access site administration','this is a test description','site_admin',0),(4,'Access Menus','this is a test description','menu_admin',0),(5,'Add menu page','this is a test description','menu_admin',0),(6,'Edit menu','this is a test description','menu_admin',0),(7,'Add menu link','this is a test description','menu_admin',0),(8,'Sort menu items','this is a test description','menu_admin',0),(9,'Access vocabularies','this is a test description','vocab',0),(10,'Access vocabulary terms','this is a test description','vocab',0),(11,'View user accounts','View list of all user accounts','user_admin',0),(12,'Edit user information','this is a test description','user_admin',0),(14,'Access DDI Browser','this is a test description','ddibrowser',0),(16,'Access site pages','this is a test description','general_site',0),(18,'View citations','this is a test description','general_site',0),(22,'Site backup','this is a test description','site_admin',0),(23,'View licensed request form','this is a test description','general_site',0),(25,'Switch site language','this is a test description','general_site',0),(27,'Translate site','this is a test description','site_admin',0),(30,'Public use files','this is a test description','general_site',0),(40,'Data Deposit','Data Deposit','site_admin',0),(41,'Publish/Unpublish study','Allows publishing study','catalog',3),(42,'Delete Study','delete study','catalog',4),(43,'Export DDI','Export','catalog',5),(44,'Import RDF','Import RDF for study resources','catalog',5),(45,'Manage Repositories','Manage repositories','repositories',9),(46,'Replace DDI','Replace a DDI file','catalog',3),(49,'Edit survey','Edit survey','catalog',4),(61,'Select collection','','repositories',1),(62,'Copy DDI','copy DDI','catalog',0),(63,'Copy studies from other collections','','catalog',6),(64,'View citations','','citation',1),(65,'Edit citation','','citation',2),(66,'Delete citation','Delete a citation','citation',3),(67,'Import citations','','citation',4),(68,'Export citations','Export citations to various formats','citation',5),(69,'View licensed requests','View list of licensed data requests','Licensed requests',0),(70,'Edit request','Edit a licensed data request','Licensed requests',1),(71,'Reports','View and generate admin reports','reports',0);
set IDENTITY_INSERT permissions OFF;


--
-- Table structure for table survey_years
--

CREATE TABLE survey_years (
  id int NOT NULL IDENTITY(1,1),
  sid int DEFAULT NULL,
  data_coll_year int DEFAULT NULL,
  PRIMARY KEY (id)
) ;

CREATE UNIQUE NONCLUSTERED INDEX IX_sur_years on [dbo].[survey_years](
	[sid] ASC,
	[data_coll_year] ASC
);

CREATE INDEX IX_sur_yrs_sid on [dbo].[survey_years](
	[sid] ASC
);



--
-- Table structure for table region_countries
--

CREATE TABLE region_countries (
  id int NOT NULL IDENTITY(1,1),
  region_id int DEFAULT NULL,
  country_id int DEFAULT NULL,
  PRIMARY KEY (id)
) ;


--
-- Table structure for table survey_notes
--

CREATE TABLE survey_notes (
  id int  NOT NULL IDENTITY(1,1),
  sid int  DEFAULT NULL,
  note text NOT NULL,
  type varchar(50) NOT NULL,
  userid int  NOT NULL,
  created int DEFAULT NULL,
  changed int DEFAULT NULL,
  PRIMARY KEY (id)
) ;



--
-- Table structure for table citation_authors
--

CREATE TABLE citation_authors (
  id int NOT NULL IDENTITY(1,1),
  cid int DEFAULT NULL,
  fname varchar(255) DEFAULT NULL,
  lname varchar(255) DEFAULT NULL,
  initial varchar(255) DEFAULT NULL,
  author_type varchar(45) DEFAULT NULL,
  PRIMARY KEY (id)
) ;



--
-- Table structure for table countries
--

CREATE TABLE countries (
  countryid int NOT NULL IDENTITY(1,1),
  name varchar(65) NOT NULL,
  iso varchar(3) NOT NULL,
  PRIMARY KEY (countryid)
);

CREATE UNIQUE NONCLUSTERED INDEX IX_countries on [dbo].[countries](
	[iso] ASC
);


--
-- Dumping data for table countries
--

set IDENTITY_INSERT countries ON;
INSERT INTO countries (countryid,name,iso)
VALUES (1,'Afghanistan','AFG'),(2,'Albania','ALB'),(3,'Antartica','ATA'),(4,'Algeria','DZA'),(5,'American Samoa','ASM'),(6,'Andorra','AND'),(7,'Angola','AGO'),(8,'Antigua and Barbuda','ATG'),(9,'Azerbaijan','AZE'),(10,'Argentina','ARG'),(11,'Australia','AUS'),(12,'Austria','AUT'),(13,'Bahamas','BHS'),(14,'Bahrain','BHR'),(15,'Bangladesh','BGD'),(16,'Armenia','ARM'),(17,'Barbados','BRB'),(18,'Belgium','BEL'),(19,'Bermuda','BMU'),(20,'Bhutan','BTN'),(21,'Bolivia','BOL'),(22,'Bosnia-Herzegovina','BIH'),(23,'Botswana','BWA'),(24,'Bouvet Island','BVT'),(25,'Brazil','BRA'),(26,'Belize','BLZ'),(27,'British Indian Ocean Territory','IOT'),(28,'Solomon Islands','SLB'),(29,'Virgin Isld. (British)','VGB'),(30,'Brunei','BRN'),(31,'Bulgaria','BGR'),(32,'Myanmar','MMR'),(33,'Burundi','BDI'),(34,'Belarus','BLR'),(35,'Cambodia','KHM'),(36,'Cameroon','CMR'),(37,'Canada','CAN'),(38,'Cape Verde','CPV'),(39,'Cayman Islands','CYM'),(40,'Central African Republic','CAF'),(41,'Sri Lanka','LKA'),(42,'Chad','TCD'),(43,'Chile','CHL'),(44,'China','CHN'),(45,'Taiwan','TWN'),(46,'Christmas Island','CXR'),(47,'Cocos Isld.','CCK'),(48,'Colombia','COL'),(49,'Comoros','COM'),(50,'Mayotte','MYT'),(51,'Congo, Rep.','COG'),(52,'Congo, Dem. Rep.','COD'),(53,'Cook Island','COK'),(54,'Costa Rica','CRI'),(55,'Croatia','HRV'),(56,'Cuba','CUB'),(57,'Cyprus','CYP'),(58,'Czech Republic','CZE'),(59,'Benin','BEN'),(60,'Denmark','DNK'),(61,'Dominica','DMA'),(62,'Dominican Republic','DOM'),(63,'Ecuador','ECU'),(64,'El Salvador','SLV'),(65,'Equatorial Guinea','GNQ'),(66,'Ethiopia','ETH'),(67,'Eritrea','ERI'),(68,'Estonia','EST'),(69,'Faeroe Isld.','FRO'),(70,'Falkland Isld.','FLK'),(71,'S. Georgia & S. Sandwich Isld.','SGS'),(72,'Fiji','FJI'),(73,'Finland','FIN'),(74,'France, Metrop.','FXX'),(75,'France','FRA'),(76,'French Guiana','GUF'),(77,'French Polynesia','PYF'),(78,'French S.T.','ATF'),(79,'Djibouti','DJI'),(80,'Gabon','GAB'),(81,'Georgia','GEO'),(82,'Gambia','GMB'),(83,'West Bank and Gaza','PSE'),(84,'Germany','DEU'),(85,'Ghana','GHA'),(86,'Gibraltar','GIB'),(87,'Kiribati','KIR'),(88,'Greece','GRC'),(89,'Greenland','GRL'),(90,'Grenada','GRD'),(91,'Guadeloupe','GLP'),(92,'Guam','GUM'),(93,'Guatemala','GTM'),(94,'Guinea','GIN'),(95,'Guyana','GUY'),(96,'Haiti','HTI'),(97,'Heard / McDonald Isld','HMD'),(98,'Holy See','VAT'),(99,'Honduras','HND'),(100,'Hungary','HUN'),(101,'Iceland','ISL'),(102,'India','IND'),(103,'Indonesia','IDN'),(104,'Iran, Islamic Rep.','IRN'),(105,'Iraq','IRQ'),(106,'Ireland','IRL'),(107,'Israel','ISR'),(108,'Italy','ITA'),(109,'Cote d''Ivoire','CIV'),(110,'Jamaica','JAM'),(111,'Japan','JPN'),(112,'Kazakhstan','KAZ'),(113,'Jordan','JOR'),(114,'Kenya','KEN'),(115,'Korea, Dem. Rep.','PRK'),(116,'Korea, Rep.','KOR'),(117,'Kuwait','KWT'),(118,'Kyrgyz Republic','KGZ'),(119,'Lao PDR','LAO'),(120,'Lebanon','LBN'),(121,'Lesotho','LSO'),(122,'Latvia','LVA'),(123,'Liberia','LBR'),(124,'Libya','LBY'),(125,'Liechtenstein','LIE'),(126,'Lithuania','LTU'),(127,'Luxembourg','LUX'),(128,'Macao','MAC'),(129,'Madagascar','MDG'),(130,'Malawi','MWI'),(131,'Malaysia','MYS'),(132,'Maldives','MDV'),(133,'Mali','MLI'),(134,'Malta','MLT'),(135,'Martinique','MTQ'),(136,'Mauritania','MRT'),(137,'Mauritius','MUS'),(138,'Mexico','MEX'),(139,'Monaco','MCO'),(140,'Mongolia','MNG'),(141,'Moldova','MDA'),(142,'Montserrat','MSR'),(143,'Morocco','MAR'),(144,'Mozambique','MOZ'),(145,'Oman','OMN'),(146,'Namibia','NAM'),(147,'Nauru','NRU'),(148,'Nepal','NPL'),(149,'Netherlands','NLD'),(150,'Neth.Antilles','ANT'),(151,'Aruba','ABW'),(152,'New Caledonia','NCL'),(153,'Vanuatu','VUT'),(154,'New Zealand','NZL'),(155,'Nicaragua','NIC'),(156,'Niger','NER'),(157,'Nigeria','NGA'),(158,'Niue','NIU'),(159,'Norfolk Isld.','NFK'),(160,'Norway','NOR'),(161,'N. Mariana Isld.','MNP'),(162,'US minor outlying Islands','UMI'),(163,'Micronesia','FSM'),(164,'Marshall Isld.','MHL'),(165,'Palau','PLW'),(166,'Pakistan','PAK'),(167,'Panama','PAN'),(168,'Papua New Guinea','PNG'),(169,'Paraguay','PRY'),(170,'Peru','PER'),(171,'Philippines','PHL'),(172,'Pitcairn Island','PCN'),(173,'Poland','POL'),(174,'Portugal','PRT'),(175,'Guinea Bissau','GNB'),(176,'Timor-Leste','TLS'),(177,'Puerto Rico','PRI'),(178,'Qatar','QAT'),(179,'Romania','ROM'),(180,'Russian Federation','RUS'),(181,'Rwanda','RWA'),(182,'St. Helena','SHN'),(183,'St.Kitts and Nevis','KNA'),(184,'Anguilla','AIA'),(185,'St. Lucia','LCA'),(186,'St. Pierre and Miquelon','SPM'),(187,'St. Vincent and Grenadines','VCT'),(188,'San Marino','SMR'),(189,'São Tomé and Príncipe','STP'),(190,'Saudi Arabia','SAU'),(191,'Senegal','SEN'),(192,'Seychelles','SYC'),(193,'Sierra Leone','SLE'),(194,'Singapore','SGP'),(195,'Slovak Republic','SVK'),(196,'Viet Nam','VNM'),(197,'Slovenia','SVN'),(198,'Somalia','SOM'),(199,'South Africa','ZAF'),(200,'Zimbabwe','ZWE'),(201,'Spain','ESP'),(202,'West. Sahara','ESH'),(203,'Sudan','SDN'),(204,'Suriname','SUR'),(205,'Svalbard and Jan Mayen Islands','SJM'),(206,'Swaziland','SWZ'),(207,'Sweden','SWE'),(208,'Switzerland','CHE'),(209,'Syrian Arab Republic','SYR'),(210,'Tajikistan','TJK'),(211,'Thailand','THA'),(212,'Togo','TGO'),(213,'Tokelau','TKL'),(214,'Tonga','TON'),(215,'Trinidad and Tobago','TTO'),(216,'United Arab Emirates','ARE'),(217,'Tunisia','TUN'),(218,'Turkey','TUR'),(219,'Turkmenistan','TKM'),(220,'Turks and Caicos Islands','TCA'),(221,'Tuvalu','TUV'),(222,'Uganda','UGA'),(223,'Ukraine','UKR'),(224,'Macedonia, FYR','MKD'),(225,'Egypt, Arab Rep.','EGY'),(226,'United Kingdom','GBR'),(227,'Tanzania','TZA'),(228,'United States','USA'),(229,'Virgin Islands, U.S.','VIR'),(230,'Burkina Faso','BFA'),(231,'Uruguay','URY'),(232,'Uzbekistan','UZB'),(233,'Venezuela, RB','VEN'),(234,'Wallis and Futuna','WLF'),(235,'Samoa','WSM'),(236,'Yemen','YEM'),(237,'Serbia and Montenegro','SCG'),(238,'Zambia','ZMB'),(239,'Westbank and Gaza','WBG'),(240,'Jerusalem','JER');
set IDENTITY_INSERT countries OFF;

--
-- Table structure for table repo_perms_groups
--

CREATE TABLE repo_perms_groups (
  repo_pg_id int NOT NULL IDENTITY(1,1),
  title varchar(45) DEFAULT NULL,
  description varchar(255) DEFAULT NULL,
  weight int DEFAULT '0',
  PRIMARY KEY (repo_pg_id)
);


--
-- Dumping data for table repo_perms_groups
--

set IDENTITY_INSERT repo_perms_groups ON;
INSERT INTO repo_perms_groups(repo_pg_id,title,[description],[weight]) 
VALUES (1,'Manage studies (full access)','Full control over the studies including adding, updating, publishing, copying from other collections, etc.',0),(2,'Manage studies (limited access)','All access except can''t publish or unpublish studies',1),(3,'Manage licensed requests','Allows user to view and process licensed data requests for the collection',2),(4,'Reviewer','Allows user to review studies from the front-end regardless of study publish/unpublish status',3);
set IDENTITY_INSERT repo_perms_groups OFF;


--
-- Table structure for table user_repo_permissions
--

CREATE TABLE user_repo_permissions (
  id int NOT NULL IDENTITY(1,1),
  user_id int DEFAULT NULL,
  repo_id int DEFAULT NULL,
  repo_pg_id int DEFAULT NULL,
  PRIMARY KEY (id)
);



--
-- Table structure for table lic_file_downloads
--

CREATE TABLE lic_file_downloads (
  id int NOT NULL IDENTITY(1,1),
  fileid varchar(45) NOT NULL,
  downloads varchar(45) DEFAULT NULL,
  download_limit varchar(45) DEFAULT NULL,
  expiry int DEFAULT NULL,
  lastdownloaded int DEFAULT NULL,
  requestid int NOT NULL,
  PRIMARY KEY (id)
) ;



--
-- Table structure for table regions
--

CREATE TABLE regions (
  id int NOT NULL IDENTITY(1,1),
  pid int DEFAULT '0',
  title varchar(45) DEFAULT NULL,
  weight int DEFAULT NULL,
  PRIMARY KEY (id)
);


--
-- Dumping data for table regions
--

set IDENTITY_INSERT regions ON;
INSERT INTO regions(id,pid,title,[weight]) 
VALUES (1,0,'By Region',0),(2,1,'East Asia and Pacific',1),(3,1,'Europe and Central Asia',1),(4,1,'Latin America & the Caribbean',1),(5,1,'Middle East and North Africa',1),(6,1,'South Asia',1),(7,1,'Sub-Saharan Africa',1),(8,0,'By Income',0),(9,8,'Low-income economies',0),(10,8,'Lower-middle-income economies',1),(11,8,'Upper-middle-income economies',3),(12,8,'High-income economies',4),(13,8,'High-income OECD members',6);
set IDENTITY_INSERT regions OFF;


--
-- Table structure for table ci_sessions
--


CREATE TABLE ci_sessions (
  id varchar(128) NOT NULL,
  ip_address varchar(45) NOT NULL,
  timestamp int DEFAULT '0',
  data blob,
  PRIMARY KEY (id)
);

CREATE INDEX ci_sess_timestamp ON dbo.ci_sessions (timestamp);



--
-- Table structure for table public_requests
--

CREATE TABLE public_requests (
  id int NOT NULL IDENTITY(1,1),
  userid int NOT NULL,
  surveyid int DEFAULT NULL,
  abstract text NOT NULL,
  posted int NOT NULL,
  request_type varchar(45) DEFAULT 'study',
  collectionid varchar(45) DEFAULT NULL,
  PRIMARY KEY (id)
) ;



--
-- Table structure for table sitelogs
--

CREATE TABLE sitelogs (
  id int NOT NULL IDENTITY(1,1),
  sessionid varchar(255) NOT NULL DEFAULT '',
  logtime varchar(45) NOT NULL DEFAULT '0',
  ip varchar(45) NOT NULL,
  url varchar(255) NOT NULL DEFAULT '',
  logtype varchar(45) NOT NULL,
  surveyid int DEFAULT '0',
  section varchar(255) DEFAULT NULL,
  keyword varchar(max),
  username varchar(100) DEFAULT NULL,
  useragent varchar(300) DEFAULT NULL,
  PRIMARY KEY (id)
) ;



--
-- Table structure for table configurations
--

CREATE TABLE configurations (
  name varchar(200) NOT NULL,
  value varchar(5000) NOT NULL,
  label varchar(255) DEFAULT NULL,
  helptext varchar(255) DEFAULT NULL,
  item_group varchar(255) DEFAULT NULL,
  PRIMARY KEY (name)
) ;


--
-- Dumping data for table configurations
--

INSERT INTO configurations VALUES ('app_version','4.0.0-06.02.2013','Application version',NULL,NULL);
INSERT INTO configurations VALUES ('cache_default_expires','7200','Cache expiry (in mili seconds)',NULL,NULL);
INSERT INTO configurations VALUES ('cache_disabled','0','Enable/disable site caching',NULL,NULL);
INSERT INTO configurations VALUES ('cache_path','cache/','Site cache folder',NULL,NULL);
INSERT INTO configurations VALUES ('catalog_records_per_page','15','Catalog search page - records per page',NULL,NULL);
INSERT INTO configurations VALUES ('catalog_root','datafiles','Survey catalog folder',NULL,NULL);
INSERT INTO configurations VALUES ('collections_vocab','2','survey collections vocabulary',NULL,NULL);
INSERT INTO configurations VALUES ('collection_search','no',NULL,NULL,NULL);
INSERT INTO configurations VALUES ('collection_search_weight','5',NULL,NULL,NULL);
INSERT INTO configurations VALUES ('da_search','no',NULL,NULL,NULL);
INSERT INTO configurations VALUES ('da_search_weight','2',NULL,NULL,NULL);
INSERT INTO configurations VALUES ('db_version','4.0.0-06.02.2013','Database version',NULL,NULL);
INSERT INTO configurations VALUES ('ddi_import_folder','imports','Survey catalog import folder',NULL,NULL);
INSERT INTO configurations VALUES ('default_home_page','home','Default home page','Default home page',NULL);
INSERT INTO configurations VALUES ('html_folder','/pages',NULL,NULL,NULL);
INSERT INTO configurations VALUES ('lang','en-us','Site Language','Site Language code',NULL);
INSERT INTO configurations VALUES ('language','english',NULL,NULL,NULL);
INSERT INTO configurations VALUES ('login_timeout','40','Login timeout (minutes)',NULL,NULL);
INSERT INTO configurations VALUES ('mail_protocol','smtp','Select method for sending emails','Supported protocols: MAIL, SMTP, SENDMAIL',NULL);
INSERT INTO configurations VALUES ('min_password_length','5','Minimum password length',NULL,NULL);
INSERT INTO configurations VALUES ('news_feed_url','http://ihsn.org/nada/index.php?q=news/feed','','','');
INSERT INTO configurations VALUES ('regional_search','no','Enable regional search',NULL,NULL);
INSERT INTO configurations VALUES ('regional_search_weight','3',NULL,NULL,NULL);
INSERT INTO configurations VALUES ('repository_identifier','default','Repository Identifier',NULL,NULL);
INSERT INTO configurations VALUES ('site_password_protect','no','Password protect website',NULL,NULL);
INSERT INTO configurations VALUES ('smtp_host','','SMTP Host name',NULL,NULL);
INSERT INTO configurations VALUES ('smtp_pass','','SMTP password',NULL,NULL);
INSERT INTO configurations VALUES ('smtp_port','25','SMTP port',NULL,NULL);
INSERT INTO configurations VALUES ('smtp_user','','SMTP username',NULL,NULL);
INSERT INTO configurations VALUES ('theme','default','Site theme name',NULL,NULL);
INSERT INTO configurations VALUES ('topics_vocab','1','Vocabulary ID for Topics',NULL,NULL);
INSERT INTO configurations VALUES ('topic_search','no','Topic search',NULL,NULL);
INSERT INTO configurations VALUES ('topic_search_weight','6',NULL,NULL,NULL);
INSERT INTO configurations VALUES ('use_html_editor','yes','Use HTML editor for entering HTML for static pages',NULL,NULL);
INSERT INTO configurations VALUES ('website_footer','Powered by NADA 4.0 and DDI','Website footer text',NULL,NULL);
INSERT INTO configurations VALUES ('website_title','National Data Archive','Website title','Provide the title of the website','website');
INSERT INTO configurations VALUES ('website_url','http://localhost/nada','Website URL','URL of the website','website');
INSERT INTO configurations VALUES ('website_webmaster_email','nada@ihsn.org','Site webmaster email address','-','website');
INSERT INTO configurations VALUES ('website_webmaster_name','noreply','Webmaster name','-','website');
INSERT INTO configurations VALUES ('year_search','no',NULL,NULL,NULL);
INSERT INTO configurations VALUES ('year_search_weight','1',NULL,NULL,NULL);


---------------------------------------------------------
-- SURVEYS 
---------------------------------------------------------

-- create a unique index or use the PK
CREATE UNIQUE INDEX pk_idx_surveys ON dbo.surveys(id);


-- create a fulltext catalog if not created already
CREATE FULLTEXT CATALOG ft AS DEFAULT;


--drop existing fulltext index
DROP FULLTEXT INDEX ON surveys;


--add table columns to index
CREATE FULLTEXT INDEX ON surveys
( 
  keywords		Language 1033,
  var_keywords		Language 1033
 ) 
KEY INDEX pk_idx_surveys ; 



---------------------------------------------------------
-- VARIABLES
---------------------------------------------------------

-- create a unique index or use the PK
CREATE UNIQUE INDEX pk_idx_variables ON dbo.variables(uid);
go

--add table columns to index
CREATE FULLTEXT INDEX ON variables
( 
  catgry	Language 1033,
  labl		Language 1033,
  name		Language 1033,
  qstn		Language 1033
 ) 
KEY INDEX pk_idx_variables; 



---
--- Table structure for table featured_surveys
---

CREATE TABLE featured_surveys (
  id int NOT NULL IDENTITY(1,1),
  repoid int DEFAULT NULL,
  sid int DEFAULT NULL,
  weight int DEFAULT '0',
  PRIMARY KEY (id)
);


CREATE UNIQUE NONCLUSTERED INDEX IX_featured_surveys on [dbo].[featured_surveys](
	[repoid] ASC,
	[sid] ASC
);



--
-- Table structure for table survey_types
--

CREATE TABLE survey_types (
  id int NOT NULL identity(1,1),
  code varchar(50) NOT NULL,
  title varchar(250) DEFAULT NULL,
  weight int DEFAULT '0',
  PRIMARY KEY (id)
);

CREATE UNIQUE NONCLUSTERED INDEX IX_survey_types on [dbo].[survey_types](
	[code] ASC
);

set IDENTITY_INSERT survey_types ON;
INSERT INTO survey_types(id,code,title, weight) VALUES(1,'survey','Survey',100);
INSERT INTO survey_types(id,code,title, weight) VALUES(2,'geospatial','Geospatial',90);
INSERT INTO survey_types(id,code,title, weight) VALUES(3,'timeseries','Time series',80);
INSERT INTO survey_types(id,code,title, weight) VALUES(4,'document','Document',50);
INSERT INTO survey_types(id,code,title, weight) VALUES(5,'table','Table',70);
INSERT INTO survey_types(id,code,title, weight) VALUES(6,'image','Photo',40);
INSERT INTO survey_types(id,code,title, weight) VALUES(7,'script','Script',30);
INSERT INTO survey_types(id,code,title, weight) VALUES(8,'visualization','Visualization',60);
INSERT INTO survey_types(id,code,title, weight) VALUES(9,'video','Video',40);
set IDENTITY_INSERT survey_types OFF;


-- 
-- Table structure for table 'survey_lic_requests'
--

CREATE TABLE survey_lic_requests (
  id int NOT NULL IDENTITY(1,1),
  request_id int NOT NULL,
  sid int NOT NULL,
  PRIMARY KEY (id)
);


CREATE UNIQUE NONCLUSTERED INDEX IX_survey_lic_req on [dbo].[survey_lic_requests](
	[request_id] ASC,
	[sid] ASC
);




-- 
-- Table structure for table 'data_files'
--
CREATE TABLE data_files (
  id int NOT NULL identity(1,1),
  sid int NOT NULL,
  file_id varchar(100) DEFAULT NULL,
  file_name varchar(255) DEFAULT NULL,
  description text,
  case_count int DEFAULT NULL,
  var_count int DEFAULT NULL,
  producer varchar(255) DEFAULT NULL,
  data_checks varchar(255) DEFAULT NULL,
  missing_data varchar(255) DEFAULT NULL,
  version varchar(255) DEFAULT NULL,
  notes varchar(255) DEFAULT NULL,
  metadata varchar(max) DEFAULT NULL,
  PRIMARY KEY (id)  
);

CREATE UNIQUE NONCLUSTERED INDEX IX_data_files on [dbo].[data_files](
	[sid] ASC,
	[file_id] ASC
);


--
-- API KEYS table
--
CREATE TABLE api_keys (
  id int NOT NULL identity(1,1),
  api_key varchar(40) NOT NULL,
  level int NOT NULL,
  ignore_limits tinyint NOT NULL DEFAULT '0',
  ip_addresses text,
  date_created int NOT NULL,
  user_id int DEFAULT NULL,
  is_private_key int NOT NULL DEFAULT '0',
  PRIMARY KEY (id),
);

CREATE UNIQUE NONCLUSTERED INDEX IX_api_keys on [dbo].[api_keys](
	[api_key] ASC
);


--
-- API Logs table
--
CREATE TABLE api_logs (
  id int NOT NULL identity(1,1),
  uri varchar(255) NOT NULL,
  method varchar(6) NOT NULL,
  params text,
  user_id int default NULL,
  api_key varchar(40) NOT NULL,
  ip_address varchar(45) NOT NULL,
  time int NOT NULL,
  rtime float DEFAULT NULL,
  authorized varchar(1) NOT NULL,
  response_code smallint DEFAULT '0',
  PRIMARY KEY (id)
);



CREATE TABLE data_files_resources (
  id INT NOT NULL identity(1,1),
  sid INT NULL,
  fid VARCHAR(45) NULL,
  resource_id INT NULL,
  file_format VARCHAR(45) NULL,
  api_use TINYINT NULL,
  PRIMARY KEY (id)
  );

CREATE UNIQUE NONCLUSTERED INDEX IX_data_files_resources on [dbo].[data_files_resources](
	[sid] ASC,
	[resource_id] ASC
);



CREATE TABLE survey_locations (
  id int NOT NULL identity(1,1),
  sid int DEFAULT NULL,
  location text NOT NULL,
  PRIMARY KEY (id)  
);


CREATE TABLE filestore (
  id int NOT NULL identity(1,1),
  file_name varchar(255) DEFAULT NULL,
  file_path varchar(500) DEFAULT NULL,
  file_ext varchar(10) DEFAULT NULL,
  is_image tinyint DEFAULT NULL,
  changed int DEFAULT NULL,
  PRIMARY KEY (id)  
);

CREATE UNIQUE NONCLUSTERED INDEX IX_filestore on [dbo].[filestore](	
	[file_name] ASC
);



CREATE TABLE data_classifications (
  id int NOT NULL,
  code varchar(45) DEFAULT NULL,
  title varchar(100) DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE UNIQUE NONCLUSTERED INDEX IX_data_class on [dbo].[data_classifications](
	[code] ASC
);



INSERT INTO data_classifications (id,code,title) VALUES 
(1,'public','Public use'),
(2,'official','Official use'),
(3,'confidential','Confidential');


CREATE TABLE roles (
  id int NOT NULL identity(1,1),
  name varchar(100) NOT NULL,
  description varchar(255) NOT NULL,
  weight int DEFAULT '0',
  is_admin tinyint DEFAULT '0',
  is_locked tinyint DEFAULT '0',
  PRIMARY KEY (id)
);


set IDENTITY_INSERT roles ON;
insert into roles(id,name,description, weight, is_admin, is_locked) values 
(1,'admin','It is the site administrator and has access to all site content', 0,1,1),
(2,'user','General user account with no access to site administration', 0,1,1);
set IDENTITY_INSERT roles OFF;



CREATE TABLE role_permissions (
  id int NOT NULL identity(1,1),
  role_id varchar(45) NOT NULL,
  resource varchar(45) DEFAULT NULL,
  permissions varchar(500) DEFAULT NULL,
  PRIMARY KEY (id)
);


CREATE TABLE user_roles (
  id int NOT NULL identity(1,1),
  user_id int DEFAULT NULL,
  role_id int DEFAULT NULL,
  PRIMARY KEY (id)
);


--
-- migrate admins from previous version
--

insert into user_roles (user_id, role_id) 
	select user_id, group_id from users_groups;




CREATE TABLE data_access_whitelist (
  id int NOT NULL identity(1,1),
  user_id int DEFAULT NULL,
  repository_id int DEFAULT NULL,
  PRIMARY KEY (id)
);