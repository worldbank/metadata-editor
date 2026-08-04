-- Microdata registry: validate against compositional survey-schema.json (ddi-schema.json + refs)
--
-- Preferred: Admin > Database Migrations (20260804000001_microdata_survey_schema_filename)
-- Manual: mysql ... < install/schema.mysql-update-microdata-survey-schema.sql
--
UPDATE metadata_schemas
SET filename = 'survey-schema.json'
WHERE uid = 'microdata' AND filename = 'microdata-schema.json';
