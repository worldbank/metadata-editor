<script type="text/javascript"> 
	if (top.frames.length!=0) {
		top.location=self.document.location;
	}
</script>	
<style>
.login-form{
    width: 100%;
    max-width: 420px;
    padding: 15px;
    margin: auto;
}
.privacy-info{
    font-size:smaller;
}

.image_captcha {
    background:gainsboro;
    padding:10px;
    margin-bottom:20px;
}
.image_captcha input{
    display:block;
}
</style>
<div class="login-form mt-5">


<?php $reason=$this->session->flashdata('reason');?>
<?php if ($reason!==""):?>
    <?php echo ($reason!="") ? '<div class="reason">'.$reason.'</div>' : '';?>
<?php endif;?>

<?php $message=$this->session->flashdata('message');?>
<?php if (isset($error) && $error!=''):?>
	<?php $error= '<div class="alert alert-danger">'.$error.'</div>'?>
<?php else:?>
	<?php $error=$this->session->flashdata('error');?>
	<?php $error= ($error!="") ? '<div class="alert-danger">'.$error.'</div>' : '';?>            
<?php endif;?>	

<?php if ($error!=''):?> 
	<div><?php echo $error;?></div>
<?php endif;?>

<?php if ($message!=''):?> 
	<div class="alert alert-primary"><?php echo $message;?></div>
<?php endif;?>


<h1><?php echo t('log_in');?></h1>
<form method="post" class="form" autocomplete="off">

<div style="padding:5px;">

    <div class="form-group">
        <!--<label for="email"><?php echo t('email');?>:</label>-->
        <input class="form-control"  name="email" type="text" id="email"  value="" placeholder="<?php echo t('email');?>" />
    </div>

    <div class="form-group">
        <!--<label for="password"><?php echo t('password');?>:</label>-->
        <input class="form-control"  name="password" type="password" id="password"  value="" placeholder="<?php echo t('password');?>"/>
    </div>
    
    <div class="captcha_container">
        <?php echo $captcha_question;?>
    </div>
        
    <div class="login-footer">
        <input type="submit" name="submit" value="<?php echo t('login');?>" class="btn btn-primary btn-block"/>                
        <div class="ot clearfix">
        <?php if ($this->config->item("site_user_register")!=='no' && $this->config->item("site_password_protect")!=='yes'):?>	
            <span class="lnk first float-left"><?php echo anchor('auth/register',t('register'),'class="jx btn btn-link btn-sm"'); ?></span>
        <?php endif;?>
        <span class="lnk float-right"><?php echo anchor('auth/forgot_password',t('forgot_password'),'class="jx btn btn-link btn-sm"'); ?></span>
        </div>
    </div>
</form>
        
<div class="privacy-info mt-4 text-secondary"><?php echo t('site_login_privacy_terms');?></div>
        
</div>    
   

</div>
</div>

<script type="text/javascript">

$(function() {
  $("#email").focus();
});
</script>