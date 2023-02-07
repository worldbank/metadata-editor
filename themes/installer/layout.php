<?php
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Editor Installer</title>
<base href="<?php echo js_base_url(); ?>" />
<link rel="stylesheet" type="text/css" href="themes/<?php echo $this->template->theme();?>/reset-fonts-grids.css" />
<link rel="stylesheet" type="text/css" href="themes/<?php echo $this->template->theme();?>/styles.css" />
<link rel="stylesheet" type="text/css" href="themes/<?php echo $this->template->theme();?>/forms.css" />
<script type="text/javascript" src="javascript/jquery.js"></script>

<?php if (isset($_styles) ){ echo $_styles;} ?>
<?php if (isset($_scripts) ){ echo $_scripts;} ?>

<script type="text/javascript"> 
   var CI = {'base_url': '<?php echo site_url(); ?>'}; 
</script> 

</head>
<body>
<div>&nbsp;</div>
<div id="custom-doc" class="" > 

	
    <!-- header -->
    <div id="hd">
    
       	<!-- logo -->
        <div class="site-logo">
            <div style="float:left;padding-top:15px;">Editor Installer</div>
        </div>
        <div style="float:right;margin-top:40px;">
        	<?php foreach($this->languages as $lang):?>
            	<a href="<?php echo site_url();?>/install/language/<?php echo $lang;?>"><?php echo strtoupper($lang);?></a>&nbsp;
            <?php endforeach;?>
        </div>
    </div>
    
    <div id="bd" >
    
    	<!-- banner-->        
		<div id="site-banner" >
                <div style="float:left;width:70%;margin-top:5px;">
	                <div  id="banner"><a href="<?php echo site_url(); ?>/install/"><?php echo t('installer_title');?></a></div>
                </div>
                <br style="clear:both"/>                
        </div>

        <div id="inner-body">

                 <!-- page contents --> 
                <div id="yui-main">
                        <div id="content" class="yui-b"><?php echo isset($content) ? $content : '';?></div>
                </div>
            
		</div>
	</div>


    <!-- footer -->
    <div id="ft"> </div>
	<!--end bd-->

</div>
<div style="padding-bottom:100px;">&nbsp;</div>
</body>
</html>