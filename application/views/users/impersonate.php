<style type="text/css">
.user-row .user-name{font-weight:bold;margin-right:5px;}
</style>
<div class='container-fluid content-fluid'>

	<h1><?php echo t('Impersonate role'); ?></h1>

<h3 class="page title mt-5 mb-3"><?php echo t('impersonate_user'); ?></h3>

	<?php if (validation_errors()): ?>
        <div class="error">
            <?php echo validation_errors(); ?>
        </div>
    <?php endif; ?>
    
    <?php $error=$this->session->flashdata('error');?>
    <?php echo ($error!="") ? '<div class="error">'.$error.'</div>' : '';?>
        
    <?php $message=$this->session->flashdata('message');?>
    <?php echo ($message!="") ? '<div class="success">'.$message.'</div>' : '';?>
	
    <?php echo form_open();?>
    
    <div class="impersonate-text"><?php echo t('impersonate_msg');?>:</div>
    <?php foreach($roles as $role): if ($role['id'] <=2) {continue;}?>
    	<div class="user-row">
		<input type="radio" name="role_id" value="<?php echo $role['id'];?>"/>
        <span class="user-name"><?php echo $role['name'];?></span>
        <span class="role-description"><?php echo $role['description'];?></span>
        </div>
    <?php endforeach;?>

    <div style="margin-top:20px;">
        <input class="btn btn-primary btn-sm" type="submit" name="submit" value="<?php echo t('impersonate'); ?>"/>
        <a class="btn btn-secondary btn-sm" href="<?php echo site_url('admin/users'); ?>"><?php echo t('cancel'); ?></a>
    </div>

    <?php echo form_close(); ?>

</div>
