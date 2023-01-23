<?php
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header('Cache-Control: no-store, no-cache, must-revalidate');
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html>
  <head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<base href="<?php echo js_base_url(); ?>">
		<title><?php echo $title; ?></title>

    <!-- Bootstrap / jquery -->
    <link href="<?php echo base_url(); ?>themes/<?php echo $this->template->theme();?>/bootstrap/css/bootstrap.css" rel="stylesheet">
    <link href="<?php echo base_url(); ?>themes/<?php echo $this->template->theme();?>/custom.css" rel="stylesheet">
	<script src="<?php echo base_url(); ?>themes/<?php echo $this->template->theme();?>/jquery/jquery-3.2.1.min.js"></script>
    <script src="<?php echo base_url(); ?>themes/<?php echo $this->template->theme();?>/bootstrap/js/bootstrap.min.js"></script>


  <script type="text/javascript">
   		var CI = {'base_url': '<?php echo site_url(); ?>'};
	</script>

	<?php if (isset($_styles) ){ echo $_styles;} ?>
  <?php if (isset($_scripts) ){ echo $_scripts;} ?>

  <style>
	  body{
		  padding-top:0px;
	  }
  </style>

	<script type="text/javascript">
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

		<div id="custom-doc-blank" >
				<?php if (isset($content) ):?>
						<?php print $content; ?>
				<?php endif;?>
		</div>

  </body>
</html>
