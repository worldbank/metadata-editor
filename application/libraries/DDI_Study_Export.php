<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * DDI Study Export Class
 *
 * Class for exporting study data and generating DDI via a template.
 *
 */

class DDI_Study_Export
{	
	private $ci;
	private $_DDI_template = '';
	private $_DDI;

	private $_variables    = array(
		'{ident_id}'                => ' ',
		'{ident_title}'             => ' ',
		'{ident_subtitle}'          => ' ',
		'{ident_abbr}'              => ' ',
		'{ident_trans_title}'       => ' ',
		'{prod_s_investigator}'     => ' ',
		'{prod_s_acknowledgements}' => ' ',
		'{prod_s_other_prod}'       => ' ',
		'{disclaimer_copyright}'    => ' ',
		'{prod_s_funding}'          => ' ',
		'{contacts_contacts}'       => ' ',
		'{ident_ddp_id}'            => ' ',
		'{ident_study_type}'        => ' ',
		'{ident_ser_info}'          => ' ',
		'{ver_prod_date}'           => ' ',
		'{ver_desc}'                => ' ',
		'{overview_methods}'        => ' ',
		'{overview_analysis}'       => ' ',
		'{ver_notes}'               => ' ',
		'{scope_keywords}'          => ' ',
		'{scope_class}'             => ' ',
		'{overview_abstract}'       => ' ',
		'{coll_dates}'              => ' ',
		'{coll_periods}'            => ' ',
		'{coverage_country}'        => ' ',
		'{coverage_geo}'            => ' ',
		'{coverage_universe}'       => ' ',
		'{overview_kind_of_data}'   => ' ',
		'{scope_definition}'        => ' ',
		'{coll_collectors}'         => ' ',
		'{sampling_procedure}'      => ' ',
		'{sampling_dev}'            => ' ',
		'{coll_mode}'               => ' ',
		'{coll_questionnaire}'      => ' ',
		'{coll_notes}'              => ' ',
		'{operational_wb_name}'     => ' ',
		'{operational_wb_id}'       => ' ',
		'{operational_wb_net}'      => ' ',
		'{operational_wb_sector}'   => ' ',
		'{operational_wb_summary}'  => ' ',
		'{operational_wb_objectives}' => ' ',
		'{impact_wb_name}'          => ' ',
		'{impact_wb_id}'            => ' ',
		'{impact_wb_area}'          => ' ',
		'{impact_wb_lead}'          => ' ',
		'{impact_wb_members}'       => ' ',
		'{impact_wb_description}'   => ' ',
		'{coll_supervision}'        => ' ',
		'{sampling_weight}'         => ' ',
		'{process_editing}'         => ' ',
		'{process_other}'           => ' ',
		'{sampling_rates}'          => ' ',
		'{appraisal_error}'         => ' ',
		'{appraisal_other}'         => ' ',
		'{access_confidentiality}'  => ' ',
		'{access_authority}'        => ' ',
		'{access_cite_require}'     => ' ',
		'{access_conditions}'       => ' ',
		'{disclaimer_disclaimer}'   => ' ',
	);

	/**
	 * Constructor 
	 *
	 */
	 
	public function __construct() {
		$this->ci =& get_instance();
		log_message('debug', "DDI Study Export Class Initialized.");
	}
	
	/**
	 * Private Methods 
	 *
	 */
	 
	private function _replace_vars(array $variables) {
		$this->_prepare_rows($variables);
		$search     = array_keys($variables);
		$replace    = array_values($variables); 	
		$this->_DDI = str_replace($search, $replace, $this->_DDI_template);
				
		return $this->_DDI;
	}
	
	private function _is($json) {
		return isset($json) && !empty($json) && $json !== ' ';
	}
	
	private function _prepare_rows(array &$variables) {
		$this->ci->load->library('Grid');
		// prod_s_investigator
		$i                       = &$variables['{prod_s_investigator}'];
		if($this->_is($i)) {
			$i                       = json_decode($i); 
			$prod_s_investigator     = '';
			foreach ($i as $rows) {
				$prod_s_investigator .= "<AuthEnty affiliation=\"{$rows[1]}\">{$rows[0]}</AuthEnty>" . PHP_EOL;
			}
			$i                       = $prod_s_investigator;
		}
		// prod_s_acknowledgements
		$i                       = &$variables['{prod_s_acknowledgements}'];
		if ($this->_is($i)) {
			$i                       = json_decode($i);
			$prod_s_acknowledgements = '';
			foreach($i as $rows) {
				$prod_s_acknowledgements .= "<othId affiliation=\"{$rows[1]}\" role=\"{$rows[2]}\">\n<p>{$rows[0]}</p>\n</othId>" . PHP_EOL;
			}
			$i                       = $prod_s_acknowledgements;
		}
		// prod_s_other_prod
		$i                       = &$variables['{prod_s_other_prod}'];
		if ($this->_is($i)) {
			$i                       = json_decode($i);
			$prod_s_other_prod       = '';
			foreach($i as $rows) {
				$prod_s_other_prod .= "<producer abbr=\"{$rows[1]}\" affiliation=\"{$rows[2]}\" role=\"{$rows[3]}\">{$rows[0]}</producer>" . PHP_EOL;
			}
			$i                       = $prod_s_other_prod;
		}
		// prod_s_funding
		$i                       = &$variables['{prod_s_funding}'];
		if ($this->_is($i)) {
			$i                       = json_decode($i);
			$prod_s_funding          = '';
			foreach($i as $rows) {
				$prod_s_funding .= "<fundAg abbr=\"{$rows[1]}\" role=\"{$rows[3]}\">{$rows[0]}</fundAg>\n<grantNo agency=\"{$rows[0]}\" role=\"{$rows[3]}\">{$rows[2]}</grantNo>" . PHP_EOL;
			}
			$i                       = $prod_s_funding;
		}
		// contacts_contacts
		$i                       = &$variables['{contacts_contacts}'];
		if ($this->_is($i)) {
			$i                       = json_decode($i);
			$contacts_contacts       = '';
			foreach($i as $rows) {
				$contacts_contacts .= "<contact affiliation=\"{$rows[1]}\" email=\"{$rows[2]}\" URI=\"{$rows[3]}\">{$rows[0]}</contact>" . PHP_EOL;
			}
			$i                       = $contacts_contacts;
		}
		// scope_keywords
		$i                       = &$variables['{scope_keywords}'];
		if ($this->_is($i)) {
			$i                       = json_decode($i);
			$scope_keywords          = '';
			foreach ($i as $rows) {
				$scope_keywords .= "<keyword vocab=\"{$rows[1]}\" vocabURI=\"{$rows[2]}\">{$rows[0]}</keyword>" . PHP_EOL;
			}
			$i                       = $scope_keywords;
		}
		// scope_class
		$i                       = &$variables['{scope_class}'];
		if ($this->_is($i)) {
			$i                       = json_decode($i);
			$scope_class             = '';
			foreach($i as $rows) {
				$scope_class .= "<topcClas vocab=\"{$rows[1]}\" vocabURI=\"{$rows[2]}\">{$rows[0]}</topcClas>" . PHP_EOL;
			}
			$i                       = $scope_class;
		}
		// coll_dates
		$i                       = &$variables['{coll_dates}'];
		if ($this->_is($i)) {
			$i                       = json_decode($i);
			$coll_dates              = '';
			foreach($i as $rows) {
				$coll_dates .= "<timePrd date=\"{$rows[0]}\" event=\"start\" cycle=\"{$rows[2]}\" />\n<timePrd date=\"{$rows[1]}\" event=\"end\" cycle=\"{$rows[2]}\" />" . PHP_EOL;
			}
			$i                       = $coll_dates;
		}
		// coll_periods
		$i                       = &$variables['{coll_periods}'];
		if ($this->_is($i)) {
			$i                       = json_decode($i);
			$coll_periods = '';
			foreach($i as $rows) {
				$coll_periods .= "<collDate date=\"{$rows[0]}\" event=\"start\" cycle=\"{$rows[2]}\" />\n<collDate date=\"{$rows[1]}\" event=\"end\" cycle=\"{$rows[2]}\" />" . PHP_EOL;
			}	
			$i                       = $coll_periods;
		}
		// coverage_country
		$i                       = &$variables['{coverage_country}'];
		if ($this->_is($i)) {
			$i                       = json_decode($i);
			$coverage_country        = '';
			foreach ($i as $rows) {
				$coverage_country .= "<nation abbr=\"{$rows[1]}\">{$rows[0]}</nation>" . PHP_EOL;
			}
			$i                       = $coverage_country;
		}
		// coll_collectors
		$i                       = &$variables['{coll_collectors}'];
		if ($this->_is($i)) {
			$i                       = json_decode($i);
			$coll_collectors         = '';
			foreach ($i as $rows) {
				$coll_collectors .= "<dataCollector abbr=\"{$rows[1]}\" affiliation=\"{$rows[2]}\">{$rows[0]}</dataCollector>" . PHP_EOL;
			}
			$i                       = $coll_collectors;
		}
		// impact_wb_lead
		$i                       = &$variables['{impact_wb_lead}'];
		if ($this->_is($i)) {
			$i                     = json_decode($i);
			$impact_wb_lead        = '';
			foreach ($i as $rows) {
				$impact_wb_lead .= "<contact affiliation=\"{$rows[1]}\" email=\"{$rows[2]}\" URI=\"{$rows[3]}\">{$rows[0]}</contact>" . PHP_EOL;
			}
			$i                     = $impact_wb_lead;
		}
		// impact_wb_members
		$i                       = &$variables['{impact_wb_members}'];
		if ($this->_is($i)) {
			$i                       = json_decode($i);
			$impact_wb_members       = '';
			foreach ($i as $rows) {
				$impact_wb_members .= "<contact affiliation=\"{$rows[1]}\" email=\"{$rows[2]}\" URI=\"{$rows[3]}\">{$rows[0]}</contact>" . PHP_EOL;
			}
			$i                       = $impact_wb_members;
		}
		
	}
	
	/**
	 * Public Methods 
	 *
	 */
	 
	public function load_template($template) {
		if (!file_exists($template)) {
			throw new Exception("DDI Template '$template' does not exist");
		}
		
		$this->_DDI_template = $template;
	}
	
	public function to_ddi($data) {
		if (!isset($this->_DDI_template)) {
			throw new Exception('DDI Template NOT set');
		}
		$data                = $data[0];
		$this->_DDI_template = file_get_contents($this->_DDI_template);
		foreach ($this->_variables as $keys => $values) {
			$keys = str_replace(array('{', '}'), '', $keys);
			if (isset($data[$keys])) {
				$this->_variables['{' . $keys . '}'] = $data[$keys];
			}
		}
		return $this->_replace_vars($this->_variables);
	}
}