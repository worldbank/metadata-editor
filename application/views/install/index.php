<style>
	h1{font-size:16px;font-weight:bold;}
	.green{color:green;padding:5px;font-weight:bold;}
	.red{background-color:red;display:block;color:white;padding:5px;}
	.required{color:red;font-weight:bold;}
	.optional{color:gray;font-size:11px}
</style>

<div class="mt-50 p-3">

<div class="break">&nbsp;</div>
<div style="padding:5px;">

<h1><?php echo t('server_information');?></h1>
<table class="grid-table table table-sm  table-borderless">
<tr>
	<td><?php echo t('php_version');?>:</td>
    <td class="font-weight-bold"><?php echo  $php_version;?></td>
</tr>
<?php if ($db_connect===TRUE):?>
<tr> 
	<td><?php echo t('db_version');?>:</td>
    <td class="font-weight-bold text-success"><?php echo $db_version;?> - <?php echo t('connection_success');?></td>
</tr>
<?php else:?>
<tr>
	<td><?php echo t('db_version');?>:</td>
    <td class="text-danger"><?php echo t('database_connection_failed');?></td>
</tr>
<?php endif;?>
<tr>
	<td><?php echo t('web_server');?>:</td>
    <td class="font-weight-bold"><?php echo $_SERVER['SERVER_SOFTWARE'];?></td>
</tr>
</table>

<?php echo $extensions;?>
<?php echo $other_settings;?>
<?php echo $permissions;?>

</div>
<?php if ($db_connect===TRUE):?>
<form method="post" action="<?php echo site_url();?>/install/installing">
<div class="bg-light text-right p-2">
<input class="btn btn-sm btn-primary" type="submit" value="<?php echo t('install_database'); ?>" />
</div>
</form>
<?php else:?>
<div class="red">
	<?php echo t('database_error_cant_continue');?>
</div>
<?php endif; ?>

</div>