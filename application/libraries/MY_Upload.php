<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Extended upload class to change a few things
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Upload
 *
 */
class MY_Upload extends CI_Upload {
 
  	/**
	 * Set the file name
	 *
	 * This function takes a filename/path as input and looks for the
	 * existence of a file with the same name. If found, it will append a
	 * number to the end of the filename to avoid overwriting a pre-existing file.
	 *
	 * @change	Adds an underscore at the end of the Encrpted file name
	 *
	 * @param	string
	 * @param	string
	 * @return	string
	 */
	public function set_filename($path, $filename)
	{
		
		if ($this->encrypt_name == TRUE)
		{
			mt_srand();
			//$filename = md5(uniqid(mt_rand())).$this->file_ext; //original
			$filename = md5(uniqid(mt_rand())).$this->file_ext."_";
		}

		return parent::set_filename($path,$filename);
		
		/*
		if ( ! file_exists($path.$filename))
		{
			return $filename;
		}

		$filename = str_replace($this->file_ext, '', $filename);

		$new_filename = '';
		for ($i = 1; $i < 100; $i++)
		{
			if ( ! file_exists($path.$filename.$i.$this->file_ext))
			{
				$new_filename = $filename.$i.$this->file_ext;
				break;
			}
		}

		if ($new_filename == '')
		{
			$this->set_error('upload_bad_filename');
			return FALSE;
		}
		else
		{
			return $new_filename;
		}*/

	}

  
  	/**
	 * Verify that the filetype is allowed
	 *
	 * @change	$ignore_mime changed to TRUE - disables the mime type checks
	 *
	 * @return	bool
	 */
	public function is_allowed_filetype($ignore_mime = TRUE)
	{
		if ($this->allowed_types == '*')
		{
			return TRUE;
		}

		if (count($this->allowed_types) == 0 OR ! is_array($this->allowed_types))
		{
			$this->set_error('upload_no_file_types');
			return FALSE;
		}

		$ext = strtolower(ltrim($this->file_ext, '.'));

		if ( ! in_array($ext, $this->allowed_types))
		{
			return FALSE;
		}

		// Images get some additional checks
		$image_types = array('gif', 'jpg', 'jpeg', 'png', 'jpe');

		if (in_array($ext, $image_types))
		{
			if (getimagesize($this->file_temp) === FALSE)
			{
				return FALSE;
			}
		}

		if ($ignore_mime === TRUE)
		{
			return TRUE;
		}

		$mime = $this->mimes_types($ext);
		
		if (is_array($mime))
		{
			if (in_array($this->file_type, $mime, TRUE))
			{
				return TRUE;
			}
		}
		elseif ($mime == $this->file_type)
		{
				return TRUE;
		}

		return FALSE;
	}

  
}
// END Upload Class

/* End of file MY_Upload.php */
/* Location: ./application/libraries/My_Upload.php */