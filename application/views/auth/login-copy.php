<style>
	body{background-color:#F0F0F0;}
	.login-container{background:gray;}
	.login-box{background-color:#F3F3F3;text-align:left;}
	.page-title{padding:10px;font-size:18px;background-color:#CCCCCC}
body
{
	text-align: center;
}

.login-container{
	margin-left: auto;
	margin-right: auto;
	margin-top:100px;
	width: 400px;	
	text-align: left;
	background-color:white;	
	border:10px solid gray;
	-moz-border-radius: 5px;
	-webkit-border-radius: 5px;
}
.login-container a{color:#666666}
.login-container a:hover{color:black;}	
.login-container a.button:hover{color:white;}	
.input-flex{font-family:"Courier New", Courier, monospace;font-size:18px;font-weight:bold;color:#003399}
.error{border:0px;background:none;}
</style>

<?php echo form_open("auth/login",array('class'=>'form','autocomplete'=>'off'));?>
<div id ="login-container" class="login-container" >
    <div class='login-box' >
    
        <div class="page-title"><?php echo anchor ("",$this->config->item('website_title'),'class="jx"'); ?></div>
        <div class="pageTitleBorder"></div>
		
        <div style="padding:5px;">
			<?php $message=$this->session->flashdata('message');?>
            <?php echo ($message!="") ? '<div class="success">'.$message.'</div>' : '';?>
            
            <?php if (isset($error)):?>
            <?php echo '<div class="error">'.$error.'</div>'?>
			<?php else:?>
				<?php $error=$this->session->flashdata('error');?>
                <?php echo ($error!="") ? '<div class="error">'.$error.'</div>' : '';?>            
            <?php endif;?>

            <div class="field">
                <label for="email"><?php echo t('email');?>:</label>
                <?php echo form_input($email,NULL,'class="input-flex"' );?>
            </div>
        
            <div class="field">
                <label for="password"><?php echo t('password');?>:</label>
                <?php echo form_input($password,NULL, 'class="input-flex" autocomplete="off"');?>
            </div>    
    
            <div class="field-inline">            
                <?php echo form_checkbox('remember', '1', FALSE,'id="remember"');?>
                <label for="remember"><?php echo t('remember_me');?></label>
            </div>
        
            <div>
                <input type="submit" name="submit" value="<?php echo t('login');?>" class="button"/>
                <input type="button" name="cancel" id="cancel" value="<?php echo t('cancel');?>" class="button jx" onclick="window.location='<?php echo site_url(); ?>';"/>
				<?php if ($this->config->item("site_user_register")==='no' || $this->config->item("site_password_protect")==='yes'):?>	
                	<?php echo anchor('auth/register',t('register'),'class="jx"'); ?>
                <?php endif;?>
                <?php echo anchor('auth/forgot_password',t('forgot_password'),'class="jx"'); ?>
            </div>
         </div>   
    </div>
</div>
<?php echo form_close();?>
<script type="text/javascript">

$(function() {
  $("#email").focus();
});


jQuery.fn.center = function () {
    this.css("position","absolute");
    this.css("top", ( $(window).height() - this.height() ) / 5+ "px");
    this.css("left", ( $(window).width() - this.width() ) / 2+$(window).scrollLeft() + "px");
    return this;
}
$("#login-container").center();

$(function(){
	is_iframe = (window.location != window.parent.location) ? true : false;
	if (is_iframe==true){
		$("#cancel").hide();
		$(".jx").attr("target","_blank")
	}
});
</script>