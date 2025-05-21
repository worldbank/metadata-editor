<?php (defined('BASEPATH')) OR exit('No direct script access allowed');

/* load the MX_Router class */
require APPPATH."third_party/MX/Router.php";

class MY_Router extends MX_Router 
{
	var $error_controller = 'page'; //controller name to be used as a 404 page handler
	var $error_method_404 = 'index';//controller method to be called

	//copied from CI core Router class
	function _validate_request($segments)
    {
        if (file_exists(APPPATH.'controllers/'.$segments[0].EXT))
        {
            return $segments;
        }
 
        if (is_dir(APPPATH.'controllers/'.$segments[0]))
        {
            $this->set_directory($segments[0]);
            $segments = array_slice($segments, 1);
 
            //support for multi level sub-folders
            while(count($segments) > 0 && is_dir(APPPATH.'controllers/'.$this->directory.$segments[0]))
            {
            	$this->directory = $this->directory . $segments[0] . '/';
				$segments = array_slice($segments, 1);
            }
			//end-change
 
            if (count($segments) > 0)
            {
                if ( ! file_exists(APPPATH.'controllers/'.$this->fetch_directory().$segments[0].EXT))
                {
                    show_404($this->fetch_directory().$segments[0]);
                }
            }
            else
            {
                $this->set_class($this->default_controller);
                $this->set_method('index');
 
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