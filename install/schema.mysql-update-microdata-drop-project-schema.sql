-- Microdata stored document no longer includes the API request envelope (project-schema.json).
--
-- Preferred: Admin > Database Migrations (20260824100001_microdata_schema_drop_project_schema)
-- Manual: mysql ... < install/schema.mysql-update-microdata-drop-project-schema.sql
--
-- Safe to re-run: no-op when project-schema.json is already absent.

UPDATE metadata_schemas
SET schema_files = JSON_REMOVE(
    schema_files,
    JSON_UNQUOTE(JSON_SEARCH(schema_files, 'one', 'project-schema.json'))
  ),
  updated = UNIX_TIMESTAMP()
WHERE uid = 'microdata'
  AND JSON_SEARCH(schema_files, 'one', 'project-schema.json') IS NOT NULL;
