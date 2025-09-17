<?php
/**
 * Variables DDI view for HTML reports
 * Shows detailed variable information using template translations
 * Adapted from pdf_reports/microdata/variables_ddi.php
 */
?>


<?php if(empty($variables)){return;}?>

<?php foreach($variables as $variable):?>    
<div style="margin-bottom:30px;border-bottom:1px solid #eee;padding-bottom:20px;" class="variable-details-section">
    <h2 style="border-left:8px solid gainsboro;padding-left:5px;"><?php echo strtoupper($variable['name']). ': '.$variable['labl'];?></h2>
    <strong><?php echo $html_report->get_template_translation('data_file', 'Data file');?>: <?php echo $file['file_name'];?></strong>
    
    <h3 style="font-size:14px;"><?php echo $html_report->get_template_translation('overview', 'Overview');?></h3>

    <div style="margin-bottom:15px;">
    <?php if(isset($variable['var_sumstat'])):?>
        
            <?php foreach($variable['var_sumstat'] as $sumstat): $sumstat=(object)$sumstat; ?>
                <?php $wgtd=isset($sumstat->wgtd) && $sumstat->wgtd=='wgtd' ? '_wgtd' : '';?>
                <span style="margin-right:15px;">
                    <span style="color:gray;"><?php echo $html_report->get_template_translation($sumstat->type. $wgtd, $sumstat->type);?>: </span>
                    <span><?php echo $sumstat->value;?></span>
                    <span>&nbsp;&nbsp;&nbsp;&nbsp;</span>
                </span>
            <?php endforeach;?>
        
    <?php endif;?>

    <!--other stats-->
    <?php
    $stat_keys=array("var_intrvl","var_dcml","var_loc_start_pos","var_loc_end_pos","loc_width");
    ?>
        
    <div style="width: 50%; float: left;">
        <?php foreach($stat_keys as $stat_key):?>
            <?php if (array_key_exists($stat_key,$variable) && $variable[$stat_key]!==null ):?>
            <?php $stat=$variable[$stat_key];?>
            <span style="margin-right:20px;margin-bottom:5px;">
                <span style="color:gray;"><?php echo $html_report->get_template_translation('variable.'.$stat_key, $stat_key);?>: </span>
                <span><?php echo $html_report->get_template_translation($stat, $stat);?></span>
                <span>&nbsp;&nbsp;&nbsp;&nbsp;</span>
            </span>
            <?php endif;?>
        <?php endforeach;?>

        <?php if (isset($variable['var_val_range'])):?>
        <span style="margin-right:20px;margin-bottom:5px;">
            <span style="color:gray;"><?php echo $html_report->get_template_translation('var_range', 'Range');?>: </span>
                <?php $range=$variable['var_val_range'];?>
                <?php  $range=(object)$range; ?>
                <span>
                <?php echo @$range->min;?> - <?php echo @$range->max;?>
            </span>
            <span>&nbsp;&nbsp;&nbsp;&nbsp;</span>
        </span>
        <?php endif;?>
        
        <?php if (isset($variable['var_format'])):?>
        <span style="margin-right:20px;margin-bottom:5px;">
            <span style="color:gray;"><?php echo $html_report->get_template_translation('var_format', 'Format');?>: </span>
            <?php $format=$variable['var_format'];?>
            <?php  $format=(object)$format; ?>
            <span><?php echo $html_report->get_template_translation(@$format->type, @$format->type);?></span>
            <span>&nbsp;&nbsp;&nbsp;&nbsp;</span>
        </span>
        <?php endif;?>

        <?php if (isset($variable['var_is_wgt'])  && $variable['var_is_wgt']=='wgt' ):?>
        <span style="margin-right:20px;margin-bottom:5px;">
            <span style="color:gray;"><?php echo $html_report->get_template_translation('var_is_wgt', 'Is Weight');?>: </span>
            <?php $var_is_wgt=$variable['var_is_wgt'];?>
            <span><?php echo $html_report->get_template_translation('yes', 'Yes');?></span>
            <span>&nbsp;&nbsp;&nbsp;&nbsp;</span>
        </span>
        <?php endif;?>

        <?php if (isset($variable['var_wgt'])):?>
        <span style="margin-right:20px;margin-bottom:5px;">
            <span style="color:gray;"><?php echo $html_report->get_template_translation('var_wgt', 'Weight');?>: </span>
            <?php $var_wgt=$variable['var_wgt'];?>
            <span><?php echo $var_wgt;?></span>
            <span>&nbsp;&nbsp;&nbsp;&nbsp;</span>
        </span>
        <?php endif;?>

    </div>
    </div>

    
    <div style="clear:both;"></div>
    

    <!-- data_collection -->
    <?php 
    $questions_fields = array(
        "var_qstn_preqtxt", "var_qstn_qstnlit", "var_catgry", 
        "var_qstn_ivuinstr", "var_qstn_postqtxt", "var_qstn_ivulnstr"
    );
    $has_questions = false;
    foreach($questions_fields as $field) {
        if (isset($variable[$field]) && !empty($variable[$field])) {
            $has_questions = true;
            break;
        }
    }
    if ($has_questions): ?>
    <div>
        <h2 style="margin-top: 1rem;"><?php echo $html_report->get_template_translation('questions_n_instructions', 'Questions and Instructions');?></h2>
        <?php foreach($questions_fields as $field): ?>
            <?php if (isset($variable[$field]) && !empty($variable[$field])): ?>
                <div style="margin-bottom:25px;">
                    <h4><?php echo $html_report->get_template_translation('variable.'.$field, $field);?></h4>
                    <div>
                        <?php if ($field == 'var_catgry' && is_array($variable[$field])): ?>
                            <?php 
                            $ci =& get_instance();
                            echo $ci->load->view('project_preview/fields/field_variable_category', array('data' => $variable[$field], 'name' => $field, 'html_report' => $html_report), true); 
                            ?>
                        <?php else: ?>
                            <?php echo nl2br(html_escape($variable[$field]));?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- description -->
    <?php 
    $description_fields = array("var_txt", "var_universe", "var_respunit");
    $has_description = false;
    foreach($description_fields as $field) {
        if (isset($variable[$field]) && !empty($variable[$field])) {
            $has_description = true;
            break;
        }
    }
    if ($has_description): ?>
    <div>
        <h2 style="margin-top: 1rem;"><?php echo $html_report->get_template_translation('description', 'Description');?></h2>
        <?php foreach($description_fields as $field): ?>
            <?php if (isset($variable[$field]) && !empty($variable[$field])): ?>
                <div style="margin-bottom:25px;">
                    <h4><?php echo $html_report->get_template_translation('variable.'.$field, $field);?></h4>
                    <div><?php echo nl2br(html_escape($variable[$field]));?></div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- concept -->
    <?php if (isset($variable['var_concept']) && !empty($variable['var_concept'])): ?>
    <div>
        <h2 style="margin-top: 1rem;"><?php echo $html_report->get_template_translation('concept', 'Concept');?></h2>
        <div style="margin-bottom:25px;">
            <h4><?php echo $html_report->get_template_translation('variable.var_concept', 'Concept');?></h4>
            <div>
                <?php if (is_array($variable['var_concept'])): ?>
                    <?php foreach($variable['var_concept'] as $concept): ?>
                        <div>
                            <?php if (is_array($concept)): ?>
                                <?php echo html_escape(implode(', ', $concept));?>
                            <?php else: ?>
                                <?php echo html_escape($concept);?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php echo nl2br(html_escape($variable['var_concept']));?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- imputation_n_derivation -->
    <?php 
    $imputation_fields = array("var_imputation", "var_codinstr");
    $has_imputation = false;
    foreach($imputation_fields as $field) {
        if (isset($variable[$field]) && !empty($variable[$field])) {
            $has_imputation = true;
            break;
        }
    }
    if ($has_imputation): ?>
    <div>
        <h2 style="margin-top: 1rem;"><?php echo $html_report->get_template_translation('imputation_n_derivation','concept');?></h2>
        <?php foreach($imputation_fields as $field): ?>
            <?php if (isset($variable[$field]) && !empty($variable[$field])): ?>
                <div style="margin-bottom:25px;">
                    <h4><?php echo $html_report->get_template_translation('variable.'.$field, $field);?></h4>
                    <div><?php echo nl2br(html_escape($variable[$field]));?></div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- others -->
    <?php 
    $other_fields = array("var_security", "var_notes");
    $has_others = false;
    foreach($other_fields as $field) {
        if (isset($variable[$field]) && !empty($variable[$field])) {
            $has_others = true;
            break;
        }
    }
    if ($has_others): ?>
    <div>
        <h2 style="margin-top: 1rem;"><?php echo $html_report->get_template_translation('others', 'Others');?></h2>
        <?php foreach($other_fields as $field): ?>
            <?php if (isset($variable[$field]) && !empty($variable[$field])): ?>
                <div style="margin-bottom:25px;">
                    <h4><?php echo $html_report->get_template_translation('variable.'.$field, $field);?></h4>
                    <div><?php echo nl2br(html_escape($variable[$field]));?></div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

<!--end-container-->
</div>

<hr/>
<?php endforeach;?>
