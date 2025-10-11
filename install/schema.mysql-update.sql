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

# geospatial features

CREATE TABLE `geospatial_features` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sid` int NOT NULL,
  `code` varchar(100) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
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


