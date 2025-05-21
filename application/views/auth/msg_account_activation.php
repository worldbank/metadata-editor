<?php if (isset($success)):?>
	<h1><?php echo t('account_activation_successful');?></h1>
	<p><?php echo t('user_account_activated');?>, <?php echo anchor('auth/login',t('click_here_to_login'));?>.</p>
<?php endif;?>

<?php if (isset($failed)):?>
	<h1><?php echo t('account_activation_failed');?></h1>
	<p style="color:red">
		<?php echo t('user_account_not_activated');?>. 
    	<?php echo sprintf(t('try_reset_password'), anchor('auth/forgot_password',t('forgot_password')));?>.
    </p>
<?php endif;?>