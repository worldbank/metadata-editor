<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Enqueue generate_microdata_resource jobs for projects in a collection.
 *
 * Usage:
 *   php index.php cli/enqueue_generate_microdata_resource/run --collection-id=42 --dry-run
 *   php index.php cli/enqueue_generate_microdata_resource/run --collection-id=42 --admin-user-id=1 --limit=10
 *   php index.php cli/enqueue_generate_microdata_resource/run --collection-id=42 --admin-user-id=1 --format=dta --export-version=14 --overwrite
 */
class Enqueue_generate_microdata_resource extends CI_Controller
{
    private $options = array(
        'collection-id' => null,
        'admin-user-id' => null,
        'limit' => 10,
        'dry-run' => false,
        'format' => 'dta',
        'export-version' => null,
        'overwrite' => false,
        'zip' => true,
        'skip-existing' => true,
        'max-wait' => 900,
    );

    public function __construct()
    {
        parent::__construct();

        if (php_sapi_name() !== 'cli') {
            show_error('This controller can only be accessed via CLI');
            exit(1);
        }

        require_once APPPATH . 'libraries/Jobs/JobHandlerInterface.php';
        require_once APPPATH . 'libraries/Jobs/JobRegistry.php';

        $this->load->database();
        $this->load->model('Job_queue_model');
        $this->load->model('Editor_datafile_model');
        $this->load->library('Microdata_resource_generator');

        $this->parse_arguments();
    }

    public function run()
    {
        if (!$this->options['collection-id']) {
            $this->stderr("Required: --collection-id=<id>\n");
            $this->print_usage();
            exit(1);
        }

        if (!$this->options['dry-run'] && !$this->options['admin-user-id']) {
            $this->stderr("Required for enqueue: --admin-user-id=<id> (use a global admin)\n");
            $this->print_usage();
            exit(1);
        }

        $export_format = strtolower(trim((string) $this->options['format']));
        if (!in_array($export_format, Microdata_resource_generator::SUPPORTED_FORMATS, true)) {
            $this->stderr("Unsupported format: {$export_format}\n");
            exit(1);
        }

        $collection_id = (int) $this->options['collection-id'];

        $row = $this->db->select('id, title')
            ->where('id', $collection_id)
            ->get('editor_collections')
            ->row_array();

        if (!$row) {
            $this->stderr("Collection not found: id={$collection_id}\n");
            exit(1);
        }

        $skip_existing = $this->options['skip-existing'] && !$this->options['overwrite'];

        echo "Collection: {$row['title']} (id={$collection_id})\n";
        echo "Format: {$export_format}\n";
        if ($export_format === 'dta' && $this->options['export-version'] !== null) {
            echo "Stata version: {$this->options['export-version']}\n";
        }
        echo "Limit: {$this->options['limit']}\n";
        echo "Dry run: " . ($this->options['dry-run'] ? 'yes' : 'no') . "\n";
        echo "Overwrite: " . ($this->options['overwrite'] ? 'yes' : 'no') . "\n";
        echo "Skip existing: " . ($skip_existing ? 'yes' : 'no') . "\n";
        echo "Zip: " . ($this->options['zip'] ? 'yes' : 'no') . "\n\n";

        $candidates = $this->find_candidates($collection_id, $export_format, $skip_existing);

        if (empty($candidates)) {
            echo "No candidates found.\n";
            exit(0);
        }

        echo 'Found ' . count($candidates) . " candidate(s) total.\n\n";

        $enqueued = 0;
        $skipped = 0;

        foreach ($candidates as $candidate) {
            if ($enqueued >= $this->options['limit']) {
                break;
            }

            $project_id = (int) $candidate['project_id'];

            if (!$this->project_has_exportable_csv($project_id)) {
                echo "SKIP  project={$project_id} ({$candidate['idno']}) — no exportable CSV data files\n";
                $skipped++;
                continue;
            }

            echo "CANDIDATE project={$project_id} ({$candidate['idno']}) data_files={$candidate['data_file_count']}\n";
            echo "          {$candidate['project_title']}\n";

            if ($this->options['dry-run']) {
                $enqueued++;
                continue;
            }

            $payload = array(
                'project_id' => $project_id,
                'export_format' => $export_format,
                'zip' => $this->options['zip'],
                'overwrite' => $this->options['overwrite'],
                'max_wait_seconds' => (int) $this->options['max-wait'],
            );

            if ($export_format === 'dta') {
                $payload['export_version'] = $this->options['export-version'] !== null
                    ? $this->options['export-version']
                    : 14;
            } elseif ($this->options['export-version'] !== null && $this->options['export-version'] !== '') {
                $payload['export_version'] = $this->options['export-version'];
            }

            $handler = JobRegistry::getHandler('generate_microdata_resource');
            if ($handler) {
                $handler->validatePayload($payload);
            }

            $job_id = $this->Job_queue_model->enqueue(
                'generate_microdata_resource',
                $payload,
                $this->options['admin-user-id'],
                0,
                2
            );

            $job = $this->Job_queue_model->get($job_id);
            $uuid = isset($job['uuid']) ? $job['uuid'] : 'n/a';

            echo "ENQUEUED job_id={$job_id} uuid={$uuid}\n\n";
            $enqueued++;
        }

        echo "\nDone. Processed={$enqueued} skipped={$skipped}\n";

        if (!$this->options['dry-run'] && $enqueued > 0) {
            echo "Monitor: SELECT * FROM job_queue WHERE job_type='generate_microdata_resource' ORDER BY id DESC LIMIT 5;\n";
        }
    }

    /**
     * @param int $collection_id
     * @param string $export_format
     * @param bool $skip_existing
     * @return array
     */
    private function find_candidates($collection_id, $export_format, $skip_existing)
    {
        $sql = "
            SELECT
                p.id AS project_id,
                p.idno,
                p.title AS project_title,
                COUNT(DISTINCT df.id) AS data_file_count
            FROM editor_collection_projects cp
            JOIN editor_projects p ON p.id = cp.sid
            JOIN editor_data_files df ON df.sid = p.id
            LEFT JOIN (
                SELECT DISTINCT erdf.sid
                FROM editor_resource_data_files erdf
                INNER JOIN editor_resources er ON er.id = erdf.resource_id
                WHERE erdf.link_type = 'generated'
                  AND erdf.export_format = ?
                  AND er.dctype LIKE '%dat/micro%'
            ) existing ON existing.sid = p.id
            WHERE cp.collection_id = ?
              AND LOWER(p.type) IN ('survey', 'microdata')
        ";

        $params = array($export_format, $collection_id);

        if ($skip_existing) {
            $sql .= " AND existing.sid IS NULL";
        }

        $sql .= "
            GROUP BY p.id, p.idno, p.title
            HAVING COUNT(DISTINCT df.id) > 0
            ORDER BY p.id
        ";

        return $this->db->query($sql, $params)->result_array();
    }

    /**
     * @param int $project_id
     * @return bool
     */
    private function project_has_exportable_csv($project_id)
    {
        $datafiles = $this->Editor_datafile_model->select_all($project_id);
        foreach (array_keys($datafiles) as $file_id) {
            if ($this->Editor_datafile_model->get_file_csv_path($project_id, $file_id)) {
                return true;
            }
        }
        return false;
    }

    private function parse_arguments()
    {
        global $argv;

        foreach ($argv as $arg) {
            if ($arg === '--dry-run') {
                $this->options['dry-run'] = true;
            } elseif ($arg === '--overwrite') {
                $this->options['overwrite'] = true;
            } elseif ($arg === '--no-zip') {
                $this->options['zip'] = false;
            } elseif ($arg === '--include-existing') {
                $this->options['skip-existing'] = false;
            } elseif (preg_match('/^--collection-id=(\d+)$/', $arg, $m)) {
                $this->options['collection-id'] = (int) $m[1];
            } elseif (preg_match('/^--admin-user-id=(\d+)$/', $arg, $m)) {
                $this->options['admin-user-id'] = (int) $m[1];
            } elseif (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
                $this->options['limit'] = (int) $m[1];
            } elseif (preg_match('/^--format=(.+)$/', $arg, $m)) {
                $this->options['format'] = strtolower(trim($m[1]));
            } elseif (preg_match('/^--export-version=(.+)$/', $arg, $m)) {
                $this->options['export-version'] = $m[1];
            } elseif (preg_match('/^--max-wait=(\d+)$/', $arg, $m)) {
                $this->options['max-wait'] = (int) $m[1];
            }
        }
    }

    private function print_usage()
    {
        echo "\nUsage:\n";
        echo "  php index.php cli/enqueue_generate_microdata_resource/run --collection-id=<id> [--dry-run] [--limit=10]\n";
        echo "  php index.php cli/enqueue_generate_microdata_resource/run --collection-id=<id> --admin-user-id=<id> [options]\n";
        echo "\nOptions:\n";
        echo "  --collection-id=<id>     Collection ID (required)\n";
        echo "  --admin-user-id=<id>     Global admin user for job ACL (required unless --dry-run)\n";
        echo "  --limit=<n>              Max projects to enqueue (default: 10)\n";
        echo "  --format=dta             Export format: csv, dta, sav, xpt, json (default: dta)\n";
        echo "  --export-version=14      Stata version when format is dta (default: 14)\n";
        echo "  --overwrite              Replace existing generated resource for this format\n";
        echo "  --include-existing       Include projects that already have a generated resource\n";
        echo "  --no-zip                 Do not zip exports (only valid for single-file projects)\n";
        echo "  --max-wait=<seconds>     Max wait per export job (default: 900)\n";
        echo "  --dry-run                List candidates only; do not enqueue\n";
    }

    private function stderr($message)
    {
        fwrite(STDERR, $message);
    }
}
