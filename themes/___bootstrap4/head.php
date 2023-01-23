<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?php echo $title; ?></title>
<!--<base href="<?php echo base_url(); ?>">-->
<meta name="description" content="Central Microdata Catalog">

<link rel="stylesheet" href="<?php echo base_url().$bootstrap_theme ?>/css/font-awesome.min.css">

<?php if($use_cdn):?>    
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
<?php else:?>
    <link rel="stylesheet" href="<?php echo base_url().$bootstrap_theme; ?>/css/bootstrap.min.css">
<?php endif;?>    

<link rel="stylesheet" href="<?php echo base_url().$bootstrap_theme ?>/css/style.css?v2">
<link rel="stylesheet" href="<?php echo base_url().$bootstrap_theme ?>/css/custom.css?v2">

<!-- Bootstrap core JavaScript
================================================== -->
<!-- Placed at the end of the document so the pages load faster -->
<!--<script src="https://code.jquery.com/jquery-3.1.1.slim.min.js"></script>-->

<script src="//code.jquery.com/jquery-3.2.1.min.js"></script>
<!--    <script src="//code.jquery.com/jquery-migrate-3.0.1.min.js"></script>-->

<?php if($use_cdn):?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>
<?php else:?>
    <script src="<?php echo base_url().$bootstrap_theme ?>/js/popper.min.js"></script>
    <script src="<?php echo base_url().$bootstrap_theme ?>/js/bootstrap.min.js"></script>
<?php endif;?>

<script src="<?php echo base_url().$bootstrap_theme ?>/js/script.js"></script>

<!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
<!--<script src="--><?php //echo base_url().$bootstrap_theme ?><!--/js/ie10-viewport-bug-workaround.js"></script>-->
<!-- tooltips  -->

<script type="text/javascript" src="<?php echo base_url();?>javascript/jquery.ba-bbq.js"></script>
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