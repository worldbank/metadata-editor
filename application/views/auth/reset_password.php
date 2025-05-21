<h1><?php echo t('reset_password');?></h1>

<?php if (validation_errors() ) : ?>
    <div class="nada-error error">
	    <?php echo validation_errors(); ?>
    </div>
<?php endif; ?>

<?php $error=$this->session->flashdata('error');?>
<?php echo ($error!="") ? '<div class="error">'.$error.'</div>' : '';?>

<?php $message=$this->session->flashdata('message');?>
<?php echo ($message!="") ? '<div class="success">'.$message.'</div>' : '';?>

<div class="row">
    <div class="col-12 col-sm-6 col-md-6 col-lg-6">
<form method="post" autocomplete="off" class="form">          
      <p><?php echo t('new_password');?>:<br />
      <?php echo form_input($new_password);?>
      </p>
      
      <p><?php echo t('confirm_new_password');?>:<br />
      <?php echo form_input($new_password_confirm);?>
      </p>
            
      <p><button class="btn btn-primary wb-btn" type="submit"><?php echo t('submit');?></button></p>
 </div>
</div>      
<?php echo form_close();?>
