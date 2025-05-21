<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * CSRF helper 
 * 
 * Inserts formID and token hidden form fields
 *
 * @access    public
 * @return    string  HTML hidden input elements
 */
function form_token() {
    $CI =& get_instance();
    
    // Form helper and csrf lib should be loaded from the controller
    // $CI->load->helper('form');
    // $CI->load->library('csrf');
    
    // Get the token from the csrf class
    $tokenArray = $CI->csrf->get_token();    
    if(!$tokenArray) {
        // Token is bad. Create a new one
        $tokenArray = $CI->csrf->create_token();    
    }
    
    // Return token hidden form field strings
    $input_formID = form_input(array('name'=>'formid', 'id'=>'formid', 'value'=>$tokenArray['formID'], 'type'=>'hidden'));
    $input_token  = form_input(array('name'=>'token', 'id'=>'token', 'value'=>$tokenArray['token'], 'type'=>'hidden'));
    
    // Visible form fields for testing. Should not be used in production
    // $input_formID = form_input(array('name'=>'formid', 'id'=>'formid', 'value'=>$tokenArray['formID'], 'type'=>'input'));
    // $input_token  = form_input(array('name'=>'token', 'id'=>'token', 'value'=>$tokenArray['token'], 'type'=>'input'));
    
    return "\n $input_formID \n $input_token\n";
}
