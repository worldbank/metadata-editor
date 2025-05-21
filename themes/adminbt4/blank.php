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

	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css" crossorigin="anonymous" />
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">   

	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"  crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
	<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>

	<link href="<?php echo base_url(); ?>themes/<?php echo $this->template->theme();?>/style.css" rel="stylesheet">
	<link href="<?php echo base_url(); ?>themes/<?php echo $this->template->theme();?>/custom.css" rel="stylesheet">


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
