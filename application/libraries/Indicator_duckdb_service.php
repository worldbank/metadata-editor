<?php defined('BASEPATH') OR exit('No direct script access allowed');

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;

/**
 * PHP → FastAPI bridge for indicator DuckDB (timeseries import, charts, job poll).
 *
 * Base URL: application/config/editor.php → editor.data_api_url (EDITOR_DATA_API_URL).
 */
class Indicator_duckdb_service {

	/** @var CI_Controller */
	protected $ci;

	/** @var string trailing slash */
	protected $base_url;

	public function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->config('editor');
		$configured = $this->ci->config->item('data_api_url', 'editor');
		$this->base_url = rtrim($configured ? $configured : 'http://localhost:8000/', '/') . '/';
	}

	/**
	 * True when row is a positional list (0..n-1) so we can map indices to timeseries column metadata.
	 *
	 * @param array $row
	 * @return bool
	 */
	protected function timeseries_row_is_positional_list(array $row)
	{
		if ($row === array()) {
			return true;
		}
		$keys = array_keys($row);
		$n = count($keys);
		for ($i = 0; $i < $n; $i++) {
			if (!isset($keys[$i]) || $keys[$i] !== $i) {
				return false;
			}
		}

		return true;
	}

	/**
	 * One cell from a timeseries/page row: exact key, case-insensitive name, or column index from metadata.
	 *
	 * @param array $row
	 * @param string $column_name Physical column name
	 * @param array|null $columns_meta page["columns"] list of [ "name" => ... ]
	 * @return mixed|null
	 */
	public function timeseries_row_get_cell(array $row, $column_name, $columns_meta = null)
	{
		$column_name = trim((string) $column_name);
		if ($column_name === '') {
			return null;
		}
		if (array_key_exists($column_name, $row)) {
			return $row[$column_name];
		}
		$want = strtoupper($column_name);
		foreach ($row as $k => $v) {
			if (is_string($k) && strtoupper(trim($k)) === $want) {
				return $v;
			}
		}
		if (is_array($columns_meta) && $this->timeseries_row_is_positional_list($row)) {
			foreach ($columns_meta as $i => $col) {
				if (!is_array($col)) {
					continue;
				}
				$nm = isset($col['name']) ? trim((string) $col['name']) : '';
				if ($nm !== '' && strtoupper($nm) === $want && array_key_exists((int) $i, $row)) {
					return $row[(int) $i];
				}
			}
		}

		return null;
	}

	/**
	 * Decode one distinct-pairs API row (tolerate alternate JSON keys).
	 *
	 * @param array $row
	 * @return array|null [ 'code' => string, 'label' => string ]
	 */
	protected function distinct_pair_decode_api_row(array $row)
	{
		$code = '';
		foreach (array('code', 'Code', 'CODE', 'value', 'Value', 'VALUE') as $k) {
			if (array_key_exists($k, $row) && $row[$k] !== null && trim((string) $row[$k]) !== '') {
				$code = trim((string) $row[$k]);
				break;
			}
		}
		if ($code === '') {
			return null;
		}
		$label = $code;
		foreach (array('label', 'Label', 'LABEL') as $k) {
			if (array_key_exists($k, $row) && $row[$k] !== null && trim((string) $row[$k]) !== '') {
				$label = trim((string) $row[$k]);
				break;
			}
		}

		return array('code' => $code, 'label' => $label);
	}

	/**
	 * Distinct codes from column-stats freq (fixes empty distinct-pairs when DuckDB returns one value or odd shapes).
	 *
	 * @param int $sid
	 * @param string $code_column
	 * @param string|null $label_column
	 * @param int $limit
	 * @return array same shape as timeseries_distinct_pairs_scan
	 */
	protected function timeseries_distinct_pairs_from_column_stats($sid, $code_column, $label_column, $limit)
	{
		$cols = array($code_column);
		if ($label_column !== null && trim((string) $label_column) !== '') {
			$cols[] = trim((string) $label_column);
		}
		$res = $this->timeseries_column_stats($sid, $cols);
		if (!is_array($res) || !empty($res['error'])) {
			return array(
				'error' => true,
				'message' => isset($res['message']) ? $res['message'] : 'column-stats failed',
				'items' => array(),
				'truncated' => false,
				'source' => 'column_stats',
			);
		}
		$by_field = array();
		foreach ($res['columns'] ?? array() as $c) {
			if (!is_array($c) || empty($c['field'])) {
				continue;
			}
			$by_field[strtoupper(trim((string) $c['field']))] = $c;
		}
		$cu = strtoupper(trim((string) $code_column));
		if (!isset($by_field[$cu])) {
			return array('items' => array(), 'truncated' => false, 'source' => 'column_stats');
		}
		$freq = isset($by_field[$cu]['freq']) && is_array($by_field[$cu]['freq']) ? $by_field[$cu]['freq'] : array();
		$items = array();
		$n = 0;
		foreach ($freq as $fr) {
			if (!is_array($fr) || $n >= $limit) {
				break;
			}
			$val = isset($fr['value']) ? trim((string) $fr['value']) : '';
			if ($val === '') {
				continue;
			}
			$items[] = array('code' => $val, 'label' => $val);
			$n++;
		}
		if ($label_column !== null && trim((string) $label_column) !== '') {
			$lu = strtoupper(trim((string) $label_column));
			if (isset($by_field[$lu])) {
				$lf = isset($by_field[$lu]['freq']) && is_array($by_field[$lu]['freq']) ? $by_field[$lu]['freq'] : array();
				if (count($lf) === 1 && isset($lf[0]['value'])) {
					$lab = trim((string) $lf[0]['value']);
					if ($lab !== '') {
						foreach ($items as $i => $it) {
							$items[$i]['label'] = $lab;
						}
					}
				}
			}
		}
		$dc = isset($by_field[$cu]['distinct_count']) ? (int) $by_field[$cu]['distinct_count'] : count($items);
		$non_null = isset($by_field[$cu]['non_null_count']) ? (int) $by_field[$cu]['non_null_count'] : 0;
		if (count($items) === 0 && ($dc > 0 || $non_null > 0)) {
			$page = $this->timeseries_page($sid, 0, 1);
			if (is_array($page) && empty($page['error']) && !empty($page['rows'][0]) && is_array($page['rows'][0])) {
				$meta = isset($page['columns']) && is_array($page['columns']) ? $page['columns'] : null;
				$raw_c = $this->timeseries_row_get_cell($page['rows'][0], $code_column, $meta);
				if ($raw_c !== null && trim((string) $raw_c) !== '') {
					$cv = trim((string) $raw_c);
					$lv = $cv;
					if ($label_column !== null && trim((string) $label_column) !== '') {
						$raw_l = $this->timeseries_row_get_cell($page['rows'][0], $label_column, $meta);
						if ($raw_l !== null && trim((string) $raw_l) !== '') {
							$lv = trim((string) $raw_l);
						}
					}
					$items[] = array('code' => $cv, 'label' => $lv);
				}
			}
		}
		$truncated = $dc > count($items);

		return array(
			'items' => $items,
			'truncated' => $truncated,
			'source' => 'column_stats',
		);
	}

	/**
	 * Distinct (code, label) pairs from project_{sid}.timeseries.
	 *
	 * Preferred: FastAPI GET timeseries/indicators/timeseries/distinct-pairs (see docs/dsd-duckdb.md).
	 * Fallback: scan paginated timeseries/page (slower; capped rows).
	 *
	 * @param int $sid
	 * @param string $code_column physical column name in timeseries
	 * @param string|null $label_column physical column name or null to use code as label
	 * @param int $limit max distinct codes (cap)
	 * @return array { items: [ ['code'=>,'label'=>], ... ], truncated: bool, source: 'fastapi'|'scan'|'column_stats' } or error shape
	 */
	public function timeseries_distinct_pairs($sid, $code_column, $label_column = null, $limit = 5000)
	{
		$code_column = trim((string) $code_column);
		if ($code_column === '') {
			return array('error' => true, 'message' => 'code_column is required');
		}
		$limit = max(1, min(20000, (int) $limit));
		$label_column = $label_column !== null && trim((string) $label_column) !== ''
			? trim((string) $label_column) : null;

		$fast = $this->timeseries_distinct_pairs_fastapi($sid, $code_column, $label_column, $limit);
		if (is_array($fast) && empty($fast['error']) && !empty($fast['items'])) {
			return $fast;
		}

		$scan = $this->timeseries_distinct_pairs_scan($sid, $code_column, $label_column, $limit);
		if (is_array($scan) && empty($scan['error']) && !empty($scan['items'])) {
			return $scan;
		}

		$stats = $this->timeseries_distinct_pairs_from_column_stats($sid, $code_column, $label_column, $limit);
		if (is_array($stats) && empty($stats['error']) && !empty($stats['items'])) {
			return $stats;
		}

		if (is_array($scan) && empty($scan['error'])) {
			return $scan;
		}
		if (is_array($fast) && empty($fast['error'])) {
			return $fast;
		}

		return is_array($scan) ? $scan : $fast;
	}

	/**
	 * @return array
	 */
	protected function timeseries_distinct_pairs_fastapi($sid, $code_column, $label_column, $limit)
	{
		$url = $this->base_url . 'timeseries/indicators/timeseries/distinct-pairs';
		$client = new Client(array('timeout' => 180, 'http_errors' => false));
		$query = array(
			'project_id' => (string) (int) $sid,
			'code_column' => $code_column,
			'limit' => (int) $limit,
		);
		if ($label_column !== null) {
			$query['label_column'] = $label_column;
		}
		try {
			$response = $client->request('GET', $url, array('query' => $query));
			$code = (int) $response->getStatusCode();
			$raw = (string) $response->getBody();
			$body = json_decode($raw, true);
			if ($code === 404) {
				return array('error' => true, 'message' => 'distinct-pairs not implemented', 'http_code' => 404);
			}
			if ($code !== 200) {
				$msg = is_array($body) && isset($body['detail']) ? $body['detail'] : $raw;

				return array(
					'error' => true,
					'message' => $msg !== '' ? $msg : 'HTTP ' . $code,
					'http_code' => $code,
				);
			}
			if (!is_array($body)) {
				return array('error' => true, 'message' => 'Invalid JSON from distinct-pairs');
			}
			$items = array();
			if (!empty($body['items']) && is_array($body['items'])) {
				foreach ($body['items'] as $row) {
					if (!is_array($row)) {
						continue;
					}
					$pair = $this->distinct_pair_decode_api_row($row);
					if ($pair !== null) {
						$items[] = $pair;
					}
				}
			}
			$truncated = !empty($body['truncated']);

			return array(
				'items' => $items,
				'truncated' => $truncated,
				'source' => 'fastapi',
			);
		}
		catch (RequestException $e) {
			$raw = $e->hasResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();

			return array('error' => true, 'message' => $raw, 'http_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0);
		}
	}

	/**
	 * Fallback: paginate timeseries until enough distinct codes or row cap.
	 *
	 * @return array
	 */
	protected function timeseries_distinct_pairs_scan($sid, $code_column, $label_column, $limit)
	{
		$max_rows = 250000;
		$page_limit = 200;
		$offset = 0;
		$code_to_label = array();
		$rows_seen = 0;
		$total = null;
		$rows = array();

		while ($rows_seen < $max_rows && count($code_to_label) < $limit) {
			$page = $this->timeseries_page($sid, $offset, $page_limit);
			if (!is_array($page) || !empty($page['error'])) {
				return array(
					'error' => true,
					'message' => isset($page['message']) ? $page['message'] : 'Timeseries page failed during scan',
				);
			}
			if ($total === null && isset($page['total_row_count'])) {
				$total = (int) $page['total_row_count'];
			}
			$rows = isset($page['rows']) && is_array($page['rows']) ? $page['rows'] : array();
			$columns_meta = isset($page['columns']) && is_array($page['columns']) ? $page['columns'] : null;
			if (count($rows) === 0) {
				break;
			}
			foreach ($rows as $row) {
				if (!is_array($row)) {
					continue;
				}
				$rows_seen++;
				$raw_code = $this->timeseries_row_get_cell($row, $code_column, $columns_meta);
				if ($raw_code === null) {
					continue;
				}
				$code = trim((string) $raw_code);
				if ($code === '') {
					continue;
				}
				if ($label_column !== null) {
					$raw_lab = $this->timeseries_row_get_cell($row, $label_column, $columns_meta);
					if ($raw_lab !== null && trim((string) $raw_lab) !== '') {
						$lab = trim((string) $raw_lab);
					} else {
						$lab = $code;
					}
				} else {
					$lab = $code;
				}
				if (!isset($code_to_label[$code])) {
					$code_to_label[$code] = $lab;
				}
				if (count($code_to_label) >= $limit) {
					break 2;
				}
			}
			$offset += count($rows);
			if ($total !== null && $offset >= $total) {
				break;
			}
			if (count($rows) < $page_limit) {
				break;
			}
		}

		$items = array();
		foreach ($code_to_label as $c => $lb) {
			$items[] = array('code' => $c, 'label' => $lb);
		}
		$table_incomplete = ($total !== null && $offset < $total) || ($total === null && isset($rows) && count($rows) >= $page_limit);
		$truncated = count($code_to_label) >= $limit
			|| ($rows_seen >= $max_rows && $table_incomplete);

		return array(
			'items' => $items,
			'truncated' => $truncated,
			'source' => 'scan',
		);
	}

	/**
	 * @param array<string, string[]>|null $filters Physical column name => list of values (trimmed string IN filter); null = no filter
	 */
	public function timeseries_page($sid, $offset = 0, $limit = 50, $filters = null)
	{
		$url = $this->base_url . 'timeseries/indicators/timeseries/page';
		$client = new Client(array('timeout' => 120, 'http_errors' => false));

		$query = array(
			'project_id' => (string) (int) $sid,
			'offset' => (int) $offset,
			'limit' => (int) $limit,
		);
		if (is_array($filters) && count($filters) > 0) {
			$query['filters'] = json_encode($filters);
		}

		try {
			$response = $client->request('GET', $url, array(
				'query' => $query,
			));

			$code = (int) $response->getStatusCode();
			$raw = (string) $response->getBody();
			$body = json_decode($raw, true);

			if ($code !== 200) {
				$msg = is_array($body) && isset($body['detail']) ? $body['detail'] : $raw;

				return array(
					'error' => true,
					'message' => $msg !== '' ? $msg : 'HTTP ' . $code,
					'http_code' => $code,
				);
			}

			return is_array($body) ? $body : array('error' => true, 'message' => 'Invalid JSON', 'http_code' => $code);
		}
		catch (TransferException $e) {
			$raw = $e->getMessage();
			if ($e instanceof RequestException && $e->hasResponse()) {
				$raw = (string) $e->getResponse()->getBody();
			}

			return array(
				'error' => true,
				'message' => $raw,
				'http_code' => ($e instanceof RequestException && $e->hasResponse())
					? $e->getResponse()->getStatusCode()
					: 0,
			);
		}
	}

	/**
	 * Column summary statistics on project_{sid}.timeseries (DuckDB via FastAPI).
	 *
	 * GET timeseries/indicators/timeseries/column-stats
	 * Missing cell: NULL or trim(cast AS VARCHAR) = ''.
	 *
	 * @param int $sid
	 * @param string[]|null $columns Physical column names; null or empty = all columns in timeseries
	 * @return array { project_id, source, computed_at, columns: [...] } or [ 'error' => true, ... ]
	 */
	public function timeseries_column_stats($sid, $columns = null)
	{
		$url = $this->base_url . 'timeseries/indicators/timeseries/column-stats';
		$client = new Client(array('timeout' => 300, 'http_errors' => false));
		$query = array(
			'project_id' => (string) (int) $sid,
		);
		if (is_array($columns) && count($columns) > 0) {
			$query['columns'] = implode(',', $columns);
		}

		try {
			$response = $client->request('GET', $url, array('query' => $query));
			$code = (int) $response->getStatusCode();
			$raw = (string) $response->getBody();
			$body = json_decode($raw, true);
			if ($code !== 200) {
				$msg = is_array($body) && isset($body['detail']) ? $body['detail'] : $raw;

				return array(
					'error' => true,
					'message' => $msg !== '' ? $msg : 'HTTP ' . $code,
					'http_code' => $code,
				);
			}

			return is_array($body) ? $body : array('error' => true, 'message' => 'Invalid JSON', 'http_code' => $code);
		}
		catch (RequestException $e) {
			$raw = $e->hasResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();

			return array(
				'error' => true,
				'message' => $raw,
				'http_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0,
			);
		}
	}

	/**
	 * Full timeseries table as CSV bytes (FastAPI export).
	 *
	 * @param int $sid
	 * @return string
	 * @throws Exception on HTTP or network error
	 */
	public function timeseries_export_csv_body($sid)
	{
		$url = $this->base_url . 'timeseries/indicators/timeseries/export';
		$client = new Client(array('timeout' => 600, 'http_errors' => false));
		$response = $client->request('GET', $url, array(
			'query' => array(
				'project_id' => (string) (int) $sid,
			),
		));
		$code = (int) $response->getStatusCode();
		if ($code !== 200) {
			$raw = (string) $response->getBody();
			$dec = json_decode($raw, true);
			$msg = is_array($dec) && isset($dec['detail']) ? $dec['detail'] : $raw;

			throw new Exception($msg !== '' ? $msg : 'Timeseries export HTTP ' . $code);
		}

		return (string) $response->getBody();
	}

	/**
	 * Queue recomputation of _ts_year / _ts_freq on existing project_{sid}.timeseries.
	 *
	 * @param int $sid
	 * @param array $time_spec from Indicator_dsd_model::build_duckdb_promote_time_spec (must include time_column)
	 * @return array Decoded JSON (job_id, …) or error envelope
	 */
	public function recompute_queue($sid, array $time_spec)
	{
		$url = $this->base_url . 'timeseries/indicators/timeseries/recompute-queue';
		$body = array(
			'project_id' => (string) (int) $sid,
			'time_spec' => $time_spec,
		);

		return $this->post_json($url, $body);
	}

	/**
	 * Distinct values in a CSV column without loading staging (indicator_id picker).
	 *
	 * @param int $sid
	 * @param string $csv_path
	 * @param string $column
	 * @param string $delimiter
	 * @param int $limit
	 * @return array
	 */
	public function csv_distinct($sid, $csv_path, $column, $delimiter = ',', $limit = 3000)
	{
		$url = $this->base_url . 'timeseries/indicators/csv/distinct';
		$client = new Client(array('timeout' => 120));

		try {
			$response = $client->request('GET', $url, array(
				'query' => array(
					'project_id' => (string) (int) $sid,
					'csv_path' => $csv_path,
					'column' => $column,
					'delimiter' => $delimiter !== '' ? $delimiter[0] : ',',
					'limit' => (int) $limit,
				),
			));

			return json_decode($response->getBody()->getContents(), true);
		}
		catch (RequestException $e) {
			$raw = $e->hasResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();

			return array(
				'error' => true,
				'message' => $raw,
				'http_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0,
			);
		}
	}

	/**
	 * Validate CSV header set matches expected DSD column names exactly (case-insensitive).
	 * Prefer Indicator_dsd_model::validate_csv_headers_for_dsd() for import (allows extra CSV columns).
	 *
	 * @param int $sid
	 * @param string $csv_path
	 * @param string[] $expected_column_names
	 * @param string $delimiter
	 * @return array
	 */
	public function csv_validate_headers($sid, $csv_path, array $expected_column_names, $delimiter = ',')
	{
		$url = $this->base_url . 'timeseries/indicators/csv/validate-headers';
		$client = new Client(array('timeout' => 60));

		try {
			$response = $client->request('GET', $url, array(
				'query' => array(
					'project_id' => (string) (int) $sid,
					'csv_path' => $csv_path,
					'expected_columns' => implode(',', array_map('strval', $expected_column_names)),
					'delimiter' => $delimiter !== '' ? $delimiter[0] : ',',
				),
			));

			return json_decode($response->getBody()->getContents(), true);
		}
		catch (RequestException $e) {
			$raw = $e->hasResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();

			return array(
				'error' => true,
				'message' => $raw,
				'http_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0,
			);
		}
	}

	/**
	 * Replace project timeseries from CSV (validate headers, filter by indicator_value). Returns job_id.
	 *
	 * @param int $sid
	 * @param string $csv_path
	 * @param string[] $expected_column_names
	 * @param string $indicator_column
	 * @param string $indicator_value
	 * @param array|null $time_spec
	 * @param string $delimiter
	 * @return array
	 */
	public function timeseries_replace_from_csv_queue(
		$sid,
		$csv_path,
		array $expected_column_names,
		$indicator_column,
		$indicator_value,
		$time_spec = null,
		$delimiter = ','
	) {
		$url = $this->base_url . 'timeseries/indicators/timeseries/replace-from-csv-queue';
		$body = array(
			'project_id' => (string) (int) $sid,
			'csv_path' => $csv_path,
			'delimiter' => $delimiter !== '' ? $delimiter[0] : ',',
			'expected_columns' => array_map(function ($name) {
				return array('name' => (string) $name);
			}, $expected_column_names),
			'indicator_column' => $indicator_column,
			'indicator_value' => (string) $indicator_value,
		);
		if (is_array($time_spec) && !empty($time_spec['time_column'])) {
			$body['time_spec'] = $time_spec;
		}

		return $this->post_json($url, $body);
	}

	/**
	 * Queue export of project_{sid}.timeseries to indicator_data.csv on shared filesystem.
	 *
	 * FastAPI: POST timeseries/indicators/timeseries/export-to-file-queue
	 *
	 * @param int $sid
	 * @param string $output_csv_path Absolute path to data/indicator_data.csv
	 * @return array Decoded JSON (job_id, …) or error envelope
	 */
	public function timeseries_export_to_file_queue($sid, $output_csv_path)
	{
		$url = $this->base_url . 'timeseries/indicators/timeseries/export-to-file-queue';
		$body = array(
			'project_id' => (string) (int) $sid,
			'output_csv_path' => (string) $output_csv_path,
		);

		return $this->post_json($url, $body);
	}

	/**
	 * Drop project_{sid}.timeseries in DuckDB (delete all published indicator data).
	 *
	 * FastAPI: DELETE timeseries/indicators/timeseries?project_id=
	 *
	 * @param int $sid
	 * @return array Decoded JSON on 2xx (includes dropped, row_count), or [ 'error' => true, 'message' => ..., 'http_code' => int ]
	 */
	public function timeseries_drop($sid)
	{
		$url = $this->base_url . 'timeseries/indicators/timeseries';
		$client = new Client(array('timeout' => 60, 'http_errors' => false));

		try {
			$response = $client->request('DELETE', $url, array(
				'query' => array(
					'project_id' => (string) (int) $sid,
				),
			));

			$code = (int) $response->getStatusCode();
			$raw = (string) $response->getBody();
			$body = json_decode($raw, true);

			if ($code >= 200 && $code < 300) {
				return is_array($body) ? $body : array('ok' => true);
			}

			return array(
				'error' => true,
				'message' => is_array($body) && isset($body['detail']) ? $body['detail'] : ($raw !== '' ? $raw : 'HTTP ' . $code),
				'http_code' => $code,
			);
		}
		catch (TransferException $e) {
			$raw = $e->getMessage();
			if ($e instanceof RequestException && $e->hasResponse()) {
				$raw = (string) $e->getResponse()->getBody();
			}

			return array(
				'error' => true,
				'message' => $raw,
				'http_code' => ($e instanceof RequestException && $e->hasResponse())
					? $e->getResponse()->getStatusCode()
					: 0,
			);
		}
	}

	/**
	 * GET /jobs/{job_id} — returns job envelope or throws on transport error.
	 *
	 * Note: FastAPI returns 400 when job status is error; 404 when missing.
	 *
	 * @param string $job_id
	 * @return array keys: success (bool), http_code (int), body (array|null), message (string|null)
	 */
	public function get_job($job_id)
	{
		$url = $this->base_url . 'jobs/' . rawurlencode($job_id);
		$client = new Client(array('timeout' => 60, 'http_errors' => false));
		$response = $client->request('GET', $url);
		$code = (int) $response->getStatusCode();
		$raw = (string) $response->getBody();
		$body = json_decode($raw, true);

		if ($code === 200) {
			return array(
				'success' => true,
				'http_code' => $code,
				'body' => is_array($body) ? $body : null,
				'message' => null,
			);
		}

		$msg = is_array($body) && isset($body['detail']) ? $body['detail'] : $raw;

		return array(
			'success' => false,
			'http_code' => $code,
			'body' => is_array($body) ? $body : null,
			'message' => $msg,
		);
	}

	/**
	 * Poll until job reaches done/error, timeout, or HTTP failure.
	 *
	 * @param string $job_id
	 * @param int $max_wait_seconds
	 * @param int $interval_seconds
	 * @return array Job-like structure: status, data (if done), error/message if failed
	 */
	public function poll_job($job_id, $max_wait_seconds = 600, $interval_seconds = 2)
	{
		$deadline = time() + (int) $max_wait_seconds;
		$interval_seconds = max(1, (int) $interval_seconds);

		while (time() < $deadline) {
			$res = $this->get_job($job_id);
			$code = (int) $res['http_code'];

			if ($code === 200 && is_array($res['body'])) {
				$st = isset($res['body']['status']) ? $res['body']['status'] : null;
				if ($st === 'done') {
					return $res['body'];
				}
				if ($st === 'error') {
					return array(
						'status' => 'error',
						'error' => isset($res['body']['error']) ? $res['body']['error'] : 'Job failed',
						'error_details' => isset($res['body']['error_details']) ? $res['body']['error_details'] : null,
					);
				}
				if ($st === 'queued' || $st === 'processing') {
					sleep($interval_seconds);
					continue;
				}
			}

			if ($code === 400) {
				return array(
					'status' => 'error',
					'error' => !empty($res['message']) ? $res['message'] : 'FastAPI job error',
				);
			}

			if ($code === 404) {
				return array('status' => 'error', 'error' => 'Job not found');
			}

			if ($code !== 200) {
				return array(
					'status' => 'error',
					'error' => !empty($res['message']) ? $res['message'] : 'Job request failed',
				);
			}

			sleep($interval_seconds);
		}

		return array('status' => 'error', 'error' => 'Job poll timeout');
	}

	protected function post_json($url, array $body)
	{
		try {
			$client = new Client(array('timeout' => 60));
			$response = $client->request('POST', $url, array(
				'json' => $body,
				'headers' => array('Content-Type' => 'application/json'),
			));

			return json_decode($response->getBody()->getContents(), true);
		}
		catch (RequestException $e) {
			$raw = $e->hasResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();

			return array(
				'error' => true,
				'message' => $raw,
				'http_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0,
			);
		}
	}

	/**
	 * Chart observation rows from project_{sid}.timeseries (DuckDB via FastAPI; one row per SDMX key, no SUM/AVG).
	 * POST timeseries/indicators/timeseries/chart-aggregate — canonical impl: metadata-editor-fastapi/src/routers/timeseries.py (indicator_timeseries_chart_aggregate).
	 *
	 * @param int $sid
	 * @param array $body request payload (project_id overwritten)
	 * @return array Decoded JSON or [ 'error' => true, 'message' => ..., 'http_code' => int ]
	 */
	public function timeseries_chart_aggregate($sid, array $body)
	{
		$url = $this->base_url . 'timeseries/indicators/timeseries/chart-aggregate';
		$body['project_id'] = (string) (int) $sid;
		$client = new Client(array('timeout' => 180, 'http_errors' => false));

		try {
			$response = $client->request('POST', $url, array(
				'json' => $body,
				'headers' => array('Content-Type' => 'application/json'),
			));
			$code = (int) $response->getStatusCode();
			$raw = (string) $response->getBody();
			$decoded = json_decode($raw, true);
			if ($code !== 200) {
				$msg = is_array($decoded) && isset($decoded['detail']) ? $decoded['detail'] : $raw;

				return array(
					'error' => true,
					'message' => $msg !== '' ? $msg : 'HTTP ' . $code,
					'http_code' => $code,
				);
			}

			return is_array($decoded) ? $decoded : array('error' => true, 'message' => 'Invalid JSON', 'http_code' => $code);
		}
		catch (RequestException $e) {
			$raw = $e->hasResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();

			return array(
				'error' => true,
				'message' => $raw,
				'http_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0,
			);
		}
	}

	/**
	 * SDMX observation-key uniqueness on full published timeseries (DuckDB aggregates only; no row paging).
	 * POST timeseries/indicators/timeseries/observation-key-validate — metadata-editor-fastapi indicator_timeseries_observation_key_validate.
	 *
	 * @param int $sid
	 * @param array $body time_column, value_column, slice_columns (physical names; project_id overwritten)
	 * @return array Decoded JSON or [ 'error' => true, 'message' => ..., 'http_code' => int ]
	 */
	public function timeseries_observation_key_validate($sid, array $body)
	{
		$url = $this->base_url . 'timeseries/indicators/timeseries/observation-key-validate';
		$body['project_id'] = (string) (int) $sid;
		$client = new Client(array('timeout' => 300, 'http_errors' => false));

		try {
			$response = $client->request('POST', $url, array(
				'json' => $body,
				'headers' => array('Content-Type' => 'application/json'),
			));
			$code = (int) $response->getStatusCode();
			$raw = (string) $response->getBody();
			$decoded = json_decode($raw, true);
			if ($code !== 200) {
				$msg = is_array($decoded) && isset($decoded['detail']) ? $decoded['detail'] : $raw;

				return array(
					'error' => true,
					'message' => is_string($msg) ? $msg : json_encode($msg),
					'http_code' => $code,
				);
			}

			return is_array($decoded) ? $decoded : array('error' => true, 'message' => 'Invalid JSON', 'http_code' => $code);
		}
		catch (RequestException $e) {
			$raw = $e->hasResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();

			return array(
				'error' => true,
				'message' => $raw,
				'http_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0,
			);
		}
	}

	/**
	 * Dataset-wide value → row counts per physical column (chart filter labels on page load).
	 * POST timeseries/indicators/timeseries/facet-value-counts.
	 *
	 * @param int $sid
	 * @param array $body must include columns (string[])
	 * @return array Decoded JSON or [ 'error' => true, ... ]
	 */
	public function timeseries_facet_value_counts($sid, array $body)
	{
		$url = $this->base_url . 'timeseries/indicators/timeseries/facet-value-counts';
		$body['project_id'] = (string) (int) $sid;
		$client = new Client(array('timeout' => 120, 'http_errors' => false));

		try {
			$response = $client->request('POST', $url, array(
				'json' => $body,
				'headers' => array('Content-Type' => 'application/json'),
			));
			$code = (int) $response->getStatusCode();
			$raw = (string) $response->getBody();
			$decoded = json_decode($raw, true);
			if ($code !== 200) {
				$msg = is_array($decoded) && isset($decoded['detail']) ? $decoded['detail'] : $raw;

				return array(
					'error' => true,
					'message' => $msg !== '' ? $msg : 'HTTP ' . $code,
					'http_code' => $code,
				);
			}

			return is_array($decoded) ? $decoded : array('error' => true, 'message' => 'Invalid JSON', 'http_code' => $code);
		}
		catch (RequestException $e) {
			$raw = $e->hasResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();

			return array(
				'error' => true,
				'message' => $raw,
				'http_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0,
			);
		}
	}
}

