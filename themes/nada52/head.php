<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Metadata editor [beta]</title>
<?php /* <base href="<?php echo base_url(); ?>"> */ ?>
<?php if (isset($_meta) ){ echo $_meta;} ?>

<?php if($use_cdn):?>    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
<?php else:?>
    <link href="<?php echo base_url().$bootstrap_theme ?>/fontawesome/css/all.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo base_url().$bootstrap_theme; ?>/css/bootstrap.min.css">
<?php endif;?>    

<link href="https://cdn.jsdelivr.net/npm/@mdi/font@6.x/css/materialdesignicons.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?php echo base_url().$bootstrap_theme ?>/css/style.css?v2023">

<?php if($use_cdn):?>
    <script src="//code.jquery.com/jquery-3.2.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>
<?php else:?>
    <script src="<?php echo base_url(); ?>javascript/jquery/jquery.js"></script>
    <script src="<?php echo base_url().$bootstrap_theme ?>/js/popper.min.js"></script>
    <script src="<?php echo base_url().$bootstrap_theme ?>/js/bootstrap.min.js"></script>
<?php endif;?>

<script type="text/javascript">
    var CI = {'base_url': '<?php echo site_url(); ?>'};

    if (top.frames.length!=0) {
        top.location=self.document.location;
    }

    $(document).ready(function()  {
        /*global ajax error handler */
        $( document ).ajaxError(function(event, jqxhr, settings, exception) {
            if(jqxhr.status==401){
                window.location=CI.base_url+'/auth/login/?destination=catalog/';
            }
            else if (jqxhr.status>=500){
                alert(jqxhr.responseText);
            }
        });

    }); //end-document-ready

</script>


<?php if (isset($_styles) ){ echo $_styles;} ?>
<?php if (isset($_scripts) ){ echo $_scripts;} ?>

<?php $google_ua_code=$this->config->item("google_ua_code"); ?>
<?php if(!empty($google_ua_code)):?>
    <?php require_once 'google_analytics.php';?>
<?php endif;?>