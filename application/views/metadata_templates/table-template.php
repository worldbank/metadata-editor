<?php
/*
 * Table data type template
 *
 * @metadata - array containing all metadata
 *
 *
 *
 **/
?>

<style>
    .field-metadata__table_description__time_periods .field-value{
        max-height:300px;
        overflow-y:auto;
    }    
</style>

<?php 
    //rendered html for all sections
    $output=array();
?>


<?php /*$output['']= render_group('',
    $fields=array(
            "metadata.resources"=>'resources_download_buttons'),
    $metadata);
    */
?>
    

<!-- identification section -->
<?php $output['description']= render_group('description',
    $fields=array(
            "metadata.table_description.title_statement.title"=>'text',
            "metadata.table_description.title_statement.sub_title"=>'text',
            "metadata.table_description.title_statement.alternate_title"=>'text',
            "metadata.table_description.title_statement.translated_title"=>'text',
            "metadata.table_description.title_statement.idno"=>'text',
            "metadata.resources"=>'resources',
            "metadata.table_description.id_numbers"=>'array',
            
            "metadata.iframe_embeds"=>'iframe_embed',

            "metadata.table_description.links"=>'array',
            
            "metadata.table_description.description"=>'text',
            "metadata.table_description.date_produced"=>'text',
            "metadata.table_description.date_released"=>'text',
            "metadata.table_description.date_changed"=>'text',

            "metadata.table_description.version"=>'text',
            "metadata.table_description.data_sources"=>'array',
            "metadata.table_description.time_periods"=>'array',
            "metadata.table_description.table_series"=>'array',
            "metadata.table_description.ref_country"=>'array',            
            
            "metadata.table_description.authoring_entity"=>'array',            
            "metadata.table_description.contributor"=>'array',
            "metadata.table_description.publisher"=>'array',

            "metadata.table_description.languages"=>'array',
            "metadata.table_description.geographic_granularity"=>'text',              
            "metadata.table_description.statistics"=>'text',
            "metadata.table_description.unit_observation"=>'array_comma',
            "metadata.table_description.universe"=>'text',

            "metadata.table_description.table_columns"=>'array',
            "metadata.table_description.table_rows"=>"array",
            /*"metadata.table_description.table_rows"=>array(
                'array',
                'options'=>array(
                    'hide_column_headings'=>false,
                    'columns'=>array('label','var_name','dataset')
                )
            ),*/

            "metadata.table_description.keywords"=>'array',
            "metadata.table_description.themes"=>'array',
            "metadata.table_description.topics"=>'array',
            "metadata.table_description.definitions"=>'array',
            "metadata.tags"=>'array_comma',
            
            "metadata.table_description.data_years_range"=>'array',
            "metadata.table_description.data_years_list"=>'array_comma',

            "metadata.table_description.geographic_units"=>'array',
            
            
            "metadata.table_description.publications"=>'array',
            
            "metadata.table_description.rights"=>'text',
            "metadata.table_description.license"=>'array',
            
            "metadata.table_description.confidentiality"=>'text',
            "metadata.table_description.notes"=>'text',
            "metadata.table_description.relations"=>'array',
            "metadata.table_description.citation"=>'text'            
    ),
    $metadata,
    $options=array(
            "metadata.table_description.table_rows"=>array(
                'hide_column_headings'=>false,
                'columns'=>array('label','var_name','dataset')
            ),
            "metadata.table_description.table_columns"=>array(
                'hide_column_headings'=>false,
                'columns'=>array('label','var_name','dataset')
            )
    )
);
?>


<!-- additional items -->
<?php if (isset($metadata['metadata']['additional'])):?>
<?php   
    $additional_fields=array();
    foreach ($metadata['metadata']['additional'] as $item_key=>$value){
        $additional_fields['metadata.additional.'.$item_key]=is_array($value) ? 'array' : 'text';
    }
    $output['additional']= render_group('additional',$fields=$additional_fields,$metadata);
?>
<?php endif;?>



<!-- disclaimer_copyright -->
<?php $output['disclaimer_copyright']= render_group('disclaimer_copyright',
    $fields=array(
            "metadata.table_description.data_access.dataset_use.disclaimer"=>'text',
            "metadata.table_description.production_statement.copyright"=>'text'
            ),
    $metadata);
?>


<!-- metadata_production -->
<?php $output['metadata_production']= render_group('metadata_production',
    $fields=array(
        "metadata.metadata_information.producers"=>'array',    
        "metadata.metadata_information.production_date"=>'text',
        "metadata.metadata_information.version"=>'text',
        "metadata.metadata_information.idno"=>'text',
            ),
    $metadata);
?>

<?php 
    //items not to be included in the left side bar
    $exclude_sidebar_items=array('download_links');
    //renders html
    $this->load->view('metadata_templates/metadata_output', array('output'=>$output, 'exclude_sidebar_items'=>$exclude_sidebar_items));
?>    
