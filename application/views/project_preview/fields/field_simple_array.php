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
        <div class="field-label"><?php echo t($template['title']);?></div>
    <?php endif;?>
    <div class="field-value">                        
        <ul>
        <?php foreach($data as $row):?>
            <li><?php echo $row;?></li>
        <?php endforeach;?>
        </ul>        
    </div>
</div>

<?php endif;?>