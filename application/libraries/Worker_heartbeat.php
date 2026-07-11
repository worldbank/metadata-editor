<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Worker heartbeat helper
 *
 * Updates the worker heartbeat file while long-running job handlers block the
 * React event loop (so periodic timers in Worker.php do not run).
 */
class Worker_heartbeat
{
	/**
	 * Touch the worker heartbeat file.
	 *
	 * @param string|null $worker_id Optional worker id; preserved from pid/heartbeat files when omitted
	 * @return bool
	 */
	public static function touch($worker_id = null)
	{
		$paths = self::resolve_paths();
		if ($paths === null) {
			return false;
		}

		if ($worker_id === null || $worker_id === '') {
			$worker_id = self::resolve_worker_id($paths['pid_file'], $paths['heartbeat_file']);
		}

		$data = array(
			'worker_id' => $worker_id,
			'pid' => getmypid(),
			'timestamp' => time(),
			'datetime' => date('Y-m-d H:i:s'),
		);

		return @file_put_contents($paths['heartbeat_file'], json_encode($data, JSON_PRETTY_PRINT)) !== false;
	}

	/**
	 * Resolve heartbeat and pid file paths from editor storage config.
	 *
	 * @return array|null { heartbeat_file, pid_file }
	 */
	public static function resolve_paths()
	{
		$ci =& get_instance();
		$ci->load->config('editor');
		$storage_path = $ci->config->item('storage_path', 'editor');
		if (empty($storage_path)) {
			return null;
		}

		$tmp_path = rtrim($storage_path, '/') . '/tmp';
		return array(
			'heartbeat_file' => $tmp_path . '/worker.heartbeat',
			'pid_file' => $tmp_path . '/worker.pid',
		);
	}

	/**
	 * @param string $pid_file
	 * @param string $heartbeat_file
	 * @return string
	 */
	private static function resolve_worker_id($pid_file, $heartbeat_file)
	{
		foreach (array($heartbeat_file, $pid_file) as $file) {
			if (!file_exists($file)) {
				continue;
			}
			$content = @file_get_contents($file);
			if ($content === false || $content === '') {
				continue;
			}
			$decoded = json_decode($content, true);
			if (is_array($decoded) && !empty($decoded['worker_id'])) {
				return (string) $decoded['worker_id'];
			}
		}

		return 'worker-' . getmypid() . '-' . time();
	}
}
