<?php

class Editor_DDI_Writer
{
    private $data;
    private $writer;
    private $ci;
    private $sid;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->model('Editor_datafile_model');
        $this->ci->load->model("Editor_variable_model");
    }


    /**
     * 
     * Create xml tag and return as string
     * 
     * @attributes - array of attributes ['name'=>'value']
     */
    function create_xml_tag($tag_name, $data, $attributes=array(), $cdata=false)
    {
        if (empty($data)){
            return false;
        }

        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString(' ');

        $writer->startElement($tag_name);
        if (!empty($attributes)){
            foreach($attributes as $attribute=>$att_value){
                $writer->writeAttribute($attribute, $att_value);
            }
        }

        if ($cdata){
            $writer->writeCData($data);
        }else{
            $writer->text($data);
        }        
        $writer->endElement();
        return $writer->outputMemory();        
    }

    function print_xml_tag($tag_name, $data, $attributes=array(), $cdata=false)
    {
        $result= $this->create_xml_tag($tag_name, $data, $attributes, $cdata);

        if ($result){
            echo (string)$result;
        }
    }
    

    function set_data($data)
    {
        $this->data=$data;
    }

    function get_el($path)
    {
        return $this->array_get_by_key($this->data,$path);
    }

    function el($path)
    {
        echo htmlspecialchars($this->get_el($path));
    }

    function escape_text($value)
    {
        return htmlspecialchars($value); 
    }

    function el_val($data, $el){
        if (isset($data[$el])){
            return htmlspecialchars($data[$el]);
        }
    }

    function attr_val($data, $attr)
    {
        if (isset($data[$attr])){
            return htmlspecialchars($data[$attr]);
        }
    }

    function attr_value()
    {
        echo $this->attr_value();
    }

    function xpath_val(&$arr, $xpath)
    {
        return $this->array_get_by_key($arr,$xpath);
    }

    function array_get_by_key(&$array, $xpath) 
    {
        $paths=explode("/",$xpath);        
        $result=null;
        
        foreach($paths as $path)
        {            
            if(!$result){
                if(empty($array[$path])){
                    return NULL;
                }
                $result=$array[$path];
            }
            else{
                if(empty($result[$path])){
                    return NULL;
                }
                $result=$result[$path];
            }
        }

        return $result;    
    }



    function write_element($element_name, $value, $attributes=array())
    {
        $this->writer->startElement($element_name);
        if (!empty($attributes)){
            foreach($attributes as $attribute=>$att_value){
                $this->writer->startAttribute($attribute);
                    $this->writer->text($att_value);
                $this->writer->endAttribute();
            }
        }
        $this->writer->text($value);
        $this->writer->endElement();
    }



    /**
     * 
     * 
     * Generate DDI for Survey
     * 
     * @idno - study IDNO
     * @output - 'php://output' or file path
     * 
     * */
	function generate_ddi($id=null, $output='php://output')
	{
        $this->ci->load->model('Editor_model');
        $this->ci->load->model("Editor_variable_model");
        //$this->ci->load->model("Variable_group_model");        

        $dataset=$this->ci->Editor_model->get_row($id);
        $this->sid=$id;

        if (!$dataset['type']=='survey'){
            throw new Exception('Project type is not `survey`:: '. $id . ' - ' . $dataset['type']);
        }

        $writer = new XMLWriter;
        $writer->openURI($output);
        $writer->startDocument('1.0', 'UTF-8');

        //codeBook start
        $writer->startElement('codeBook');
        $writer->writeAttribute('version','2.5');
        $writer->writeAttribute('ID',$dataset['study_idno']  ? $dataset['study_idno'] : $dataset['idno']);
        $writer->writeAttribute('xml-lang','en');
        $writer->writeAttribute('xmlns','ddi:codebook:2_5');
        $writer->writeAttribute('xmlns:xsi','http://www.w3.org/2001/XMLSchema-instance');
        $writer->writeAttribute('xsi:schemaLocation','ddi:codebook:2_5 http://www.ddialliance.org/Specification/DDI-Codebook/2.5/XMLSchema/codebook.xsd');
        
        //document description
        $writer->writeRaw("\n");
        $writer->writeRaw($this->get_doc_desc_xml($dataset['metadata']));

        //study description
        $writer->writeRaw("\n");
        $writer->writeRaw($this->get_study_desc_xml($dataset));

        //file description
        $files=$this->ci->Editor_datafile_model->select_all($id,$include_file_info=false);

        $writer->writeRaw("\n");
        
        if (!empty($files)){
            foreach($files as $file){
                //get key variables for file
                $key_vars=$this->ci->Editor_variable_model->get_key_variable_names($id,$file_id=$file['file_id'], $use_vid=true);

                $writer->writeRaw($this->get_file_desc_xml($file, $key_vars));
                $writer->writeRaw("\n");
            }
        }

        //dataDscr
        $writer->startElement('dataDscr');
        $writer->writeRaw("\n");

        /* //todo
        //variable groups
        $var_groups=$this->ci->Variable_group_model->select_all($id);
        foreach($var_groups as $var_group){
            $writer->writeRaw($this->get_vargroup_desc_xml($var_group));
            $writer->writeRaw("\n");
        }
        */

        //variables
        foreach($this->ci->Editor_variable_model->chunk_reader_generator($id) as $variable){
            $writer->writeRaw($this->get_var_desc_xml($variable['metadata']));
            $writer->writeRaw("\n");
        }     
        
        $writer->endElement();//end-dataDscr
        $writer->endElement();//end-codebook
        $writer->endDocument();        
    }


    function get_doc_desc_xml($data)
    {
        $dataset_metadata = new \Adbar\Dot($data);
        $doc_desc = new \Adbar\Dot();

        //document description
        $doc_desc->set([
            'citation.titlStmt.IDNo'=>$dataset_metadata['doc_desc.idno'],
            'citation.titlStmt.titl'=>$dataset_metadata['doc_desc.title'],
            'citation.prodStmt.producer'=>'',
            'citation.prodStmt.prodDate._value'=>$dataset_metadata['doc_desc.prod_date'],
            'citation.prodStmt.prodDate._attributes'=>['date' => $dataset_metadata['doc_desc.prod_date']],
            'citation.prodStmt.software._attributes'=>['version'=>'beta'],
            'citation.prodStmt.software._value'=>'MetadataEditor',
            //version
            'citation.verStmt.version._value'=> $dataset_metadata['doc_desc.version_statement.version'],
            'citation.verStmt.version._attributes'=>[
                'date'=>$dataset_metadata['doc_desc.version_statement.version_date']                
            ],
            'citation.verStmt.notes'=>$dataset_metadata['doc_desc.version_statement.version_notes'],
            'citation.verStmt.verResp'=>$dataset_metadata['doc_desc.version_statement.version_resp']
        ]);
        
        //doc_desc/producers
        $producers=new \Adbar\Dot($dataset_metadata->get('doc_desc.producers'));
        foreach($producers->all() as $idx=>$producer){
            $doc_desc->set([
                'citation.prodStmt.producer.'.$idx.'._value'=>$producers["{$idx}.name"],
                'citation.prodStmt.producer.'.$idx.'._attributes'=>[
                    'abbr'=>$producers["{$idx}.abbr"],
                    'affiliation'=>$producers["{$idx}.affiliation"],
                    'role'=>$producers["{$idx}.role"],
                ],
            ]);
        }

        //remove nulls
        $doc_desc = $this->remove_empty($doc_desc->all());

        $result = new Spatie\ArrayToXml\ArrayToXml($doc_desc,'docDscr');
        $result=$result->prettify()->toDom();
        //$result->formatOutput = true;
        //$result=$this->remove_empty_xml($result);
        return $result->saveXML($result->documentElement);
    }

    function remove_empty_xml($xmlDoc)
    {        
        $xmlDoc->preserveWhiteSpace = false;        
        $xpath = new DOMXPath($xmlDoc);

        while (($notNodes = $xpath->query('//*[not(node())]')) && ($notNodes->length)) {
            foreach($notNodes as $node) {
                $node->parentNode->removeChild($node);
            }
        }

        return $xmlDoc;

        //$doc->formatOutput = true;
        return $xmlDoc->saveXML();
    }

    function get_study_desc_xml($data)
    {
        $stdy_desc=new DOMDocument();
        $this->set_data($data['metadata']);
        $xml_str=$this->ci->load->view('editor_ddi/ddi25_stdy_dscr',array('survey'=>$data), true);
        $xml_str=str_replace("\t","",$xml_str);
        
        $stdy_desc->preserveWhiteSpace = false;
        $stdy_desc->formatOutput = true;
        $stdy_desc->loadXML($xml_str);        
        return $stdy_desc->saveXML($stdy_desc->documentElement);
    }

    
    function get_file_desc_xml($data, $key_vars=null)
    {
        $file = new \Adbar\Dot($data);
        $output = new \Adbar\Dot();

        /*
        <fileStrc type="relational">
            <recGrp keyvar="V2 V3"/>
        </fileStrc>
        */
        

        //document description
        $output->set([
            '_attributes'=>['ID'=>$file['file_id']],
            'fileTxt.fileName'=>$file['file_name'],
        ]);

        if ($key_vars){
            $output->set([
                'fileTxt.fileStrc._attributes'=>[
                    'type'=>'relational'
                ],
                'fileTxt.fileStrc.recGrp._attributes'=>[
                    'keyvar'=> implode(" ", $key_vars)
                ]
            ]);
        }

        $output->set([
            'fileTxt.fileCont'=>$file['description'],
            'fileTxt.dimensns.caseQnty'=>$file['case_count'],
            'fileTxt.dimensns.varQnty'=>$file['var_count'],            
            'fileTxt.dataChck'=>$file['data_checks'],
            'fileTxt.dataMsng'=>$file['missing_data'],
            'fileTxt.verStmt.version'=>$file['version'],
            'notes'=>$file['notes']
        ]);

        
        
        $result = new Spatie\ArrayToXml\ArrayToXml($output->all(),'fileDscr');
        $result=$result->prettify()->toDom();
        //$result->formatOutput = true;
        return ($result->saveXML($result->documentElement));
    }


    /**
     * 
     * Remove empty array values
     */
    function remove_empty($arr)
    {
        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                $arr[$key] = $this->remove_empty($arr[$key]);
            }
    
            if (empty($arr[$key])) {
                unset($arr[$key]);
            }
        }
    
        return $arr;
    }

    function get_vargroup_desc_xml($data)
    {
        $vargrp = new \Adbar\Dot($data);
        $output = new \Adbar\Dot();

        $output->set([
            '_attributes'=>[
                'ID'=>$vargrp['vgid'],
                'type'=>$vargrp['group_type'],
                'var'=>$vargrp['variables'],
            ],
            'labl'=>$vargrp['label'],
            'txt'=>$vargrp['txt'],
            //'concept'=>$vargrp['concept'],//repeated - not supported
            'defntn'=>$vargrp['definition'],
            'universe'=>$vargrp['universe'],
            'notes'=>$vargrp['notes']            
        ]);
        
        $output = $this->remove_empty($output->all());
        $result = new Spatie\ArrayToXml\ArrayToXml($output,'varGrp');
        $result=$result->prettify()->toDom();
        return ($result->saveXML($result->documentElement));
    }

    //is weight variable?
    function get_is_var_wgt($var){

        if (isset($var['var_wgt']) && (int)$var['var_wgt']==1){
            return 'wgt';
        }
        return '';
    }

    //has weight applied
    function get_var_wgt($var){

        if (!isset($var['var_wgt_id']) ){
            return '';
        }
        
        if (strtolower(substr($var['var_wgt_id'],0,1))=='v'){
            return $var['var_wgt_id'];
        }else{
            $result=$this->ci->Editor_variable_model->vid_by_uid($this->sid,$var['var_wgt_id']);

            if ($result){
                return $result;
            }
        }

        return '';
    }

    

    function get_var_desc_xml($data)
    {
        $var = new \Adbar\Dot($data);
        $output = new \Adbar\Dot();

        //variable description
        $output->set([
            '_attributes'=>[
                'ID'=>$var['vid'],
                'name'=>$var['name'],
                'files'=>$var['fid'],
                'dcml'=>$var['var_dcml'],
                'intrvl'=>$var['var_intrvl'],
                'wgt'=> $this->get_is_var_wgt($var),
                'wgt-var'=>$this->get_var_wgt($var),
            ],

            'varFormat'=>[
                '_value'=> (string)$var['var_format.value'],
                '_attributes'=>[
                    'type'=>$var['var_format.type'],
                    //'schema'=>$var['var_format.schema'],//not supported
                    'formatname'=>$var['var_format.name']
                ]
            ],

            'location'=>[
                '_attributes'=>[
                    'StartPos'=>$var['loc_start_pos'],
                    'EndPos'=>$var['loc_end_pos'],
                    'width'=>$var['loc_width'],
                    'RecSegNo'=>$var['loc_rec_seg_no'],
                ]
            ],

            'labl'=>$var['labl'],
            'imputation'=>$var['var_imputation'],
            'security'=>$var['var_security'],
            'respUnit'=>$var['var_respunit'],            
            'qstn.preQTxt'=>$var['var_qstn_preqtxt'],
            'qstn.qstnLit'=>$var['var_qstn_qstnlit'],
            'qstn.postQTxt'=>$var['var_qstn_postqtxt'],
            'qstn.ivuInstr'=>$var['var_qstn_ivulnstr'],

            //'valrng'=>$var[''],//repeatable field - not supported
            'universe'=>$var['var_universe'],
            'sumStat'=> [], //repeatable
            
            'catgry'=>[],
            'notes'=>$var['var_notes'],
            'txt'=>$var['var_txt'],
            'stdCatgry'=>$this->transform_std_catgry($var['var_std_catgry']),
            'codInstr'=>$var['var_codinstr'],
            'concept'=>$var['var_concept']            
        ]);


        //sumstats
        $sumstats=new \Adbar\Dot($var->get('var_sumstat'));
        foreach($sumstats->all() as $idx=>$sumstat){
            $output->set([
                'sumStat.'.$idx.'._value'=>(string)$sumstats["{$idx}.value"],
                'sumStat.'.$idx.'._attributes'=>[
                    'type'=>$sumstats["{$idx}.type"],
                    'wgtd'=>$sumstats["{$idx}.wgtd"]
                ],
            ]);
        }

        //catgry
        $categories=new \Adbar\Dot($var->get('var_catgry'));
        $categories_value_labels=$this->get_var_categories_value_labels_indexed($var->get('var_catgry_labels'));

        foreach($categories->all() as $idx=>$cat){
            $output->set([
                'catgry.'.$idx=>[                    
                    '_attributes'=>[
                        'missing'=> isset($categories["{$idx}.is_missing"]) && intval($categories["{$idx}.is_missing"])==1 ? 'Y' : ''
                    ]
                ],
                'catgry.'.$idx.'.catValu'=> $categories["{$idx}.value"],
                'catgry.'.$idx.'.labl'=> isset($categories_value_labels->{$categories["{$idx}.value"]}) ? $categories_value_labels->{$categories["{$idx}.value"]} : '',
                'catgry.'.$idx.'.catStat'=>[                    
                    '_attributes'=>[
                        'type'=>$sumstats["{$idx}.type"],
                        'wgtd'=>$sumstats["{$idx}.wgtd"],
                    ],
                    '_value'=> (string)$categories["{$idx}.stats.value"]
                ]
            ]);
        }
        
        $output = $this->remove_empty($output->all());
        $result = new Spatie\ArrayToXml\ArrayToXml($output,'var');
        $result=$result->prettify()->toDom();
        //$result->formatOutput = true;
        return ($result->saveXML($result->documentElement));
    }

    function get_var_categories_value_labels_indexed($var_catgry_labels)
    {
        $result=new stdClass();
        foreach($var_catgry_labels as $label){
            if (!isset($label['value'])){
                continue;
            }
            $result->{$label['value']}=isset($label['labl']) ? $label['labl'] : '';
        }        
        return $result;        
    }

    /**
     * 
     * Variable stdCatgry
     * 
     * If array, use the first row only and convert to object
     * 
     * @std_catgry - array or object
     * 
     *  
     * 
     */
    function transform_std_catgry($std_catgry)
    {
        if (is_array($std_catgry) && count($std_catgry)>0){
            $std_catgry=$std_catgry[0];
            return $std_catgry;
        }

        return $std_catgry;
    }
}