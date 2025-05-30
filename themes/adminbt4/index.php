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

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">   

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"  crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>

    <link href="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/css/bootstrap4-toggle.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/js/bootstrap4-toggle.min.js"></script>

    <link href="<?php echo base_url(); ?>themes/<?php echo $this->template->theme();?>/custom.css?v=bt4" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="<?php echo base_url(); ?>themes/<?php echo $this->template->theme();?>/style.css?v=3">
    

    
    <script type="text/javascript"> 
        var CI = {'base_url': '<?php echo site_url(); ?>'}; 
    </script> 

    <?php if (isset($_styles) ){ echo $_styles;} ?>
    <?php if (isset($_scripts) ){ echo $_scripts;} ?>


    <style>
      .dropdown-submenu {
        position: relative;
      }

      .dropdown-submenu>.dropdown-menu {
        top: 0;
        left: 100%;
      }
      .dropdown-submenu ul{
        max-height:550px;
        overflow-y: scroll;
      }

      .dropdown-submenu ul li > a{
        border-bottom:1px solid gainsboro;
      }

      .dropdown-menu > li > a {
          display: block;
          padding: 3px 20px;
          clear: both;
          font-weight: normal;
          line-height: 1.42857143;
          color: #333333;
          white-space: nowrap;
      }

      .sub-header {
          background: #F1F1F1;
          background: -webkit-gradient(radial, 100 36, 0, 100 -40, 120, from(#FAFAFA), to(#F1F1F1)), #F1F1F1;
          border-bottom: 1px solid #666;
          border-color: #E5E5E5;
          height: 100px;
          width: 100%;
          margin-top: -10px;
          margin-bottom: 20px;
          padding: 10px 25px
      }


      .nada-site-admin-nav .nav > li > a {
          position: relative;
          display: block;
          padding: 10px 15px;
          color:white;
          font-size:14px;
      }

      .app-version{
        font-weight:normal;
        font-size: 0.8em;
        vertical-align: super;        
      }
    </style>

    <script>
    $(document).ready(function()  {
      /*global ajax error handler */
      $( document ).ajaxError(function(event, jqxhr, settings, exception) {
        if(jqxhr.status==401){
          window.location=CI.base_url+'/auth/login/?destination=admin/';
        }
      });
    });

    $(function() {
      $("ul.dropdown-menu [data-toggle='dropdown']").on("click", function(event) {
        event.preventDefault();
        event.stopPropagation();
        
        //method 1: remove show from sibilings and their children under your first parent
        
    /* 		if (!$(this).next().hasClass('show')) {
              
                $(this).parents('.dropdown-menu').first().find('.show').removeClass('show');
            }  */     
        
        
        //method 2: remove show from all siblings of all your parents
        $(this).parents('.dropdown-submenu').siblings().find('.show').removeClass("show");
        
        $(this).siblings().toggleClass("show");
        
        
        //collapse all after nav is closed
        $(this).parents('li.nav-item.dropdown.show').on('hidden.bs.dropdown', function(e) {
          $('.dropdown-submenu .show').removeClass("show");
        });

      });
    });
    </script>

</head>
<body>


<nav class="navbar navbar-inverse navbar-expand-lg navbar-secondary bg-dark nada-site-admin-nav">  
  <a class="navbar-brand site-title" href="<?php echo site_url();?>/admin">Editor <span class="app-version"><?php echo APP_VERSION;?></span></a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>

  <div class="collapse navbar-collapse" id="navbarSupportedContent">
    <ul class="navbar-nav mr-auto">
      <?php echo $site_navigation_menu;?>      
    </ul>
    <ul class="nav navbar-nav navbar-right float-right pull-right">
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
          <li><a target="_blank" href="<?php echo site_url();?>"><?php echo t('home');?></a></li>
        </ul>
        <?php endif;?>
      </li>
    </ul>
</nav>



<?php if(isset($collection)):?>
<div class="sub-header" > <?php echo $collection;?></div>
<?php endif;?>

    <div class="container-fluid">
        <div>             
                
            <div id="content">
              <?php if (isset($content) ):?>
                  <?php print $content; ?>
              <?php endif;?>
            </div>         
        </div>
    </div>    
    
  </body>
</html>