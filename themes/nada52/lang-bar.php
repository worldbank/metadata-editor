<?php
//build a list of links for available languages
$languages=$this->config->item("supported_languages");

//$languages=array("english","french");

$lang_ul='';
if ($languages!==FALSE){
	if (count($languages)>1){
		foreach($languages as $language){
			$lang_ul.='<span class="lang-label"> '.anchor('switch_language/'.$language.'/?destination=catalog', strtoupper(t(strtolower($language)))).' </span>';
		}
	}
}
?>

<?php if($lang_ul!=''):?>
<div class="container-fluid wb-user-bar">
	<div class="container">
		<div class="float-right">
			<span class="lang-container">
				<?php echo $lang_ul; ?>
			</span>	
		</div>
		<br/>
	</div>
</div>
<?php endif;?>