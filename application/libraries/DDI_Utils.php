<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 *
 * DDI utilities
 */ 
class DDI_Utils
{
    
    var $ci;

    function __construct() 
	{
		$this->ci =& get_instance();
		$this->ci->load->model("Catalog_model");
    }
	


	/**
	*
	* Strip metadata elements from the DDI
	*
	* @strip - 'summary_stats', 'variables', 'keep_basic'
	*
	**/
	function strip_ddi($sid, $strip, $keep_original)
	{
		switch($strip){
			case 'summary_stats':
				return $this->strip_ddi_summary_stats($sid,$keep_original);
			break;

			case 'variables':
				return $this->strip_ddi_variables($sid,$keep_original);
			break;

			case 'keep_basic':
				return $this->strip_ddi_to_basic($sid,$keep_original);
			break;
		}

		return false;
	}
	
	/**
	 * 
	 * 
	 * Strip summary stats from DDI
	 * 
	 */
	function strip_ddi_summary_stats($sid, $keep_original=true)
	{
		$xpath_array=array(
			'//ddi:codeBook/ddi:dataDscr//ddi:sumStat'=>'sumStat'
		);

		return $this->strip_ddi_parts($sid,$xpath_array, $keep_original);	
	}



	/**
	 * 
	 * 
	 * Strip data files and variables from DDI
	 * 
	 */
	function strip_ddi_variables($sid, $keep_original=true)
	{
		$xpath_array=array(
			'//ddi:codeBook/ddi:dataDscr'=>'dataDscr',
			'//ddi:codeBook/ddi:fileDscr'=>'fileDscr',
		);

		return $this->strip_ddi_parts($sid,$xpath_array, $keep_original);	
	}



	/**
	 * 
	 * 
	 * Strip all elements except titlStmt
	 * 
	 */
	function strip_ddi_to_basic($sid, $keep_original=true)
	{
		//paths to trim
		$xpath_array=array(
			'//ddi:codeBook/ddi:docDscr'=>'docDscr',
			//'//ddi:codeBook/ddi:stdyDscr'=>'stdyDscr',
			'//ddi:codeBook/ddi:stdyDscr//ddi:producer'=>'producer',
			'//ddi:codeBook/ddi:stdyDscr//ddi:fundAg'=>'sponsor',
			'//ddi:codeBook/ddi:stdyDscr//ddi:serStmt'=>'sername',
			'//ddi:codeBook/ddi:stdyDscr//ddi:geogCover'=>'geogCover',
			'//ddi:codeBook/ddi:stdyDscr//ddi:universe'=>'universe',
			'//ddi:codeBook/ddi:stdyDscr//ddi:method'=>'method',
			'//ddi:codeBook/ddi:stdyDscr//ddi:dataAccs'=>'dataAccs',
			'//ddi:codeBook/ddi:stdyDscr//ddi:distStmt'=>'distStmt',

			'//ddi:codeBook/ddi:stdyDscr//ddi:verStmt'=>'verStmt',
			'//ddi:codeBook/ddi:stdyDscr//ddi:subject'=>'subject',

			'//ddi:codeBook/ddi:stdyDscr/ddi:stdyInfo/ddi:abstract'=>'abstract',
			'//ddi:codeBook/ddi:stdyDscr/ddi:stdyInfo/ddi:sumDscr/ddi:timePrd'=>'timePrd',
			'//ddi:codeBook/ddi:stdyDscr/ddi:stdyInfo/ddi:sumDscr/ddi:geogCover'=>'geogCover',
			'//ddi:codeBook/ddi:stdyDscr/ddi:stdyInfo/ddi:sumDscr/ddi:anlyUnit'=>'anlyUnit',
			'//ddi:codeBook/ddi:stdyDscr/ddi:stdyInfo/ddi:sumDscr/ddi:universe'=>'universe',
			'//ddi:codeBook/ddi:stdyDscr/ddi:stdyInfo/ddi:sumDscr/ddi:dataKind'=>'dataKind',
			'//ddi:codeBook/ddi:stdyDscr/ddi:stdyInfo/ddi:notes'=>'notes',
			'//ddi:codeBook/ddi:stdyDscr/ddi:method/ddi:dataColl/ddi:dataCollector'=>'dataCollector',
			'//ddi:codeBook/ddi:stdyDscr/ddi:method/ddi:dataColl/ddi:sampProc'=>'sampProc',
			'//ddi:codeBook/ddi:stdyDscr/ddi:method/ddi:dataColl/ddi:deviat'=>'deviat',
			'//ddi:codeBook/ddi:stdyDscr/ddi:method/ddi:dataColl/ddi:collMode'=>'collMode',
			'//ddi:codeBook/ddi:stdyDscr/ddi:method/ddi:dataColl/ddi:resInstru'=>'resInstru',
			'//ddi:codeBook/ddi:stdyDscr/ddi:method/ddi:dataColl/ddi:collSitu'=>'collSitu',
			'//ddi:codeBook/ddi:stdyDscr/ddi:method/ddi:dataColl/ddi:actMin'=>'actMin',
			'//ddi:codeBook/ddi:stdyDscr/ddi:method/ddi:dataColl/ddi:weight'=>'weight',
			'//ddi:codeBook/ddi:stdyDscr/ddi:method/ddi:dataColl/ddi:cleanOps'=>'cleanOps',
			'//ddi:codeBook/ddi:stdyDscr/ddi:method/ddi:notes'=>'notes',
			'//ddi:codeBook/ddi:stdyDscr/ddi:method/ddi:anlyInfo/ddi:respRate'=>'respRate',
			'//ddi:codeBook/ddi:stdyDscr/ddi:method/ddi:anlyInfo/ddi:EstSmpErr'=>'EstSmpErr',
			'//ddi:codeBook/ddi:stdyDscr/ddi:method/ddi:anlyInfo/ddi:dataAppr'=>'dataAppr',
			'//ddi:codeBook/ddi:stdyDscr/ddi:dataAccs/ddi:useStmt/ddi:confDec'=>'confDec',
			'//ddi:codeBook/ddi:stdyDscr/ddi:dataAccs/ddi:useStmt/ddi:contact'=>'contact',
			'//ddi:codeBook/ddi:stdyDscr/ddi:dataAccs/ddi:useStmt/ddi:citReq'=>'citReq',
			'//ddi:codeBook/ddi:stdyDscr/ddi:dataAccs/ddi:useStmt/ddi:conditions'=>'conditions',
			'//ddi:codeBook/ddi:stdyDscr/ddi:dataAccs/ddi:useStmt/ddi:disclaimer'=>'disclaimer',
			'//ddi:codeBook/ddi:stdyDscr/ddi:citation/ddi:prodStmt/ddi:copyright'=>'copyright',
			'//ddi:codeBook/ddi:stdyDscr/ddi:citation/ddi:prodStmt/ddi:fundAg'=>'fundAg',
			'//ddi:codeBook/ddi:stdyDscr/ddi:citation/ddi:distStmt/ddi:contact'=>'contact',

			'//ddi:codeBook/ddi:dataDscr'=>'dataDscr',
			'//ddi:codeBook/ddi:fileDscr'=>'fileDscr',
			'//ddi:codeBook/ddi:dataDscr//ddi:sumStat'=>'sumStat',
			'//ddi:codeBook/ddi:otherMat'=>'otherMat',
		);

		return $this->strip_ddi_parts($sid,$xpath_array, $keep_original);	
	}


	/**
     * 
     * Strip elements from the DDI codebook
     * 
     * @sid - Survey ID
     * @xpath_array - array of xpaths to be removed
     * @keep_original - make a copy of the original file? 
	 * 
	 * 
     */
	function strip_ddi_parts($sid, $xpath_array=array(),$keep_original=true)
	{		
		$ddi_file=$this->ci->Catalog_model->get_survey_ddi_path($sid);

		if 	(!file_exists($ddi_file)){
			throw new Exception("FILE_NOT_FOUND: ". $ddi_file);
		}

		if(!is_array($xpath_array) || !count($xpath_array)>0){
			throw new Exception ("PARAM:XPATH_ARRAY_IS_EMPTY");
		}

		$doc = new DOMDocument;
		$doc->load($ddi_file);

		$xpath=new DOMXPath($doc);
		$rootNamespace = $doc->lookupNamespaceUri($doc->namespaceURI);
		$xpath->registerNamespace('ddi', $rootNamespace);		

		foreach($xpath_array as $xpath_key=>$value)
		{
			//find all matching nodes
			$nodes = $xpath->query($xpath_key);

			//replace all matching nodes
			foreach($nodes as $node) {
				//$result->parentNode->removeChild($result);
				$replacement_element=$doc->createElement($value);
				$node->parentNode->replaceChild($replacement_element, $node);
			}
		}

		//make a copy of the original file
		if ($keep_original==true){
			$original_file=str_replace(".xml","-original.xml",$ddi_file);
			//skip if backup file already exists
			if(!file_exists($original_file)){
				copy($ddi_file,$original_file);
			};
		}

		file_put_contents($ddi_file,$doc->saveXML());		
		return $ddi_file;
	}



	/**
	*
	* Reload metadata from DDI
	*
	* Updates database with the metadata from DDI
	* 
	* partial - if yes, only update study level metadata
	*
	**/
	function reload_ddi($id=NULL,$user_id=null, $partial=false)
	{		
		$this->ci->load->model("Catalog_model");
		$this->ci->load->library('DDI2_import');
		$this->ci->load->library('Dataset_manager');

		//get survey ddi file path by id
		$ddi_file=$this->ci->Catalog_model->get_survey_ddi_path($id);

		if ($ddi_file===FALSE){
			throw new Exception("DDI_FILE_NOT_FOUND: ".$ddi_file);
		}
		
		$dataset=$this->ci->dataset_manager->get_row($id);

		$params=array(
			'file_type'=>'survey',
			'file_path'=>$ddi_file,
			'repositoryid'=>$dataset['repositoryid'],
			'overwrite'=>'yes',
			'user_id'=>$user_id,
			'partial'=>$partial
		);
				
		$result=$this->ci->ddi2_import->import($params,$id);

		//reset changed and created dates
		$update_options=array(
			'changed'=>$dataset['changed'],
			'created'=>$dataset['created'],
			'repositoryid'=>$dataset['repositoryid'],
			//'link_da'=>$dataset['link_da'],
			'formid'=>$dataset['formid']
		);

		$this->ci->dataset_manager->update_options($id,$update_options);

		//get updated info
		$dataset=$this->ci->dataset_manager->get_row($id);

		return $dataset;
	}

}	