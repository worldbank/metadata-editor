<?php
/**
 * Variables list view for HTML reports
 * Shows variables for a specific data file
 * Uses template translations instead of global t() function
 */
?>

<div class="data-file-section mb-4">
    <h4><?php echo $html_report->get_template_translation('data_file', 'Data file');?>: <?php echo html_escape($file['file_name']);?></h4>
    
    <?php if (!empty($file['description'])): ?>
        <p><?php echo nl2br(html_escape($file['description']));?></p>
    <?php endif; ?>
    
    <table class="table table-sm mb-3">
        <tr>
            <td style="width:100px;"><strong><?php echo $html_report->get_template_translation('data_file.case_count', 'Cases');?>:</strong></td>
            <td><?php echo $file['case_count'];?></td>
        </tr>
        <tr>
            <td><strong><?php echo $html_report->get_template_translation('data_file.var_count', 'Variables');?>:</strong></td>
            <td><?php echo $file_variables_count;?></td>
        </tr>
        
        <?php if (!empty($file['producer'])): ?>
        <tr>
            <td><strong><?php echo $html_report->get_template_translation('data_file.producer', 'Producer');?>:</strong></td>
            <td><?php echo html_escape($file['producer']);?></td>
        </tr>
        <?php endif; ?>
        
        <?php if (!empty($file['notes'])): ?>
        <tr>
            <td><strong><?php echo $html_report->get_template_translation('data_file.notes', 'Notes');?>:</strong></td>
            <td><?php echo nl2br(html_escape($file['notes']));?></td>
        </tr>
        <?php endif; ?>
    </table>
    
    <h5><?php echo $html_report->get_template_translation('variable_description', 'Variables');?></h5>
    <table class="table table-sm table-bordered">
        <thead>
            <tr>
                <th><?php echo $html_report->get_template_translation('variable.vid', 'ID');?></th>
                <th><?php echo $html_report->get_template_translation('variable.name', 'Name');?></th>
                <th><?php echo $html_report->get_template_translation('variable.labl', 'Label');?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($variables as $variable): ?>
            <tr>
                <td><?php echo html_escape($variable['vid']);?></td>
                <td><?php echo html_escape($variable['name']);?></td>
                <td><?php echo html_escape($variable['labl']);?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <p><strong><?php echo $html_report->get_template_translation('total', 'Total');?>:</strong> <?php echo $file_variables_count;?></p>
</div>
