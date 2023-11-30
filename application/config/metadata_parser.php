<?php

//map ddi2 to NADA survey type schema
//note: all complex types are tranformed by the DDI2Reader at the time of parsing the DDI. The transform_callbacks are not implemented yet.
$config['survey']=array(
    #docDesc elements
    'doc_desc/title'=>array('xpath'=>'codeBook/docDscr/citation/titlStmt/titl', 'transform_callback'=>'none'),
    'doc_desc/idno'=>array('xpath'=>'codeBook/docDscr/citation/titlStmt/IDNo', 'transform_callback'=>'none'),
    'doc_desc/producers'=>array(
        'xpath'=>'codeBook/docDscr/citation/prodStmt/producer', 
        'transform_callback'=>'none',
        'type'=>'array'
    ),
    'doc_desc/prod_date'=>array('xpath'=>'codeBook/docDscr/citation/prodStmt/prodDate', 'transform_callback'=>'none'),
    #version statement    
    'doc_desc/version_statement/version'=>array('xpath'=>'codeBook/docDscr/citation/verStmt/version', 'transform_callback'=>'none'),
    'doc_desc/version_statement/version_date'=>array('xpath'=>'codeBook/docDscr/citation/verStmt/version/@date', 'transform_callback'=>'none'),
    'doc_desc/version_statement/version_notes'=>array('xpath'=>'codeBook/docDscr/citation/verStmt/notes', 'transform_callback'=>'none'),
    'doc_desc/version_statement/version_resp'=>array('xpath'=>'codeBook/docDscr/citation/verStmt/verResp', 'transform_callback'=>'none'),
    
    
    #stdyDesc elements
    'idno'=>array('xpath'=>'codeBook/stdyDscr/citation/titlStmt/IDNo', 'transform_callback'=>'none'),
    'study_desc/title_statement/idno'=>array('xpath'=>'codeBook/stdyDscr/citation/titlStmt/IDNo', 'transform_callback'=>'none'),
    'study_desc/title_statement/title'=>array(
            'xpath'=>'codeBook/stdyDscr/citation/titlStmt/titl', 
            'transform_callback'=>'none'    
    ),
    'study_desc/title_statement/sub_title'=>array('xpath'=>'codeBook/stdyDscr/citation/titlStmt/subTitl', 'transform_callback'=>'none'),
    'study_desc/title_statement/alternate_title'=>array('xpath'=>'codeBook/stdyDscr/citation/titlStmt/altTitl', 'transform_callback'=>'none'),
    'study_desc/title_statement/translated_title'=>array('xpath'=>'codeBook/stdyDscr/citation/titlStmt/parTitl', 'transform_callback'=>'none'),    

    'study_desc/authoring_entity'=>array(
        'xpath'=>'codeBook/stdyDscr/citation/rspStmt/AuthEnty', 
        'transform_callback'=>'none',
        'type'=>'array'
    ),
    'study_desc/oth_id'=>array(
        'xpath'=>'codeBook/stdyDscr/citation/rspStmt/othId', 
        'transform_callback'=>'none',
        'type'=>'array'
    ),

    #production statement
    'study_desc/production_statement/producers'=>array(
        'xpath'=>'codeBook/stdyDscr/citation/prodStmt/producer', 
        'transform_callback'=>'none',
        'type'=>'array'
    ),
    'study_desc/production_statement/copyright'=>array('xpath'=>'codeBook/stdyDscr/citation/prodStmt/copyright', 'transform_callback'=>'none'),
    'study_desc/production_statement/prod_date'=>array('xpath'=>'codeBook/stdyDscr/citation/prodStmt/prodDate', 'transform_callback'=>'none'),
    'study_desc/production_statement/prod_place'=>array('xpath'=>'codeBook/stdyDscr/citation/prodStmt/prodPlac', 'transform_callback'=>'none'),


    #'study_desc/software'=>array('xpath'=>'codeBook/stdyDscr/citation/prodStmt/software', 'transform_callback'=>'none'),
    #'study_desc/software_version'=>array('xpath'=>'codeBook/stdyDscr/citation/prodStmt/software/@version', 'transform_callback'=>'none'),
    #'study_desc/software_date'=>array('xpath'=>'codeBook/stdyDscr/citation/prodStmt/software/@date', 'transform_callback'=>'none'),
    'study_desc/production_statement/funding_agencies'=>array('xpath'=>'codeBook/stdyDscr/citation/prodStmt/fundAg', 'transform_callback'=>'none','type'=>'array'),
    'study_desc/production_statement/grant_no'=>array('xpath'=>'codeBook/stdyDscr/citation/prodStmt/grantNo', 'transform_callback'=>'none'),
    
    #distribution statement
    'study_desc/distribution_statement/distributors'=>array('xpath'=>'codeBook/stdyDscr/citation/distStmt/distrbtr', 'transform_callback'=>'none','type'=>'array'),
    'study_desc/distribution_statement/contact'=>array('xpath'=>'codeBook/stdyDscr/citation/distStmt/contact', 'transform_callback'=>'none','type'=>'array'),
    'study_desc/distribution_statement/depositor'=>array('xpath'=>'codeBook/stdyDscr/citation/distStmt/depositr', 'transform_callback'=>'none','type'=>'array'),
    'study_desc/distribution_statement/deposit_date'=>array('xpath'=>'codeBook/stdyDscr/citation/distStmt/depDate/@date', 'transform_callback'=>'none'),
    'study_desc/distribution_statement/distribution_date'=>array('xpath'=>'codeBook/stdyDscr/citation/distStmt/distDate/@date', 'transform_callback'=>'none'),

    #series statement    
    'study_desc/series_statement/series_name'=>array('xpath'=>'codeBook/stdyDscr/citation/serStmt/serName', 'transform_callback'=>'none'),
    'study_desc/series_statement/series_info'=>array('xpath'=>'codeBook/stdyDscr/citation/serStmt/serInfo', 'transform_callback'=>'none'),
     
    #version statement    
    'study_desc/version_statement/version'=>array('xpath'=>'codeBook/stdyDscr/citation/verStmt/version', 'transform_callback'=>'none'),
    'study_desc/version_statement/version_date'=>array('xpath'=>'codeBook/stdyDscr/citation/verStmt/version/@date', 'transform_callback'=>'none'),
    'study_desc/version_statement/version_notes'=>array('xpath'=>'codeBook/stdyDscr/citation/verStmt/notes', 'transform_callback'=>'none'),
    'study_desc/version_statement/version_resp'=>array('xpath'=>'codeBook/stdyDscr/citation/verStmt/verResp', 'transform_callback'=>'none'),

    'study_desc/bib_citation'=>array('xpath'=>'codeBook/stdyDscr/citation/biblCit', 'transform_callback'=>'none'),
    'study_desc/bib_citation_format'=>array('xpath'=>'codeBook/stdyDscr/citation/biblCit/@format', 'transform_callback'=>'none'),

    #holdings
    #todo - check if transform is created
    'study_desc/holdings'=>array('xpath'=>'codeBook/stdyDscr/citation/holdings', 'transform_callback'=>'none','type'=>'array'),
    'study_desc/study_notes'=>array('xpath'=>'codeBook/stdyDscr/citation/notes', 'transform_callback'=>'none'),
    
    #study authorization
    ##TODO - need transforms
    'study_desc/study_authorization/date'=>array('xpath'=>'codeBook/stdyDscr/studyAuthorization/@date', 'transform_callback'=>'none'),
    'study_desc/study_authorization/agency'=>array('xpath'=>'codeBook/stdyDscr/studyAuthorization/authorizingAgency', 'transform_callback'=>'none','type'=>'array'),
    'study_desc/study_authorization/authorization_statement'=>array('xpath'=>'codeBook/stdyDscr/studyAuthorization/authorizationStatement', 'transform_callback'=>'none'),

    #study info
    'study_desc/study_info/study_budget'=>array(
        'xpath'=>'codeBook/stdyDscr/stdyInfo/studyBudget', 
        'transform_callback'=>'none'
    ),
    'study_desc/study_info/keywords'=>array(
        'xpath'=>'codeBook/stdyDscr/stdyInfo/subject/keyword', 
        'type'=>'array',
        'transform_callback'=>'transform_ddi_keywords'
    ),
    'study_desc/study_info/topics'=>array(
        'xpath'=>'codeBook/stdyDscr/stdyInfo/subject/topcClas', 
        'transform_callback'=>'transform_ddi_topics',
        'type'=>'array'
    ),
    'study_desc/study_info/abstract'=>array(
        'xpath'=>'codeBook/stdyDscr/stdyInfo/abstract', 
        'transform_callback'=>'none'
    ),
    'study_desc/study_info/time_periods'=>array(
        'xpath'=>'codeBook/stdyDscr/stdyInfo/sumDscr/timePrd', 
        'transform_callback'=>'none','type'=>'array'
    ),
    'study_desc/study_info/coll_dates'=>array(
        'xpath'=>'codeBook/stdyDscr/stdyInfo/sumDscr/collDate', 
        'transform_callback'=>'transform_ddi_coll_date','type'=>'array'
    ),
    'study_desc/study_info/nation'=>array(
        'xpath'=>'codeBook/stdyDscr/stdyInfo/sumDscr/nation', 
        'transform_callback'=>'transform_ddi_nation','type'=>'array'
    ),
    'study_desc/study_info/bbox'=>array(
        'xpath'=>'codeBook/stdyDscr/stdyInfo/sumDscr/geoBndBox', 
        'transform_callback'=>'none','type'=>'array'
    ),
    'study_desc/study_info/bound_poly'=>array(
        'xpath'=>'codeBook/stdyDscr/stdyInfo/sumDscr/boundPoly', 
        'transform_callback'=>'none','type'=>'array'
    ),

    'study_desc/study_info/geog_coverage'=>array('xpath'=>'codeBook/stdyDscr/stdyInfo/sumDscr/geogCover', 'transform_callback'=>'none'),
    
    #TODO - not part of the DDI spec
    'study_desc/study_info/geog_coverage_notes'=>array('xpath'=>'codeBook/stdyDscr/stdyInfo/sumDscr/geogCoverNote', 'transform_callback'=>'none'),
    'study_desc/study_info/geog_unit'=>array('xpath'=>'codeBook/stdyDscr/stdyInfo/sumDscr/geogUnit', 'transform_callback'=>'none'),

    'study_desc/study_info/analysis_unit'=>array('xpath'=>'codeBook/stdyDscr/stdyInfo/sumDscr/anlyUnit', 'transform_callback'=>'none'),
    'study_desc/study_info/universe'=>array('xpath'=>'codeBook/stdyDscr/stdyInfo/sumDscr/universe', 'transform_callback'=>'none'),
    'study_desc/study_info/data_kind'=>array('xpath'=>'codeBook/stdyDscr/stdyInfo/sumDscr/dataKind', 'transform_callback'=>'none'),
    'study_desc/study_info/notes'=>array('xpath'=>'codeBook/stdyDscr/stdyInfo/notes', 'transform_callback'=>'none'),

    #quality statement
    //'study_desc/study_info/quality_statement/standard_name'=>array('xpath'=>'codeBook/stdyDscr/stdyInfo/qualityStatement/standardsCompliance/standard/standardName', 'transform_callback'=>'none'),
    //'study_desc/study_info/quality_statement/standard_producer'=>array('xpath'=>'codeBook/stdyDscr/stdyInfo/qualityStatement/standardsCompliance/standard/producer', 'transform_callback'=>'none'),
    'study_desc/study_info/quality_statement/standards'=>array('xpath'=>'codeBook/stdyDscr/stdyInfo/qualityStatement/standardsCompliance/standard', 'transform_callback'=>'none','type'=>'array'),
    'study_desc/study_info/quality_statement/compliance_description'=>array('xpath'=>'codeBook/stdyDscr/stdyInfo/qualityStatement/standardsCompliance/complianceDescription', 'transform_callback'=>'none'),
    'study_desc/study_info/quality_statement/other_quality_statement'=>array('xpath'=>'codeBook/stdyDscr/stdyInfo/qualityStatement/otherQualityStatement', 'transform_callback'=>'none'),

    #ex-post-evaluation
    'study_desc/study_info/ex_post_evaluation/completion_date'=>array('xpath'=>'codeBook/stdyDscr/stdyInfo/exPostEvaluation/@completionDate', 'transform_callback'=>'none'),
    'study_desc/study_info/ex_post_evaluation/type'=>array('xpath'=>'codeBook/stdyDscr/stdyInfo/exPostEvaluation/@type', 'transform_callback'=>'none'),
    #todo - need mapping
    'study_desc/study_info/ex_post_evaluation/evaluator'=>array('xpath'=>'codeBook/stdyDscr/stdyInfo/exPostEvaluation/evaluator', 'transform_callback'=>'none','type'=>'array'),
    'study_desc/study_info/ex_post_evaluation/evaluation_process'=>array('xpath'=>'codeBook/stdyDscr/stdyInfo/exPostEvaluation/evaluationProcess', 'transform_callback'=>'none'),
    'study_desc/study_info/ex_post_evaluation/outcomes'=>array('xpath'=>'codeBook/stdyDscr/stdyInfo/exPostEvaluation/outcomes', 'transform_callback'=>'none'),

    #study development
    //'study_desc/study_development/activity_type'=>array('xpath'=>'codeBook/stdyDscr/studyDevelopment/developmentActivity/@type', 'transform_callback'=>'none'),
    //'study_desc/study_development/activity_description'=>array('xpath'=>'codeBook/stdyDscr/studyDevelopment/developmentActivity/description', 'transform_callback'=>'none'),
    //'study_desc/study_development/participants'=>array('xpath'=>'codeBook/stdyDscr/studyDevelopment/developmentActivity/participant', 'transform_callback'=>'none','type'=>'array'),
    'study_desc/study_development/development_activity'=>array('xpath'=>'codeBook/stdyDscr/studyDevelopment/developmentActivity', 'transform_callback'=>'none', 'type'=>'array'),

    #study development > resource
    'study_desc/study_development/resource/data_source'=>array('xpath'=>'codeBook/stdyDscr/studyDevelopment/developmentActivity/resource/dataSrc', 'transform_callback'=>'transform_ddi_datasource','type'=>'array'),
    'study_desc/study_development/resource/source_origin'=>array('xpath'=>'codeBook/stdyDscr/studyDevelopment/developmentActivity/resource/srcOrig', 'transform_callback'=>'none'),
    'study_desc/study_development/resource/source_char'=>array('xpath'=>'codeBook/stdyDscr/studyDevelopment/developmentActivity/resource/srcChar', 'transform_callback'=>'none'),

    'study_desc/study_development/outcome'=>array('xpath'=>'codeBook/stdyDscr/studyDevelopment/developmentActivity/outcome', 'transform_callback'=>'none'),

    #method > data collection
    'study_desc/method/data_collection/time_method'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/timeMeth', 'transform_callback'=>'none'),    
    'study_desc/method/data_collection/data_collectors'=>array(
        'xpath'=>'codeBook/stdyDscr/method/dataColl/dataCollector', 
        'transform_callback'=>'transform_ddi_datacollector','type'=>'array'
    ),
    #method > data collection > collector training
    //'study_desc/method/data_collection/collector_training/type'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/collectorTraining/@type', 'transform_callback'=>'none'),
    //'study_desc/method/data_collection/collector_training/training'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/collectorTraining', 'transform_callback'=>'none'),
    'study_desc/method/data_collection/collector_training'=>array(
        'xpath'=>'codeBook/stdyDscr/method/dataColl/collectorTraining', 
        'transform_callback'=>'none',
        'type'=>'array'
    ),

    'study_desc/method/data_collection/frequency'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/frequenc', 'transform_callback'=>'none'),
    'study_desc/method/data_collection/sampling_procedure'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/sampProc', 'transform_callback'=>'none'),

    #sample frame
    'study_desc/method/data_collection/sample_frame/name'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/sampleFrame/sampleFrameName', 'transform_callback'=>'none'),
    #todo - needs mapping
    'study_desc/method/data_collection/sample_frame/valid_period'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/sampleFrame/validPeriod', 'transform_callback'=>'none','type'=>'array'),
    'study_desc/method/data_collection/sample_frame/custodian'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/sampleFrame/custodian', 'transform_callback'=>'none'),
    'study_desc/method/data_collection/sample_frame/universe'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/sampleFrame/universe', 'transform_callback'=>'none'),

    #sample frame > frame unit
    'study_desc/method/data_collection/sample_frame/frame_unit/is_primary'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/sampleFrame/frameUnit/@isPrimary', 'transform_callback'=>'none'),
    'study_desc/method/data_collection/sample_frame/frame_unit/unit_type'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/sampleFrame/frameUnit/unitType', 'transform_callback'=>'none'),
    'study_desc/method/data_collection/sample_frame/frame_unit/num_of_units'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/sampleFrame/frameUnit/unitType/@numberOfUnits', 'transform_callback'=>'none'),

    #todo - mapping needed
    'study_desc/method/data_collection/sample_frame/reference_period'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/sampleFrame/referencePeriod', 'transform_callback'=>'none','type'=>'array'),

    'study_desc/method/data_collection/sample_frame/update_procedure'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/sampleFrame/updateProcedure', 'transform_callback'=>'none'),


    'study_desc/method/data_collection/sampling_deviation'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/deviat', 'transform_callback'=>'none'),
    
    'study_desc/method/data_collection/coll_mode'=>array(
        'xpath'=>'codeBook/stdyDscr/method/dataColl/collMode', 'transform_callback'=>'none'),
    
    'study_desc/method/data_collection/coll_mode'=>array(
            'xpath'=>'codeBook/stdyDscr/method/dataColl/collMode', 
            'transform_callback'=>'transform_ddi_collmode','type'=>'array'
        ),
    
    
    
    'study_desc/method/data_collection/research_instrument'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/resInstru', 'transform_callback'=>'none'),
    'study_desc/method/data_collection/instru_development'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/instrumentDevelopment', 'transform_callback'=>'none'),
    'study_desc/method/data_collection/instru_development_type'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/instrumentDevelopment/@type', 'transform_callback'=>'none'),
    
    //sources - todo - not all fields are mapped for sources
    'study_desc/method/data_collection/sources'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/sources', 'transform_callback'=>'none','type'=>'array'),
    
    'study_desc/method/data_collection/coll_situation'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/collSitu', 'transform_callback'=>'none'),
    'study_desc/method/data_collection/act_min'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/actMin', 'transform_callback'=>'none'),
    'study_desc/method/data_collection/control_operations'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/ConOps', 'transform_callback'=>'none'),
    'study_desc/method/data_collection/weight'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/weight', 'transform_callback'=>'none'),
    'study_desc/method/data_collection/cleaning_operations'=>array('xpath'=>'codeBook/stdyDscr/method/dataColl/cleanOps', 'transform_callback'=>'none'),
    'study_desc/method/method_notes'=>array('xpath'=>'codeBook/stdyDscr/method/notes', 'transform_callback'=>'none'),
    
    #method > analysis info
    'study_desc/method/analysis_info/response_rate'=>array('xpath'=>'codeBook/stdyDscr/method/anlyInfo/respRate', 'transform_callback'=>'none'),
    'study_desc/method/analysis_info/sampling_error_estimates'=>array('xpath'=>'codeBook/stdyDscr/method/anlyInfo/EstSmpErr', 'transform_callback'=>'none'),
    'study_desc/method/analysis_info/data_appraisal'=>array('xpath'=>'codeBook/stdyDscr/method/anlyInfo/dataAppr', 'transform_callback'=>'none'),

    'study_desc/method/study_class'=>array('xpath'=>'codeBook/stdyDscr/method/stdyClas', 'transform_callback'=>'none'),
    'study_desc/method/data_processing'=>array('xpath'=>'codeBook/stdyDscr/method/dataProcessing', 'transform_callback'=>'none', 'type'=>'array'),
    //'study_desc/method/data_processing_type'=>array('xpath'=>'codeBook/stdyDscr/method/dataProcessing/@type', 'transform_callback'=>'none'),

    #coding instructions
    'study_desc/method/coding_instructions'=>array('xpath'=>'codeBook/stdyDscr/method/codingInstructions', 'transform_callback'=>'none', 'type'=>'array'),
    /*
    'study_desc/method/coding_instructions/related_processes'=>array('xpath'=>'codeBook/stdyDscr/method/codingInstructions/@relatedProcesses', 'transform_callback'=>'none'),
    'study_desc/method/coding_instructions/type'=>array('xpath'=>'codeBook/stdyDscr/method/codingInstructions/@type', 'transform_callback'=>'none'),
    'study_desc/method/coding_instructions/txt'=>array('xpath'=>'codeBook/stdyDscr/method/codingInstructions/@txt', 'transform_callback'=>'none'),
    'study_desc/method/coding_instructions/command'=>array('xpath'=>'codeBook/stdyDscr/method/codingInstructions/command', 'transform_callback'=>'none'),
    'study_desc/method/coding_instructions/command_language'=>array('xpath'=>'codeBook/stdyDscr/method/codingInstructions/command/@formalLanguage', 'transform_callback'=>'none'),
    */

    #data access > data availability
    'study_desc/data_access/dataset_availability/access_place'=>array(
        'xpath'=>'codeBook/stdyDscr/dataAccs/setAvail/accsPlac', 
        'transform_callback'=>'none',
        'type'=>'array'
    ),
    //not a ddi field - created by the transform to convert the access_place repeated element to a non-repeatable fields
    'study_desc/data_access/dataset_availability/access_place_url'=>array('xpath'=>'codeBook/stdyDscr/dataAccs/setAvail/accsPlac_url', 'transform_callback'=>'none'),

    'study_desc/data_access/dataset_availability/original_archive'=>array('xpath'=>'codeBook/stdyDscr/dataAccs/setAvail/origArch', 'transform_callback'=>'none'),
    'study_desc/data_access/dataset_availability/status'=>array('xpath'=>'codeBook/stdyDscr/dataAccs/setAvail/avlStatus', 'transform_callback'=>'none'),
    'study_desc/data_access/dataset_availability/coll_size'=>array('xpath'=>'codeBook/stdyDscr/dataAccs/setAvail/collSize', 'transform_callback'=>'none'),
    'study_desc/data_access/dataset_availability/complete'=>array('xpath'=>'codeBook/stdyDscr/dataAccs/setAvail/complete', 'transform_callback'=>'none'),
    'study_desc/data_access/dataset_availability/file_quantity'=>array('xpath'=>'codeBook/stdyDscr/dataAccs/setAvail/file_quantity', 'transform_callback'=>'none'),
    'study_desc/data_access/dataset_availability/notes'=>array('xpath'=>'codeBook/stdyDscr/dataAccs/setAvail/notes', 'transform_callback'=>'none'),

    #data access > data use

    #confidentiality declaration
    'study_desc/data_access/dataset_use/conf_dec'=>array(
        'xpath'=>'codeBook/stdyDscr/dataAccs/useStmt/confDec', 
        'transform_callback'=>'none', 'type'=>'array'
    ),
    /*'study_desc/data_access/dataset_use/conf_dec/form_url'=>array(
        'xpath'=>'codeBook/stdyDscr/dataAccs/useStmt/confDec/@URI', 
        'transform_callback'=>'none'
    ),*/

    #special permissions
    'study_desc/data_access/dataset_use/spec_perm'=>array(
        'xpath'=>'codeBook/stdyDscr/dataAccs/useStmt/specPerm', 
        'transform_callback'=>'none', 'type'=>'array'
    ),
    /*'study_desc/data_access/dataset_use/spec_perm/form_url'=>array(
        'xpath'=>'codeBook/stdyDscr/dataAccs/useStmt/specPerm/@URI', 
        'transform_callback'=>'none'
    ),*/

    'study_desc/data_access/dataset_use/restrictions'=>array('xpath'=>'codeBook/stdyDscr/dataAccs/useStmt/restrctn', 'transform_callback'=>'none'),
    'study_desc/data_access/dataset_use/contact'=>array('xpath'=>'codeBook/stdyDscr/dataAccs/useStmt/contact', 'transform_callback'=>'none','type'=>'array'),
    'study_desc/data_access/dataset_use/cit_req'=>array('xpath'=>'codeBook/stdyDscr/dataAccs/useStmt/citReq', 'transform_callback'=>'none'),
    'study_desc/data_access/dataset_use/deposit_req'=>array('xpath'=>'codeBook/stdyDscr/dataAccs/useStmt/deposReq', 'transform_callback'=>'none'),
    'study_desc/data_access/dataset_use/conditions'=>array('xpath'=>'codeBook/stdyDscr/dataAccs/useStmt/conditions', 'transform_callback'=>'none'),
    'study_desc/data_access/dataset_use/disclaimer'=>array('xpath'=>'codeBook/stdyDscr/dataAccs/useStmt/disclaimer', 'transform_callback'=>'none'),

    'study_desc/data_access/notes'=>array('xpath'=>'codeBook/stdyDscr/dataAccs/setAvail/notes', 'transform_callback'=>'none'),
);


//ISO19139/XML mapping to NADA geospatial JSON schema
$config['geospatial']=array(
    #docDesc elements 
    'description/idno'=>array('xpath'=>'metadata_file_identifier', 'transform_callback'=>'none'),
    'description/language'=>array('xpath'=>'metadata_lang', 'transform_callback'=>'none'),
    'description/characterSet/codeListValue'=>array('xpath'=>'metadata_charset', 'transform_callback'=>'none'),
    'description/hierarchyLevel'=>array('xpath'=>'codeBook/docDscr/citation/titlStmt/IDNo', 'transform_callback'=>'none'),
    'description/dateStamp'=>array('xpath'=>'metadata_date', 'transform_callback'=>'none'),
    'description/contact'=>array(
        'xpath'=>'metadata_contacts', 
        'transform_callback'=>'none',
        'type'=>'array'
    ),
    'description/identificationInfo/citation/title'=>array('xpath'=>'ident_title', 'transform_callback'=>'none'),
    'description/identificationInfo/citation/alternate_title'=>array('xpath'=>'ident_alternate_title', 'transform_callback'=>'none'),
    'description/identificationInfo/citation/date'=>array(
        'xpath'=>'ident_dates', 
        'transform_callback'=>'none',
        'type'=>'array'
    ),
    'description/identificationInfo/citation/edition'=>array('xpath'=>'codeBook/docDscr/citation/prodStmt/prodDate', 'transform_callback'=>'none'),
    /*'description/identificationInfo/identifiers'=>array(
        'xpath'=>'--', 
        'transform_callback'=>'none',
        'type'=>'array'
    ),*/
    'description/identificationInfo/citation/presentationForm'=>array('xpath'=>'codeBook/docDscr/citation/prodStmt/prodDate', 'transform_callback'=>'none'),
    'description/identificationInfo/abstract'=>array('xpath'=>'ident_description', 'transform_callback'=>'none'),
    'description/identificationInfo/purpose'=>array('xpath'=>'ident_purpose', 'transform_callback'=>'none'),
    'description/identificationInfo/credit'=>array('xpath'=>'ident_credit', 'transform_callback'=>'none'),
    'description/identificationInfo/status'=>array('xpath'=>'ident_status', 'transform_callback'=>'none'),
    'description/identificationInfo/graphicOverview'=>array(
        'xpath'=>'ident_graphic_overview', 
        'transform_callback'=>'none',
        'type'=>'array'
    ),
    'description/identificationInfo/pointOfContact'=>array(
        'xpath'=>'ident_contacts', 
        'transform_callback'=>'none',
        'type'=>'array'
    ),



    'description/distributionInfo/distributor'=>array(
        'xpath'=>'distributor_contacts', 
        'transform_callback'=>'none',
        'type'=>'array'
    ),

    'description/distribution_info/online_resource'=>array(
        'xpath'=>'distribution_info', 
        'transform_callback'=>'none',
        'type'=>'array'
    ),

    'description/identificationInfo/resource_maintenance'=>array(
        'xpath'=>'codeBook/docDscr/citation/prodStmt/producer', 
        'transform_callback'=>'none',
        'type'=>'array'
    ),


    'description/identificationInfo/graphic_overview'=>array(
        'xpath'=>'codeBook/docDscr/citation/prodStmt/producer', 
        'transform_callback'=>'none',
        'type'=>'array'
    ),

    'description/identificationInfo/descriptiveKeywords'=>array(
        'xpath'=>'ident_descriptive_keywords', 
        'transform_callback'=>'none',
        'type'=>'array'
    ),
    

    'description/identificationInfo/resource_contraints/legal_constraints'=>array(
        'xpath'=>'codeBook/docDscr/citation/prodStmt/producer', 
        'transform_callback'=>'none',
        'type'=>'array'
    ),

    'description/identificationInfo/resource_contraints/use_limitations'=>array('xpath'=>'codeBook/docDscr/citation/prodStmt/prodDate', 'transform_callback'=>'none'),
    'description/identificationInfo/resource_contraints/other_constraints'=>array('xpath'=>'codeBook/docDscr/citation/prodStmt/prodDate', 'transform_callback'=>'none'),


    'description/identificationInfo/spatial_representation_type'=>array('xpath'=>'codeBook/docDscr/citation/prodStmt/prodDate', 'transform_callback'=>'none'),
    'description/identificationInfo/representative_fraction_denominator'=>array('xpath'=>'codeBook/docDscr/citation/prodStmt/prodDate', 'transform_callback'=>'none'),
    'description/identificationInfo/language'=>array('xpath'=>'codeBook/docDscr/citation/prodStmt/prodDate', 'transform_callback'=>'none'),
    'description/identificationInfo/charset_code'=>array('xpath'=>'codeBook/docDscr/citation/prodStmt/prodDate', 'transform_callback'=>'none'),
    /*'description/identificationInfo/topics'=>array(
        'xpath'=>'topic_category', 
        'transform_callback'=>'none',
        'type'=>'array'
    ),*/

    'description/identificationInfo/extent/geographicElement/geographicBoundingBox'=>array(
        'xpath'=>'ident_extent_bbox', 
        'transform_callback'=>'none',
        'type'=>'array'
    ),

    'description/identificationInfo/supplemental_information'=>array('xpath'=>'codeBook/docDscr/citation/prodStmt/prodDate', 'transform_callback'=>'none'),
    
    'description/data_quality_info/scope'=>array('xpath'=>'codeBook/docDscr/citation/prodStmt/prodDate', 'transform_callback'=>'none'),
    'description/data_quality_info/lineage'=>array('xpath'=>'codeBook/docDscr/citation/prodStmt/prodDate', 'transform_callback'=>'none'),

    'description/spatial_representation_info/topology_level'=>array('xpath'=>'codeBook/docDscr/citation/prodStmt/prodDate', 'transform_callback'=>'none'),
    //[needfix in schema]
    'description/spatial_representation_info/geomatric_object_code'=>array('xpath'=>'codeBook/docDscr/citation/prodStmt/prodDate', 'transform_callback'=>'none'),

    'description/reference_system_info'=>array(
        'xpath'=>'codeBook/docDscr/citation/prodStmt/producer', 
        'transform_callback'=>'none',
        'type'=>'array'
    ),

);