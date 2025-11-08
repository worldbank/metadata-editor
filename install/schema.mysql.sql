--
-- Table structure for table `meta`
--

DROP TABLE IF EXISTS `meta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `meta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
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

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(30) NOT NULL,
  `login` varchar(100) NOT NULL,
  `time` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;




--
-- Table structure for table `dcformats`
--

DROP TABLE IF EXISTS `dcformats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dcformats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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

DROP TABLE IF EXISTS `dctypes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dctypes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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

DROP TABLE IF EXISTS `users`;
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
  `created_on` int(11) NOT NULL,
  `last_login` int(11) NOT NULL,
  `active` tinyint(3) DEFAULT NULL,
  `authtype` varchar(40) DEFAULT NULL,
  `otp_code` varchar(45) DEFAULT NULL,
  `otp_expiry` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;


--
-- Table structure for table `ci_sessions`
--

DROP TABLE IF EXISTS `ci_sessions`;
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

DROP TABLE IF EXISTS `sitelogs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sitelogs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sessionid` varchar(255) NOT NULL DEFAULT '',
  `logtime` varchar(45) NOT NULL DEFAULT '0',
  `ip` varchar(45) NOT NULL,
  `url` varchar(255) NOT NULL DEFAULT '',
  `logtype` varchar(45) NOT NULL,
  `surveyid` int(11) DEFAULT '0',
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

DROP TABLE IF EXISTS `configurations`;
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
INSERT INTO `configurations` VALUES ('theme','default','Site theme name',NULL,NULL);
INSERT INTO `configurations` VALUES ('topics_vocab','1','Vocabulary ID for Topics',NULL,NULL);
INSERT INTO `configurations` VALUES ('topic_search','no','Topic search',NULL,NULL);
INSERT INTO `configurations` VALUES ('topic_search_weight','6',NULL,NULL,NULL);
INSERT INTO `configurations` VALUES ('use_html_editor','yes','Use HTML editor for entering HTML for static pages',NULL,NULL);
INSERT INTO `configurations` VALUES ('website_footer','',NULL,NULL);
INSERT INTO `configurations` VALUES ('website_title','Metadata Editor','Website title','Provide the title of the website','website');
INSERT INTO `configurations` VALUES ('website_url','','Website URL','URL of the website','website');
INSERT INTO `configurations` VALUES ('website_webmaster_email','','Site webmaster email address','-','website');
INSERT INTO `configurations` VALUES ('website_webmaster_name','','Webmaster name','-','website');
/*!40000 ALTER TABLE `configurations` ENABLE KEYS */;
UNLOCK TABLES;



--
-- API KEYS table
--
CREATE TABLE `api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_key` varchar(40) NOT NULL,
  `level` int(2) NOT NULL,
  `ignore_limits` tinyint(1) NOT NULL DEFAULT '0',
  `ip_addresses` text,
  `date_created` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_private_key` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_UNIQUE` (`api_key`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

--
-- API Logs table
--
CREATE TABLE `api_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uri` varchar(255) NOT NULL,
  `method` varchar(6) NOT NULL,
  `params` text,
  `user_id` int DEFAULT NULL,
  `api_key` varchar(40) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `time` int(11) NOT NULL,
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

CREATE TABLE `api_logs_ip_daily` (
  `stat_date` date NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `total_requests` int NOT NULL DEFAULT 0,
  `error_count` int NOT NULL DEFAULT 0,
  `avg_response_time` float DEFAULT NULL,
  PRIMARY KEY (`stat_date`,`ip_address`)
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
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `weight` int(11) DEFAULT '0',
  `is_admin` tinyint(4) DEFAULT '0',
  `is_locked` tinyint(4) DEFAULT '0',
  PRIMARY KEY (`id`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
insert into roles(id,name,description, weight, is_admin, is_locked) values 
(1,'admin','Site administrator and has access to all site content', 0,1,1),
(2,'user','General user account with no access to site administration', 0,0,1);
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;


CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` varchar(45) NOT NULL,
  `resource` varchar(45) DEFAULT NULL,
  `permissions` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`)
) AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;


CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
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
  `created` int DEFAULT NULL,
  `changed` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `changed_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
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
  `changed` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
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
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `data_type` varchar(30) NOT NULL,
  `template_uid` varchar(255) NOT NULL,  
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8mb4;


insert into `editor_templates_default` (data_type, template_uid)
values('resource','resource-system-en');



CREATE TABLE `editor_template_acl` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int NOT NULL,
  `permissions` varchar(100) DEFAULT NULL,
  `user_id` int NOT NULL,
  `created` int DEFAULT NULL,
  PRIMARY KEY (`id`)
)  AUTO_INCREMENT=1;


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


create table editor_project_tags (
  id int not null auto_increment,
  sid int not null,
  tag_id int not null,
  primary key (id)
) DEFAULT CHARSET=utf8mb4;


CREATE TABLE editor_tags(  
    id int NOT NULL PRIMARY KEY AUTO_INCREMENT,    
    tag VARCHAR(255) not null
) DEFAULT CHARSET=utf8mb4;


CREATE TABLE editor_project_tags(  
    id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    sid int not null,
    tag_id int not null
) DEFAULT CHARSET=utf8mb4;


CREATE TABLE `editor_variable_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sid` int(11) DEFAULT NULL,
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

