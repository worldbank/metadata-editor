<style>
fieldset{
    border:0px;
}
label{
  font-weight:bold;
}
fieldset legend{
    font-weight:normal;
    margin:0px;
    padding:15px !important;
}
.form-check label{
    font-weight:normal;
   
}
</style>
<div class="container-fluid">

<h3 class="mt-5 mb-5">Test email configurations</h3>


<form method="post" id="email-form" >


    <div class="form-group row">
        <label for="smtp-host" class="col-sm-2 col-form-label">SMTP host</label>
        <div class="col-sm-10">
            <input type="text" class="form-control" name="smtp_host" value="<?php echo set_value('smtp_host', $smtp_host); ?>" id="smtp-host" placeholder="SMTP host e.g. smtp.example.com">
        </div>
    </div>

    <div class="form-group row">
        <label for="smtp-port" class="col-sm-2 col-form-label">Port</label>
        <div class="col-sm-10">
            <input type="number" class="form-control" name="smtp_port" value="<?php echo set_value('smtp_port', $smtp_port); ?>" id="smtp-port" placeholder="Port" >
        </div>
    </div>

    <div class="form-group row">
    <div class="col-sm-2"><label>SMTP authentication</label></div>
    <div class="col-sm-10 ">
      <div class="text-left">      
        <?php echo form_checkbox('smtp_auth', 'true', $smtp_auth,array('id'=>"smtp_auth"));?>
        <label class="form-check-label font-weight-normal" for="smtp_auth">
          Use SMTP authentication
        </label>
      </div>
    </div>
  </div>

    <fieldset class="form-group">
    <div class="row">
      <legend class="col-form-label col-sm-2 pt-0"><label>Secure connection</label></legend>
      <div class="col-sm-10">
      <div class="row">
      <div class="col-sm-2">
      <div class="form-check">
          <input class="form-check-input" type="radio" name="smtp_crypto" id="smtp_secure_none" 
            value="" 
            <?php echo ($smtp_crypto=='') ? 'checked' : '';?>>
          <label class="form-check-label" for="smtp_secure_none">
            None
          </label>
        </div>
        </div>
        <div class="col-sm-2">
        <div class="form-check">
          <input class="form-check-input" type="radio" name="smtp_crypto" id="smtp_secure_tls" 
            value="tls" 
            <?php echo ($smtp_crypto=='tls') ? 'checked' : '';?>>
          <label class="form-check-label" for="smtp_secure_tls">
            TLS
          </label>
        </div>
        </div>
        <div class="col-sm-2">
        <div class="form-check">
          <input class="form-check-input" type="radio" name="smtp_crypto" 
                id="smtp_secure_ssl" 
                value="ssl"
                <?php echo ($smtp_crypto=='ssl') ? 'checked' : '';?>>
          <label class="form-check-label" for="smtp_secure_ssl">
            SSL
          </label>
        </div>
        </div>  
        </div>      
      </div>
        </div>
    </fieldset>


    <fieldset class="form-group">
    <div class="row">
      <legend class="col-form-label col-sm-2 pt-0"><label>Email library</label></legend>
      <div class="col-sm-10">
      <?php 
        $user_agents=array(
            'CodeIgniter'=>'Default',
            'PHPMailer'=>'PHPMailer'
        );

      ?>
      <?php echo form_dropdown('useragent', $user_agents, $useragent);?>
      <small class="form-text text-muted">
            Email engine/library to use for sending out emails
      </small>    

      </div>
      </div>

    </fieldset>

    

  <div class="form-group row">
    <label for="inputEmail3" class="col-sm-2 col-form-label">Username/Email</label>
    <div class="col-sm-10">
      <input type="email" class="form-control" name="smtp_user" value="<?php echo set_value('smtp_user', $smtp_user); ?>" placeholder="Email">
    </div>
  </div>

  <div class="form-group row">
    <label for="inputPassword3" class="col-sm-2 col-form-label">Password</label>
    <div class="col-sm-10">
      <input type="password" class="form-control" name="smtp_pass" value="<?php echo set_value('smtp_pass', $smtp_pass); ?>" placeholder="Password - leave this empty to use password from config/email.php">
    </div>
  </div>

  <div class="form-group row">
    <label for="inputEmail3" class="col-sm-2 col-form-label">Email from</label>
    <div class="col-sm-10">
      <input type="email" class="form-control" name="mail_from" value="<?php echo set_value('mail_from', $mail_from); ?>"" placeholder="Leave this empty, if Username/Email are the same">
    </div>
  </div>

  <div class="form-group row">
    <label for="email_to" class="col-sm-2 col-form-label">Email to</label>
    <div class="col-sm-10">
      <input type="email" class="form-control" name="mail_to" id="email_to" placeholder="Recipient email address">
    </div>
  </div>

  <div class="form-group row">
    <div class="col-sm-10">
      <button type="button" id="btn_submit" class="btn btn-primary">Send email</button>
    </div>
  </div>
</form>

<pre style="display:none;" id="email_output">  <i class="fas fa-spinner fa-spin"></i>
</pre>

</div>




<script>
function send_mail(){
    $("#email_output").show().html('<i class="fa fa-spinner fa-pulse fa-2x fa-fw"></i> sending ...');
    data=$("#email-form").serialize();
    url=CI.base_url+'/admin/configurations/send_test_email';
    $.post(url,data,function (data){
        $("#email_output").html('<div>'+data+'</div>');
    });
}

$( "#btn_submit" ).on( "click", function() {
  send_mail();
});
</script>