<style>
    .fld-inline .fld-name{color:gray;}
    .fld-container,.clear{clear:both;}

    .var-breadcrumb{
        list-style:none;
        clear:both;
        margin-bottom:25px;
        color:gray;
    }

    .var-breadcrumb li{display:inline;}
    .variables-container .bar-container {min-width:150px;}
    .xsl-warning{display:none}
    h2,
    .xsl-subtitle{font-size:14px!important;}
    .variable-title{border-left:8px solid gainsboro;padding-left:5px;}
</style>

<?php if(empty($variables)){return;}?>

<?php foreach($variables as $variable):?>    
<div class="variable-container">
    <h2 class="variable-title"><?php echo strtoupper($variable['name']). ': '.$variable['labl'];?></h2>
    <strong><?php echo t('data_file');?>: <?php echo $file['file_name'];?></strong>
    
    <h3 class="xsl-subtitle"><?php echo t('overview');?></h3>

    <div class="xrow">
    <?php if(isset($variable['var_sumstat'])):?>
        
            <?php foreach($variable['var_sumstat'] as $sumstat): $sumstat=(object)$sumstat; ?>
                <?php $wgtd=isset($sumstat->wgtd) && $sumstat->wgtd=='wgtd' ? '_wgtd' : '';?>
                <span class="fld-inline sum-stat sum-stat-<?php echo $sumstat->type;?>-<?php echo $wgtd;?>">
                    <span class="fld-name sum-stat-type"><?php echo t('var_'.$sumstat->type. $wgtd);?>: </span>
                    <span class="fld-value sum-stat-value" ><?php echo $sumstat->value;?></span>
                    <span>&nbsp;&nbsp;&nbsp;&nbsp;</span>
                </span>
            <?php endforeach;?>
        
    <?php endif;?>

    <!--other stats-->
    <?php
    $stat_keys=array("var_intrvl","var_dcml","var_loc_start_pos","var_loc_end_pos","loc_width");
    ?>
        
    <div class="col-md-6">
        <?php foreach($stat_keys as $stat_key):?>
            <?php if (array_key_exists($stat_key,$variable) && $variable[$stat_key]!==null ):?>
            <?php $stat=$variable[$stat_key];?>
            <span class="fld-inline sum-stat sum-stat-<?php echo $stat_key;?>">
                <span class="fld-name sum-stat-type"><?php echo t($stat_key);?>: </span>
                <span class="fld-value sum-stat-value"><?php echo t($stat);?></span>
                <span>&nbsp;&nbsp;&nbsp;&nbsp;</span>
            </span>
            <?php endif;?>
        <?php endforeach;?>

        <?php if (isset($variable['var_val_range'])):?>
        <span class="fld-inline sum-stat sum-stat-range">
            <span class="fld-name sum-stat-type"><?php echo t('var_range');?>: </span>
                <?php $range=$variable['var_val_range'];?>
                <?php  $range=(object)$range; ?>
                <span class="fld-value sum-stat-value">
                <?php echo @$range->min;?> - <?php echo @$range->max;?>
            </span>
            <span>&nbsp;&nbsp;&nbsp;&nbsp;</span>
        </span>
        <?php endif;?>
        
        <?php if (isset($variable['var_format'])):?>
        <span class="fld-inline sum-stat var-format">
            <span class="fld-name var-format-fld"><?php echo t('var_format');?>: </span>
            <?php $format=$variable['var_format'];?>
            <?php  $format=(object)$format; ?>
            <span class="fld-value format-value"><?php echo t(@$format->type);?></span>
            <span>&nbsp;&nbsp;&nbsp;&nbsp;</span>
        </span>
        <?php endif;?>

        <?php if (isset($variable['var_is_wgt'])  && $variable['var_is_wgt']=='wgt' ):?>
        <span class="fld-inline sum-stat var_is_wgt">
            <span class="fld-name var-fld-var_is_wgt"><?php echo t('var_is_wgt');?>: </span>
            <?php $var_is_wgt=$variable['var_is_wgt'];?>
            <span class="fld-value var_is_wgt-value"><?php echo t('yes');?></span>
            <span>&nbsp;&nbsp;&nbsp;&nbsp;</span>
        </span>
        <?php endif;?>

        <?php if (isset($variable['var_wgt'])):?>
        <span class="fld-inline sum-stat var_wgt">
            <span class="fld-name var-fld-var_wgt"><?php echo t('var_wgt');?>: </span>
            <?php $var_wgt=$variable['var_wgt'];?>
            <span class="fld-value var_wgt-value"><?php echo $var_wgt;?></span>
            <span>&nbsp;&nbsp;&nbsp;&nbsp;</span>
        </span>
        <?php endif;?>

    </div>
    </div>

    
    <div class="clear"></div>
    

    <!-- data_collection -->
    <?php echo render_group('questions_n_instructions',
        $fields=array(
            "var_qstn_preqtxt"=>'text',
            "var_qstn_qstnlit"=> 'text',
            "var_catgry"=>'var_category',
            "var_qstn_ivuinstr"=>'text',            
            "var_qstn_postqtxt"=>'text',
            "var_qstn_ivulnstr"=>'text'
        ),
        $variable);
    ?>


    <!-- description -->
    <?php echo render_group('description',
        $fields=array(
            "var_txt"=>'text',
            "var_universe"=>'text',
            "var_resp_unit"=>'text'
        ),
        $variable);
    ?>

    <?php echo render_group('concept',
        $fields=array(
            "var_concept"=>'array'
        ),
        $variable);
    ?>


    <?php echo render_group('imputation_n_derivation',
        $fields=array(
            "var_imputation"=>'text',
            "var_codinstr"=>'text'
        ),
        $variable);
    ?>

    <?php echo render_group('others',
        $fields=array(
            "var_security"=>'text',
            "var_notes"=>'text'
        ),
        $variable);
    ?>

<!--end-container-->
</div>

<line-break/>
<?php endforeach;?>
