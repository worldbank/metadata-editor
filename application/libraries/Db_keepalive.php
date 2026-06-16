<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Keep MySQL connections alive during long-running CLI jobs (worker, imports).
 *
 * Uses SELECT 1 to reset server idle timeout and reconnect()/initialize() when the
 * socket is already dead.
 */
class Db_keepalive
{
	/** Default interval (seconds) between keepalive pings in long loops */
	const DEFAULT_INTERVAL = 300;

	/**
	 * Ping the database: reconnect if needed, then run SELECT 1.
	 *
	 * @param CI_DB_query_builder|null $db Database instance (defaults to CI db)
	 * @return bool True if the connection responds
	 */
	public function ping($db = null)
	{
		$db = $db ?: get_instance()->db;

		$db->reconnect();

		if ( ! $db->conn_id)
		{
			if ( ! $db->initialize())
			{
				log_message('error', 'Db_keepalive: failed to initialize database connection');
				return FALSE;
			}
		}

		if ($db->query('SELECT 1') !== FALSE)
		{
			return TRUE;
		}

		$db->conn_id = FALSE;

		if ( ! $db->initialize() || $db->query('SELECT 1') === FALSE)
		{
			log_message('error', 'Db_keepalive: ping failed after reconnect');
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Ping only if at least $interval seconds have passed since $last_ping_at.
	 *
	 * @param int|null $last_ping_at Unix timestamp of last ping; updated when a ping runs
	 * @param int $interval Seconds between pings
	 * @param CI_DB_query_builder|null $db Database instance
	 * @return bool True if a ping was performed and succeeded
	 */
	public function ping_if_due(&$last_ping_at, $interval = self::DEFAULT_INTERVAL, $db = null)
	{
		$now = time();

		if ($last_ping_at !== null && ($now - $last_ping_at) < $interval)
		{
			return FALSE;
		}

		if ($this->ping($db))
		{
			$last_ping_at = $now;
			return TRUE;
		}

		return FALSE;
	}
}
