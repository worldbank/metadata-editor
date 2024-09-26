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
) ENGINE=InnoDB AUTO_INCREMENT=1;



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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3;
