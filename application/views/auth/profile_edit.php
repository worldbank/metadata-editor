<?php
$options_country = $this->ion_auth_model->get_all_countries();
?>

<div class="container"> 

	<h1><?php echo t('edit_profile'); ?> - <?php echo $user->first_name . ' ' . $user->last_name; ?></h1>

	<?php if (validation_errors()) : ?>
		<div class="error">
			<?php echo validation_errors(); ?>
		</div>
	<?php endif; ?>

	<?php $error = $this->session->flashdata('error'); ?>
	<?php echo ($error != "") ? '<div class="error">' . $error . '</div>' : ''; ?>

	<?php $message = $this->session->flashdata('message'); ?>
	<?php echo ($message != "") ? '<div class="success">' . $message . '</div>' : ''; ?>


	<form method="post" autocomplete="off" style="max-width:400px;">

		<input type="hidden" name="<?php echo $csrf['keys']['name']; ?>" value="<?php echo $csrf['name']; ?>" />
		<input type="hidden" name="<?php echo $csrf['keys']['value']; ?>" value="<?php echo $csrf['value']; ?>" />

		<div class="form-group">
			<label for="first_name"><?php echo t('first_name'); ?><span class="required">*</span></label>
			<?php echo form_input('first_name', get_form_value('first_name', isset($user->first_name) ? $user->first_name : ''), 'class="form-control"'); ?>
		</div>

		<div class="form-group">
			<label for="last_name"><?php echo t('last_name'); ?><span class="required">*</span></label>
			<?php echo form_input('last_name', get_form_value('last_name', isset($user->last_name) ? $user->last_name : ''), 'class="form-control"'); ?>
		</div>
		<!--
		<div class="form-group">
			<label for="email"><?php echo t('email'); ?><span class="required">*</span></label>
			<span><?php echo $user->email; ?></span>
		</div>
		-->

		<div class="form-group">
			<label for="company"><?php echo t('company'); ?></label>
			<?php echo form_input('company', get_form_value('company', isset($user->company) ? $user->company : ''), 'class="form-control"'); ?>
		</div>


		<div class="form-group">
			<label for="phone"><?php echo t('phone'); ?></label>
			<?php echo form_input('phone', get_form_value('phone', isset($user->phone) ? $user->phone : ''), 'class="form-control"'); ?>
		</div>

		<div class="form-group">
			<label for="phone"><?php echo t('country'); ?></label>
			<?php echo form_dropdown('country', $options_country, get_form_value("country", isset($user->country) ? $user->country : ''), 'class="form-control"'); ?>
		</div>



		<div style="margin-top:10px;">
			<?php echo form_submit('submit', t('update'), array('class' => 'btn btn-primary')); ?>
			<?php echo anchor('auth/profile', t('cancel'), array('class' => 'btn btn-link')); ?>
		</div>
	</form>

</div> <!-- /.container -->