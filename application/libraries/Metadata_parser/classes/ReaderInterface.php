<?php

interface ReaderInterface{
    
    public function get_languages();
    public function get_id();
    public function get_title();
    public function get_abbreviation();
    public function get_authenty();
    public function get_producers();
    public function get_sponsors();
    public function get_start_year();
    public function get_end_year();
    public function get_years();
    
    public function get_countries();
    public function get_countries_str();
    
    public function get_topics();
    public function get_keywords();
    public function get_metadata_array();
    public function get_bounding_box();
    
    public function get_data_files();
    
    //return iterator for variable level metadata
    public function get_variable_iterator();    
}

