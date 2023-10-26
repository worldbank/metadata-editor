<?php

class DDI2Reader
{

    private $xml_file;
    private $xml_obj;
    private $namespaces = array();
    private $elements = array();

    //for tabular elements
    private $table_elements = array();

    //all key/value pairs from xml
    protected $metadata_array = array();

    //field labels
    private $labels = array();

    private $xml_reader = NULL;
    private $xml_reader_is_valid = TRUE;

    private $variable_iterator = NULL;


    function __construct($xml_file)
    {
        $xpath_group = array();

        $xpath_group['codeBook/fileDscr'] = array(
            'label' => 'file description',
            'type' => 'table',
            'cols' => array(
                '@ID' => 'id',
                '@URI' => 'uri',
                'fileTxt/fileName' => 'filename',
                'fileTxt/fileName/@ID' => 'file_id',
                'fileTxt/dimensns/caseQnty' => 'caseQnty',
                'fileTxt/dimensns/varQnty' => 'varQnty',
                'fileTxt/fileType' => 'filetype',
                'fileTxt/fileCont' => 'fileCont',
                'fileTxt/filePlac' => 'filePlac'
            )
        );

        $xpath_group['codeBook/dataDscr/varGrp'] = array(
            'label' => 'Variable group',
            'type' => 'table',
            'cols' => array(
                '@ID' => 'vgid',
                '@type' => 'group_type',
                '@varGrp' => 'variable_groups',
                '@var' => 'variables',
                'labl' => 'label',
                'defntn' => 'definition'
            )
        );

        $xpath_group['codeBook/stdyDscr/stdyInfo/sumDscr/collDate'] = array(
            'label' => 'file description',
            'type' => 'table',
            'cols' => array(
                '@date' => 'date',
                '@event' => 'event',
                '@cycle' => 'cycle',
            )
        );


        $xpath_group['/ddi:codeBook/ddi:stdyDscr/ddi:stdyInfo/ddi:subject/ddi:topcClas'] = array(
            'label' => 'Topics Classifications',
            'type' => 'table',
            'cols' => array(
                '.' => 'topic',
                '@vocabURI' => 'uri',
                '@vocab' => 'vocab'
            )
        );


        $xpath_group['codeBook/docDscr/citation/prodStmt/producer'] = array(
            'label' => 'Producers',
            'type' => 'table',
            'cols' => array(
                '.' => 'name',
                '@abbr' => 'abbr',
                '@affiliation' => 'affiliation',
                '@role' => 'role',
            )
        );

        $xpath_group['codeBook/stdyDscr/citation/distStmt/depositr'] = array(
            'label' => 'Depositors',
            'type' => 'table',
            'cols' => array(
                '.' => 'name',
                '@abbr' => 'abbr',
                '@affiliation' => 'affiliation',
                '@URI' => 'uri'
            )
        );

        $xpath_group['codeBook/stdyDscr/citation/distStmt/distrbtr'] = array(
            'label' => 'Depositors',
            'type' => 'table',
            'cols' => array(
                '.' => 'name',
                '@abbr' => 'abbr',
                '@affiliation' => 'affiliation',
                '@URI' => 'uri'
            )
        );

        $xpath_group['codeBook/stdyDscr/citation/holdings'] = array(
            'label' => 'Holdings',
            'type' => 'table',
            'cols' => array(
                '.' => 'name',
                '@location' => 'location',
                '@callno' => 'callno',
                '@URI'=>'uri'
            )
        );

        $xpath_group['codeBook/stdyDscr/studyAuthorization/authorizingAgency'] = array(
            'label' => 'Agency',
            'type' => 'table',
            'cols' => array(
                '.' => 'name',
                '@abbr' => 'abbr',
                '@affiliation' => 'affiliation'
            )
        );

        $xpath_group['codeBook/stdyDscr/stdyInfo/sumDscr/geoBndBox'] = array(
            'label' => 'BBOX',
            'type' => 'table',
            'cols' => array(
                'westBL' => 'west',
                'eastBL' => 'east',
                'southBL' => 'south',
                'northBL' => 'north'
            )
        );

        $xpath_group['codeBook/stdyDscr/stdyInfo/sumDscr/boundPoly/polygon/point'] = array(
            'label' => 'Polygon points',
            'type' => 'table',
            'cols' => array(
                'gringLat' => 'lat',
                'gringLon' => 'lon',
            )
        );
    
        $xpath_group['codeBook/stdyDscr/stdyInfo/exPostEvaluation/evaluator'] = array(
            'label' => 'Evaluator',
            'type' => 'table',
            'cols' => array(
                '.' => 'name',
                '@abbr' => 'abbr',
                '@affiliation' => 'affiliation',
                '@role' => 'role'
            )
        );

        /*$xpath_group["codeBook/stdyDscr/studyDevelopment/developmentActivity/participant"] = array(
            'label' => 'participant',
            'type' => 'table',
            'cols' => array(
                '.' => 'name',
                '@affiliation' => 'affiliation',
                '@role' => 'role'
            )
        );*/

        $xpath_group["codeBook/stdyDscr/studyDevelopment/developmentActivity"] = array(
            'label' => 'Study development activity',
            'type' => 'table',
            'is_nested'=>true,
            'cols' => array(
                '@type'=>'activity_type',
                'description' => 'activity_description',
                'participant' => 'participants',
                'resource' => 'resources',
                'outcome'=> 'outcome'
            )
        );

        $xpath_group["codeBook/stdyDscr/stdyInfo/qualityStatement/standardsCompliance/standard"] = array(
            'label' => 'Standard',
            'type' => 'table',
            'cols' => array(
                'standardName'=>'name',
                'producer' => 'producer'
            )
        );
        

        $xpath_group["codeBook/stdyDscr/studyDevelopment/developmentActivity/resource"] = array(
            'label' => 'Resource',
            'type' => 'table',            
            'cols' => array(
                'dataSrc'=>'name',
                'srcOrig' => 'origin',
                'srcChar' => 'characteristics',
            )
        );

        $xpath_group["codeBook/stdyDscr/studyDevelopment/developmentActivity/participant"] = array(
            'label' => 'Participant',
            'type' => 'table',            
            'cols' => array(
                '.'=>'name',
                'affiliation' => 'affiliation',
                'role' => 'role',
            )
        );

        $xpath_group["codeBook/stdyDscr/dataAccs/useStmt/confDec"] = array(
            'label' => 'Data access confidentiality',
            'type' => 'table',
            'cols' => array(
                '.' => 'txt',
                '@required' => 'required',
                '@formNo' => 'form_no',
                '@URI' => 'form_uri'
            )
        );

        $xpath_group["codeBook/stdyDscr/method/dataColl/collectorTraining"] = array(
            'label' => 'Collector training',
            'type' => 'table',
            'cols' => array(
                '.' => 'txt',
                '@type' => 'type',
                '.' => 'training'
            )
        );

        $xpath_group["codeBook/stdyDscr/dataAccs/useStmt/specPerm"] = array(
            'label' => 'Special permissions',
            'type' => 'table',
            'cols' => array(
                '.' => 'txt',
                '@required' => 'required',
                '@formNo' => 'form_no',
                '@URI' => 'form_uri'
            )
        );

        $xpath_group["codeBook/stdyDscr/method/codingInstructions"] = array(
            'label' => 'Data access confidentiality',
            'type' => 'table',            
            'cols' => array(
                //'.' => 'txt',
                '@relatedProcesses' => 'related_processes',
                '@type' => 'type',
                'txt' => 'txt',
                'command' => 'command',
                'command/@formalLanguage' => 'formal_language',
            )
        );

        
        

        $xpath_group["codeBook/stdyDscr/dataAccs/useStmt/specPerm"] = array(
            'label' => 'Special permissions',
            'type' => 'table',
            'cols' => array(
                '.' => 'txt',
                '@required' => 'required',
                '@formNo' => 'form_no',
                '@URI' => 'uri'
            )
        );

        

        $xpath_group['codeBook/stdyDscr/stdyInfo/subject/topcClas'] = array(
            'label' => 'Topics',
            'type' => 'table',
            'cols' => array(
                '.' => 'topic',
                '@vocab' => 'vocab',
                '@vocabURI' => 'uri',
            )
        );

        $xpath_group['codeBook/stdyDscr/citation/prodStmt/fundAg'] = array(
            'label' => 'Funding',
            'type' => 'table',
            'cols' => array(
                '.' => 'name',
                '@abbr' => 'abbr',
                '@role' => 'role',
            )
        );


        $xpath_group['codeBook/stdyDscr/stdyInfo/sumDscr/timePrd'] = array(
            'label' => 'Time Periods',
            'type' => 'table',
            'cols' => array(
                '@date' => 'date',
                '@event' => 'event',
                '@cycle' => 'cycle',
            )
        );

        
        $xpath_group['codeBook/stdyDscr/method/dataProcessing'] = array(
            'label' => 'Data processing',
            'type' => 'table',
            'cols' => array(
                '.' => 'description',
                '@type' => 'type',
            )
        );

        $xpath_group['codeBook/stdyDscr/method/dataColl/sampleFrame/validPeriod'] = array(
            'label' => 'Valid period',
            'type' => 'table',
            'cols' => array(
                '.' => 'date',
                '@event' => 'event',
            )
        );

        $xpath_group['codeBook/stdyDscr/method/dataColl/sampleFrame/referencePeriod'] = array(
            'label' => 'reference period',
            'type' => 'table',
            'cols' => array(
                '.' => 'date',
                '@event' => 'event',
            )
        );

        $xpath_group['codeBook/stdyDscr/stdyInfo/sumDscr/nation'] = array(
            'label' => 'Countries',
            'type' => 'table',
            'cols' => array(
                '.' => 'name',
                '@abbr' => 'abbreviation',
            )
        );


        $xpath_group['codeBook/stdyDscr/method/dataColl/sources'] = array(
            'label' => 'Sources',
            'type' => 'table',
            'cols' => array(
                'dataSrc' => 'name',
                'srcOrig' => 'origin',
                'srcChar' => 'characteristics',
            )
        );



        $xpath_group['codeBook/stdyDscr/method/dataColl/dataCollector'] = array(
            'label' => 'Data Collectors',
            'type' => 'table',
            'cols' => array(
                '.' => 'name',
                '@abbr' => 'abbr',
                '@role' =>'role',
                '@affiliation' => 'affiliation',
            )
        );

        //access authority
        $xpath_group['codeBook/stdyDscr/dataAccs/useStmt/contact'] = array(
            'label' => 'Data Collectors',
            'type' => 'table',
            'cols' => array(
                '.' => 'name',
                '@affiliation' => 'affiliation',
                '@email' => 'email',
                '@URI' => 'uri',
            )
        );

        $xpath_group['codeBook/stdyDscr/citation/rspStmt/AuthEnty'] = array(
            'label' => 'authenty',
            'type' => 'table',
            'cols' => array(
                '.' => 'name',
                '@affiliation' => 'affiliation',
            )
        );


        $xpath_group['codeBook/stdyDscr/citation/rspStmt/othId'] = array(
            'label' => 'othid',
            'type' => 'table',
            'cols' => array(
                'p' => 'name',
                '@affiliation' => 'affiliation',
                '@email' => 'email',
                '@role' => 'role',
            )
        );

        $xpath_group['codeBook/stdyDscr/citation/prodStmt/producer'] = array(
            'label' => 'othid',
            'type' => 'table',
            'cols' => array(
                '.' => 'name',
                '@abbr'=>'abbr',
                '@affiliation' => 'affiliation',
                '@role' => 'role',
            )
        );


        $xpath_group['codeBook/stdyDscr/citation/distStmt/contact'] = array( 
            'label' => 'Data Collectors',
            'type' => 'table',
            'cols' => array(
                '.' => 'name',
                '@affiliation' => 'affiliation',
                '@email' => 'email',
                '@URI' => 'uri',
            )
        );

        $xpath_group['codeBook/stdyDscr/dataAccs/setAvail/accsPlac'] = array(
            'label' => 'Data Collection Location',
            'type' => 'table',
            'cols' => array(
                '.' => 'name',
                '@URI' => 'uri',
            )
        );


        $xpath_group['codeBook/stdyDscr/stdyInfo/subject/keyword'] = array(
            'label' => 'Keyword',
            'type' => 'table',
            'cols' => array(
                '.' => 'keyword',                
                '@vocab'=>'vocab',
                '@vocabURI' => 'uri'
            )
        );

        $xpath_group['var/catgry'] = array(
            'label' => 'Variable category',
            'type' => 'table',
            'cols' => array(
                'catValu' => 'value',
                'labl' => 'label',
                'catStat' => 'stats',
                'catStat/@type' => 'type',
            )
        );


        $xpath_group['var/sumStat'] = array(
            'label' => 'sumStat',
            'type' => 'table',
            'cols' => array(
                '.' => 'value',
                '@type' => 'type',
            )
        );

        $xpath_group['var/concept'] = array(
            'label' => 'sumStat',
            'type' => 'table',
            'cols' => array(
                '.' => 'name',
                '@vocab' => 'vocab',
                '@uri' => 'uri'
            )
        );
        

        $this->table_elements = $xpath_group;
        
        $this->initialize($xml_file);        
    }


    //returns the iterator for variables
    public function get_variable_iterator()
    {
        return $this->variable_iterator;
    }


    /**
     *
     * Intialize the reader
     *
     * @xml_file    path to the xml file
     **/
    public function initialize($xml_file)
    {
        if (!file_exists($xml_file)) {
            throw new Exception("file not found: " . $xml_file);
        }

        $this->xml_file = $xml_file;

        //set the variable iterator
        //$this->variable_iterator = new varIterator($xml_file);

        //generator
        $this->variable_iterator=$this->getVariables($xml_file);
        $this->metadata_array=$this->extract_study_meta_array();
    }


    /**
     * 
     * Variable iterator using generators
     * 
     */
    function getVariables($xml_file) 
    {
        require dirname(__FILE__).'/DdiVariable.php';

        $xml_reader= new XMLReader();

        //read the xml file
        if(!$this->xml_reader->open($xml_file,null,LIBXML_NOERROR | LIBXML_NOWARNING)){
            throw new Exception("DDIVARIABLEGENERATOR::FAILED TO OPEN FILE:".$xml_file );
        }

        //read only the DDI docDscr and stdyDscr sections
        while ($this->xml_reader->read() )
        {
            if ($this->xml_reader->nodeType == XMLReader::ELEMENT && $this->xml_reader->localName == "var")
            {
                //get variable xml
                $xml=$this->xml_reader->readOuterXML();

                //convert to xml object
                if (!$xml_obj=simplexml_load_string($xml)){
                    throw new Exception("VARIABLE OUTPUT NOT VALID: ".$xml);
                }

                yield new DdiVariable($xml_obj);                
            }
        }
    }





    //returns the study IDNO from the stdyDscr
    public function get_study_IDNO()
    {
        $study_metadata_arr = $this->get_ddi_part_array('stdyDscr');
        //$idno=$study_metadata_arr['codeBook/stdyDscr/citation/titlStmt/IDNo'];

        if (isset($study_metadata_arr['codeBook/stdyDscr/citation/titlStmt/IDNo']) &&
            count($study_metadata_arr['codeBook/stdyDscr/citation/titlStmt/IDNo']) > 0
        ) {
            return trim($study_metadata_arr['codeBook/stdyDscr/citation/titlStmt/IDNo'][0]);
        }
    }


    /**
     *
     * Returns a key/values for a part of the DDI document
     *
     * @section    codeBook, docDscr, stdyDscr, fileDscr
     **/

    public function get_ddi_part_array($section)
    {
        $xml_reader = new XMLReader();

        if (!$xml_reader->open($this->xml_file, null, LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return false;
        }
        
        $key_values = array();
        $xml_obj = NULL;

        while ($xml_reader->read()) {
            if ($xml_reader->nodeType == XMLReader::ELEMENT && $xml_reader->localName == "codeBook" && $section == 'codeBook') {
                //read the codeBook attributes
                $key_values['ID'] = $xml_reader->getAttribute('ID');
                $key_values['xmlns'] = $xml_reader->getAttribute('xmlns');
                $key_values['version'] = $xml_reader->getAttribute('version');

                break;
            } else if ($xml_reader->nodeType == XMLReader::ELEMENT
                && in_array($xml_reader->localName, array('docDscr', 'stdyDscr'))
                && in_array($section, array('docDscr', 'stdyDscr'))
                && $section === $xml_reader->localName
            ) {
                $xml_obj = simplexml_load_string($xml_reader->readOuterXML());
                $parent_path = 'codeBook/' . $xml_obj->getName();
                $key_values = array();
                $key_values = $this->get_child_elements_array($xml_obj, $parent_path, $key_values);
                break;

            } else if ($xml_reader->nodeType == XMLReader::ELEMENT
            && in_array($xml_reader->localName, array('varGrp'))
            && in_array($section, array('varGrp'))
            && $section === $xml_reader->localName
            ) {
                $xml_obj = simplexml_load_string($xml_reader->readOuterXML());
                $parent_path = 'codeBook/' . $xml_obj->getName();
                $key_values = array();
                $key_values = $this->get_child_elements_array($xml_obj, $parent_path, $key_values);
                break;

            } else if ($xml_reader->nodeType == XMLReader::ELEMENT
                && in_array($xml_reader->localName, array('fileDscr'))
                && in_array($section, array('fileDscr'))
                && $section === $xml_reader->localName
            ) {
                $xml_obj = simplexml_load_string($xml_reader->readOuterXML());
                $parent_path = 'codeBook/' . $xml_obj->getName();
                $key_values = $this->get_child_elements_array($xml_obj, $parent_path, $key_values);
                continue;
            } else if ($xml_reader->nodeType == XMLReader::ELEMENT && $xml_reader->localName === 'dataDscr') {
                break;
            }
        }

        $xml_reader->close();

        //apply additional transforms for converting data
        $coll_items=array(
            'codeBook/stdyDscr/stdyInfo/sumDscr/timePrd',
            'codeBook/stdyDscr/stdyInfo/sumDscr/collDate'
        );

        foreach($coll_items as $date_field) {
            if (array_key_exists($date_field, $key_values)) {
                $key_values[$date_field] = $this->transform_collection_dates($key_values[$date_field]);
            }
        }
        
        /*
        //data source element transform
		$data_source_items=array(
			'codeBook/stdyDscr/studyDevelopment/developmentActivity/resource/dataSrc',
			'codeBook/stdyDscr/method/dataColl/sources/dataSrc'
		);

		foreach($data_source_items as $source) {
			if (array_key_exists($source, $key_values)) {
				$key_values[$source] = $this->transform_data_source($key_values[$source]);
			}
		}        
        */

		//convert access_place - JSON schema supports only a single value
		$access_place_key='codeBook/stdyDscr/dataAccs/setAvail/accsPlac';
		if (array_key_exists($access_place_key, $key_values)) {
			$access_place=$key_values[$access_place_key];
			if(is_array($access_place)){
				$key_values[$access_place_key]=@$access_place[0]['name'];
				$key_values[$access_place_key.'_url']=@$access_place[0]['uri'];
			}else{
				$key_values[$access_place_key]='';
			}
		}

        return $key_values;
    }


    public function extract_study_meta_array()
    {
        return $this->get_ddi_part_array('stdyDscr');
    }

    public function extract_doc_meta_array()
    {
        return $this->get_ddi_part_array('docDscr');
    }

    public function extract_file_meta_array()
    {
        return $this->get_ddi_part_array('fileDscr');
    }

    public function extract_codebook_meta_array()
    {
        return $this->get_ddi_part_array('codeBook');
    }

    public function extract_var_groups_array()
    {
        return $this->read_variable_groups();
    }


    public function load_metadata()
    {
        $key_value_array = array();
        $parent_path = $this->xml_obj->getName();
        $this->get_child_elements_array($this->xml_obj, $parent_path, $key_value_array);
        $this->metadata_array = $key_value_array;
    }


    public function get_all_metadata()
    {
        $output = array();
        foreach ($this->elements as $name => $xpath) {
            $output[$name] = @$this->metadata_array[$xpath];
        }
        
        return $output;
    }


    //return all lists, e.g. elements, table type elements, field labels
    public function get_lookup_data()
    {
        return array(
            'elements' => $this->elements,
            'table_elements' => $this->table_elements,
            'labels' => $this->labels
        );
    }

    //return all data grouped by group
    public function get_all_metadata_by_groups()
    {
        //get all group keys
        $groups = array_keys($this->geodata_groups);

        $output = array();
        foreach ($groups as $group) {
            $output[$group] = $this->get_metadata_by_group($group);
        }

        return $output;
    }


    public function get_metadata($key)
    {
        return $this->metadata_array[$key];
    }

    public function get_metadata_by_group($group_name)
    {
        if (array_key_exists($group_name, $this->geodata_groups)) {
            //key/values for the group
            $group_items = array();
            $output = array();

            //var_dump($this->geodata_groups[$group_name]);
            //exit;

            foreach ($this->geodata_groups[$group_name] as $item) {
                $group_items[$item] = $this->elements[$item];
            }

            //populate item values
            foreach ($group_items as $key => $element_xpath) {
                $output[$key] = @$this->metadata_array[$element_xpath];
            }
        }

        return $output;
    }


    public function read_variables()
    {
        if (!$this->xml_reader) {
            $this->xml_reader = new XMLReader();

            //read the xml file
            if (!$this->xml_reader->open($this->xml_file, null, LIBXML_NOERROR | LIBXML_NOWARNING)) {
                return false;//can't open the file
            }
        }

        //read only the DDI docDscr and stdyDscr sections
        while ($this->xml_reader->read()) {
            if ($this->xml_reader->nodeType == XMLReader::ELEMENT && $this->xml_reader->localName == "var") {
                return $this->xml_reader->readOuterXML();
            }
        }

        $this->xml_reader->close();
        return;
    }

    public function read_variable_groups()
    {
        if (!$this->xml_reader) {
            $this->xml_reader = new XMLReader();

            //read the xml file
            if (!$this->xml_reader->open($this->xml_file, null, LIBXML_NOERROR | LIBXML_NOWARNING)) {
                return false;//can't open the file
            }
        }

        $groups=array();

        //read only the DDI docDscr and stdyDscr sections
        while ($this->xml_reader->read()) {
            if ($this->xml_reader->nodeType == XMLReader::ELEMENT && $this->xml_reader->localName == "varGrp") {
                $xml_obj = simplexml_load_string($this->xml_reader->readOuterXML());
                $parent_path = 'codeBook/dataDscr/' . $xml_obj->getName();
                //$parent_path='varGrp';
                $output=array();
                $var_grp= $this->get_child_elements_array($xml_obj, $parent_path,$output);

                $groups[]=$var_grp['codeBook/dataDscr/varGrp'][0];
            }
        }

        $this->xml_reader->close();
        return $groups;
    }


    //print all element paths and values
    public function print_all_key_values()
    {
        $this->xml_obj = simplexml_load_file($this->xml_file);
        $this->namespaces = $this->xml_obj->getNamespaces(true);

        $key_value_array = array();
        $parent_path = $this->xml_obj->getName();

        echo '<table>';
        $this->print_child_elements($this->xml_obj, $parent_path);
        echo '</table>';
    }


    /*
    *
    * Returns an array of all xml document paths
    *
    *
    */
    public function extract_meta_key_values()
    {
        $key_value_array = array();
        $parent_path = $this->xml_obj->getName();

        echo '<table>';
        $this->print_child_elements($this->xml_obj, $parent_path);
        //$this->get_child_elements_array($this->xml_obj,$parent_path,$key_value_array);
        //echo '<pre>';
        //print_r($key_value_array);
        echo '</table>';
    }

    /*
    *
    * print child paths
    *
    */
    public function print_child_elements(&$xml_obj, $parent_path = "/")
    {
        if ($xml_obj->getName() == 'dataDscr') {
            return;
        }


        //var_dump($this->table_elements);exit;
        if (array_key_exists($parent_path, $this->table_elements)) {
            $result = $this->get_element_flattened($xml_obj, $element_parent_path = NULL, $result = array());

            echo '<tr><td>';
            echo $parent_path;
            echo '</td><td>';
            echo '<pre>';
            print_r($result);
            echo '</pre>';
            echo '</td></tr>';
            return;
        } else {
            if (trim((string)$xml_obj) != "") {
                echo '<tr><td style="text-align:left;">';
                echo $parent_path;////$this->make_path($parent_path,$xml_obj->getName());
                echo '</td><td>';
                echo trim((string)$xml_obj);
                echo '</td></tr>';
            }
        }


        //add attributes
        foreach ($xml_obj->attributes() as $att_name => $att_value) {
            $xpath_ = $parent_path . '/@' . $att_name;

            echo '<tr><td style="text-align:left;">';
            echo $xpath_;
            echo '</td><td>';
            echo (string)$att_value;
            echo '</td></tr>';
        }

        //get namespaces for the element
        $this->namespaces = $xml_obj->getNamespaces(true);

        foreach ($this->namespaces as $ns_key => $ns_value) {
            foreach ($xml_obj->children($ns_value) as $child) {
                $this->print_child_elements($child, $parent_path . '/' . $child->getName());
            }
        }
    }



    /*
    *
    * return an array of all child elements with values
    *
    */
    public function get_child_elements_array(&$xml_obj, $parent_path = "/", &$elements_array)
    {
        if (array_key_exists($parent_path, $this->table_elements)) {

            if (isset($this->table_elements[$parent_path]['is_nested']) && $this->table_elements[$parent_path]['is_nested']==true)
            {
                //custom mapping for developmentActivity element
                if ($parent_path=='codeBook/stdyDscr/studyDevelopment/developmentActivity'){
                    $elements_array[$parent_path][]=$this->get_development_activity($xml_obj);
                }
                return $elements_array;
            }

            $result = $this->get_element_flattened($xml_obj, $element_parent_path = NULL, $result);
            $cols = $this->table_elements[$parent_path]['cols'];

            //remove keys not registered
            foreach ($result as $key => $value) {
                if (!array_key_exists($key, $cols)) {
                    unset($result[$key]);
                }
            }

            //use column names instead of the xpaths to table type elements
            $column_data = array();
            foreach ($cols as $xpath => $name) {
                $column_data[$name] = @$result[$xpath];
            }

            $result = $column_data;

            $elements_array[$parent_path][] = $result;

            return $elements_array;//to avoid duplicate items
        } else {
            if (trim((string)$xml_obj) != "") {
                $elements_array[$parent_path][] = trim((string)$xml_obj);
            }
        }


        //add attributes
        foreach ($xml_obj->attributes() as $att_name => $att_value) {
            $xpath_ = $parent_path . '/@' . $att_name;

            $elements_array[$xpath_] = (string)$att_value;
        }


        //get namespaces for the element
        $this->namespaces = $xml_obj->getNamespaces(true);

        foreach ($this->namespaces as $ns_key => $ns_value) {
            foreach ($xml_obj->children($ns_value) as $child) {
                $this->get_child_elements_array($child, $parent_path . '/' . $child->getName(), $elements_array);
            }
        }

        return $elements_array;
    }


    //flatten the element values and attributes into an flat array
    function get_element_flattened(&$xml_obj, $parent_path = NULL, &$output)
    {
        //element value?
        if ($parent_path) {
            $output[$parent_path] = trim((string)$xml_obj);
        } else {
            $output["."] = trim((string)$xml_obj);
        }

        //attributes
        foreach ($xml_obj->attributes() as $att_name => $att_value) {
            $xpath_ = $this->make_path($parent_path, '@' . $att_name);
            $output[$xpath_] = (string)$att_value;
        }

        $namespaces = $xml_obj->getNamespaces(true);
        foreach ($namespaces as $ns_key => $ns_value) {
            foreach ($xml_obj->children($ns_value) as $child) {
                $this->get_element_flattened($child, $this->make_path($parent_path, $child->getName()), $output);
            }

        }
        return $output;
    }


    //creates and normalizes the path based on parent and child element
    function make_path($parent_path, $child_element)
    {
        if (!$parent_path) {
            return $child_element;
        } else {
            return $parent_path . '/' . $child_element;
        }
    }

    //transform the timePrd and collDate array from date, event, cycle to start, end, cycle format
    private function transform_collection_dates($data)
    {
        $output=array();
        for($i=0;$i<count($data);$i+=2) {
            //if (isset($data[$i]['cycle'])){
                $output[] = array(
                    'start' => $data[$i]['date'],
                    'end' => @$data[$i + 1]['date'],
                    'cycle' => @$data[$i]['cycle']
                );
            //}
        }
        return $output;
    }
    

    private function transform_data_source($data)
	{
		$output=array();
		foreach($data as $item){
			$output[]['source']=$item;
		}
		return $output;
	}



    /**
     * 
     * 
     * @xml_obj = codeBook/stdyDscr/studyDevelopment/developmentActivity
     * 
     */
    function get_development_activity($node)
    {        
        $activity_item=array(
            'activity_type'=>$this->get_attribute_value($node, 'type'),
            'activity_description'=>strval($node->description),
            'participants'=>$this->get_development_activity_participants($node),
            'resources'=>$this->get_development_activity_resources($node),
            'outcome'=>strval($node->outcome),
        );

        return $activity_item;
    }

    function get_development_activity_resources($node){

        $resource = $node->resource;

        $resource_output=array();
        foreach ($resource as $resource) {
            $resource_output[]=array(
                'name'=>strval($resource->dataSrc),
                'origin'=>strval($resource->srcOrig),
                'characteristics'=>strval($resource->srcChar),
            );
        }
        return $resource_output;
    }
    
    function get_development_activity_participants($node)
    {
        $participants = $node->participant;
        $participants_output=array();
        foreach ($participants as $participant) {
            $participants_output[]=array(
                'affiliation'=>$this->get_attribute_value($participant, 'affiliation'),
                'role'=>$this->get_attribute_value($participant, 'role'),
                'name'=>strval($participant),
            );
        }
        return $participants_output;
    }
    
    function get_attribute_value($node, $attribute_name)
    {
        foreach ($node->attributes() as $att_name=>$att_value) {
            if ($att_name == $attribute_name) {
                return strval($att_value);
            }
        }
        return null;
    }
}




/*
*
*
*
* Iterator for DDI variables
*
*
*/

class varIterator implements Iterator {
	
	private $xml_reader=NULL;
	private $position=0;
	private $is_valid=TRUE;
	
	public function __construct($xml_file) 
	{
		$this->xml_reader= new XMLReader();
	
		//read the xml file
		if(!$this->xml_reader->open($xml_file,null,LIBXML_NOERROR | LIBXML_NOWARNING))
		{ 
			return false;//can't open the file
		}
		
		//read only the DDI docDscr and stdyDscr sections 
		while ($this->xml_reader->read() ) 
		{
			if ($this->xml_reader->nodeType == XMLReader::ELEMENT && $this->xml_reader->localName == "var") 
			{
				$this->position=0;
				break;
			}
		}
				
    }

    function rewind() {
		//return $this->xml_reader->readOuterXML();
    }

    function current() {
	
		if ($this->xml_reader->nodeType == XMLReader::ELEMENT && $this->xml_reader->localName == "var") 
		{
				return $this->xml_reader->readOuterXML();
		}
	}

    function key() {}

    function next() 
	{		
		$this->is_valid=$this->xml_reader->next();
		
		if (!$this->is_valid)
		{
			return false;
		}
		
		if ($this->is_valid==TRUE && $this->xml_reader->nodeType == XMLReader::ELEMENT && $this->xml_reader->localName == "var") 
		{
			$this->position++;
		}
		else
		{
			$this->next();
		}
	}
		
    function valid() {
		return $this->is_valid;
    }
}




/* End of file ddi_reader.php */
/* Location: ./application/libraries/ddi_reader.php */