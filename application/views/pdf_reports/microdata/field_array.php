<?php
/**
 * 
 * Table field
 *
 *  options
 * 
 *  - hide_column_headings - hide column headings 
 */

 $hide_column_headings=false;
 $hide_field_title=false;
 $columns=NULL;

 if (isset($options[$name])){
    $options=$options[$name];
}

 if(isset($options['hide_column_headings'])){
     $hide_column_headings=$options['hide_column_headings'];
 }
 
 if(isset($options['columns'])){
    $columns=$options['columns'];
}

 if(isset($options['hide_field_title'])){
    $hide_field_title=$options['hide_field_title'];
 }

 

?>
<?php if (isset($data) && is_array($data) && count($data)>0 ):?>
<div class="table-responsive field field-<?php echo str_replace(".","__",$name);?>">
    <?php if ($hide_field_title!=true):?>
    <div class="xsl-caption field-caption"><?php echo t($name);?></div>
    <?php endif;?>
    <div class="field-value">                
        <?php if (isset($data[0]) && is_array($data[0])):?>
        <?php            
            if(!isset($columns)){
             $columns=array_keys($data[0]);
             foreach($data as $row){                 
                 foreach(array_keys($row) as $row_col){
                    if (!in_array($row_col,$columns)){
                        $columns[]=$row_col;
                    }
                 }                 
             }
            }

            //remove empty columns
            $non_empty_columns=array();            
            foreach($columns as $column){
                $column_data=array_filter(array_column($data, $column));
                if(!empty($column_data)){
                    $non_empty_columns[]=$column;
                }
            }
            $columns=$non_empty_columns;
        ?>
        <div class="table-responsive table-overflow-max-height-400">
        <table class="table table-bordered table-striped table-condensed xsl-table table-grid">
            <?php if($hide_column_headings!==true):?>
            <tr>
                <?php foreach($columns as $column_name):?>
                    <th><?php echo t($name.'.'.$column_name);?></th>
                <?php endforeach;?>
            </tr>
            <?php endif;?>
            
            <?php foreach($data as $row):?>
                <tr>
                    
                    <?php foreach($columns as $column_name):?>                        
                        <td>
                            <?php if(empty($row[$column_name])){continue;}?>    
                            <?php if(is_array($row[$column_name])):?>
                            <?php echo render_field($field_type='array_badge',$field_name=$name.'.'.$column_name,$row[$column_name], array('hide_column_headings'=>true));?>
                            <?php else:?>
                                <?php if(is_url($row[$column_name])):?>
                                     <a target="_blank" href="<?php echo html_escape($row[$column_name]);?>"><i class="fas fa-external-link-alt"></i> <?php echo t('Link');?></a>
                                <?php else:?>
                                    <?php echo $row[$column_name];?>
                                <?php endif;?>
                            <?php endif;?>    
                        </td>
                    <?php endforeach;?>                    
                    <?php  /*
                    <?php foreach($row as $key=>$value):?>
                        <?php if(!in_array($key,$columns)){continue;}?>
                        <td>
                            <?php if(is_array($value)):?>
                            <?php echo render_field($field_type='array_br',$field_name=$name.'.'.$key,$value, array('hide_column_headings'=>true));?>
                            <?php else:?>
                                <?php echo $value;?>
                            <?php endif;?>    
                        </td>
                    <?php endforeach;?>                    
                    */ ?>
                </tr>
            <?php endforeach;?>
        </table>
        </div>
        <?php else:?>
        <table class="table xsl-table table-grid">            
               <?php foreach($data as $row):?>
               <tr>
                <td><?php echo $row;?></td>
               </tr>
               <?php endforeach;?>
        </table>
        <?php endif;?>

    </div>
</div>
<?php endif;?>
