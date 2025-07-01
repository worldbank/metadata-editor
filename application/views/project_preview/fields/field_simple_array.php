<?php if (isset($data) && is_array($data) && count($data)>0 ):?>
<?php
/**
 * 
 * Array list
 *
 *  options
 * 
 */

 $hide_column_headings=false;
 $data= array_remove_empty($data);
?>
<div class="table-responsive field field-<?php echo $template['title'];?>">
    <?php if($hide_column_headings!==true):?>
        <h4 class="field-label"><?php echo t($template['title']);?></h4>
    <?php endif;?>
    <div class="field-value">                        
        <ul>
        <?php foreach($data as $row):?>
            <li><?php echo html_escape($row);?></li>
        <?php endforeach;?>
        </ul>        
    </div>
</div>

<?php endif;?>