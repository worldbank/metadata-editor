<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Links indicator/timeseries projects (editor_projects.id) to global data_structures rows.
 * Column definitions are read live from data_structure_components via Project_dsd_resolver.
 */
class Editor_project_dsd_model extends CI_Model {

    private $table = 'editor_project_dsd';

    public function get_by_sid($sid)
    {
        $this->db->where('sid', (int) $sid);
        $row = $this->db->get($this->table)->row_array();

        return $row ?: null;
    }

    /**
     * @param int         $sid
     * @param int         $data_structure_id
     * @param string|null $indicator_id_value
     * @return bool
     */
    public function bind($sid, $data_structure_id, $indicator_id_value = null)
    {
        $sid = (int) $sid;
        $data_structure_id = (int) $data_structure_id;
        $now = time();

        $existing = $this->get_by_sid($sid);
        $row = array(
            'sid' => $sid,
            'data_structure_id' => $data_structure_id,
            'updated' => $now,
        );

        if ($indicator_id_value !== null) {
            $row['indicator_id_value'] = trim((string) $indicator_id_value) !== ''
                ? trim((string) $indicator_id_value)
                : null;
        }

        if ($existing) {
            $row['has_published_data'] = 0;
            $row['published_row_count'] = null;
            $row['data_imported_at'] = null;
            $this->db->where('sid', $sid);
            return (bool) $this->db->update($this->table, $row);
        }

        $row['created'] = $now;
        $row['has_published_data'] = 0;
        $row['published_row_count'] = null;
        $row['data_imported_at'] = null;

        return (bool) $this->db->insert($this->table, $row);
    }

    /**
     * @param int      $sid
     * @param int|null $row_count
     * @return bool
     */
    public function mark_published_data($sid, $row_count = null)
    {
        $upd = array(
            'has_published_data' => 1,
            'data_imported_at' => time(),
            'updated' => time(),
        );
        if ($row_count !== null && $row_count !== '') {
            $upd['published_row_count'] = max(0, (int) $row_count);
        }

        $this->db->where('sid', (int) $sid);
        return (bool) $this->db->update($this->table, $upd);
    }

    /**
     * @param int $sid
     * @return bool
     */
    public function clear_published_data($sid)
    {
        $this->db->where('sid', (int) $sid);
        return (bool) $this->db->update($this->table, array(
            'has_published_data' => 0,
            'published_row_count' => null,
            'data_imported_at' => null,
            'updated' => time(),
        ));
    }

    /**
     * @param int $sid
     * @return bool
     */
    public function has_published_data($sid)
    {
        $row = $this->get_by_sid($sid);
        return is_array($row) && !empty($row['has_published_data']);
    }

    /**
     * @param int    $sid
     * @param string $indicator_id_value
     * @return bool
     */
    public function update_indicator_id_value($sid, $indicator_id_value)
    {
        $value = trim((string) $indicator_id_value);
        $this->db->where('sid', (int) $sid);
        return (bool) $this->db->update($this->table, array(
            'indicator_id_value' => $value !== '' ? $value : null,
            'updated' => time(),
        ));
    }

    /**
     * @param int    $sid
     * @param string $implied_freq_code SDMX FREQ or empty string to clear
     * @return bool
     */
    public function update_implied_freq_code($sid, $implied_freq_code)
    {
        $value = trim((string) $implied_freq_code);
        $this->db->where('sid', (int) $sid);
        return (bool) $this->db->update($this->table, array(
            'implied_freq_code' => $value !== '' ? $value : null,
            'updated' => time(),
        ));
    }

    /**
     * @param int $sid
     * @return bool
     */
    public function unbind($sid)
    {
        $this->db->where('sid', (int) $sid);
        return (bool) $this->db->delete($this->table);
    }

    /**
     * @param int $data_structure_id
     * @return int
     */
    public function count_by_structure($data_structure_id)
    {
        $this->db->where('data_structure_id', (int) $data_structure_id);
        return (int) $this->db->count_all_results($this->table);
    }
}
