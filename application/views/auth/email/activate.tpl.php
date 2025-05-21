<?php 
/*
	User registration email message - sent when a user registers for an account
*/
?>
<html>
<body>
	<p><?php echo sprintf(t('thank_you_for_registration'),$this->config->item('website_title'));?></p>
	<p><b><?php echo anchor('auth/activate/'. $id .'/'. $activation, site_url().'/auth/activate/'. $id .'/'. $activation);?></b></p>
	<p><?php echo t('your_account_details');?>:</p>
    <p><?php echo t('username');?>: <?php echo $email; ?></p>
    <p>&nbsp;</p>
    <p>&nbsp;</p>
    <p>-----------------------------------------------------------
    <br/><?php echo $this->config->item('website_title'); ?> - <?php echo site_url(); ?></p>
    <p>&nbsp;</p>
    <p><?php echo t('do_not_reply_to_this_email');?></p>
</body>
</html>