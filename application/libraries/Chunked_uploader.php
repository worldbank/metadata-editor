<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 
 * CodeIgniter Spark library for handling chunked uploads from Plupload.
 * 
 * An object-oriented adaptation of the PHP demo server for Plupload 
 * by Moxiecode Systems AB
 * 
 * (http://www.plupload.com/)
 * 
 * Released under GPLv2 License (http://www.gnu.org/licenses/gpl-2.0.html)
 * 
 * @author Jeremy Elliot <jeremyelliot@gmail.com>
 * @version 0.0.1
 */
class Chunked_uploader {

	/* configurable via constructor parameter array */
	private $_target_dir; // to which the files will be saved
	private $_cleanup_target_dir = 0.1; // chance of doing a cleanup this time (0 == never, 1 == always)
	private $_max_tmp_file_age = 3600; // max lifetime of temp file in seconds
	private $_transfer_block_size = 4096; // bytes
	private $_max_execution_time = 300; // seconds
	private $_partial_file_suffix = '.part';
	private $_allowed_extensions='txt,xml,rdf';//default file types allowed
	private $_overwrite_file=TRUE; //overwrite if the file already exists?

	/* not configurable */
	private $_file_name; // original name of the uploaded file
	private $_request; // Chunked_upload_request object
	private $_is_completed = FALSE;

	/**
	 * Constructs and initialised a new ChunkedUploader.
	 * The configurable properties are: 
	 * - target_dir 
	 * - cleanup_target_dir (chance of doing a cleanup this time, between 0.00 and 1.00)
	 * - max_tmp_file_age (max lifetime of temp file in seconds)
	 * - transfer_block_size (bytes) 
	 * - max_execution_time (seconds before this script dies of old age)
	 * - partial_file_suffix (string appended to name of incomplete files)
	 * 
	 * @param array $config 
	 */
	public function __construct($config)
	{
		$this->_target_dir = ini_get("upload_tmp_dir");
		// configure this object if a config array was passed in
		if (is_array($config))
		{
			// loop through config array and set values on $this
			foreach ($config as $property => $value)
			{
				$property = '_' . $property;
				if (isset($this->$property)) // check property is defined
				{
					$this->$property = $value; // set new value
				}
			}
		}
		
		// set non-configurable properties
		$this->_is_completed = FALSE;
		$request = new Chunked_upload_request(); // Load the upload request parameters 
		
		if ($this->_overwrite_file)
		{
			$this->_file_name=$request->get_name();
		}
		else
		{		
			// set the name of the file to which the completed upload will be written
			$this->_file_name = $this->_get_unique_file_name($request->get_name());
		}
			
		$this->_request = $request;
	}

	/**
	*
	* check if uploaded file extension is allowed
	* note: checks only the file extenions, no mime-type checks are implemented
	**/
	private function is_allowed_extension($file_name)
	{
		$extensions=explode("|",$this->_allowed_extensions);
		$file_ext= pathinfo($file_name, PATHINFO_EXTENSION);
		
		if(in_array($file_ext,$extensions))
		{
			return TRUE;
		}
		
		return FALSE;
	}

	/**
	 * Uploads the file or chunk of file
	 */
	public function upload()
	{
		@set_time_limit($this->_max_execution_time);
		$this->_prepare_target_dir($this->_target_dir);
		
		$is_allowed=$this->is_allowed_extension($this->get_file_path());
				
		if (!$is_allowed )
		{
			throw new Exception("FILE-TYPE-NOT-ALLOWED");
		}
		
		if ($this->_request->is_multipart())
		{
			$this->_upload_multipart($this->get_file_path(), $this->_request);
		}
		else
		{
			$this->_upload_non_multipart($this->get_file_path(), $this->_request);
		}
		if ($this->_request->is_last_chunk())
		{
			rename($this->get_file_path() . $this->_partial_file_suffix,
					$this->get_file_path());
			$this->_is_completed = TRUE;
		}
	}

	/**
	 * Prepares the target directory.
	 * Creates it if it doesn't exist and empties it if $this->cleanup_target_dir
	 * @param string $target_dir name of the target directory
	 * @return void
	 * @throws RuntimeException if temp dir cannot be opened
	 */
	private function _prepare_target_dir($target_dir)
	{
		// Create target dir
		if (!file_exists($target_dir))
		{
			//@mkdir($target_dir);
			return FALSE;
		}		
		
		// do a cleanup if $this->_cleanup_target_dir is greater than 
		// a random number between 0.00 and 1.00
		if ($this->_cleanup_target_dir >= (mt_rand(0, 100) / 100))
		{
			if (!(is_dir($target_dir) && ($dir = opendir($target_dir))))
			{
				throw new RuntimeException("Failed to open temp directory.", 100);
			}
			// Remove old temp files	
			while (($file = readdir($dir)) !== false)
			{
				$tmp_file_path = $target_dir . DIRECTORY_SEPARATOR . $file;
				// Remove temp file if older than the max age and not the current file
				$is_tmp_file = (substr($file, 0 - strlen($this->_partial_file_suffix))
						== $this->_partial_file_suffix);
				if ($is_tmp_file
						&& (filemtime($tmp_file_path) < time() - $this->_max_tmp_file_age)
						&& ($tmp_file_path != $this->get_file_path() . $this->_partial_file_suffix))
				{
					@unlink($tmp_file_path);
				}
			}
			closedir($dir);
		}
	}


	private function _file_exists($file_name)
	{
		if (file_exists($this->_target_dir . DIRECTORY_SEPARATOR . $file_name))
		{
			return TRUE;
		}		

		return FALSE;
	}	


	/**
	 * Creates new sequential file name if the target file name already exists
	 * @param string $file_name
	 * @return string unique file name
	 */
	private function _get_unique_file_name($file_name)
	{
		// Make sure the fileName is unique
		if (file_exists($this->_target_dir . DIRECTORY_SEPARATOR . $file_name))
		{
			$ext = strrpos($file_name, '.');
			$file_name_a = substr($file_name, 0, $ext);
			$file_name_b = substr($file_name, $ext);
			$count = 1;
			// increment the number at the end of the filename until an unused 
			// number is found
			while (file_exists($this->_target_dir . DIRECTORY_SEPARATOR . $file_name_a
					. '_' . $count . $file_name_b))
			{
				$count++;
			}
			$file_name = $file_name_a . '_' . $count . $file_name_b;
		}
		return $file_name;
	}

	/**
	 * Handle multipart uploads.
	 * Older WebKit versions didn't support multipart in HTML5
	 * @param type $file_path
	 * @param Chunked_upload_request $request
	 * @throws RuntimeException 
	 */
	private function _upload_multipart($file_path, Chunked_upload_request $request)
	{
		$temp_name = isset($_FILES['file']['tmp_name'])
				? $_FILES['file']['tmp_name']
				: '';
		if (is_uploaded_file($temp_name))
		{
			// attempt to open temp output file
			$in_file = $temp_name;
			$append = (!$request->is_first_chunk());
			$this->_transfer_data($in_file, $file_path, $append);
		}
		else
		{
			throw new RuntimeException("Failed to move uploaded file.", 103);
		}
	}

	/**
	 * Handles non-multipart uploads.
	 * @param type $file_path
	 * @param Chunked_upload_request $request 
	 */
	private function _upload_non_multipart($file_path,
			Chunked_upload_request $request)
	{
		$in_file = 'php://input';
		$append = (!$request->is_first_chunk());
		$this->_transfer_data($in_file, $file_path, $append);
	}

	/**
	 * Transfers data from input file to output file
	 * @param type $in_file
	 * @param type $out_file
	 * @param Chunked_upload_request $request
	 * @throws RuntimeException 
	 */
	private function _transfer_data($in_file, $out_file, $append_mode = FALSE)
	{
		$out_file = $out_file . $this->_partial_file_suffix;
		// Open temp file
		$out = fopen($out_file, ($append_mode)
						? 'ab'
						: 'wb');
		if ($out)
		{
			// Read input stream and write/append it to output file
			$in = fopen($in_file, "rb");
			if ($in)
			{
				while ($buffer = fread($in, $this->_transfer_block_size))
				{
					fwrite($out, $buffer);
				}
				fclose($in);
				fclose($out);
			}
			else
			{
				fclose($out);
				throw new RuntimeException("Failed to open input stream.", 101);
			}
		}
		else
		{
			throw new RuntimeException("Failed to open output stream", 102);
		}
	}

	/**
	 * Returns the final name of the downloaded file.
	 * This is the original name of the file after it is cleaned and
	 * possibly modified to be unique on the server.
	 * @return string filename 
	 */
	public function get_file_name()
	{
		return $this->_file_name;
	}

	/**
	 * Returns the full path to the downloaded file, including filename.
	 * Includes the target (temp) directory, may be relative or absolute
	 * @return string file path 
	 */
	public function get_file_path()
	{
		return $this->_target_dir . DIRECTORY_SEPARATOR . $this->_file_name;
	}

	/**
	 * Returns TRUE if the download is completed, otherwise FALSE
	 * @return boolean download state
	 */
	public function is_completed()
	{
		return $this->_is_completed;
	}

}

/**
 * Represents a file upload request 
 */
class Chunked_upload_request {

	private $_chunks; // total number of chunks
	private $_chunk; // number of current chunk
	private $_name; // original name of file
	private $_content_type; // content-type of the request
	private $_file_type; // mime-type of file

	/**
	 * Initialises new object with values from the $_REQUEST and 
	 * $_SERVER arrays
	 */

	public function __construct()
	{
		$this->_chunk = isset($_REQUEST["chunk"])
				? intval($_REQUEST["chunk"])
				: 0;
		$this->_chunks = isset($_REQUEST["chunks"])
				? intval($_REQUEST["chunks"])
				: 0;
		$this->_file_type = isset($_SERVER['HTTP_X_FILE_TYPE'])
				? $_SERVER['HTTP_X_FILE_TYPE']
				: '';
		// attempt to get file name from $_REQUEST, otherwise look for X_FILE_NAME header
		$name = isset($_REQUEST["name"])
				? $_REQUEST["name"]
				: (isset($_SERVER['HTTP_X_FILE_NAME'])
						? $_SERVER['HTTP_X_FILE_NAME']
						: 'file');
		// clean the filename for security
		$this->_name = preg_replace('/[^\w\._]+/', '_', $name);
		// attempt to get the content-type from $_SERVER array
		$this->_content_type = (isset($_SERVER["HTTP_CONTENT_TYPE"])) ? $_SERVER["HTTP_CONTENT_TYPE"]: '';
		
		if ($this->_content_type==''){
			$this->_content_type=(isset($_SERVER["CONTENT_TYPE"])) ? $_SERVER["CONTENT_TYPE"]: '';
		}
	}

	/**
	 * Returns the original filename (from client machine)
	 * @return string file name 
	 */
	public function get_name()
	{
		return $this->_name;
	}

	/**
	 * Returns the content-type of this request
	 * @return string 
	 */
	public function get_content_type()
	{
		return $this->_content_type;
	}

	/**
	 * Returns the file's mime-type
	 * @return string mime-type
	 */
	public function get_file_type()
	{
		return $this->_file_type;
	}

	/**
	 * Returns TRUE if chunking is enabled for this request
	 * @return boolean TRUE if chunking is enabled, otherwise FALSE
	 */
	public function is_chunking_enabled()
	{
		return ($this->_chunks < 2);
	}

	/**
	 * Returns TRUE if this request includes the first chunk.
	 * @return boolean TRUE if first chunk, otherwise FALSE
	 */
	public function is_first_chunk()
	{
		return ($this->_chunk == 0);
	}

	/**
	 * Returns TRUE if this request includes the final chunk.
	 * @return boolean TRUE if final chunk, otherwise FALSE
	 */
	public function is_last_chunk()
	{
		return ($this->_chunks == 0 OR ($this->_chunk == ($this->_chunks - 1)));
	}

	/**
	 * Returns TRUE if request content-type is multipart
	 * @return boolean TRUE if multipart, otherwise FALSE
	 */
	public function is_multipart()
	{
		return (strpos($this->_content_type, "multipart") !== false);
	}

}

/* End of file Chunked_uploader.php */
/* Location: /sparks/chunked_uploader/0.0.1/libraries/Chunked_uploader.php */