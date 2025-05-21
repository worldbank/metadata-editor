<?php
//enable datadeposit
$config['datadeposit']['enable_datadeposit']=true;

//Show metadata sections expanded or collapsed?
$config['datadeposit']['sections_collapsed']=true;

//Show help text for form fields?
$config['datadeposit']['show_help']=false;

$config['datadeposit']['mandatory_fields'] = array(
	'ident_title',
	'coll_dates',
	'coverage_country'
);

$config['datadeposit']['mandatory_fields2'] = array(
	'Title',
	'Dates of Data Collection',
	'Country',
);

$config['datadeposit']['merged'] = array_combine($config['datadeposit']['mandatory_fields'], $config['datadeposit']['mandatory_fields2']);

$config['datadeposit']['recommended_fields'] = array(
	'overview_abstract',
	'coverage_geo',
	'prim_investigator',
	'funding',
	'impact_wb_name',
	'impact_wb_id'
);

$config['datadeposit']['recommended_fields2'] = array(
	'Abstract',
	'Geographical Coverage',
	'Primary Investigator',
	'Funding',
	'IE Project Name',
	'IE Project ID'
);


/**
 * 
 * Data access options
 * 
 * 
 */
$config['datadeposit']['access_policy_options']=array(
	''					=>	'--',
	'Direct Access'		=>	'Direct Access',
	'Public Use Files'	=>	'Public Use Files',
	'Licensed Access'	=>	'Licensed Access',
	'Data Enclave'		=>	'Data Enclave',
	'Not Defined'		=>	'Not Defined'
);



/**
 * 
 * Catalog publishing options
 * 
 */
$config['datadeposit']['to_catalog_options']=array(
	''					=>	'--',
	'Internal'			=>	'Internal',
	'External'			=>	'External',
);

/**
 * 
 *  To disable catalog publishing options, set the options to false
 * 
 */
//$config['datadeposit']['to_catalog_options']=false;


/**
 * 
 * Study types
 * 
 */
$config['datadeposit']['study_types']=array(
	''=>'--',
	"1-2-3 Survey, phase 1 [hh/123-1]" => "1-2-3 Survey, phase 1 [hh/123-1]",
	"1-2-3 Survey, phase 2 [hh/123-2]" => "1-2-3 Survey, phase 2 [hh/123-2]",
	"1-2-3 Survey, phase 3 [hh/123-3]" => "1-2-3 Survey, phase 3 [hh/123-3]",
	"Administrative Records, Health (ad/hea]" => "Administrative Records, Health (ad/hea]",
	"Administrative Records, Education (ad/edu]" => "Administrative Records, Education (ad/edu]",
	"Administrative Records, Other (ad/oth]" => "Administrative Records, Other (ad/oth]",
	"Agricultural Census [ag/census]" => "Agricultural Census [ag/census]",
	"Agricultural Survey [ag/oth]" => "Agricultural Survey [ag/oth]",
	"Child Labor Survey [hh/cls]" => "Child Labor Survey [hh/cls]",
	"Core Welfare Indicators Questionnaire [hh/cwiq]" => "Core Welfare Indicators Questionnaire [hh/cwiq]",
	"Demographic and Health Survey [hh/dhs]" => "Demographic and Health Survey [hh/dhs]",
	"Demographic and Health Survey, Round 1 [hh/dhs-1]" => "Demographic and Health Survey, Round 1 [hh/dhs-1]",
	"Demographic and Health Survey, Round 2 [hh/dhs-2]" => "Demographic and Health Survey, Round 2 [hh/dhs-2]",
	"Demographic and Health Survey, Round 3  [hh/dhs-3]" => "Demographic and Health Survey, Round 3  [hh/dhs-3]",
	"Demographic and Health Survey, Round 4 [hh/dhs-4]" => "Demographic and Health Survey, Round 4 [hh/dhs-4]",
	"Demographic and Health Survey, Interim [hh/dhs-int]" => "Demographic and Health Survey, Interim [hh/dhs-int]",
	"Demographic and Health Survey, Special [hh/dhs-sp]" => "Demographic and Health Survey, Special [hh/dhs-sp]",
	"Enterprise Survey [en/oth]" => "Enterprise Survey [en/oth]",
	"Enterprise Census [en/census]" => "Enterprise Census [en/census]",
	"Income/Expenditure/Household Survey [hh/ies]" => "Income/Expenditure/Household Survey [hh/ies]",
	"Informal Sector Survey [hh/iss]" => "Informal Sector Survey [hh/iss]",
	"Integrated Survey (non-LSMS) [hh/is]" => "Integrated Survey (non-LSMS) [hh/is]",
	"Multiple Indicator Cluster Survey - Round 1 [hh/mics-1]" => "Multiple Indicator Cluster Survey - Round 1 [hh/mics-1]",
	"Multiple Indicator Cluster Survey - Round 2 [hh/mics-2]" => "Multiple Indicator Cluster Survey - Round 2 [hh/mics-2]",
	"Multiple Indicator Cluster Survey - Round 3 [hh/mics-3]" => "Multiple Indicator Cluster Survey - Round 3 [hh/mics-3]",
	"Labor Force Survey [hh/lfs]" => "Labor Force Survey [hh/lfs]",
	"Living Standards Measurement Study [hh/lsms]" => "Living Standards Measurement Study [hh/lsms]",
	"Other Household Health Survey [hh/hea]" => "Other Household Health Survey [hh/hea]",
	"Other Household Survey [hh/oth]" => "Other Household Survey [hh/oth]",
	"Price Survey [hh/prc]" => "Price Survey [hh/prc]",
	"Priority Survey (hh/ps]" => "Priority Survey (hh/ps]",
	"Population and Housing Census [hh/popcen]" => "Population and Housing Census [hh/popcen]",
	"Sample Frame, Households [sf/hh]" => "Sample Frame, Households [sf/hh]",
	"Sample Frame, Enterprises [sf/en]" => "Sample Frame, Enterprises [sf/en]",
	"Service Provision Assessments [hh/spa]" => "Service Provision Assessments [hh/spa]",
	"Socio-Economic/Monitoring Survey [hh/sems]" => "Socio-Economic/Monitoring Survey [hh/sems]",
	"Statistical Info. &amp; Monitoring Prog. [hh/simpoc]" => "Statistical Info. &amp; Monitoring Prog. [hh/simpoc]",
	"World Fertility Survey [hh/wfs]" => "World Fertility Survey [hh/wfs]",
	"World Health Survey [hh/whs]" => "World Health Survey [hh/whs]"
);


//folder for storing project files
$config['datadeposit']['resources'] = 'datafiles';

$config['datadeposit']['recommended'] = array_combine($config['datadeposit']['recommended_fields'], $config['datadeposit']['recommended_fields2']);

//enable/disable operational_information and impact evaluation fields
$config['datadeposit']['additional_fields']=true;

