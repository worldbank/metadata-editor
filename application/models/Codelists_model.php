<?php

/**
 * 
 * Global Codelists Model
 * 
 * Manages global codelists with support for codes and multilingual labels
 * 
 */
class Codelists_model extends CI_Model {

    const STATUS_DRAFT = 'draft';
    const STATUS_ACTIVE = 'active';
    const STATUS_LOCKED = 'locked';
    const STATUS_ARCHIVED = 'archived';

    private $table_codelists = 'codelists';
    private $table_items = 'codelist_items';
    private $table_item_labels = 'codelist_items_labels';
    /** Header translations: multilingual name/description per codelist (FK codelist_id → codelists.id) */
    private $table_codelist_translations = 'codelist_labels';

    private $codelist_fields = array(
        'idno',
        'agency',
        'name',
        'version',
        'version_seq',
        'title',
        'description',
        'uri',
        'status',
        'created_at',
        'changed_at',
        'created_by',
        'changed_by'
    );

    /**
     * @return string[]
     */
    public function valid_statuses()
    {
        return array(
            self::STATUS_DRAFT,
            self::STATUS_ACTIVE,
            self::STATUS_LOCKED,
            self::STATUS_ARCHIVED,
        );
    }

    /**
     * @param string|null $status
     * @return string
     */
    public function normalize_status($status)
    {
        $status = strtolower(trim((string) $status));
        if ($status === '' || !in_array($status, $this->valid_statuses(), true)) {
            return self::STATUS_ACTIVE;
        }

        return $status;
    }

    /**
     * Whether header, codes, and translations may be edited.
     *
     * @param array|string $row_or_status Codelist row or status string
     * @return bool
     */
    public function is_content_mutable($row_or_status)
    {
        $status = is_array($row_or_status)
            ? $this->normalize_status(isset($row_or_status['status']) ? $row_or_status['status'] : null)
            : $this->normalize_status($row_or_status);

        return in_array($status, array(self::STATUS_DRAFT, self::STATUS_ACTIVE), true);
    }

    /**
     * Whether the codelist row may be deleted.
     *
     * @param array|string $row_or_status
     * @return bool
     */
    public function is_deletable($row_or_status)
    {
        return $this->is_content_mutable($row_or_status);
    }

    /**
     * @param string $from
     * @param string $to
     * @param bool $is_admin
     * @return bool
     */
    public function is_status_transition_allowed($from, $to, $is_admin = false)
    {
        $from = $this->normalize_status($from);
        $to = $this->normalize_status($to);
        if ($from === $to) {
            return true;
        }
        if ($is_admin) {
            return true;
        }
        // Non-admins may only move between draft and active.
        return in_array($from, array(self::STATUS_DRAFT, self::STATUS_ACTIVE), true)
            && in_array($to, array(self::STATUS_DRAFT, self::STATUS_ACTIVE), true);
    }

    /**
     * @param int $codelist_pk
     * @return array Codelist row
     * @throws Exception
     */
    public function require_content_mutable($codelist_pk)
    {
        $row = $this->get_by_id($codelist_pk);
        if (!$row) {
            throw new Exception('Codelist not found');
        }
        if (!$this->is_content_mutable($row)) {
            throw new Exception('Codelist is ' . $row['status'] . ' and cannot be modified');
        }

        return $row;
    }

    /**
     * @param int $codelist_pk
     * @return array
     * @throws Exception
     */
    public function require_deletable($codelist_pk)
    {
        $row = $this->get_by_id($codelist_pk);
        if (!$row) {
            throw new Exception('Codelist not found');
        }
        if (!$this->is_deletable($row)) {
            throw new Exception('Codelist is ' . $row['status'] . ' and cannot be deleted');
        }

        return $row;
    }

    /**
     * @param int $code_id
     * @return int
     */
    public function get_codelist_pk_for_code($code_id)
    {
        $this->db->select('codelist_id');
        $this->db->where('id', (int) $code_id);
        $row = $this->db->get($this->table_items)->row_array();

        return $row ? (int) $row['codelist_id'] : 0;
    }

    private $item_fields = array(
        'codelist_id',
        'code',
        'parent_id',
        'sort_order'
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('date');
    }

    /**
     * 
     * Get all codelists with optional filters
     * 
     * @param array $filters - Optional filters (agency, search)
     * @param int $offset - Offset for pagination (default: 0)
     * @param int $limit - Limit for pagination (default: null, returns all)
     * @param string $order_by - Order by field (default: 'created_at')
     * @param string $order_dir - Order direction (default: 'DESC')
     * @return array List of codelists
     * 
     */
    public function get_all($filters = array(), $offset = 0, $limit = null, $order_by = 'created_at', $order_dir = 'DESC')
    {
        $this->db->select('*');
        $this->db->from($this->table_codelists);

        // Apply filters
        if (isset($filters['agency']) && !empty($filters['agency'])) {
            $this->db->where('agency', $filters['agency']);
        }
        if (isset($filters['search']) && !empty($filters['search'])) {
            $this->db->group_start();
            $this->db->like('title', $filters['search']);
            $this->db->or_like('name', $filters['search']);
            $this->db->or_like('idno', $filters['search']);
            $this->db->or_like('agency', $filters['search']);
            $this->db->or_like('description', $filters['search']);
            $this->db->group_end();
        }
        if (!empty($filters['exclude_archived'])) {
            $this->db->where('status !=', self::STATUS_ARCHIVED);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $this->db->where('status', $this->normalize_status($filters['status']));
        }

        if ($order_by === 'created_at') {
            $this->db->order_by('agency', 'ASC');
            $this->db->order_by('name', 'ASC');
            $this->db->order_by('version_seq', 'ASC');
            $this->db->order_by('id', 'ASC');
        } else {
            $this->db->order_by($order_by, $order_dir);
        }

        if ($limit !== null && $limit > 0) {
            $this->db->limit($limit, $offset);
        }

        return $this->db->get()->result_array();
    }

    /**
     * 
     * Get a single codelist by ID
     * 
     * @param int $id - Codelist ID
     * @return array|false Codelist or false if not found
     * 
     */
    public function get_by_id($id)
    {
        $this->db->where('id', $id);
        $codelist = $this->db->get($this->table_codelists)->row_array();

        if (!$codelist) {
            return false;
        }

        return $codelist;
    }

    /**
     * Get a codelist by SDMX identity (agency + maintainable name + version).
     *
     * @param string $agency
     * @param string $name SDMX maintainable id (codelists.name)
     * @param string $version
     * @return array|false
     */
    public function get_by_identity($agency, $name, $version)
    {
        $agency = trim((string) $agency);
        $name = trim((string) $name);
        $version = $version === null ? '' : trim((string) $version);

        if ($name === '') {
            return false;
        }

        if ($version === '') {
            $this->db->where('agency', $agency);
            $this->db->where('name', $name);
            $this->db->order_by('version_seq', 'DESC');
            $this->db->order_by('id', 'DESC');
            $this->db->limit(1);
            $codelist = $this->db->get($this->table_codelists)->row_array();
        } else {
            $this->db->where('agency', $agency);
            $this->db->where('name', $name);
            $this->db->where('version', $version);
            $codelist = $this->db->get($this->table_codelists)->row_array();
        }

        if (!$codelist) {
            return false;
        }

        return $codelist;
    }

    /**
     * DSD components bound to this codelist row.
     *
     * @param int $codelist_id
     * @return int
     */
    public function count_data_structure_components_by_codelist($codelist_id)
    {
        if (!$this->db->table_exists('data_structure_components')) {
            return 0;
        }
        $codelist_id = (int) $codelist_id;
        if ($codelist_id <= 0) {
            return 0;
        }
        $this->db->where('codelist_id', $codelist_id);
        return (int) $this->db->count_all_results('data_structure_components');
    }

    /**
     * DSD components referencing any version for (agency, name).
     *
     * @param string $agency
     * @param string $name
     * @return int
     */
    public function count_data_structure_components_by_codelist_family($agency, $name)
    {
        if (!$this->db->table_exists('data_structure_components')) {
            return 0;
        }
        $agency = trim((string) $agency);
        $name = trim((string) $name);
        if ($agency === '' || $name === '') {
            return 0;
        }
        $sql = 'SELECT COUNT(*) AS n FROM data_structure_components WHERE codelist_id IN '
            . '(SELECT id FROM codelists WHERE agency = ? AND name = ?)';
        $q = $this->db->query($sql, array($agency, $name));
        if (!$q) {
            return 0;
        }
        $r = $q->row_array();
        return isset($r['n']) ? (int) $r['n'] : 0;
    }

    /**
     * @param string $agency
     * @param string $name
     * @return int
     */
    public function get_next_version_seq($agency, $name)
    {
        $agency = trim((string) $agency);
        $name = trim((string) $name);
        $row = $this->db->select_max('version_seq')
            ->from($this->table_codelists)
            ->where('agency', $agency)
            ->where('name', $name)
            ->get()
            ->row_array();
        $max = isset($row['version_seq']) ? (int) $row['version_seq'] : 0;
        return $max + 1;
    }

    /**
     * All version rows for (agency, name), ordered by version_seq.
     *
     * @param string      $name
     * @param string|null $agency
     * @return array
     */
    public function get_codelist_versions($name, $agency = null)
    {
        $agency = ($agency === null || $agency === '') ? self::NADA_DEFAULT_AGENCY : trim((string) $agency);
        $name = trim((string) $name);
        if ($name === '') {
            return array();
        }
        $this->db->where('name', $name);
        $this->db->where('agency', $agency);
        $this->db->order_by('version_seq', 'ASC');
        $this->db->order_by('id', 'ASC');
        return $this->db->get($this->table_codelists)->result_array();
    }

    /**
     * One row per codelist family (family head: id = pid), with versions_count.
     *
     * @param array $filters agency, search, status, exclude_archived
     * @param bool  $with_counts item_count, dsd_component_count (by family)
     * @return array
     */
    public function get_all_collapsed($filters = array(), $with_counts = false)
    {
        $this->db->select(
            'c.*, (SELECT COUNT(*) FROM codelists v WHERE v.pid = c.id) AS versions_count',
            false
        );
        $this->db->from($this->table_codelists . ' c');
        $this->db->where('c.id = c.pid', null, false);
        $this->_apply_catalog_filters($filters, 'c.');
        $this->db->order_by('c.agency', 'ASC');
        $this->db->order_by('c.name', 'ASC');
        $rows = $this->db->get()->result_array();
        if ($with_counts && !empty($rows)) {
            $this->_attach_item_counts($rows);
            $this->_attach_dsd_component_counts($rows, true);
        }
        return $rows;
    }

    /**
     * Paginated catalogue: flat (all versions) or collapsed (family heads).
     *
     * @param array $options page, per_page, search, flat, with_counts, agency, status, exclude_archived
     * @return array{ rows: array, total: int, page: int, per_page: int }
     */
    public function get_codelists_paged(array $options = array())
    {
        $page = isset($options['page']) ? max(1, (int) $options['page']) : 1;
        $perPage = isset($options['per_page']) ? (int) $options['per_page'] : 50;
        if ($perPage < 1) {
            $perPage = 50;
        }
        if ($perPage > 200) {
            $perPage = 200;
        }
        $offset = ($page - 1) * $perPage;
        $filters = array();
        if (!empty($options['agency'])) {
            $filters['agency'] = $options['agency'];
        }
        if (isset($options['search'])) {
            $filters['search'] = $options['search'];
        }
        if (!empty($options['exclude_archived'])) {
            $filters['exclude_archived'] = true;
        }
        if (isset($options['status']) && $options['status'] !== '') {
            $filters['status'] = $options['status'];
        }
        $withCounts = !empty($options['with_counts']);
        $flat = !empty($options['flat']);

        if ($flat) {
            return $this->_get_codelists_flat_paged($page, $perPage, $offset, $filters, $withCounts);
        }
        return $this->_get_codelists_collapsed_paged($page, $perPage, $offset, $filters, $withCounts);
    }

    /**
     * @param array  $filters
     * @param string $alias e.g. 'c.' or ''
     */
    private function _apply_catalog_filters(array $filters, $alias = '')
    {
        $p = $alias === '' ? '' : rtrim((string) $alias, '.') . '.';
        if (isset($filters['agency']) && $filters['agency'] !== '') {
            $this->db->where($p . 'agency', $filters['agency']);
        }
        if (isset($filters['search']) && trim((string) $filters['search']) !== '') {
            $search = trim((string) $filters['search']);
            $this->db->group_start();
            $this->db->like($p . 'title', $search);
            $this->db->or_like($p . 'name', $search);
            $this->db->or_like($p . 'idno', $search);
            $this->db->or_like($p . 'agency', $search);
            $this->db->or_like($p . 'description', $search);
            if (ctype_digit($search)) {
                $this->db->or_where($p . 'id', (int) $search);
            }
            $this->db->group_end();
        }
        if (!empty($filters['exclude_archived'])) {
            $this->db->where($p . 'status !=', self::STATUS_ARCHIVED);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $this->db->where($p . 'status', $this->normalize_status($filters['status']));
        }
    }

    /**
     * @return array{ rows: array, total: int, page: int, per_page: int }
     */
    private function _get_codelists_flat_paged($page, $perPage, $offset, array $filters, $withCounts)
    {
        $this->db->from($this->table_codelists);
        $this->_apply_catalog_filters($filters, '');
        $total = (int) $this->db->count_all_results();

        $this->db->from($this->table_codelists);
        $this->_apply_catalog_filters($filters, '');
        $this->db->order_by('agency', 'ASC');
        $this->db->order_by('name', 'ASC');
        $this->db->order_by('version_seq', 'ASC');
        $this->db->order_by('id', 'ASC');
        $this->db->limit($perPage, $offset);
        $rows = $this->db->get()->result_array();

        if ($withCounts && !empty($rows)) {
            $this->_attach_item_counts($rows);
            $this->_attach_dsd_component_counts($rows, false);
        }

        return array(
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        );
    }

    /**
     * @return array{ rows: array, total: int, page: int, per_page: int }
     */
    private function _get_codelists_collapsed_paged($page, $perPage, $offset, array $filters, $withCounts)
    {
        $this->db->from($this->table_codelists . ' c');
        $this->db->where('c.id = c.pid', null, false);
        $this->_apply_catalog_filters($filters, 'c.');
        $total = (int) $this->db->count_all_results();

        $this->db->select(
            'c.*, (SELECT COUNT(*) FROM codelists v WHERE v.pid = c.id) AS versions_count',
            false
        );
        $this->db->from($this->table_codelists . ' c');
        $this->db->where('c.id = c.pid', null, false);
        $this->_apply_catalog_filters($filters, 'c.');
        $this->db->order_by('c.agency', 'ASC');
        $this->db->order_by('c.name', 'ASC');
        $this->db->limit($perPage, $offset);
        $rows = $this->db->get()->result_array();

        if ($withCounts && !empty($rows)) {
            $this->_attach_item_counts($rows);
            $this->_attach_dsd_component_counts($rows, true);
        }

        return array(
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        );
    }

    /**
     * @param array $rows
     * @param bool  $by_family
     */
    public function attach_catalog_counts(array &$rows, $by_family = false)
    {
        $this->_attach_item_counts($rows);
        $this->_attach_dsd_component_counts($rows, $by_family);
    }

    /**
     * @param array $rows
     */
    private function _attach_item_counts(array &$rows)
    {
        if (empty($rows)) {
            return;
        }
        $ids = array();
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
        }
        $ids = array_values(array_unique(array_filter($ids)));
        $item_map = array();
        if (!empty($ids)) {
            $this->db->select('codelist_id, COUNT(*) AS cnt', false);
            $this->db->from($this->table_items);
            $this->db->where_in('codelist_id', $ids);
            $this->db->group_by('codelist_id');
            $q = $this->db->get();
            if ($q) {
                foreach ($q->result_array() as $r) {
                    $item_map[(int) $r['codelist_id']] = (int) $r['cnt'];
                }
            }
        }
        foreach ($rows as &$row) {
            $id = (int) $row['id'];
            $row['item_count'] = isset($item_map[$id]) ? $item_map[$id] : 0;
        }
        unset($row);
    }

    /**
     * @param array $rows
     * @param bool  $by_family collapsed heads: count DSD refs across all versions
     */
    private function _attach_dsd_component_counts(array &$rows, $by_family)
    {
        if (empty($rows)) {
            return;
        }
        if (!$this->db->table_exists('data_structure_components')) {
            foreach ($rows as &$row) {
                $row['dsd_component_count'] = 0;
            }
            unset($row);
            return;
        }
        if ($by_family) {
            $pairs = array();
            foreach ($rows as $row) {
                $a = trim((string) (isset($row['agency']) ? $row['agency'] : ''));
                $n = trim((string) (isset($row['name']) ? $row['name'] : ''));
                if ($a !== '' && $n !== '') {
                    $pairs[$a . "\0" . $n] = array('agency' => $a, 'name' => $n);
                }
            }
            $family_map = array();
            if (!empty($pairs)) {
                $parts = array();
                $bind = array();
                foreach ($pairs as $p) {
                    $parts[] = '(c.agency = ? AND c.name = ?)';
                    $bind[] = $p['agency'];
                    $bind[] = $p['name'];
                }
                $sql = 'SELECT c.agency, c.name, COUNT(*) AS cnt FROM data_structure_components dsc '
                    . 'INNER JOIN codelists c ON c.id = dsc.codelist_id WHERE '
                    . implode(' OR ', $parts)
                    . ' GROUP BY c.agency, c.name';
                $q = $this->db->query($sql, $bind);
                if ($q) {
                    foreach ($q->result_array() as $r) {
                        $key = trim((string) (isset($r['agency']) ? $r['agency'] : '')) . "\0"
                            . trim((string) (isset($r['name']) ? $r['name'] : ''));
                        $family_map[$key] = (int) $r['cnt'];
                    }
                }
            }
            foreach ($rows as &$row) {
                $a = trim((string) (isset($row['agency']) ? $row['agency'] : ''));
                $n = trim((string) (isset($row['name']) ? $row['name'] : ''));
                $key = $a . "\0" . $n;
                $row['dsd_component_count'] = isset($family_map[$key]) ? $family_map[$key] : 0;
            }
            unset($row);
            return;
        }
        $ids = array();
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
        }
        $ids = array_values(array_unique(array_filter($ids)));
        $map = array();
        if (!empty($ids)) {
            $this->db->select('codelist_id, COUNT(*) AS cnt', false);
            $this->db->from('data_structure_components');
            $this->db->where_in('codelist_id', $ids);
            $this->db->group_by('codelist_id');
            $q = $this->db->get();
            if ($q) {
                foreach ($q->result_array() as $r) {
                    $map[(int) $r['codelist_id']] = (int) $r['cnt'];
                }
            }
        }
        foreach ($rows as &$row) {
            $id = (int) $row['id'];
            $row['dsd_component_count'] = isset($map[$id]) ? $map[$id] : 0;
        }
        unset($row);
    }

    /**
     * Count family heads (collapsed catalogue total).
     *
     * @param array $filters
     * @return int
     */
    public function count_collapsed($filters = array())
    {
        $this->db->from($this->table_codelists . ' c');
        $this->db->where('c.id = c.pid', null, false);
        $this->_apply_catalog_filters($filters, 'c.');
        return (int) $this->db->count_all_results();
    }

    /**
     * @param string $idno Catalogue handle (codelists.idno)
     * @return array|false
     */
    public function get_by_idno($idno)
    {
        $idno = trim((string) $idno);
        if ($idno === '') {
            return false;
        }
        $this->db->where('idno', $idno);

        return $this->db->get($this->table_codelists)->row_array();
    }

    /**
     * Deterministic idno: '{agency}_{name}_{version}'.
     *
     * @param string $agency
     * @param string $name SDMX maintainable id
     * @param string $version
     * @return string
     */
    public static function make_idno($agency, $name, $version)
    {
        $agency  = trim((string) $agency) !== '' ? trim((string) $agency) : self::NADA_DEFAULT_AGENCY;
        $version = trim((string) $version) !== '' ? trim((string) $version) : self::NADA_DEFAULT_VERSION;
        $name    = trim((string) $name);

        return $agency . '_' . $name . '_' . $version;
    }

    /**
     * 
     * Create a new codelist
     * 
     * @param array $data - Codelist data
     * @return int|false Inserted codelist ID or false on failure
     * 
     */
    public function create($data)
    {
        if (isset($data['access_mode'])) {
            unset($data['access_mode']);
        }

        $insert_data = array();
        foreach ($this->codelist_fields as $field) {
            if (isset($data[$field])) {
                $insert_data[$field] = $data[$field];
            }
        }

        $agency = isset($insert_data['agency']) ? trim((string) $insert_data['agency']) : '';
        $name = isset($insert_data['name']) ? trim((string) $insert_data['name']) : '';
        if ($agency === '' || $name === '') {
            return false;
        }

        if (!isset($insert_data['created_at'])) {
            $insert_data['created_at'] = date('Y-m-d H:i:s');
        }
        if (!isset($insert_data['status'])) {
            $insert_data['status'] = self::STATUS_ACTIVE;
        } else {
            $insert_data['status'] = $this->normalize_status($insert_data['status']);
        }

        if (!isset($insert_data['title']) || trim((string) $insert_data['title']) === '') {
            $insert_data['title'] = $name;
        }

        $insert_data = $this->_sanitize_codelist_insert_row($insert_data);
        $agency = isset($insert_data['agency']) ? trim((string) $insert_data['agency']) : '';
        $name = isset($insert_data['name']) ? trim((string) $insert_data['name']) : '';
        if ($agency === '' || $name === '') {
            return false;
        }

        if (empty($insert_data['idno'])) {
            $ver = isset($insert_data['version']) ? $insert_data['version'] : '';
            $insert_data['idno'] = self::make_idno($agency, $name, $ver);
        }

        if (!isset($insert_data['version_seq']) || (int) $insert_data['version_seq'] <= 0) {
            $insert_data['version_seq'] = $this->get_next_version_seq($agency, $name);
        } else {
            $insert_data['version_seq'] = (int) $insert_data['version_seq'];
        }

        $interim_pid = null;
        $latest_family_row = $this->_get_latest_codelist_version_row($agency, $name);
        if ($latest_family_row) {
            $interim_pid = !empty($latest_family_row['pid'])
                ? (int) $latest_family_row['pid']
                : (int) $latest_family_row['id'];
        }
        $insert_data['pid'] = $interim_pid;

        $own_trans = ! $this->db->trans_active();
        if ($own_trans) {
            $this->db->trans_begin();
        }
        $this->db->reset_query();
        if (!$this->db->insert($this->table_codelists, $insert_data)) {
            log_message('error', 'Codelists_model::create insert failed: ' . json_encode($this->db->error()));
            if ($own_trans) {
                $this->db->trans_rollback();
            }
            return false;
        }
        $id = $this->_resolve_inserted_codelist_id($insert_data);
        if ($id <= 0) {
            if ($own_trans) {
                $this->db->trans_rollback();
            }
            return false;
        }

        if (!$this->_set_codelist_family_head($agency, $name, $id)) {
            log_message('error', 'Codelists_model::create family head update failed: ' . json_encode($this->db->error()));
            if ($own_trans) {
                $this->db->trans_rollback();
            }
            return false;
        }

        if ($this->db->trans_status() === false) {
            if ($own_trans) {
                $this->db->trans_rollback();
            }
            return false;
        }
        if ($own_trans) {
            $this->db->trans_commit();
        }

        $this->seed_default_codelist_translation($id, $insert_data);
        return $id;
    }

    /**
     * Create a codelist or throw with the underlying database error when present.
     *
     * @param array  $data
     * @param string $messagePrefix
     * @return int
     * @throws Exception
     */
    public function create_or_throw(array $data, $messagePrefix = 'Failed to create codelist')
    {
        $id = $this->create($data);
        if (!$id) {
            throw $this->_codelist_create_exception($messagePrefix);
        }

        return (int) $id;
    }

    /**
     * Resolve the primary key of a row just inserted into codelists.
     *
     * @param array $insert_data
     * @return int
     */
    private function _resolve_inserted_codelist_id(array $insert_data)
    {
        $agency = isset($insert_data['agency']) ? trim((string) $insert_data['agency']) : '';
        $name = isset($insert_data['name']) ? trim((string) $insert_data['name']) : '';
        $version = isset($insert_data['version']) ? trim((string) $insert_data['version']) : '';

        if ($agency !== '' && $name !== '' && $version !== '') {
            $this->db->reset_query();
            $row = $this->db->select('id')
                ->from($this->table_codelists)
                ->where('agency', $agency)
                ->where('name', $name)
                ->where('version', $version)
                ->limit(1)
                ->get()
                ->row_array();
            if ($row && (int) $row['id'] > 0) {
                return (int) $row['id'];
            }
        }

        if (!empty($insert_data['idno'])) {
            $this->db->reset_query();
            $row = $this->db->select('id')
                ->from($this->table_codelists)
                ->where('idno', $insert_data['idno'])
                ->limit(1)
                ->get()
                ->row_array();
            if ($row && (int) $row['id'] > 0) {
                return (int) $row['id'];
            }
        }

        $candidate = (int) $this->db->insert_id();
        if ($candidate <= 0) {
            return 0;
        }

        $this->db->reset_query();
        $row = $this->db->select('id')
            ->from($this->table_codelists)
            ->where('id', $candidate)
            ->limit(1)
            ->get()
            ->row_array();

        return ($row && (int) $row['id'] > 0) ? (int) $row['id'] : 0;
    }

    /**
     * Latest version row for a codelist family, if any.
     *
     * @param string $agency
     * @param string $name
     * @return array|false
     */
    private function _get_latest_codelist_version_row($agency, $name)
    {
        $agency = trim((string) $agency);
        $name = trim((string) $name);
        if ($agency === '' || $name === '') {
            return false;
        }

        $this->db->reset_query();
        $row = $this->db->select('id, pid, version_seq')
            ->from($this->table_codelists)
            ->where('agency', $agency)
            ->where('name', $name)
            ->order_by('version_seq', 'DESC')
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        return $row ? $row : false;
    }

    /**
     * Point all versions of a codelist family at the newest head row.
     *
     * Self-referential pid FK requires the head row to reference itself before
     * sibling versions are repointed in a separate update.
     *
     * @param string $agency
     * @param string $name
     * @param int    $head_id
     * @return bool
     */
    private function _set_codelist_family_head($agency, $name, $head_id)
    {
        $head_id = (int) $head_id;
        $agency = trim((string) $agency);
        $name = trim((string) $name);
        if ($head_id <= 0 || $agency === '' || $name === '') {
            return false;
        }

        $this->db->reset_query();
        $this->db->where('id', $head_id);
        if (!$this->db->update($this->table_codelists, array('pid' => $head_id))) {
            return false;
        }
        if ((int) $this->db->affected_rows() !== 1) {
            return false;
        }

        $this->db->reset_query();
        $this->db->where('agency', $agency);
        $this->db->where('name', $name);
        $this->db->where('id <>', $head_id);
        return (bool) $this->db->update($this->table_codelists, array('pid' => $head_id));
    }

    /**
     * Truncate string columns to catalogue limits before insert/update.
     *
     * @param array $insert_data
     * @return array
     */
    private function _sanitize_codelist_insert_row(array $insert_data)
    {
        if (isset($insert_data['idno'])) {
            $insert_data['idno'] = $this->_sdmx_truncate(trim((string) $insert_data['idno']), 191);
        }
        if (isset($insert_data['agency'])) {
            $insert_data['agency'] = $this->_sdmx_truncate(trim((string) $insert_data['agency']), 64);
        }
        if (isset($insert_data['name'])) {
            $insert_data['name'] = $this->_sdmx_truncate(trim((string) $insert_data['name']), 64);
        }
        if (isset($insert_data['version'])) {
            $insert_data['version'] = $this->_sdmx_truncate(trim((string) $insert_data['version']), 32);
        }
        if (isset($insert_data['title'])) {
            $insert_data['title'] = $this->_sdmx_truncate(trim((string) $insert_data['title']), 255);
        }
        if (isset($insert_data['uri'])) {
            $insert_data['uri'] = $this->_sdmx_truncate(trim((string) $insert_data['uri']), 500);
        }

        return $insert_data;
    }

    /**
     * Last database error message for codelist create/import failures.
     *
     * @return string
     */
    private function _last_db_error_message()
    {
        $err = $this->db->error();
        if (is_array($err) && !empty($err['message'])) {
            return (string) $err['message'];
        }

        return '';
    }

    /**
     * @param string $prefix
     * @return Exception
     */
    private function _codelist_create_exception($prefix = 'Failed to create codelist')
    {
        $detail = $this->_last_db_error_message();
        if ($detail !== '') {
            return new Exception($prefix . ': ' . $detail);
        }

        return new Exception($prefix);
    }

    /**
     * Insert default English row in codelist_labels so item labels work without an extra user step.
     *
     * @param int $codelist_pk
     * @param array $insert_data Row just inserted into codelists
     */
    private function seed_default_codelist_translation($codelist_pk, $insert_data)
    {
        $name = isset($insert_data['title']) ? trim((string) $insert_data['title']) : '';
        if ($name === '' && isset($insert_data['name'])) {
            $name = trim((string) $insert_data['name']);
        }
        if ($name === '') {
            $name = '-';
        }
        if (function_exists('mb_substr')) {
            $label = mb_substr($name, 0, 500);
        } else {
            $label = substr($name, 0, 500);
        }

        $desc = null;
        if (isset($insert_data['description']) && $insert_data['description'] !== '' && $insert_data['description'] !== null) {
            $desc = $insert_data['description'];
        }

        if (!$this->set_codelist_translation($codelist_pk, 'en', $label, $desc)) {
            log_message('error', 'Codelists_model: failed to seed en translation for codelist id ' . $codelist_pk);
        }
    }

    /**
     * 
     * Update an existing codelist
     * 
     * @param int $id - Codelist ID
     * @param array $data - Codelist data to update
     * @return bool Success status
     * 
     */
    public function update($id, $data)
    {
        // Remove deprecated field name if present
        if (isset($data['access_mode'])) {
            unset($data['access_mode']);
        }
        
        // Filter to allowed fields
        $update_data = array();
        foreach ($this->codelist_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
            }
        }

        if (empty($update_data)) {
            return false;
        }
        if (isset($update_data['status'])) {
            $update_data['status'] = $this->normalize_status($update_data['status']);
        }

        $this->db->where('id', $id);
        return $this->db->update($this->table_codelists, $update_data);
    }

    /**
     * 
     * Delete a codelist (cascades to codes and labels)
     * 
     * @param int $id - Codelist ID
     * @return bool Success status
     * 
     */
    public function delete($id)
    {
        $id = (int) $id;
        $existing = $this->get_by_id($id);
        if (!$existing) {
            throw new Exception('Codelist not found');
        }

        $dsd_refs = $this->count_data_structure_components_by_codelist($id);
        if ($dsd_refs > 0) {
            throw new Exception(
                'This codelist cannot be deleted because it is referenced by '
                . $dsd_refs
                . ' data structure component'
                . ($dsd_refs === 1 ? '' : 's')
                . '. Remove the codelist from those components or delete the structures first.'
            );
        }

        $agency = (string) $existing['agency'];
        $name = (string) $existing['name'];
        $family = $this->db->select('id, version_seq')
            ->from($this->table_codelists)
            ->where('agency', $agency)
            ->where('name', $name)
            ->order_by('version_seq', 'DESC')
            ->order_by('id', 'DESC')
            ->get()
            ->result_array();

        $this->db->trans_begin();
        if (count($family) > 1) {
            $new_pid = null;
            foreach ($family as $row) {
                if ((int) $row['id'] !== $id) {
                    $new_pid = (int) $row['id'];
                    break;
                }
            }
            if ($new_pid) {
                $this->db->where('agency', $agency);
                $this->db->where('name', $name);
                $this->db->where('id <>', $id);
                $this->db->update($this->table_codelists, array('pid' => $new_pid));
            }
        }

        // Family head rows use pid = id (self-reference). Clear before DELETE so
        // fk_codelists_pid ON DELETE RESTRICT does not block removal.
        $this->db->where('id', $id);
        $this->db->update($this->table_codelists, array('pid' => null));

        $this->db->where('id', $id);
        $this->db->delete($this->table_codelists);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            log_message('error', 'Codelists_model::delete failed: ' . json_encode($this->db->error()));
            throw $this->_codelist_create_exception('Failed to delete codelist');
        }
        $this->db->trans_commit();
        return true;
    }

    /**
     * Translations for the codelist header (codelist_labels table).
     *
     * @param int $codelist_pk Primary key of codelists row
     * @return array
     */
    public function get_codelist_translations($codelist_pk)
    {
        $this->db->where('codelist_id', $codelist_pk);
        $this->db->order_by('language', 'ASC');
        return $this->db->get($this->table_codelist_translations)->result_array();
    }

    /**
     * @param int $translation_row_id
     * @return array|false
     */
    public function get_codelist_translation_by_id($translation_row_id)
    {
        $this->db->where('id', $translation_row_id);
        return $this->db->get($this->table_codelist_translations)->row_array();
    }

    /**
     * Upsert one header translation row (unique per codelist + language).
     *
     * @param int $codelist_pk
     * @return int|false Label row id
     */
    public function set_codelist_translation($codelist_pk, $language, $label, $description = null)
    {
        $this->db->where('codelist_id', $codelist_pk);
        $this->db->where('language', $language);
        $existing = $this->db->get($this->table_codelist_translations)->row_array();

        $row = array(
            'codelist_id' => $codelist_pk,
            'language' => $language,
            'label' => $label,
            'description' => $description
        );

        if ($existing) {
            $this->db->where('id', $existing['id']);
            if ($this->db->update($this->table_codelist_translations, $row)) {
                return (int) $existing['id'];
            }
            return false;
        }

        if ($this->db->insert($this->table_codelist_translations, $row)) {
            return (int) $this->db->insert_id();
        }

        return false;
    }

    /**
     * @param int $translation_row_id
     * @return bool
     */
    public function delete_codelist_translation($translation_row_id)
    {
        $this->db->where('id', $translation_row_id);
        return $this->db->delete($this->table_codelist_translations);
    }

    /**
     * Whether a language code is configured on the codelist (item labels must use only these).
     *
     * @param int $codelist_pk
     * @param string $language
     * @return bool
     */
    public function codelist_has_language($codelist_pk, $language)
    {
        $this->db->from($this->table_codelist_translations);
        $this->db->where('codelist_id', $codelist_pk);
        $this->db->where('language', $language);
        return $this->db->count_all_results() > 0;
    }

    /**
     * Get codes for a codelist with optional search and pagination.
     *
     * New optional params are appended so all existing callers remain unaffected.
     *
     * @param int         $codelist_id
     * @param string|null $language       Filter labels to this language (null = all)
     * @param bool        $include_labels Attach label rows to each code
     * @param string|null $search         LIKE filter on code value or any label text
     * @param int         $offset         Pagination offset (default 0)
     * @param int|null    $limit          Page size (null = no limit)
     * @return array
     */
    public function get_codes($codelist_id, $language = null, $include_labels = true, $search = null, $offset = 0, $limit = null)
    {
        $this->db->select('cc.*');
        $this->db->from($this->table_items . ' cc');
        $this->db->where('cc.codelist_id', $codelist_id);

        if ($search !== null && $search !== '') {
            $safe = $this->db->escape_like_str($search);
            $this->db->group_start();
            $this->db->like('cc.code', $search);
            // Match codes whose label text (any language) contains the search term.
            $this->db->or_where("cc.id IN (SELECT codelist_item_id FROM {$this->table_item_labels} WHERE label LIKE '%{$safe}%')", null, false);
            $this->db->group_end();
        }

        $this->db->order_by('cc.sort_order', 'ASC');
        $this->db->order_by('cc.code', 'ASC');

        if ($limit !== null && $limit > 0) {
            $this->db->limit((int) $limit, (int) $offset);
        }

        $codes = $this->db->get()->result_array();

        if ($include_labels && !empty($codes)) {
            $code_ids = array_column($codes, 'id');

            $this->db->select('*');
            $this->db->from($this->table_item_labels);
            $this->db->where_in('codelist_item_id', $code_ids);

            if ($language !== null) {
                $this->db->where('language', $language);
            }

            $labels = $this->db->get()->result_array();

            $labels_by_code = array();
            foreach ($labels as $label) {
                $labels_by_code[$label['codelist_item_id']][] = $label;
            }

            foreach ($codes as &$code) {
                $code['labels'] = isset($labels_by_code[$code['id']]) ? $labels_by_code[$code['id']] : array();
            }
        }

        return $codes;
    }

    /**
     * Count codes for a codelist, applying the same optional search filter as get_codes().
     *
     * @param int         $codelist_id
     * @param string|null $search
     * @return int
     */
    public function count_codes($codelist_id, $search = null)
    {
        $this->db->from($this->table_items . ' cc');
        $this->db->where('cc.codelist_id', $codelist_id);

        if ($search !== null && $search !== '') {
            $safe = $this->db->escape_like_str($search);
            $this->db->group_start();
            $this->db->like('cc.code', $search);
            $this->db->or_where("cc.id IN (SELECT codelist_item_id FROM {$this->table_item_labels} WHERE label LIKE '%{$safe}%')", null, false);
            $this->db->group_end();
        }

        return (int) $this->db->count_all_results();
    }

    /**
     * True when every non-empty code in $codes exists on the codelist (batched IN queries).
     *
     * @param int   $codelist_id
     * @param array $codes
     * @param int   $batch_size
     * @return bool
     */
    public function codelist_contains_all_codes($codelist_id, array $codes, $batch_size = 500)
    {
        $codelist_id = (int) $codelist_id;
        if ($codelist_id <= 0) {
            return false;
        }

        $unique = $this->_normalize_code_list($codes);
        if (empty($unique)) {
            return true;
        }

        $found = array();
        $batch_size = max(1, (int) $batch_size);
        $list = array_keys($unique);
        for ($i = 0; $i < count($list); $i += $batch_size) {
            $chunk = array_slice($list, $i, $batch_size);
            $this->db->reset_query();
            $this->db->select('code');
            $this->db->from($this->table_items);
            $this->db->where('codelist_id', $codelist_id);
            $this->db->where_in('code', $chunk);
            $rows = $this->db->get()->result_array();
            foreach ($rows as $row) {
                if (isset($row['code'])) {
                    $found[strtoupper((string) $row['code'])] = true;
                }
            }
        }

        foreach ($list as $code) {
            if (!isset($found[strtoupper($code)])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Latest family version whose codes are a superset of $codes (newest match wins).
     *
     * @param string $agency
     * @param string $name
     * @param array  $codes
     * @return array|false Codelist row
     */
    public function find_compatible_codelist_version($agency, $name, array $codes)
    {
        $agency = trim((string) $agency);
        $name = trim((string) $name);
        if ($agency === '' || $name === '') {
            return false;
        }

        $unique = $this->_normalize_code_list($codes);
        $needed = count($unique);
        if ($needed === 0) {
            return false;
        }

        $versions = $this->get_codelist_versions($name, $agency);
        if (empty($versions)) {
            return false;
        }

        usort($versions, function ($a, $b) {
            $sa = isset($a['version_seq']) ? (int) $a['version_seq'] : 0;
            $sb = isset($b['version_seq']) ? (int) $b['version_seq'] : 0;
            if ($sa !== $sb) {
                return $sb - $sa;
            }
            return (int) $b['id'] - (int) $a['id'];
        });

        foreach ($versions as $ver) {
            $cid = isset($ver['id']) ? (int) $ver['id'] : 0;
            if ($cid <= 0) {
                continue;
            }
            if ($this->count_codes($cid) < $needed) {
                continue;
            }
            if ($this->codelist_contains_all_codes($cid, array_keys($unique))) {
                return $ver;
            }
        }

        return false;
    }

    /**
     * Suggest the next SDMX version label for a codelist family.
     *
     * @param string $agency
     * @param string $name
     * @return string
     */
    public function suggest_next_version_string($agency, $name)
    {
        $agency = trim((string) $agency);
        $name = trim((string) $name);
        if ($agency === '' || $name === '') {
            return self::NADA_DEFAULT_VERSION;
        }

        $this->db->reset_query();
        $row = $this->db->select('version, version_seq')
            ->from($this->table_codelists)
            ->where('agency', $agency)
            ->where('name', $name)
            ->order_by('version_seq', 'DESC')
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        $candidate = $row
            ? $this->_bump_version_label(isset($row['version']) ? $row['version'] : self::NADA_DEFAULT_VERSION)
            : self::NADA_DEFAULT_VERSION;

        $guard = 0;
        while ($this->get_by_identity($agency, $name, $candidate) && $guard < 100) {
            $candidate = $this->_bump_version_label($candidate);
            $guard++;
        }

        return $candidate;
    }

    /**
     * @param string $version
     * @return string
     */
    private function _bump_version_label($version)
    {
        $ver = trim((string) $version);
        if ($ver !== '' && preg_match('/^(\d+)\.(\d+)$/', $ver, $m)) {
            return $m[1] . '.' . ((int) $m[2] + 1);
        }
        return self::NADA_DEFAULT_VERSION;
    }

    /**
     * @param array $codes
     * @return array<string, true> map of trimmed non-empty codes
     */
    private function _normalize_code_list(array $codes)
    {
        $unique = array();
        foreach ($codes as $code) {
            $c = trim((string) $code);
            if ($c !== '') {
                $unique[$c] = true;
            }
        }
        return $unique;
    }

    /**
     * 
     * Get a single code by ID
     * 
     * @param int $code_id - Code ID
     * @param string $language - Optional language for labels
     * @return array|false Code with labels or false if not found
     * 
     */
    public function get_code_by_id($code_id, $language = null)
    {
        $this->db->where('id', $code_id);
        $code = $this->db->get($this->table_items)->row_array();

        if (!$code) {
            return false;
        }

        // Get labels
        $this->db->where('codelist_item_id', $code_id);
        if ($language !== null) {
            $this->db->where('language', $language);
        }
        $code['labels'] = $this->db->get($this->table_item_labels)->result_array();

        return $code;
    }

    /**
     * 
     * Add a code to a codelist
     * 
     * @param int $codelist_id - Codelist ID
     * @param array $data - Code data
     * @return int|false Inserted code ID or false on failure
     * 
     */
    public function add_code($codelist_id, $data)
    {
        try {
            return $this->add_code_or_throw($codelist_id, $data);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Add a code or throw with a descriptive error (duplicate code, DB constraint, etc.).
     *
     * @param int    $codelist_id
     * @param array  $data
     * @param string $prefix
     * @return int
     * @throws Exception
     */
    public function add_code_or_throw($codelist_id, $data, $prefix = 'Failed to add code')
    {
        $insert_data = $this->_prepare_code_write_data($codelist_id, $data);
        $code = $insert_data['code'];

        if ($this->find_item_by_code($codelist_id, $code)) {
            throw new Exception('Code "' . $code . '" already exists in this codelist.');
        }

        if (!$this->db->insert($this->table_items, $insert_data)) {
            log_message('error', 'Codelists_model::add_code insert failed: ' . json_encode($this->db->error()));
            throw $this->_code_item_exception($prefix, $code);
        }

        return (int) $this->db->insert_id();
    }

    /**
     * 
     * Update an existing code
     * 
     * @param int $code_id - Code ID
     * @param array $data - Code data to update
     * @return bool Success status
     * 
     */
    public function update_code($code_id, $data)
    {
        try {
            return $this->update_code_or_throw($code_id, $data);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param int    $code_id
     * @param array  $data
     * @param string $prefix
     * @return bool
     * @throws Exception
     */
    public function update_code_or_throw($code_id, $data, $prefix = 'Failed to update code')
    {
        $code_id = (int) $code_id;
        $existing = $this->get_code_by_id($code_id);
        if (!$existing) {
            throw new Exception('Code not found');
        }

        $update_data = array();
        foreach ($this->item_fields as $field) {
            if (array_key_exists($field, $data) && $field !== 'codelist_id') {
                $update_data[$field] = $data[$field];
            }
        }

        if (empty($update_data)) {
            throw new Exception('No code fields to update');
        }

        if (array_key_exists('code', $update_data)) {
            $update_data['code'] = trim((string) $update_data['code']);
            if ($update_data['code'] === '') {
                throw new Exception('Code identifier is required');
            }
            if ($this->find_item_by_code((int) $existing['codelist_id'], $update_data['code'], $code_id)) {
                throw new Exception('Code "' . $update_data['code'] . '" already exists in this codelist.');
            }
        }

        $this->db->where('id', $code_id);
        if (!$this->db->update($this->table_items, $update_data)) {
            log_message('error', 'Codelists_model::update_code failed: ' . json_encode($this->db->error()));
            $codeLabel = array_key_exists('code', $update_data) ? $update_data['code'] : $existing['code'];
            throw $this->_code_item_exception($prefix, $codeLabel);
        }

        return true;
    }

    /**
     * Find a codelist item row by (codelist_id, code).
     *
     * @param int         $codelist_pk
     * @param string      $code
     * @param int|null    $exclude_id Optional item id to ignore (updates)
     * @return array|false
     */
    public function find_item_by_code($codelist_pk, $code, $exclude_id = null)
    {
        $codelist_pk = (int) $codelist_pk;
        $code = trim((string) $code);
        if ($codelist_pk <= 0 || $code === '') {
            return false;
        }

        $this->db->where('codelist_id', $codelist_pk);
        $this->db->where('code', $code);
        if ($exclude_id !== null && (int) $exclude_id > 0) {
            $this->db->where('id <>', (int) $exclude_id);
        }

        $row = $this->db->get($this->table_items)->row_array();
        return $row ? $row : false;
    }

    /**
     * @param int   $codelist_id
     * @param array $data
     * @return array
     * @throws Exception
     */
    private function _prepare_code_write_data($codelist_id, $data)
    {
        $insert_data = array('codelist_id' => (int) $codelist_id);
        foreach ($this->item_fields as $field) {
            if (isset($data[$field]) && $field !== 'codelist_id') {
                $insert_data[$field] = $data[$field];
            }
        }

        if (!isset($insert_data['code'])) {
            throw new Exception('Code identifier is required');
        }

        $insert_data['code'] = trim((string) $insert_data['code']);
        if ($insert_data['code'] === '') {
            throw new Exception('Code identifier is required');
        }

        return $insert_data;
    }

    /**
     * @param string      $prefix
     * @param string|null $code
     * @return Exception
     */
    private function _code_item_exception($prefix, $code = null)
    {
        $detail = $this->_last_db_error_message();
        if ($detail !== '') {
            if (stripos($detail, 'Duplicate') !== false || stripos($detail, 'uq_item_per_list') !== false) {
                $label = ($code !== null && trim((string) $code) !== '') ? trim((string) $code) : 'value';
                return new Exception('Code "' . $label . '" already exists in this codelist.');
            }

            return new Exception($prefix . ': ' . $detail);
        }

        if ($code !== null && trim((string) $code) !== '') {
            return new Exception($prefix . ' (code: ' . trim((string) $code) . ')');
        }

        return new Exception($prefix);
    }

    /**
     * 
     * Delete a code (cascades to labels)
     * 
     * @param int $code_id - Code ID
     * @return bool Success status
     * 
     */
    public function delete_code($code_id)
    {
        // Get codelist_id before deletion
        $code = $this->get_code_by_id($code_id);
        if (!$code) {
            return false;
        }
        $codelist_id = $code['codelist_id'];

        $this->db->where('id', $code_id);
        $result = $this->db->delete($this->table_items);

        return $result;
    }

    /**
     * 
     * Add or update a label for a code
     * 
     * @param int $code_id - Code ID
     * @param string $language - Language code
     * @param string $label - Label text
     * @param string $description - Optional description
     * @return int|false Label ID or false on failure
     * 
     */
    public function set_code_label($code_id, $language, $label, $description = null)
    {
        // Check if label exists
        $this->db->where('codelist_item_id', $code_id);
        $this->db->where('language', $language);
        $existing = $this->db->get($this->table_item_labels)->row_array();

        $label_data = array(
            'codelist_item_id' => $code_id,
            'language' => $language,
            'label' => $label,
            'description' => $description
        );

        if ($existing) {
            // Update existing
            $this->db->where('id', $existing['id']);
            if ($this->db->update($this->table_item_labels, $label_data)) {
                return $existing['id'];
            }
        } else {
            // Insert new
            if ($this->db->insert($this->table_item_labels, $label_data)) {
                return $this->db->insert_id();
            }
        }

        return false;
    }

    /**
     * 
     * Delete a label for a code
     * 
     * @param int $label_id - Label ID
     * @return bool Success status
     * 
     */
    public function delete_code_label($label_id)
    {
        $this->db->where('id', $label_id);
        return $this->db->delete($this->table_item_labels);
    }

    /**
     * 
     * Get codelist count with optional filters
     * 
     * @param array $filters - Optional filters
     * @return int Count of codelists
     * 
     */
    public function count($filters = array())
    {
        $this->db->from($this->table_codelists);

        // Apply same filters as get_all
        if (isset($filters['agency']) && !empty($filters['agency'])) {
            $this->db->where('agency', $filters['agency']);
        }
        if (isset($filters['search']) && !empty($filters['search'])) {
            $this->db->group_start();
            $this->db->like('title', $filters['search']);
            $this->db->or_like('name', $filters['search']);
            $this->db->or_like('idno', $filters['search']);
            $this->db->or_like('agency', $filters['search']);
            $this->db->or_like('description', $filters['search']);
            $this->db->group_end();
        }
        if (!empty($filters['exclude_archived'])) {
            $this->db->where('status !=', self::STATUS_ARCHIVED);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $this->db->where('status', $this->normalize_status($filters['status']));
        }

        return $this->db->count_all_results();
    }

    /**
     * 
     * Get hierarchical structure of codes (parent-child relationships)
     * 
     * @param int $codelist_id - Codelist ID
     * @param string $language - Optional language for labels
     * @return array Hierarchical structure with children arrays
     * 
     */
    public function get_hierarchical_structure($codelist_id, $language = null)
    {
        $codes = $this->get_codes($codelist_id, $language, true);
        if (empty($codes)) {
            return array();
        }

        $nodes = array();
        foreach ($codes as $code) {
            $code['children'] = array();
            $nodes[$code['id']] = $code;
        }

        $tree = array();
        foreach ($nodes as $id => &$node) {
            $pid = !empty($node['parent_id']) ? (int) $node['parent_id'] : 0;
            if ($pid && isset($nodes[$pid])) {
                $nodes[$pid]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);

        return $tree;
    }

    /**
     * Import one codelist from SdmxCodelistImporter parse row (non-SDMX callers should not use).
     *
     * @param array $row Parsed codelist (agency, name, title, version, description, uri, names, descriptions, codes[])
     * @param array $options dry_run (bool), replace_existing (bool), created_by (int|null)
     * @return array ok, action (created|skipped|dry_run|error), message?, id?, codes_imported?, warnings[]
     */
    public function import_sdmx_codelist(array $row, array $options = array())
    {
        $warnings = array();
        $dry = !empty($options['dry_run']);
        $replace = !empty($options['replace_existing']);
        $created_by = isset($options['created_by']) ? $options['created_by'] : null;

        $agency = isset($row['agency']) ? trim((string) $row['agency']) : '';
        $sdmx_name = isset($row['name']) ? trim((string) $row['name']) : '';
        $ver = isset($row['version']) ? trim((string) $row['version']) : '';
        if ($sdmx_name === '') {
            return array(
                'ok' => false,
                'action' => 'error',
                'message' => 'Missing name (SDMX maintainable id)',
                'warnings' => $warnings,
            );
        }

        $existing = $this->get_by_identity($agency, $sdmx_name, $ver);
        if ($existing) {
            if (!$replace) {
                return array(
                    'ok' => true,
                    'action' => 'skipped',
                    'message' => 'Already exists (pass replace=1 to overwrite)',
                    'agency' => $agency,
                    'name' => $sdmx_name,
                    'version' => $ver,
                    'warnings' => $warnings,
                );
            }
            if (!$this->is_content_mutable($existing)) {
                return array(
                    'ok' => false,
                    'action' => 'error',
                    'message' => 'Codelist is ' . $existing['status'] . ' and cannot be replaced',
                    'agency' => $agency,
                    'name' => $sdmx_name,
                    'version' => $ver,
                    'warnings' => $warnings,
                );
            }
            if (!$dry) {
                $this->delete((int) $existing['id']);
            }
        }

        $codes = isset($row['codes']) && is_array($row['codes']) ? $row['codes'] : array();
        if ($dry) {
            return array(
                'ok' => true,
                'action' => 'dry_run',
                'agency' => $agency,
                'name' => $sdmx_name,
                'version' => $ver,
                'codes_count' => count($codes),
                'warnings' => $warnings,
            );
        }

        $title = isset($row['title']) ? trim((string) $row['title']) : '';
        if ($title === '') {
            $title = $sdmx_name;
        }

        $this->db->trans_start();

        $insert = array(
            'agency' => $agency,
            'name' => $this->_sdmx_truncate($sdmx_name, 64),
            'version' => $ver,
            'title' => $this->_sdmx_truncate($title, 255),
            'idno' => self::make_idno($agency, $sdmx_name, $ver),
            'description' => isset($row['description']) && $row['description'] !== '' ? $row['description'] : null,
            'uri' => isset($row['uri']) && $row['uri'] !== '' ? $this->_sdmx_truncate((string) $row['uri'], 500) : null,
            'created_by' => $created_by,
            'changed_by' => $created_by,
        );

        $pk = $this->create($insert);
        if (!$pk) {
            $this->db->trans_rollback();
            return array(
                'ok' => false,
                'action' => 'error',
                'message' => 'Failed to create codelist',
                'warnings' => $warnings,
            );
        }

        $this->_sdmx_sync_codelist_header_languages((int) $pk, $row, $codes, $warnings);

        $map = $this->_sdmx_import_codes_and_labels((int) $pk, $codes, $warnings);

        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return array(
                'ok' => false,
                'action' => 'error',
                'message' => 'Transaction failed',
                'warnings' => $warnings,
            );
        }

        return array(
            'ok' => true,
            'action' => 'created',
            'id' => (int) $pk,
            'agency' => $agency,
            'name' => $sdmx_name,
            'version' => $ver,
            'codes_imported' => count($map),
            'warnings' => $warnings,
        );
    }

    /**
     * @param int   $codelist_pk
     * @param array $row
     * @param array $codes
     * @param array $warnings
     */
    private function _sdmx_sync_codelist_header_languages($codelist_pk, array $row, array $codes, array &$warnings)
    {
        $names = isset($row['names']) && is_array($row['names']) ? $row['names'] : array();
        $descs = isset($row['descriptions']) && is_array($row['descriptions']) ? $row['descriptions'] : array();
        $langs = $this->_sdmx_collect_iso_langs($names, $descs);
        foreach ($codes as $c) {
            $cn = isset($c['names']) && is_array($c['names']) ? $c['names'] : array();
            $cd = isset($c['descriptions']) && is_array($c['descriptions']) ? $c['descriptions'] : array();
            $langs = array_unique(array_merge($langs, $this->_sdmx_collect_iso_langs($cn, $cd)));
        }

        $fallbackName = isset($row['title']) ? trim((string) $row['title']) : '';
        if ($fallbackName === '' && isset($row['name'])) {
            $fallbackName = trim((string) $row['name']);
        }
        if ($fallbackName === '') {
            $fallbackName = '-';
        }

        foreach ($langs as $lang) {
            $label = isset($names[$lang]) ? trim((string) $names[$lang]) : '';
            if ($label === '') {
                $label = $this->_sdmx_first_label_for_lang($codes, $lang);
            }
            if ($label === '') {
                $label = $fallbackName;
            }
            $label = $this->_sdmx_truncate($label, 500);
            $desc = isset($descs[$lang]) ? $descs[$lang] : null;
            if ($desc !== null && $desc !== '') {
                $desc = (string) $desc;
            } else {
                $desc = null;
            }
            if (!$this->set_codelist_translation($codelist_pk, $lang, $label, $desc)) {
                $warnings[] = 'Failed to set codelist header language ' . $lang;
            }
        }
    }

    /**
     * @param array $names
     * @param array $descriptions
     * @return string[]
     */
    private function _sdmx_collect_iso_langs(array $names, array $descriptions)
    {
        $this->load->config('iso_languages');
        $iso = $this->config->item('iso_languages');
        if (!is_array($iso)) {
            return array();
        }
        $out = array();
        foreach (array_keys($names) as $l) {
            if ($l !== 'und' && array_key_exists($l, $iso)) {
                $out[] = $l;
            }
        }
        foreach (array_keys($descriptions) as $l) {
            if ($l !== 'und' && array_key_exists($l, $iso) && !in_array($l, $out, true)) {
                $out[] = $l;
            }
        }
        return $out;
    }

    /**
     * @param array  $codes
     * @param string $lang
     * @return string
     */
    private function _sdmx_first_label_for_lang(array $codes, $lang)
    {
        foreach ($codes as $c) {
            $names = isset($c['names']) && is_array($c['names']) ? $c['names'] : array();
            if (isset($names[$lang]) && trim((string) $names[$lang]) !== '') {
                return trim((string) $names[$lang]);
            }
        }
        return '';
    }

    /**
     * @param int   $codelist_pk
     * @param array $codes
     * @param array $warnings
     * @return array<string,int> code => item id
     */
    private function _sdmx_import_codes_and_labels($codelist_pk, array $codes, array &$warnings)
    {
        $map = array();
        foreach ($codes as $c) {
            $codeStr = isset($c['code']) ? trim((string) $c['code']) : '';
            if ($codeStr === '') {
                continue;
            }
            $codeStr = $this->_sdmx_truncate($codeStr, 150);
            $so = null;
            if (isset($c['sort_order']) && $c['sort_order'] !== '' && $c['sort_order'] !== null) {
                $so = (int) $c['sort_order'];
            }
            $id = $this->add_code($codelist_pk, array(
                'code' => $codeStr,
                'parent_id' => null,
                'sort_order' => $so,
            ));
            if (!$id) {
                $warnings[] = 'Skipped duplicate or invalid code: ' . $codeStr;
                continue;
            }
            $map[$codeStr] = (int) $id;
        }

        foreach ($codes as $c) {
            $codeStr = isset($c['code']) ? trim((string) $c['code']) : '';
            if ($codeStr === '') {
                continue;
            }
            $codeStr = $this->_sdmx_truncate($codeStr, 150);
            if (!isset($map[$codeStr])) {
                continue;
            }
            $parentCode = isset($c['parent_code']) ? trim((string) $c['parent_code']) : '';
            if ($parentCode === '') {
                continue;
            }
            if (!isset($map[$parentCode])) {
                $warnings[] = 'Unknown parent code "' . $parentCode . '" for "' . $codeStr . '"';
                continue;
            }
            $this->update_code($map[$codeStr], array('parent_id' => $map[$parentCode]));
        }

        $this->load->config('iso_languages');
        $iso = $this->config->item('iso_languages');

        foreach ($codes as $c) {
            $codeStr = isset($c['code']) ? trim((string) $c['code']) : '';
            if ($codeStr === '') {
                continue;
            }
            $codeStr = $this->_sdmx_truncate($codeStr, 150);
            if (!isset($map[$codeStr])) {
                continue;
            }
            $itemId = $map[$codeStr];
            $names = isset($c['names']) && is_array($c['names']) ? $c['names'] : array();
            $descs = isset($c['descriptions']) && is_array($c['descriptions']) ? $c['descriptions'] : array();
            $langs = array_unique(array_merge(array_keys($names), array_keys($descs)));
            foreach ($langs as $lang) {
                if (!is_array($iso) || !array_key_exists($lang, $iso)) {
                    continue;
                }
                if (!$this->codelist_has_language($codelist_pk, $lang)) {
                    continue;
                }
                $lab = isset($names[$lang]) ? trim((string) $names[$lang]) : '';
                if ($lab === '') {
                    $lab = $codeStr;
                }
                $lab = $this->_sdmx_truncate($lab, 500);
                $d = isset($descs[$lang]) && $descs[$lang] !== '' ? (string) $descs[$lang] : null;
                $this->set_code_label($itemId, $lang, $lab, $d);
            }
        }

        foreach ($map as $codeStr => $itemId) {
            $this->db->where('codelist_item_id', (int) $itemId);
            $n = $this->db->count_all_results($this->table_item_labels);
            if ($n === 0 && $this->codelist_has_language($codelist_pk, 'en')) {
                $this->set_code_label((int) $itemId, 'en', $codeStr, null);
            }
        }

        return $map;
    }

    /**
     * @param string $s
     * @param int    $len
     * @return string
     */
    private function _sdmx_truncate($s, $len)
    {
        $s = (string) $s;
        if (function_exists('mb_substr')) {
            return mb_substr($s, 0, $len, 'UTF-8');
        }
        return substr($s, 0, $len);
    }

    /** NADA default agency when omitted from JSON seed / import_json payloads. */
    const NADA_DEFAULT_AGENCY = 'NADA';

    /** NADA default version when omitted from JSON. */
    const NADA_DEFAULT_VERSION = '1.0';

    /**
     * Remove all codes (and item labels) for a codelist; header rows are kept.
     *
     * @param int $codelist_pk
     * @return bool
     */
    public function delete_all_items_for_codelist($codelist_pk)
    {
        $this->db->where('codelist_id', (int) $codelist_pk);
        return $this->db->delete($this->table_items);
    }

    /**
     * Build a NADA-compatible codelist JSON document (seed / import_json shape).
     *
     * @param int $codelist_pk
     * @return array
     * @throws Exception
     */
    public function export_nada_json_document($codelist_pk)
    {
        $row = $this->get_by_id($codelist_pk);
        if (!$row) {
            throw new Exception('Codelist not found');
        }

        $codes = $this->get_codes((int) $codelist_pk, null, true, null, 0, null);
        $id_by_pk = array();
        foreach ($codes as $c) {
            $id_by_pk[(int) $c['id']] = isset($c['code']) ? (string) $c['code'] : '';
        }

        $items = array();
        foreach ($codes as $c) {
            $title = $this->_json_pick_item_title($c);
            $entry = array(
                'code' => (string) $c['code'],
                'title' => $title,
            );
            if (isset($c['sort_order']) && $c['sort_order'] !== null && $c['sort_order'] !== '') {
                $entry['sort_order'] = (int) $c['sort_order'];
            }
            $pid = isset($c['parent_id']) ? (int) $c['parent_id'] : 0;
            if ($pid > 0 && isset($id_by_pk[$pid]) && $id_by_pk[$pid] !== '') {
                $entry['parent_code'] = $id_by_pk[$pid];
            }
            $labels = isset($c['labels']) && is_array($c['labels']) ? $c['labels'] : array();
            if (count($labels) > 1 || (count($labels) === 1 && (!isset($labels[0]['language']) || $labels[0]['language'] !== 'en'))) {
                $entry['labels'] = array();
                foreach ($labels as $lbl) {
                    if (!is_array($lbl) || empty($lbl['language'])) {
                        continue;
                    }
                    $entry['labels'][] = array(
                        'language' => (string) $lbl['language'],
                        'label' => isset($lbl['label']) ? (string) $lbl['label'] : '',
                        'description' => isset($lbl['description']) ? $lbl['description'] : null,
                    );
                }
            }
            $items[] = $entry;
        }

        $translations = $this->get_codelist_translations((int) $codelist_pk);
        $header_trans = array();
        foreach ($translations as $tr) {
            $header_trans[] = array(
                'language' => $tr['language'],
                'label' => $tr['label'],
                'description' => isset($tr['description']) ? $tr['description'] : null,
            );
        }

        $doc = array(
            'idno' => (string) $row['idno'],
            'agency' => (string) $row['agency'],
            'name' => (string) $row['name'],
            'version' => (string) $row['version'],
            'title' => (string) $row['title'],
            'description' => isset($row['description']) ? $row['description'] : null,
            'items' => $items,
        );
        if (!empty($row['uri'])) {
            $doc['uri'] = (string) $row['uri'];
        }
        if (isset($row['status']) && $row['status'] !== '') {
            $doc['status'] = (string) $row['status'];
        }
        if (!empty($header_trans)) {
            $doc['translations'] = $header_trans;
        }

        return $doc;
    }

    /**
     * Import one codelist from a NADA-compatible (or ME-extended) JSON object.
     *
     * @param array $payload Flat codelist object or envelope with codelist + items
     * @param array $options dry_run, replace_existing (overwrite), created_by
     * @return array
     */
    public function import_json_codelist(array $payload, array $options = array())
    {
        $warnings = array();
        $dry = !empty($options['dry_run']);
        $replace = !empty($options['replace_existing']);
        $created_by = isset($options['created_by']) ? $options['created_by'] : null;

        $payload = $this->_json_normalize_import_payload($payload);

        $agency = isset($payload['agency']) && trim((string) $payload['agency']) !== ''
            ? trim((string) $payload['agency'])
            : self::NADA_DEFAULT_AGENCY;
        $version = isset($payload['version']) && trim((string) $payload['version']) !== ''
            ? trim((string) $payload['version'])
            : self::NADA_DEFAULT_VERSION;

        $sdmx_name = '';
        if (isset($payload['name']) && trim((string) $payload['name']) !== '') {
            $sdmx_name = trim((string) $payload['name']);
        }
        if ($sdmx_name === '') {
            return array(
                'ok' => false,
                'action' => 'error',
                'message' => 'name (SDMX maintainable id) is required',
                'warnings' => $warnings,
            );
        }

        $title = '';
        if (isset($payload['title']) && trim((string) $payload['title']) !== '') {
            $title = trim((string) $payload['title']);
        } elseif (isset($payload['display_name']) && trim((string) $payload['display_name']) !== '') {
            $title = trim((string) $payload['display_name']);
        } elseif (isset($payload['label']) && trim((string) $payload['label']) !== '') {
            $title = trim((string) $payload['label']);
        } else {
            $title = $sdmx_name;
        }

        $idno = '';
        if (isset($payload['idno']) && trim((string) $payload['idno']) !== '') {
            $idno = trim((string) $payload['idno']);
        } else {
            $idno = self::make_idno($agency, $sdmx_name, $version);
        }

        $items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : array();
        foreach ($items as $idx => $item) {
            if (!is_array($item)) {
                return array(
                    'ok' => false,
                    'action' => 'error',
                    'message' => "items[{$idx}] must be an object",
                    'warnings' => $warnings,
                );
            }
            $code = isset($item['code']) ? trim((string) $item['code']) : '';
            if ($code === '') {
                return array(
                    'ok' => false,
                    'action' => 'error',
                    'message' => "items[{$idx}].code is required",
                    'warnings' => $warnings,
                );
            }
            if (strlen($code) > 150) {
                return array(
                    'ok' => false,
                    'action' => 'error',
                    'message' => "items[{$idx}].code exceeds 150 characters",
                    'warnings' => $warnings,
                );
            }
        }

        if (!empty($payload['groups']) && is_array($payload['groups']) && count($payload['groups']) > 0) {
            $warnings[] = 'groups are not stored in Metadata Editor; group definitions were ignored';
        }

        $existing = $this->get_by_identity($agency, $sdmx_name, $version);

        if ($dry) {
            $action = 'create';
            if ($existing) {
                $action = ($replace && count($items) > 0) ? 'update' : 'reuse';
            }
            return array(
                'ok' => true,
                'action' => 'dry_run',
                'agency' => $agency,
                'name' => $sdmx_name,
                'version' => $version,
                'codes_count' => count($items),
                'planned_action' => $action,
                'warnings' => $warnings,
            );
        }

        if ($existing && (!$replace || count($items) === 0)) {
            if (count($items) > 0 && !$replace) {
                $warnings[] = 'Codelist already exists; pass replace=1 (or overwrite=true) to replace items';
            }
            return array(
                'ok' => true,
                'action' => 'skipped',
                'id' => (int) $existing['id'],
                'agency' => $agency,
                'name' => $sdmx_name,
                'version' => $version,
                'warnings' => $warnings,
            );
        }

        $this->db->trans_start();

        try {
            if ($existing) {
                $pk = (int) $existing['id'];
                if (!$this->is_content_mutable($existing)) {
                    $this->db->trans_rollback();
                    return array(
                        'ok' => false,
                        'action' => 'error',
                        'message' => 'Codelist is ' . $existing['status'] . ' and cannot be replaced',
                        'warnings' => $warnings,
                    );
                }
                $this->update($pk, array(
                    'title' => $this->_sdmx_truncate($title, 255),
                    'description' => isset($payload['description']) ? $payload['description'] : null,
                    'uri' => isset($payload['uri']) ? $this->_sdmx_truncate((string) $payload['uri'], 500) : null,
                    'changed_by' => $created_by,
                ));
                $this->delete_all_items_for_codelist($pk);
                $imported = $this->_json_import_items($pk, $items, $warnings);
                $this->_json_import_header_translations($pk, $payload, $title, $warnings);
                $action = 'updated';
            } else {
                $insert = array(
                    'idno' => $this->_sdmx_truncate($idno, 191),
                    'agency' => $agency,
                    'name' => $this->_sdmx_truncate($sdmx_name, 64),
                    'version' => $version,
                    'title' => $this->_sdmx_truncate($title, 255),
                    'description' => isset($payload['description']) ? $payload['description'] : null,
                    'uri' => isset($payload['uri']) ? $this->_sdmx_truncate((string) $payload['uri'], 500) : null,
                    'created_by' => $created_by,
                    'changed_by' => $created_by,
                );
                if (isset($payload['status'])) {
                    $insert['status'] = $this->normalize_status($payload['status']);
                }
                $pk = $this->create($insert);
                if (!$pk) {
                    throw $this->_codelist_create_exception();
                }
                $imported = $this->_json_import_items((int) $pk, $items, $warnings);
                $this->_json_import_header_translations((int) $pk, $payload, $title, $warnings);
                $action = 'created';
            }

            if ($this->db->trans_status() === false) {
                throw new Exception('Transaction failed');
            }
            $this->db->trans_complete();

            return array(
                'ok' => true,
                'action' => $action,
                'id' => (int) $pk,
                'agency' => $agency,
                'name' => $sdmx_name,
                'version' => $version,
                'codes_imported' => $imported,
                'warnings' => $warnings,
            );
        } catch (Exception $e) {
            $this->db->trans_rollback();
            return array(
                'ok' => false,
                'action' => 'error',
                'message' => $e->getMessage(),
                'warnings' => $warnings,
            );
        }
    }

    /**
     * @param array $payload
     * @return array
     */
    private function _json_normalize_import_payload(array $payload)
    {
        if (isset($payload['codelist']) && is_array($payload['codelist'])) {
            $cl = $payload['codelist'];
            if (isset($payload['items']) && is_array($payload['items'])) {
                $cl['items'] = $payload['items'];
            }
            if (isset($payload['translations']) && is_array($payload['translations'])) {
                $cl['translations'] = $payload['translations'];
            }
            if (isset($payload['groups']) && is_array($payload['groups'])) {
                $cl['groups'] = $payload['groups'];
            }
            return $cl;
        }

        return $payload;
    }

    /**
     * @param array $code_row From get_codes()
     * @return string
     */
    private function _json_pick_item_title(array $code_row)
    {
        $labels = isset($code_row['labels']) && is_array($code_row['labels']) ? $code_row['labels'] : array();
        foreach ($labels as $lbl) {
            if (is_array($lbl) && isset($lbl['language']) && $lbl['language'] === 'en' && !empty($lbl['label'])) {
                return (string) $lbl['label'];
            }
        }
        if (!empty($labels) && is_array($labels[0]) && !empty($labels[0]['label'])) {
            return (string) $labels[0]['label'];
        }

        return (string) $code_row['code'];
    }

    /**
     * @param int   $codelist_pk
     * @param array $items
     * @param array $warnings
     * @return int Number of items imported
     */
    private function _json_import_items($codelist_pk, array $items, array &$warnings)
    {
        $code_to_id = array();
        $pending_parents = array();

        foreach ($items as $idx => $item) {
            if (!is_array($item)) {
                continue;
            }
            $code = trim((string) $item['code']);
            if ($code === '') {
                continue;
            }
            $sort_order = isset($item['sort_order']) ? (int) $item['sort_order'] : 0;
            $item_id = $this->add_code($codelist_pk, array(
                'code' => $code,
                'sort_order' => $sort_order,
                'parent_id' => null,
            ));
            if (!$item_id) {
                $warnings[] = "Failed to insert item code {$code}";
                continue;
            }
            $code_to_id[$code] = (int) $item_id;

            $title = '';
            if (isset($item['title']) && trim((string) $item['title']) !== '') {
                $title = trim((string) $item['title']);
            } elseif (isset($item['label']) && trim((string) $item['label']) !== '') {
                $title = trim((string) $item['label']);
            }

            if (!empty($item['labels']) && is_array($item['labels'])) {
                foreach ($item['labels'] as $lbl) {
                    if (!is_array($lbl) || empty($lbl['language']) || !isset($lbl['label'])) {
                        continue;
                    }
                    $lang = trim((string) $lbl['language']);
                    $this->set_code_label(
                        (int) $item_id,
                        $lang,
                        (string) $lbl['label'],
                        isset($lbl['description']) ? $lbl['description'] : null
                    );
                }
            } elseif ($title !== '' && $this->codelist_has_language($codelist_pk, 'en')) {
                $this->set_code_label((int) $item_id, 'en', $title, null);
            } elseif ($title !== '') {
                $this->set_codelist_translation($codelist_pk, 'en', $this->_sdmx_truncate($title, 500), null);
                $this->set_code_label((int) $item_id, 'en', $title, null);
            }

            if (isset($item['parent_code']) && trim((string) $item['parent_code']) !== '') {
                $pending_parents[] = array(
                    'item_id' => (int) $item_id,
                    'parent_code' => trim((string) $item['parent_code']),
                );
            } elseif (isset($item['parent_id']) && (int) $item['parent_id'] > 0) {
                $warnings[] = "items[{$idx}].parent_id ignored; use parent_code in JSON interchange";
            }
        }

        foreach ($pending_parents as $link) {
            $parent_code = $link['parent_code'];
            if (!isset($code_to_id[$parent_code])) {
                $warnings[] = "Unknown parent_code \"{$parent_code}\" for item id {$link['item_id']}";
                continue;
            }
            $this->update_code($link['item_id'], array('parent_id' => $code_to_id[$parent_code]));
        }

        return count($code_to_id);
    }

    /**
     * @param int    $codelist_pk
     * @param array  $payload
     * @param string $title
     * @param array  $warnings
     */
    private function _json_import_header_translations($codelist_pk, array $payload, $title, array &$warnings)
    {
        $rows = isset($payload['translations']) && is_array($payload['translations']) ? $payload['translations'] : array();
        if (empty($rows)) {
            return;
        }
        foreach ($rows as $tr) {
            if (!is_array($tr) || empty($tr['language']) || !isset($tr['label'])) {
                continue;
            }
            $this->set_codelist_translation(
                $codelist_pk,
                trim((string) $tr['language']),
                (string) $tr['label'],
                isset($tr['description']) ? $tr['description'] : null
            );
        }
    }

    /** CSV column order for item export/import (Excel-compatible). */
    const CSV_ITEMS_COLUMNS = array('sort', 'parent_code', 'language', 'code', 'label', 'description');

    /**
     * Export all codelist items as UTF-8 CSV with BOM (Excel-friendly).
     *
     * @param int $codelist_pk
     * @return string
     * @throws Exception
     */
    public function export_items_csv($codelist_pk)
    {
        $row = $this->get_by_id($codelist_pk);
        if (!$row) {
            throw new Exception('Codelist not found');
        }

        $codes = $this->get_codes((int) $codelist_pk, null, true, null, 0, null);
        $id_by_pk = array();
        foreach ($codes as $c) {
            $id_by_pk[(int) $c['id']] = isset($c['code']) ? (string) $c['code'] : '';
        }

        $lines = array();
        $lines[] = $this->_csv_format_row(self::CSV_ITEMS_COLUMNS);

        foreach ($codes as $c) {
            $code = isset($c['code']) ? (string) $c['code'] : '';
            $sort = isset($c['sort_order']) && $c['sort_order'] !== null && $c['sort_order'] !== ''
                ? (string) (int) $c['sort_order']
                : '';
            $parent_code = '';
            $pid = isset($c['parent_id']) ? (int) $c['parent_id'] : 0;
            if ($pid > 0 && isset($id_by_pk[$pid])) {
                $parent_code = $id_by_pk[$pid];
            }

            $labels = isset($c['labels']) && is_array($c['labels']) ? $c['labels'] : array();
            if (empty($labels)) {
                $lines[] = $this->_csv_format_row(array($sort, $parent_code, '', $code, '', ''));
                continue;
            }
            foreach ($labels as $lbl) {
                if (!is_array($lbl)) {
                    continue;
                }
                $lines[] = $this->_csv_format_row(array(
                    $sort,
                    $parent_code,
                    isset($lbl['language']) ? (string) $lbl['language'] : '',
                    $code,
                    isset($lbl['label']) ? (string) $lbl['label'] : '',
                    isset($lbl['description']) ? (string) $lbl['description'] : '',
                ));
            }
        }

        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Import items from CSV into an existing codelist (replace all items by default).
     *
     * @param int    $codelist_pk
     * @param string $csv_content
     * @param array  $options dry_run, replace_existing (default true)
     * @return array
     */
    public function import_csv_codelist_items($codelist_pk, $csv_content, array $options = array())
    {
        $warnings = array();
        $errors = array();
        $dry = !empty($options['dry_run']);
        $replace = !array_key_exists('replace_existing', $options) || !empty($options['replace_existing']);

        $codelist = $this->get_by_id($codelist_pk);
        if (!$codelist) {
            return array(
                'ok' => false,
                'message' => 'Codelist not found',
                'errors' => array('Codelist not found'),
                'warnings' => $warnings,
            );
        }

        if (!$replace) {
            return array(
                'ok' => false,
                'message' => 'CSV item import requires replace=1 (all existing items are replaced)',
                'errors' => array('replace=1 is required'),
                'warnings' => $warnings,
            );
        }

        if (!$this->is_content_mutable($codelist)) {
            return array(
                'ok' => false,
                'message' => 'Codelist is ' . $codelist['status'] . ' and cannot be modified',
                'errors' => array('Codelist is not editable'),
                'warnings' => $warnings,
            );
        }

        $parse = $this->_parse_csv_items_document($csv_content);
        if (!empty($parse['errors'])) {
            return array(
                'ok' => false,
                'message' => $parse['errors'][0],
                'errors' => $parse['errors'],
                'warnings' => $warnings,
            );
        }

        $enabled_langs = array();
        foreach ($this->get_codelist_translations((int) $codelist_pk) as $tr) {
            $lang = trim((string) (isset($tr['language']) ? $tr['language'] : ''));
            if ($lang !== '') {
                $enabled_langs[strtolower($lang)] = $lang;
            }
        }
        if (empty($enabled_langs)) {
            $enabled_langs['en'] = 'en';
        }

        $build = $this->_csv_rows_to_import_items($parse['rows'], $enabled_langs, $errors, $warnings);
        if (!empty($errors)) {
            return array(
                'ok' => false,
                'message' => $errors[0],
                'errors' => $errors,
                'warnings' => $warnings,
                'rows_parsed' => count($parse['rows']),
            );
        }

        $items = $build['items'];
        $row_count = count($parse['rows']);
        $code_count = count($items);

        if ($dry) {
            return array(
                'ok' => true,
                'action' => 'dry_run',
                'dry_run' => true,
                'rows_parsed' => $row_count,
                'codes_count' => $code_count,
                'warnings' => $warnings,
            );
        }

        $this->db->trans_start();
        try {
            $this->delete_all_items_for_codelist((int) $codelist_pk);
            $imported = $this->_json_import_items((int) $codelist_pk, $items, $warnings);
            if ($this->db->trans_status() === false) {
                throw new Exception('Transaction failed');
            }
            $this->db->trans_complete();

            return array(
                'ok' => true,
                'action' => 'updated',
                'codes_imported' => $imported,
                'rows_parsed' => $row_count,
                'codes_count' => $code_count,
                'warnings' => $warnings,
            );
        } catch (Exception $e) {
            $this->db->trans_rollback();
            return array(
                'ok' => false,
                'message' => $e->getMessage(),
                'errors' => array($e->getMessage()),
                'warnings' => $warnings,
            );
        }
    }

    /**
     * @param array $fields
     * @return string
     */
    private function _csv_format_row(array $fields)
    {
        $out = array();
        foreach ($fields as $field) {
            $value = (string) $field;
            if (strpos($value, '"') !== false || strpos($value, ',') !== false
                || strpos($value, "\n") !== false || strpos($value, "\r") !== false) {
                $out[] = '"' . str_replace('"', '""', $value) . '"';
            } else {
                $out[] = $value;
            }
        }

        return implode(',', $out);
    }

    /**
     * @param string $csv_content
     * @return array{ rows: array, errors: array }
     */
    private function _parse_csv_items_document($csv_content)
    {
        $errors = array();
        $csv_content = (string) $csv_content;
        if ($csv_content === '') {
            return array('rows' => array(), 'errors' => array('CSV file is empty'));
        }

        $csv_content = preg_replace('/^\xEF\xBB\xBF/', '', $csv_content);
        $csv_content = str_replace(array("\r\n", "\r"), "\n", $csv_content);
        $csv_content = trim($csv_content);
        if ($csv_content === '') {
            return array('rows' => array(), 'errors' => array('CSV file is empty'));
        }

        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            return array('rows' => array(), 'errors' => array('Failed to read CSV'));
        }
        fwrite($stream, $csv_content);
        rewind($stream);

        $parsed = array();
        $line_no = 0;
        while (($cells = fgetcsv($stream)) !== false) {
            $line_no++;
            if ($cells === array(null) || (count($cells) === 1 && trim((string) $cells[0]) === '')) {
                continue;
            }
            $parsed[] = array('line' => $line_no, 'cells' => $cells);
        }
        fclose($stream);

        if (empty($parsed)) {
            return array('rows' => array(), 'errors' => array('No data rows found in CSV'));
        }

        $header_map = array();
        $first = $parsed[0]['cells'];
        $normalized_first = array_map(function ($c) {
            return strtolower(trim((string) $c));
        }, $first);
        $expected = self::CSV_ITEMS_COLUMNS;
        $is_header = true;
        foreach ($expected as $idx => $col) {
            if (!isset($normalized_first[$idx]) || $normalized_first[$idx] !== $col) {
                $is_header = false;
                break;
            }
        }

        $start = 0;
        if ($is_header) {
            foreach ($expected as $idx => $col) {
                $header_map[$idx] = $col;
            }
            $start = 1;
        } else {
            foreach ($expected as $idx => $col) {
                $header_map[$idx] = $col;
            }
        }

        $rows = array();
        for ($i = $start; $i < count($parsed); $i++) {
            $cells = $parsed[$i]['cells'];
            $line = $parsed[$i]['line'];
            $row = array();
            foreach ($header_map as $idx => $key) {
                $row[$key] = isset($cells[$idx]) ? trim((string) $cells[$idx]) : '';
            }
            if ($row['code'] === '' && $row['label'] === '' && $row['language'] === ''
                && $row['sort'] === '' && $row['parent_code'] === '' && $row['description'] === '') {
                continue;
            }
            if ($row['code'] === '') {
                $errors[] = 'Line ' . $line . ': code is required';
                continue;
            }
            if (strlen($row['code']) > 150) {
                $errors[] = 'Line ' . $line . ': code exceeds 150 characters';
                continue;
            }
            $rows[] = $row;
        }

        if (!empty($errors)) {
            return array('rows' => array(), 'errors' => $errors);
        }
        if (empty($rows)) {
            return array('rows' => array(), 'errors' => array('No item rows found in CSV'));
        }

        return array('rows' => $rows, 'errors' => array());
    }

    /**
     * @param array $csv_rows
     * @param array $enabled_langs map lowercase lang => canonical lang
     * @param array $errors
     * @param array $warnings
     * @return array{ items: array }
     */
    private function _csv_rows_to_import_items(array $csv_rows, array $enabled_langs, array &$errors, array &$warnings)
    {
        $sole_lang = null;
        if (count($enabled_langs) === 1) {
            $sole_lang = (string) reset($enabled_langs);
        }
        $items_by_code = array();
        $seen_lang = array();

        foreach ($csv_rows as $idx => $row) {
            $line = $idx + 1;
            $code = $row['code'];
            $lang_input = $row['language'];
            if ($lang_input === '' && $sole_lang !== null) {
                $lang_input = $sole_lang;
            }
            if ($lang_input === '') {
                $errors[] = 'Row for code "' . $code . '": language is required (or configure a single codelist language)';
                continue;
            }
            $lang_key = strtolower($lang_input);
            if (!isset($enabled_langs[$lang_key])) {
                $errors[] = 'Row for code "' . $code . '": language "' . $lang_input . '" is not configured for this codelist';
                continue;
            }
            $lang = $enabled_langs[$lang_key];
            $dup_key = $code . "\0" . $lang_key;
            if (isset($seen_lang[$dup_key])) {
                $errors[] = 'Duplicate language "' . $lang_input . '" for code "' . $code . '"';
                continue;
            }
            $seen_lang[$dup_key] = true;

            $lab = $row['label'];
            if ($lab === '') {
                $errors[] = 'Row for code "' . $code . '" (' . $lang . '): label is required';
                continue;
            }

            if (!isset($items_by_code[$code])) {
                $item = array('code' => $code, 'labels' => array());
                if ($row['sort'] !== '' && is_numeric($row['sort'])) {
                    $item['sort_order'] = (int) $row['sort'];
                }
                if ($row['parent_code'] !== '') {
                    $item['parent_code'] = $row['parent_code'];
                }
                $items_by_code[$code] = $item;
            } else {
                if ($row['sort'] !== '' && is_numeric($row['sort'])
                    && !isset($items_by_code[$code]['sort_order'])) {
                    $items_by_code[$code]['sort_order'] = (int) $row['sort'];
                }
                if ($row['parent_code'] !== ''
                    && empty($items_by_code[$code]['parent_code'])) {
                    $items_by_code[$code]['parent_code'] = $row['parent_code'];
                }
            }

            $items_by_code[$code]['labels'][] = array(
                'language' => $lang,
                'label' => $lab,
                'description' => $row['description'] !== '' ? $row['description'] : null,
            );
        }

        if (!empty($errors)) {
            return array('items' => array());
        }

        return array('items' => array_values($items_by_code));
    }
}
