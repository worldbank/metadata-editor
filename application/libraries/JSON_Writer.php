<?php

class JSON_Writer
{
    private $data;
    private $writer;
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }


    
    /**
     * 
     * 
     * Generate JSON for a study
     * 
     * @idno - study IDNO
     * @output - 'php://output' or file path
     * 
     * */
	function write_json($sid=null, $output='php://output')
	{
        $this->ci->load->model('Data_file_model');
        $this->ci->load->model("Variable_model");
        $this->ci->load->model("Variable_group_model");        

        if(!$sid){
            throw new Exception('STUDY NOT FOUND');
        }

        $dataset=$this->ci->Dataset_model->get_row_detailed($sid);

        if (!$dataset['type']=='survey'){
            throw new Exception('Dataaset type is not `survey`:: '. $sid . ' - ' . $dataset['type']);
        }

        //codeBook start
        
        //document description

        //study description

        //file description
        $files=$this->ci->Data_file_model->get_all_by_survey($sid);
        $writer->writeRaw("\n");
        
        foreach($files as $file){
            $writer->writeRaw($this->get_file_desc_xml($file));
            $writer->writeRaw("\n");
        }

        //dataDscr
        $writer->startElement('dataDscr');
        $writer->writeRaw("\n");

        //variable groups
        $var_groups=$this->ci->Variable_group_model->select_all($sid);
        foreach($var_groups as $var_group){
            $writer->writeRaw($this->get_vargroup_desc_xml($var_group));
            $writer->writeRaw("\n");
        }

        //variables
        foreach($this->ci->Variable_model->chunk_reader_generator($sid) as $variable){
            $writer->writeRaw($this->get_var_desc_xml($variable['metadata']));
            $writer->writeRaw("\n");
        }        

    }


    
    
    function get_file_desc_xml($data)
    {
        $file = new \Adbar\Dot($data);
        $output = new \Adbar\Dot();

        //document description
        $output->set([
            '_attributes'=>['ID'=>$file['file_id']],
            'fileTxt.fileName'=>$file['file_name'],
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



}