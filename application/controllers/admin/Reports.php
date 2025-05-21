<?php
class Reports extends MY_Controller {
 
    public function __construct()
    {
        parent::__construct();
		
		//set template to admin 
		$this->template->set_template('admin');
      	$this->load->model('Reports_model');
		
		$this->lang->load("reports");	
		//$this->output->enable_profiler(TRUE);
    }
 
	function index()
	{	
		$this->template->set_template('admin5');		
		//javascript/css needed for showing the date picker
		$this->template->add_css('javascript/jquery/ui/themes/base/jquery-ui.css');
		$this->template->add_js('javascript/jquery/ui/jquery.ui.js');	
		
		//show the view with report options		
		$this->template->write_view('content', 'reports/index', NULL, TRUE);			
		$this->template->write('title', 'Reports',true);
		$this->template->render();
	}


	//reports/?from=x&to=X&report=top_keywords		
	function _remap()
	{		
	   //required
	   $report=$this->input->get("report");
	   
	   //report format
	   $format=$this->input->get("format"); //e.g. excel
	   
	   $from=$this->input->get("from");//MUST be in MM/DD/YYYY format
	   $to=$this->input->get("to");//MUST be in MM/DD/YYYY format

	   $from=$this->_unix_date($from);
	   $to=$this->_unix_date($to);	   	   	   
	   	   
	   switch($report)
	   {
	   		case 'top-keywords':
				$data['rows']=$this->Reports_model->get_top_keywords($from,$to);
				
				if ($format=='excel')
				{
					$this->_export_to_excel($data['rows'],'download-detailed-'.date("m-d-y").'.xls');
					return;
				}	
				
				$this->load->view("reports/top_keywords",$data);
			break;
			
			case 'survey-summary':
				$data['rows']=$this->Reports_model->get_survey_summary($from,$to);
				
				if ($format=='excel')
				{
					$this->_export_to_csv($data['rows'],'download-detailed-'.date("m-d-y").'.csv');
					return;
				}	
				
				$this->load->view("reports/survey_summary",$data);
			break;

			case 'survey-detailed':
				
				if ($format=='excel')
				{
					
				    $data['rows']=$this->Reports_model->get_survey_detailed_all($from,$to);
					$this->_export_to_csv($data['rows'] ,'download-detailed-'.date("m-d-y").'.csv');
					return;
				}
					
				$data['rows']=$this->Reports_model->get_survey_detailed($from,$to);
				$this->load->view("reports/survey_detailed",$data);
			break;
			
			case 'downloads-detailed':
				$data['rows']=$this->Reports_model->downloads_detailed($from,$to);
				
				if ($format=='excel')
				{
					$this->_export_to_csv($data['rows'],'download-detailed-'.date("m-d-y").'.csv');
					return;
				}	
				
				$this->load->view("reports/downloads_detailed",$data);
			break;

			case 'licensed-requests':
				$data['rows']=$this->Reports_model->licensed_requests($from,$to);
				
				if ($format=='excel')
				{
					$this->_export_to_csv($data['rows'],'licensed-requests-'.date("m-d-y").'.csv');
					return;
				}
					
				$this->load->view("reports/licensed_requests",$data);
			break;

			case 'public-requests':
				$data['rows']=$this->Reports_model->public_requests($from,$to);
				
				if ($format=='excel')
				{
					$this->_export_to_csv($data['rows'],'public-requests-'.date("m-d-y").'.csv');
					return;
				}
					
				$this->load->view("reports/public_requests",$data);
			break;
			
			case 'study-statistics':
				$data=$this->Reports_model->survey_summary_statistics();
				
				if ($format=='excel')
				{
					$this->_export_study_statistics_to_csv($data,'study-statistics-'.date("m-d-y").'.csv');
					return;
				}					
				$this->load->view("reports/study_statistics",$data);
			break;
			
			case 'users-statistics':
				$data['rows']=$this->Reports_model->user_stats($from,$to);

				if ($format=='excel')
				{
					$output=$this->load->view("reports/users_statistics",$data,TRUE);
					$this->_export_to_csv($output,'users-statistics-'.date("m-d-y").'.csv');
					return;
				}					
				$this->load->view("reports/users_statistics",$data);
			break;

			case 'user-activity':
				$data['rows']=$this->Reports_model->user_activity($from,$to);

				if ($format=='excel')
				{
					$output=$this->load->view("reports/user_activity",$data,TRUE);
					$this->_export_to_csv($output,'user-activity-'.date("m-d-y").'.xls');
					return;
				}					
				$this->load->view("reports/user_activity",$data);
			break;
			
			case 'study-data-access':
				$data['rows']=$this->Reports_model->study_data_access();
				if ($format=='excel')
				{
					$output=$this->load->view("reports/study_data_access",$data,TRUE);
					$this->_export_to_csv($output,'study-data-'.date("m-d-y").'.xls');
					return;
				}
				$this->load->view("reports/study_data_access",$data);
				break;
				
			case 'broken-resources':
				//find broken links for microdata types only
				$data['rows']=$this->Reports_model->broken_resources(array('%dat/micro]%', '%dat]%'));
				if ($format=='excel')
				{
					$output=$this->load->view("reports/broken_resources",$data,TRUE);
					$this->_export_to_csv($output,'broken-resources-'.date("m-d-y").'.xls');
					return;
				}
				$this->load->view("reports/broken_resources",$data);
				break;	
			default:
				$this->index();
			break;
	   }
	   
/*	   	   //validate {from,to,report}
	   $from=$this->input->get("from");//MUST be in MM/DD/YYYY format
	   $to=$this->input->get("to");//MUST be in MM/DD/YYYY format

	   $from=$this->_unix_date($from);
	   $to=$this->_unix_date($to);
*/	   
	}

    function _export_study_statistics_to_csv($options,$filename)
    {
        foreach ($options['rows'] as $row_idx => $row) {
            $options['rows'][$row_idx]['has_data'] = (isset($options['data'][$row['id']]) ? $options['data'][$row['id']] : 'N/A');
            $options['rows'][$row_idx]['has_data'] = (isset($options['citations'][$row['id']])) ? $options['citations'][$row['id']] : 0;
            $options['rows'][$row_idx]['has_reports'] = (isset($options['reports'][$row['id']])) ? $options['reports'][$row['id']] : 0;
            $options['rows'][$row_idx]['has_questionnaire'] = (isset($options['questionnaires'][$row['id']])) ? $options['questionnaires'][$row['id']] : 0;
        }

        $this->_export_to_csv($options['rows'],$filename);
    }


	
	function _export_to_excel($rows,$filename)
	{
		//set header for outputing to Excel
		header("Expires: Sat, 01 Jan 1980 00:00:00 GMT");
		header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: public"); 
		header("Content-Description: File Transfer");
		
		session_cache_limiter("must-revalidate");
		header("Content-Type: application/vnd.ms-excel");
		header('Content-Disposition: attachment; filename="'.$filename.'"'); 

		if (!is_array($rows))
		{
			echo $rows;
		}
		else
		{	
			$this->load->view('reports/excel',array('rows'=>$rows));
		}	
	}
	
	
	function _export_to_csv($rows,$filename)
	{
		//set header for outputing to CSV
		header("Expires: Sat, 01 Jan 1980 00:00:00 GMT");
		header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: public"); 
		header("Content-Description: File Transfer");
		
		//session_cache_limiter("must-revalidate");
        header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		
		if (!is_array($rows))
		{
			echo $rows;exit;
		}

		$outstream = fopen("php://output", "w");

		//header row with column names
		$column_names=array_keys($rows[0]);
		
		//unix timestamp type columns that needs to be formatted as readable date types
		$date_columns=array('logtime','created','changed','updated','posted');
		
		//$date_type_idx=array();
		
		//date type columns used by current data
		$date_columns_found=array();
		
		//get indexes for date type columns
		foreach($column_names as $col)
		{
		   /* $idx=array_search($col,$date_columns);
		    if ($idx!=false)
		    {
			$date_type_idx[]=$idx;			
		    }*/
		    
		    if (in_array($col,$date_columns)){
				$date_columns_found[]=$col;			
		    }
		}

        //UTF8 BOM
        echo "\xEF\xBB\xBF";

		fputcsv($outstream, $column_names,$delimiter=",", $enclosure='"');
		
		//get data format
		$date_format=$this->config->item('date_format_long');

		if(!$date_format){
			$date_format="Y/M/d H:i:s";
		}
	            
		//data rows
		foreach($rows as $row)
		{		    
		    if ($date_columns_found)
		    {			
				foreach($date_columns_found as $col)
				{
					$row[$col]=date($date_format, $row[$col]); 
				}			
		    }
		    
		    fputcsv($outstream, array_values($row));
		}
	
		fclose($outstream);
	}
	
	
	/**
	*
	* Converts the M/D/Y formatted date to Unix timestamp or return false
	*/
	function _unix_date($date)
	{
		$tmp=str_replace("/","",$date);
		
		if (!is_numeric($tmp))
		{
			return FALSE;
		}
		
		list($month, $day,$year) = explode("/", $date);
		if (is_numeric($year) && is_numeric($month) && is_numeric($day) ){
			return date('U', mktime(0, 0, 0, $month, $day, $year));
		}
		return FALSE;
	}
	
}
/* End of file reports.php */
/* Location: ./controllers/reports.php */