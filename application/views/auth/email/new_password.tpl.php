<html>
<body>
	<p><?php echo sprintf(t('password_reset_to'),$new_password);?></p>
    <p>	<?php echo t('username');?>: <?php echo $identity; ?><br/>
    	<?php echo t('password');?>: <?php echo $new_password; ?></p>
    <p>&nbsp;</p>    
    <p>-----------------------------------------------------------
    <br/><?php echo $this->config->item('website_title'); ?> - <?php echo site_url(); ?></p>
    <p>&nbsp;</p>
    <p><?php echo t('do_not_reply_to_this_email');?></p>    
</body>
</html>