<?php if (!defined('BASEPATH')) exit('No direct script access allowed');


/*
|--------------------------------------------------------------------------
| Which captcha driver to use?
|--------------------------------------------------------------------------
|
| options are: none, image_captcha, recaptcha
*/

$config['captcha_driver']='none';

/*
|--------------------------------------------------------------------------
| Image Captcha options
|--------------------------------------------------------------------------
|
| Note: requires the GD extension
*/

//folder where images will be created. It needs to be an absolute path.
$config['captcha_img_path'] = APPPATH.'/../files/captcha/';

//url path to load captcha images
$config['captcha_img_url'] = base_url().'files/captcha';

//path to the TTF font to use with captcha. 
$config['captcha_font_path'] = APPPATH.'/../modules/captcha/Merienda/Merienda-Bold.ttf';

//image width
$config['captcha_img_width'] = 300;

//image height
$config['captcha_img_height'] = 100;

//captcha expiration time (seconds)
$config['captcha_expiration'] = 7200;



/*
|--------------------------------------------------------------------------
| Google's Recaptcha library options
|--------------------------------------------------------------------------
|
*/
$config['recaptcha']['apiserver']="http://www.google.com/recaptcha/api";
$config['recaptcha']['apisecureserver']="https://www.google.com/recaptcha/api";
$config['recaptcha']['verifyserver']="www.google.com";
$config['recaptcha']['publickey']="";
$config['recaptcha']['privatekey']="";
$config['recaptcha']['language']="en";
$config['recaptcha']['theme']="clean";
//$config['recaptcha']['mailhide']['publickey']="yourpublicemailhidekey";
//$config['recaptcha']['mailhide']['privatekey']="yourprivateemailhidekey";

