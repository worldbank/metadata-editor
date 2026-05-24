<script type="text/javascript">
	if (top.frames.length != 0) {
		top.location = self.document.location;
	}
</script>
<style>
.login-form {
	width: 100%;
	max-width: 500px;
	padding: 30px;
	margin: auto;
	border: 1px solid #dee2e6;
	border-radius: 8px;
	background-color: #fff;
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>
<div class="login-form mt-5">

<?php $reason = $this->session->flashdata('reason'); ?>
<?php if ($reason !== ''): ?>
	<div class="reason"><?php echo $reason; ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
	<div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!empty($message)): ?>
	<div class="alert alert-primary"><?php echo $message; ?></div>
<?php endif; ?>

<h1><?php echo t('log_in'); ?></h1>

<p class="text-secondary mb-2"><?php echo t('local_mode_login_message'); ?></p>
<p class="mb-1"><strong><?php echo htmlspecialchars(isset($local_admin_name) ? $local_admin_name : t('local_administrator')); ?></strong></p>
<p class="text-secondary mb-4"><?php echo t('local_mode_no_password_required'); ?></p>

<form method="post" class="form" autocomplete="off">
	<input type="hidden" name="zero_auth_login" value="1">
	<?php if (!empty($popup_mode)): ?>
	<input type="hidden" name="mode" value="popup">
	<?php endif; ?>
	<div class="login-footer">
		<input type="submit" name="submit" value="<?php echo t('login'); ?>" class="btn btn-primary btn-block"/>
	</div>
</form>

</div>
