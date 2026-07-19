<?php

/**
 * Tags model
 *
 * Manages tags (vocabulary) and project–tag assignments.
 * Tables: tags, project_tags
 *
 */
class Tags_model extends CI_Model {

    private $table_tags = 'tags';
    private $table_project_tags = 'project_tags';

    private $tag_fields = array('tag', 'is_core');

    public function __construct()
    {
        parent::__construct();
    }


    /**
     * Get a tag by ID
     *
     * @param int $id Tag ID
     * @return array|false Tag row or false
     */
    public function get_by_id($id)
    {
        $this->db->where('id', (int) $id);
        $row = $this->db->get($this->table_tags)->row_array();
        return $row ?: false;
    }

    /**
     * Get tags by IDs (for facet display when only selected tags are needed).
     * Returns array of { id, title } for facet compatibility.
     *
     * @param array $ids Tag IDs
     * @return array
     */
    public function get_tags_by_ids($ids)
    {
        if (!is_array($ids) || empty($ids)) {
            return array();
        }
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids);
        if (empty($ids)) {
            return array();
        }
        $this->db->select('id, tag AS title');
        $this->db->from($this->table_tags);
        $this->db->where_in('id', $ids);
        $this->db->order_by('tag', 'ASC');
        return $this->db->get()->result_array();
    }

    /**
     * Get all tags with optional filters (paginated).
     *
     * @param array $filters Optional: is_core (0|1), search (substring on tag)
     * @param int|null $limit Limit (default 50; null = 50)
     * @param int $offset Offset
     * @return array Keys: total (int), tags (array), offset (int), limit (int)
     */
    public function get_all($filters = array(), $limit = 50, $offset = 0)
    {
        $limit = $limit === null || $limit < 1 ? 50 : (int) $limit;
        $offset = (int) $offset;

        $this->db->from($this->table_tags);
        if (isset($filters['is_core']) && $filters['is_core'] !== null && $filters['is_core'] !== '') {
            $this->db->where('is_core', (int) $filters['is_core']);
        }
        if (!empty($filters['search'])) {
            $this->db->like('tag', $this->normalize_tag($filters['search']));
        }
        $total = $this->db->count_all_results();

        $this->db->from($this->table_tags);
        if (isset($filters['is_core']) && $filters['is_core'] !== null && $filters['is_core'] !== '') {
            $this->db->where('is_core', (int) $filters['is_core']);
        }
        if (!empty($filters['search'])) {
            $this->db->like('tag', $this->normalize_tag($filters['search']));
        }
        $this->db->order_by('tag', 'ASC');
        $this->db->limit($limit, $offset);
        $tags = $this->db->get()->result_array();

        return array(
            'total'  => $total,
            'tags'   => $tags,
            'offset' => $offset,
            'limit'  => $limit,
        );
    }

    /**
     * Create a new tag
     *
     * @param array $data Keys: tag (required), is_core (optional, default 0)
     * @return int|false Inserted tag ID or false
     */
    public function create($data)
    {
        $insert = array();
        foreach ($this->tag_fields as $field) {
            if (array_key_exists($field, $data)) {
                $insert[$field] = $field === 'tag' ? $this->normalize_tag($data[$field]) : (int) $data[$field];
            }
        }
        if (empty($insert['tag'])) {
            return false;
        }
        if (!isset($insert['is_core'])) {
            $insert['is_core'] = 0;
        }
        if ($this->db->insert($this->table_tags, $insert)) {
            return $this->db->insert_id();
        }
        return false;
    }

    /**
     * Update a tag
     *
     * @param int $id Tag ID
     * @param array $data Keys: tag, is_core
     * @return bool Success
     */
    public function update($id, $data)
    {
        $update = array();
        foreach ($this->tag_fields as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $field === 'tag' ? $this->normalize_tag($data[$field]) : (int) $data[$field];
            }
        }
        if (empty($update)) {
            return false;
        }
        $this->db->where('id', (int) $id);
        return $this->db->update($this->table_tags, $update);
    }

    /**
     * Delete a tag
     * 
     * Only deletes the tag if it is not used by any project.
     *
     * @param int $id Tag ID
     * @return bool Success
     */
    public function delete($id)
    {
        $id = (int) $id;

        //check if the tag is used by any project
        if ($this->check_project_tag_used($id)) {
            throw new Exception('TAG_IN_USE: Tag is in use and cannot be deleted.');
        }

        $this->db->where('tag_id', $id);
        $this->db->delete($this->table_project_tags);
        $this->db->where('id', $id);
        return $this->db->delete($this->table_tags);
    }

    /**
     * Get all tags with project count (paginated).
     * project_count = number of projects using the tag.
     *
     * @param array $filters Optional: is_core (0|1), search (substring on tag)
     * @param int|null $limit Limit (default 50)
     * @param int $offset Offset
     * @return array Keys: total (int), tags (array with id, tag, is_core, project_count), offset (int), limit (int)
     */
    public function get_all_with_counts($filters = array(), $limit = 50, $offset = 0)
    {
        $limit = $limit === null || $limit < 1 ? 50 : (int) $limit;
        $offset = (int) $offset;

        $sub_project = 'SELECT tag_id, COUNT(DISTINCT sid) AS project_count FROM ' . $this->db->dbprefix($this->table_project_tags) . ' GROUP BY tag_id';

        $this->db->from($this->table_tags . ' t');
        $this->db->join('(' . $sub_project . ') pc', 'pc.tag_id = t.id', 'left');
        if (isset($filters['is_core']) && $filters['is_core'] !== null && $filters['is_core'] !== '') {
            $this->db->where('t.is_core', (int) $filters['is_core']);
        }
        if (!empty($filters['search'])) {
            $this->db->like('t.tag', $this->normalize_tag($filters['search']));
        }
        $total = $this->db->count_all_results('', false);

        $this->db->reset_query();
        $this->db->select('t.id, t.tag, t.is_core, COALESCE(pc.project_count, 0) AS project_count');
        $this->db->from($this->table_tags . ' t');
        $this->db->join('(' . $sub_project . ') pc', 'pc.tag_id = t.id', 'left');
        if (isset($filters['is_core']) && $filters['is_core'] !== null && $filters['is_core'] !== '') {
            $this->db->where('t.is_core', (int) $filters['is_core']);
        }
        if (!empty($filters['search'])) {
            $this->db->like('t.tag', $this->normalize_tag($filters['search']));
        }
        $this->db->order_by('t.tag', 'ASC');
        $this->db->limit($limit, $offset);
        $q = $this->db->get();
        $tags = $q->result_array();

        foreach ($tags as &$row) {
            $row['project_count'] = (int) $row['project_count'];
        }

        return array(
            'total'  => $total,
            'tags'   => $tags,
            'offset' => $offset,
            'limit'  => $limit,
        );
    }

    /**
     * Delete all tags that are not used by any project.
     *
     * @return int Number of tags deleted
     */
    public function delete_unused()
    {
        $used = $this->db->select('tag_id')->from($this->table_project_tags)->get()->result_array();
        $used_ids = array_unique(array_map(function ($r) {
            return (int) $r['tag_id'];
        }, $used));

        $this->db->from($this->table_tags);
        if (!empty($used_ids)) {
            $this->db->where_not_in('id', $used_ids);
        }
        $to_delete = $this->db->get()->result_array();
        $deleted = 0;
        foreach ($to_delete as $row) {
            if ($this->delete($row['id'])) {
                $deleted++;
            }
        }
        return $deleted;
    }

    // -------------------------------------------------------------------------
    // Upsert & helpers
    // -------------------------------------------------------------------------

    /**
     * Insert or update a tag by tag string; return tag ID.
     * If tag exists: optionally update is_core and return id.
     * If not: insert and return id.
     *
     * @param string $tag Tag label (max 50 chars)
     * @param int $is_core 0 or 1 (used on insert; on update only if $update_existing is true)
     * @param bool $update_existing If true, update is_core when tag already exists
     * @return int|false Tag ID or false on failure
     */
    public function upsert($tag, $is_core = 0, $update_existing = false)
    {
        $tag = $this->normalize_tag($tag);
        if ($tag === '') {
            return false;
        }
        $is_core = (int) $is_core;

        $existing = $this->get_by_tag($tag);
        if ($existing) {
            if ($update_existing) {
                $this->update($existing['id'], array('is_core' => $is_core));
            }
            return (int) $existing['id'];
        }
        $id = $this->create(array('tag' => $tag, 'is_core' => $is_core));
        return $id ? $id : false;
    }

    /**
     * Get a tag row by tag string (case-insensitive match)
     *
     * @param string $tag Tag label
     * @return array|false Tag row or false
     */
    public function get_by_tag($tag)
    {
        $tag = $this->normalize_tag($tag);
        if ($tag === '') {
            return false;
        }
        $this->db->where('tag', $tag);
        $row = $this->db->get($this->table_tags)->row_array();
        return $row ?: false;
    }

    /**
     * Check if a tag exists by ID or by tag string
     *
     * @param int|string $id_or_tag Tag ID (int) or tag label (string)
     * @return bool
     */
    public function tag_exists($id_or_tag)
    {
        if (is_int($id_or_tag) || ctype_digit((string) $id_or_tag)) {
            $row = $this->get_by_id((int) $id_or_tag);
            return $row !== false;
        }
        return $this->get_by_tag($id_or_tag) !== false;
    }

    /**
     * Resolve an array of tag identifiers (mix of tag IDs and tag names) to a list of tag IDs.
     * Tag names are looked up case-insensitively. Invalid or missing tags are skipped.
     * Duplicates are removed; order is preserved.
     *
     * @param array $tags_or_ids Array of tag IDs (int) and/or tag names (string)
     * @return array List of unique tag IDs
     */
    public function resolve_to_tag_ids($tags_or_ids)
    {
        if (!is_array($tags_or_ids)) {
            return array();
        }
        $ids = array();
        foreach ($tags_or_ids as $item) {
            if (is_int($item) || ctype_digit((string) $item)) {
                $id = (int) $item;
                if ($this->tag_exists($id) && !in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            } else {
                $row = $this->get_by_tag($item);
                if ($row !== false) {
                    $id = (int) $row['id'];
                    if (!in_array($id, $ids, true)) {
                        $ids[] = $id;
                    }
                }
            }
        }
        return $ids;
    }

    /**
     * Get all tags assigned to a project
     *
     * @param int $sid Project ID (editor_projects.id)
     * @return array List of tag rows (id, tag, is_core)
     */
    public function get_tags_by_project($sid)
    {
        $sid = (int) $sid;
        $this->db->select('t.id, t.tag, t.is_core');
        $this->db->from($this->table_tags . ' t');
        $this->db->join($this->table_project_tags . ' pt', 'pt.tag_id = t.id');
        $this->db->where('pt.sid', $sid);
        $this->db->order_by('t.tag', 'ASC');
        return $this->db->get()->result_array();
    }

    /**
     * Get tags facet for project search: tags with project counts (only projects user can access).
     * Returns array of { id, title, count } for use in facets API.
     *
     * @param int|null $user_id User ID (null = no filter, count all projects)
     * @return array
     */
    public function get_tags_facet($user_id = null)
    {
        $this->load->library('Editor_acl');
        $user = $this->ion_auth->get_user($user_id);
        $is_admin = $user && $this->editor_acl->user_sees_all_projects($user);


        $this->db->select('t.id, t.tag AS title, COUNT(DISTINCT pt.sid) AS count');
        $this->db->from($this->table_tags . ' t');
        $this->db->join($this->table_project_tags . ' pt', 'pt.tag_id = t.id');
        $this->db->join('editor_projects p', 'p.id = pt.sid');
        $this->db->where('p.pid IS NULL');

        if ($user_id !== null && $user_id !== '') {
            
            
            if (!$is_admin) {
                $uid = (int) $user_id;
                $this->load->helper('collection_acl');
                $subquery = 'SELECT sid FROM editor_project_owners WHERE user_id = ' . $uid;
                $coll_query = collection_acl_sql_project_ids_for_user($uid);
                $this->db->where('(p.created_by = ' . $uid . ' OR p.id IN (' . $subquery . ') OR p.id IN (' . $coll_query . '))', null, false);
            }
        }

        $this->db->group_by(array('t.id', 't.tag'));
        $this->db->order_by('t.tag', 'ASC');
        $rows = $this->db->get()->result_array();
        foreach ($rows as &$row) {
            $row['count'] = (int) $row['count'];
        }
        return $rows;
    }

    /**
     * Get tags for multiple projects (batch). Returns array keyed by project id.
     *
     * @param array $sids Project IDs (editor_projects.id)
     * @return array [ sid => [ tag rows (id, tag, is_core), ... ], ... ]
     */
    public function get_tags_by_projects($sids)
    {
        if (empty($sids) || !is_array($sids)) {
            return array();
        }
        $sids = array_map('intval', $sids);
        $sids = array_filter($sids);
        if (empty($sids)) {
            return array();
        }
        $this->db->select('t.id, t.tag,  pt.sid');
        $this->db->from($this->table_tags . ' t');
        $this->db->join($this->table_project_tags . ' pt', 'pt.tag_id = t.id');
        $this->db->where_in('pt.sid', $sids);
        $this->db->order_by('t.tag', 'ASC');
        $rows = $this->db->get()->result_array();
        $output = array();
        foreach ($sids as $sid) {
            $output[$sid] = array();
        }
        foreach ($rows as $row) {
            $sid = (int) $row['sid'];
            unset($row['sid']);
            $output[$sid][] = $row;
        }
        return $output;
    }

    
    /**
     * 
     * Check if a tag is used by any project.
     * 
     * @param int $tag_id Tag ID
     * @return bool
     */
        public function check_project_tag_used($tag_id)
        {
            $this->db->select('sid')->from($this->table_project_tags)->where('tag_id', (int) $tag_id);
            $result = $this->db->get()->result_array();
            return count($result) > 0;
        }

    /**
     * Check if a tag is already assigned to a project.
     *
     * @param int $sid Project ID
     * @param int $tag_id Tag ID
     * @return bool
     */
    public function check_project_tag_exists($sid, $tag_id)
    {
        $this->db->where('sid', (int) $sid);
        $this->db->where('tag_id', (int) $tag_id);
        return $this->db->count_all_results($this->table_project_tags) > 0;
    }

    /**
     * Assign a tag to a project (idempotent)
     *
     * @param int $sid Project ID
     * @param int $tag_id Tag ID
     * @return bool Success (true if inserted or already assigned)
     */
    public function add_tag_to_project($sid, $tag_id)
    {
        $sid = (int) $sid;
        $tag_id = (int) $tag_id;
        $this->db->where('sid', $sid);
        $this->db->where('tag_id', $tag_id);
        if ($this->db->count_all_results($this->table_project_tags) > 0) {
            return true;
        }
        return $this->db->insert($this->table_project_tags, array('sid' => $sid, 'tag_id' => $tag_id));
    }

    /**
     * Add tag IDs to a project (only existing tags; invalid IDs are skipped).
     *
     * @param int $sid Project ID
     * @param array $tag_ids Array of tag IDs (integers)
     * @return array List of tag IDs that were valid and assigned
     */
    public function add_tag_ids_to_project($sid, $tag_ids)
    {
        $sid = (int) $sid;
        if (!is_array($tag_ids)) {
            return array();
        }
        $valid = array();
        foreach ($tag_ids as $id) {
            $id = (int) $id;
            if ($id > 0 && $this->tag_exists($id) && !in_array($id, $valid, true)) {
                $valid[] = $id;
                $this->add_tag_to_project($sid, $id);
            }
        }
        return $valid;
    }

    /**
     * Resolve an array of tag IDs and/or tag names to unique tag IDs; create tags by name if needed.
     * Then add each to the project.
     *
     * @param int $sid Project ID
     * @param array $tags Array of tag IDs (int) and/or tag names (string), e.g. [12, 15, "A new tag"]
     * @return array List of tag IDs that were assigned (unique, order preserved)
     */
    public function add_tags_to_project($sid, $tags)
    {
        $sid = (int) $sid;
        if (!is_array($tags)) {
            return array();
        }
        $tag_ids = array();
        foreach ($tags as $item) {
            if (is_int($item) || ctype_digit((string) $item)) {
                $id = (int) $item;
                if ($id > 0 && $this->tag_exists($id) && !in_array($id, $tag_ids, true)) {
                    $tag_ids[] = $id;
                }
            } else {
                $name = trim((string) $item);
                if ($name !== '') {
                    $id = $this->upsert($name, 0, false);
                    if ($id !== false && !in_array($id, $tag_ids, true)) {
                        $tag_ids[] = $id;
                    }
                }
            }
        }
        foreach ($tag_ids as $tag_id) {
            $this->add_tag_to_project($sid, $tag_id);
        }
        return $tag_ids;
    }

    /**
     * Remove a tag from a project
     *
     * @param int $sid Project ID
     * @param int $tag_id Tag ID
     * @return bool Success
     */
    public function remove_tag_from_project($sid, $tag_id)
    {
        $this->db->where('sid', (int) $sid);
        $this->db->where('tag_id', (int) $tag_id);
        $result=$this->db->delete($this->table_project_tags);

        if ($result) {
            // Delete tag if it is not used by any project
            $this->delete($tag_id);
        }
        return $result;
    }

    /**
     * Normalize tag: trim, normalize whitespace, strip special chars, max 50 chars.
     * Preserves accented characters (é, ü, ñ, etc.) and all Unicode letters.
     * Multiple spaces are collapsed to single space. Parenthesis, brackets, quotes, &lt; &gt;, etc. are removed.
     *
     * @param string $tag
     * @return string
     */
    private function normalize_tag($tag)
    {
        $tag = trim((string) $tag);
        if ($tag === '') {
            return '';
        }
                
        // Normalize multiple spaces/whitespace to single space
        $tag = preg_replace('/\s+/u', ' ', $tag);
        // Keep letters (including accented), digits, hyphen, underscore, and space
        $tag = preg_replace('/[^\p{L}0-9_\-\s]/u', '', $tag);
        $tag = trim($tag);

        if ($tag === '') {
            return '';
        }

        if (strlen($tag) > 50) {
            return substr($tag, 0, 50);
        }
        
        return $tag;
    }
}
