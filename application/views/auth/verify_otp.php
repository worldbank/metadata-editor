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
</style>
<div class="login-form">


<?php $reason=$this->session->flashdata('reason');?>
<?php if ($reason!==""):?>
    <?php echo ($reason!="") ? '<div class="reason">'.$reason.'</div>' : '';?>
<?php endif;?>

<?php $message=$this->session->flashdata('message');?>
<?php if (isset($error) && $error!=''):?>
	<?php $error= '<div class="error">'.$error.'</div>'?>
<?php else:?>
	<?php $error=$this->session->flashdata('error');?>
	<?php $error= ($error!="") ? '<div class="error">'.$error.'</div>' : '';?>            
<?php endif;?>	

<?php if ($error!='' || $message!=''):?> 
	<div><?php echo $message.$error;?></div>
<?php endif;?>

<h1><?php echo t('Verification code');?></h1>
<form method="post" class="form" autocomplete="off">

<div style="padding:5px;">

    <div class="form-group">
        <input class="form-control"  name="code" maxlength="10"  id="code"  value="" />        
    </div>    
        
    <div class="login-footer">
        <input type="submit" name="submit" value="<?php echo t('Submit');?>" class="btn btn-primary btn-block"/>                
    </div>
</form>
        
<div class="privacy-info mt-4 text-secondary">
    <a href="<?php echo site_url('auth/send_otp_code');?>">Send me a new code</a>
</div>
        
</div>    
   

</div>
</div>

<script type="text/javascript">

$(function() {
  $("#code").focus();
});
</script>