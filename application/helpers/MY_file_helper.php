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
 * is_deletable_filename
 *
 * True when a basename is safe to pass to file deletion (not empty, not a path segment).
 */
if ( ! function_exists('is_deletable_filename'))
{
	function is_deletable_filename($filename)
	{
		$filename = trim((string) $filename);

		if ($filename === '' || $filename === '.' || $filename === '..') {
			return false;
		}

		if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
			return false;
		}

		if (strpos($filename, "\0") !== false) {
			return false;
		}

		return true;
	}
}


/**
 * safe_unlink_file
 *
 * Delete a regular file only. Never deletes directories, symlinks to directories, or empty paths.
 *
 * @return bool True when a file was deleted, false when skipped or failed
 */
if ( ! function_exists('safe_unlink_file'))
{
	function safe_unlink_file($file_path)
	{
		if ($file_path === null || trim((string) $file_path) === '') {
			return false;
		}

		if (!file_exists($file_path)) {
			return false;
		}

		if (is_dir($file_path)) {
			log_message('error', 'safe_unlink_file: refused to delete directory: ' . $file_path);
			return false;
		}

		$resolved = realpath($file_path);
		if ($resolved === false) {
			return false;
		}

		if (is_dir($resolved)) {
			log_message('error', 'safe_unlink_file: refused to delete directory: ' . $resolved);
			return false;
		}

		if (!is_file($resolved)) {
			log_message('error', 'safe_unlink_file: not a regular file: ' . $resolved);
			return false;
		}

		if (!is_deletable_filename(basename($resolved))) {
			return false;
		}

		return @unlink($resolved);
	}
}


/**
 * silent_unlink
 *
 * Deletes a file silently without throwing warnings. Delegates to safe_unlink_file.
 */
if ( ! function_exists('silent_unlink'))
{
	function silent_unlink($file_path)
	{
		return safe_unlink_file($file_path);
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
	   
		$bytes = max((float)$bytes, 0); 
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
 * Sanitize an uploaded data file name for safe storage.
 *
 * Shared by chunked uploads (Resumable_upload) and ZIP extraction
 * (Microdata_zip_processor) so both produce the same names:
 * accented letters become plain ASCII (é -> e, ß -> ss), other characters
 * outside [A-Za-z0-9._-] become "_", repeated "_" collapse, dots in the
 * name part become "_", and leading/trailing "_" are trimmed.
 * e.g. "7.1 base+_numérica.csv" -> "7_1_base_numerica.csv"
 *
 * @param string $filename Client or archive entry file name
 * @param int    $maxLength Maximum length of the result
 * @return string
 * @throws Exception when nothing usable remains of the name (e.g. "+++.csv")
 */
function sanitize_data_filename(string $filename, int $maxLength = 255): string
{
	$original = $filename;
	$filename = basename(str_replace('\\', '/', $filename));

	// Remove any null bytes
	$filename = str_replace("\0", '', $filename);

	// Accented letters -> plain ASCII before non-ASCII is replaced below
	$filename = filename_to_ascii($filename);

	// CodeIgniter's filename filter (when running inside the framework)
	if (function_exists('get_instance')) {
		$CI =& get_instance();
		if ($CI && isset($CI->security)) {
			$filename = $CI->security->sanitize_filename($filename);
		}
	}

	// Allow only alphanumeric, dots, hyphens, and underscores
	$filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
	$filename = preg_replace('/_+/', '_', $filename);
	$filename = trim($filename, '_');

	// Replace dots with underscores in the name part only (consistent with resources model)
	$extension = pathinfo($filename, PATHINFO_EXTENSION);
	$name = pathinfo($filename, PATHINFO_FILENAME);
	$name = str_replace('.', '_', $name);
	$name = preg_replace('/_+/', '_', $name);
	$name = trim($name, '_');

	if ($name === '') {
		throw new Exception(
			'File name "' . basename(str_replace('\\', '/', $original)) . '" has no usable characters; '
			. 'rename the file using letters, numbers, hyphens or underscores'
		);
	}

	$filename = $extension === '' ? $name : $name . '.' . $extension;

	if (strlen($filename) > $maxLength) {
		$name = $extension === ''
			? substr($name, 0, $maxLength)
			: substr($name, 0, $maxLength - strlen($extension) - 1);
		$filename = $extension === '' ? $name : $name . '.' . $extension;
	}

	return $filename;
}

/**
 * Convert accented and other Latin letters in a file name to plain ASCII
 * (é -> e, ñ -> n, ß -> ss, ø -> o). Characters with no ASCII equivalent are
 * left for the caller to replace.
 *
 * Uses intl (Transliterator / Normalizer) when available, with a built-in map
 * of common Latin letters as fallback. Handles both precomposed (NFC) and
 * decomposed (NFD, e.g. macOS ZIP entry names) input. Byte strings that are not
 * valid UTF-8 (e.g. CP437 ZIP names) are returned unchanged.
 *
 * @param string $name
 * @param bool   $use_intl Use the intl extension when available (false: built-in map only; for tests)
 * @return string
 */
function filename_to_ascii(string $name, bool $use_intl = true): string
{
	// Pure ASCII or not valid UTF-8: nothing to convert safely
	if (!preg_match('/[\x80-\xFF]/', $name) || preg_match('//u', $name) !== 1) {
		return $name;
	}

	if ($use_intl && class_exists('Transliterator')) {
		static $transliterator = null;
		if ($transliterator === null) {
			$transliterator = Transliterator::create('Any-Latin; Latin-ASCII') ?: false;
		}
		if ($transliterator) {
			$converted = $transliterator->transliterate($name);
			if (is_string($converted)) {
				return $converted;
			}
		}
	}

	// Decompose and drop combining marks (é -> e + ́ -> e)
	if ($use_intl && class_exists('Normalizer')) {
		$decomposed = Normalizer::normalize($name, Normalizer::FORM_D);
		if (is_string($decomposed)) {
			$name = preg_replace('/\p{Mn}+/u', '', $decomposed);
		}
	} else {
		// Without intl, decomposed input still needs its combining marks removed
		$name = preg_replace('/[\x{0300}-\x{036F}]+/u', '', $name);
	}

	static $map = array(
		'À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A','Ā'=>'A','Ă'=>'A','Ą'=>'A',
		'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','ā'=>'a','ă'=>'a','ą'=>'a',
		'Ç'=>'C','Ć'=>'C','Č'=>'C','ç'=>'c','ć'=>'c','č'=>'c',
		'Ď'=>'D','Đ'=>'D','Ð'=>'D','ď'=>'d','đ'=>'d','ð'=>'d',
		'È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E','Ē'=>'E','Ę'=>'E','Ě'=>'E',
		'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ē'=>'e','ę'=>'e','ě'=>'e',
		'Ğ'=>'G','ğ'=>'g',
		'Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I','Ī'=>'I','İ'=>'I',
		'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ī'=>'i','ı'=>'i',
		'Ł'=>'L','ł'=>'l',
		'Ñ'=>'N','Ń'=>'N','Ň'=>'N','ñ'=>'n','ń'=>'n','ň'=>'n',
		'Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','Ø'=>'O','Ō'=>'O','Ő'=>'O',
		'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o','ō'=>'o','ő'=>'o',
		'Ř'=>'R','ř'=>'r',
		'Ś'=>'S','Š'=>'S','Ş'=>'S','ś'=>'s','š'=>'s','ş'=>'s','ß'=>'ss',
		'Ť'=>'T','Ţ'=>'T','ť'=>'t','ţ'=>'t','Þ'=>'Th','þ'=>'th',
		'Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U','Ū'=>'U','Ů'=>'U','Ű'=>'U',
		'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ū'=>'u','ů'=>'u','ű'=>'u',
		'Ý'=>'Y','Ÿ'=>'Y','ý'=>'y','ÿ'=>'y',
		'Ź'=>'Z','Ż'=>'Z','Ž'=>'Z','ź'=>'z','ż'=>'z','ž'=>'z',
		'Æ'=>'AE','æ'=>'ae','Œ'=>'OE','œ'=>'oe',
	);
	return strtr($name, $map);
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
    if ($filename === '' || $filename === '.' || $filename === '..') {
        throw new Exception('File name cannot be empty, "." or ".."');
    }

    // No hidden files / names without a base name (e.g. ".csv")
    if ($filename[0] === '.') {
        throw new Exception('File name cannot start with a dot');
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