<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if ( ! function_exists('var_dump_pre'))
{
	function nada_dump($data) {
			echo '<pre>';
			var_dump($data);
			echo '</pre>';
	}
}

/* End of file debug_helper.php */
/* Location: ./application/helpers/debug_helper.php */
