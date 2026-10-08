<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * metadata display helper function
 *
 */

if ( ! function_exists('render_field'))
{
	function render_field($type, $name, $data, $options=array())
	{
		$ci =& get_instance();

		switch($type)
		{
			case 'text':
				return render_text($name, $data,$options);
				break;
			case 'array':
			case 'table':
				return render_table($name, $data,$options);
				break;
			case 'bounding_box':
				return render_bounding_box($name, $data,$options);
				break;
            case 'var_category':
                return render_var_category($name, $data,$options);
                break;
			default:				
				return render_custom($type,$name,$data,$options);
		}
		
	}
}

if ( ! function_exists('render_custom'))
{
	function render_custom($type,$name, $data,$options=array())
	{
		$ci =& get_instance();
		$custom_field='application/views/metadata_templates/fields/field_'.$type.'.php';
		$view_file='metadata_templates/fields/field_'.$type;
		if (file_exists($custom_field)){
			return $ci->load->view($view_file,array('name'=>$name, 'data'=>$data,'options'=>$options), TRUE);
		}
		else{
			return render_table($name,$data);
		}
	}
}


 
if ( ! function_exists('render_text'))
{
	function render_text($name, $data, $options=array())
	{
		$ci =& get_instance();
		return $ci->load->view('metadata_templates/fields/field_text',array('name'=>$name, 'data'=>$data, 'options'=>$options), TRUE);
	}
}



if ( ! function_exists('render_bounding_box'))
{
	function render_bounding_box($name, $data)
	{
		$ci =& get_instance();
		return $ci->load->view('metadata_templates/fields/field_bounding_box',array('name'=>$name, 'data'=>$data), TRUE);
	}
}



if ( ! function_exists('render_table'))
{
	function render_table($name, $data, $options=array())
	{
		$ci =& get_instance();
		return $ci->load->view('metadata_templates/fields/field_array',array('name'=>$name, 'data'=>$data, 'options'=>$options), TRUE);
	}
}



function get_field_value($name,$data)
{
	/*if (array_key_exists($name, $metadata_array)){
		return $metadata_array[$name];
	}*/

	$paths = explode('.', $name);
    #$result = $metadata;
    //recursively find the path
    foreach ($paths as $path) {
        if(!isset($data[$path])){
            return false;
        }
        $data = $data[$path];
    }
    return $data;
}


if ( ! function_exists('render_group'))
{
	function render_group($name, $fields, $metadata,$options=array())
	{
		$ci =& get_instance();
		return $ci->load->view('metadata_templates/fields/section',
			array(
				'section_name'=>$name, 
				'metadata'=>$metadata,
				'fields'=>$fields,
				'options'=>$options
			)
			, TRUE);
	}
}


if ( ! function_exists('render_group_array'))
{
	function render_group_array($name, $fields, $metadata,$options=array())
	{
		$ci =& get_instance();

		$output=[];
		foreach($fields as $field_name=>$field_type){
			$value=get_field_value($field_name,$metadata);
			//$field_options=isset($field_type['options'])
			if (is_array($field_type)){
				$output[$field_name]= render_field($field_type[0],$field_name,$value,$options=$field_type['options']);
			}
			else{
				$output[$field_name]= render_field($field_type,$field_name,$value,$options);
			}
		}
    
		return $output;
	}
}


if ( ! function_exists('render_group_text'))
{
	function render_group_text($section_name, $html)
	{
		$ci =& get_instance();
		return $ci->load->view('metadata_templates/fields/section_field',
			array(
				'section_name'=>$section_name, 
				'output'=>$html				
			)
			, TRUE);
	}
}



if ( ! function_exists('render_columns')) 
{
	function render_columns($name, $fields, $metadata,$options=array())
	{
		$ci =& get_instance();
		return $ci->load->view('metadata_templates/fields/bootstrap_columns',
			array(
				'section_name'=>$name, 
				'metadata'=>$metadata,
				'fields'=>$fields,
				'options'=>$options
			)
			, TRUE);
	}
}


if ( ! function_exists('render_columns_array'))
{
	function render_columns_array($name,$fields=array(), $options=array())
	{
		$ci =& get_instance();
		return $ci->load->view('metadata_templates/fields/columns_array',
			array(
				'section_name'=>$name, 
				'fields'=>$fields,
				'options'=>$options
			)
			, TRUE);
	}
}



if ( ! function_exists('render_var_category'))
{
    function render_var_category($name, $data)
    {
        $ci =& get_instance();
		return $ci->load->view('metadata_templates/fields/field_var_category',array('name'=>$name, 'data'=>$data), TRUE);
    }
}


if ( ! function_exists('cap_variable_categories_for_report'))
{
	/**
	 * Limit categories kept on a variable for HTML/PDF reports.
	 * Totals and case sums are stored so percentages still use the full distribution.
	 */
	function cap_variable_categories_for_report(&$variable, $limit = 500)
	{
		if (!isset($variable['var_catgry']) || !is_array($variable['var_catgry'])) {
			return;
		}

		$total = count($variable['var_catgry']);
		$sum_cases = 0;
		$sum_cases_wgtd = 0;

		foreach ($variable['var_catgry'] as $item) {
			if (!isset($item['stats']) || !is_array($item['stats'])) {
				continue;
			}
			$ismissing = isset($item['is_missing']) ? $item['is_missing'] : '';
			if ($ismissing != '') {
				continue;
			}
			foreach ($item['stats'] as $stat_row) {
				if (!isset($stat_row['value']) || !is_numeric($stat_row['value'])) {
					continue;
				}
				$wgtd = isset($stat_row['wgtd']) ? $stat_row['wgtd'] : '';
				if ($wgtd === 'wgtd') {
					$sum_cases_wgtd += $stat_row['value'];
				} else {
					$sum_cases += $stat_row['value'];
				}
			}
		}

		$variable['var_catgry_total'] = $total;
		$variable['var_catgry_sum_cases'] = $sum_cases;
		$variable['var_catgry_sum_cases_wgtd'] = $sum_cases_wgtd;
		$variable['var_catgry_hidden'] = 0;

		if ($total > $limit) {
			$variable['var_catgry_hidden'] = $total - $limit;
			$variable['var_catgry'] = array_values(array_slice($variable['var_catgry'], 0, $limit));
		}
	}
}




/**
 *
 * Creates a string value out of array type elements
 *
 * Note: uses \r\n for line breaks between multiple rows
 *
 **/
function get_string_value($data,$type='text')
{
    if(!$data)
    {
        return NULL;
    }

    if ($type=='text' || $type=='string')
    {
        if (!is_array($data)){
            return $data;
        }

        return implode("\r\n",$data);
    }
    else if(in_array($type, array('table','array')))
    {
        $output=array();
        foreach($data as $row)
        {
            $row_output=array();

            foreach($row as $field_name=>$field_value)
            {
                if(trim($field_value)!=''){
                    $row_output[]=$field_value;
                }
            }

            //concat a single row
            $output[]=implode(", ",$row_output);
        }

        //combine all rows with line break
        return implode("\r\n",$output);
    }

    throw new Exception("TYPE_NOT_SUPPORTED: ".$type);
}


if ( ! function_exists('authors_to_string'))
{
    function authors_to_string($authors=array())
    {
		$output=array();
        foreach($authors as $author){
			$author_name=array(
				isset($author['first_name']) ? $author['first_name'] : '', 
				isset($author['last_name']) ? $author['last_name']: ''
			);
			$output[]=implode(" ", array_filter($author_name));
		}

		return implode(", ", $output);
    }
}


if ( ! function_exists('escape_html_attribute'))
{
	function escape_html_attribute($value)
	{
		//convert . to _
		$value=str_replace('.','_',$value);
		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	}
}

if ( ! function_exists('preview_value_has_content'))
{
	function preview_value_has_content($value)
	{
		if ($value === null || $value === '') {
			return false;
		}

		if (is_array($value)) {
			foreach ($value as $item) {
				if (preview_value_has_content($item)) {
					return true;
				}
			}
			return false;
		}

		return true;
	}
}

if ( ! function_exists('preview_row_column_data'))
{
	/**
	 * Resolve template column data from a nested_array row (e.g. geographicElement).
	 */
	function preview_row_column_data(array $row, array $column)
	{
		$key = isset($column['key']) ? $column['key'] : '';

		if ($key !== '' && array_key_exists($key, $row) && preview_value_has_content($row[$key])) {
			return $row[$key];
		}

		if ($key !== '' && substr($key, -8) === '-section') {
			$base_key = substr($key, 0, -8);
			if (array_key_exists($base_key, $row) && preview_value_has_content($row[$base_key])) {
				return $row[$base_key];
			}
		}

		if (!empty($column['props']) && is_array($column['props'])) {
			$first_prop_key = isset($column['props'][0]['key']) ? $column['props'][0]['key'] : '';
			if ($first_prop_key !== '' && strpos($first_prop_key, '.') !== false) {
				$prefix = explode('.', $first_prop_key, 2)[0];
				if (array_key_exists($prefix, $row) && preview_value_has_content($row[$prefix])) {
					return $row[$prefix];
				}
			}
		}

		return null;
	}
}

if ( ! function_exists('preview_section_prop_value'))
{
	function preview_section_prop_value($section_data, $section_key, $prop_key)
	{
		if (!is_array($section_data) || $prop_key === '') {
			return null;
		}

		if (strpos($prop_key, '.') === false) {
			return array_key_exists($prop_key, $section_data) ? $section_data[$prop_key] : null;
		}

		$root = explode('.', $prop_key, 2)[0];
		$relative = explode('.', $prop_key, 2)[1];

		$section_base = $section_key;
		if (substr($section_key, -8) === '-section') {
			$section_base = substr($section_key, 0, -8);
		}

		if ($section_base === $root || $section_key === $root) {
			return array_data_get($section_data, $relative);
		}

		if (array_key_exists($root, $section_data) && is_array($section_data[$root])) {
			return array_data_get($section_data, $prop_key);
		}

		return array_data_get($section_data, $prop_key);
	}
}

if ( ! function_exists('preview_format_coordinate_pairs'))
{
	function preview_format_coordinate_pairs($coordinates)
	{
		if (!is_array($coordinates) || count($coordinates) === 0) {
			return '';
		}

		$lines = array();
		foreach ($coordinates as $pair) {
			if (!is_array($pair) || count($pair) < 2) {
				continue;
			}
			$values = array_values($pair);
			$lines[] = $values[0] . ', ' . $values[1];
		}

		return implode("\n", $lines);
	}
}

if ( ! function_exists('preview_bounding_box_corners'))
{
	/**
	 * Parse west/east/south/north from a bounding box object for map rendering.
	 *
	 * @return array|null Keys west, east, south, north or null if incomplete/invalid
	 */
	function preview_bounding_box_corners(array $column, $bounding_box)
	{
		if (!is_array($bounding_box)) {
			return null;
		}

		$opts = isset($column['bounding_box_options']) ? $column['bounding_box_options'] : array(
			'west' => 'westBoundLongitude',
			'east' => 'eastBoundLongitude',
			'south' => 'southBoundLatitude',
			'north' => 'northBoundLatitude',
		);

		$read = function ($path) use ($bounding_box) {
			$short = strpos($path, '.') !== false ? substr($path, strrpos($path, '.') + 1) : $path;
			$value = array_key_exists($short, $bounding_box) ? $bounding_box[$short] : array_data_get($bounding_box, $path);
			if ($value === '' || $value === null) {
				return null;
			}
			if (!is_numeric($value)) {
				return null;
			}
			return $value + 0;
		};

		$west = $read($opts['west']);
		$east = $read($opts['east']);
		$south = $read($opts['south']);
		$north = $read($opts['north']);

		if ($west === null || $east === null || $south === null || $north === null) {
			return null;
		}
		if ($south >= $north) {
			return null;
		}

		return array(
			'west' => $west,
			'east' => $east,
			'south' => $south,
			'north' => $north,
		);
	}
}

if ( ! function_exists('preview_format_bounding_box'))
{
	function preview_format_bounding_box(array $column, $bounding_box)
	{
		if (!is_array($bounding_box)) {
			return '';
		}

		$opts = isset($column['bounding_box_options']) ? $column['bounding_box_options'] : array(
			'west' => 'westBoundLongitude',
			'east' => 'eastBoundLongitude',
			'south' => 'southBoundLatitude',
			'north' => 'northBoundLatitude',
		);

		$parts = array();
		foreach ($opts as $label => $path) {
			$key = strpos($path, '.') !== false ? substr($path, strrpos($path, '.') + 1) : $path;
			$value = array_key_exists($key, $bounding_box) ? $bounding_box[$key] : array_data_get($bounding_box, $path);
			if ($value !== null && $value !== '') {
				$parts[] = ucfirst($label) . ': ' . $value;
			}
		}

		return implode('; ', $parts);
	}
}

if ( ! function_exists('preview_format_scalar'))
{
	function preview_format_scalar($value, array $column = array())
	{
		if ($value === null || $value === '') {
			return '';
		}

		if (isset($column['enum']) && is_array($column['enum'])) {
			$store = isset($column['enum_store_column']) ? $column['enum_store_column'] : 'code';
			foreach ($column['enum'] as $option) {
				if (!is_array($option)) {
					continue;
				}
				if (isset($option[$store]) && $option[$store] == $value) {
					return isset($option['label']) ? $option['label'] : $value;
				}
			}
		}

		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}

		return (string) $value;
	}
}

/* End of file metadata_view_helper.php */
/* Location: ./application/helpers/metadata_view_helper.php */