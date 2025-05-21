<style>
.description{color:gray;font-size:11px;}
.user-role{
  text-transform:capitalize;
}
.user_groups{
  padding-left:20px;
}
label{font-weight:bold;}
.user-role{font-weight:normal;}
</style>
<div class='container-fluid users-edit-page'>

<div class="row">
<div class="col-md-6">
    
    <h3 class="page title mt-4 mb-3"><?php echo $page_title; ?></h3>
    <?php if (validation_errors()): ?>
        <div class="alert alert-danger">
            <?php echo validation_errors(); ?>
        </div>
    <?php endif;?>

    <?php $error = $this->session->flashdata('error');?>
    <?php echo ($error != "") ? '<div class="alert alert-danger">' . $error . '</div>' : ''; ?>

    <?php $message = $this->session->flashdata('message');?>
    <?php echo ($message != "") ? '<div class="alert alert-success">' . $message . '</div>' : ''; ?>

    <?php
      $form_action_url = site_url() . '/admin/users';
      if ($this->uri->segment(3) == 'add') {
          $form_action_url .= '/add';
      } else {
          $form_action_url .= '/edit/' . $this->uri->segment(4);
      }
    ?>

    <?php echo form_open($form_action_url, array('class' => 'form register', 'autocomplete' => 'off')); ?>

      <?php echo form_input($id); ?>
      <div class="col form-group">
          <label for="username"><?php echo t('username'); ?><span class="required">*</span></label>
          <?php echo form_input($username); ?>
      </div>

      <div class="col form-group">
          <label for="email"><?php echo t('email'); ?><span class="required">*</span></label>
          <?php echo form_input($email); ?>
      </div>
        
      <div class="col form-row">
        <div class="col form-group">
            <label for="first_name"><?php echo t('first_name'); ?><span class="required">*</span></label>
            <?php echo form_input($first_name); ?>
        </div>

        <div class="col form-group">
            <label for="last_name"><?php echo t('last_name'); ?><span class="required">*</span></label>
            <?php echo form_input($last_name); ?>
        </div>
    </div>

    <div class="col form-row">
      <div class="col form-group">
          <label for="company"><?php echo t('company'); ?></label>
          <?php echo form_input($company); ?>
      </div>

      <div class="col form-group">
          <label for="phone1"><?php echo t('phone'); ?></label>
          <?php echo form_input($phone1); ?>
      </div>

    </div>
    
    <div class="col-6 form-group">
      <label for="country"><?php echo t('country'); ?></label>
      <?php echo form_dropdown('country', $options_country, get_form_value("country", isset($country) ? $country : ''), 'class="form-control"'); ?>
    </div>
      
    <div class="col form-group">
      <span class="text-danger"><em><?php echo t('leave_password_blank'); ?></em></span>
    </div>

    <div class="col form-row">
      <div class="col-6 form-group">
          <label for="password"><?php echo t('password'); ?><span class="required">*</span></label>
          <?php echo form_input($password); ?>
      </div>

      <div class="col-6 form-group">
          <label for="password_confirm"><?php echo t('password_confirmation'); ?><span class="required">*</span></label>
          <?php echo form_input($password_confirm); ?>
      </div>
    </div>

    <div class="col form-group">
      <label for="password_confirm"><?php echo t('user_account_status'); ?></label>
      <span style="padding-right:10px;"><?php echo form_radio('active', '1', $active == 1); ?> <?php echo t('user_active'); ?> </span>
      <span><?php echo form_radio('active', '0', $active != 1); ?> <?php echo t('user_blocked'); ?> </span>
    </div>
  
    <div class="col form-group">
        <label for="user_groups">
          <?php echo t('User roles');?> 
        </label>
        <div class="user_groups">
        <?php foreach($roles as $role): $role_selected=false;?>
        	<?php if (isset($user_role) && count($user_role)>0 && in_array($role['id'],$user_role)):?>
          <?php $role_selected=true;?>
          <?php endif;?>
            <div class="checkbox">
              <label class="user-role">
                <input type="checkbox" <?php echo $role_selected ? 'checked="checked"' : '';?> name="role[]" value="<?php echo $role['id'];?>"> <?php echo t($role['name']);?>
              </label>
          </div>
	  	  <?php endforeach;?>
        </div>        
    </div>

      <div class="col form-group">
            <span class="custom-fields"><?php echo form_submit('submit', t('update'), array('class' => 'btn btn-primary btn-sm')); ?></span>
            <?php echo anchor('admin/users', t('cancel'), array('class' => 'btn btn-secondary btn-sm')); ?>
      </div>
    <?php echo form_close(); ?>

    </div>
    </div>
</div>
