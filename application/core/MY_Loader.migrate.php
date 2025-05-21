<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 4.3.2 or newer
 *
 * @package		CodeIgniter
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2008 - 2009, EllisLab, Inc.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource: 
 */

// ------------------------------------------------------------------------

/**
 * Loader Class - exended to add external views
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Loader
 * @author		http://codeigniter.com/forums/viewthread/132960/#671434
 * @link		http://codeigniter.com/forums/viewthread/132960/#671434
 *
 * only the create_links method has been modified
 */

/* load the MX_Loader class */
require APPPATH."third_party/MX/Loader.php";
class MY_Loader extends MX_Loader {} 

/*class MY_Loader extends MX_Loader{
    public function __construct()
    {
        parent::__construct();
    }

    public function external_view($path, $view, $vars = array(), $return = FALSE)
    {
        $full_path = $path.'/'.$view.'.php';

        if (file_exists($full_path))
        {
			return $this->_ci_load(array('_ci_path' => $full_path, '_ci_view' => $view, '_ci_vars' => $this->_ci_object_to_array($vars), '_ci_return' => $return));
        }
        else
        {
            show_error('Unable to load the requested module template file: '.$view);
        }
    }
} 
} */
// END Class

/* End of file MY_Loader.php */
/* Location: ./application/libraries/My_Loader.php */