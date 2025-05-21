<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Extended CodeIgniter Exception class
 *
 * Compatible with PHP versions 4.3.0+ and 5
 *
 * Will display a backtrace list on the error page if the constant
 * DEBUG_BACKTRACE has been set to TRUE. False disables all change
 * from default CI behaviour. Please add a constant definition to your
 * config.php, I recommend just below log_threshold since they are related
 * Example: define('DEBUG_BACKTRACE', TRUE);
 *
 * Remember to set it to FALSE on your live site!
 *
 * @version   0.1.0
 * @link - http://codeigniter.com/forums/viewthread/69407/
 * @Author Beren - http://codeigniter.com/forums/member/58252/
 */
define('DEBUG_BACKTRACE', ENVIRONMENT=='development');
class MY_Exceptions extends CI_Exceptions {

  /**
   * Generates a pretty backtrace for display in the browser
   *
   * Backtrace processing inspired by Kohana project (kohanaphp.com)
   *
   * @access public
   * @return string  full HTML string ready to be output
   */
  function generate_backtrace()
  {
      // the first two results are this function and $this->show_error() call
      // so we'll ignore them
      $backtrace = array_slice(debug_backtrace(), 2);

      $trace_output = array();

      foreach($backtrace as $entry)
      {
        $html = '<li>';

        if( isset($entry['file']))
        {
          $html .= 'Line #<strong>' . $entry['line'] . '</strong> of <strong>' . $entry['file'] . '</strong>';
        }

        $html .= '<pre>';

        if( isset($entry['class']))
        {
          $html .= $entry['class'].$entry['type'];
        }

        if( isset($entry['function']))
        {
          $html .= $entry['function'] . '(';

          if( isset($entry['args']))
          {
            if( isset($entry['args'][0]) AND is_array($entry['args'][0]))
            {
              $seperator = '';

              foreach($entry['args'] as $argument)
              {
			  	if(is_array($argument))
				{
					continue;
				}
                $html .= $seperator . strval($argument);
                $seperator = ', ';
              }
            }
            else
            {
				/*
              $html .= implode(', ', $entry['args']);  */
            }
          }

          $html .= ')';
        }

        $html .= '</li>';

        $trace_output[] = $html;
      }
      echo '<h2 style="font-weight:normal">Backtrace</h2><ul>' . implode("\n", $trace_output) . '</ul>';
  }


    /**
     * General Error Page
     *
     * This function takes an error message as input
     * (either as a string or an array) and displays
     * it using the specified template.
     *
     * @access    private
     * @param    string    the heading
     * @param    string    the message
     * @param    string    the template name
     * @return    string
     */
    public function show_error($heading, $message, $template = 'error_general', $status_code = 500)
    {
      $templates_path = config_item('error_views_path');
      if (empty($templates_path))
      {
        $templates_path = VIEWPATH.'errors'.DIRECTORY_SEPARATOR;
      }

      if (is_cli())
      {
        $message = "\t".(is_array($message) ? implode("\n\t", $message) : $message);
        $template = 'cli'.DIRECTORY_SEPARATOR.$template;
      }
      else
      {
        set_status_header($status_code);
        $message = '<p>'.(is_array($message) ? implode('</p><p>', $message) : $message).'</p>';
        $template = 'html'.DIRECTORY_SEPARATOR.$template;
      }

      if (ob_get_level() > $this->ob_level + 1)
      {
        ob_end_flush();
      }
      ob_start();
      include($templates_path.$template.'.php');

      if (DEBUG_BACKTRACE)
			{
			  echo $this->generate_backtrace();
			}

      $buffer = ob_get_contents();
      ob_end_clean();
      return $buffer;
    }

    /**
     * Native PHP error handler
     *
     * @access    private
     * @param    string    the error severity
     * @param    string    the error string
     * @param    string    the error filepath
     * @param    string    the error line number
     * @return    string
     */
    public function show_php_error($severity, $message, $filepath, $line)
  	{
  		$templates_path = config_item('error_views_path');
  		if (empty($templates_path))
  		{
  			$templates_path = VIEWPATH.'errors'.DIRECTORY_SEPARATOR;
  		}

  		$severity = isset($this->levels[$severity]) ? $this->levels[$severity] : $severity;

  		// For safety reasons we don't show the full file path in non-CLI requests
  		if ( ! is_cli())
  		{
  			$filepath = str_replace('\\', '/', $filepath);
  			if (FALSE !== strpos($filepath, '/'))
  			{
  				$x = explode('/', $filepath);
  				$filepath = $x[count($x)-2].'/'.end($x);
  			}

  			$template = 'html'.DIRECTORY_SEPARATOR.'error_php';
  		}
  		else
  		{
  			$template = 'cli'.DIRECTORY_SEPARATOR.'error_php';
  		}

  		if (ob_get_level() > $this->ob_level + 1)
  		{
  			ob_end_flush();
  		}
  		ob_start();
  		include($templates_path.$template.'.php');


      if (DEBUG_BACKTRACE)
      {
        echo $this->generate_backtrace();
      }

  		$buffer = ob_get_contents();
  		ob_end_clean();
  		echo $buffer;
  	}

}
