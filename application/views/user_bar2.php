<?php 
/*
* Shows the login/logout links at the top of the page
*
*/
?>
<?php
//build a list of links for available languages
$languages=$this->config->item("supported_languages");

$uri        = $this->uri->segment(2);
$links   = array('', 'update', 'study', 'datafiles', 'citations', 'summary', 'submit');
$color   = array_search($uri, $links);
$lang_ul='';
if ($languages!==FALSE)
{
	if (count($languages)>1)
	{
		foreach($languages as $language)
		{
			$lang_ul.='<li> '.anchor('switch_language/'.$language.'/?destination=catalog', strtoupper(t(strtolower($language)))).' </li>';
		}
	}
}
?>
<script type="text/javascript">
$(function() {
	$('div.button').click(function() {
		$(this).parents('form').submit();
	});
	colors=[<?php foreach($links as $link) { echo '\'', $link, '\'', ','; }?>];
	$.each(colors, function(index, value) {
		uri="<?php echo $uri; ?>";
		if (value == uri) {
			$('.tab-header').attr('id', 'color'+index);
			$('.m-head').attr('id', 'color'+index);
			$('.tab-content').css({borderColor: $('.m-head').css('background-color')});
			$('.m-body').css({borderColor: $('.m-head').css('background-color')});
			var l = [
				'0',
				'5px',
				'170px',
				'330px',
				'490px',
				'655px',
				'815px'
			];
			if ($('.navbox').length == 1) {
				l[5] = '55px';
				$('.navbox').children('img').css('display', 'none');
			}
			$('#here').css({position:"relative",left:l[index]});
			$('.tab-header').html($('.navbox#color'+index).children('a').html());
		}
	});
});
</script>
<div id="user-bar">
<p style="color:#fff;font-size:14pt;font-weight:bold;margin-top:10px;float:left">Data Deposit</p>
<?php $user=$this->session->userdata('username'); ?>
<?php if ($user!=''):?>
    <div class="user-box">
        <ul>                
            <li class="username"><?php echo $user; ?></li>
            <?php //if ($this->session->userdata('group_id')==1):?>
            <li class="profile"><a href="<?php echo site_url(); ?>/auth/profile"><?php echo t('profile');?></a></li>
            <li class="password"><a href="<?php echo site_url(); ?>/auth/change_password"><?php echo t('password');?></a></li>                                    
            <li><a href="<?php echo site_url(); ?>/auth/logout"><?php echo t('logout');?></a></li>
            <?php echo $lang_ul;?>
        </ul>        
    </div>
<?php else:?>

<?php endif;?>
</div>
