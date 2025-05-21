<?php
$options_country=$this->ion_auth_model->get_all_countries();
?>

<?php echo form_open(site_url().'/install/create_user/', array('class'=>'form register'));?>   
<div class='content-container' >

	<h3 class="pb-2 mb-4"><?php echo t('create_admin_account');?></h3>

	<?php if (validation_errors() ) : ?>
        <div class="error">
            <?php echo validation_errors(); ?>
        </div>
    <?php endif; ?>
    
    <?php if ( isset($this->error)) : ?>
        <div class="error">
            <p><?php echo $this->error; ?></p>
        </div>
    <?php endif; ?>
    
    <?php $error=$this->session->flashdata('error');?>
    <?php echo ($error!="") ? '<div class="error text-danger">'.$error.'</div>' : '';?>
        
    <?php $message=$this->session->flashdata('message');?>
    <?php echo ($message!="") ? '<div class="success text-success">'.$message.'</div>' : '';?>
	

    <div class="form-group">
	      <label for="first_name"><?php echo t('first_name');?><span class="required">*</span></label>
	      <?php echo form_input($first_name,'','class="form-control"');?>
      </div>

      <div class="form-group">
	      <label for="last_name"><?php echo t('last_name');?><span class="required">*</span></label>
	      <?php echo form_input($last_name,'','class="form-control"');?>
      </div>

      <div class="form-group">
	      <label for="email"><?php echo t('email');?><span class="required">*</span></label>
	      <?php echo form_input($email,'','class="form-control"');?>
      </div>

<?php /*      
      <div class="form-group">
	      <label for="company"><?php echo t('company');?></label>
	      <?php echo form_input($company,'','class="form-control"');?>
      </div>

      <div class="form-group">
	      <label for="phone1"><?php echo t('phone');?></label>
	      <?php echo form_input($phone1,'','class="form-control"');?>
      </div>
      
        <div class="form-group">
            <label for="country"><?php echo t('country');?></label>
            <?php echo form_dropdown('country', $options_country, get_form_value("country",isset($country) ? $country : ''), 'class="form-control"'); ?>
        </div>

*/?>        
      <div class="form-group">
	      <label for="password"><?php echo t('password');?><span class="required">*</span></label>
	      <?php echo form_input($password,'','class="form-control"');?>
      </div>
      
      <div class="form-group">
	      <label for="password_confirm"><?php echo t('password_confirmation');?><span class="required">*</span></label>
	      <?php echo form_input($password_confirm,'','class="form-control"');?>
      </div> 
 </div>

	<div class="bg-light text-right p-2">
 		<?php echo form_submit('submit', t('create_user'),'class="btn btn-primary"');?>
	</div>     
     
<?php echo form_close();?>