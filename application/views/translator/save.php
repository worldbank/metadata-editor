<?php 
/*
	Creates the formatted output for the language file
*/


?>
<?php foreach($translations as $key=>$value): ?>
$lang['<?php echo $key;?>']="<?php echo str_replace('"','&quot;',$value);?>";<?php echo "\n";?>
<?php endforeach;?>

/* End of file <?php echo $translation_file; ?> */
/* Location: ./application/language/<?php echo $language_name; ?>/<?php echo $translation_file; ?> */