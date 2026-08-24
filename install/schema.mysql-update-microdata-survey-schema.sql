-- Microdata registry: validate against compositional survey-schema.json (DDI document + refs).
-- Request-only project-schema.json is not part of the stored document (see 20260824100001).
--
-- Preferred: Admin > Database Migrations (20260804000001_microdata_survey_schema_filename)
-- Manual: mysql ... < install/schema.mysql-update-microdata-survey-schema.sql
--
UPDATE metadata_schemas
SET filename = 'survey-schema.json'
WHERE uid = 'microdata' AND filename = 'microdata-schema.json';
