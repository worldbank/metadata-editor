<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * PDF report helper functions
 *
 */

if ( ! function_exists('render_field'))
{	
        function render_group($group_name, $fields, $variable){
			
					$group_output=[];
					foreach($fields as $field=>$type){
						//ignore null
						if (!isset($variable[$field])){
							continue;
						}

						if ($type=='var_category'){
							$group_output[]= render_var_category($field, $variable[$field]);
							continue;
						}

						if ($type=='array'){
							$group_output[]= render_array_field($field, $variable[$field]);
							continue;
						}


						if(isset($variable[$field])){
							$field_output='';
							$field_output.='<div class="fld-inline">';
								$field_output.='<span class="fld-name">'. t($field).': </span>';
								if (!is_array($variable[$field])){
									$field_output.='<span class="fld-value">'. $variable[$field].'</span>';
								}else{
									$field_output.='<pre>';
									$field_output.='<span class="fld-value">'. var_dump($variable[$field]).'</span>';
									$field_output.='</pre>';
								}
							$field_output.='</div>';
							$group_output[]=$field_output;							
						}
					}

					if (count(array_filter($group_output))==0){
						return;
					}

					//echo '<div class="xrow">';
					//	echo '<h3 class="xsl-subtitle">'. t($group_name).'</h3>';
					//	echo '<div class="xcol">';
						foreach($group_output as $output){
							echo $output;
						}

					//	echo '</div>';
					//echo '</div>';		
		}
}


if ( ! function_exists('render_variable_categories'))
{	
	function render_variable_categories($variable)
	{
		$CI =& get_instance();
		$output=$CI->load->view('pdf_reports/microdata/variable_categories', array('variable'=>$variable), true);
		return $output;
	}
}


if ( ! function_exists('render_var_category'))
{
    function render_var_category($name, $data)
    {
        $ci =& get_instance();
        return $ci->load->view('pdf_reports/microdata/field_var_category',array('name'=>$name, 'data'=>$data), TRUE);
    }
}

if ( ! function_exists('render_array_field'))
{
    function render_array_field($name, $data)
    {
        $ci =& get_instance();
        return $ci->load->view('pdf_reports/microdata/field_array',array('name'=>$name, 'data'=>$data), TRUE);
    }
}



/* End of file pdf_report_helper.php */
/* Location: ./application/helpers/pdf_report_helper.php */