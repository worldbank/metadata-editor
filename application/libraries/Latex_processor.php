<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * 
 * 
 * LaTeX Processor Library
 * 
 * Handles LaTeX detection, conversion to images, and processing for PDF generation
 *
 */
class Latex_processor {
	
	var $ci;
	
    //constructor
	function __construct($params=NULL)
	{
		$this->ci =& get_instance();
    }

	/**
	 * 
	 * Check if metadata includes LaTeX formulas and return element paths
	 * 
	 * @param array $metadata - The metadata array to check
	 * @param string $current_path - Current path for recursive calls
	 * @return array - Array of element paths containing LaTeX
	 */
	function check_latex_in_metadata($metadata, $current_path = '')
	{
		$latex_paths = array();
		
		if (!is_array($metadata)) {
			return $latex_paths;
		}
		
		foreach ($metadata as $key => $value) {
			$element_path = $current_path ? $current_path . '/' . $key : $key;
			
			if (is_array($value)) {
				// Recursively check nested arrays
				$nested_paths = $this->check_latex_in_metadata($value, $element_path);
				$latex_paths = array_merge($latex_paths, $nested_paths);
			} else if (is_string($value)) {
				// Check if string contains LaTeX delimiters
				if ($this->contains_latex($value)) {
					$latex_paths[] = $element_path;
				}
			}
		}
		
		return $latex_paths;
	}
	
	/**
	 * 
	 * Check if a string contains LaTeX formulas
	 * 
	 * @param string $text - The text to check
	 * @return boolean - True if LaTeX is found
	 */
	private function contains_latex($text)
	{
		// Common LaTeX delimiters used in MathJax
		$latex_patterns = array(
			'/\$\$.*?\$\$/',           // Display math: $$...$$
			'/\$.*?\$/',               // Inline math: $...$
			'/\\\\\(.*?\\\\\)/',       // Inline math: \(...\)
			'/\\\\\[.*?\\\\\]/',       // Display math: \[...\]
			'/\\\\begin\{.*?\}.*?\\\\end\{.*?\}/s', // LaTeX environments
			'/\\\\[a-zA-Z]+\{.*?\}/',  // LaTeX commands with braces
			'/\\\\[a-zA-Z]+/',         // LaTeX commands without braces
		);
		
		foreach ($latex_patterns as $pattern) {
			if (preg_match($pattern, $text)) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * 
	 * Get LaTeX elements for timeseries projects
	 * 
	 * @param array $project_metadata - Project metadata array
	 * @return array - Array of element paths containing LaTeX
	 */
	function get_latex_elements($project_metadata)
	{
		if (!is_array($project_metadata)) {
			return array();
		}
		
		return $this->check_latex_in_metadata($project_metadata);
	}

	/**
	 * 
	 * Process LaTeX content in metadata and convert to images
	 * 
	 * @param array &$metadata - Reference to metadata array
	 */
	function process_latex_content(&$metadata)
	{
		// Check if MathJax processing is enabled
		if (!$this->ci->config->item('mathjax_enabled', 'editor')) {
			return;
		}
		
		if (!is_array($metadata)) {
			return;
		}
		
		foreach ($metadata as $key => &$value) {
			if (is_array($value)) {
				$this->process_latex_content($value);
			} else if (is_string($value)) {
				if ($this->contains_latex($value)) {
					$value = $this->convert_latex_to_images($value);
				}
			}
		}
	}
	
	/**
	 * 
	 * Convert LaTeX expressions to images using MathJax server
	 * 
	 * @param string $text - Text containing LaTeX
	 * @return string - Text with LaTeX replaced by image tags
	 */
	private function convert_latex_to_images($text)
	{
		// API support converting latex for a whole text with multiple expressions
		$mathjax_api_url = $this->ci->config->item('mathjax_api_url', 'editor') ?: 'http://localhost:3000/';
		$api_url = $mathjax_api_url . 'process-text';
		$request_data = array(
			'text' => $text,
			'scale' => 3,
			'includeFormattedText' => true,
			'includeSvg' => false,
			'inlineImages' => true,//images are inline using base64 encoding
			'output_dir' => FCPATH . 'datafiles/tmp/mathjax/'
		);

		// Make request to MathJax server
		$response = $this->call_mathjax_request_process_text($request_data);

		if ($response && isset($response['success']) && $response['success']) {
			$formatted_text = $response['formattedText'];

			//extract images from formatted text
			//$images = $this->extract_images_from_formatted_text($formatted_text);
			
			// Download images and update paths for PDF generation
			//$formatted_text = $this->download_and_update_image_paths($formatted_text, $images);
			
			return $formatted_text;
		}
		
		// Return original text if conversion fails
		return $text;
	}

	private function extract_images_from_formatted_text($formatted_text)
	{
		//extract images from formatted text
		$images = [];
		$pattern = '/<img[^>]+src="([^"]+)"[^>]*>/i';
		preg_match_all($pattern, $formatted_text, $matches);
		return $matches[1];
	}


	private function call_mathjax_request_process_text($request_data)
	{
		// Configuration for MathJax server
		$mathjax_api_url = $this->ci->config->item('mathjax_api_url', 'editor') ?: 'http://localhost:3000/';
		$api_url = $mathjax_api_url . 'process-text';
		
		// Create output directory if it doesn't exist
		if (isset($request_data['output_dir']) && !is_dir($request_data['output_dir'])) {
			mkdir($request_data['output_dir'], 0755, true);
		}
		
		try {
			// Create Guzzle client
			$client = new Client([
				'timeout' => 30,
				'base_uri' => $mathjax_api_url
			]);
			
			// Make HTTP request to MathJax server
			$api_response = $client->request('POST', '/process-text', [
				'json' => $request_data,
				'headers' => [
					'Content-Type' => 'application/json'
				]
			]);
			
			// Check HTTP response code
			if ($api_response->getStatusCode() === 200) {
				$response = json_decode($api_response->getBody()->getContents(), true);
				if ($response === null) {
					log_message('error', 'MathJax API JSON decode error: ' . json_last_error_msg());
					return false;
				}
				return $response;
			} else {
				log_message('error', 'MathJax API HTTP error: ' . $api_response->getStatusCode() . ' - Response: ' . $api_response->getBody()->getContents());
				return false;
			}
			
		} catch (RequestException $e) {
			log_message('error', 'MathJax API Guzzle error: ' . $e->getMessage());
			return false;
		} catch (Exception $e) {
			log_message('error', 'MathJax API error: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * 
	 * Download images from MathJax server and update HTML paths for PDF generation
	 * 
	 * @param string $formatted_text - HTML text with image tags
	 * @param array $images - Array of image metadata from MathJax API
	 * @return string - Updated HTML text with local image paths
	 */
	private function download_and_update_image_paths($formatted_text, $images)
	{
		//download images
		foreach($images as $image){
			$this->download_image($image, FCPATH . 'datafiles/tmp/mathjax/' . basename($image));
		}

		//update image paths in formatted text
		foreach($images as $image){
			$formatted_text = str_replace($image, FCPATH . 'datafiles/tmp/mathjax/' . basename($image), $formatted_text);
		}

		return $formatted_text;
	}
	
	/**
	 * 
	 * Download an image from a URL to a local file
	 * 
	 * @param string $url - Remote image URL
	 * @param string $local_path - Local file path to save the image
	 * @return boolean - True if download successful, false otherwise
	 */
	private function download_image($url, $local_path)
	{
		try {
			// Create Guzzle client
			$client = new Client([
				'timeout' => 30,
				'verify' => false, // Disable SSL verification for compatibility
				'allow_redirects' => true
			]);
			
			// Download the image
			$response = $client->request('GET', $url);
			
			if ($response->getStatusCode() === 200) {
				$image_data = $response->getBody()->getContents();
				
				// Save the image to local file
				$result = file_put_contents($local_path, $image_data);
				if ($result === false) {
					log_message('error', 'Failed to save image to: ' . $local_path);
					return false;
				}
				
				return true;
			} else {
				log_message('error', 'Image download failed: ' . $url . ' - HTTP: ' . $response->getStatusCode());
				return false;
			}
			
		} catch (RequestException $e) {
			log_message('error', 'Image download failed: ' . $url . ' - Error: ' . $e->getMessage());
			return false;
		} catch (Exception $e) {
			log_message('error', 'Image download failed: ' . $url . ' - Error: ' . $e->getMessage());
			return false;
		}
	}

	

}// END Latex_processor Class

/* End of file Latex_processor.php */
/* Location: ./application/libraries/Latex_processor.php */ 