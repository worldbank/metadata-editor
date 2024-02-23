<?php foreach($parent_item['props'] as $item):?>

    <div class="prop-details" id="<?php echo str_replace(".","-",$parent_item['key'].'.'.$item['key']);?>" >
        
        <div class="mt-3">            
            <h4><?php echo $item['title']; ?></h4>
            <span class="badge badge-primary" title="Type"><?php echo $item['type']; ?></span>
            <span class="badge badge-light" title="Key"><?php echo $parent_item['key'].'.'.$item['key']; ?></span>
        </div>

        <?php /*
        <?php foreach(array("help_text") as $field):?>
            <?php if (isset($item[$field]) && trim($item[$field])!=='') : ?>
                <div class="field-info field-<?php echo $field;?>">
                    <div class="label"><?php echo $field;?></div>
                    <div class="field-value"><?php echo nl2br($item[$field]);?></div>
                </div>
            <?php endif; ?>
        <?php endforeach;?>
        */?>

        <?php if (isset($item['help_text']) && trim($item['help_text'])!=='') : ?>
            <div class="field-info field-help_text bg-light p-3 mb-3">                
                <div class="field-value"><?php echo nl2br($item['help_text']);?></div>
            </div>
        <?php endif; ?>


        <?php if (isset($item['props'])) : ?>
            <div class="border bg-light pl-5 nested-prop-prop">
            <?php echo $this->load->view('templates/props_info', array('parent_item' => $item), true); ?>
            </div>
        <?php endif; ?>
        

    </div>

<?php endforeach;?>
