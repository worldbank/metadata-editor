<?php

require_once 'application/libraries/Auth/AuthInterface.php';
require_once 'application/libraries/Auth/DefaultAuth.php';

class SsoAuth extends DefaultAuth implements AuthInterface {

    function __construct()
    {
        parent::__construct($skip_auth=TRUE);
    }

    //add functions to override the default methods in DefaultAuth


}//end-class
