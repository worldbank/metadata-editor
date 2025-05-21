<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once(APPPATH."../modules/mpdf/vendor/autoload.php");

//require_once 'vendor/autoload.php';

//$mpdf = new \Mpdf\Mpdf();

/**
 * PDF Generation class wrapper
 * 
 *
 *
 *
 * @subpackage	Libraries
 * @category	PDF Generator
 *
 */ 
class MY_mPDF extends \Mpdf\Mpdf{
    
	function __construct($params=NULL)
	{
		if (is_array($params))
		{
			//set temp folder path
			$params['tempDir'] = FCPATH.'/datafiles/tmp';

			if (isset($params['codepage']))
			{
				parent::__construct($params);
				return;
			}	
		}
		
		parent::__construct($params);
	}
}
// END MY_mPDF Class

/* End of file my_mpdf.php */
/* Location: ./application/libraries/my_mpdf.php */