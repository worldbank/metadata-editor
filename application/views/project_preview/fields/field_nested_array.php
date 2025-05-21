<?php 
/**
 * 
 * nested repeatd field
 *
 *  options
 * 
 *  - hide_column_headings - hide column headings 
 */

 $columns=$template['props'];
 $name=$template['title'];
 $hide_field_title=false;
 $hide_column_headings=false;
?>

<?php if ($hide_field_title!=true):?>
    <h4 class="field-caption"><?php echo t($template['title']);?></h4>
<?php endif;?>
<div class="table-responsive field field-<?php echo str_replace(".","_",$template['key']);?>">
<table class="table table-bordered table-striped table-condensed xsl-table table-grid">
    <tr>
        <?php foreach($columns as $column):?>            
        <th><?php echo $column['title'];?></th>
        <?php endforeach;?>
    </tr>
    <?php foreach($data as $row):?>
    <tr>
        <?php foreach($columns as $column):?>        
        <td>
            <?php if (in_array($column['type'],array('array','nested_array','simple_array'))):?>
                <?php 
                    $column['hide_column_headings']=true;
                    $column['hide_field_title']=true;
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