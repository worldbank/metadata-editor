<div class="container-fluid content-container">
<h1 class="page-title"><?php echo t('batch_import_users'); ?></h1>

<?php if (validation_errors()): ?>
    <div class="error">
	    <?php echo validation_errors(); ?>
    </div>
<?php endif;?>

<?php $error = $this->session->flashdata('error');?>
<?php echo ($error != "") ? '<div class="error">' . $error . '</div>' : ''; ?>

<?php $message = $this->session->flashdata('message');?>
<?php echo ($message != "") ? '<div class="success">' . $message . '</div>' : ''; ?>

<?php echo form_open('admin/users/batch_import', array('class' => 'form')); ?>
    <div class="field">
        <label for="csv"><?php echo t('csv_file_content'); ?><span class="required">*</span></label>
        <textarea class="input-flex" name="csv" id="csv" rows="20"><?php echo get_form_value('csv', isset($csv) ? $csv : ''); ?></textarea>
    </div>

    <div class="field" style="display:block;">
     	<label for="sep"><?php echo t('field_seperator'); ?><span class="required">*</span></label>
        <input name="sep" type="text" id="sep" maxlength="5"  value="<?php echo get_form_value('sep', isset($sep) ? $sep : ','); ?>"/>
    </div>

<?php
echo form_submit('submit', t('batch_import'));
echo anchor('admin/users', t('cancel'), array('class' => 'button'));
echo form_close();
?>
</div>