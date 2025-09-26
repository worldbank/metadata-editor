<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class CsvWriter
{
    private $ci;

    function __construct()
    {
        log_message('debug', "CsvWriter Class Initialized.");
        $ci = get_instance();
        $this->ci = $ci;
    }

    function generate_csv($structure_type, $structure_id, $action = 'I', $dimensions = [], $metadata = [])
    {
        $fp = fopen('php://temp', 'w+');

        // Flatten metadata
        $metadata = $this->flatten_metadata($metadata);

        // Normalize line breaks for metadata
        $normalize = function ($val) {
            if (is_string($val)) {
                return str_replace(["\r\n", "\r", "\n"], "\r\n", $val);
            }
            return $val;
        };

        // Build header (columns) from dimensions and metadata
        $header = array_merge(
            ["STRUCTURE", "STRUCTURE_ID", "ACTION"],
            array_keys($dimensions),
            array_keys($metadata)
        );
        fputcsv($fp, $header);

        // Build row (values)
        $row = array_merge(
            [$structure_type, $structure_id, $action],
            array_map($normalize, $dimensions),
            array_map($normalize, $metadata)
        );

        fputcsv($fp, $row);
        rewind($fp);

        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv;
    }


    /**
     * Flatten JSON metadata into key => value pairs for SDMX-CSV
     *
     * Handles:
     *  - Nested objects
     *  - Repeatable arrays (concatenates values with "; ")
     *
     * @param array  $data    JSON metadata (decoded as array)
     * @param string $prefix  Parent path (for recursion)
     * @return array
     */
    function flatten_metadata(array $data, string $prefix = ''): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            // Build column name (CSV header)
            $flatKey = strtoupper(trim($prefix . ($prefix ? '__' : '') . $key));

            if (is_array($value)) {
                // Case 1: Array of objects (repeatable)
                if (isset($value[0]) && is_array($value[0])) {
                    $subFields = [];
                    foreach ($value as $entry) {
                        foreach ($entry as $subKey => $subVal) {
                            $subFlatKey = strtoupper($flatKey . '__' . $subKey);
                            $subFields[$subFlatKey][] = is_array($subVal) ? json_encode($subVal) : $subVal;
                        }
                    }
                    foreach ($subFields as $subFlatKey => $vals) {
                        $result[$subFlatKey] = implode("; ", $vals); // join repeats
                    }
                }
                // Case 2: Associative array (nested object)
                else {
                    $result = array_merge($result, $this->flatten_metadata($value, $flatKey));
                }
            } else {
                // Scalar value
                $result[$flatKey] = $value;
            }
        }

        return $result;
    }


}