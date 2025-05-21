<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter XSL Transform helper
 *
 * @package		CodeIgniter
 * @subpackage	Helpers
 * @category	Helpers
 * @author		International household surveys network
 *
 */

// ------------------------------------------------------------------------

/**
 * Element
 *
 * Transforms the XML and returns the output as XML,TEXT, or HTML
 *
 * @access	public
 * @param	string
 * @param	string
 * @param	array
 * @param	string
 * @return	string
 */
if ( ! function_exists('xsl_transform'))
{
function xsl_transform($xml,$xslt,$parameters, $format="html") {
	//test xsl extension is loaded
	if(phpversion() >= 5) {
		$extension='xsl';
	}
	else{
		$extension='xslt';
	}

	if (!extension_loaded($extension) ){
		echo '<br/><div style="background:beige;color:red;padding:5px">';
		echo '<strong>Fatal error:</strong> '.$extension.' extension was not loaded. Please check the php configurations and enable the extension.';
		echo '<br><br>Error source: xslTransform';
		echo '</div><br/>';
		exit;
	}

	if(isset($xml)) {
		if(isset($xslt)) {
			// Check php version
			if(phpversion() >= 5) {
					// use libxslt
					// load the xml file and stylesheet as domdocuments
					$xmlDom = new DomDocument(); //new DOMDocument('1.0','UTF-8');
                    if (@is_file($xml) ) {  //load xml file
                        $xmlDom->load($xml);
                    }
                    else{ //load xml string
					    $xmlDom->loadXML($xml);
                    }
                      //print $xmlDom->saveXML();

					 //load xslt file or string
					$xslDom = new DomDocument();//new DOMDocument('1.0','UTF-8');
					if ( is_file($xslt) )  {
						$xslDom->load($xslt);
					}
					else {
						$xslDom->loadXML($xslt);
					}

					// create the processor and import the stylesheet
					$proc = new XsltProcessor();
					$proc->importStylesheet($xslDom);
					//$proc->registerPhpFunctions();
          if (isset($parameters) ){
    					foreach($parameters as $key => $value) $proc->setParameter(null, $key, $value);
          }

					//transform and output the xml document
					$outputDom = @$proc->transformToDoc($xmlDom);
					if ($format=='xml') {
						$output = $outputDom->saveXML();
					}
					else
					{
						$output = $outputDom->saveHTML();
					}

			}
			else {// <php5
				// use sablotron
				$xh = xslt_create();
				// check if file path or xml content is passed
				if (is_file($xml)){//xml file path
					$output = xslt_process($xh,
						'file://'.$xml,
						'file://'.$xslt,
						NULL, NULL, $parameters);
				}
				else{//xml content
					$xh = xslt_create();
						$args['/_xml']=$xml;

					/*echo "<pre>";
					print_r($parameters);
					echo "</pre>";*/
					$output  = xslt_process($xh,
								'arg:/_xml','file://'.$xslt,
								NULL,
								$args,$parameters);
				}
				if (!$output) {
				   $output = "XML Error ".xslt_errno($xh).": ". xslt_error($xh);
				   var_dump($output);
				}
				xslt_free($xh);
			}
		}
		else {
			$output="<div>ERROR: Transform $xslt not found.</div>";
		}
	}
	else {
		$output="<div>ERROR: XML file $xml not found.</div>";
	}
	return($output);
}
}

/* End of file xslt_helper.php */
/* Location: ./application/helpers/xslt_helper.php */
