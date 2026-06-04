<?php

class Editor_DDI_Writer
{
    private $data;
    private $writer;
    private $ci;
    private $sid;
    private $uid_vid_cache = null;

    /** Minimum memory_limit for DDI export when php.ini is lower (bytes). */
    private const DDI_MIN_MEMORY_BYTES = 268435456; // 256M

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->model('Editor_datafile_model');
        $this->ci->load->model("Editor_variable_model");
        $this->ci->load->library("project_json_writer");
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
        echo htmlspecialchars($this->get_el($path), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    function el_cdata($path)
    {
        echo (string)$this->get_el($path);
    }

    function escape_text($value)
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8'); 
    }

    function el_val($data, $el){
        if (isset($data[$el])){
            return htmlspecialchars($data[$el], ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
    }

    function attr_val($data, $attr)
    {
        if (isset($data[$attr])){
            return htmlspecialchars($data[$attr], ENT_QUOTES | ENT_XML1, 'UTF-8');
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
		set_time_limit(0);
        $this->ci->load->model('Editor_model');
        $this->ci->load->model("Editor_variable_model");

        $this->ensure_ddi_memory_limit();

        $dataset=$this->ci->Editor_model->get_row($id);
        $this->sid=$id;

        if ($dataset['type']!='survey' && $dataset['type']!='microdata'){
            throw new Exception('Project type is not `microdata`:: '. $id . ' - ' . $dataset['type']);
        }

        $writer = new XMLWriter;
        if (!$writer->openURI($output)) {
            throw new Exception('DDI export: cannot open output ' . $output);
        }
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
        $this->flush_xml_writer($writer);

        //study description
        $writer->writeRaw("\n");
        $writer->writeRaw($this->get_study_desc_xml($dataset));
        $this->flush_xml_writer($writer);

        //file description
        $files=$this->ci->Editor_datafile_model->select_all($id,$include_file_info=false);

        $writer->writeRaw("\n");
        
        if (!empty($files)){
            foreach($files as $file){
                //get key variables for file
                $key_vars=$this->ci->Editor_variable_model->get_key_variable_names($id,$file_id=$file['file_id'], $use_vid=true);

                $writer->writeRaw($this->get_file_desc_xml($file, $key_vars));
                $writer->writeRaw("\n");
                $this->flush_xml_writer($writer);
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

        //pre-load UID->VID mapping
        $this->uid_vid_cache = $this->ci->Editor_variable_model->uid_vid_list($id);

        //variables — stream each <var> directly to XMLWriter (no ArrayToXml per variable)
        foreach($this->ci->Editor_variable_model->chunk_reader_generator($id) as $variable){
            $variable = $this->ci->project_json_writer->transform_variable($variable);
            $this->write_var_desc_to_writer($writer, $variable['metadata']);
            $writer->writeRaw("\n");
            $this->flush_xml_writer($writer);
            unset($variable);
        }     
        
        $writer->endElement();//end-dataDscr
        $writer->endElement();//end-codebook
        $writer->endDocument();
        $this->flush_xml_writer($writer);
    }


    /**
     * Push buffered XMLWriter output to disk when writing to a file URI.
     */
    private function flush_xml_writer(XMLWriter $writer): void
    {
        if (method_exists($writer, 'flush')) {
            $writer->flush();
        }
    }


    /**
     * Raise memory_limit to at least 256M for DDI generation when php.ini is lower.
     */
    private function ensure_ddi_memory_limit(): void
    {
        $current = ini_get('memory_limit');
        if ($current === false || $current === '') {
            ini_set('memory_limit', '256M');
            return;
        }

        if ($this->parse_memory_limit_bytes($current) < self::DDI_MIN_MEMORY_BYTES) {
            ini_set('memory_limit', '256M');
        }
    }


    /**
     * @param string|int $limit PHP memory_limit value (e.g. 128M, -1)
     */
    private function parse_memory_limit_bytes($limit): int
    {
        if ($limit === -1 || $limit === '-1') {
            return PHP_INT_MAX;
        }

        $limit = trim((string)$limit);
        if ($limit === '') {
            return 0;
        }

        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int)$limit;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }


    private function var_format_array(array $var): array
    {
        if (isset($var['var_format']) && is_array($var['var_format'])) {
            return $var['var_format'];
        }
        return array(
            'value' => $var['var_format.value'] ?? null,
            'type' => $var['var_format.type'] ?? null,
            'name' => $var['var_format.name'] ?? null,
        );
    }


    private function write_xml_text_element(XMLWriter $writer, string $name, $value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $writer->startElement($name);
        $writer->text((string)$value);
        $writer->endElement();
    }


    private function write_xml_attribute_element(XMLWriter $writer, string $name, array $attributes, $text_value = null): void
    {
        $has_attr = false;
        foreach ($attributes as $attr_val) {
            if ($attr_val !== null && $attr_val !== '') {
                $has_attr = true;
                break;
            }
        }
        if (!$has_attr && ($text_value === null || $text_value === '')) {
            return;
        }
        $writer->startElement($name);
        foreach ($attributes as $attr_name => $attr_val) {
            if ($attr_val !== null && $attr_val !== '') {
                $writer->writeAttribute($attr_name, (string)$attr_val);
            }
        }
        if ($text_value !== null && $text_value !== '') {
            $writer->text((string)$text_value);
        }
        $writer->endElement();
    }


    /**
     * Write a single <var> element directly to XMLWriter (memory-efficient vs ArrayToXml).
     */
    function write_var_desc_to_writer(XMLWriter $writer, array $var): void
    {
        $writer->startElement('var');

        $attrs = array(
            'ID' => $var['vid'] ?? '',
            'name' => $var['name'] ?? '',
            'files' => $var['fid'] ?? '',
            'dcml' => $var['var_dcml'] ?? '',
            'intrvl' => $var['var_intrvl'] ?? '',
            'wgt' => $this->get_is_var_wgt($var),
            'wgt-var' => $this->get_var_wgt($var),
        );
        foreach ($attrs as $attr_name => $attr_val) {
            if ($attr_val !== null && $attr_val !== '') {
                $writer->writeAttribute($attr_name, (string)$attr_val);
            }
        }

        $vf = $this->var_format_array($var);
        $this->write_xml_attribute_element(
            $writer,
            'varFormat',
            array(
                'type' => $vf['type'] ?? '',
                'formatname' => $vf['name'] ?? '',
            ),
            $vf['value'] ?? ''
        );

        $this->write_xml_attribute_element(
            $writer,
            'location',
            array(
                'StartPos' => $var['loc_start_pos'] ?? '',
                'EndPos' => $var['loc_end_pos'] ?? '',
                'width' => $var['loc_width'] ?? '',
                'RecSegNo' => $var['loc_rec_seg_no'] ?? '',
            )
        );

        $this->write_xml_text_element($writer, 'labl', $var['labl'] ?? '');
        $this->write_xml_text_element($writer, 'imputation', $var['var_imputation'] ?? '');
        $this->write_xml_text_element($writer, 'security', $var['var_security'] ?? '');
        $this->write_xml_text_element($writer, 'respUnit', $var['var_respunit'] ?? '');

        $qstn_fields = array(
            'preQTxt' => $var['var_qstn_preqtxt'] ?? '',
            'qstnLit' => $var['var_qstn_qstnlit'] ?? '',
            'postQTxt' => $var['var_qstn_postqtxt'] ?? '',
            'ivuInstr' => $var['var_qstn_ivulnstr'] ?? '',
        );
        $has_qstn = false;
        foreach ($qstn_fields as $qv) {
            if ($qv !== null && $qv !== '') {
                $has_qstn = true;
                break;
            }
        }
        if ($has_qstn) {
            $writer->startElement('qstn');
            foreach ($qstn_fields as $el => $qv) {
                $this->write_xml_text_element($writer, $el, $qv);
            }
            $writer->endElement();
        }

        $this->write_xml_text_element($writer, 'universe', $var['var_universe'] ?? '');

        if (isset($var['var_sumstat']) && is_array($var['var_sumstat'])) {
            foreach ($var['var_sumstat'] as $sumstat) {
                if (!is_array($sumstat)) {
                    continue;
                }
                $value = $sumstat['value'] ?? null;
                if ($value === null || $value === '' || $value === 'None') {
                    continue;
                }
                $this->write_xml_attribute_element(
                    $writer,
                    'sumStat',
                    array(
                        'type' => $sumstat['type'] ?? '',
                        'wgtd' => $sumstat['wgtd'] ?? '',
                    ),
                    (string)$value
                );
            }
        }

        if (isset($var['var_catgry']) && is_array($var['var_catgry']) && count($var['var_catgry']) > 0) {
            $missing_values = array();
            if (isset($var['var_invalrng']['values']) && is_array($var['var_invalrng']['values'])) {
                $missing_values = array_map('strval', $var['var_invalrng']['values']);
            }

            foreach ($var['var_catgry'] as $cat) {
                if (!is_array($cat)) {
                    continue;
                }
                $cat_value = isset($cat['value']) ? (string)$cat['value'] : '';
                $is_missing = $cat_value !== '' && !empty($missing_values)
                    && in_array($cat_value, $missing_values, true);

                $writer->startElement('catgry');
                if ($is_missing) {
                    $writer->writeAttribute('missing', 'Y');
                }
                $this->write_xml_text_element($writer, 'catValu', $cat['value'] ?? '');
                $this->write_xml_text_element($writer, 'labl', $cat['labl'] ?? '');

                if (isset($cat['stats']) && is_array($cat['stats'])) {
                    foreach ($cat['stats'] as $stat) {
                        if (!is_array($stat)) {
                            continue;
                        }
                        $stat_value = $stat['value'] ?? null;
                        if ($stat_value === null || $stat_value === '' || $stat_value === 'None') {
                            continue;
                        }
                        $this->write_xml_attribute_element(
                            $writer,
                            'catStat',
                            array(
                                'type' => $stat['type'] ?? '',
                                'wgtd' => $stat['wgtd'] ?? '',
                            ),
                            (string)$stat_value
                        );
                    }
                }
                $writer->endElement();
            }
        }

        $this->write_xml_text_element($writer, 'notes', $var['var_notes'] ?? '');
        $this->write_xml_text_element($writer, 'txt', $var['var_txt'] ?? '');
        $this->write_xml_text_element($writer, 'codInstr', $var['var_codinstr'] ?? '');

        $std = $this->transform_std_catgry($var['var_std_catgry'] ?? null);
        if (is_array($std)) {
            $writer->startElement('stdCatgry');
            foreach ($std as $key => $val) {
                if (is_scalar($val) && $val !== '') {
                    $this->write_xml_text_element($writer, (string)$key, $val);
                }
            }
            $writer->endElement();
        }

        if (isset($var['var_concept']) && is_scalar($var['var_concept']) && $var['var_concept'] !== '') {
            $this->write_xml_text_element($writer, 'concept', $var['var_concept']);
        }

        $writer->endElement();
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

        return $result->saveXML($result->documentElement);
    }


    /**
     * 
     * Remove empty nodes from XML document
     * 
     * @param \DOMDocument $xmlDoc
     * @return \DOMDocument
     */
    function remove_empty_xml($xmlDoc)
    {        
        $xmlDoc->preserveWhiteSpace = false;
        $xpath = new DOMXPath($xmlDoc);

        // Remove empty attributes first
        $nodesWithAttributes = $xpath->query('//*[@*]');
        foreach ($nodesWithAttributes as $node) {
            $attributesToRemove = [];
            foreach ($node->attributes as $attr) {
                if (trim($attr->value) === '') {
                    $attributesToRemove[] = $attr;
                }
            }
            foreach ($attributesToRemove as $attr) {
                $node->removeAttributeNode($attr);
            }
        }

        // Find elements with no child nodes and no attributes
        while (($emptyNodes = $xpath->query('//*[not(node()) and not(@*)]')) && ($emptyNodes->length)) {
            foreach($emptyNodes as $node) {
                $node->parentNode->removeChild($node);
            }
        }

        return $xmlDoc;
    }


    /**
     * 
     * Recursively remove empty nodes from DOM
     * 
     * @param \DOMNode $node
     */
    function remove_empty_nodes(\DOMNode $node): void
    {
        // check if node has attributes
        if ($node->hasAttributes()) {
            foreach (iterator_to_array($node->childNodes) as $child) {
                $this->remove_empty_nodes($child);
            }
            return;
        }

        // node with no attributes and no children
        if (!$node->hasChildNodes() && trim($node->nodeValue) === '') {
            $node->parentNode->removeChild($node);
            return;
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            $this->remove_empty_nodes($child);
        }

        // remove empty nodes with no attributes and no children
        if (!$node->hasChildNodes() && trim($node->nodeValue) === '' && !$node->hasAttributes()) {
            $node->parentNode->removeChild($node);
        }
    }

    function get_study_desc_xml($data)
    {
        $stdy_desc=new DOMDocument();
        $this->set_data($data['metadata']);
        $xml_str=$this->ci->load->view('editor_ddi/ddi25_stdy_dscr',array('survey'=>$data), true);
        $xml_str=str_replace("\t","",$xml_str);

        //remove empty <![CDATA[]]>
        $xml_str=preg_replace('/<!\[CDATA\[\s*\]\]>/','',$xml_str);
        
        $stdy_desc->preserveWhiteSpace = false;
        $stdy_desc->formatOutput = true;
        $stdy_desc->loadXML($xml_str);
        
        //remove empty elements
        $stdy_desc=$this->remove_empty_xml($stdy_desc);

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

        $this->remove_empty_nodes($result->documentElement);
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
        
        // If var_wgt_id already starts with 'V', it's already a VID - return as is
        if (strtolower(substr($var['var_wgt_id'],0,1))=='v'){
            return $var['var_wgt_id'];
        }
        
        // var_wgt_id is a UID - convert to VID
        if($this->uid_vid_cache !== null && isset($this->uid_vid_cache[$var['var_wgt_id']])){
            return $this->uid_vid_cache[$var['var_wgt_id']];
        }
        
        //fallback
        $result=$this->ci->Editor_variable_model->vid_by_uid($this->sid,$var['var_wgt_id']);
        if ($result){
            return $result;
        }

        return '';
    }

    

    function get_var_desc_xml($data)
    {
        $mem = new XMLWriter();
        $mem->openMemory();
        $this->write_var_desc_to_writer($mem, is_array($data) ? $data : array());
        return $mem->outputMemory();
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