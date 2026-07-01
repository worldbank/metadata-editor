<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

use Swaggest\JsonDiff\JsonDiff;
use Swaggest\JsonDiff\JsonPointer;

/**
 * Build normalized metadata change records for audit / edit history.
 *
 * Tracks v1 change payloads with explicit old/new values per path.
 * Normalization uses a root denylist (layer 1) and study-only out-of-scope keys (layer 2).
 */
class Metadata_change_log
{
    const VERSION = 1;

    const SCOPE_STUDY = 'study_metadata';
    const SCOPE_ADMIN = 'admin_metadata';
    const SCOPE_TEMPLATE = 'template';

    /** @var CI_Controller */
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->library('Audit_log');
    }

    /**
     * Root keys stripped before diff (layer 1).
     *
     * @return array
     */
    public static function root_denylist_keys()
    {
        return array(
            // Application-managed metadata keys
            'schema',
            'schema_version',
            'type',
            'changed',
            'changed_utc',
            'created',
            'created_utc',
            'created_by',
            'changed_by',
            'user_id',
            // Project / API table-level keys
            'id',
            'idno',
            'pid',
            'title',
            'abbreviation',
            'authoring_entity',
            'nation',
            'year_start',
            'year_end',
            'study_idno',
            'attributes',
            'metafile',
            'dirpath',
            'thumbnail',
            'varcount',
            'published',
            'is_shared',
            'is_locked',
            'template_uid',
            'partial_update',
            'collection_ids',
            'version_number',
            'version_created',
            'version_created_by',
            'version_notes',
            'metadata',
            'sid',
            'validate',
            'overwrite',
        );
    }

    /**
     * Study metadata root keys excluded from tracking (layer 2 — out of scope).
     *
     * @return array
     */
    public static function study_out_of_scope_keys()
    {
        return array(
            'variables',
            'data_files',
            'variable_groups',
            'data_structure',
            'data_structure_reference',
            // Synced from editor_project_dsd binding, not edited via metadata form
            'indicator_id_value',
        );
    }

    /**
     * Normalize metadata for change tracking.
     *
     * @param mixed  $metadata
     * @param string $scope One of SCOPE_* constants
     * @return array
     */
    public static function normalize_for_change_tracking($metadata, $scope)
    {
        if ($metadata === null) {
            return array();
        }
        if (is_object($metadata)) {
            $metadata = json_decode(json_encode($metadata), true);
        }
        if (!is_array($metadata)) {
            return array();
        }

        $out = $metadata;
        foreach (self::root_denylist_keys() as $key) {
            unset($out[$key]);
        }

        if ($scope === self::SCOPE_STUDY) {
            foreach (self::study_out_of_scope_keys() as $key) {
                unset($out[$key]);
            }
        }

        return $out;
    }

    /**
     * Build a v1 change record or return null when there are no changes.
     *
     * @param array  $before Normalized before state
     * @param array  $after  Normalized after state
     * @param string $scope
     * @return array|null
     * @throws Exception
     */
    public static function build_change_record(array $before, array $after, $scope)
    {
        $flags = JsonDiff::TOLERATE_ASSOCIATIVE_ARRAYS | JsonDiff::COLLECT_MODIFIED_DIFF;
        $diff = new JsonDiff($before, $after, $flags);

        $changes = self::collect_changes($diff, $before, $after);
        if (count($changes) === 0) {
            return null;
        }

        return array(
            'version' => self::VERSION,
            'scope' => $scope,
            'summary' => self::summarize_changes($changes),
            'patch' => $changes,
        );
    }

    /**
     * Build change record without throwing; on failure returns a diff_failed payload.
     *
     * @param mixed  $before_raw
     * @param mixed  $after_raw
     * @param string $scope
     * @return array|null
     */
    public static function build_change_record_safe($before_raw, $after_raw, $scope)
    {
        $before_norm = self::normalize_for_change_tracking($before_raw, $scope);
        $after_norm = self::normalize_for_change_tracking($after_raw, $scope);

        try {
            return self::build_change_record($before_norm, $after_norm, $scope);
        } catch (Exception $e) {
            log_message(
                'error',
                'Metadata_change_log::build_change_record failed scope=' . $scope . ': ' . $e->getMessage()
            );

            return array(
                'version' => self::VERSION,
                'scope' => $scope,
                'status' => 'diff_failed',
                'error' => $e->getMessage(),
                'patch' => array(),
                'before' => $before_norm,
                'after' => $after_norm,
            );
        }
    }

    /**
     * Build RFC 6902 patch operations to undo a list of changes on current metadata.
     *
     * @param array $changes
     * @return array
     */
    public static function build_reverse_patch(array $changes)
    {
        $reverse = array();
        $list = array_reverse($changes);

        foreach ($list as $change) {
            if (!isset($change['op'], $change['path'])) {
                continue;
            }

            switch ($change['op']) {
                case 'add':
                    $reverse[] = array(
                        'op' => 'remove',
                        'path' => $change['path'],
                    );
                    break;
                case 'remove':
                    $reverse[] = array(
                        'op' => 'add',
                        'path' => $change['path'],
                        'value' => array_key_exists('old', $change) ? $change['old'] : null,
                    );
                    break;
                case 'replace':
                    $reverse[] = array(
                        'op' => 'replace',
                        'path' => $change['path'],
                        'value' => array_key_exists('old', $change) ? $change['old'] : null,
                    );
                    break;
            }
        }

        return $reverse;
    }

    /**
     * @param int         $sid
     * @param mixed       $before_raw
     * @param mixed       $after_raw
     * @param string      $action
     * @param int|null    $user_id
     * @param array       $extra Optional keys merged into the logged record (e.g. reverted_log_id)
     */
    public function record_project_metadata_change($sid, $before_raw, $after_raw, $action, $user_id = null, array $extra = array())
    {
        $record = self::build_change_record_safe($before_raw, $after_raw, self::SCOPE_STUDY);
        if ($record === null) {
            return;
        }
        if (!empty($extra)) {
            $record = array_merge($record, $extra);
        }
        $this->ci->audit_log->log_event('project', (int) $sid, $action, $record, $user_id);
    }

    /**
     * @param int      $admin_metadata_id
     * @param int      $project_sid
     * @param mixed    $before_raw
     * @param mixed    $after_raw
     * @param string   $action
     * @param int|null $user_id
     * @param array    $extra
     */
    public function record_admin_metadata_change(
        $admin_metadata_id,
        $project_sid,
        $before_raw,
        $after_raw,
        $action,
        $user_id = null,
        array $extra = array()
    ) {
        $record = self::build_change_record_safe($before_raw, $after_raw, self::SCOPE_ADMIN);
        if ($record === null) {
            return;
        }
        if (!empty($extra)) {
            $record = array_merge($record, $extra);
        }
        $this->ci->audit_log->log_event(
            'admin-metadata',
            (int) $admin_metadata_id,
            $action,
            $record,
            $user_id,
            (int) $project_sid
        );
    }

    /**
     * @param int      $template_id
     * @param mixed    $before_raw  e.g. array('template' => ...)
     * @param mixed    $after_raw
     * @param string   $action
     * @param int|null $user_id
     * @param array    $extra
     */
    public function record_template_change($template_id, $before_raw, $after_raw, $action, $user_id = null, array $extra = array())
    {
        $record = self::build_change_record_safe($before_raw, $after_raw, self::SCOPE_TEMPLATE);
        if ($record === null) {
            return;
        }
        if (!empty($extra)) {
            $record = array_merge($record, $extra);
        }

        $this->ci->load->model('Edit_history_model');
        $this->ci->Edit_history_model->log('template', (int) $template_id, $action, $record, $user_id);
    }

    /**
     * @param JsonDiff $diff
     * @param array    $before
     * @param array    $after
     * @return array
     */
    private static function collect_changes(JsonDiff $diff, array $before, array $after)
    {
        $changes = array();
        $seen_paths = array();

        foreach ($diff->getModifiedDiff() as $modified) {
            $path = (string) $modified->path;
            if ($path === '' || isset($seen_paths[$path])) {
                continue;
            }
            $seen_paths[$path] = true;
            $changes[] = array(
                'op' => 'replace',
                'path' => $path,
                'old' => $modified->original,
                'new' => $modified->new,
            );
        }

        foreach ($diff->getRemovedPaths() as $path) {
            if ($path === '' || isset($seen_paths[$path])) {
                continue;
            }
            $seen_paths[$path] = true;
            $changes[] = array(
                'op' => 'remove',
                'path' => $path,
                'old' => self::value_at_json_pointer($before, $path),
                'new' => null,
            );
        }

        foreach ($diff->getAddedPaths() as $path) {
            if ($path === '' || isset($seen_paths[$path])) {
                continue;
            }
            $seen_paths[$path] = true;
            $changes[] = array(
                'op' => 'add',
                'path' => $path,
                'old' => null,
                'new' => self::value_at_json_pointer($after, $path),
            );
        }

        usort($changes, function ($a, $b) {
            return strcmp($a['path'], $b['path']);
        });

        return $changes;
    }

    /**
     * @param array  $changes
     * @return array
     */
    private static function summarize_changes(array $changes)
    {
        $summary = array(
            'added' => 0,
            'removed' => 0,
            'replaced' => 0,
        );

        foreach ($changes as $change) {
            if (!isset($change['op'])) {
                continue;
            }
            if ($change['op'] === 'add') {
                $summary['added']++;
            } elseif ($change['op'] === 'remove') {
                $summary['removed']++;
            } elseif ($change['op'] === 'replace') {
                $summary['replaced']++;
            }
        }

        return $summary;
    }

    /**
     * @param array  $document
     * @param string $path JSON Pointer path
     * @return mixed|null
     */
    private static function value_at_json_pointer(array $document, $path)
    {
        if ($path === '' || $path === '/') {
            return $document;
        }

        try {
            $path_items = JsonPointer::splitPath($path);
            return JsonPointer::get($document, $path_items);
        } catch (Exception $e) {
            return null;
        }
    }
}
