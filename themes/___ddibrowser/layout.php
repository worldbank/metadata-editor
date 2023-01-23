<?php
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header('Cache-Control: no-store, no-cache, must-revalidate');
header("Pragma: no-cache");
$menu_horizontal=FALSE;
$secondary_menu=FALSE;
$secondary_menu_formatted=FALSE;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
          "http://www.w3.org/TR/html4/strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo t($title);?></title>
<?php if (isset($_meta) ){ echo $_meta;} ?>
<meta property="og:image" content="<?php echo base_url().$this->config->item("fb_catalog_image");?>" />

<base href="<?php echo js_base_url(); ?>" />
<link rel="stylesheet" type="text/css" href="themes/<?php echo $this->template->theme();?>/reset-fonts-grids.css" />
<link rel="stylesheet" type="text/css" href="javascript/superfish/css/superfish.css" media="screen">
<link rel="stylesheet" type="text/css" href="themes/<?php echo $this->template->theme();?>/styles.css" />
<link rel="stylesheet" type="text/css" href="themes/<?php echo $this->template->theme();?>/forms.css" />

<link rel="stylesheet" type="text/css" href="javascript/jquery/themes/ui-lightness/ui.core.css" />
<link rel="stylesheet" type="text/css" href="javascript/jquery/themes/ui-lightness/ui.base.css" />
<link rel="stylesheet" type="text/css" href="javascript/jquery/themes/ui-lightness/ui.accordion.css" />
<link rel="stylesheet" type="text/css" href="javascript/jquery/themes/ui-lightness/ui.theme.css" />

<script src="javascript/jquery-1.5.1.min.js"></script>
<script type="text/javascript" src="javascript/ui.core.js"></script>
<script type="text/javascript" src="javascript/jquery/ui/ui.tabs.js"></script>
<script src="javascript/tree/jquery.treeview.pack.js"></script>
	<script>
		$(function(){
			//tree-view 
			$(".filetree").treeview({collapsed: false});
		});
	</script>


<?php if (isset($_styles) ){ echo $_styles;} ?>
<?php if (isset($_scripts) ){ echo $_scripts;} ?>
<link rel="stylesheet" href="javascript/tree/jquery.treeview.css" />
<link rel="stylesheet" type="text/css" href="themes/opendata/opendata.css" />
<link rel="stylesheet" type="text/css" href="themes/<?php echo $this->template->theme();?>/ddi.css" />
<!--[if lt IE 8]>
<style type="text/css">
    .ui-tabs .ui-tabs-nav li{padding-bottom:4px;}
    .ui-tabs .ui-tabs-nav li.ui-tabs-selected{padding-bottom:4px;}
    .ui-tabs .ui-tabs-nav li a{padding:5px;padding-bottom:4px;}
</style>
<![endif]-->

<style>
.ui-layout-content ul{border:none;}
.ui-layout-content ul li.active{font-weight:bold;background-color:#F7F7F7}
</style>
<script type="text/javascript"> 
 /* <![CDATA[ */
 if(top.frames.length > 0) top.location.href=self.location;
 /* ]]> */
</script> 

<script type="text/javascript"> 
   var CI = {
				'base_url': '<?php echo site_url(); ?>',
				'current_section': '<?php echo site_url().'/'.$this->uri->segment(1).'/'.$this->uri->segment(2); ?>',
				'js_loading': '<?php echo t('js_loading'); ?>'  
			}; 	
</script> 


<script type="text/javascript">
function adjust_sidebar(){
	$("#sidebar-wrap").height("auto");
  if ($("#sidebar-wrap").height()<$("#yui-main").height()) {
  	$("#sidebar-wrap").height($("#yui-main").height());//fix sidebar height
	}
}

jQuery(function(){
	$("#footer-glob-links").hide();
	$("#glob-nav-toggle").click(function(e){toggle_global_nav();});
	adjust_sidebar();
});

$.ajax({
   complete: function(){
	 adjust_sidebar();
   }
});
 
$(document).ajaxComplete(function() {
adjust_sidebar();
});

//collapse/expand div
function toggle_global_nav(e){
	if ($("#footer-glob-links").is(":visible")){
		$("#footer-glob-links").slideUp("slow");
		$("#glob-nav-toggle img").attr('src','themes/<?php echo $this->template->theme();?>/plus.png');
	}
	else{
		$("#footer-glob-links").slideDown("slow");
		$('#glob-nav-toggle img').attr('src','themes/<?php echo $this->template->theme();?>/minus.png');
	}
}	

$(document).ready(function () { 
		bind_behaviours();
});	

function get_variable(id)
{
	//panel id
	var pnl="#pnl-"+id;
	
	//collapse
	if ($("#"+id).is(".pnl-active")){
		$("#"+id).toggleClass("pnl-active");
		$(pnl).parent().hide();
		return;
	}

	//unset any active panels
	$('.table-variable-list tr').removeClass("pnl-active");
		
	//expand
	ajax_error_handler('pnl-'+id);
	url=CI.current_section+'/variable/'+id;

	//hide any open panels
	$('.var-info-panel').hide();
	
	//show/hide panel
	$("#"+id).toggleClass("pnl-active");
	$(pnl).parent().show();
	$(pnl).html('<img src="images/loading.gif" border="0"/> '+ CI.js_loading);
	$(pnl).load(url+'?ajax=true', function(){
		var fooOffset = jQuery('.pnl-active').offset(),
        destination = fooOffset.top;
	    $('html,body').animate({scrollTop: destination-50}, 500);
	})
}

//show/hide resource
function toggle_resource(element_id){
	$("#"+element_id).toggle();
}
	
function bind_behaviours() {
	//show variable info by id
	$(".table-variable-list .row-color1, .table-variable-list .row-color2, .table-variable-list .row").click(function(){
		if($(this).attr("id")!=''){
			get_variable($(this).attr("id"));
		}
		return false;
	});	
}


function ajax_error_handler(id)	
{
	$.ajaxSetup({
		error:function(XHR,e)	{
			$("#"+id).html('<div class="error">'+XHR.responseText+'</div>');
		}				
	});	
}
</script>
</head>
<body>
<div id="custom-doc" class="<?php echo ($secondary_menu_formatted!==FALSE) ? 'yui-t1' : 'yui-t1'; ?>" > 
	<!--login information bar-->
    <?php //$this->load->view('user_bar');?>	
    
    <!-- masthead -->
    <div id="hd">    
       	<!-- logo -->
        <div class="site-logo">
        	<a title="<?php echo $this->config->item("website_title");?> - Home Page"  href="<?php echo site_url();?>">
            <img src="themes/<?php echo $this->template->theme();?>/wb-logo.gif"  border="0" alt="Logo"/>
            </a>
        </div>      	      
    </div>
    
    <!-- title bar-->        
    <div id="title-bar" >
            <div class="site-name"><a href="http://data.worldbank.org/">Data</a></div>
    </div>
        
	<div style="clear:both;"></div>
    
    <!-- content wrapper -->
    <div id="bd" >
        <div id="inner-body">           
            
            <!-- page contents -->	
            <div id="yui-main">            	
				<div id="content" class="yui-b">                

					<div id="page-tools">
					<?php include 'share.php';?>
                	</div>
                    
                    <?php $breadcrumbs_str= $this->breadcrumb->to_string();?>
                	<!-- bread crumbs -->                    
					<?php if ($breadcrumbs_str!=''):?>
                        <div id="breadcrumb">
						<?php echo $breadcrumbs_str;?>
                        </div>
                    <?php endif;?>
               
            <?php 
				$tab_urls=array('home','catalog','citations','xaccess_licensed');
				$catalog_tabs=array('catalog','xaccess_licensed');
				
				$show_tab=TRUE;
				
				if ($this->uri->segment(1)=='catalog' && $this->uri->segment(2)!==FALSE)
				{
					$show_tab=FALSE;
				}
				
			if (in_array($this->uri->segment(1),$tab_urls) && $show_tab===TRUE):?>
			<!-- tabs -->
            <h1><?php echo $this->config->item("website_title");?></h1>
            <div class="ui-tabs ui-widget ui-widget-content ui-corner-all" id="tabs">
            <div class="tab-heading">&nbsp;</div>
                <ul class="ui-tabs-nav ui-helper-reset ui-helper-clearfix ui-widget-header ui-corner-all">
                    <li class="ui-state-default ui-corner-top <?php echo ($this->uri->segment(1)=='home') ? 'ui-tabs-selected ui-state-active' : '';?>"><a href="<?php echo site_url();?>/home">About</a></li>
                    <li class="ui-state-default ui-corner-top <?php echo (in_array($this->uri->segment(1),$catalog_tabs)) ? 'ui-tabs-selected ui-state-active' : '';?>"><a href="<?php echo site_url();?>/catalog">Datasets</a></li>
                    <?php if ($this->config->item("hide_citations")!=='yes'):?>
                    <li class="ui-state-default ui-corner-top <?php echo ($this->uri->segment(1)=='citations') ? 'ui-tabs-selected ui-state-active' : '';?>"><a href="<?php echo site_url();?>/citations">Citations</a></li>
                    <?php endif;?>
                </ul>
                <div class="ui-tabs-panel ui-widget-content ui-corner-bottom" id="tabs-1">
					<!--breadcrumbs -->
					<?php if (isset($breadcrumb) && $breadcrumb!==''):?>
						<div id="nada-breadcrumb">
						<?php echo $breadcrumb;?>
						</div>
					<?php endif;?>
                    <!-- page content area -->
                    <?php echo isset($content) ? $content : '';?>
                    
                </div>            
            </div>
            <?php else:?>
             					
                <div style="margin-top:20px;">
                <?php if (isset($survey_title)):?>
                    	<h2><?php echo $survey_title;?></h2>
                <?php endif;?>
                </div>
                
              	<div class="page-body" >
					<?php echo isset($content) ? $content : '';?>
                </div>
                
                <div class="sidebar" >
					<?php echo isset($sidebar) ? $sidebar : ''; ?>
    		  	</div>
      		<?php endif;?>
            
      </div>
      </div>
            
		</div>
	</div>
    <!-- highlights -->
    <?php if (isset($this->blocks['highlights'])):?>
			<?php echo $this->blocks['highlights'];?>
    <?php endif;?>

	

	<!-- footer legal & copyrighs -->
    <div id="footer"><?php echo $this->config->item("website_footer");?></div>

    <!-- footer -->    
	<div id="ft">&nbsp;</div>

</div>
<div style="padding-bottom:100px;">&nbsp;</div>
<?php @include_once(APPPATH.'/../tracking.php');?>
</body>
</html>