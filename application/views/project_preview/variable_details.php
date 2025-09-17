<?php
/**
 * Variable details view for HTML reports
 * Shows comprehensive information about a single variable
 * Uses template translations instead of global t() function
 */
?>

<div class="variable-details-section mb-4">
    <!-- Variable Header -->
    <div class="variable-header mb-4">
        <h4><?php echo $html_report->get_template_translation('variable_description', 'Variable Details');?></h4>
        <div class="variable-title">
            <h5><?php echo html_escape($variable['name']);?></h5>
            <?php if (!empty($variable['labl'])): ?>
                <p class="text-muted"><?php echo html_escape($variable['labl']);?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Basic Information -->
    <div class="variable-basic-info mb-4">
        <h6><?php echo $html_report->get_template_translation('variable_description', 'Basic Information');?></h6>
        <table class="table table-sm table-bordered">
            <tbody>
                <tr>
                    <td style="width:150px;"><strong><?php echo $html_report->get_template_translation('variable.vid', 'Variable ID');?>:</strong></td>
                    <td><?php echo html_escape($variable['vid']);?></td>
                </tr>
                <tr>
                    <td><strong><?php echo $html_report->get_template_translation('variable.name', 'Variable Name');?>:</strong></td>
                    <td><?php echo html_escape($variable['name']);?></td>
                </tr>
                <?php if (!empty($variable['labl'])): ?>
                <tr>
                    <td><strong><?php echo $html_report->get_template_translation('variable.labl', 'Variable Label');?>:</strong></td>
                    <td><?php echo html_escape($variable['labl']);?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><strong><?php echo $html_report->get_template_translation('variable.file_id', 'File ID');?>:</strong></td>
                    <td><?php echo html_escape($variable['fid']);?></td>
                </tr>
                <?php if (!empty($data_file['file_name'])): ?>
                <tr>
                    <td><strong><?php echo $html_report->get_template_translation('data_file.file_name', 'Data File');?>:</strong></td>
                    <td><?php echo html_escape($data_file['file_name']);?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Variable Definition -->
    <?php if (!empty($variable['var_txt'])): ?>
    <div class="variable-definition mb-4">
        <h6><?php echo $html_report->get_template_translation('variable.var_txt', 'Variable Definition');?></h6>
        <div class="definition-content">
            <?php echo nl2br(html_escape($variable['var_txt']));?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Universe -->
    <?php if (!empty($variable['var_universe'])): ?>
    <div class="variable-universe mb-4">
        <h6><?php echo $html_report->get_template_translation('variable.var_universe', 'Universe');?></h6>
        <div class="universe-content">
            <?php echo nl2br(html_escape($variable['var_universe']));?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Technical Format -->
    <?php if (!empty($variable['var_format'])): ?>
    <div class="variable-format mb-4">
        <h6><?php echo $html_report->get_template_translation('variable.format', 'Technical Format');?></h6>
        <table class="table table-sm table-bordered">
            <tbody>
                <?php if (!empty($variable['var_format']['type'])): ?>
                <tr>
                    <td style="width:150px;"><strong><?php echo $html_report->get_template_translation('variable.var_format.type', 'Type');?>:</strong></td>
                    <td><?php echo html_escape($variable['var_format']['type']);?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($variable['var_format']['name'])): ?>
                <tr>
                    <td><strong><?php echo $html_report->get_template_translation('variable.var_format.name', 'Name');?>:</strong></td>
                    <td><?php echo html_escape($variable['var_format']['name']);?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($variable['var_format']['note'])): ?>
                <tr>
                    <td><strong><?php echo $html_report->get_template_translation('variable.var_format.note', 'Note');?>:</strong></td>
                    <td><?php echo nl2br(html_escape($variable['var_format']['note']));?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Position in Fixed Format File -->
    <?php if (!empty($variable['loc_start_pos']) || !empty($variable['loc_end_pos']) || !empty($variable['loc_width']) || !empty($variable['loc_rec_seg_no'])): ?>
    <div class="variable-position mb-4">
        <h6><?php echo $html_report->get_template_translation('variable1674857761764', 'Position in Fixed Format File');?></h6>
        <table class="table table-sm table-bordered">
            <tbody>
                <?php if (!empty($variable['loc_start_pos'])): ?>
                <tr>
                    <td style="width:150px;"><strong><?php echo $html_report->get_template_translation('variable.loc_start_pos', 'Start Position');?>:</strong></td>
                    <td><?php echo html_escape($variable['loc_start_pos']);?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($variable['loc_end_pos'])): ?>
                <tr>
                    <td><strong><?php echo $html_report->get_template_translation('variable.loc_end_pos', 'End Position');?>:</strong></td>
                    <td><?php echo html_escape($variable['loc_end_pos']);?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($variable['loc_width'])): ?>
                <tr>
                    <td><strong><?php echo $html_report->get_template_translation('variable.loc_width', 'Width');?>:</strong></td>
                    <td><?php echo html_escape($variable['loc_width']);?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($variable['loc_rec_seg_no'])): ?>
                <tr>
                    <td><strong><?php echo $html_report->get_template_translation('variable.loc_rec_seg_no', 'Record Segment Number');?>:</strong></td>
                    <td><?php echo html_escape($variable['loc_rec_seg_no']);?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Question Information -->
    <?php if (!empty($variable['var_qstn_qstnlit'])): ?>
    <div class="variable-question mb-4">
        <h6><?php echo $html_report->get_template_translation('variable.var_qstn_qstnlit', 'Literal Question');?></h6>
        <div class="question-content">
            <?php echo nl2br(html_escape($variable['var_qstn_qstnlit']));?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Post Question Text -->
    <?php if (!empty($variable['var_qstn_postqtxt'])): ?>
    <div class="variable-post-question mb-4">
        <h6><?php echo $html_report->get_template_translation('variable.var_qstn_postqtxt', 'Post Question Text');?></h6>
        <div class="post-question-content">
            <?php echo nl2br(html_escape($variable['var_qstn_postqtxt']));?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Concepts -->
    <?php if (!empty($variable['var_concept']) && is_array($variable['var_concept'])): ?>
    <div class="variable-concepts mb-4">
        <h6><?php echo $html_report->get_template_translation('variable.var_concept', 'Concepts');?></h6>
        <div class="concepts-content">
            <?php foreach($variable['var_concept'] as $concept): ?>
                <span class="badge badge-secondary mr-2 mb-2"><?php echo html_escape($concept);?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Keywords -->
    <?php if (!empty($variable['keywords']) && is_array($variable['keywords'])): ?>
    <div class="variable-keywords mb-4">
        <h6><?php echo $html_report->get_template_translation('keywords', 'Keywords');?></h6>
        <div class="keywords-content">
            <?php foreach($variable['keywords'] as $keyword): ?>
                <span class="badge badge-info mr-2 mb-2"><?php echo html_escape($keyword);?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Categories -->
    <?php if (!empty($variable['catgry']) && is_array($variable['catgry'])): ?>
    <div class="variable-categories mb-4">
        <h6><?php echo $html_report->get_template_translation('variable.catgry', 'Categories');?></h6>
        <table class="table table-sm table-bordered">
            <thead>
                <tr>
                    <th><?php echo $html_report->get_template_translation('variable.catgry.value', 'Value');?></th>
                    <th><?php echo $html_report->get_template_translation('variable.catgry.labl', 'Label');?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($variable['catgry'] as $category): ?>
                <tr>
                    <td><?php echo html_escape($category['value'] ?? '');?></td>
                    <td><?php echo html_escape($category['labl'] ?? '');?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Summary Statistics -->
    <?php if (!empty($variable['var_sumstat']) && is_array($variable['var_sumstat'])): ?>
    <div class="variable-summary-stats mb-4">
        <h6><?php echo $html_report->get_template_translation('variable.var_sumstat', 'Summary Statistics');?></h6>
        <table class="table table-sm table-bordered">
            <tbody>
                <?php foreach($variable['var_sumstat'] as $stat): ?>
                <tr>
                    <td style="width:150px;"><strong><?php echo html_escape($stat['type'] ?? '');?>:</strong></td>
                    <td><?php echo html_escape($stat['value'] ?? '');?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Value Range -->
    <?php if (!empty($variable['var_val_range'])): ?>
    <div class="variable-value-range mb-4">
        <h6><?php echo $html_report->get_template_translation('variable.var_val_range', 'Value Range');?></h6>
        <table class="table table-sm table-bordered">
            <tbody>
                <?php if (!empty($variable['var_val_range']['min'])): ?>
                <tr>
                    <td style="width:150px;"><strong><?php echo $html_report->get_template_translation('variable.var_val_range.min', 'Minimum');?>:</strong></td>
                    <td><?php echo html_escape($variable['var_val_range']['min']);?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($variable['var_val_range']['max'])): ?>
                <tr>
                    <td><strong><?php echo $html_report->get_template_translation('variable.var_val_range.max', 'Maximum');?>:</strong></td>
                    <td><?php echo html_escape($variable['var_val_range']['max']);?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>
