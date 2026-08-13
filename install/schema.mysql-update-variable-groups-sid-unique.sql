-- One variable-group tree per project.
-- Safe to re-run: duplicate unique key name is skipped by MY_Migration.

DELETE g1 FROM editor_variable_groups g1
INNER JOIN editor_variable_groups g2
  ON g1.sid = g2.sid AND g1.id < g2.id
WHERE g1.sid IS NOT NULL;

ALTER TABLE editor_variable_groups
  ADD UNIQUE KEY uidx_editor_variable_groups_sid (sid);
