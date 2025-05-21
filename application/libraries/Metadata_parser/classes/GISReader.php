<?php

class GISReader implements ReaderInterface{
    
    
    private $file;
    private $isoreader;
    private $metadata;
    
    public function __construct($file){
        $this->file=$file;        
        require_once dirname(__FILE__).'/ISO19115_Parser.php';
		$this->isoreader= new ISO19115_Parser();
        $this->isoreader->initialize($file);		
        $this->metadata= $this->isoreader->xml_to_array();
        
        //echo '<pre>';
        //var_dump($this->metadata);
    }
    
    //find an item by their key
    public function get_key($key)
    {
        if (array_key_exists($key,$this->metadata))
        {
            return $this->metadata[$key];
        }
        
        return false;
    }
    
    /**
     *
     * Creates a string value out of array type elements
     *
     * Note: uses \r\n for line breaks between multiple rows
     *
     **/
    public function array_to_string($data,$type='text')
    {
			if(!$data)
            {
                return NULL;
            }
            
            if ($type=='text' || $type=='string')
			{
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
    
    
    
    public function get_id(){
        $idno=null; //$this->get_key("metadata_file_identifier");
        
        //generate id if none defined
        if (!$idno)
        {
            $title=$this->get_title();
            
            if ($title)
            {
                return md5($title);
            }
        }
        
        return $idno;
    }
    
    
    public function get_title(){
        return $this->get_key('ident_title');
    }
    public function get_abbreviation(){}
    
    public function get_authenty()
    {
        $data=$this->get_key("ident_contacts");
        
        if (!$data)
        {
            return NULL;
        }
        
        $names= array_unique(array_column($data,"org_name"));
        
        $output=array();
        foreach($names as $name)
        {
            $output[]=array('name'=>$name);
        }
        
        return $output;
    }
    
    
    public function get_producers()
    {
         $data=$this->get_key("metadata_contacts");
        
        if (!$data)
        {
            return NULL;
        }
        
        $names= array_unique(array_column($data,"org_name"));
        
        $output=array();
        foreach($names as $name)
        {
            $output[]=array('name'=>$name);
        }
        
        return $output;
    }
    
    
    public function get_sponsors(){}
    
    public function get_start_year()
	{
		$years=$this->get_years();
		return min($years);
	}
	
	public function get_end_year()
	{
		$years=$this->get_years();
		return max($years);
	}
    
    public function get_years(){
        $data= $this->get_key("ident_dates");
        
        if (!$data)
        {
            return 0;
        }
        
        $years=array();
		foreach($data as $row)
		{
			if (!$row){continue;}
			
			$years[]=(integer)substr($row['date'],0,4);
		}

		//create years range
		if (count($years)>0)
		{		
			$year_min=min($years);
			$year_max=max($years);			
			$years= range($year_min, $year_max);
		}
        
        return $years;
    }
    
    public function get_bounding_box()
    {
        return $this->get_key("ident_extent_bbox");
    }
    
    
    public function get_countries(){
        $data=$this->get_key("ident_contacts");
        
        if (!$data)
        {
            return NULL;
        }
        
        $countries= array_column($data,"country");
        
        $output=array();
        foreach($countries as $country)
        {
            $output[]=array('name'=>$country);
        }
        return $output;
    }
    
    
    public function get_languages()
    {
        $data= $this->get_key("ident_language");
        
        if($data)
        {
            return (array)$data;
        }        
    }
    
    public function get_countries_str()
    {    
        $countries=$this->get_countries();
		
		if (!$countries)
		{
			return NULL;
		}
		
		$names=array_column($countries,'name');
		return implode(", ", $names);       
    }
    
    public function get_topics(){}
    public function get_keywords(){}
    public function get_metadata_array(){
        return $this->metadata;
    }
    
    public function get_data_files(){}
    
    //return iterator for variable level metadata
    public function get_variable_iterator(){}
}
