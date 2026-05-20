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


-- update editor_projects data type column

-- replace 'survey' with 'microdata'
UPDATE `editor_projects` SET `type` = 'microdata' WHERE `type` = 'survey';

-- replace 'timeseries' with 'indicator'
UPDATE `editor_projects` SET `type` = 'indicator' WHERE `type` = 'timeseries';

-- replace 'timeseries-db' with 'indicator-db'
UPDATE `editor_projects` SET `type` = 'indicator-db' WHERE `type` = 'timeseries-db';

-- geospatial features
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


CREATE INDEX idx_feature_chars_feature_id ON geospatial_feature_chars(feature_id);

-- analytics
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


-- job queue
CREATE TABLE `job_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `job_type` varchar(50) NOT NULL,
  `job_hash` varchar(64) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
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


-- Editor templates
ALTER TABLE `editor_templates`
ADD COLUMN `template_type` VARCHAR(20) NOT NULL DEFAULT 'custom' AFTER `lang`;

UPDATE `editor_templates`
SET `template_type` = CASE
    WHEN `uid` LIKE '%__core' THEN 'generated'
    ELSE 'custom'
END;

-- Seed default custom template mapping if missing
INSERT INTO `editor_templates_default` (`data_type`, `template_uid`)
SELECT 'custom', 'custom-system-en'
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `editor_templates_default`
  WHERE `data_type`='custom' AND `template_uid`='custom-system-en'
);


-- drop old tags table
DROP TABLE `editor_tags`;
DROP TABLE `tags`;
DROP TABLE `editor_project_tags`;

-- create tags and project_tags
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


-- seed supported languages configuration
INSERT INTO `configurations` VALUES ('supported_languages','[{"folder":"english","code":"en","display":"English","direction":"ltr"}]','Supported languages in JSON format',NULL,NULL);


-- add unqiue key to roles table
ALTER TABLE `roles` ADD UNIQUE KEY `name_UNIQUE` (`name`);

-- Add user role - Editor if not exists
insert into roles(name,description, weight, is_admin, is_locked) values 
('Editor','General role required for projects management', 0,0,1);

-- Editor role permissions

-- delete existing Editor role permissions
delete from role_permissions where role_id = (select id from roles where name = 'Editor');

-- Editor role access to view own projects
insert into role_permissions(role_id,resource,permissions) values
((select id from roles where name = 'Editor'),'editor','admin');

-- Editor role access for collections
insert into role_permissions(role_id,resource,permissions) values
((select id from roles where name = 'Editor'),'collection','view');

-- Editor role access for templates
insert into role_permissions(role_id,resource,permissions) values
((select id from roles where name = 'Editor'),'template_manager','view');
