<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


require_once(APPPATH.'/libraries/Metadata_parser/classes/ISO19139Reader.php');

/**
 * Import Geospatial xml file
 *
 */
class Geospatial_import{

    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->model("Editor_model");
    }

    function import($sid,$file_path)
	{
        //try{
            $parser = new ISO19139Reader($file_path);        
            $metadata=$parser->parse();

            //remove any empty values
            array_remove_empty($metadata);
        
            if (isset($metadata['fileIdentifier'])){
                $metadata['fileIdentifier']=str_replace(" ","_",$metadata['fileIdentifier']);
            }

            //import metadata
            $this->ci->Editor_model->update_project($type='geospatial',$sid,$metadata,$validate=true);
            
       /* }
        catch (Exception $e) {
            throw new Exception("Error: " . $e->getMessage());
        }*/
        
        return $metadata;
    }
}