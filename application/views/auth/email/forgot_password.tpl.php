<html>
<body>
    <p><?php echo t('click_url_to_reset_password');?>:</p>
    <p><?php echo anchor('auth/reset_password/'. $forgotten_password_code);?></p>
    <p>&nbsp;</p>
    <p><?php echo t('request_password_ignore');?></p>
    <p>&nbsp;</p>
    <p>-----------------------------------------------------------
    <br/><?php echo $this->config->item('website_title'); ?> - <?php echo site_url(); ?></p>
    <p>&nbsp;</p>
    <p><?php echo t('do_not_reply_to_this_email');?></p>
</body>
</html>