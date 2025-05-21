<?php if (trim($output)!=""):?>    
    <div class="section-<?php echo $section_name;?>">
        <?php if (trim($section_name)!=""):?>
            <h2 id="metadata-<?php echo $section_name;?>" class="field-section mt-3"><?php echo t($section_name);?></h2>
        <?php endif;?>
        <?php echo $output;?>
    </div>
    
<?php endif;?>
