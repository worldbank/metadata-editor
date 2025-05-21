<?php $missing=array();?>
<?php foreach($template_file as $key=>$value): ?>
<?php
	$lang_key_found=array_key_exists($key, $edit_file);
	$lang_value='';
	if ($lang_key_found)
	{
		$lang_value=($edit_file[$key]);
	}
	else
	{
		$missing[$key]=$value;
	}
?>
<?php if ($lang_key_found) :?>
$lang['<?php echo $key;?>']="<?php echo str_replace('"','&quot;',$lang_value);?>";<?php echo "\n";?>
<?php endif;?>
<?php endforeach;?>

<?php if ($fill_missing):?>
<?php foreach($missing as $key=>$value):?>
$lang['<?php echo $key;?>']="<?php echo str_replace('"','&quot;',$value);?>";<?php echo "\n";?>
<?php endforeach;?>
<?php endif;?>

/* End of file <?php echo $language_file; ?> */
/* Location: ./application/language/<?php echo $language; ?>/<?php echo $language_file; ?> */