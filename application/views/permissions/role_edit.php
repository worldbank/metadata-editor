<style>
    .form-role{max-width:500px;}
</style>
<div class="container-fluid page-role-edit">

<?php 
	//menu breadcrumbs
	//include 'menu_breadcrumb.php'; 
?>

<h1 class="page-title"><?php echo t('Edit role'); ?></h1>

<?php if (validation_errors() ) : ?>
    <div class="alert alert-danger">
	    <?php echo validation_errors(); ?>
    </div>
<?php endif; ?>

<?php $error=$this->session->flashdata('error');?>
<?php echo ($error!="") ? '<div class="error">'.$error.'</div>' : '';?>

<?php $message=$this->session->flashdata('message');?>
<?php echo ($message!="") ? '<div class="success">'.$message.'</div>' : '';?>

<?php echo form_open(current_url(), array('class'=>'form form-role') ); ?>
    <div class="form-group">
        <label for="role"><?php echo t('Role name');?><span class="required">*</span></label>
        <input class="form-control" name="role" type="text" id="role" maxlength="50"  value="<?php echo get_form_value('name',isset($name) ? $name : ''); ?>"/>
    </div>

    <div class="form-group">
        <label for="description"><?php echo t('description');?><span class="required">*</span></label>
        <textarea class="form-control" maxlength="150" name="description" rows="3"><?php echo get_form_value('description',isset($description) ? $description : ''); ?></textarea>
    </div>

    <div class="form-group">
        <label for="weight"><?php echo t('weight');?><span class="required">*</span></label>
        <input class="form-control" name="weight" type="text" id="weight" size="3" maxlength="3"  value="<?php echo get_form_value('weight',isset($weight) ? $weight : ''); ?>"/>
    </div>

    <?php
	    echo form_submit('submit',t('update'),array('class'=>'btn btn-primary')); 
 	    echo anchor('admin/permissions/roles',t('cancel'),array('class'=>'btn btn-default') );	
    ?>

<?php echo form_close(); ?>
</div>
