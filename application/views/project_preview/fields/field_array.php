<?php if (isset($data) && is_array($data) && count($data)>0 ):?>
<?php
/**
 * 
 * Table field
 *
 *  options
 * 
 *  - hide_column_headings - hide column headings 
 */

$columns=$template['props'];
$name=$template['title'];
$hide_field_title=isset($template['hide_field_title']) ? $template['hide_field_title'] : false;
$hide_column_headings=isset($template['hide_column_headings']) ? $template['hide_column_headings'] : false;

//remove empty columns
$non_empty_columns=array();            
foreach($columns as $column){
    $column_data=array_filter(array_column($data, $column['key']));
    if(!empty($column_data)){
        $non_empty_columns[]=$column;
    }
}

if (count($non_empty_columns)<1){
    return false;
}

$columns=$non_empty_columns;
$data= array_remove_empty($data);

//remove empty rows
/*foreach($data as $idx=>$row){
    $row=array_filter($row);
    if (empty($row)){
        unset($data[$idx]);
    }
}*/
?>
<?php if (count($data)<1 ){return false;} ?>
<div class="table-responsive field field-<?php echo str_replace(".","_",$template['key']);?>">
<?php if ($hide_field_title!=true):?>
    <h4 class="field-caption"><?php echo t($template['title']);?></h4>
<?php endif;?>
<table class="table table-sm table-bordered table-striped table-condensed xsl-table table-grid">
    <?php if ($hide_column_headings!=true):?>
    <tr>
        <?php foreach($columns as $column):?>
        <th><?php echo $column['title'];?></th>
        <?php endforeach;?>
    </tr>
    <?php endif;?>
    <?php foreach($data as $row):?>
    <tr>
        <?php foreach($columns as $column):?>
        <td>            
            <?php if (in_array($column['type'],array('array','nested_array','simple_array'))):?>
                <?php 
                    $column['hide_column_headings']=true;
                    $column['hide_field_title']=true;
                    $display_field=isset($template['display_field']) ? $template['display_field'] : '';
                ?>
                <?php  echo $this->load->view('project_preview/fields/field_array',array('data'=>isset($row[$column['key']]) ? $row[$column['key']] : [] ,'template'=>$column),true);?>
            <?php else:?>
                <?php echo isset($row[$column['key']]) ? $row[$column['key']] : '';?>
            <?php endif;?>
        </td>
        <?php endforeach;?>
    </tr>
    <?php endforeach;?>    
</table>
</div>

<?php endif;?>