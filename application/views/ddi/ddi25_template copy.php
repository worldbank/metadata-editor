<?php
//var_dump($survey);die();
$ddi=$this->ddi_writer;
$survey['title']=$survey['title'].' - <';
?>
<?php echo "<?xml version='1.0' encoding='UTF-8'?>\r\n";?>
<codeBook 
    version="1.2.2" 
    ID="<?php echo $survey['idno'];?>" 
    xml-lang="en" 
    xmlns="http://www.icpsr.umich.edu/DDI" 
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
    xsi:schemaLocation="http://www.icpsr.umich.edu/DDI http://www.icpsr.umich.edu/DDI/Version1-2-2.xsd">
    
  <docDscr>
    <citation>
      <titlStmt>
        <titl><?php $ddi->el('doc_desc/title');?></titl>
        <IDNo><?php echo $survey['idno'];?></IDNo>
      </titlStmt>
      <prodStmt>
        <?php $doc_producers=(array)$ddi->get_el('doc_desc/producers');?>        
        <?php foreach($doc_producers as $producer):?>
          <producer abbr="<?php echo @$producer['abbreviation'];?>" affiliation="<?php echo @$producer['affiliation'];?>" role="<?php echo @$producer['role'];?>"><?php echo @$producer['name'];?></producer>
        <?php endforeach;?>
        <prodDate date="<?php $ddi->el('doc_desc/prod_date');?>"><?php $ddi->el('doc_desc/prod_date');?></prodDate>
        <software version="5.0" date="<?php echo date("Y-m-d");?>">NADA</software>
      </prodStmt>
      <verStmt>
        <version><?php $ddi->el('doc_desc/version_statement/version');?></version>
      </verStmt>
    </citation>
  </docDscr>

  <stdyDscr>
    <citation>
      <titlStmt>
        <titl><?php $ddi->el('study_desc/title_statement/title');?></titl>       
        <subTitl><?php $ddi->el('study_desc/title_statement/sub_title');?></subTitl>
        <altTitl><?php $ddi->el('study_desc/title_statement/alt_title');?></altTitl>
        <parTitl><?php $ddi->el('study_desc/title_statement/translated_title');?></parTitl>
        <IDNo><?php $ddi->el('study_desc/title_statement/idno');?></IDNo>
      </titlStmt>      
      <rspStmt>
        <?php $authoring_entities=$ddi->get_el('study_desc/authoring_entity');?>
        <?php foreach($authoring_entities as $auth_entity):?>
        <AuthEnty affiliation="<?php echo @$auth_entity['affiliation'];?>">
          <?php echo @$auth_entity['name'];?>
        </AuthEnty>  
        <?php endforeach;?>

        <?php //othID - [email attribute is not supported by DDI] ?>
        <?php $oth_ids=(array)$ddi->get_el('study_desc/oth_id');?>        
        <?php foreach($oth_ids as $oth_id):?>        
        <othId role="<?php echo @$oth_id['role'];?>" affiliation="<?php echo @$oth_id['affiliation'];?>" email="<?php echo @$oth_id['email'];?>">
          <p><?php echo @$oth_id['name'];?></p>
        </othId>
        <?php endforeach;?>
      </rspStmt>          
      <prodStmt>
        <?php //producers ?>
        <?php $producers=(array)$ddi->get_el('study_desc/production_statement/producers');?>  
        <?php foreach($producers as $producer):?>    
        <producer abbr="<?php echo @$producer['abbreviation'];?>" affiliation="<?php echo (@$producer['affiliation']);?>" role="<?php echo (@$producer['role']);?>">
          <?php echo (@$producer['name']);?>
        </producer>
        <?php endforeach;?>
        
        <copyright><?php $ddi->el('study_desc/production_statement/copyright');?></copyright>
        <software version="5.0" date="<?php echo date("Y-m-d");?>">NADA</software>

        <?php //funding agencies ?>
        <?php $fundags=$ddi->get_el('study_desc/production_statement/funding_agencies');?>  
        <?php foreach($fundags as $fundag):?>   
        <fundAg abbr="<?php echo @$fundag['abbreviation'];?>" role="<?php echo @$fundag['role'];?>">
          <?php echo (@$fundag['name']);?>
        </fundAg>
        <?php endforeach;?>

        <grantNo><?php $ddi->el('study_desc/production_statement/grant_no');?></grantNo>
      </prodStmt>

      <distStmt>
        <?php //distributor ?>
        <?php $distributors=(array)$ddi->get_el('study_desc/distribution_statement/distributors');?>
        <?php foreach($distributors as $distributor):?>   
          <distrbtr abbr="<?php echo @$distributor['abbreviation'];?>" affiliation="<?php echo @$distributor['affiliation'];?>" URI="<?php echo @$distributor['uri'];?>"><?php echo (@$distributor['name']);?></distrbtr>
        <?php endforeach;?>

        <?php //contacts ?>
        <?php $contacts=$ddi->get_el('study_desc/distribution_statement/contact');?>
        <?php foreach($contacts as $contact):?>   
          <contact affiliation="<?php echo @$contact['affiliation'];?>" URI="<?php echo @$contact['uri'];?>" email="<?php echo @$contact['email'];?>"><?php echo (@$contact['name']);?></contact>
        <?php endforeach;?>
        
        <?php //depositor ?>
        <?php $depositors=(array)$ddi->get_el('study_desc/distribution_statement/depositor');?>
        <?php foreach($depositors as $contact):?>   
          <depositr abbr="<?php echo @$contact['abbreviation'];?>"  affiliation="<?php echo @$contact['affiliation'];?>"><?php echo (@$contact['name']);?></depositr>
        <?php endforeach;?>
        
        <depDate date="<?php $ddi->el('study_desc/distribution_statement/deposit_date');?>" />
        <distDate date="<?php $ddi->el('study_desc/distribution_statement/distribution_date');?>" />
     </distStmt>

     <serStmt>
        <serName><?php $ddi->el('study_desc/series_statement/series_name');?></serName>
        <serInfo><?php $ddi->el('study_desc/series_statement/series_info');?></serInfo>
     </serStmt>

     <verStmt>
        <version date="<?php $ddi->el('study_desc/version_statement/version_date');?>" ><?php $ddi->el('study_desc/version_statement/version');?></version>
        <verResp><?php $ddi->el('study_desc/version_statement/version_resp');?></verResp>
        <notes><?php $ddi->el('study_desc/version_statement/version_notes');?></notes>
     </verStmt>
     
     <biblCit format="<?php $ddi->el('study_desc/bib_citation_format');?>"><?php $ddi->el('study_desc/bib_citation');?></biblCit>

      <?php //holdings ?>
      <?php $holdings=(array)$ddi->get_el('study_desc/holdings');?>
      <?php foreach($holdings as $holding):?>   
        <holdings location="<?php echo @$holding['location'];?>" callno="<?php echo @$holding['callno'];?>" URI="<?php echo @$holding['uri'];?>"><?php echo @$holding['name'];?></holdings>
      <?php endforeach;?>
      <notes><?php $ddi->el('study_desc/study_notes');?></notes>
  </citation>
  
  <?php  /*
  <!-- studyAuthorization - DDI2.5 
    Provides structured information on the agency that authorized the study, the date of authorization, 
    and an authorization statement.
  -->
  <studyAuthorization date="2010-11-04"> 
    <authorizingAgency affiliation="University of Georgia" abbr="HSO">Human Subjects Office</authorizingAgency> 
    <authorizationStatement>Statement of authorization issued bu OUHS on 2010-11-04</authorizationStatement>
  </studyAuthorization>
  */ ?>
  <stdyInfo>
    <?php /*
     <!-- studyBudget - ddi2.5 
     Describe the budget of the project in as much detail as needed. Use XHTML structure elements to identify 
     discrete pieces of information in a way that facilitates direct transfer of information on the study budget 
     between DDI 2 and DDI 3 structures.
     --> 
     */?>
     <studyBudget><?php $ddi->el('study_desc/study_info/study_budget');?></studyBudget>
     <subject>
      <?php //keywords ?>
      <?php $keywords=(array)$ddi->get_el('study_desc/study_info/keywords');?>
      <?php foreach($keywords as $keyword):?>
        <keyword vocab="<?php echo @$keyword['vocab'];?>" vocabURI="<?php echo @$keyword['uri'];?>"><?php echo @$keyword['keyword'];?></keyword>
      <?php endforeach;?>

      <?php //topics ?>
      <?php $topics=(array)$ddi->get_el('study_desc/study_info/topics');?>
      <?php foreach($topics as $topic):?>
        <topcClas vocab="<?php echo @$topic['vocab'];?>" vocabURI="<?php echo @$topic['uri'];?>"><?php echo @$topic['topic'];?></topcClas>
      <?php endforeach;?>

    </subject>
     <abstract><?php $ddi->el('study_desc/study_info/abstract');?></abstract>
     <sumDscr>
        <?php //time periods ?>  
        <?php $time_periods=(array)$ddi->get_el('study_desc/study_info/time_periods');?>
        <?php foreach($time_periods as $time_period):?>
          <timePrd date="<?php echo @$time_period['start'];?>" event="start" cycle="<?php echo @$time_period['cycle'];?>" />
          <timePrd date="<?php echo @$time_period['end'];?>" event="end" cycle="<?php echo @$time_period['cycle'];?>" />
        <?php endforeach;?>

        <?php //collection dates?>  
        <?php $time_periods=(array)$ddi->get_el('study_desc/study_info/coll_dates');?>
        <?php foreach($time_periods as $time_period):?>
          <collDate date="<?php echo @$time_period['start'];?>" event="start" cycle="<?php echo @$time_period['cycle'];?>" />
          <collDate date="<?php echo @$time_period['end'];?>" event="end" cycle="<?php echo @$time_period['cycle'];?>" />
        <?php endforeach;?>

        <?php //nation?>  
        <?php $nations=(array)$ddi->get_el('study_desc/study_info/nation');?>
        <?php foreach($nations as $nation):?>
          <nation abbr="<?php echo @$nation['abbreviation'];?>"><?php echo @$nation['name'];?></nation>
        <?php endforeach;?>

        <geogCover><?php $ddi->el('study_desc/study_info/geog_coverage');?></geogCover>
        <geogUnit><?php $ddi->el('study_desc/study_info/geog_unit');?></geogUnit>

        <?php //bounding box?>  
        <?php $bbox=(array)$ddi->get_el('study_desc/study_info/bbox');?>
        <?php foreach($bbox as $bound):?>
        <geoBndBox>
          <westBL><?php echo @$bound['west'];?></westBL>
          <eastBL><?php echo @$bound['east'];?></eastBL>
          <southBL><?php echo @$bound['south'];?></southBL>
          <northBL><?php echo @$bound['north'];?></northBL>
        </geoBndBox>
        <?php endforeach;?>

        <?php /*
        <!-- 
        boundPoly - not supported
        -->
        <boundPoly>
            <polygon>
                <point><gringLat>42.002207</gringLat><gringLon>-120.005729004</gringLon></point>
                <point><gringLat>42.002207</gringLat><gringLon>-114.039663</gringLon></point>
                <point><gringLat>35.9</gringLat><gringLon>-114.039663</gringLon></point>
                <point><gringLat>36.080</gringLat><gringLon>-114.544</gringLon></point>
                <point><gringLat>35.133</gringLat><gringLon>-114.542</gringLon></point>
                <point><gringLat>35.00208499998</gringLat><gringLon>-114.63288</gringLon></point>
                <point><gringLat>35.00208499998</gringLat><gringLon>-114.63323</gringLon></point>
                <point><gringLat>38.999</gringLat><gringLon>-120.005729004</gringLon></point>
                <point><gringLat>42.002207</gringLat><gringLon>-120.005729004</gringLon></point>
            </polygon>
        </boundPoly>
        */ ?>  

        <anlyUnit><?php $ddi->el('study_desc/study_info/analysis_unit');?></anlyUnit>
        <universe><?php $ddi->el('study_desc/study_info/universe');?></universe>
        <dataKind><?php $ddi->el('study_desc/study_info/data_kind');?></dataKind>
     </sumDscr>
     
     <!-- qualityStatement - ddi2.5 - complex type
     
     This structure consists of two parts, standardsCompliance and otherQualityStatements. 
     In standardsCompliance list all specific standards complied with during the execution of this 
     study. Note the standard name and producer and how the study complied with the standard. 
     Enter any additional quality statements in otherQualityStatements.
     
     -->
     <qualityStatement>
        <standardsCompliance>
          <standard> 
            <standardName><?php $ddi->el('study_desc/study_info/quality_statement/standard_name');?></standardName>
            <producer><?php $ddi->el('study_desc/study_info/quality_statement/standard_producer');?></producer>
          </standard> 
          <complianceDescription><?php $ddi->el('study_desc/study_info/quality_statement/standard_compliance_desc');?></complianceDescription> 
        </standardsCompliance>
        <otherQualityStatement><?php $ddi->el('study_desc/study_info/quality_statement/other_quality_statement');?></otherQualityStatement>
     </qualityStatement> 
     
     <notes><?php $ddi->el('study_desc/study_info/notes');?></notes>

    <!-- exPostEvaluation ddi2.5
      Use this section to describe evaluation procedures not address in data evaluation processes. 
      These may include issues such as timing of the study, sequencing issues, cost/budget issues, 
      relevance, instituional or legal arrangments etc. of the study. 
      
      The completionDate attribute holds the date the evaluation was completed. 
      The type attribute is an optional type to identify the type of evaluation with or without 
      the use of a controlled vocabulary.
    -->
    <exPostEvaluation completionDate="<?php $ddi->el('study_desc/study_info/ex_post_evaluation/completion_date');?>" type="<?php $ddi->el('study_desc/study_info/ex_post_evaluation/type');?>"> 
        <?php //evaluators?>  
        <?php $evals=(array)$ddi->get_el('study_desc/study_info/ex_post_evaluation/evaluator');?>
        <?php foreach($evals as $eval):?>
          <evaluator affiliation="<?php echo @$eval['affiliation'];?>" abbr="<?php echo @$eval['abbr'];?>" role="<?php echo @$eval['role'];?>"><?php echo @$eval['name'];?></evaluator> 
        <?php endforeach;?>  
      <evaluationProcess><?php $ddi->el('study_desc/study_info/ex_post_evaluation/evaluation_process');?></evaluationProcess>
      <outcomes><?php $ddi->el('study_desc/study_info/ex_post_evaluation/outcomes');?></outcomes> 
    </exPostEvaluation>

  </stdyInfo>

  <?php /*
  <!--studyDevelopment - DDI2.5 
    Study Development
  
   Describe the process of study development as a series of development activities. These activities can be typed using
   a controlled vocabulary. Describe the activity, listing participants with their role and affiliation, 
   resources used (sources of information), and the outcome of the development activity.
  
    Example
    This would allow you to provide inputs for a number of development activities you wanted to
    capture using separate entry screens and tagged storage of developmentActivity using the 
    type attribute. For example if there was an activity related to data availability the developmentActivity 
    might be as follows:
  -->
  <studyDevelopment>
      <developmentActivity type="checkDataAvailability"> 
        <description>A number of potential sources were evaluated for content, consistency and quality</description> 
        <participant affiliation="NSO" role="statistician">John Doe</participant> 
        <resource> 
          <dataSrc>Study S</dataSrc> 
          <srcOrig>Collected in 1970 using unknown sampling method</srcOrig> 
          <srcChar>Information incomplete missing X province</srcChar> 
        </resource> 
        <outcome>Due to quality issues this was determined not to be a viable source of data for the study</outcome> 
      </developmentActivity>
  </studyDevelopment>
*/?>

  <method>
     <dataColl>
        <timeMeth><?php $ddi->el('study_desc/method/data_collection/time_method');?></timeMeth>

        <?php $collectors=(array)$ddi->get_el('study_desc/method/data_collection/data_collectors');?>
        <?php foreach($collectors as $collector):?>
          <dataCollector abbr="<?php echo @$collector['abbreviation'];?>" affiliation="<?php echo @$collector['affiliation'];?>"><?php echo @$collector['name'];?></dataCollector>
        <?php endforeach;?> 
        
        <!-- collectorTraining - DDI2.5
        
        Collector Training

        Describes the training provided to data collectors including internviewer training, process testing, 
        compliance with standards etc. This is repeatable for language and to capture different aspects of the 
        training process. The type attribute allows specification of the type of training being described.
        
        -->        
        <collectorTraining type="<?php $ddi->el('study_desc/method/data_collection/collector_training/type');?>"><?php $ddi->el('study_desc/method/data_collection/collector_training/training');?></collectorTraining>

        <frequenc><?php $ddi->el('study_desc/method/data_collection/frequency');?></frequenc>
        <sampProc><?php $ddi->el('study_desc/method/data_collection/sampling_procedure');?></sampProc>
        
        <sampleFrame>
          <sampleFrameName><?php $ddi->el('study_desc/method/data_collection/sample_frame/name');?></sampleFrameName>
          
          <?php $valid_periods=(array)$ddi->get_el('study_desc/method/data_collection/sample_frame/valid_period');?>
          <?php foreach($valid_periods as $period):?>
            <validPeriod event="<?php echo @$period['event'];?>"><?php echo @$period['date'];?></validPeriod>
          <?php endforeach;?>
          
          <custodian><?php $ddi->el('study_desc/method/data_collection/sample_frame/custodian');?></custodian>
          <universe><?php $ddi->el('study_desc/method/data_collection/sample_frame/universe');?></universe>
          <frameUnit isPrimary="<?php $ddi->el('study_desc/method/data_collection/sample_frame/frame_unit/is_primary');?>">
            <unitType numberOfUnits="<?php $ddi->el('study_desc/method/data_collection/sample_frame/num_of_units');?>"><?php $ddi->el('study_desc/method/data_collection/sample_frame/unit_type');?></unitType>
          </frameUnit>
          
          <?php $ref_periods=(array)$ddi->get_el('study_desc/method/data_collection/sample_frame/reference_period');?>
          <?php foreach($ref_periods as $period):?>
            <referencePeriod event="<?php echo @$period['event'];?>"><?php echo @$period['date'];?></referencePeriod>
          <?php endforeach;?>
          
          <updateProcedure><?php $ddi->el('study_desc/method/data_collection/sample_frame/update_procedure');?></updateProcedure>
        </sampleFrame>

        <deviat><?php $ddi->el('study_desc/method/data_collection/sampling_deviation');?></deviat>
        <collMode><?php $ddi->el('study_desc/method/data_collection/coll_mode');?></collMode>
        <resInstru><?php $ddi->el('study_desc/method/data_collection/research_instrument');?></resInstru>

        <!-- instrumentDevelopment - DDI2.5             
        Describe any development work on the data collection instrument. Type attribute allows for the optional use of a defined development type with or without use of a controlled vocabulary.
        -->
        <instrumentDevelopment type="<?php $ddi->el('study_desc/method/data_collection/instru_development_type');?>"><?php $ddi->el('study_desc/method/data_collection/instru_development');?></instrumentDevelopment>

        <?php /*
        <!-- sources - DD2.5 - complex type 
        
        NOTE: WON'T be supported by IHSN schemas
        -->
        <sources>
            <dataSrc>data sources [repeatabled]- NOT used by IHSN**  Used to list the book(s), article(s), serial(s), and/or machine-readable data file(s)--if any--that served as the source(s) of the data collection.</dataSrc>
            <dataSrc>data sources [repeatabled]- NOT used by IHSN**  Used to list the book(s), article(s), serial(s), and/or machine-readable data file(s)--if any--that served as the source(s) of the data collection.</dataSrc>
            <!-- sourceCitation - uses citation type element - WONT BE SUPPORTED BY IHSN -->
            <sourceCitation><titlStmt><titl>sourceCitation WONT-SUPPORT</titl></titlStmt></sourceCitation>
            <srcOrig>!NESSTAR** -  Origins of Sources - For historical materials, information about the origin(s) of the sources and the rules followed in establishing the sources should be specified. May not be relevant to survey data.</srcOrig>
            <srcChar>!NESSTAR** -  Characteristics of Source Noted</srcChar>
            <srcDocu>!NESSTAR** - Documentation and Access to Sources</srcDocu>
        </sources>
        */?>

        <collSitu><?php $ddi->el('study_desc/method/data_collection/coll_situation');?></collSitu>
        <actMin><?php $ddi->el('study_desc/method/data_collection/act_min');?></actMin>
        <ConOps><?php $ddi->el('study_desc/method/data_collection/control_operations');?></ConOps>
        <weight><?php $ddi->el('study_desc/method/data_collection/weight');?></weight>
        <cleanOps><?php $ddi->el('study_desc/method/data_collection/cleaning_operations');?></cleanOps>
     </dataColl>
     <notes><?php $ddi->el('study_desc/method/method_notes');?></notes>
     <anlyInfo>
        <respRate><?php $ddi->el('study_desc/method/analysis_info/response_rate');?></respRate>
        <EstSmpErr><?php $ddi->el('study_desc/method/analysis_info/sampling_error_estimates');?></EstSmpErr>
        <dataAppr><?php $ddi->el('study_desc/method/analysis_info/data_appraisal');?></dataAppr>
     </anlyInfo>
     <stdyClas><?php $ddi->el('study_desc/method/study_class');?></stdyClas>

     <dataProcessing type="<?php $ddi->el('study_desc/method/data_processing_type');?>"><?php $ddi->el('study_desc/method/data_processing');?></dataProcessing>

     <codingInstructions relatedProcesses="<?php $ddi->el('study_desc/method/coding_instructions/related_processes');?>" type="<?php $ddi->el('study_desc/method/coding_instructions/type');?>"> 
        <txt><?php $ddi->el('study_desc/method/coding_instructions/txt');?></txt> 
        <command formalLanguage="<?php $ddi->el('study_desc/method/coding_instructions/command_language');?>"><?php $ddi->el('study_desc/method/coding_instructions/command');?></command> 
     </codingInstructions>
  </method>



  <dataAccs>
     <setAvail media="dataset availability &gt; media - The type of media the data collection is available on.">
        <?php /*
        //nada schema does not support repeated values
        <accsPlac URI="URI">data collection location name</accsPlac>
        <accsPlac URI="URI">data collectino location 2 name</accsPlac>
        */?>

        <accsPlac URI="<?php $ddi->el('study_desc/data_access/dataset_availability/access_place_uri');?>"><?php $ddi->el('study_desc/data_access/dataset_availability/access_place');?></accsPlac>        
        <origArch><?php $ddi->el('study_desc/data_access/dataset_availability/original_archive');?></origArch>
        <avlStatus><?php $ddi->el('study_desc/data_access/dataset_availability/status');?></avlStatus>
        <collSize><?php $ddi->el('study_desc/data_access/dataset_availability/coll_size');?></collSize>
        <complete><?php $ddi->el('study_desc/data_access/dataset_availability/complete');?></complete>
        <fileQnty><?php $ddi->el('study_desc/data_access/dataset_availability/file_quantity');?></fileQnty>
        <notes><?php $ddi->el('study_desc/data_access/dataset_availability/notes');?></notes>
     </setAvail>


     <useStmt>
        
        <?php //confdec -- TODO - schema is missing the attributes ?>  
        <?php $confdec_arr=(array)$ddi->get_el('study_desc/data_access/dataset_use/conf_dec');?>
        <?php foreach($confdec_arr as $conf):?>
        <confDec required="<?php echo @$conf['required'];?>" formNo="<?php echo @$conf['form_no'];?>" URI="<?php echo @$conf['uri'];?>">
          <?php echo @$conf['txt'];?>
        </confDec>
        <?php endforeach;?>

        <?php $spec_perms=(array)$ddi->get_el('study_desc/data_access/dataset_use/spec_perm');?>
        <?php foreach($spec_perms as $perm):?>
        <specPerm required="<?php echo @$perm['required'];?>" formNo="<?php echo @$perm['form_no'];?>" URI="<?php echo @$perm['uri'];?>"><?php echo @$perm['txt'];?></specPerm>
        <?php endforeach;?>
        
        <restrctn><?php $ddi->el('study_desc/data_access/dataset_use/restrictions');?></restrctn>
        
        <?php $contacts=(array)$ddi->get_el('study_desc/data_access/dataset_use/contact');?>
        <?php foreach($contacts as $contact):?>
          <contact affiliation="<?php echo @$contact['affiliation'];?>" URI="<?php echo @$contact['uri'];?>" email="<?php echo @$contact['email'];?>"><?php echo @$contact['name'];?></contact>
        <?php endforeach;?>
        
        <citReq><?php $ddi->el('study_desc/data_access/dataset_use/cit_req');?></citReq>
        <deposReq><?php $ddi->el('study_desc/data_access/dataset_use/deposit_req');?></deposReq>
        <conditions><?php $ddi->el('study_desc/data_access/dataset_use/conditions');?></conditions>
        <disclaimer><?php $ddi->el('study_desc/data_access/dataset_use/disclaimer');?></disclaimer>
     </useStmt>
     <notes><?php $ddi->el('study_desc/data_access/notes');?></notes>
  </dataAccs>
  <notes><?php $ddi->el('study_desc/notes');?></notes>      
</stdyDscr>


<?php foreach($data_files as $file):?>
<fileDscr ID="<?php echo $file['file_id'];?>" URI="Name=<?php echo $file['file_name'];?>">
  <fileTxt>
     <fileName><?php echo $file['file_name'];?></fileName>
    <?php /*
     <!-- fileCitation - DDI2.5 - citation element - WONT-SUPPORT -->
     <fileCitation><titlStmt><titl></titl></titlStmt></fileCitation>

    <!-- dataFingerprint - DDI2.5 
    
    Allows for assigning a hash value (digital fingerprint) to the data or data file. Set the 
    attribute flag to "data" when the hash value provides a digital fingerprint to the data 
    contained in the file regardless of the storage format (ASCII, SAS, binary, etc.). One approach 
    to compute a data fingerprint is the Universal Numerical Fingerprint (UNF). Set the attribute 
    flag to "dataFile" if the digital fingerprint is only for the data file in its current storage 
    format. Provide the digital fingerprint in digitalFingerprintValue and identify the algorithm 
    specification used (add version as a separate entry if it is not part of the specification entry).
    
    -->

    <dataFingerprint type="data">
      <digitalFingerprintValue>UNF:3:DaYlT6QSX9r0D50ye+tXpA== </digitalFingerprintValue> 
      <algorithmSpecification>UNF v5.0 Calculation Producture [http://thedata.org/book/unf-version-5-0]</algorithmSpecification>
      <algorithmVersion>UNF V5</algorithmVersion>
    </dataFingerprint>
  */ ?>
     <fileCont><?php echo $file['description'];?></fileCont>
<?php /*     
     <fileStrc type="relational">
        <recGrp recGrp="F5" keyvar="V94 V95">
         <!-- recDimnsn - ddi2.5 
          
          Information about the physical characteristics of the record. The "level" attribute on this 
          element should be set to "record".
          
          -->
          <recDimnsn level="record">
            <varQnty>NOT-SUPPORTED** - Number of variables per record</varQnty>
            <caseQnty>NOT-SUPPORTED** -  Number of cases / Record Quantity</caseQnty>
            <logRecL>NOT-SUPPORTED** - Record Length / Logical Record Length</logRecL>
          </recDimnsn>

        </recGrp>

        <!--notes DDI2.5 -->
        <notes><?php echo $file['notes'];?></notes>
     </fileStrc>
*/ ?>     
     <dimensns>
        <caseQnty><?php echo $file['case_count'];?></caseQnty>
        <varQnty><?php echo $file['var_count'];?></varQnty>
<?php /*        
        <!--logRecl - ddi2.5 
        Logical Record Length e.g. 25
        -->
        <logRecL>25</logRecL>

        <!--recPrCas - ddi2.5 
        Records per Case
        
        Records per case in the file. This element should be used for card-image data or other files 
        in which there are multiple records per case.

        <recPrCas>5</recPrCas>
        -->
        <recPrCas>5</recPrCas>

        <!--recNumTot - DDI2.5 
        Overall Number of Records

        Overall record count in file. Particularly helpful in instances such as files with multiple cards/decks or records per case.

        Example
        <dimensns> <recNumTot>2400</recNumTot> </dimensns>
        
        -->
        <recNumTot>5</recNumTot>
*/ ?>        
     </dimensns>
<?php /*     
     <fileType>N/A</fileType>
     <format>NOT-SUPPORTED** - Data Format e.g. delimited format, free format</format>
*/ ?>     
     <filePlac><?php echo $file['producer'];?></filePlac>
     <dataChck><?php echo $file['data_checks'];?></dataChck>
<?php /*
     <ProcStat>NOT-SUPPORTED** - Processing Status</ProcStat>
*/ ?>     
     <dataMsng><?php echo $file['missing_data'];?></dataMsng>
<?php /*     <software>N/A</software> */?>
     <verStmt>
        <version><?php echo $file['version'];?></version>
<?php /*        
      <verResp>NOT-SUPPORTED** - Version Responsibility Statement</verResp>
      <notes>NOT-SUPPORTED** - version notes</notes>
*/ ?>        
     </verStmt>         
  </fileTxt>
<?php /*
  <!-- NOT-SUPPORTED** - Location Map and sub items
  <locMap>
    <dataItem/>
    <CubeCoord/>
    <PhysLoc/>
  </locMap>

  example:
      <physLoc type="rectangular" recRef="R1" startPos="55" endPos="57" width="3"/> 
      <physLoc type="hierarchical" recRef="R6" startPos="25" endPos="25" width="1"/>
  -->
  <locMap>
    <dataItem>
      <physLoc type="rectangular" recRef="R1" startPos="55" endPos="57" width="3"/> 
    </dataItem>
  </locMap>
*/ ?>  
  <notes><?php echo $file['notes'];?></notes>
</fileDscr>
<?php endforeach;?>

<?php return;?>
<dataDscr>
  <!-- varGRP section TODO -->
  <varGrp></varGrp>

  <?php foreach($variables as $variable):$variable=$variable['metadata'];?>        
    <var ID="<?php echo $variable['vid'];?>" name="<?php echo $variable['name'];?>" files="<?php echo $variable['file_id'];?>" dcml="<?php echo @$variable['var_dcml'];?>" intrvl="<?php echo @$variable['var_intrvl'];?>">
        <pre>
          <?php var_dump($variable);?>
        </pre>
      <labl><?php @$ddi->escape_text($variable['labl']);?></labl>
    </var>


    <var ID="<?php echo $variable['vid'];?>" name="<?php echo $variable['name'];?>" files="<?php echo $variable['file_id'];?>" dcml="<?php echo @$variable['var_dcml'];?>" intrvl="<?php echo @$variable['var_intrvl'];?>">
     <location StartPos="<?php @$ddi->escape_text($variable['loc_start_pos']);?>" EndPos="61" width="1" RecSegNo="1" />
     
  </var>



  <?php endforeach;?>


  <var ID="V94" name="hh_socialbenefit" files="F1" dcml="0" intrvl="contin">
     <location StartPos="61" EndPos="61" width="1" RecSegNo="1" />
     <labl>Sample variable with all fields</labl>
     <imputation>imputation text</imputation>
     <security>security</security>
     <embargo event="notBefore" date="2001-09-30">NOT-SUPPORTED** - The data associated with this variable/nCube will not become available until September 30, 2001, because of embargo provisions established by the data producers.</embargo>
     <respUnit>source of information</respUnit>
     <anlysUnit>NOT-SUPPORTED** -- Household</anlysUnit>
     <qstn>
        <preQTxt>pre question text</preQTxt>
        <qstnLit>literal question text</qstnLit>
        <postQTxt>post question text</postQTxt>
        <forward qstn="Q120 Q121 Q122 Q123 Q124">NOT-SUPPORTED** - If yes, please ask questions 120-124.</forward>
        <backward qstn="Q12 Q13 Q14 Q15">NOT-SUPPORTED** - For responses on a similar topic, see questions 12-15.</backward>
        <ivuInstr>interview instructions</ivuInstr>
     </qstn>
     
     <!--NOT-SUPPORTED** valrng - Range of Valid Data Values -->
     <valrng><item VALUE="1" /><item VALUE="2" /><item VALUE="3" /></valrng>
    
    <!--NOT-SUPPORTED** invalrng -Range of Invalid Data Values -->
     <invalrng> 
        <range UNITS="INT" min="98" max="99"></range> 
        <key> 
        98 DK 
        99 Inappropriate 
        </key> 
      </invalrng> 

    <!-- <undocCod> List of Undocumented Codes -->  
    <undocCod>NOT-SUPPORTED** - Responses for categories 9 and 10 are unavailable.</undocCod>        

    <universe clusion="I">universe here</universe>

    <!-- <TotlResp> Total Responses -->
    <TotlResp>NOT-SUPPORTED** - There are only 725 responses to this question since it was not asked in Tanzania.</TotlResp>

     <sumStat type="min">0</sumStat>
     <txt>variable definition here</txt>

     <stdCatgry date="1981" source="producer">NOT-SUPPORTED** - Standard Categories- U. S. Census of Population and Housing, Classified Index of Industries and Occupations </stdCatgry>
     
     <!--catgryGrp - NOTSUPPORTED** -->
     <catgryGrp><labl>NOT-SUPPORTED** - Category Group</labl></catgryGrp>

     <codInstr>recoding and derivation</codInstr>

     <!-- verStmt - DDI2.5 
     
     Version statement - not supported by NESSTAR
     
     -->
     <verStmt>
        <version date="2018-04" type="version type">variable version description</version>
        <verResp affiliation="version responsibility affliation">var version responsibility</verResp>
        <notes>var version notes</notes>
     </verStmt>

     <concept vocab="concept vocab" vocabURI="uri">concept1</concept>
     
     <!-- 
     
     Derivation - DDI2.5

     Used only in the case of a derived variable, this element provides both a description of 
     how the derivation was performed and the command used to generate the derived variable, as well 
     as a specification of the other variables in the study used to generate the derivation. The "var" 
     attribute provides the ID values of the other variables in the study used to generate this derived variable.
     
      - drvdesc - A textual description of the way in which this variable was derived. The element may be repeated to support multiple language expressions of the content.
      - drvcmd - The actual command used to generate the derived variable. The "syntax" attribute is used to indicate the command language employed (e.g., SPSS, SAS, Fortran, etc.). The element may be repeated to support multiple language expressions of the content.
     --> 

     <derivation>
        <drvdesc> VAR215.01 "Outcome of first pregnancy" (1988 NSFG=VAR611 PREGOUT1) If R has never been pregnant (VAR203 PREGNUM EQ 0) then OUTCOM01 is blank/inapplicable. Else, OUTCOM01 is transferred from VAR225 OUTCOME for R's 1st pregnancy. </drvdesc> 
        <drvcmd syntax="SPSS">RECODE V1 TO V3 (0=1) (1=0) (2=-1) INTO DEFENSE WELFAREHEALTH. </drvcmd>
     </derivation> 

     <varFormat type="numeric" schema="other" />

    <!-- geoMap - DDI2.5 

    This element is used to point, using a "URI" attribute, to an external map that displays the geography in question. The "levelno" attribute indicates the level of the geographic hierarchy relayed in the map. The "mapformat" attribute indicates the format of the map.
    
    -->
     <geoMap URI="http://url-to-map" mapformat="map-format"/>         
     <notes>variable notes</notes>
  </var>
  <var ID="V95" name="hh_pensions" files="F1" dcml="0" intrvl="contin">
     <location StartPos="61" EndPos="61" width="1" RecSegNo="1" />
     <labl>1:someone in the hh receives pension; 0:else</labl>
     <imputation>imputation</imputation>
     <security>security</security>
     <respUnit>source of information</respUnit>
     <qstn>
        <preQTxt>pre question text</preQTxt>
        <qstnLit>literal question</qstnLit>
        <postQTxt>post question text</postQTxt>
        <ivuInstr>interviewer instructions</ivuInstr>
     </qstn>
     <universe clusion="I">universe</universe>
     <txt>definition here</txt>
     <catgry>
        <catValu>1</catValu>
        <labl>category label 1</labl>
     </catgry>
     <catgry>
        <catValu>2</catValu>
        <labl>category label 2</labl>
        <txt>category text</txt>
     </catgry>
     <codInstr>recoding and derivation</codInstr>
     <concept vocab="vocab" vocabURI="uri">concept text</concept>
     <varFormat type="numeric" schema="other" />
     <notes>notes</notes>
  </var>
  <var ID="V97" name="name" files="F1" intrvl="discrete">
     <location width="15" RecSegNo="1" />
     <labl>person name</labl>
     <varFormat type="character" schema="other" />
  </var>
  <var ID="V99" name="dob" files="F1" intrvl="discrete">
     <location width="15" RecSegNo="1" />
     <labl>date of birth</labl>
     <varFormat type="character" formatname="Nesstar.date" schema="other" category="date" />
  </var>
  <var ID="V517" name="V5" files="F1" dcml="0" intrvl="discrete">
     <location width="15" RecSegNo="1" />
     <labl />
     <varFormat type="numeric" schema="other" />
  </var>
</dataDscr>
<otherMat level="study">
<!--<ihsn:item key="published">1</ihsn:item>-->
</otherMat>
</codeBook>