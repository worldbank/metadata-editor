<?php

class DDIReader implements ReaderInterface {

    //private $variable_iterator = NULL;
    private $file;
    private $ddi2reader;
	private $metadata;
	private $variable_groups;	
	private $metadata_short_names=array(
		"ddi_version"=>"codeBook/@version",
		"ddi_id"=>"codeBook/@ID",
		"ddi_lang"=>"codeBook/@lang",
		"doc_producer"=>"codeBook/docDscr/citation/prodStmt/producer",
		"doc_version"=>"codeBook/docDscr/citation/verStmt/version",
		"doc_idno"=>"codeBook/docDscr/citation/titlStmt/IDNO",
		"doc_titl"=>"codeBook/docDscr/citation/titlStmt/titl",
		"stdy_titl"=>"codeBook/stdyDscr/citation/titlStmt/titl",
		"stdy_sub_titl"=>"codeBook/stdyDscr/citation/titlStmt/subTitl",
		"stdy_alt_titl"=>"codeBook/stdyDscr/citation/titlStmt/altTitl",
		"stdy_par_titl"=>"codeBook/stdyDscr/citation/titlStmt/parTitl",
		"stdy_id"=>"codeBook/stdyDscr/citation/titlStmt/IDNo",
		"stdy_authenty"=>"codeBook/stdyDscr/citation/rspStmt/AuthEnty",
		"stdy_othid"=>"codeBook/stdyDscr/citation/rspStmt/othId",
		"stdy_producer"=>"codeBook/stdyDscr/citation/prodStmt/producer",
		"stdy_copyright"=>"codeBook/stdyDscr/citation/prodStmt/copyright",
		"stdy_fundag"=>"codeBook/stdyDscr/citation/prodStmt/fundAg",
		"stdy_contact"=>"codeBook/stdyDscr/citation/distStmt/contact",
		"stdy_sername"=>"codeBook/stdyDscr/citation/serStmt/serName",
		"stdy_serinfo"=>"codeBook/stdyDscr/citation/serStmt/serInfo",
		"stdy_version"=>"codeBook/stdyDscr/citation/verStmt/version",
		"stdy_version_date"=>"codeBook/stdyDscr/citation/verStmt/version/@date",
		"stdy_version_notes"=>"codeBook/stdyDscr/citation/verStmt/notes",
		"stdy_keyword"=>"codeBook/stdyDscr/stdyInfo/subject/keyword",
		"stdy_topic"=>"codeBook/stdyDscr/stdyInfo/subject/topcClas",
		"stdy_abstract"=>"codeBook/stdyDscr/stdyInfo/abstract",
		"stdy_time_prd"=>"codeBook/stdyDscr/stdyInfo/sumDscr/timePrd",
		"stdy_coll_date"=>"codeBook/stdyDscr/stdyInfo/sumDscr/collDate",
		"stdy_nation"=>"codeBook/stdyDscr/stdyInfo/sumDscr/nation",
		"stdy_geogcover"=>"codeBook/stdyDscr/stdyInfo/sumDscr/geogCover",
		"stdy_anlyunit"=>"codeBook/stdyDscr/stdyInfo/sumDscr/anlyUnit",
		"stdy_universe"=>"codeBook/stdyDscr/stdyInfo/sumDscr/universe",
		"stdy_datakind"=>"codeBook/stdyDscr/stdyInfo/sumDscr/dataKind",
		"stdy_notes"=>"codeBook/stdyDscr/stdyInfo/notes",
		"stdy_data_collector"=>"codeBook/stdyDscr/method/dataColl/dataCollector",
		"stdy_data_coll_freq"=>"codeBook/stdyDscr/method/dataColl/frequenc",
		"stdy_data_coll_src"=>"codeBook/stdyDscr/method/dataColl/sources/dataSrc",
		"stdy_samp_proc"=>"codeBook/stdyDscr/method/dataColl/sampProc",
		"stdy_deviat"=>"codeBook/stdyDscr/method/dataColl/deviat",
		"stdy_collmode"=>"codeBook/stdyDscr/method/dataColl/collMode",
		"stdy_resinstru"=>"codeBook/stdyDscr/method/dataColl/resInstru",
		"stdy_collsite"=>"codeBook/stdyDscr/method/dataColl/collSitu",
		"stdy_actmin"=>"codeBook/stdyDscr/method/dataColl/actMin",
		"stdy_weight"=>"codeBook/stdyDscr/method/dataColl/weight",
		"stdy_cleanops"=>"codeBook/stdyDscr/method/dataColl/cleanOps",
		"stdy_method_notes"=>"codeBook/stdyDscr/method/notes",
		"stdy_resprate"=>"codeBook/stdyDscr/method/anlyInfo/respRate",
		"stdy_estsamperr"=>"codeBook/stdyDscr/method/anlyInfo/EstSmpErr",
		"stdy_data_appr"=>"codeBook/stdyDscr/method/anlyInfo/dataAppr",
		"stdy_dataaccs_confdec"=>"codeBook/stdyDscr/dataAccs/useStmt/confDec",
		"stdy_dataaccs_contact"=>"codeBook/stdyDscr/dataAccs/useStmt/contact",
		"stdy_dataaccs_citreq"=>"codeBook/stdyDscr/dataAccs/useStmt/citReq",
		"stdy_dataaccs_conditions"=>"codeBook/stdyDscr/dataAccs/useStmt/conditions",
		"stdy_dataaccs_disclaimer"=>"codeBook/stdyDscr/dataAccs/useStmt/disclaimer"
	);

	
    
    //@metadata_keys_map - array of keys to rename metadata keys
	public function __construct($file,$metadata_keys_map=NULL)
	{
        require_once dirname(__FILE__).'/DDI2Reader.php';
        require_once dirname(__FILE__).'/DdiVariableIterator.php';

        $this->file=$file;
		$this->ddi2reader= new DDI2Reader($file);

		$this->metadata=$this->ddi2reader->extract_doc_meta_array();
		$this->metadata=array_merge($this->metadata,$this->ddi2reader->extract_study_meta_array());

		$this->variable_groups=$this->ddi2reader->extract_var_groups_array();

		//convert NULL To empty string, needed for schema validation
        array_walk_recursive($this->metadata, function(&$item, $key) {
			if ($item == NULL) $item = '';
		});

		/*if ($metadata_keys_map)
		{
			$this->metadata_keys_map=array_merge($this->metadata_keys_map,$metadata_keys_map);
			$this->transform_metadata_keys();
		}*/
    }
    
	private function transform_metadata_keys()
	{
		$output=array();
		
		foreach($this->metadata as $key=>$value)
		{
			if (array_key_exists($key,$this->metadata_keys_map))
			{
				$output[$this->metadata_keys_map[$key]]=$value;
			}			
		}
		
		$this->metadata=$output;
	}
	
	
    //find an item by their key
    public function get_key($key)
    {
		$el_name=$this->metadata_short_names[$key];
        if (array_key_exists($el_name,$this->metadata))
        {
            return $this->metadata[$el_name];
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
    
    
    
    //array for the study level metadata
    public function to_array(){
        $this->metadata= $this->ddi2reader->extract_study_meta_array();
        echo '<pre>';
        var_dump($this->metadata);
        echo 'parsing ddi file '.$this->file;        
    }
    
    
    public function get_variable_iterator()
    {
        return new DdiVariableIterator($this->file);
	}
	
	public function get_variable_groups()
	{
		return $this->variable_groups;
	}
    
    
    public function get_id(){
        return $this->array_to_string($this->get_key('stdy_id'),$type='text');
    }
    
    public function get_title(){    
        return $this->array_to_string($this->get_key('stdy_titl'),$type='text');
    }
	
	public function get_abbreviation(){
		return $this->array_to_string($this->get_key('stdy_par_titl'),$type='text');
	}
    
    public function get_authenty(){
        
        return $this->get_key($key='stdy_authenty');
    
        /*if(!is_array($data))
        {
            return false;
        }

        $authenty=NULL;

        foreach($data as $row)
        {
            $authenty[]=$row['name'];
        }
        
        return $authenty;
        */
    }
    
    public function get_producers()
	{		
		return $this->get_key('stdy_producer');		
	}
	
	
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
	
	
	public function get_data_files()
    {
        $files= $this->ddi2reader->extract_file_meta_array();

        if (isset($files['codeBook/fileDscr']))
        {
            return $files['codeBook/fileDscr'];
        }
    }
	
	
	public function get_sponsors(){
		return $this->get_key('stdy_fundag');		
	}
	
    public function get_years(){
                
        $data=$this->get_key($key='stdy_coll_date');

        if (!$data){
            return 0; //if no years, then default value should be 0 instead of NULL for the filters to work
        }        
        
        $years=array();
		foreach($data as $row)
		{
			if (!$row){continue;}
			
			$years[]=(integer)$row['start'];
			$years[]=(integer)$row['end'];
		}

		//create years range
		if (count($years)>0)
		{		
			$year_min=min($years);
			$year_max=max($years);

            if ($year_min==0)
            {
                $year_min=$year_max;
            }
			$years= range($year_min, $year_max);
		}
        
        return $years;       
    }
	
    public function get_countries(){
		return $this->get_key($key='stdy_nation');
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
	
	public function get_bounding_box()
    {
        return NULL;
    }
	
	public function get_languages()
    {
        return NULL;
    }
    
}
