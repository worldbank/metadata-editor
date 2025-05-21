<?php


/**
 * 
 * Editor files
 * 
 */
class Editor_files_model extends ci_model {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Editor_model');
    }

	/**
	 * 
	 * Get all files for a project
	 * 
	 * @param int $sid
	 * @return array
	 * 
	 */
	public function get_files($sid)
	{
		$project_folder = $this->Editor_model->get_project_folder($sid);
		$folder_size=$this->dir_size($project_folder);
		$result =array(
			'folder_info'=>array(
				'folder_path'=> basename($project_folder),
				'folder_size'=>$folder_size,
				'folder_size_human'=>$this->file_size_to_human($folder_size)
			),
			'files'=>$this->get_dir_recursive($project_folder)
		);		
		return $result;
	}


	/**
	 * 
	 * Delete a file by path
	 * 
	 * 
	 */
	public function delete_by_path($sid, $file_path)
	{
		$project_folder = $this->Editor_model->get_project_folder($sid);
		$full_path = $project_folder . DIRECTORY_SEPARATOR . $file_path;
		//convert to absolute path
		$full_path = realpath($full_path);
		$project_folder = realpath($project_folder);

		if (!$full_path) {
			throw new Exception("Invalid file_path") ;
		}

		//if the file path is not in the project folder, throw an exception
		if (strpos($full_path, $project_folder) !== 0) {
			throw new Exception("Invalid file_path" . $full_path) ;
		}

		//if the file does not exist, throw an exception
		if (!file_exists($full_path)) {
			throw new Exception("File not found");
		}

		//if the file is a directory, throw an exception
		if (is_dir($full_path)) {
			//delete folder recursively
			return delete_files($full_path, $del_dir = true);
		} else {
			//delete file
			return unlink($full_path);
		}

	}



	/**
	 * 
	 * Get all files for a given folder path
	 * 
	 * @param int $sid
	 * @return array
	 * 
	 */
	function get_dir_recursive($path, $make_relative = true) 
	{
		$result = [];
		$path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		$directoryIterator = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
		$iterator = new RecursiveIteratorIterator($directoryIterator, RecursiveIteratorIterator::SELF_FIRST);

		foreach ($iterator as $fileInfo) {
			$filePath = $fileInfo->getPathname();
			if ($make_relative) {
				$filePath = str_replace($path, '', $filePath);
			}
	
			$result[] = [
				'dir_path' => dirname($filePath),
				'name' => $fileInfo->getFilename(),
				'size' => $fileInfo->getSize(),
				'size_human' => $this->file_size_to_human($fileInfo->getSize()),
				'is_dir' => $fileInfo->isDir(),
				'timestamp' => date("c",$fileInfo->getMTime())
			];
		}

		return $result;
	}



	/**
	 * 
	 * Get the size of a directory
	 * 
	 * @param string $path
	 * @return int
	 * 
	 */
	function dir_size($path) 
	{
		$size = 0;
		$directoryIterator = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
		$iterator = new RecursiveIteratorIterator($directoryIterator, RecursiveIteratorIterator::SELF_FIRST);

		foreach ($iterator as $fileInfo) {
			$size += $fileInfo->getSize();
		}

		return $size;
	}


	/**
	 * 
	 * Convert file size to human readable format
	 * 
	 * @param int $size
	 */
	function file_size_to_human($size) 
	{
		$units = ['B', 'KB', 'MB', 'GB', 'TB'];
		$unit = 0;

		while ($size > 1024) {
			$size /= 1024;
			$unit++;
		}

		return round($size, 2) . ' ' . $units[$unit];
	}
	
	

}    