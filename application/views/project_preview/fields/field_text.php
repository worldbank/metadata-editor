<?php
    $show_empty=false;
    if(isset($options['show_empty'])){
        $show_empty=$options['show_empty'];
    }
?>
<?php if ( (isset($data) && $data !== '' && $data !== null) || $show_empty==true ):?>
<div class="preview-field" id="<?php echo escape_html_attribute($template['key']);?>">
    <div class="field-label"><?php echo html_escape($template['title']);?></div>
    <div class="field-value">
        <?php if (is_array($data)):?>
        <?php foreach($data as $value):?>
            <?php if (is_array($value)){
                $value=implode(" ",$value);
            }?>
            <span id="<?php echo escape_html_attribute($template['key']);?>_value"><?php echo nl2br(html_escape(trim($value)));?></span>
        <?php endforeach;?>
        <?php else:?>
            <?php if($data !== '' && $data !== null):?>
                <span id="<?php echo escape_html_attribute($template['key']);?>_value"><?php echo nl2br(html_escape(trim((string)$data)));?></span>
            <?php else: ?>
                -
            <?php endif;?>

        <?php endif;?>
    </div>
</div>
<?php endif;?>
