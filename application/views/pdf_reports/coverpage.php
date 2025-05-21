<?php /*<div style="text-align:right;margin-top:200px;">
<?php echo $project['title'];?>
</div>
*/ ?>

<div style="width:100%;background-color:#0969da;">

    <div style="padding:10px;padding-top:300px;text-align:right;font-size:3em;color:white;">
        <?php echo $project['title'];?>
    </div>

    
</div>

<div style="text-align:right">

    <div style="margin-top:20px;font-size:12pt;color:#0969da;font-weight:bold;">
    <?php echo $project['idno']; ?>
    </div>
    
    <div style="margin-top:5px;font-size:12pt;color:gray;">
        <?php echo t('Report generated on');?>: <?php echo date("F j, Y",date("U")); ?>
        <div style="margin-top:15px;">Project type: <?php echo $project['type'] =='survey' ? 'Microdata' : $project['type']; ?></div>
    </div>

    <div style="margin-top:50px;font-size:12pt;color:gray;">
    <?php //echo t('visit_data_catalog_at');?>: <?php //echo anchor($website_url);?>
    </div>

</div>