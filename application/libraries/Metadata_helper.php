<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Metadata_helper
{

	function __construct()
	{
		log_message('debug', "Metadata_helper Class Initialized.");
		$this->ci =& get_instance();
		$this->ci->load->helper('array_helper');
	}


	function extract_country_names_str($type, $metadata)
	{
		if ($type=='survey' || $type=='microdata'){
			$nations=(array)get_array_nested_value($metadata,'study_desc/study_info/nation');
        	$nations=$this->get_array_column_values($nations, 'name');
			return $this->get_array_to_string($nations, 3);
		}		
	}

	function extract_year_start($type, $metadata)
	{
		$years=$this->extract_years($type, $metadata);

		if (isset($years['start'])){
			return $years['start'];
		}		
	}

	function extract_year_end($type, $metadata)
	{
		$years=$this->extract_years($type, $metadata);
		if (isset($years['end'])){
			return $years['end'];
		}
	}

	function extract_years($type, $metadata)
	{
		if ($type=='survey' || $type=='microdata'){
			$years=$this->get_data_collection_years($type,$metadata);
			return $years;
		}
		else if ($type=='timeseries' || $type=='indicator')
		{
			$years=$this->get_data_collection_years($type,$metadata);
			return $years;
		}
	}


	/**
	 * 
	 * Extract attributes from metadata
	 * 
	 * 
	 * - timeseries:
	 * 	- database_id
	 * 
	 * 
	 */
	function extract_attributes($type, $metadata, $encoded=false)
	{
		if ($type=='timeseries' || $type=='indicator'){
			$database_id=get_array_nested_value($metadata,'series_description/database_id');

			if ($database_id === null || $database_id === '') {
				$databases = get_array_nested_value($metadata, 'series_description/databases');
				if (is_array($databases) && count($databases) > 0) {
					foreach ($databases as $db) {
						if (!is_array($db)) {
							continue;
						}
						if (!empty($db['is_primary']) && isset($db['id']) && $db['id'] !== '') {
							$database_id = $db['id'];
							break;
						}
					}
					if ($database_id === null || $database_id === '') {
						$first = $databases[0];
						if (is_array($first) && isset($first['id']) && $first['id'] !== '') {
							$database_id = $first['id'];
						}
					}
				}
			}
			
			$output=array(
				'database_id'=>$database_id
			);

			if ($encoded){
				$output=json_encode($output);
			}

			return $output;
		}

	}

	/**
     * 
     * get data collection years from a ddi data collection element
     * 
     **/
	function get_data_collection_years($type,$options)
	{
		$years=array();

		if ($type=='survey' || $type=='microdata'){
        	$data_coll=get_array_nested_value($options,'study_desc/study_info/coll_dates');
		}
		else if ($type=='timeseries' || $type=='indicator'){
			$data_coll=get_array_nested_value($options,'series_description/time_periods');
		}
		else{
			return array(
				'start'=>0,
				'end'=>0
			);
		}

        if (is_array($data_coll)){
            foreach($data_coll as $row){
                $year_=substr(trim($row['start']),0,4);
                if((int)$year_>0){
                    $years[]=$year_;
                }					
                if(isset($row['end'])){
                    $year_=substr(trim($row['end']),0,4);
                    if((int)$year_>0){
                        $years[]=$year_;
                    }
                }
            }
        }

		$start=0;
		$end=0;
		
		if (count($years)>0){
			$start=min($years);
			$end=max($years);
		}

		if ($start==0){
			$start=$end;
		}

		if($end==0){
			$start=$end;
		}

		return array(
			'start'=>$start,
			'end'=>$end
		);
	}


	function get_country_names($nations)
	{
        if(!is_array($nations)){
            return false;
        }

        $nation_names=array();

        foreach($nations as $nation){
            $nation_names[]=$nation['name'];
        }	
        return $nation_names;	
    }



	
	function get_array_column_values($array, $column)
	{
		$values=array();
		foreach($array as $row){
			if (isset($row[$column])){
				$values[]=$row[$column];
			}
		}
		return $values;
	}


	/**
     * 
     * Return the values of an array as a comma separated string 
	 * with max number of values to show
	 * 
	 * @param array $array
	 * @param int $max_values
	 * @return string
	 * 
     */
    function get_array_to_string($array, $max_values=3)
	{
		if (!is_array($array)){
			return '';
		}

		$str='';
		if (count($array)>$max_values){
			$str=implode(", ", array_slice($array, 0, $max_values));
			$str.='...and '. (count($array) - $max_values). ' more';
		}else{
			$str=implode(", ", $array);
		}

		return $str;
	}

	/**
	 * Geospatial bounding polygon coordinates: schema expects [[lon, lat], ...].
	 * Legacy editor rows may use {value: [lon, lat]}; normalize before validation/persist.
	 */
	public function normalize_geospatial_metadata_for_schema($metadata)
	{
		if (!is_array($metadata)) {
			return $metadata;
		}

		return $this->transform_geospatial_bounding_polygon_coordinates($metadata, 'normalize');
	}

	/**
	 * Convert legacy coordinate shapes to [[lon, lat], ...] for the coordinate_pairs field.
	 */
	public function prepare_geospatial_metadata_for_editor($metadata)
	{
		if (!is_array($metadata)) {
			return $metadata;
		}

		return $this->transform_geospatial_bounding_polygon_coordinates($metadata, 'denormalize');
	}

	private function transform_geospatial_bounding_polygon_coordinates($metadata, $mode)
	{
		if (!isset($metadata['description']) || !is_array($metadata['description'])) {
			return $metadata;
		}

		$identification = $metadata['description']['identificationInfo'] ?? null;
		if (!is_array($identification)) {
			return $metadata;
		}

		if (isset($identification['extent'])) {
			$this->transform_geospatial_extent_geographic_elements($metadata['description']['identificationInfo'], $mode);
			return $metadata;
		}

		foreach ($metadata['description']['identificationInfo'] as $index => $info) {
			if (is_array($info)) {
				$this->transform_geospatial_extent_geographic_elements($metadata['description']['identificationInfo'][$index], $mode);
			}
		}

		return $metadata;
	}

	private function transform_geospatial_extent_geographic_elements(&$identificationInfo, $mode)
	{
		if (!isset($identificationInfo['extent']['geographicElement']) || !is_array($identificationInfo['extent']['geographicElement'])) {
			return;
		}

		foreach ($identificationInfo['extent']['geographicElement'] as $geo_index => $element) {
			if (!is_array($element) || !isset($element['geographicBoundingPolygon']['polygon'])) {
				continue;
			}

			$polygons = &$identificationInfo['extent']['geographicElement'][$geo_index]['geographicBoundingPolygon']['polygon'];
			if (!is_array($polygons)) {
				continue;
			}

			foreach ($polygons as $ring_index => $ring) {
				if (!is_array($ring)) {
					continue;
				}

				if ($mode === 'normalize' && isset($ring['type']) && $ring['type'] === 'line') {
					$polygons[$ring_index]['type'] = 'lineString';
				}

				if (!isset($ring['coordinates'])) {
					continue;
				}

				$polygons[$ring_index]['coordinates'] = $this->transform_geospatial_coordinate_list(
					$ring['coordinates'],
					$mode
				);
			}
		}
	}

	private function transform_geospatial_coordinate_list($coordinates, $mode)
	{
		if (!is_array($coordinates)) {
			return $coordinates;
		}

		if ($mode === 'normalize') {
			$normalized = array();
			foreach ($coordinates as $item) {
				if (is_array($item) && array_key_exists('value', $item) && is_array($item['value'])) {
					$normalized[] = $this->coerce_geospatial_coordinate_pair($item['value']);
				} elseif (is_array($item) && (isset($item['longitude']) || isset($item['latitude']))) {
					$normalized[] = $this->coerce_geospatial_coordinate_pair(array(
						$item['longitude'] ?? '',
						$item['latitude'] ?? '',
					));
				} elseif ($this->is_geospatial_coordinate_pair($item)) {
					$normalized[] = $this->coerce_geospatial_coordinate_pair($item);
				}
			}
			return $normalized;
		}

		if ($mode === 'denormalize') {
			$converted = array();
			foreach ($coordinates as $item) {
				if ($this->is_geospatial_coordinate_pair($item)) {
					$converted[] = $this->coerce_geospatial_coordinate_pair($item);
				} elseif (is_array($item) && array_key_exists('value', $item) && is_array($item['value'])) {
					$converted[] = $this->coerce_geospatial_coordinate_pair($item['value']);
				} elseif (is_array($item) && (isset($item['longitude']) || isset($item['latitude']))) {
					$converted[] = $this->coerce_geospatial_coordinate_pair(array(
						$item['longitude'] ?? '',
						$item['latitude'] ?? '',
					));
				}
			}
			return $converted;
		}

		return $coordinates;
	}

	private function is_geospatial_coordinate_pair($item)
	{
		if (!is_array($item) || array_key_exists('value', $item)) {
			return false;
		}

		$values = array_values($item);
		return count($values) >= 2;
	}

	private function coerce_geospatial_coordinate_pair(array $pair)
	{
		$values = array_slice(array_values($pair), 0, 2);
		$result = array();

		foreach ($values as $value) {
			if (is_numeric($value)) {
				$result[] = $value + 0;
			} else {
				$result[] = $value;
			}
		}

		return $result;
	}


} 