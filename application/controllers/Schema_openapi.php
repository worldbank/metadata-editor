<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Serves core JSON Schema files from application/schemas/ for OpenAPI / ReDoc.
 */
class Schema_openapi extends CI_Controller {

    /** @var array Legacy filename => canonical file in application/schemas/ */
    private static $filename_aliases = array(
        'microdata-schema.json' => 'survey-schema.json',
    );

    public function serve($filename = '')
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+\.json$/', $filename)) {
            $this->output->set_status_header(404);
            return;
        }

        if (isset(self::$filename_aliases[$filename])) {
            $filename = self::$filename_aliases[$filename];
        }

        $path = APPPATH . 'schemas/' . $filename;

        if (!file_exists($path)) {
            $this->output->set_status_header(404);
            return;
        }

        $content = file_get_contents($path);
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/index.php';
            $app_path = preg_replace('#/index\.php$#', '', $script);
            $decoded['$id'] = $scheme . '://' . $host . $app_path . '/index.php/openapi_schema/' . $filename;
            $encoded = json_encode($decoded, JSON_UNESCAPED_SLASHES);
            if ($encoded !== false) {
                $content = $encoded;
            }
        }

        $this->output
            ->set_content_type('application/json')
            ->set_header('Access-Control-Allow-Origin: *')
            ->set_output($content);
    }
}
