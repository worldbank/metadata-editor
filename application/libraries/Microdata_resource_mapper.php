<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Map editor data files and export options to external resource metadata fields.
 */
class Microdata_resource_mapper
{
	const DCTYPE_MICRO = 'dat/micro';

	private static $format_labels = array(
		'csv' => 'CSV',
		'dta' => 'Stata',
		'sav' => 'SPSS',
		'xpt' => 'SAS',
		'json' => 'JSON',
	);

	private static $dcformat_by_format = array(
		'csv' => 'text/plain',
		'dta' => 'application/x-stata',
		'sav' => 'application/x-spss',
		'xpt' => 'application/x-sas',
		'json' => 'text/plain',
	);

	/**
	 * @param string $export_format
	 * @param string|null $export_version
	 * @return string
	 */
	public function format_label($export_format, $export_version = null)
	{
		$format = strtolower(trim((string) $export_format));
		$label = isset(self::$format_labels[$format]) ? self::$format_labels[$format] : strtoupper($format);

		if ($format === 'dta' && $export_version !== null && $export_version !== '') {
			return 'Stata ' . (int) $export_version;
		}

		return $label;
	}

	/**
	 * @param string $export_format
	 * @param string|null $export_version
	 * @return string
	 */
	public function build_title($export_format, $export_version = null)
	{
		return 'Data in ' . $this->format_label($export_format, $export_version) . ' format';
	}

	/**
	 * First non-empty description or notes from data file rows.
	 *
	 * @param array $datafiles List or map of data file rows
	 * @return string|null
	 */
	public function build_description(array $datafiles)
	{
		foreach ($datafiles as $datafile) {
			if (!is_array($datafile)) {
				continue;
			}
			$desc = isset($datafile['description']) ? trim((string) $datafile['description']) : '';
			if ($desc !== '') {
				return $desc;
			}
			$notes = isset($datafile['notes']) ? trim((string) $datafile['notes']) : '';
			if ($notes !== '') {
				return $notes;
			}
		}

		return null;
	}

	/**
	 * @param string $export_format
	 * @param bool $is_zip
	 * @return string dcformat code
	 */
	public function dcformat_code($export_format, $is_zip = false)
	{
		if ($is_zip) {
			return 'application/zip';
		}

		$format = strtolower(trim((string) $export_format));
		if (isset(self::$dcformat_by_format[$format])) {
			return self::$dcformat_by_format[$format];
		}

		return 'application/octet-stream';
	}

	/**
	 * @param int $sid
	 * @param array $options export_format, export_version, filename, bundle_type, description override
	 * @return array editor_resources insert/update fields (codes for dctype/dcformat)
	 */
	public function build_resource_fields($sid, array $options)
	{
		$export_format = strtolower(trim((string) $options['export_format']));
		$export_version = isset($options['export_version']) ? $options['export_version'] : null;
		$is_zip = !empty($options['bundle_type']) && $options['bundle_type'] === 'zip';

		$fields = array(
			'sid' => (int) $sid,
			'dctype' => self::DCTYPE_MICRO,
			'title' => $this->build_title($export_format, $export_version),
			'filename' => isset($options['filename']) ? $options['filename'] : null,
			'dcformat' => $this->dcformat_code($export_format, $is_zip),
			'source_type' => isset($options['source_type']) ? $options['source_type'] : 'generated',
			'bundle_type' => isset($options['bundle_type']) ? $options['bundle_type'] : ($is_zip ? 'zip' : 'single'),
		);

		if (array_key_exists('description', $options)) {
			$fields['description'] = $options['description'];
		}

		return $fields;
	}

	/**
	 * Zip entry basename for a data file row.
	 *
	 * @param array $datafile
	 * @param string $export_format
	 * @return string
	 */
	public function zip_entry_name_for_datafile(array $datafile, $export_format)
	{
		$base = '';
		if (!empty($datafile['file_name'])) {
			$base = (string) $datafile['file_name'];
		} elseif (!empty($datafile['file_physical_name'])) {
			$base = pathinfo($datafile['file_physical_name'], PATHINFO_FILENAME);
		} else {
			$base = (string) $datafile['file_id'];
		}

		$base = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $base);
		if ($base === '') {
			$base = 'data';
		}

		return $base . '.' . strtolower((string) $export_format);
	}

	/**
	 * Expected tmp export filename after FastAPI job (basename only).
	 *
	 * @param array $datafile
	 * @param string $export_format
	 * @return string
	 */
	public function tmp_export_basename(array $datafile, $export_format)
	{
		$physical = isset($datafile['file_physical_name']) ? (string) $datafile['file_physical_name'] : '';
		if ($physical !== '') {
			$base = pathinfo($physical, PATHINFO_FILENAME);
		} elseif (!empty($datafile['file_name'])) {
			$base = (string) $datafile['file_name'];
		} else {
			$base = (string) $datafile['file_id'];
		}

		return $base . '.' . strtolower((string) $export_format);
	}
}
