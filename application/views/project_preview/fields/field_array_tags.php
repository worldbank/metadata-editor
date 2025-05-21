<?php
/**
 * 
 * Array to comma seperated list
 *
 *  options
 * 
 *  - hide_column_headings - hide column headings 
 *  - badge class - a single or multiple classes 
 */

 $columns=$template['props'];
 $name=$template['title'];
 $hide_field_title=isset($template['hide_field_title']) ? $template['hide_field_title'] : false;
 $badge_class="badge badge-pill badge-light";

 if(isset($options['badge_class'])){
    $badge_class=$options['badge_class'];
 }
?>
<?php if (isset($data) && is_array($data) && count($data)>0 ):?>
<div class="field-<?php echo $name;?>">
    <?php if($hide_column_headings!==true):?>
        <h4 class="field-caption"><?php echo t($name);?></h4>
    <?php endif;?>
    <div class="field-value">                        
        <?php foreach($data as $row):?>
            <span class="<?php echo $badge_class;?>"><?php echo $row;?></span>
        <?php endforeach;?>
    </div>
</div>
<?php endif;?>