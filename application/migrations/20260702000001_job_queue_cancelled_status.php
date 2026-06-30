<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Add 'cancelled' status to job_queue for user-initiated job cancellation.
 *
 * Applies install/schema-job-queue-cancelled.sql:
 * - job_queue.status enum includes 'cancelled'
 *
 * Used by POST /api/jobs/cancel/{uuid} and metadata assessment cancel flow.
 */
class Migration_Job_queue_cancelled_status extends MY_Migration {

	public function up()
	{
		log_message('info', 'Migration_Job_queue_cancelled_status::up()');

		if (!$this->db->table_exists('job_queue')) {
			log_message('info', 'job_queue table missing; skipping cancelled status migration');
			echo "⊘ SKIPPED: job_queue table does not exist\n";
			return;
		}

		if ($this->job_queue_has_cancelled_status()) {
			log_message('info', 'job_queue.status already includes cancelled; skipping');
			echo "⊘ SKIPPED: cancelled status already present on job_queue.status\n";
			return;
		}

		$sql_file = $this->get_sql_file_path('schema-job-queue-cancelled');
		if (!file_exists($sql_file)) {
			throw new Exception('SQL file not found: ' . $sql_file);
		}

		$this->execute_sql_file($sql_file);

		log_message('info', 'Migration_Job_queue_cancelled_status completed successfully');
	}

	/**
	 * @return bool
	 */
	private function job_queue_has_cancelled_status()
	{
		$query = $this->db->query("SHOW COLUMNS FROM `job_queue` LIKE 'status'");
		if (!$query || $query->num_rows() === 0) {
			return false;
		}

		$row = $query->row_array();
		if (empty($row['Type'])) {
			return false;
		}

		return stripos($row['Type'], 'cancelled') !== false;
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}
}
