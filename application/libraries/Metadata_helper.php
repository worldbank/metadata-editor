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
		if ($type=='survey'){
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
		if ($type=='survey'){
			$years=$this->get_data_collection_years($type,$metadata);
			return $years;
		}
		else if ($type=='timeseries')
		{
			$years=$this->get_data_collection_years($type,$metadata);
			return $years;
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

		if ($type=='survey'){
        	$data_coll=get_array_nested_value($options,'study_desc/study_info/coll_dates');
		}
		else if ($type=='timeseries'){
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
    
    



}