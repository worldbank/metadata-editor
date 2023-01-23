<?php
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header('Cache-Control: no-store, no-cache, must-revalidate');
header("Pragma: no-cache");
?>
<?php //include_once APPPATH.'/config/site_menus.php'; ?>
<?php
//build a list of links for available languages
$languages=$this->config->item("supported_languages");

$lang_list='';
if ($languages!==FALSE)
{
	if (count($languages)>1)
	{
		foreach($languages as $language)
		{
			$lang_list.='| <span> '.anchor('switch_language/'.$language.'/?destination=admin', strtoupper($language)).' </span>';
		}
	}
}

$this->load->helper('site_menu');
$site_navigation_menu=get_site_menu();
?>
<!DOCTYPE html>
<html>
  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
	  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	  <base href="<?php echo js_base_url(); ?>">
	  <title><?php echo $title; ?></title>

    <link rel="stylesheet" href="<?php echo base_url()?>themes/nada/css/font-awesome.min.css">

    <!-- Bootstrap -->
    <link href="<?php echo base_url(); ?>themes/<?php echo $this->template->theme();?>/bootstrap/css/bootstrap.css" rel="stylesheet">
    <link href="<?php echo base_url(); ?>themes/<?php echo $this->template->theme();?>/custom.css" rel="stylesheet">
    <link href="<?php echo base_url(); ?>themes/<?php echo $this->template->theme();?>/plupload.css" rel="stylesheet">

    <!--bootstrap toggle button-->
    <link href="<?php echo base_url(); ?>javascript/bootstrap-toggle/css/bootstrap-toggle.min.css" rel="stylesheet">
    
    <link rel="stylesheet" type="text/css" href="<?php echo base_url(); ?>themes/<?php echo $this->template->theme();?>/style.css">
      <!--[if lt IE 8]>
        <style>
        .btn-group > .btn-mini + .dropdown-toggle{border:0px solid red;padding:4px;vertical-align:top}
        </style>
      <![endif]-->    
      <!-- Le HTML5 shim, for IE6-8 support of HTML5 elements -->
      <!--[if lt IE 9]>
        <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
      <![endif]-->

    <script src="<?php echo base_url(); ?>javascript/jquery/jquery.js"></script>
    <script src="<?php echo base_url(); ?>themes/<?php echo $this->template->theme();?>/bootstrap/js/bootstrap.min.js"></script>

    <!--bootstrap toggle button-->
    <script src="<?php echo base_url(); ?>javascript/bootstrap-toggle/js/bootstrap-toggle.min.js"></script>
    
    <script type="text/javascript"> 
        var CI = {'base_url': '<?php echo site_url(); ?>'}; 
    </script> 

    <?php if (isset($_styles) ){ echo $_styles;} ?>
    <?php if (isset($_scripts) ){ echo $_scripts;} ?>

    <script>
    $(document).ready(function()  {
      /*global ajax error handler */
      $( document ).ajaxError(function(event, jqxhr, settings, exception) {
        if(jqxhr.status==401){
          window.location=CI.base_url+'/auth/login/?destination=admin/';
        }
      });
    });
    </script>

</head>
<body>
  
<div class="navbar navbar-inverse navbar-fixed-top">
<div class="navbar-inner">
        <div class="container-fluid">
          
          <div class="navbar-header"><a class="navbar-toggle collapsed" data-toggle="collapse" data-target=".collapse">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </a>
          <a class="navbar-brand" href="<?php echo site_url();?>/admin">NADA <?php echo APP_VERSION;?></a></div>
          <div id="navbar" class="navbar-collapse collapse">
          	<?php echo $site_navigation_menu;?>
          
          <ul class="nav navbar-nav navbar-right">
              <li class="divider-vertical"></li>
              <li class="dropdown">
              <?php $user=strtoupper($this->session->userdata('username'));?>
			  <?php if ($user):?>
                <a href="#" class="dropdown-toggle" data-toggle="dropdown"><?php echo $user;?> <b class="caret"></b></a>
                <ul class="dropdown-menu">
                	<?php if ($this->session->userdata('impersonate_user')):?>
                  		<li><?php echo anchor('admin/users/exit_impersonate',t('exit_impersonate'));?></li>  
                    <?php endif;?>
                  <li><?php echo anchor('auth/change_password',t('change_password'));?></li>
                  <li><?php echo anchor('auth/logout',t('logout'));?></li>
                  <li class="divider"></li>
                  <li><a target="_blank" href="<?php echo site_url();?>"/><?php echo t('home');?></a></li>
                  <li><a  target="_blank" href="<?php echo site_url();?>/catalog"/><?php echo t('data_catalog');?></a></li>
                  <li><a  target="_blank" href="<?php echo site_url();?>/citations"/><?php echo t('citations');?></a></li>
                </ul>
                <?php endif;?>
              </li>
            </ul>
        </div>
      </div>  
</div>

</div>

<?php if(isset($collection)):?>
<div class="sub-header" > <?php echo $collection;?></div>
<?php endif;?>
    
    <div class="container-fluid">
        <div>
             
             <!--breadcrumbs -->
			<?php $breadcrumbs_str= $this->breadcrumb->to_string();?>
            <?php if ($breadcrumbs_str!=''):?>
                <div id="breadcrumb" class="notabs">
                <?php echo $breadcrumbs_str;?>
                </div>
            <?php endif;?>
                
            <div id="content">
            <?php if (isset($content) ):?>
                <?php print $content; ?>
            <?php endif;?>
            </div> 
        
        </div>
    </div>    
    
  </body>
</html>