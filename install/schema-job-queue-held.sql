-- Add 'held' status to job_queue for pausing pending jobs without deleting them.
-- Held jobs are skipped by the worker; restore to 'pending' to re-queue.

ALTER TABLE `job_queue`
  MODIFY COLUMN `status` enum('pending','held','processing','completed','failed') DEFAULT 'pending';
