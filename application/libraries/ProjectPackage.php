<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


/**
 * 
 * 
 * Project package import and export
 * 
 * 
 */
class ProjectPackage
{

	/**
	 * Constructor
	 */
	function __construct()
	{
		log_message('debug', "ProjectPackage Class Initialized.");
		$this->ci =& get_instance();

		$this->ci->load->model("Editor_model");
	}


    function generate_zip($sid)
	{
        $project=$this->ci->Editor_model->get_basic_info($sid);
		$path = $this->ci->Editor_model->get_project_folder($sid); 
        
        if (!$project){
            throw new Exception("Project not found");
        }

        $zip_path=$path.'/'.$project['idno'].'.zip';

        if (file_exists($zip_path)){
            unlink($zip_path);
        }
        
        //generate info.json
        $this->create_info_json($sid);

        //create zip file
        $zipFile = new \PhpZip\ZipFile();
        try{
            set_time_limit(0);
            $zipFile
                ->addDirRecursive($path) // add files from the directory
                ->saveAsFile($zip_path) // save the archive to a file
                ->close();					
        }
        catch(\PhpZip\Exception\ZipException $e){
            throw new Exception("Failed to generate zip file". $e->getMessage());
        }
        finally{
            $zipFile->close();
        }

        return $zip_path;
	}
    
    /**
     * 
     * Create project info.json file
     * 
     * Includes:
     *  - project basic info
     *      - idno
     *      - created
     *      - created_by      
     *  - project type
     *  - thumbnail
     *  - xml_file
     *  - json_file
     *  - rdf_xml_file
     *  - rdf_json_file
     * 
     * 
     */
    function create_info_json($sid)
    {
        $project=$this->ci->Editor_model->get_basic_info($sid);

        if (!$project){
            throw new Exception("Project not found");
        }

        $project_folder_path=$this->ci->Editor_model->get_project_folder($sid);

        $info=array(
            'idno'=>$project['idno'],
            'created'=>date("c"),//iso-date
            'type'=>$project['type'],
            'thumbnail'=>$this->ci->Editor_model->get_thumbnail($sid),
            'xml_file'=>$project['idno'].'.xml',
            'json_file'=>$project['idno'].'.json',
            'rdf_xml_file'=>$project['idno'].'.rdf',
            'rdf_json_file'=>$project['idno'].'.rdf.json',
        );

        file_put_contents($project_folder_path.'/info.json', json_encode($info,JSON_PRETTY_PRINT));
    }
    

}


