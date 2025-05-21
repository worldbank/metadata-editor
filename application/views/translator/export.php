<h3>Download Language Package</h3>
<ul>
<?php foreach($languages as $lang):?>
	<li><a style="text-transform:capitalize" href="<?php echo site_url();?>/translate/export/<?php echo $lang;?>"><?php echo $lang;?></a></li>
<?php endforeach;?>
</ul>