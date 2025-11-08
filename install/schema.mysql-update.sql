ALTER TABLE `editor_data_files` MODIFY COLUMN data_checks TEXT DEFAULT NULL;
ALTER TABLE `editor_data_files` MODIFY COLUMN missing_data TEXT DEFAULT NULL;
ALTER TABLE `editor_data_files` MODIFY COLUMN notes TEXT DEFAULT NULL;

ALTER TABLE `editor_data_files` MODIFY COLUMN metadata TEXT DEFAULT NULL;


# Add pid and wgt columns to editor_collections
ALTER TABLE `editor_collections` add pid int DEFAULT NULL;
ALTER TABLE `editor_collections` add wgt int DEFAULT NULL;
ALTER TABLE `editor_collections` ADD UNIQUE index `idx_title_pid` (`title`,`pid`);


# Add fulltext index to editor_projects
ALTER TABLE `editor_projects` 
ADD FULLTEXT INDEX `ft_projects` (`title`) ;


ALTER TABLE `audit_logs` 
ADD COLUMN `metadata` JSON NULL;

ALTER TABLE `audit_logs` 
CHANGE COLUMN `description` `action_type` VARCHAR(10) NOT NULL ;


CREATE TABLE `editor_template_acl` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int NOT NULL,
  `permissions` varchar(100) DEFAULT NULL,
  `user_id` int NOT NULL,
  `created` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;



ALTER TABLE `editor_templates` 
ADD COLUMN `created_by` INT;

ALTER TABLE `editor_templates` 
ADD COLUMN `changed_by` INT;

ALTER TABLE `editor_templates`
ADD COLUMN `owner_id` INT;

ALTER TABLE `editor_templates`
ADD COLUMN `is_private` INT NULL;

ALTER TABLE `editor_templates`
ADD COLUMN `is_published` INT NULL;

ALTER TABLE `editor_templates`
ADD COLUMN `is_deleted` INT NULL AFTER `is_published`,
ADD COLUMN `deleted_by` INT NULL AFTER `is_deleted`,
ADD COLUMN `deleted_at` INT NULL AFTER `deleted_by`;


update editor_templates set created_by=1 where created_by is null;
update editor_templates set owner_id=created_by where owner_id is null;


drop table `edit_history`;
CREATE TABLE `edit_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `obj_type` varchar(15) NOT NULL,
  `obj_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action_type` varchar(10) NOT NULL,
  `created` INT DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;


ALTER TABLE `editor_data_files` 
ADD COLUMN `store_data` INT NULL;


ALTER TABLE `editor_data_files` 
ADD COLUMN `created` INT NULL AFTER `store_data`,
ADD COLUMN `changed` INT NULL AFTER `created`,
ADD COLUMN `created_by` INT NULL AFTER `changed`,
ADD COLUMN `changed_by` INT NULL AFTER `created_by`;


# 2025/02/11
# admin metadata types

drop table `metadata_schemas`;  
drop TABLE `metadata_types`;
drop TABLE `metadata_types_acl`;
drop TABLE `metadata_types_data`;



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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

CREATE TABLE `admin_metadata_acl` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int NOT NULL,
  `permissions` varchar(100) DEFAULT NULL,
  `user_id` int NOT NULL,
  `created` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

# 2025/02/15
CREATE TABLE `admin_metadata_projects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sid` int DEFAULT NULL,
  `template_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8mb4;

# 2025/03/31
ALTER TABLE `audit_logs` 
CHANGE COLUMN `obj_type` `obj_type` VARCHAR(25) NOT NULL ;

ALTER TABLE `audit_logs` 
CHANGE COLUMN `action_type` `action_type` VARCHAR(25) NOT NULL ;

ALTER TABLE `editor_projects` 
ADD COLUMN `attributes` JSON NULL;

ALTER TABLE `editor_variables`
ADD COLUMN `is_key` INT NULL;


# 2025/06/07
ALTER TABLE `audit_logs`
ADD COLUMN `obj_ref_id` INT NULL;


# 2025/06/19
# project versions

ALTER TABLE `editor_projects` 
ADD COLUMN `pid` INT NULL AFTER `study_idno`,
ADD COLUMN `is_locked` INT NULL AFTER `pid`,
ADD COLUMN `version_created` INT NULL AFTER `is_locked`,
ADD COLUMN `version_created_by` INT NULL AFTER `version_created`,
ADD COLUMN `version_notes` VARCHAR(500) NULL AFTER `version_created_by`,
ADD COLUMN `version_number` VARCHAR(15) NULL AFTER `study_idno`,
ADD UNIQUE INDEX `unq_idno` (`idno` ASC, `version_number` ASC);

# need this to optimize the search for variables
CREATE INDEX idx_sid_fid_name ON editor_variables (sid, fid, name);


# 2025/08/24
# collections ACL

CREATE TABLE `editor_collection_acl` (
  `id` int NOT NULL AUTO_INCREMENT,
  `collection_id` int NOT NULL,
  `permissions` varchar(100) DEFAULT NULL,
  `user_id` int NOT NULL,
  `created` int DEFAULT NULL,
  `changed` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;


# rename editor_collection_access to editor_collection_project_acl
ALTER TABLE `editor_collection_access` RENAME TO `editor_collection_project_acl`;


# 2025/09/10
# Add interval_type field to editor_variables table
ALTER TABLE `editor_variables` ADD COLUMN `interval_type` varchar(20) DEFAULT NULL;


# 2025/09/17
# audit_logs indexes
ALTER TABLE `audit_logs` ADD INDEX `idx_audit_logs_created` (`created` DESC);

-- Index on user_id for filtering
ALTER TABLE `audit_logs` ADD INDEX `idx_audit_logs_user_id` (`user_id`);

-- Index on obj_type for filtering
ALTER TABLE `audit_logs` ADD INDEX `idx_audit_logs_obj_type` (`obj_type`);

-- Index on action_type for filtering
ALTER TABLE `audit_logs` ADD INDEX `idx_audit_logs_action_type` (`action_type`);

-- Composite index for common query patterns (user_id + created)
ALTER TABLE `audit_logs` ADD INDEX `idx_audit_logs_user_created` (`user_id`, `created` DESC);

-- Composite index for object type queries (obj_type + created)
ALTER TABLE `audit_logs` ADD INDEX `idx_audit_logs_obj_type_created` (`obj_type`, `created` DESC);

-- Composite index for action type queries (action_type + created)
ALTER TABLE `audit_logs` ADD INDEX `idx_audit_logs_action_created` (`action_type`, `created` DESC);


-- collection indexes
CREATE INDEX idx_eca_user_collection ON editor_collection_acl(user_id, collection_id);
CREATE INDEX idx_ecpa_user_collection ON editor_collection_project_acl(user_id, collection_id);
CREATE INDEX idx_collections_created_by ON editor_collections(created_by);
CREATE INDEX idx_collection_id ON editor_collection_projects(collection_id);


-- analytics
-- 2025/11/07
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