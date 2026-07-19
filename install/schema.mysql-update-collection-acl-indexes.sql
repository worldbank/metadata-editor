-- Indexes for collection ACL inheritance and project-via-collection access.
-- Safe to re-run: duplicate index names are skipped by MY_Migration.

-- Already present on fresh installs via schema.mysql.sql; ensure PROD upgrades get them.
CREATE INDEX idx_eca_user_collection ON editor_collection_acl (user_id, collection_id);

CREATE INDEX idx_ecpa_user_collection ON editor_collection_project_acl (user_id, collection_id);

CREATE INDEX idx_collections_created_by ON editor_collections (created_by);

CREATE INDEX idx_collection_id ON editor_collection_projects (collection_id);

-- Needed for project access checks / EXISTS by project SID
CREATE INDEX idx_ecp_sid ON editor_collection_projects (sid);

-- Covering index for collection → projects membership scans
CREATE INDEX idx_ecp_collection_sid ON editor_collection_projects (collection_id, sid);

-- Closure lookups: child → ancestors (JOIN t.child_id = ecp.collection_id)
CREATE INDEX idx_ect_child_parent ON editor_collections_tree (child_id, parent_id, depth);
