<?php
header('Cache-Control: no-store, no-cache, must-revalidate');
header ("Pragma: no-cache"); 
header("Expires: -1");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo $title; ?></title>
<base href="<?php echo js_base_url(); ?>">
<link rel="stylesheet" type="text/css" href="themes/<?php echo $this->template->theme();?>/reset-fonts-grids.css" />
<link rel="stylesheet" type="text/css" href="themes/<?php echo $this->template->theme();?>/styles.css" />
<link rel="stylesheet" type="text/css" href="themes/<?php echo $this->template->theme();?>/forms.css" />

<script type="text/javascript" src="javascript/jquery.js"></script>
<script type="text/javascript"> 
   var CI = {'base_url': '<?php echo site_url(); ?>'}; 
</script> 

<?php if (isset($_styles) ){ echo $_styles;} ?>
<?php if (isset($_scripts) ){ echo $_scripts;} ?>

<script type="text/javascript"> 
 /* <![CDATA[ */
 if(top.frames.length > 0) top.location.href=self.location;
 /* ]]> */
</script> 
<style type="text/css">
	body,html{background-color:white !important ;padding:0px;margin:0px;text-align:left;}
.header{
background: url(themes/opendata/sprite.png) 0px -170px repeat-x;
clear: both;height:60px;overflow:hidden;}
.header .title, .title a{
	font-size:18px;color:white;padding:16px;
}
.body-content	{margin:10px;}
</style>
</head>
<body>
<div class="header">
	<div class="title"><?php echo anchor ("",$this->config->item('website_title'),'class="jx"'); ?></div>
</div>

    <div class="body-content">
        <?php if (isset($content) ):?>
            <?php print $content; ?>
        <?php endif;?>
    </div>
</body>
</html>