<?php

class ISO19115_Parser
{
    //https://geo-ide.noaa.gov/wiki/index.php?title=ISO_19115_Core_Elements

    private $xml_obj;
    private $root_tag='gmi:MI_Metadata';//'gmd:MD_Metadata';
    private $xpath_map;


    function initialize($xml_file)
    {
        if (!file_exists($xml_file))
        {
            throw new exception("FILE NOT FOUND ". $xml_file);
        }
        $this->xml_obj= simplexml_load_file($xml_file);

        //set namespaced root element name
        if ($this->xml_obj->getName()=='MD_Metadata'){
            $this->root_tag="gmd:MD_Metadata";
        }        
        
        //register all namespaces used by the standard
        $this->xml_obj->registerXPathNamespace("gco","http://www.isotc211.org/2005/gco");

        $this->xml_obj->registerXPathNamespace('gmd', 'http://www.isotc211.org/2005/gmd');

        
        //automatically register namespaces found in the xml file
        foreach($this->xml_obj->getDocNamespaces() as $strPrefix => $strNamespace) {
            /*if(strlen($strPrefix)==0) {
                $strPrefix="gmd"; //Assign an arbitrary namespace prefix.
            }*/
            $this->xml_obj->registerXPathNamespace($strPrefix,$strNamespace);
        }

        $this->init_xpaths();

        foreach($this->xpath_map as $field_name=>&$field)
        {
            if ($field['type']=='text'){
                $field['data']=$this->xpath_query($field['xpath'],$field['is_repeated']);
            }
            else if ($field['type']=='complex')
            {
                    $field['data']=$this->xpath_query_complex($field);
            }
        }
        
        //normalize
        $this->normalize_keywords();
        $this->normalize_distribution_info();
        

        return;

        echo "<pre>";

        print_r($this->xpath_map);

        return;
    
    
    
    
        foreach($this->xpath_map as $field_name=>&$field)
        {
            if ($field['type']=='text'){
                $field['data']=$this->xpath_query($field['xpath'],$field['is_repeated']);
            }
            else if ($field['type']=='complex'){
                /*foreach($field['items'] as &$item)
                {
                    $item['data']=$this->xpath_query($item['xpath']);
                }*/
                $field['data']=$this->xpath_query_complex($field);
            }
        }

        echo "<pre>";

        print_r($this->xpath_map);
        return;
        $res = $this->xml_obj->xpath("//gmd:fileIdentifier");
        if (!$res){
            echo "nothing!!!";
        }
        foreach($res as $result) {
            print $xpath."<BR>";
            print dom_import_simplexml($result)->textContent;
            //var_dump($result->asXML());
        }
    }
    
    //returns an array of the xml data
    function xml_to_array()
    {
        $output=array();
        
        foreach($this->xpath_map as $key=>$value)
        {
            $output[$key]=$value['data'];
        }
        
        return $output;
    }
    
    
    public function get_key_value($key)
    {
        if (!is_array($this->xpath_map))
        {
            var_dump($key, $this->xpath_map);
            die("xpath_map not an array");
        }
        if (array_key_exists($key, $this->xpath_map))
        {
            return $this->xpath_map[$key]['data'];
        }
    }
    
    
    //find the country from the contacts
    public function get_owner_country()
    {
        //contact can have the following roles
        //right column defines how to treat each role for guessing the owner of the resource
        $roles=array(
                'custodian'             => 'owner',
                'resourceProvider'      => 'distributor',
                'owner'                 => 'owner',
                'user'                  => 'distributor',
                'distributor'           => 'distributor',
                'originator'            => 'owner',
                'pointOfContact'        => 'owner',
                'principalInvestigator' => 'owner',
                'processor'             => 'owner',
                'pubisher'              => 'owner',
                'author'                => 'owner'                    
        );
        
        $countries=array();

        //check the ident_contact
        $contacts=$this->get_key_value('ident_contacts');
        
        if ($contacts)
        {
            foreach($contacts as $contact)
            {
                if (array_key_exists($contact['role'],$roles)){
                    $role=$roles[$contact['role']];
                }
                else{
                    $role='distributor';
                }
                
                if ($contact['country']!==NULL){
                    $countries[$role][]=$contact['country'];
                }
            }
        }
        
        $meta_contacts=$this->get_key_value('metadata_contacts');
        
        
        if ($meta_contacts)
        {
            foreach($meta_contacts as $contact)
            {
                if (array_key_exists($contact['role'],$roles)){
                    $role=$roles[$contact['role']];
                }
                else{
                    $role='distributor';
                }

                if ($contact['country']!==NULL){
                    $countries[$role][]=$contact['country'];
                }
            }
        }
                
        if (isset($countries['owner']))
        {
            return array_unique($countries['owner']);
        }
        
        if (isset($countries['distributor']))
        {
            return array_unique($countries['distributor']);
        }
        
        return NULL;
    }
    
    
    public function get_publication_date()
    {
        $dates=$this->get_key_value('ident_dates');
        
        if ($dates)
        {
            foreach($dates as $date)
            {
                //return the first year
               return substr($date['date'],0,4); 
            }
        }
        
        return NULL;
    }
    
    public function get_authenty()
    {
        //contact can have the following roles
        //right column defines how to treat each role for guessing the owner of the resource
        $roles=array(
                'custodian'             => 'owner',
                'resourceProvider'      => 'distributor',
                'owner'                 => 'owner',
                'user'                  => 'distributor',
                'distributor'           => 'distributor',
                'originator'            => 'owner',
                'pointOfContact'        => 'owner',
                'principalInvestigator' => 'owner',
                'processor'             => 'owner',
                'pubisher'              => 'owner',
                'author'                => 'owner'                    
                     );
        
        
        $countries=array();

        //check the ident_contact
        $contacts=$this->get_key_value('ident_contacts');
        
        if ($contacts)
        {
            foreach($contacts as $contact)
            {
                $role=NULL;
                if (array_key_exists($contact['role'],$roles)){
                    $role=$roles[$contact['role']];
                }
                
                if ($role=='owner' && isset($contact['org_name']))  {
                    return $contact['org_name'];
                }
            }
        }
        
        $meta_contacts=$this->get_key_value('metadata_contacts');
                
        if ($meta_contacts)
        {
            foreach($meta_contacts as $contact)
            {
                if (array_key_exists($contact['role'],$roles)){
                    $role=$roles[$contact['role']];
                }
        
                if ($role=='owner' && isset($contact['org_name']))  {
                    return $contact['org_name'];
                }
            }
        }
                
        return NULL;
    }
    
    
    //normalize the structure in a tabular format
    private function normalize_distribution_info()
    {
        $distribution_info=$this->get_key_value('distribution_info');
        
        $output=array();
        $columns=array_keys($this->xpath_map['distribution_info']['items']);
        
        //there could be multiple distribution info tags
        //combine all into one array
        foreach($distribution_info as $row)
        {
            foreach($row as $key=>$value)
            {
                foreach($value as $val){
                    $output[$key][]=$val;
                }
            }
        }
        
        $distribution_info=$output;
        $output=array();
        
        //create associated array
        foreach($distribution_info as $key=>$value)
        {
            $k=0;
            foreach($value as $val)
            {
                $output[$k][$key]=$val;
                $k++;
            }
            
        }
        
        $this->xpath_map['distribution_info']['data']=$output;       
    }
    
    
   
    
    //convert keywords data into a tabular array
    private function normalize_keywords()
    {
        /*
         'ident_keywords' => array(
                'type'=>'complex',
                'is_repeated'=>true,
                'xpath'=>'//gmd:identificationInfo/gmd:MD_DataIdentification/gmd:descriptiveKeywords/gmd:MD_Keywords',
                'items'=>array(
                    'keyword'=> array(
                        'type'=>'array',
                        'xpath'=>'gmd:keyword',
                        'is_repeated'=>true,
                    ),
                    'type'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:type',
                        'is_repeated'=>false
                    ),
                    'thesaurus'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:thesaurusName/gmd:CI_Citation/gmd:title',
                        'is_repeated'=>false
                    )
                )
            ),
         */
        
        $keywords=$this->xpath_map['ident_descriptive_keywords']['data'];
                
        $output=array();        
        foreach($keywords as $row)
        {
            $keywords_arr=implode("\n",$row['keyword']);
            $keywords_arr=explode("\n",$keywords_arr);

            foreach($keywords_arr as $keyword)
            {
                $output[]=array(
                    'keyword'   =>  trim($keyword),
                    'type'      =>  isset($row['type']) ? $row['type'] : '',
                    'thesaurusName' =>  isset($row['thesaurusName']) ? $row['thesaurusName'] : ''
                );                
            }
        }

        $this->xpath_map['ident_descriptive_keywords']['data']=$output;
    }
    
    

    
    function init_xpaths()
    {
        $root_tag="//".$this->root_tag."/";

        $this->xpath_map=array(
            //identification elements
            'ident_title' => array(
                'type'=>'text',
                'is_repeated'=>false,
                'xpath'=>'//gmd:identificationInfo/gmd:MD_DataIdentification/gmd:citation/gmd:CI_Citation/gmd:title/gco:CharacterString'
            ),
            'ident_alternate_title' => array(
                'type'=>'text',
                'is_repeated'=>false,
                'xpath'=>'//gmd:identificationInfo/gmd:MD_DataIdentification/gmd:citation/gmd:CI_Citation/gmd:alternateTitle'
            ),

            'ident_description' => array(
                'type'=>'text',
                'is_repeated'=>false,
                'xpath'=>'//gmd:identificationInfo/gmd:MD_DataIdentification/gmd:abstract/gco:CharacterString'
            ),
            'ident_purpose' => array(
                'type'=>'text',
                'is_repeated'=>false,
                'xpath'=>'//gmd:identificationInfo/gmd:MD_DataIdentification/gmd:purpose'
            ),
            'ident_credit' => array(
                'type'=>'text',
                'is_repeated'=>false,
                'xpath'=>'//gmd:identificationInfo/gmd:MD_DataIdentification/gmd:credit'
            ),

            'ident_status' => array(
                'type'=>'text',
                'is_repeated'=>false,
                'xpath'=>'//gmd:identificationInfo/gmd:MD_DataIdentification/gmd:status/gmd:MD_ProgressCode/@codeListValue'
            ),

            //recheck https://project-open-data.cio.gov/v1.1/metadata-resources/
            /*'ident_modified' => array(
                'type'=>'text',
                'is_repeated'=>false,
                'xpath'=>'//gmd:identificationInfo/gmd:MD_DataIdentification/gmd:resourceMaintenance/gmd:MD_MaintenanceInformation/gmd:maintenanceAndUpdateFrequency/gmd:userDefinedMaintenanceFrequency/gts:TM_PeriodDuration'
            ),*/


            'ident_graphic_overview' =>array(
                'type'=>'complex',
                'xpath'=>'//gmd:identificationInfo/gmd:MD_DataIdentification/gmd:graphicOverview',
                'items'=>array(
                    'fileName'=>array(
                        'xpath'=>'gmd:MD_BrowseGraphic/gmd:fileName/gco:CharacterString'
                    ),
                    'fileDescription'=>array(
                        'xpath'=>'gmd:MD_BrowseGraphic/gmd:fileDescription/gco:CharacterString'
                    ),
                    'fileType'=>array(
                        'xpath'=>'gmd:MD_BrowseGraphic/gmd:fileType/gco:CharacterString'
                    ),
                    
                )
            ),

            'ident_dates' =>array(
                'type'=>'complex',
                'xpath'=>'//gmd:identificationInfo/gmd:MD_DataIdentification/gmd:citation/gmd:CI_Citation/gmd:date',
                'items'=>array(
                    'date'=>array(
                        'xpath'=>'gmd:CI_Date/gmd:date'
                    ),
                    'date_type'=>array(
                        'xpath'=>'gmd:CI_Date/gmd:dateType/gmd:CI_DateTypeCode'
                    )
                )
            ),

            'ident_descriptive_keywords' => array(
                'type'=>'complex',
                'is_repeated'=>true,
                'xpath'=>'//gmd:identificationInfo/gmd:MD_DataIdentification/gmd:descriptiveKeywords/gmd:MD_Keywords',
                'items'=>array(
                    'type'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:type/gmd:MD_KeywordTypeCode/@codeListValue',
                        'is_repeated'=>false
                    ),
                    'keyword'=> array(
                        'type'=>'array',
                        'xpath'=>'gmd:keyword',
                        'is_repeated'=>true,
                    ),                    
                    'thesaurusName'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:thesaurusName/gmd:CI_Citation/gmd:title',
                        'is_repeated'=>false
                    )
                )
            ),

            'ident_resource_constraints'  => array(
                'type'=>'complex',
                'xpath'=>'//gmd:identificationInfo/gmd:MD_DataIdentification/gmd:resourceConstraints',
                'is_repeated'=>true,
                'items'=>array(
                    'use_constraints'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:MD_LegalConstraints/gmd:useConstraints'
                    ),
                    'other_constraints'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:MD_LegalConstraints/gmd:otherConstraints'
                    )
                )
            ),
            'ident_spatial_rep_type' => array(
                'type'=>'text',
                'is_repeated'=>true,
                'xpath'=>'//gmd:identificationInfo/gmd:MD_DataIdentification/gmd:spatialRepresentationType/gmd:MD_SpatialRepresentationTypeCode/@codeListValue'
            ),

            //check:is_repeatable
            'ident_language' => array(
                'type'=>'text',
                'is_repeated'=>false,
                'xpath'=>'//gmd:identificationInfo/gmd:MD_DataIdentification/gmd:language'
            ),

            //check if this repeatable
            'topic_category' => array(
                'type'=>'text',
                'is_repeated'=>true,
                'xpath'=>'//gmd:identificationInfo/gmd:MD_DataIdentification/gmd:topicCategory'
            ),

            'ident_extent_bbox'  => array(
                'type'=>'complex',
                'xpath'=>'//gmd:identificationInfo/gmd:MD_DataIdentification/gmd:extent/gmd:EX_Extent/gmd:geographicElement/gmd:EX_GeographicBoundingBox',
                'is_repeated'=>true,
                'items'=>array(
                    'westBoundLongitude'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:westBoundLongitude'
                    ),
                    'eastBoundLongitude'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:eastBoundLongitude'
                    ),
                    'southBoundLatitude'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:southBoundLatitude'
                    ),
                    'northBoundLatitude'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:northBoundLatitude'
                    )
                )
            ),
            'ident_contacts'  => array(
                'type'=>'complex',
                'xpath'=>'//gmd:identificationInfo/gmd:MD_DataIdentification//gmd:pointOfContact/gmd:CI_ResponsibleParty',
                'is_repeated'=>true,
                'items'=>array(
                    'org_name'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:organisationName'
                    ),
                    'position'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:positionName'
                    ),
                    'phone'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:contactInfo/gmd:CI_Contact/gmd:phone/gmd:CI_Telephone/gmd:voice'
                    ),
                    'fax'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:contactInfo/gmd:CI_Contact/gmd:phone/gmd:CI_Telephone/gmd:facsimile'
                    ),
                    'email'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:contactInfo/gmd:CI_Contact//gmd:CI_Address/gmd:electronicMailAddress'
                    ),
                    'role'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:role'
                    ),
                    'country'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:contactInfo/gmd:CI_Contact//gmd:CI_Address/gmd:country'
                    ),
                )
            ),

            'ident_supplement_info' => array(
                'type'=>'text',
                'is_repeated'=>false,
                'xpath'=>'gmd:identificationInfo/gmd:MD_DataIdentification/gmd:supplementalInformation'
            ),
            'ident_identifier' => array(
                'type'=>'text',
                'is_repeated'=>false,
                'xpath'=>'gmd:identificationInfo/gmd:MD_DataIdentification/gmd:citation/gmd:CI_Citation/gmd:identifier//gmd:code'
            ),

            'metadata_file_identifier' => array(
                'type'=>'text',
                'is_repeated'=>false,
                'xpath'=>'gmd:fileIdentifier'
            ),
            'metadata_charset' => array(
                'type'=>'text',
                'is_repeated'=>false,
                'xpath'=>'gmd:characterSet'
            ),
            'metadata_lang' => array(
                'type'=>'text',
                'is_repeated'=>false,
                'xpath'=> 'gmd:language'
            ),
            'metadata_date' => array(
                'type'=>'text',
                'is_repeated'=>false,
                'xpath'=>'gmd:dateStamp'
            ),
            'metadata_standard_name' => array(
                'type'=>'text',
                'is_repeated'=>false,
                'xpath'=>'gmd:metadataStandardName'
            ),
            'metadata_standard_ver' => array(
                'type'=>'text',
                'is_repeated'=>false,
                'xpath'=>'//gmd:metadataStandardVersion'
            ),
            'metadata_contacts'  => array(
                'type'=>'complex',
                'xpath'=>$root_tag.'/gmd:contact/gmd:CI_ResponsibleParty',
                'is_repeated'=>true,
                'items'=>array(
                    'person_name'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:individualName'
                    ),
                    'organisation'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:organisationName'
                    ),
                    'position'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:positionName'
                    ),
                    'phone'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:contactInfo/gmd:CI_Contact/gmd:phone/gmd:CI_Telephone/gmd:voice'
                    ),
                    'fax'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:contactInfo/gmd:CI_Contact/gmd:phone/gmd:CI_Telephone/gmd:facsimile'
                    ),
                    'email'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:contactInfo/gmd:CI_Contact//gmd:CI_Address/gmd:electronicMailAddress'
                    ),
                    'role'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:role'
                    ),
                    'country'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:contactInfo/gmd:CI_Contact//gmd:CI_Address/gmd:country'
                    ),
                )
            ),

            'distributor_contacts' => array(
                'type'=>'complex',
                'is_repeated'=>true,
                'xpath'=>'//gmd:distributionInfo/gmd:MD_Distribution/gmd:distributor/gmd:MD_Distributor/gmd:distributorContact/gmd:CI_ResponsibleParty',
                'items'=>array(
                    'org_name'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:organisationName'
                    ),
                    'position'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:positionName'
                    ),
                    'phone'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:contactInfo/gmd:CI_Contact/gmd:phone/gmd:CI_Telephone/gmd:voice'
                    ),
                    'fax'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:contactInfo/gmd:CI_Contact/gmd:phone/gmd:CI_Telephone/gmd:facsimile'
                    ),
                    'email'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:contactInfo/gmd:CI_Contact//gmd:CI_Address/gmd:electronicMailAddress'
                    ),
                    'role'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:role'
                    ),
                    'address'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:contactInfo//gmd:address'
                    ),
                    'country'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:contactInfo//gmd:country'
                    ),
                )
            ),

            //only imports gmd:onLine/gmd:CI_OnlineResource resources
            //distributor and distribution are different
            'distributor_info'  => array(
                'type'=>'complex',
                'xpath'=>'//gmd:distributionInfo/gmd:MD_Distribution/gmd:distributor/gmd:MD_Distributor',
                'is_repeated'=>true,
                'items'=>array(
                    /*'contact'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:distributorContact'
                    ),*/
                    /*'order_process'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:distributionOrderProcess'
                    ),*/
                    'format'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:distributorFormat'
                    ),
                    'url'=> array(
                        'type'=>'array',
                        'xpath'=>'gmd:distributorTransferOptions/gmd:MD_DigitalTransferOptions/gmd:onLine/gmd:CI_OnlineResource/gmd:linkage',
                    ),
                    'protocol'=> array(
                        'type'=>'array',
                        'xpath'=>'gmd:distributorTransferOptions/gmd:MD_DigitalTransferOptions/gmd:onLine/gmd:CI_OnlineResource/gmd:protocol',
                    ),
                    'app_profile'=> array(
                        'type'=>'array',
                        'xpath'=>'gmd:distributorTransferOptions/gmd:MD_DigitalTransferOptions/gmd:onLine/gmd:CI_OnlineResource/gmd:applicationProfile',
                    ),
                    'name'=> array(
                        'type'=>'array',
                        'xpath'=>'gmd:distributorTransferOptions/gmd:MD_DigitalTransferOptions/gmd:onLine/gmd:CI_OnlineResource/gmd:name',
                    ),
                    'description'=> array(
                        'type'=>'array',
                        'xpath'=>'gmd:distributorTransferOptions/gmd:MD_DigitalTransferOptions/gmd:onLine/gmd:CI_OnlineResource/gmd:description',
                    ),
                    /*'function'=> array(
                        'type'=>'array',
                        'xpath'=>'gmd:distributorTransferOptions/gmd:MD_DigitalTransferOptions/gmd:onLine/gmd:CI_OnlineResource/gmd:function',
                    )*/
                )
            ),

            'distribution_info'  => array(
                'type'=>'complex',
                'xpath'=>'//gmd:distributionInfo/gmd:MD_Distribution',
                'is_repeated'=>true,
                'items'=>array(
                    'format'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:distributionFormat/gmd:MD_Format/gmd:name',
                        'is_repeated'=>true
                    ),
                    'url'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:transferOptions/gmd:MD_DigitalTransferOptions/gmd:onLine//gmd:linkage',
                        'is_repeated'=>true
                    ),
                    'protocol'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:transferOptions/gmd:MD_DigitalTransferOptions/gmd:onLine//gmd:protocol',
                        'is_repeated'=>true
                    ),
                    'name'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:transferOptions/gmd:MD_DigitalTransferOptions/gmd:onLine//gmd:name',
                        'is_repeated'=>true
                    ),
                    'description'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:transferOptions/gmd:MD_DigitalTransferOptions/gmd:onLine//gmd:description',
                        'is_repeated'=>true
                    ),
                    /*'function'=> array(
                        'type'=>'text',
                        'xpath'=>'gmd:transferOptions/gmd:MD_DigitalTransferOptions/gmd:onLine//gmd:function',
                        'is_repeated'=>true
                    ),*/
                )
            ),



            //document type = dataset, service, series
            'dq_scope'  => array(
                'type'=>'text',
                'xpath'=>'//gmd:dataQualityInfo/gmd:DQ_DataQuality/gmd:scope/gmd:DQ_Scope',
                'is_repeated'=>false,
            ),

            'dq_lineage'  => array(
                'type'=>'text',
                'xpath'=>'//gmd:dataQualityInfo/gmd:DQ_DataQuality/gmd:lineage/gmd:LI_Lineage//gmd:description',
                'is_repeated'=>false,
            ),

            'metadata_maintenance_freq'  => array(
                'type'=>'text',
                'xpath'=>'//gmd:metadataMaintenance//gmd:maintenanceAndUpdateFrequency',
                'is_repeated'=>false,
            ),



            //todo:

            //gmd:distributionInfo am here....<---

            /*"keyword",
            "modified",
            "publisher",
            "contact_name",
            "contact_email",
            "identifier"
            */

        );
    }


    private function xpath_query_complex(&$complex_obj)
    {
        $output=array();

        //return $this->xpath_query($complex_obj['xpath'],$complex_obj['is_repeated']);
        $result = $this->xml_obj->xpath($complex_obj['xpath']);

        foreach ($result as $row) {
            //print "<HR>";
            //print $complex_obj['xpath'] . "<BR>";
            //$value = trim(dom_import_simplexml($row)->textContent);
            //print $value;

            //echo "<HR>";
            $elements=array();
            foreach($complex_obj['items'] as $field_name=>$item)
            {
                $row->registerXPathNamespace('gmd', 'http://www.isotc211.org/2005/gmd');
                $row->registerXPathNamespace("gco","http://www.isotc211.org/2005/gco");
                $sub_result=$row->xpath($item['xpath']);
                //var_dump($sub_result);
                //echo "<HR>";
                //print $item['xpath'] . "<BR>";

                foreach($sub_result as $sub_row)
                {
                    $value = trim(dom_import_simplexml($sub_row)->textContent);
                    //print $value;

                    $elements[$field_name][]=$value;
                }

                if (!isset($item['is_repeated']))
                {
                    $item['is_repeated']=false;
                }

                //if item is not repeatable, convert to string
                if (!$item['is_repeated'] && isset($elements[$field_name])){
                    $elements[$field_name]=implode("||",$elements[$field_name]);
                }
            }
            $output[]=$elements;
        }
        return $output;
    }

    private function xpath_query($xpath,$is_repeated=false)
    {
        $result = $this->xml_obj->xpath($xpath);

        if (!$result) {
            //print "<HR>";
            //print $xpath . "<BR>";
            //echo "nothing!!!";
            return NULL;
        }

        $output = array();
        foreach ($result as $row) {
            //print "<HR>";
            //print $xpath . "<BR>";
            $value = trim(dom_import_simplexml($row)->textContent);
            //print $value;
            $output[] = $value;
        }

        //concatenate with BR non-repeating fields
        if (!$is_repeated) {
            $output=implode("||", $output);
        }
        return $output;
    }


    



    function to_json()
    {
        $json=json_encode($this->xml_obj);
        echo $json;
    }

}