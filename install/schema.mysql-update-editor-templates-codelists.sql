-- Template ↔ global codelist bindings (synced from editor_templates.template JSON on save).
-- Source of truth remains template JSON; this table supports usage counts and delete protection.

CREATE TABLE `editor_templates_codelists` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `template_id` int NOT NULL COMMENT 'FK -> editor_templates.id',
  `field_path` varchar(500) NOT NULL COMMENT 'prop_key or dotted field key',
  `codelist_id` bigint NOT NULL COMMENT 'FK -> codelists.id',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_template_field` (`template_id`, `field_path`),
  KEY `idx_codelist_id` (`codelist_id`),
  CONSTRAINT `fk_editor_templates_codelists_template`
    FOREIGN KEY (`template_id`) REFERENCES `editor_templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_editor_templates_codelists_codelist`
    FOREIGN KEY (`codelist_id`) REFERENCES `codelists` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
