<div style="width:100%;background-color:#e9ecef;">

    <div style="width:150px;padding:15px;">
        <img style="width:100px;height:100px;" src="<?php echo base_url();?>images/<?php echo $template['data_type']; ?>.png"  />
    </div>

    <div style="padding:10px;padding-top:300px;text-align:right;font-size:3em;">
        <?php echo $template['name'];?>
    </div>    
</div>

<div style="text-align:right">

    <div style="margin-top:20px;font-size:12pt;font-weight:bold;">
        <?php if (isset($template['organization'])):?>
            <?php echo $template['organization'];?>
        <?php endif;?>                
    </div>
    

    <?php if (isset($template['author'])):?>
        <div style="font-weight:bold;">
        <?php echo $template['author'];?>
        </div>
    <?php endif;?>

    <?php if (isset($template['version'])):?>
    <div style="margin-top:10px;">
        <?php echo $template['version'];?>
    </div>
    <?php endif;?>
    
    <div style="margin-top:25px;font-size:12pt;color:gray;">
        <?php echo t('Generated on');?>: <?php echo date("F j, Y",date("U")); ?>
    </div>

</div>