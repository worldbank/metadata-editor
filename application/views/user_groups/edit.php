<?php
$options_groups=array(
	'user'=>t('group_type_user'),	
	'reviewer'=>t('group_type_reviewer'),
	'admin'=>t('group_type_admin')	
);


$options_access=array(
	'none'=>t('access_type_none'),	
	'limited'=>t('access_type_limited'),
	'unlimited'=>t('access_type_unlimited')	
);

?>
<div class="container-fluid content-container">
<?php 	$this->load->view('user_groups/navigation_links');  ?>

<h1 class="page-name"><?php echo isset($id) ? t('user_groups_edit') : t('user_groups_add'); ?></h1>
<?php if (validation_errors() ) : ?>
    <div class="error">
	    <?php echo validation_errors(); ?>
    </div>
<?php endif; ?>

<?php $error=$this->session->flashdata('error');?>
<?php echo ($error!="") ? '<div class="error">'.$error.'</div>' : '';?>

<?php $message=$this->session->flashdata('message');?>
<?php echo ($message!="") ? '<div class="success">'.$message.'</div>' : '';?>

<?php echo form_open($this->html_form_url, array('class'=>'form') ); ?>
    <div class="form-group">
        <label for="name"><?php echo t('name');?><span class="required">*</span></label>
        <input class="form-control" name="name" type="text" id="name"  value="<?php echo get_form_value('name',isset($name) ? $name : ''); ?>"/>
        <input type="hidden" name="pid" value="<?php echo get_form_value('pid',isset($pid) ? $pid : ''); ?>"/>
    </div>

    <div class="form-group">
        <label for="description"><?php echo t('description');?></label>
        <textarea id="body" class="form-control"  name="description" rows="10"><?php echo get_form_value('description',isset($description) ? $description : ''); ?></textarea>        
    </div>

    <div class="form-group">
        <label for="group_type"><?php echo t('group_type');?></label>
        <?php echo form_dropdown('group_type', $options_groups, get_form_value("group_type",isset($group_type) ? $group_type : '')); ?>
    </div>
    
    <div class="form-group">
        <label for="access_type"><?php echo t('access_type');?></label>
		<?php echo form_dropdown('access_type', $options_access, get_form_value("access_type",isset($access_type) ? $access_type : '')); ?>
	</div>
<?php
 //edit user
	echo form_submit('submit',t('update'),'id="btnupdate" class="btn btn-primary"'); 
 	echo anchor('admin/user_groups',t('cancel'));	
?>

<? echo form_close(); ?>    
</div>