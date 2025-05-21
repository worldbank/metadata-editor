<?php
/**
* Extends the CI Router to including Routing for page URLs stored in the database.
*
* 	1) searches controller folders/subfolders
* 	2) load the page through the default controller [page] which handles the static pages
* 	3) if a page is not found throught 1 and 2, throws a 404 page not found error
*
*	NOTE: with this there is no need to create routes in the routes.php file for the PAGE controller
*
* 	source: http://maestric.com/doc/php/codeigniter_404
*/
class MY_Router extends CI_Router {
 
	var $error_controller = 'page'; //controller name to be used as a 404 page handler
	var $error_method_404 = 'index';//controller method to be called
 
    function __construct()
    {
        //parent::__construct();
    }
 
	// this is just the same method as in Router.php, with show_404() replaced by $this->error_404();
	function _validate_request($segments)
	{
		//return parent::_validate_request($segments);
		$o_segments=$segments;
		// Does the requested controller exist in the root folder?
		if (file_exists(APPPATH.'controllers/'.$segments[0].EXT))
		{
			return $segments;
		}
 
		// Is the controller in a sub-folder?
		if (is_dir(APPPATH.'controllers/'.$segments[0]))
		{		
			// Set the directory and remove it from the segment array
			$this->set_directory($segments[0]);
			$segments = array_slice($segments, 1);
 
 			//search multi-level deep folders
			while(count($segments) > 0 && is_dir(APPPATH.'controllers/'.$this->directory.$segments[0]))
            {
				echo "X";
				// Set the directory and remove it from the segment array
				$this->set_directory($this->directory . $segments[0]);
				$segments = array_slice($segments, 1);
            }
 
			if (count($segments) > 0)
			{
				// Does the requested controller exist in the sub-folder?
				if ( ! file_exists(APPPATH.'controllers/'.$this->fetch_directory().$segments[0].EXT))
				{
					return parent::_validate_request($o_segments);
				}
			}
			else
			{
				$this->set_class($this->default_controller);
				$this->set_method('index');
 
				// Does the default controller exist in the sub-folder?
				if ( ! file_exists(APPPATH.'controllers/'.$this->fetch_directory().$this->default_controller.EXT))
				{
					$this->directory = '';
					return array();
				}
			}
 
			return $segments;
		}
 		 
		// Can't find the requested controller...
		return $this->error_404();
	}
	
	function error_404()
	{
		$segments = array();
		$segments[] = $this->error_controller;
		$segments[] = $this->error_method_404;
		return $segments;
	}
	
}	
?>