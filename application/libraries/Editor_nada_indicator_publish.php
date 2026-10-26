<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Nada_catalog_client.php';

/**
 * Publish indicator DSD and DuckDB timeseries data to a NADA catalog.
 */
class Editor_nada_indicator_publish {

	/** @var CI_Controller */
	private $ci;

	public function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->model('Editor_model');
		$this->ci->load->model('Catalog_connections_model');
		$this->ci->load->model('Editor_project_dsd_model');
		$this->ci->load->model('Data_structure_model');
		$this->ci->load->library('Data_structure_util');
		$this->ci->load->library('Indicator_duckdb_service');
	}

	/**
	 * @param string $type
	 * @return bool
	 */
	public function is_indicator_project_type($type)
	{
		return in_array($type, array('indicator', 'timeseries'), true);
	}

	/**
	 * Local binding + publish readiness for the UI.
	 *
	 * @param int $sid
	 * @return array
	 */
	public function get_local_indicator_context($sid)
	{
		$sid = (int) $sid;
		$binding = $this->ci->Editor_project_dsd_model->get_by_sid($sid);
		$reference = $this->ci->data_structure_util->resolve_project_reference($sid);
		$project = $this->ci->Editor_model->get_basic_info($sid);
		$study_idno = ($project && !empty($project['study_idno']))
			? trim((string) $project['study_idno'])
			: '';
		$has_published_data = is_array($binding) && !empty($binding['has_published_data']);

		if (!$has_published_data && $binding !== null) {
			$this->ci->load->model('Indicator_dsd_model');
			$has_published_data = $this->ci->Indicator_dsd_model->sync_published_data_tracking_from_duckdb($sid);
		}

		return array(
			'bound' => $binding !== null,
			'data_structure_reference' => $reference,
			'data_structure_id' => ($binding && isset($binding['data_structure_id']))
				? (int) $binding['data_structure_id']
				: null,
			'has_published_data' => $has_published_data,
			'published_row_count' => ($binding && isset($binding['published_row_count']))
				? (int) $binding['published_row_count']
				: null,
			'study_idno' => $study_idno !== '' ? $study_idno : null,
		);
	}

	/**
	 * Look up DSD on NADA and compare with local registry row when bound.
	 *
	 * @param Nada_catalog_client $client
	 * @param array|null $reference
	 * @param int|null $local_structure_id
	 * @return array
	 */
	public function get_nada_dsd_status(Nada_catalog_client $client, $reference, $local_structure_id = null)
	{
		if (!$reference || empty($reference['idno'])) {
			return array(
				'exists' => false,
				'status' => 'not_bound',
				'message' => 'No data structure is bound to this project',
			);
		}

		$idno = trim((string) $reference['idno']);
		$local_hash = null;
		if ($local_structure_id) {
			$row = $this->ci->Data_structure_model->get_structure_by_id((int) $local_structure_id, false);
			if ($row && !empty($row['content_hash'])) {
				$local_hash = (string) $row['content_hash'];
			}
		}

		try {
			$response = $client->get('admin/data_structures/' . rawurlencode($idno));
		} catch (ApiRequestException $e) {
			$details = $e->getDetails();
			$http = isset($details['status']) ? (int) $details['status'] : 0;
			if ($http === 404) {
				return array(
					'exists' => false,
					'idno' => $idno,
					'local_content_hash' => $local_hash,
					'matches_local' => false,
				);
			}

			return array(
				'exists' => false,
				'idno' => $idno,
				'error' => $e->getMessage(),
				'local_content_hash' => $local_hash,
			);
		}

		$structure = null;
		if (isset($response['data_structure']) && is_array($response['data_structure'])) {
			$structure = $response['data_structure'];
		} elseif (isset($response['structure']) && is_array($response['structure'])) {
			$structure = $response['structure'];
		} elseif (isset($response['result']) && is_array($response['result'])) {
			$structure = $response['result'];
		}

		$nada_hash = ($structure && isset($structure['content_hash'])) ? (string) $structure['content_hash'] : null;

		return array(
			'exists' => true,
			'idno' => $idno,
			'status' => ($structure && isset($structure['status'])) ? $structure['status'] : null,
			'title' => ($structure && isset($structure['title'])) ? $structure['title'] : null,
			'version' => ($structure && isset($structure['version'])) ? $structure['version'] : null,
			'nada_content_hash' => $nada_hash,
			'local_content_hash' => $local_hash,
			'matches_local' => ($local_hash !== null && $nada_hash !== null && $local_hash === $nada_hash),
		);
	}

	/**
	 * Optional NADA observation count when study exists and is linked.
	 *
	 * @param Nada_catalog_client $client
	 * @param string $study_idno
	 * @return array|null
	 */
	public function get_nada_data_count(Nada_catalog_client $client, $study_idno)
	{
		$study_idno = trim((string) $study_idno);
		if ($study_idno === '') {
			return null;
		}

		try {
			$response = $client->get('admin/timeseries/data/' . rawurlencode($study_idno) . '/count');
			$count = null;
			if (isset($response['result']['count'])) {
				$count = (int) $response['result']['count'];
			} elseif (isset($response['count'])) {
				$count = (int) $response['count'];
			}

			return array(
				'count' => $count,
				'study_idno' => $study_idno,
			);
		} catch (Exception $e) {
			return array(
				'count' => null,
				'study_idno' => $study_idno,
				'error' => $e->getMessage(),
			);
		}
	}

	/**
	 * @param int $sid
	 * @param int $user_id
	 * @param int $catalog_connection_id
	 * @param bool $overwrite
	 * @return array
	 */
	public function publish_dsd($sid, $user_id, $catalog_connection_id, $overwrite = false, $force = false)
	{
		$this->ci->data_structure_util->resolve_project_reference($sid, true);

		$conn = $this->get_connection($user_id, $catalog_connection_id);
		$client = Nada_catalog_client::from_connection($conn);
		$context = $this->get_local_indicator_context($sid);

		if (!$context['bound'] || empty($context['data_structure_id'])) {
			throw new Exception('No data structure is bound to this project');
		}
		if (empty($context['data_structure_reference']['idno'])) {
			throw new Exception('Project data structure reference has no idno');
		}

		$idno = trim((string) $context['data_structure_reference']['idno']);
		$nadaStatus = $this->get_nada_dsd_status(
			$client,
			$context['data_structure_reference'],
			(int) $context['data_structure_id']
		);

		if (!empty($nadaStatus['exists']) && !$overwrite) {
			return array(
				'status' => 'skipped',
				'reason' => 'DSD already exists on NADA',
				'idno' => $idno,
				'nada_dsd' => $nadaStatus,
			);
		}

		$payload = $this->ci->data_structure_util->build_nada_import_payload(
			(int) $context['data_structure_id'],
			$overwrite
		);

		$codelists = $this->ci->data_structure_util->sync_dsd_codelists_to_nada(
			$client,
			(int) $context['data_structure_id'],
			$overwrite
		);

		// Re-importing a DSD on NADA flags every study that uses it for re-sync, so an unchanged
		// structure is not re-imported (codelists above are still synced). dsd_force skips this check.
		if (!empty($nadaStatus['exists']) && $overwrite && !$force
			&& $this->nada_dsd_matches_local($client, $idno, (int) $context['data_structure_id'])) {
			return array(
				'status' => 'skipped',
				'reason' => 'DSD on NADA is unchanged',
				'idno' => $idno,
				'codelists' => $codelists,
				'nada_dsd' => $nadaStatus,
			);
		}

		$response = $client->post_json('admin/data_structures/import_json', $payload);

		return array(
			'status' => 'success',
			'idno' => $idno,
			'overwrite' => (bool) $overwrite,
			'codelists' => $codelists,
			'response' => $response,
		);
	}

	/**
	 * True when the components of the DSD on NADA match the local registry DSD.
	 *
	 * @param Nada_catalog_client $client
	 * @param string $idno
	 * @param int    $local_structure_id
	 * @return bool
	 */
	public function nada_dsd_matches_local(Nada_catalog_client $client, $idno, $local_structure_id)
	{
		try {
			$response = $client->get('admin/data_structures/' . rawurlencode($idno) . '?with_components=1');
		} catch (Exception $e) {
			return false;
		}
		$remote = isset($response['result']['data_structure']['components'])
			? $response['result']['data_structure']['components']
			: (isset($response['data_structure']['components']) ? $response['data_structure']['components'] : null);
		if (!is_array($remote)) {
			return false;
		}
		$this->ci->load->model('Data_structure_component_model');
		$local = $this->ci->Data_structure_component_model->get_components_by_structure_id((int) $local_structure_id);
		return self::dsd_components_fingerprint($local) === self::dsd_components_fingerprint($remote);
	}

	/**
	 * Comparable fingerprint of DSD component rows (editor or NADA shape): order, name, label, description,
	 * column type, data type, whether a codelist is attached, and the metadata keys NADA keeps.
	 * Codelist identity is not compared (ids differ between the two registries; codelists are synced separately).
	 *
	 * @param array $components
	 * @return string
	 */
	public static function dsd_components_fingerprint(array $components)
	{
		$meta_keys = array('paired_time_column', 'value_label_column', 'attachment_level', 'assignment_status', 'display_role');
		$rows = array();
		foreach ($components as $c) {
			if (!is_array($c) || empty($c['name'])) {
				continue;
			}
			$meta = isset($c['metadata']) ? $c['metadata'] : null;
			if (is_string($meta) && $meta !== '') {
				$meta = json_decode($meta, true);
			}
			$kept = array();
			foreach ($meta_keys as $k) {
				if (is_array($meta) && isset($meta[$k]) && $meta[$k] !== '') {
					$kept[$k] = (string) $meta[$k];
				}
			}
			$rows[] = array(
				'sort'     => isset($c['sort_order']) ? (int) $c['sort_order'] : 0,
				'name'     => (string) $c['name'],
				'label'    => isset($c['label']) ? (string) $c['label'] : '',
				'desc'     => isset($c['description']) ? (string) $c['description'] : '',
				'type'     => isset($c['column_type']) ? (string) $c['column_type'] : '',
				'dtype'    => isset($c['data_type']) ? (string) $c['data_type'] : '',
				'codelist' => !empty($c['codelist_id']),
				'meta'     => $kept,
			);
		}
		usort($rows, function ($a, $b) {
			return $a['sort'] === $b['sort'] ? strcmp($a['name'], $b['name']) : $a['sort'] - $b['sort'];
		});
		foreach ($rows as &$r) {
			unset($r['sort']);
		}
		unset($r);
		return md5(json_encode($rows));
	}

	/**
	 * Import observations on NADA from a completed resumable upload_id.
	 *
	 * @param int $sid
	 * @param int $user_id
	 * @param int $catalog_connection_id
	 * @param string $nada_upload_id
	 * @return array
	 */
	public function import_indicator_data_from_nada_upload($sid, $user_id, $catalog_connection_id, $nada_upload_id)
	{
		$nada_upload_id = trim((string) $nada_upload_id);
		if ($nada_upload_id === '') {
			throw new Exception('nada_upload_id is required');
		}

		$this->ci->data_structure_util->resolve_project_reference($sid, true);

		$conn = $this->get_connection($user_id, $catalog_connection_id);
		$client = Nada_catalog_client::from_connection($conn);
		$context = $this->get_local_indicator_context($sid);

		if (empty($context['has_published_data'])) {
			throw new Exception('No published indicator data in DuckDB. Import data in the editor first.');
		}
		if (empty($context['study_idno'])) {
			throw new Exception('Study IDNO is not set');
		}
		if (empty($context['data_structure_reference']['idno'])) {
			throw new Exception('Project has no data_structure_reference idno');
		}

		$status = $client->get_resumable_upload_status($nada_upload_id);
		$uploadStatus = isset($status['upload_status']) ? $status['upload_status'] : '';
		if ($uploadStatus !== 'completed') {
			throw new Exception('NADA resumable upload is not completed');
		}

		$study_idno = (string) $context['study_idno'];
		$dsd_idno = trim((string) $context['data_structure_reference']['idno']);

		try {
			$result = $client->post_multipart(
				'admin/timeseries/data/' . rawurlencode($study_idno) . '/import',
				array(
					'idno' => $study_idno,
					'upload_id' => $nada_upload_id,
					'dsd_idno' => $dsd_idno,
					'delimiter' => ',',
					'ensure_unique_index' => '1',
				)
			);
		} catch (ApiRequestException $e) {
			$details = $e->getDetails();
			if (!is_array($details)) {
				$details = array();
			}
			$details['payload_idno'] = $study_idno;
			$details['payload_dsd_idno'] = $dsd_idno;
			throw new ApiRequestException(
				$e->getMessage() . ' (idno used in payload: ' . $study_idno . ')',
				$details
			);
		}

		return array(
			'status' => 'success',
			'study_idno' => $study_idno,
			'dsd_idno' => $dsd_idno,
			'upload_id' => $nada_upload_id,
			'response' => $result,
		);
	}

	/**
	 * Export DuckDB timeseries CSV, resumable-upload to NADA, import observations.
	 *
	 * @param int $sid
	 * @param int $user_id
	 * @param int $catalog_connection_id
	 * @return array
	 */
	public function publish_indicator_data($sid, $user_id, $catalog_connection_id)
	{
		$context = $this->get_local_indicator_context($sid);

		if (empty($context['has_published_data'])) {
			throw new Exception('No published indicator data in DuckDB. Import data in the editor first.');
		}

		$conn = $this->get_connection($user_id, $catalog_connection_id);
		$client = Nada_catalog_client::from_connection($conn);
		$csvBody = $this->ci->indicator_duckdb_service->timeseries_export_csv_body(
			$sid,
			$this->ci->Indicator_dsd_model->get_dsd_column_names_for_csv($sid)
		);

		$tmpPath = tempnam(sys_get_temp_dir(), 'me_nada_ts_');
		if ($tmpPath === false) {
			throw new Exception('Could not create temporary CSV file');
		}
		$csvPath = $tmpPath . '.csv';
		@unlink($tmpPath);
		if (@file_put_contents($csvPath, $csvBody) === false) {
			throw new Exception('Could not write DuckDB export CSV');
		}

		try {
			$uploadId = $client->upload_local_file_resumable(
				$csvPath,
				'indicator_timeseries_' . (int) $sid . '.csv'
			);

			return $this->import_indicator_data_from_nada_upload(
				$sid,
				$user_id,
				$catalog_connection_id,
				$uploadId
			);
		} finally {
			if (is_file($csvPath)) {
				@unlink($csvPath);
			}
		}
	}

	/**
	 * @param int $sid
	 * @param int $user_id
	 * @param int $catalog_connection_id
	 * @param array $options publish_dsd, dsd_overwrite, publish_indicator_data
	 * @return array
	 */
	public function publish_indicator_extras($sid, $user_id, $catalog_connection_id, array $options = array())
	{
		$out = array(
			'dsd' => null,
			'data' => null,
		);

		$publish_dsd = !empty($options['publish_dsd']);
		$publish_data = !empty($options['publish_indicator_data']);
		$dsd_overwrite = $this->parse_bool_option($options, 'dsd_overwrite', false);

		if ($publish_dsd) {
			$out['dsd'] = $this->publish_dsd(
				$sid,
				$user_id,
				$catalog_connection_id,
				$dsd_overwrite,
				$this->parse_bool_option($options, 'dsd_force', false)
			);
		}
		if ($publish_data) {
			if (!empty($options['nada_upload_id'])) {
				$out['data'] = $this->import_indicator_data_from_nada_upload(
					$sid,
					$user_id,
					$catalog_connection_id,
					$options['nada_upload_id']
				);
			} else {
				$out['data'] = $this->publish_indicator_data($sid, $user_id, $catalog_connection_id);
			}
		}

		return $out;
	}

	/**
	 * @param array  $options
	 * @param string $key
	 * @param bool   $default
	 * @return bool
	 */
	private function parse_bool_option(array $options, $key, $default = false)
	{
		if (!array_key_exists($key, $options)) {
			return (bool) $default;
		}

		$value = $options[$key];
		if (is_bool($value)) {
			return $value;
		}
		if (is_int($value) || is_float($value)) {
			return ((int) $value) === 1;
		}
		if (is_string($value)) {
			$normalized = strtolower(trim($value));
			if (in_array($normalized, array('1', 'true', 'yes', 'on'), true)) {
				return true;
			}
			if (in_array($normalized, array('0', 'false', 'no', 'off', ''), true)) {
				return false;
			}
		}

		return (bool) $value;
	}

	/**
	 * @param int $user_id
	 * @param int $catalog_connection_id
	 * @return array
	 */
	private function get_connection($user_id, $catalog_connection_id)
	{
		return $this->ci->Catalog_connections_model->require_connection($user_id, $catalog_connection_id);
	}
}
