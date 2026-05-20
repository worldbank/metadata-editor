-- Global codelists
-- Apply: mysql ... < install/schema-codelists.sql
-- Reset existing dev DB: install/schema-codelists-drop.sql then this file

CREATE TABLE codelists (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  pid BIGINT NULL COMMENT 'Family head row id (latest version for agency+name); set on create',
  idno VARCHAR(191) NOT NULL,
  agency VARCHAR(64) NOT NULL,
  name VARCHAR(64) NOT NULL COMMENT 'SDMX maintainable id (NADA codelists.name)',
  version VARCHAR(32) NOT NULL,
  version_seq INT NOT NULL COMMENT 'Monotonic sequence within (agency, name)',
  title VARCHAR(255) NOT NULL COMMENT 'Human-readable list title',
  description TEXT NULL,
  uri VARCHAR(500) NULL,
  status ENUM('draft','active','locked','archived') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  changed_at TIMESTAMP NULL,
  created_by INT NULL,
  changed_by INT NULL,
  UNIQUE KEY uq_codelists_idno (idno),
  UNIQUE KEY uq_codelist_identity (agency, name, version),
  UNIQUE KEY uq_codelists_family_seq (agency, name, version_seq),
  KEY idx_codelists_agency (agency),
  KEY idx_codelists_pid (pid),
  KEY idx_codelists_status (status),
  KEY idx_codelists_created (created_at),
  CONSTRAINT fk_codelists_pid FOREIGN KEY (pid) REFERENCES codelists (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE codelist_labels (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  codelist_id BIGINT NOT NULL COMMENT 'FK -> codelists.id',
  language VARCHAR(10) NOT NULL,
  label VARCHAR(500) NOT NULL,
  description TEXT NULL,
  FOREIGN KEY (codelist_id) REFERENCES codelists(id) ON DELETE CASCADE,
  UNIQUE KEY uq_codelist_language (codelist_id, language),
  KEY idx_language (language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE codelist_items (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  codelist_id BIGINT NOT NULL COMMENT 'FK -> codelists.id',
  code VARCHAR(150) NOT NULL,
  parent_id BIGINT NULL,
  sort_order INT NULL,
  FOREIGN KEY (codelist_id) REFERENCES codelists(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES codelist_items(id) ON DELETE SET NULL,
  UNIQUE KEY uq_item_per_list (codelist_id, code),
  KEY idx_parent_id (parent_id),
  KEY idx_sort (codelist_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE codelist_items_labels (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  codelist_item_id BIGINT NOT NULL,
  language VARCHAR(10) NOT NULL,
  label VARCHAR(500) NOT NULL,
  description TEXT NULL,
  FOREIGN KEY (codelist_item_id) REFERENCES codelist_items(id) ON DELETE CASCADE,
  UNIQUE KEY uq_codelist_item_language (codelist_item_id, language),
  KEY idx_language (language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
