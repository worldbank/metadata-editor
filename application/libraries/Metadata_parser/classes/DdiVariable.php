<?php

class DdiVariable
{

    private $namespaces = array();
    private $metadata=array();
    private $variable=array();


    public function __construct($xmlObj)
    {   
        require_once dirname(__FILE__).'/JsonSerializer.php';
        $this->variable= $this->tranform_ddi_variable($xmlObj);
    }


    function variable_xml_to_array(&$xml_obj)
    {
        $xml = new JsonSerializer($xml_obj->asXML());
        $xml_json=json_encode($xml,JSON_PRETTY_PRINT);
        return json_decode($xml_json,true);
    }


    function get_element_value($path,$metadata=NULL)
    {
        if(!$metadata){
            $metadata=$this->variable_metadata;
        }

        $element=NULL;
        $output=NULL;

        if(isset($metadata[$path])){
            $element=$metadata[$path];
        }
        
        //attributes/non-array
        if(!empty($element) && !is_array($element)){
            return $element;
        }

        //array type        
        if(!empty($element) && count($element)>1){
            $output=[];
            foreach($element as $idx=>$item){
                $output[]=$this->get_element_text($item);
            }
        }
        else if(!empty($element) && count($element)==1){            
            if(isset($element[0])){
                $output=$this->get_element_text($element[0]);
            }
        }

        return $output;
    }

    function get_element_text($element)
    {
        if (isset($element['_text'])){
            return $element['_text'];
        }
    }


    /**
     * 
     *  works on a non-repeated element only
     * 
     */
    function get_simple_element($name,$metadata=NULL)
    {
        if(!$metadata){
            $metadata=$this->variable_metadata;
        }

        if(isset($metadata[$name])){
            if(isset($metadata[$name][0])){
                return $metadata[$name][0];
            }
        }
    }


    function get_repeatable_element($name,$metadata=NULL)
    {
        if(!$metadata){
            $metadata=$this->variable_metadata;
        }

        if(isset($metadata[$name])){            
            return $metadata[$name];
        }
    }


    function get_attribute_value($element,$att_name)
    {
        if(!empty($element) && isset($element['@attr'][$att_name])){
            return $element['@attr'][$att_name];
        }
    }


    /**
     * 
     * Convert to JSON schema format
     **/ 
    private function tranform_ddi_variable(&$xml_obj)
    {
        $var_array=$this->variable_xml_to_array($xml_obj);
        $var_array=$var_array['var'];
        $this->variable_metadata=$var_array;

        $output=array(
            "file_id"=> $this->get_attribute_value($var_array,'files'),
            "vid"=> $this->get_attribute_value($var_array,'ID'),
            "name"=> $this->get_attribute_value($var_array,'name'),
            "var_intrvl"=> $this->get_attribute_value($var_array,'intrvl'),
            "var_dcml"=> $this->get_attribute_value($var_array,'dcml'),
            "var_wgt"=> $this->get_attribute_value($var_array,'wgt-var'),
            "var_is_wgt"=> $this->get_attribute_value($var_array,'wgt')
        );

        //location
        $location=$this->get_simple_element('location');
        $output["loc_start_pos"] = $this->get_attribute_value($location,'StartPos');
        $output["loc_end_pos"] = $this->get_attribute_value($location,'EndPos');
        $output["loc_width"] = $this->get_attribute_value($location,'width');
        $output["loc_rec_seg_no"] = $this->get_attribute_value($location,'RecSegNo');

        $output["labl"] = $this->get_element_value('labl');
        $output["var_imputation"] = $this->get_element_value('imputation');
        $output["var_security"] = $this->get_element_value('security');
        $output["var_resp_unit"] = $this->get_element_value('respUnit');
        $output["var_analysis_unit"] = $this->get_element_value('anlysUnit');

        //question
        $question=$this->get_simple_element('qstn');
        $output["var_qstn_preqtxt"] = $this->get_element_value('preQTxt',$question);
        $output["var_qstn_qstnlit"] = $this->get_element_value('qstnLit',$question);
        $output["var_qstn_postqtxt"] = $this->get_element_value('postQTxt',$question);
        $output["var_qstn_ivuinstr"] = $this->get_element_value('ivuInstr',$question);

        $output["var_universe"] = $this->get_element_value('universe');
        $output["var_universe_clusion"] = $this->get_attribute_value($this->get_simple_element('universe'),'clusion');

        //sumStat
        $sum_stats=[];
        $sum_stats_list=(array)$this->get_repeatable_element('sumStat');
        foreach($sum_stats_list as $idx=>$item){
            $sum_stats[]=array(
                'value'=>$this->get_element_text($item),
                'type'=>$this->get_attribute_value($item,'type'),                
                'wgtd'=>$this->get_attribute_value($item,'wgtd')
            );
        }
        $output["var_sumstat"]=$sum_stats;
        $output["var_txt"] = $this->get_element_value('txt');

        //Category repeated field
        $categories=[];
        $category_list=(array)$this->get_repeatable_element('catgry');
        foreach($category_list as $idx=>$item){
            $category_stats=array();

            if(isset($item['catStat'])){
                foreach($item['catStat'] as $cat_stat){
                    $category_stats[]=array(
                        'value'=>$this->get_element_text($cat_stat),
                        'type'=>$this->get_attribute_value($cat_stat,'type'),
                        'wgtd'=>$this->get_attribute_value($cat_stat,'wgtd'),
                    );
                }
            }

            $categories[]=array(
                'value'=>$this->get_element_text($this->get_simple_element('catValu',$item)),
                'labl'=>$this->get_element_text($this->get_simple_element('labl',$item)),
                'is_missing'=>$this->get_attribute_value($item,'missing'),
                'stats'=>$category_stats
            );
        }
        $output['var_catgry']=$categories;

        $output["var_codinstr"] = $this->get_element_value('codInstr');
        
        $concepts=[];
        $concept_list=(array)$this->get_repeatable_element('concept');        
        foreach($concept_list as $idx=>$item){
            $concepts[]=array(
                'title'=>$this->get_element_text($item),
                'vocab'=>$this->get_attribute_value($item,'vocab'),
                'uri'=>$this->get_attribute_value($item,'vocabURI'),
            );
        }
        $output["var_concept"] = $concepts;
        
        //format
        $var_format=$this->get_simple_element('varFormat');
        $output['var_format']=array(
            "type" => $this->get_attribute_value($var_format,'type'),
            "schema" => $this->get_attribute_value($var_format,'schema'),
            "category" => $this->get_attribute_value($var_format,'category'),
            "name" => $this->get_attribute_value($var_format,'formatname')
        );

        $output["var_notes"] = $this->get_element_value('notes');

        //range
        $range=$this->get_simple_element('range',$this->get_simple_element('valrng'));
        $output['var_valrng']=array(
            "range"=>array(
                'min'=>$this->get_attribute_value($range,'min'),
                'max'=>$this->get_attribute_value($range,'max')
            )
        );                
        return $output;
    }


    public function get_id(){
        return $this->get_key('vid');
    }

    public function get_file_id(){
        return $this->get_key('file_id');
    }

    public function get_name(){
        return $this->get_key('name');
    }

    public function get_label(){
        return $this->get_key('labl');
    }

    public function get_question(){
        return $this->get_key('var_qstn_qstnlit');
    }

    public function get_categories(){
        return $this->get_key('var_catgry');
    }

    public function get_notes(){
        return $this->get_key('var_notes');
    }

    public function get_txt(){
        return $this->get_key('var_txt');
    }
    

    public function get_categories_str()
    {
        $categories=$this->get_categories();

        if(!is_array($categories)){
            return null;
        }

        if (empty($categories)){
            return null;
        }

        $categories_labl=array_column($categories,"labl");
        $categories=array_merge($categories_labl,array_column($categories,"label"));
        $categories=array_unique(explode(" ",implode(" ",$categories)));

        return implode(" ",$categories);
    }

    public function get_metadata_array(){
        return $this->variable;
    }


    //find an item by their key
    public function get_key($key)
    {
        if (array_key_exists($key,$this->variable))
        {
            return $this->variable[$key];
        }

        return false;
    }

}