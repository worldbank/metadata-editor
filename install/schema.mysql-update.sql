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

