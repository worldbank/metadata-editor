<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Captcha_lib extends CI_Driver_Library
{
	protected $valid_drivers 	= array(
		'recaptcha',
		'image_captcha',
		'none',
	);

	//default driver to load if no driver is specified
	protected $_adapter	= 'image_captcha';

	/**
	 * Constructor
	 *
	 * @param array
	 */
	public function __construct($config = array())
	{
		if ( ! empty($config))
		{
			$this->_initialize($config);
		}
		else
		{
			//load captcha configurations
			$CI =&get_instance();
			$CI->config->load("captcha");

			//select driver to use from config
			$driver=$CI->config->item("captcha_driver");
			$config=array('adapter' => $driver);

			//load driver
			$this->_initialize($config);
		}
	}

	/**
	 * Initialize
	 *
	 * Initialize class properties based on the configuration array.
	 *
	 * @param	array
	 * @return 	void
	 */
	private function _initialize($config)
	{
		//default driver to use if non set
		$default_config = array(
				'adapter',
				'recaptcha'
			);

		foreach ($default_config as $key)
		{
			if (isset($config[$key]))
			{
				$param = '_'.$key;
				$this->{$param} = $config[$key];
			}
		}
	}

	/**
	 * Is the requested driver supported in this environment?
	 *
	 * @param 	string	The driver to test.
	 * @return 	array
	 */
	public function is_supported($driver)
	{
		$prefix='captcha_lib_';
		if ( in_array($prefix.$driver,$this->valid_drivers))
		{
			return TRUE;
		}
		return FALSE;
	}


	function get_html()
	{
		return $this->{$this->_adapter}->get_html();
	}

	function check_answer()
	{
		return $this->{$this->_adapter}->check_answer();
	}

	function get_question_field()
	{
		return $this->{$this->_adapter}->get_question_field();
	}

}
