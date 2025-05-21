<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Metadata editor [beta]</title>
<?php /* <base href="<?php echo base_url(); ?>"> */ ?>
<?php if (isset($_meta) ){ echo $_meta;} ?>

<?php if($use_cdn):?>    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">    
<?php else:?>
    <link href="<?php echo base_url().$bootstrap_theme ?>/fontawesome/css/all.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo base_url().$bootstrap_theme; ?>/css/bootstrap.min.css">
<?php endif;?>    

<link href="https://cdn.jsdelivr.net/npm/@mdi/font@6.x/css/materialdesignicons.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?php echo base_url().$bootstrap_theme ?>/css/style.css?v2024">

<?php if($use_cdn):?>
    <script src="//code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>    
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