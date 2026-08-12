<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Serves core JSON Schema files and the dynamic OpenAPI YAML for ReDoc.
 */
class Schema_openapi extends CI_Controller {

    /** @var array Legacy filename => canonical file in application/schemas/ */
    private static $filename_aliases = array(
        'microdata-schema.json' => 'survey-schema.json',
    );

    /**
     * Serve openapi.yaml with servers + external $ref values resolved via site URLs.
     */
    public function spec()
    {
        $path = FCPATH . 'api-documentation/editor/openapi.yaml';
        $openapi_yaml = file_get_contents($path);

        if ($openapi_yaml === false) {
            $this->output->set_status_header(500);
            $this->output->set_output('Error: openapi.yaml not found');
            return;
        }

        $api_base = rtrim(site_url('api'), '/') . '/';
        $schemas_base = rtrim(site_url('openapi_schema'), '/') . '/';

        $servers_block = "servers:\n  - url: " . $api_base . "\n    description: API Server\n";
        $openapi_yaml = preg_replace('/^servers:.*?(?=^\S)/ms', $servers_block . "\n", $openapi_yaml);

        $openapi_yaml = preg_replace_callback(
            '/^(\s*)(-\s*)?(\$ref:\s*)(.+)$/m',
            function ($m) use ($schemas_base) {
                $indent = $m[1];
                $list = isset($m[2]) ? $m[2] : '';
                $prefix = $m[3];
                $raw = trim($m[4]);
                if ($raw === '' || $raw[0] === '#') {
                    return $m[0];
                }

                $value = $raw;
                if ($value[0] === '"' || $value[0] === "'") {
                    if (substr($value, -1) === $value[0]) {
                        $value = substr($value, 1, -1);
                    }
                }

                if ($value === '' || $value[0] === '#') {
                    return $m[0];
                }

                if (!preg_match('/^(?:[^\/]+\/)*([a-zA-Z0-9_-]+\.json)(#.*)?$/', $value, $parts)) {
                    return $m[0];
                }

                $url = $schemas_base . $parts[1] . (isset($parts[2]) ? $parts[2] : '');
                $quoted = "'" . str_replace("'", "''", $url) . "'";

                return $indent . $list . $prefix . $quoted;
            },
            $openapi_yaml
        );

        $this->output
            ->set_content_type('application/x-yaml', 'utf-8')
            ->set_header('Access-Control-Allow-Origin: *')
            ->set_output($openapi_yaml);
    }

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
            $decoded['$id'] = site_url('openapi_schema/' . $filename);
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
