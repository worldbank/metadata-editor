<div class='container'>

	<h1 class="page-title"><?php echo t('forgot_password'); ?></h1>
	<?php $reason = $this->session->flashdata('reason'); ?>
	<?php if ($reason !== "") : ?>
		<?php echo ($reason != "") ? '<div class="reason">' . $reason . '</div>' : ''; ?>
	<?php endif; ?>

	<?php $message = $this->session->flashdata('message'); ?>
	<?php if (isset($error) && $error != '') : ?>
		<?php $error = '<div class="alert alert-danger">' . $error . '</div>' ?>
	<?php else : ?>
		<?php $error = $this->session->flashdata('error'); ?>
		<?php $error = ($error != "") ? '<div class="alert-danger">' . $error . '</div>' : ''; ?>
	<?php endif; ?>

	<?php if ($error != '') : ?>
		<div class="error"><?php echo $error; ?></div>
	<?php endif; ?>

	<?php if ($message != '') : ?>
		<div class="alert alert-primary"><?php echo $message; ?></div>
	<?php endif; ?>

	<p><?php echo t('enter_email_to_reset_password'); ?></p>

	<form method="post" class="form" autocomplete="off">

		<div class="field">
			<label for="email"><?php echo t('email'); ?></label>
			<?php echo form_input($email); ?>
			<?php echo form_submit('submit', t('submit')); ?>
		</div>

		<?php echo form_close(); ?>

</div> <!-- /.container -->