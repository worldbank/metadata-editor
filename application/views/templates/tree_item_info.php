<?php foreach($parent_item['items'] as $item):?>

    <div class="item-details mb-5" id="<?php echo str_replace(".","-",$item['key']);?>" >
        <div class="mt-3">
            <?php if ($item['type'] == 'section') : ?>
                <h2><?php echo $item['title']; ?></h2>
            <?php else: ?>
                <h3><?php echo $item['title']; ?>
                <?php if (isset($item['is_required']) && $item['is_required']===true) : ?>
                    <sup title="Required" style="color:red;">M</sup>
                <?php endif; ?>
                </h3>
            <?php endif; ?>
                            
            <span class="badge badge-primary" title="Type"><?php echo $item['type']; ?></span>
            <span class="badge badge-light" title="Key"><?php echo $item['key']; ?></span>

            <span class="ml-2">
                <?php if (isset($item['is_required']) && $item['is_required']===true) : ?>                    
                    <span class="badge badge-danger text-required" title="Required">Required</span>
                <?php endif; ?>

                <?php if (isset($item['is_recommended']) && $item['is_recommended']===true && !isset($item['is_required'])) : ?>                    
                    <span class="badge badge-warning text-recommended" title="Recommended">Recommended</span>
                <?php endif; ?>
            </span>
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
            <div class="field-props">
            <div><strong>Array properties</strong></div>
                <?php echo $this->load->view('templates/props_info', array('parent_item' => $item), true); ?>
            </div>
        <?php endif; ?>


        <?php if (isset($item['enum']) && is_array($item['enum']) && count($item['enum'])>0) : ?>
            <div class="field-enum">

                <div class="field-info mt-3">
                    <div><strong>Controlled vocabulary</strong></div>
                    <div class="field-value">                    
                        <?php echo $this->load->view('templates/enum_info', array('values'=>$item['enum'], 'item'=>$item), true); ?>                    
                    </div>
                </div>                
            </div>
        <?php endif; ?>

        <?php if (isset($item['rules']) && is_array($item['rules']) && count($item['rules'])>0  ) : ?>
            <div class="field-rules field-info mt-2">
                <div><strong>Validation rules</strong></div>    
                <div class="field-value">
                    <?php echo $this->load->view('templates/validation_rules_info', array('rules'=>$item['rules']), true); ?>
                </div>
            </div>
        <?php endif; ?>


        <?php if (isset($item['items'])) : ?>
            <div class="field-items pl-2 border-left">
                <?php echo $this->load->view('templates/tree_item_info', array('parent_item' => $item), true); ?>
            </div>
        <?php endif; ?>

    </div>

<?php endforeach;?>
