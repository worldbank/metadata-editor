<?php
/**
 *
 *  SOLR configurations for catalog search
 *
 */

/*
|--------------------------------------------------------------------------
| SOLR connection configurations
|--------------------------------------------------------------------------
|
| solr_host         SOLR host name
| solr_port         Port used by SOLR
| solr_collection   Collection path
|
| 
*/
$config['solr_host'] = "localhost";
$config['solr_port'] = "8983";
$config['solr_collection'] = "nada";

$config['solr_edismax_options']=array(
  'qf'=>'title^20.0 nation^20.0 years^30.0 authoring_entity idno keywords', //query fields - qf="title^20 nation keywords"
  'pf'=>'', //phrase fields
  'mm'=>'3<90%', //minimum match - 3, 2, 75%, -25% 
  'ps'=>'',
  'qs'=>'',
  'bq'=>'',
  'bf'=>''
);

//variable search
$config['solr_edismax_variable_options']=array(
  'qf'=>'labl^20.0 name^20.0 catgry qstn', //query fields - qf="title^20 nation keywords"
  'pf'=>'', //phrase fields
  'mm'=>'3<90%', //minimum match - 3, 2, 75%, -25% 
  'ps'=>'',
  'qs'=>'',
  'bq'=>'',
  'bf'=>''
);

$config['solr_debug']=false;


/**
 * 
 * SOLR - Create core
 * 
 * #from the CLI run: 
 * solr create_core -c nada
 * 
 * The core folder e.g. (nada), will include a 'config' folder with the default configurations including the schema file.
 * 
 */


 /**
  *
  * SOLR Schema
  * 
  * edit managed-schema  - add following fields
  *
  **/ 
/*
<fieldType name="text_en_var" class="solr.TextField" positionIncrementGap="100">
    <analyzer type="index">
      <tokenizer class="solr.StandardTokenizerFactory"/>
      <filter class="solr.StopFilterFactory" words="lang/stopwords_en.txt" ignoreCase="true"/>
      <filter class="solr.LowerCaseFilterFactory"/>
      <filter class="solr.EnglishPossessiveFilterFactory"/>
      <filter class="solr.KeywordMarkerFilterFactory" protected="protwords.txt"/>
      <filter class="solr.PorterStemFilterFactory"/>
      <!-- remove all numeric values except when numbers are part of text -->
      <filter class="solr.PatternReplaceFilterFactory" pattern="\b([0-9]+)\b" replacement="" replace="all" />
      <!-- remove strings with length <3 -->
      <filter class="solr.PatternReplaceFilterFactory" pattern="\b\w{1,2}\b" replacement="" replace="all" />        
    </analyzer>
    <analyzer type="query">
      <tokenizer class="solr.StandardTokenizerFactory"/>
      <filter class="solr.SynonymFilterFactory" expand="true" ignoreCase="true" synonyms="synonyms.txt"/>
      <filter class="solr.StopFilterFactory" words="lang/stopwords_en.txt" ignoreCase="true"/>
      <filter class="solr.LowerCaseFilterFactory"/>
      <filter class="solr.EnglishPossessiveFilterFactory"/>
      <filter class="solr.KeywordMarkerFilterFactory" protected="protwords.txt"/>
      <filter class="solr.PorterStemFilterFactory"/>
    </analyzer>
  </fieldType>


  <field name="abstract" type="text_en" indexed="true" stored="false"/>
  <field name="alt_title" type="text_en" indexed="true" stored="false"/>
  <field name="authenty" type="text_en" indexed="true" stored="true"/>
  <field name="authoring_entity" type="text_en" multiValued="false" indexed="true" stored="true"/>
  <field name="authors" type="text_en" multiValued="true" indexed="true" stored="false"/>
  <field name="catgry" type="text_en" multiValued="false" indexed="true" stored="false"/>
  <field name="changed" type="pint" indexed="true" stored="true"/>
  <field name="citation_id" type="pint" indexed="true" stored="true"/>
  <field name="citation_uuid" type="text_general" multiValued="false" indexed="true" stored="true"/>
  <field name="countries" type="pint" multiValued="true" indexed="true" stored="true"/>
  <field name="country" type="text_general" indexed="true" stored="false"/>
  <field name="created" type="pint" indexed="true" stored="true"/>
  <field name="ctype" type="string" indexed="true" stored="true"/>
  <field name="data_coll_end" type="pint" indexed="true" stored="true"/>
  <field name="data_coll_start" type="pint" indexed="true" stored="true"/>
  <field name="dataset_type" type="text_general" multiValued="false" indexed="true" stored="true"/>
  <field name="doctype" type="pint" indexed="true" stored="true"/>
  <field name="doi" type="string" indexed="true" stored="true"/>
  <field name="flag" type="string" indexed="true" stored="true"/>
  <field name="form_model" type="string" indexed="true" stored="true"/>
  <field name="formid" type="pint" indexed="true" stored="true"/>
  <field name="ft_keywords" type="text_en" indexed="true" stored="false"/>
  <field name="idno" type="text_general" multiValued="false" indexed="true" stored="true"/>
  <field name="idnumber" type="text_general" indexed="true" stored="true"/>
  <field name="ihsn_id" type="pint" indexed="true" stored="true"/>
  <field name="keywords" type="text_en" indexed="true" stored="false"/>
  <field name="labl" type="text_en" indexed="true" stored="true"/>
  <field name="link_da" type="string" indexed="true" stored="true"/>
  <field name="metadata" type="text_en" indexed="true" stored="false"/>
  <field name="name" type="text_en" indexed="true" stored="true"/>
  <field name="nation" type="text_en" multiValued="false" indexed="true" stored="true"/>
  <field name="notes" type="text_en" indexed="true" stored="false"/>
  <field name="organization" type="text_en" indexed="true" stored="false"/>
  <field name="owner" type="text_general" indexed="true" stored="false"/>
  <field name="place_publication" type="text_en" indexed="true" stored="false"/>
  <field name="proddate" type="pint" indexed="true" stored="true"/>
  <field name="producer" type="text_en" indexed="true" stored="true"/>
  <field name="pub_date" type="string" indexed="true" stored="false"/>
  <field name="published" type="pint" indexed="true" stored="true"/>
  <field name="publisher" type="text_en" indexed="true" stored="false"/>
  <field name="qstn" type="text_en" multiValued="false" indexed="true" stored="true"/>
  <field name="related_surveys" type="pint" multiValued="true" indexed="true" stored="true"/>
  <field name="repo_title" type="string" multiValued="false" indexed="true" stored="true"/>
  <field name="repositories" type="text_general" multiValued="true" indexed="true" stored="true"/>
  <field name="repositoryid" type="string" indexed="true" stored="true"/>
  <field name="sid" type="pint" indexed="true" stored="true"/>
  <field name="sponsor" type="text_en" indexed="true" stored="true"/>
  <field name="subtitle" type="text_en" indexed="true" stored="false"/>
  <field name="survey_uid" type="pint" indexed="true" stored="true"/>
  <field name="surveyid" type="text_general" indexed="true" stored="true"/>
  <field name="text" type="text_en" multiValued="true" indexed="true" stored="false"/>
  <field name="thumbnail" type="string" multiValued="false" indexed="false" stored="true"/>
  <field name="title" type="text_en" multiValued="false" indexed="true" stored="true"/>
  <field name="topics" type="pint" multiValued="true" indexed="true" stored="false"/>
  <field name="total_downloads" type="pint" indexed="true" stored="true"/>
  <field name="total_views" type="pint" indexed="true" stored="true"/>
  <field name="type" type="string" indexed="true" stored="true"/>
  <field name="var_keywords" type="text_en_var" multiValued="false" indexed="true" stored="false"/>
  <field name="var_uid" type="pint" indexed="true" stored="true"/>
  <field name="varcount" type="pint" indexed="true" stored="true"/>
  <field name="varid" type="string" indexed="false" stored="true"/>
  <field name="vid" type="string" multiValued="false" indexed="true" stored="true"/>
  <field name="volume" type="string" indexed="true" multiValued="false" />
  <field name="issue" type="string" indexed="true" multiValued="false" />
  <field name="edition" type="string" indexed="true" multiValued="false" />

  <field name="year_end" type="pint" indexed="true" stored="true"/>
  <field name="year_start" type="pint" indexed="true" stored="true"/>
  <field name="years" type="pint" multiValued="true" indexed="true" stored="true"/>

  <dynamicField name="fq_*" type="pint" multiValued="true" indexed="true" stored="true"/>
*/