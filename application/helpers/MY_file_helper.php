<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * File Helpers
 *
 * @package		CodeIgniter
 * @subpackage	Helpers
 * @category	Helpers
 * @author		IHSN
 * @link		
 */

// ------------------------------------------------------------------------

/**
 * unix_path
 *
 * Converts the forward slashes in file path to back slashs and remove double slashes
 *
 * @access	public
 * @param	string	path to the file
 * @return	string
 */	
if ( ! function_exists('unix_path'))
{
	function unix_path($file_path)
	{
		$is_network_share=FALSE;
		
		if (substr($file_path,0,2)=='//' || substr($file_path,0,2)=='\\')
		{
			$is_network_share=TRUE;
		}
		
		$file_path=str_replace('\\','/',$file_path);
		$file_path=str_replace('//','/',$file_path);
		
		if ($is_network_share)
		{
			$file_path='/'.$file_path;
		}
		return $file_path;
	}
}


/**
 * unix_realpath
 *
 * Converts the forward slashes in file path to back slashs and remove double slashes
 *
 * @access	public
 * @param	string	file path
 * @return	string
 */	
if ( ! function_exists('unix_realpath'))
{
	function unix_realpath($file_path)
	{
		$file_path=unix_path($file_path);
		return unix_path(realpath($file_path));
	}
}


/**
*
* Return filename from file path
*
*/
if ( ! function_exists('get_filename'))
{
	function get_filename($file_path)
	{
		$file_path=str_replace('\\','/',$file_path);
		$file_path=str_replace('//','/',$file_path);
		
		$arr=explode('/',$file_path);
		
		return end($arr);
	}
}

if ( ! function_exists('is_url'))
{
	function is_url($str)
	{
		$str=trim($str);
		if (strpos($str,'http:')!==FALSE || strpos($str,'https:')!==FALSE || strpos($str,'ftp:')!==FALSE )
		{
			return TRUE;
		}
		return FALSE;
	}
}
/**
 * silent_unlink
 *
 * Deletes a file silently without throwing any warnings if the file was not found
 *
 * @access	public
 * @param	string	the language line
 * @param	string	the id of the form element
 * @return	string
 */	
if ( ! function_exists('silent_unlink'))
{
	function silent_unlink($file_path)
	{
		$error_reporting=error_reporting();
		error_reporting(E_ERROR);
		return unlink($file_path);
		error_reporting($error_reporting);
	}
}


/**
* Convert file size into human readable format
*
* @author: nak5ive at DONT-SPAM-ME dot gmail dot com
* @link: http://php.net/manual/en/function.filesize.php
*/ 
if ( ! function_exists('format_bytes'))
{
	function format_bytes($bytes, $precision = 2) 
	{ 
		$units = array('B', 'KB', 'MB', 'GB', 'TB'); 
	   
		$bytes = max($bytes, 0); 
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
		$pow = min($pow, count($units) - 1); 
	   
		$bytes /= pow(1024, $pow); 
	   
		return round($bytes, $precision) . ' ' . $units[$pow]; 
	}
}

if ( ! function_exists('get_file_extension'))
{
	function get_file_extension($filename) 
	{ 
		$parts = explode('.',$filename);
		if (!is_array($parts) || count($parts)==1)
		{
			return FALSE;
		}
		return $parts[count($parts)-1];	   
	}
}


/**
 * 
 *  Return file extension info array 
 * 
 *  @output
 * 	- ext - file ext
 *  - link_type - download | view
 * 
 */
if ( ! function_exists('get_file_extension_info'))
{
	function get_file_extension_info($ext) 
	{ 
		$link_type='download';

		switch($ext){
			case 'html':
			case 'aspx':			
			case 'php':
				$link_type='view';
			break;
		}

		return array(
			'ext'=>$ext,
			'link_type'=>$link_type
		);

	}
}



if ( ! function_exists('get_file_icon'))
{
	function get_file_icon($ext)
	{
		switch ($ext)
		{
			case 'pdf':
				return 'images/acrobat.png';
				break;
				
			case 'xls':
			case 'xlsx':
				return 'images/page_white_excel.png';
				break;
				
			case 'doc':
			case 'docx':
				return 'images/page_white_word.png';
				break;
						
			case 'txt':
				return 'images/page_white_text.png';
				break;
				
			case 'zip':
				return 'images/page_white_compressed.png';
				
			default:
				return 'images/page_white.png';
		}
	}
}

/**
 * Remove folder and subfolders recursively
 *
 * Author: holger1 at NOSPAMzentralplan dot de
 * Link: http://www.php.net/manual/en/function.rmdir.php#98622
 *
 * Note: not tested and used
 */	
if ( ! function_exists('remove_folder'))
{
	function remove_folder($dir) 
	{ 
		   if (is_dir($dir)) 
		   { 
				 $objects = scandir($dir); 
				 foreach ($objects as $object) 
				 { 
					   if ($object != "." && $object != "..")
					   { 
						 if (filetype($dir."/".$object) == "dir") remove_folder($dir."/".$object); else unlink($dir."/".$object); 
					   } 
				 } 
				 reset($objects); 
				 rmdir($dir); 
		   } 
	 } 
}


/**
*
* Return an array of files and folders
* 
*	@path				folder path
*	@make_relative_to	Make the folder paths relative to a folder
*/	
if ( ! function_exists('get_dir_recursive'))
{
	function get_dir_recursive($path,$make_relative_to=FALSE)
	{	
		$files=array();
		$folders=array();
		
		if ($make_relative_to!==FALSE)
		{
			$make_relative_to=unix_path($make_relative_to);
		}

		$iterator = new RecursiveDirectoryIterator($path);
		foreach (new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST) as  $file)
		{	
			//is a folder
			if (!$file->isFile()) 
			{
				//folder path
				$folder=unix_path($file->getPathname());
				
				if ($make_relative_to!==FALSE)
				{
					$folder=str_replace($make_relative_to,"",$folder);
				}

				$folders[]=$folder;
			}
			else
			{
				//file path
				$file_path=unix_path($file->getPathname());
				
				if ($make_relative_to!==FALSE)
				{
					$file_path=str_replace($make_relative_to,"",$file_path);
				}				
				
				$files[]=$file_path;
			}
		}		
		return array('files'=>$files, 'folders'=>$folders);
	}
}

if ( ! function_exists('get_dir_tree'))
{
	function get_dir_tree($path)
	{	
		$result = array();
		$dir = scandir($path);
		
		foreach ($dir as $key => $value){		 
			if (!in_array($value,array(".","..",".DS_Store"))){
				if (is_dir($path . DIRECTORY_SEPARATOR . $value)){		 
					$result[]=[
					"type"=>"folder",						
					"name"=>$value,
					//"size"=>get_dir_size($path . DIRECTORY_SEPARATOR . $value),
					"items"=>get_dir_tree($path . DIRECTORY_SEPARATOR . $value)
					];				
				}
				else{		 
				$result[]=[
					"type"=>"file",
					"name"=>$value,
					"size"=>filesize($path . DIRECTORY_SEPARATOR . $value)
				];					
				} 		 
			}
		}
		
		return $result;
	}
}	


if ( ! function_exists('get_catalog_root'))
{
	function get_catalog_root()
	{	
		$CI =& get_instance();
		$catalog_root=$CI->config->item("catalog_root");
		
		if(!$catalog_root || trim($catalog_root)==''){
			throw new Exception("CATALOG_ROOT-NOT_SET");
		}
		
		//if not fixed path, use a relative path
		if (!file_exists($catalog_root) ){
			$catalog_root=FCPATH.$catalog_root;
		}

		return $catalog_root;
	}
}


if ( ! function_exists('get_dir_size'))
{
	function get_dir_size($folder_path,$details=FALSE)
	{	
		$size = 0;
		$files=array();
		foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder_path,RecursiveDirectoryIterator::SKIP_DOTS)) as $file){
			$size_=$file->getSize();
			$files[]=array(
				'name'=>$file->getFileName(),
				'size'=>$size_,
				'path'=>str_replace($folder_path,'',$file->getPath()),
			);
			$size+=$size_;
		}

		$output= [
			'size'=>$size,
			'size_mb'=>get_size_to_mb($size),
			'size_formatted'=>format_bytes($size),			
			'path'=>$folder_path
		];

		if($details){
			$output['files']=$files;
		}

		return $output;
	}
}



if ( ! function_exists('get_size_to_mb'))
{
	function get_size_to_mb($size){
		//size in mb
		return round($size/1024/1024,2);
	}
}


if ( ! function_exists('get_zip_archive_list'))
{
	/**
	 * 
	 * 
	 * Return an array of files and folders of a zip archive
	 * 
	 * @ignore - file or folder names or ignore
	 * 
	 */
	function get_zip_archive_list($zipfile_path,$ignore=array())
	{
		$zip = new ZipArchive();
		$zip->open($zipfile_path);

		if (count($ignore)==0){
			$ignore = array( 'MACOSX/', 'MACOSX/._','.DS_Store' );
		}

		$output=array();

		for( $i = 0; $i < $zip->numFiles; $i++ ){
			if (in_array(basename($zip->getNameIndex($i)), $ignore)) {
				continue;
			} else

			if(substr($zip->getNameIndex($i), 0, 9) === "__MACOSX/") {
				continue;
			} else {
				$stat = $zip->statIndex($i);
			}
		
			print_r($stat);
			echo "<HR>";
			print_r(( $stat['name'] ) . PHP_EOL );
			
			$output[$stat['name']]=$stat;
		}

		return $output;		
	}
}

/**
 * Validate a file name against security rules.
 *
 * @param string $filename The filename to validate
 * @param int    $maxLength Maximum length (default 255)
 * @return bool TRUE if valid
 * @throws Exception if validation fails
 */
function validate_filename(string $filename, int $maxLength = 255): bool
{
    // Must not be empty
    if ($filename === '.' || $filename === '..') {
        throw new Exception('File name cannot be empty, "." or ".."');
    }

    // Remove path info (if filename includes directories, reject)
    if ($filename !== basename($filename)) {
        throw new Exception('File name cannot contain directory separators');
    }

    // Check length
    if (strlen($filename) > $maxLength) {
        throw new Exception("File name exceeds maximum length of {$maxLength} characters");
    }

	//check for spaces before and after the filename
	if ($filename !== trim($filename)) {
     	throw new Exception('File name cannot contain leading or trailing spaces');
	}

	// Allow letters, numbers, spaces, underscores, hyphens, dots, parentheses
	if (!preg_match('/^[A-Za-z0-9\s._()\-]+$/', $filename)) {
        throw new Exception('File name contains invalid characters. Only letters, numbers, dots, hyphens, and underscores are allowed');
    }

    return true;
}

// ------------------------------------------------------------------------
/* End of file MY_file_helper.php */
/* Location: ./system/helpers/MY_file_helper.php */