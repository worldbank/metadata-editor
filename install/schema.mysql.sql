--
-- Table structure for table `meta`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `meta` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `company` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;


--
-- Table structure for table `login_attempts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(30) NOT NULL,
  `login` varchar(100) NOT NULL,
  `time` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;




--
-- Table structure for table `dcformats`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dcformats` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dcformats`
--

LOCK TABLES `dcformats` WRITE;
/*!40000 ALTER TABLE `dcformats` DISABLE KEYS */;
INSERT INTO `dcformats` VALUES (1,'Compressed, Generic [application/x-compressed]'),(2,'Compressed, ZIP [application/zip]'),(3,'Data, CSPro [application/x-cspro]'),(4,'Data, dBase [application/dbase]'),(5,'Data, Microsoft Access [application/msaccess]'),(6,'Data, SAS [application/x-sas]'),(7,'Data, SPSS [application/x-spss]'),(8,'Data, Stata [application/x-stata]'),(9,'Document, Generic [text]'),(10,'Document, HTML [text/html]'),(11,'Document, Microsoft Excel [application/msexcel]'),(12,'Document, Microsoft PowerPoint [application/mspowerpoint'),(13,'Document, Microsoft Word [application/msword]'),(14,'Document, PDF [application/pdf]'),(15,'Document, Postscript [application/postscript]'),(16,'Document, Plain [text/plain]'),(17,'Document, WordPerfect [text/wordperfect]'),(18,'Image, GIF [image/gif]'),(19,'Image, JPEG [image/jpeg]'),(20,'Image, PNG [image/png]'),(21,'Image, TIFF [image/tiff]');
/*!40000 ALTER TABLE `dcformats` ENABLE KEYS */;
UNLOCK TABLES;


--
-- Table structure for table `dctypes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dctypes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dctypes`
--

LOCK TABLES `dctypes` WRITE;
/*!40000 ALTER TABLE `dctypes` DISABLE KEYS */;
INSERT INTO `dctypes` VALUES 
(1,'Document, Administrative [doc/adm]'),
(2,'Document, Analytical [doc/anl]'),
(3,'Document, Other [doc/oth]'),
(4,'Document, Questionnaire [doc/qst]'),
(5,'Document, Reference [doc/ref]'),
(6,'Document, Report [doc/rep]'),
(7,'Document, Technical [doc/tec]'),
(8,'Audio [aud]'),
(9,'Database [dat]'),
(10,'Map [map]'),
(11,'Microdata File [dat/micro]'),
(12,'Photo [pic]'),
(13,'Program [prg]'),
(14,'Table [tbl]'),
(15,'Video [vid]'),
(16,'Web Site [web]');
/*!40000 ALTER TABLE `dctypes` ENABLE KEYS */;
UNLOCK TABLES;

-- additional types
INSERT INTO `dctypes` (`title`) VALUES ('Data, Geospatial [dat/geo]');
INSERT INTO `dctypes` (`title`) VALUES ('Data, Table [dat/table]');
INSERT INTO `dctypes` (`title`) VALUES ('Data, Document [dat/doc]');


--
-- Table structure for table `users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` mediumint(8) NOT NULL AUTO_INCREMENT,
  `ip_address` char(16) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(1000) NOT NULL,
  `salt` varchar(40) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `activation_code` varchar(40) DEFAULT NULL,
  `forgotten_password_code` varchar(40) DEFAULT NULL,
  `remember_code` varchar(40) DEFAULT NULL,
  `created_on` int NOT NULL,
  `last_login` int NOT NULL,
  `active` tinyint(3) DEFAULT NULL,
  `authtype` varchar(40) DEFAULT NULL,
  `otp_code` varchar(45) DEFAULT NULL,
  `otp_expiry` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;


--
-- Table structure for table `ci_sessions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ci_sessions` (
  `id` varchar(128) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `timestamp` int unsigned NOT NULL DEFAULT '0',
  `data` blob NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ci_sessions_timestamp` (`timestamp`)
) DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;




--
-- Table structure for table `sitelogs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sitelogs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sessionid` varchar(255) NOT NULL DEFAULT '',
  `logtime` varchar(45) NOT NULL DEFAULT '0',
  `ip` varchar(45) NOT NULL,
  `url` varchar(255) NOT NULL DEFAULT '',
  `logtype` varchar(45) NOT NULL,
  `surveyid` int DEFAULT '0',
  `section` varchar(255) DEFAULT NULL,
  `keyword` text,
  `username` varchar(100) DEFAULT NULL,
   `useragent` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;



--
-- Table structure for table `configurations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `configurations` (
  `name` varchar(200) NOT NULL,
  `value` varchar(5000) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `helptext` varchar(255) DEFAULT NULL,
  `item_group` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`name`)
) DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configurations`
--

LOCK TABLES `configurations` WRITE;
/*!40000 ALTER TABLE `configurations` DISABLE KEYS */;
INSERT INTO `configurations` VALUES ('app_version','1.0.0','Application version',NULL,NULL);
INSERT INTO `configurations` VALUES ('cache_default_expires','7200','Cache expiry (in mili seconds)',NULL,NULL);
INSERT INTO `configurations` VALUES ('cache_disabled','1','Enable/disable site caching',NULL,NULL);
INSERT INTO `configurations` VALUES ('cache_path','cache/','Site cache folder',NULL,NULL);
INSERT INTO `configurations` VALUES ('default_home_page','home','Default home page','Default home page',NULL);
INSERT INTO `configurations` VALUES ('html_folder','/pages',NULL,NULL,NULL);
INSERT INTO `configurations` VALUES ('lang','en-us','Site Language','Site Language code',NULL);
INSERT INTO `configurations` VALUES ('language','english',NULL,NULL,NULL);
INSERT INTO `configurations` VALUES ('login_timeout','40','Login timeout (minutes)',NULL,NULL);
INSERT INTO `configurations` VALUES ('mail_protocol','smtp','Select method for sending emails','Supported protocols: MAIL, SMTP, SENDMAIL',NULL);
INSERT INTO `configurations` VALUES ('min_password_length','5','Minimum password length',NULL,NULL);
INSERT INTO `configurations` VALUES ('site_password_protect','no','Password protect website',NULL,NULL);
INSERT INTO `configurations` VALUES ('smtp_host','','SMTP Host name',NULL,NULL);
INSERT INTO `configurations` VALUES ('smtp_pass','','SMTP password',NULL,NULL);
INSERT INTO `configurations` VALUES ('smtp_port','25','SMTP port',NULL,NULL);
INSERT INTO `configurations` VALUES ('smtp_user','','SMTP username',NULL,NULL);
INSERT INTO `configurations` VALUES ('website_footer','','',NULL,NULL);
INSERT INTO `configurations` VALUES ('website_title','Metadata Editor','Website title','Provide the title of the website','website');
INSERT INTO `configurations` VALUES ('website_url','','Website URL','URL of the website','website');
INSERT INTO `configurations` VALUES ('website_webmaster_email','','Site webmaster email address','-','website');
INSERT INTO `configurations` VALUES ('website_webmaster_name','','Webmaster name','-','website');
INSERT INTO `configurations` VALUES ('supported_languages','[{"folder":"english","code":"en","display":"English","direction":"ltr"}]','Supported languages in JSON format',NULL,NULL);
/*!40000 ALTER TABLE `configurations` ENABLE KEYS */;
UNLOCK TABLES;



--
-- API KEYS table
--
CREATE TABLE `api_keys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `api_key` varchar(40) NOT NULL,
  `level` int(2) NOT NULL,
  `ignore_limits` tinyint(1) NOT NULL DEFAULT '0',
  `ip_addresses` text,
  `date_created` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `is_private_key` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_UNIQUE` (`api_key`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

--
-- API Logs table
--
CREATE TABLE `api_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `uri` varchar(255) NOT NULL,
  `method` varchar(6) NOT NULL,
  `params` text,
  `user_id` int DEFAULT NULL,
  `api_key` varchar(40) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `time` int NOT NULL,
  `rtime` float DEFAULT NULL,
  `authorized` varchar(1) NOT NULL,
  `response_code` smallint(3) DEFAULT '0',
  PRIMARY KEY (`id`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

CREATE TABLE `api_logs_daily` (
  `stat_date` date NOT NULL,
  `uri` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL,
  `total_requests` int NOT NULL DEFAULT 0,
  `success_count` int NOT NULL DEFAULT 0,
  `error_count` int NOT NULL DEFAULT 0,
  `avg_response_time` float DEFAULT NULL,
  `avg_runtime` float DEFAULT NULL,
  PRIMARY KEY (`stat_date`,`uri`,`method`)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE `api_logs_user_daily` (
  `stat_date` date NOT NULL,
  `user_id` int NOT NULL DEFAULT 0,
  `api_key` varchar(40) NOT NULL DEFAULT '',
  `total_requests` int NOT NULL DEFAULT 0,
  `error_count` int NOT NULL DEFAULT 0,
  `avg_response_time` float DEFAULT NULL,
  PRIMARY KEY (`stat_date`,`user_id`,`api_key`)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `analytics_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL COMMENT 'User ID if logged in, NULL for anonymous',
  `session_id` varchar(255) NOT NULL COMMENT 'Client-generated session ID',
  `browser_id` varchar(255) DEFAULT NULL COMMENT 'Persistent browser fingerprint',
  `event_type` varchar(50) NOT NULL COMMENT 'Event type: page_view, click, error, slow_page, etc.',
  `page` varchar(255) NOT NULL COMMENT 'Page URL/path (clean, no query params)',
  `ip_address` varchar(45) NOT NULL COMMENT 'User IP address',
  `user_agent` varchar(100) DEFAULT NULL COMMENT 'Compact user agent: Browser-OS-Device',
  `obj_type` varchar(50) DEFAULT NULL COMMENT 'Object type: project, collection, template (for fast queries)',
  `obj_value` varchar(255) DEFAULT NULL COMMENT 'Object ID/value (for fast queries)',
  `data` json DEFAULT NULL COMMENT 'Additional event data (flexible JSON)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When event was recorded',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_browser_id` (`browser_id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_page` (`page`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_obj_type_value` (`obj_type`, `obj_value`),
  KEY `idx_event_obj` (`event_type`, `obj_type`, `obj_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Daily aggregated analytics stats
CREATE TABLE IF NOT EXISTS `analytics_daily` (
  `id` int NOT NULL AUTO_INCREMENT,
  `stat_date` date NOT NULL COMMENT 'Date for this stat record',
  `page` varchar(255) NOT NULL COMMENT 'Page URL/path',
  `obj_type` varchar(50) DEFAULT NULL COMMENT 'Object type: project, collection, template (for object-level aggregation)',
  `obj_value` varchar(255) DEFAULT NULL COMMENT 'Object ID/value (for object-level aggregation)',
  `total_views` int NOT NULL DEFAULT 0 COMMENT 'Total page views',
  `unique_users` int NOT NULL DEFAULT 0 COMMENT 'Unique logged-in users',
  `unique_ips` int NOT NULL DEFAULT 0 COMMENT 'Unique IP addresses',
  `unique_sessions` int NOT NULL DEFAULT 0 COMMENT 'Unique session IDs',
  `avg_time_on_page` int DEFAULT NULL COMMENT 'Average time on page (seconds)',
  `total_clicks` int NOT NULL DEFAULT 0 COMMENT 'Total click events',
  `total_errors` int NOT NULL DEFAULT 0 COMMENT 'Total error events',
  `avg_load_time` int DEFAULT NULL COMMENT 'Average page load time (ms)',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `date_page_obj_UNIQUE` (`stat_date`, `page`, `obj_type`, `obj_value`),
  KEY `idx_stat_date` (`stat_date`),
  KEY `idx_page` (`page`),
  KEY `idx_obj_type_value` (`obj_type`, `obj_value`),
  KEY `idx_date_obj` (`stat_date`, `obj_type`, `obj_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `weight` int DEFAULT '0',
  `is_admin` tinyint(4) DEFAULT '0',
  `is_locked` tinyint(4) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_UNIQUE` (`name`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
insert into roles(id,name,description, weight, is_admin, is_locked) values 
(1,'Admin','Site administrator', 0,1,1),
(2,'User','General user account', 0,0,1),
(3,'Editor','General role required for projects management', 0,0,1),
(6,'Template manager','Global role for managing templates', 0,0,0),
(7,'Collection manager','Global role for managing collections', 0,0,0),
(8,'Schema manager','Global role for managing schemas', 0,0,0),
(9,'Tag manager','Global role for managing tags', 0,0,0),
(10,'Codelist manager','Global role for managing codelists', 0,0,0),
(11,'Data structure manager','Global role for managing data structures', 0,0,0),
(12,'Project manager','Global access to all projects', 0,0,0);
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;


CREATE TABLE `role_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role_id` varchar(45) NOT NULL,
  `resource` varchar(45) DEFAULT NULL,
  `permissions` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

-- Default Role permissions
-- Editor also gets codelist/data_structure view (upgrade_v1_3_0 migration backfill)
insert into role_permissions(role_id,resource,permissions) values
(3,'editor','admin'),
(3,'collection','view'),
(3, 'template_manager','view'),
(3,'codelist','view'),
(3,'data_structure','view'),
(6,'template_manager','admin'),
(7,'collection','admin'),
(8,'schema','admin'),
(9,'tag','admin'),
(10,'codelist','admin'),
(11,'data_structure','admin'),
(12,'editor','view'),
(12,'project_manager','admin');


CREATE TABLE `user_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `role_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;



CREATE TABLE `editor_catalogs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(200) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `api_key` varchar(200) DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;


CREATE TABLE `editor_data_files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sid` int NOT NULL,
  `file_id` varchar(100)  DEFAULT NULL,
  `file_name` varchar(255)  DEFAULT NULL,
  `description` text ,
  `case_count` int DEFAULT NULL,
  `var_count` int DEFAULT NULL,
  `producer` varchar(255)  DEFAULT NULL,
  `data_checks` text,
  `missing_data` text,
  `version` varchar(255)  DEFAULT NULL,
  `notes` text,
  `metadata` text,
  `wght` int DEFAULT NULL,
  `file_physical_name` varchar(500) DEFAULT NULL,
  `store_data` int DEFAULT NULL,
  `source_format` varchar(10) DEFAULT NULL COMMENT 'csv|dta|sav — format of the uploaded source file',
  `source_format_version` varchar(50) DEFAULT NULL COMMENT 'Stata/SPSS file format version (e.g. 14, 118)',
  `source_upload_filename` varchar(500) DEFAULT NULL COMMENT 'Original client filename at upload (before sanitize)',
  `source_status` varchar(20) NOT NULL DEFAULT 'unknown' COMMENT 'present|missing|not_applicable|unknown',
  `source_attached_at` int DEFAULT NULL COMMENT 'Unix time when source file was uploaded or attached',
  `source_attached_by` int DEFAULT NULL COMMENT 'User id who uploaded or attached the source file',
  `created` int DEFAULT NULL,
  `changed` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `changed_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`),
  KEY `idx_edf_source_status` (`source_status`),
  KEY `idx_edf_source_format` (`source_format`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;



CREATE TABLE `editor_projects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idno` varchar(200) DEFAULT NULL,
  `type` varchar(15) DEFAULT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  `abbreviation` varchar(45) DEFAULT NULL,
  `authoring_entity` text ,
  `nation` varchar(150) DEFAULT '',
  `year_start` int DEFAULT '0',
  `year_end` int DEFAULT '0',
  `metafile` varchar(255) DEFAULT NULL,
  `dirpath` varchar(255) DEFAULT NULL,
  `varcount` int DEFAULT NULL,
  `published` tinyint DEFAULT NULL,
  `created` int DEFAULT NULL,
  `changed` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `changed_by` int DEFAULT NULL,
  `thumbnail` varchar(300) DEFAULT NULL,
  `metadata` mediumtext ,
  `template_uid` varchar(100) DEFAULT NULL,
  `is_shared` int DEFAULT NULL,
  `study_idno` varchar(300) DEFAULT NULL,
  `pid` int DEFAULT NULL,
  `is_locked` int DEFAULT NULL,
  `version_number` varchar(15) DEFAULT NULL,
  `version_created` int DEFAULT NULL,
  `version_created_by` int DEFAULT NULL,
  `version_notes` varchar(500) DEFAULT NULL,
  `attributes` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unq_idno` (`idno`,`version_number`),
  FULLTEXT KEY `ft_projects` (`title`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;



CREATE TABLE `editor_resources` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sid` int NOT NULL,
  `dctype` varchar(255) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `dcdate` varchar(45) DEFAULT NULL,
  `country` varchar(45) DEFAULT NULL,
  `language` varchar(255) DEFAULT NULL,
  `id_number` varchar(255) DEFAULT NULL,
  `contributor` varchar(255) DEFAULT NULL,
  `publisher` varchar(255) DEFAULT NULL,
  `rights` varchar(255) DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4,
  `abstract` text CHARACTER SET utf8mb4,
  `toc` text CHARACTER SET utf8mb4,
  `subjects` varchar(45) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `dcformat` varchar(255) DEFAULT NULL,
  `source_type` varchar(20) DEFAULT 'manual',
  `bundle_type` varchar(10) DEFAULT NULL,
  `changed` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;


CREATE TABLE `editor_resource_data_files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sid` int NOT NULL,
  `resource_id` int NOT NULL,
  `file_id` varchar(100) NOT NULL,
  `export_format` varchar(20) DEFAULT NULL,
  `export_version` varchar(20) DEFAULT NULL,
  `zip_entry_name` varchar(255) DEFAULT NULL,
  `link_type` varchar(20) DEFAULT NULL,
  `data_file_changed` int DEFAULT NULL,
  `source_csv_mtime` int DEFAULT NULL,
  `source_mode` varchar(20) DEFAULT NULL COMMENT 'original|generated',
  `source_physical_mtime` int DEFAULT NULL COMMENT 'Unix mtime when original was used',
  `generated_at` int DEFAULT NULL,
  `created` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_resource_file` (`resource_id`, `file_id`),
  KEY `idx_erdf_sid_resource` (`sid`, `resource_id`),
  KEY `idx_erdf_sid_file` (`sid`, `file_id`),
  KEY `idx_erdf_resource` (`resource_id`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;


CREATE TABLE `editor_variables` (
  `uid` int NOT NULL AUTO_INCREMENT,
  `sid` int NOT NULL,
  `fid` varchar(45)  DEFAULT NULL,
  `vid` varchar(45)  DEFAULT '',
  `name` varchar(100)  DEFAULT '',
  `labl` varchar(255)  DEFAULT '',
  `metadata` mediumtext ,
  `sort_order` int DEFAULT '0',
  `user_missings` varchar(300) DEFAULT NULL,
  `is_weight` int DEFAULT '0',
  `field_dtype` varchar(30) DEFAULT NULL,
  `field_format` varchar(50) DEFAULT NULL,
  `var_wgt_id` int DEFAULT NULL,
  `is_key` int DEFAULT NULL,
  `interval_type` enum('discrete','contin') DEFAULT NULL,
  PRIMARY KEY (`uid`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_sid_fid_name ON editor_variables (sid, fid, name);


CREATE TABLE `editor_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `uid` varchar(45) DEFAULT NULL,
  `data_type` varchar(45) NOT NULL,
  `lang` varchar(45) DEFAULT NULL,
  `template_type` varchar(20) NOT NULL DEFAULT 'custom',
  `name` varchar(100) NOT NULL,
  `version` varchar(45) DEFAULT NULL,
  `organization` varchar(300) DEFAULT NULL,
  `author` varchar(100) DEFAULT NULL,
  `description` text,
  `template` mediumtext,
  `created` int DEFAULT NULL,
  `changed` int DEFAULT NULL,
  `instructions` text,
  `created_by` int DEFAULT NULL,
  `changed_by` int DEFAULT NULL,
  `owner_id` int DEFAULT NULL,
  `is_private` int DEFAULT NULL,
  `is_published` int DEFAULT NULL,
  `is_deleted` int DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `deleted_at` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid_UNIQUE` (`uid`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;


CREATE TABLE `editor_templates_default` (
  `id` int NOT NULL AUTO_INCREMENT,
  `data_type` varchar(30) NOT NULL,
  `template_uid` varchar(255) NOT NULL,  
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8mb4;


insert into `editor_templates_default` (data_type, template_uid)
values
('microdata','microdata-system-en'),
('indicator','timeseries-system-en'),
('indicator-db','timeseries-db-system-en'),
('script','script-system-en'),
('geospatial','geospatial-system-en'),
('document','document-system-en'),
('table','table-system-en'),
('image','image-system-en'),
('video','video-system-en'),
('resource','resource-system-en'),
('admin_meta','system-core-admin-meta'),
('custom','custom-system-en');



CREATE TABLE `editor_template_acl` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int NOT NULL,
  `permissions` varchar(100) DEFAULT NULL,
  `user_id` int NOT NULL,
  `created` int DEFAULT NULL,
  PRIMARY KEY (`id`)
)  AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;


create table editor_project_owners (
  id int not null auto_increment,
  permissions varchar(100),
  sid int not null,
  user_id int not null,
  created int,
  primary key (id)
) DEFAULT CHARSET=utf8mb4;


CREATE TABLE `editor_collections` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` varchar(1000) DEFAULT NULL,
  `created` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `changed` int DEFAULT NULL,
  `changed_by` int DEFAULT NULL,
  `pid` int DEFAULT NULL,
  `wgt` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `title` (`title`,`pid`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;


create table editor_collection_projects (
  id int not null auto_increment,
  collection_id int not null,
  sid int not null,
  primary key (id)
) DEFAULT CHARSET=utf8mb4;

create table editor_collection_project_acl (
  id int not null auto_increment,
  collection_id int not null,
  user_id int not null,
  permissions varchar(100),
  primary key (id)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE `editor_collection_acl` (
  `id` int NOT NULL AUTO_INCREMENT,
  `collection_id` int NOT NULL,
  `permissions` varchar(100) DEFAULT NULL,
  `user_id` int NOT NULL,
  `created` int DEFAULT NULL,
  `changed` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;


CREATE TABLE `editor_variable_groups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sid` int DEFAULT NULL,
  `metadata` MEDIUMTEXT,
  PRIMARY KEY (`id`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;



CREATE TABLE editor_variables_sort_tmp(  
    id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    sid int not null,
    var_uid int not null,
    sort_order int not null
) DEFAULT CHARSET=utf8mb4;


CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `obj_type` varchar(25) NOT NULL,
  `obj_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action_type` varchar(25) NOT NULL,
  `created` datetime NOT NULL,
  `metadata` json DEFAULT NULL,
  `obj_ref_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;



CREATE TABLE `edit_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `obj_type` varchar(15) NOT NULL,
  `obj_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action_type` varchar(10) NOT NULL,
  `created` datetime NOT NULL,
  `metadata` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;



CREATE TABLE editor_code_lists(  
    pk_id int NOT NULL AUTO_INCREMENT,
    id varchar(100) not null,
    agency_id varchar(100) not null,
    name varchar(300) not null,
    description varchar(300) default null,
    version varchar(30) default null,    
    created int default null,
    changed int default null,
    created_by int DEFAULT null,
    changed_by int default null,    
    PRIMARY KEY (`pk_id`),
    UNIQUE KEY `cl_id` (`id`,`agency_id`, `version`)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE editor_code_list_items(  
    pk_id int NOT NULL AUTO_INCREMENT,
    cl_id int not null,
    id varchar(100) not null,
    name varchar(300) not null,
    description varchar(300) default null,
    created int default null,
    changed int default null,
    created_by int DEFAULT null,
    changed_by int default null,    
    PRIMARY KEY (`pk_id`),
    UNIQUE KEY `cl_item_id` (`id`,`cl_id`)
) DEFAULT CHARSET=utf8mb4;


CREATE TABLE `editor_collections_tree` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parent_id` int DEFAULT NULL,
  `child_id` int DEFAULT NULL,
  `depth` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_closure` (`parent_id`,`child_id`,`depth`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;


CREATE TABLE `admin_metadata` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int DEFAULT NULL,
  `sid` int DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `changed_by` int DEFAULT NULL,
  `created` int DEFAULT NULL,
  `changed` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `meta_unq` (`template_id`,`sid`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

CREATE TABLE `admin_metadata_acl` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int NOT NULL,
  `permissions` varchar(100) DEFAULT NULL,
  `user_id` int NOT NULL,
  `created` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

CREATE TABLE `admin_metadata_projects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sid` int DEFAULT NULL,
  `template_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8mb4;


-- collection indexes
CREATE INDEX idx_eca_user_collection ON editor_collection_acl(user_id, collection_id);
CREATE INDEX idx_ecpa_user_collection ON editor_collection_project_acl(user_id, collection_id);
CREATE INDEX idx_collections_created_by ON editor_collections(created_by);
CREATE INDEX idx_collection_id ON editor_collection_projects(collection_id);


CREATE TABLE `metadata_schemas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(100) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `agency` varchar(100) DEFAULT NULL,
  `description` text,
  `is_core` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('active','draft') DEFAULT 'active',
  `storage_path` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL DEFAULT 'main.json',
  `schema_files` json DEFAULT NULL,
  `metadata_options` json DEFAULT NULL,
  `alias` varchar(100) DEFAULT NULL,
  `created` int unsigned NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid_unique` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;



INSERT INTO metadata_schemas
  (uid,title,agency,description,is_core,status,storage_path,filename,
   schema_files,metadata_options,alias,created)
VALUES
  ('microdata','Microdata (DDI 2.5)','IHSN','Microdata schema based on DDI CodeBook 2.5',
   1,'active','', 'microdata-schema.json',
   '["ddi-schema.json", "datacite-schema.json", "provenance-schema.json", "datafile-schema.json", "variable-schema.json", "variable-group-schema.json"]',
   '{"core_fields":{"idno":"study_desc.title_statement.idno","title":"study_desc.title_statement.title"},"derived_fields":{"countries":"study_desc.study_info.nation[*].name","year_start":"study_desc.study_info.coll_dates[0].start","year_end":"study_desc.study_info.coll_dates[0].end"}}',
   'survey',
   UNIX_TIMESTAMP()),
  ('document','Document','IHSN','Document schema based on Dublin Core',
   1,'active','', 'document-schema.json',
   '["provenance-schema.json"]',
   '{"core_fields":{"idno":"document_description.title_statement.idno","title":"document_description.title_statement.title"}}',
   '',
   UNIX_TIMESTAMP()),
  ('table','Statistical Table','IHSN','Statistical table schema based on Dublin Core',
   1,'active','', 'table-schema.json',
   '["provenance-schema.json"]',
   '{"core_fields":{"idno":"table_description.title_statement.idno","title":"table_description.title_statement.title"}}',
   '',
   UNIX_TIMESTAMP()),
  ('script','Script / Project','IHSN','Script/Project schema based on Dublin Core',
   1,'active','', 'script-schema.json',
   '["datacite-schema.json","provenance-schema.json"]',
   '{"core_fields":{"idno":"project_desc.title_statement.idno","title":"project_desc.title_statement.title"}}',
   '',
   UNIX_TIMESTAMP()),
  ('video','Video','IHSN','Video schema based on Dublin Core',
   1,'active','', 'video-schema.json',
   '[]',
   '{"core_fields":{"idno":"video_description.idno","title":"video_description.title"}}',
   '',
   UNIX_TIMESTAMP()),
  ('indicator','Indicator','IHSN','Indicator schema',
   1,'active','', 'timeseries-schema.json',
   '["datacite-schema.json","provenance-schema.json"]',
   '{"core_fields":{"idno":"series_description.idno","title":"series_description.name"},"derived_fields":{"year_start":"series_description.time_periods[0].start","year_end":"series_description.time_periods[0].end"}}',
   'timeseries',
   UNIX_TIMESTAMP()),
  ('indicator-db','Indicator Database','IHSN','Indicator database schema',
   1,'active','', 'timeseries-db-schema.json',
   '["provenance-schema.json"]',
   '{"core_fields":{"idno":"database_description.title_statement.idno","title":"database_description.title_statement.title"}}',
   'timeseries-db',
   UNIX_TIMESTAMP()),
  ('geospatial','Geospatial','IHSN','Geospatial schema based on ISO 19139',
   1,'active','', 'geospatial-schema.json',
   '["provenance-schema.json"]',
   '{"core_fields":{"idno":"description.idno","title":"description.identificationInfo.citation.title"}}',
    '',
   UNIX_TIMESTAMP()),
  ('image','Image','IHSN','Image schema based on DCMI and IPTC',
   1,'active','', 'image-schema.json',
   '["dcmi-schema.json","iptc-pmd-schema.json","iptc-phovidmdshared-schema.json"]',
   '{"core_fields":{"idno":"image_description.idno","title":"image_description.dcmi.title"}}',
   '',
   UNIX_TIMESTAMP()),
  ('custom','Custom','IHSN','Catch-all schema for custom content',
   1,'active','', 'custom-schema.json',
   '[]',
   '{"core_fields":{"idno":"/identification/idno","title":"/identification/title"}}',
   '',
   UNIX_TIMESTAMP());

CREATE TABLE `geospatial_features` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sid` int NOT NULL,
  `code` varchar(100) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `definition` text default NULL,
  `is_abstract` tinyint(1) DEFAULT '0',
  `aliases` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created` datetime DEFAULT CURRENT_TIMESTAMP,
  `changed` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `changed_by` int DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` bigint DEFAULT NULL,
  `upload_status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `processing_notes` text,
  `layer_name` varchar(255) DEFAULT NULL,
  `layer_type` enum('point','line','polygon','raster','mixed','unknown') DEFAULT 'unknown',
  `feature_count` int DEFAULT '0',
  `geometry_type` varchar(100) DEFAULT NULL,
  `bounds` json DEFAULT NULL,
  `data_file` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;


CREATE TABLE `geospatial_feature_chars` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sid` int NOT NULL,
  `feature_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `data_type` varchar(30) DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created` datetime DEFAULT CURRENT_TIMESTAMP,
  `changed` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `changed_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_feature_char` (`sid`,`feature_id`,`name`),
  KEY `idx_feature_chars_feature_id` (`feature_id`),
  CONSTRAINT `geospatial_feature_chars_ibfk_1` FOREIGN KEY (`feature_id`) REFERENCES `geospatial_features` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;


CREATE TABLE `job_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `job_type` varchar(50) NOT NULL,
  `job_hash` varchar(64) DEFAULT NULL,
  `status` enum('pending','held','processing','completed','failed','cancelled') DEFAULT 'pending',
  `priority` int DEFAULT 0,
  `user_id` int DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `result` json DEFAULT NULL,
  `error_message` text,
  `attempts` int DEFAULT 0,
  `max_attempts` int DEFAULT 3,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `started_at` timestamp NULL,
  `completed_at` timestamp NULL,
  `worker_id` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_uuid` (`uuid`),
  KEY `idx_status_priority` (`status`, `priority` DESC, `created_at`),
  KEY `idx_job_type` (`job_type`),
  KEY `idx_job_hash` (`job_hash`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_worker` (`worker_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `tags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tag` varchar(50) NOT NULL,
  `is_core` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `tag_UNIQUE` (`tag`),
  KEY `idx_is_core` (`is_core`)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE `project_tags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sid` int NOT NULL,
  `tag_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unq_sid_tag` (`sid`,`tag_id`),
  KEY `idx_tag_id` (`tag_id`)
) DEFAULT CHARSET=utf8mb4;


-- Global codelists registry (upgrades: install/schema.mysql-update-1.3.sql)
CREATE TABLE `codelists` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `pid` bigint DEFAULT NULL COMMENT 'Family head row id (latest version for agency+name); set on create',
  `idno` varchar(191) NOT NULL,
  `agency` varchar(64) NOT NULL,
  `name` varchar(64) NOT NULL COMMENT 'SDMX maintainable id (NADA codelists.name)',
  `version` varchar(32) NOT NULL,
  `version_seq` int NOT NULL COMMENT 'Monotonic sequence within (agency, name)',
  `title` varchar(255) NOT NULL COMMENT 'Human-readable list title',
  `description` text DEFAULT NULL,
  `uri` varchar(500) DEFAULT NULL,
  `status` enum('draft','active','locked','archived') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `changed_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `changed_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_codelists_idno` (`idno`),
  UNIQUE KEY `uq_codelist_identity` (`agency`,`name`,`version`),
  UNIQUE KEY `uq_codelists_family_seq` (`agency`,`name`,`version_seq`),
  KEY `idx_codelists_agency` (`agency`),
  KEY `idx_codelists_pid` (`pid`),
  KEY `idx_codelists_status` (`status`),
  KEY `idx_codelists_created` (`created_at`),
  CONSTRAINT `fk_codelists_pid` FOREIGN KEY (`pid`) REFERENCES `codelists` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `codelist_labels` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `codelist_id` bigint NOT NULL COMMENT 'FK -> codelists.id',
  `language` varchar(10) NOT NULL,
  `label` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_codelist_language` (`codelist_id`,`language`),
  KEY `idx_language` (`language`),
  CONSTRAINT `fk_codelist_labels_codelist` FOREIGN KEY (`codelist_id`) REFERENCES `codelists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `codelist_items` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `codelist_id` bigint NOT NULL COMMENT 'FK -> codelists.id',
  `code` varchar(150) NOT NULL,
  `parent_id` bigint DEFAULT NULL,
  `sort_order` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_item_per_list` (`codelist_id`,`code`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_sort` (`codelist_id`,`sort_order`),
  CONSTRAINT `fk_codelist_items_codelist` FOREIGN KEY (`codelist_id`) REFERENCES `codelists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_codelist_items_parent` FOREIGN KEY (`parent_id`) REFERENCES `codelist_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `codelist_items_labels` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `codelist_item_id` bigint NOT NULL,
  `language` varchar(10) NOT NULL,
  `label` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_codelist_item_language` (`codelist_item_id`,`language`),
  KEY `idx_language` (`language`),
  CONSTRAINT `fk_codelist_items_labels_item` FOREIGN KEY (`codelist_item_id`) REFERENCES `codelist_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Global data structure registry + project binding (upgrades: install/schema.mysql-update-1.3.sql)
CREATE TABLE `data_structures` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pid` int DEFAULT NULL,
  `agency` varchar(64) NOT NULL DEFAULT 'NADA',
  `name` varchar(64) NOT NULL COMMENT 'SDMX maintainable id',
  `version` varchar(32) NOT NULL,
  `version_seq` int NOT NULL,
  `idno` varchar(191) DEFAULT NULL,
  `status` smallint NOT NULL DEFAULT 0,
  `title` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `notes` text,
  `content_hash` char(64) DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created` int DEFAULT NULL,
  `updated` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unq_data_structures_identity` (`agency`,`name`,`version`),
  UNIQUE KEY `unq_data_structures_family_seq` (`agency`,`name`,`version_seq`),
  UNIQUE KEY `unq_data_structures_idno` (`idno`),
  KEY `idx_data_structures_agency_name` (`agency`,`name`),
  KEY `idx_data_structures_pid` (`pid`),
  CONSTRAINT `fk_data_structures_pid` FOREIGN KEY (`pid`) REFERENCES `data_structures` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `data_structure_components` (
  `id` int NOT NULL AUTO_INCREMENT,
  `data_structure_id` int NOT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `name` varchar(100) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `description` text,
  `data_type` enum('string','integer','float','double','date','boolean') DEFAULT NULL,
  `column_type` enum('dimension','time_period','measure','attribute','indicator_id','indicator_name','annotation','geography','observation_value','periodicity') NOT NULL,
  `codelist_id` bigint DEFAULT NULL COMMENT 'FK -> codelists.id',
  `metadata` json DEFAULT NULL,
  `created` int DEFAULT NULL,
  `updated` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unq_dsc_structure_name` (`data_structure_id`,`name`),
  KEY `idx_dsc_structure_sort` (`data_structure_id`,`sort_order`),
  KEY `idx_dsc_codelist` (`codelist_id`),
  CONSTRAINT `fk_dsc_data_structure` FOREIGN KEY (`data_structure_id`) REFERENCES `data_structures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dsc_codelist` FOREIGN KEY (`codelist_id`) REFERENCES `codelists` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `editor_project_dsd` (
  `sid` int NOT NULL,
  `data_structure_id` int NOT NULL,
  `indicator_id_value` varchar(191) DEFAULT NULL COMMENT 'Value written to indicator_id column on CSV import',
  `implied_freq_code` varchar(16) DEFAULT NULL COMMENT 'SDMX FREQ when structure has no periodicity column',
  `has_published_data` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 after successful timeseries import',
  `published_row_count` int DEFAULT NULL,
  `data_imported_at` int DEFAULT NULL,
  `created` int DEFAULT NULL,
  `updated` int DEFAULT NULL,
  PRIMARY KEY (`sid`),
  KEY `idx_editor_project_dsd_structure` (`data_structure_id`),
  CONSTRAINT `fk_editor_project_dsd_project` FOREIGN KEY (`sid`) REFERENCES `editor_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_editor_project_dsd_structure` FOREIGN KEY (`data_structure_id`) REFERENCES `data_structures` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Project metadata issues (upgrades: install/schema.mysql-update-1.3.sql)
CREATE TABLE `project_issues` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `category` varchar(100) DEFAULT NULL,  
  `severity` enum('low','medium','high','critical') DEFAULT NULL,
  `status` enum('open','accepted','rejected','fixed','dismissed','false_positive') NOT NULL DEFAULT 'open',
  `field_path` varchar(500) DEFAULT NULL,
  `current_metadata` json DEFAULT NULL,
  `suggested_metadata` json DEFAULT NULL,
  `source` varchar(50) DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created` int DEFAULT NULL,
  `assigned_to` int DEFAULT NULL,
  `resolved_by` int DEFAULT NULL,
  `resolved` int DEFAULT NULL,
  `applied` tinyint(1) NOT NULL DEFAULT 0,
  `applied_by` int DEFAULT NULL,
  `applied_on` int DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_status` (`status`),
  KEY `idx_category` (`category`),
  KEY `idx_field_path` (`field_path`),
  KEY `idx_created` (`created`),
  CONSTRAINT `fk_project_issues_project` FOREIGN KEY (`project_id`) REFERENCES `editor_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- CodeIgniter migration ledger (fresh installs: schema already includes all migration SQL;
-- bump version when adding application/migrations/*.php)
CREATE TABLE `migrations` (
  `version` bigint unsigned NOT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `migrations` (`version`) VALUES (20260703000002);