<?php
/*
 * DDI template - display metadata for DDI fields
 *
 * @metadata - array containing all metadata
 *
 * @id - survey id
 * @surveyid - IDNO
 * @ all survey table fields
 * @section = array - names of sections 
 *
 *
 **/
?>


<?php 
    //rendered html for all sections
    $output=array();
?>





<!-- identification section -->
<?php $output['identification']= render_group('identification',
    $fields=array(
            "idno"=>'text',
            "title"=>'text',
            "metadata.study_desc.title_statement.sub_title"=>'text',            
            "metadata.study_desc.title_statement.alternate_title"=>'text',
            "metadata.study_desc.title_statement.translated_title"=>'text',
            "metadata.study_desc.study_info.nation"=>'array',
            //"metadata.study_desc.study_info.nation"=>'array_badge',
            //"metadata.study_desc.study_info.nation"=>'array_comma',
            "metadata.study_desc.geog_units"=>'array',
            "metadata.study_desc.series_statement.series_name"=>'text',
            "metadata.iframe_embeds"=>'iframe_embed',
            "metadata.study_desc.series_statement.series_info"=>'text',            
            "metadata.study_desc.study_info.abstract"=>'text',
            "metadata.study_desc.study_info.data_kind"=>'text',
            "metadata.study_desc.study_info.analysis_unit"=>'text'
    ),
    $metadata);
?>


<!-- version -->
<?php $output['version']= render_group('version',
    $fields=array(
            "metadata.study_desc.version_statement.version"=>'text',
            "metadata.study_desc.version_statement.version_date"=>'text',
            "metadata.study_desc.version_statement.version_notes"=>'text'
            ),
    $metadata);
?>


<!-- scope -->
<?php $output['scope']= render_group('scope',
    $fields=array(
            "metadata.study_desc.study_info.notes"=>'text',
            "metadata.study_desc.study_info.topics"=>'array',
            "metadata.study_desc.study_info.keywords"=>'array'
            ),
    $metadata);
?>


<!-- coverage -->
<?php $output['coverage']= render_group('coverage',
    $fields=array(
            "metadata.study_desc.study_info.geog_coverage"=>'text',
            "metadata.study_desc.study_info.geog_coverage_notes"=>'text',
            "metadata.study_desc.study_info.geog_unit"=>'text',            
            "metadata.study_desc.study_info.universe"=>'text'
            ),
    $metadata);
?>


<!-- producers_sponsors -->
<?php $output['producers_sponsors']= render_group('producers_sponsors',
    $fields=array(
            "metadata.study_desc.authoring_entity"=>'array',
            "metadata.study_desc.production_statement.producers"=>'array',
            "metadata.study_desc.production_statement.funding_agencies"=>'array',
            "metadata.study_desc.oth_id"=>'array'
            ),
    $metadata);
?>


<!-- sampling -->
<?php $output['sampling']= render_group('sampling',
    $fields=array(
            "metadata.study_desc.method.data_collection.sampling_procedure"=>'text',
            "metadata.study_desc.method.data_collection.sampling_deviation"=>'text',
            "metadata.study_desc.method.analysis_info.response_rate"=>'text',
            'metadata.study_desc.method.data_collection.weight'=>'text'
            ),
    $metadata);
?>


<!-- data_collection -->
<?php $output['data_collection']= render_group('data_collection',
    $fields=array(
            "metadata.study_desc.study_info.coll_dates"=>'array',
            "metadata.study_desc.method.data_collection.frequency"=>'text',
            "metadata.study_desc.study_info.time_periods"=>'array',
            "metadata.study_desc.method.data_collection.sources.data_source"=>'array_list',
            "metadata.study_desc.method.data_collection.sources.source_origin"=>'text',
            "metadata.study_desc.method.data_collection.coll_mode"=>'text',
            "metadata.study_desc.method.data_collection.act_min"=>'text',
            "metadata.study_desc.method.data_collection.coll_situation"=>'text',            
            //"metadata.study_desc.method.data_collection.research_instrument"=>'text',
            "metadata.study_desc.method.data_collection.data_collectors"=>'array',
            ),
    $metadata);
?>


<!-- questionnaires -->
<?php $output['questionnaires']= render_group('questionnaires',
    $fields=array(
            "metadata.study_desc.method.data_collection.research_instrument"=>'text',
            ),
    $metadata);
?>


<!-- data_processing -->
<?php $output['data_processing']= render_group('data_processing',
    $fields=array(
            "metadata.study_desc.method.data_collection.cleaning_operations"=>'text',
            "metadata.study_desc.method.data_collection.method_notes"=>'text'
            ),
    $metadata);
?>


<!-- data_appraisal -->
<?php $output['data_appraisal']= render_group('data_appraisal',
    $fields=array(
        "metadata.study_desc.method.analysis_info.sampling_error_estimates"=>'text',    
        "metadata.study_desc.method.analysis_info.data_appraisal"=>'text'
            ),
    $metadata);
?>


<!-- data_access -->
<?php $output['data_access']= render_group('data_access',
    $fields=array(
            "metadata.study_desc.distribution_statement.contact"=>'array',
            "metadata.study_desc.data_access.dataset_use.conf_dec"=>'array_comma',
            "metadata.study_desc.data_access.dataset_use.conf_dec.form_url"=>'text',
            "metadata.study_desc.data_access.dataset_use.conditions"=>'text',
            "metadata.study_desc.data_access.dataset_use.restrictions"=>'text',
            "metadata.study_desc.data_access.dataset_use.cit_req"=>'text',
            "metadata.study_desc.data_access.dataset_use.contact"=>'array',
            "metadata.study_desc.data_access.dataset_use.deposit_req">'text',
            "metadata.study_desc.data_access.dataset_availability.access_place"=>'text',
            "metadata.study_desc.data_access.dataset_availability.original_archive"=>'text', 
            "metadata.study_desc.data_access.dataset_availability.availability_status"=>'text',            
            ),
    $metadata);
?>


<!-- disclaimer_copyright -->
<?php $output['disclaimer_copyright']= render_group('disclaimer_copyright',
    $fields=array(
            "metadata.study_desc.data_access.dataset_use.disclaimer"=>'text',
            "metadata.study_desc.production_statement.copyright"=>'text'
            ),
    $metadata);
?>


<!-- metadata_production -->
<?php $output['metadata_production']= render_group('metadata_production',
    $fields=array(
            "metadata.doc_desc.idno"=>'text',
            "metadata.doc_desc.producers"=>'array',
            "metadata.doc_desc.prod_date"=>'text',
            "metadata.doc_desc.version_statement.version"=>'text',
            ),
    $metadata);
?>


<?php if (isset($sections) && count($sections)>0):?>
    <!-- show only selected sections -->
    <div class="metadata-sections-container mb-5">
    <?php foreach($sections as $section):?>
        <?php if(isset($output[$section])):?>
            <?php echo $output[$section];?>
        <?php endif;?>
    <?php endforeach;?>
    </div>
<?php else:?>
    <!-- sidebar with section links -->
    <div class="col-sm-2 col-lg-2  d-none d-sm-block">
        <div class="navbar-collapse sticky-top">
            <ul class="navbar-nav flex-column wb--full-width">
                <?php foreach($output as $key=>$value):?>
                    <?php if(trim($value)!==""):?>
                    <li class="nav-item">
                        <a href="<?php echo current_url();?>#metadata-<?php echo $key;?>"><?php echo t($key);?></a>
                    </li>
                    <?php endif;?>
                <?php endforeach;?>
            </ul>
        </div>
    </div>
    <!--metadata content-->
    <div class="col-12 col-sm-10 col-lg-10 wb-border-left">
        <?php echo implode('',$output);?>
        <?php //echo html_entity_decode(implode('',$output));?>
    </div>
<?php endif;?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jQuery-linkify/2.1.8/linkify.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jQuery-linkify/2.1.8/linkify-jquery.min.js"></script>

<script>
    $(function() {
        $(".metadata-container").linkify();
    });
</script>    