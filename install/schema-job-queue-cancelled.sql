-- Add 'cancelled' status to job_queue for user-initiated job cancellation.
-- Applied by migration 20260703000001_upgrade_v1_3_0.php
-- Used by POST /api/jobs/cancel/{uuid} and metadata assessment cancel flow.

ALTER TABLE `job_queue`
  MODIFY COLUMN `status` enum('pending','held','processing','completed','failed','cancelled') DEFAULT 'pending';
