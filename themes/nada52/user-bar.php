<?php 
/*
* Shows the login/logout links at the top of the page
*
*/
?>
<?php
//build a list of links for available languages
$languages=$this->config->item("supported_languages");

$lang_ul='';
if ($languages!==FALSE)
{
	if (count($languages)>1)
	{
		foreach($languages as $language)
		{
			$lang_ul.='<span class="lang-label"> '.anchor('switch_language/'.$language.'/?destination=catalog', strtoupper(t(strtolower($language)))).' </span>';
		}
	}
}

$user=$this->session->userdata('username');

?>
<div class="row">
    <?php if ($user!=''): ?>
        <div class="col-12 mt-2 mb-2 wb-login-link login-bar">
            <div class="float-right">
            <div class="dropdown ml-auto">
                <a class="dropdown-toggle small" href="#" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-user-circle fa-lg"></i><?php echo $user; ?>
                </a>

                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuLink">
                    <a class="dropdown-item small" href="<?php echo site_url('auth/profile'); ?>"><?php echo t('profile');?></a>
                    <a class="dropdown-item small" href="<?php echo site_url('auth/change_password'); ?>"><?php echo t('password');?></a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item small" href="<?php echo site_url('auth/logout'); ?>"><?php echo t('logout');?></a>
                </div>
                <?php if($lang_ul!=''):?>
                <span class="lang-container">
                    <?php echo $lang_ul; ?>
                </span>
                <?php endif;?>
            </div>
            </div>

        </div>
    <?php else: ?>
        <div class="col-12 mt-2 mb-2 wb-login-link login-bar">
            <div class="float-right">
            
            <div class="dropdown ml-auto">
                <a class="dropdown-toggle small" href="" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fas fa-user-circle fa-lg"></i><?php echo t('login');?></a>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuLink">
                    <a class="dropdown-item small" href="<?php echo site_url('auth/login'); ?>"><?php echo t('login');?></a>
                </div>
                <?php if (!$this->config->item("site_user_register")=='no' || !$this->config->item("site_password_protect")=='yes'): ?>
                    <a class="dropdown-toggle small" href="<?php echo site_url('auth/register'); ?>" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fas fa-user-circle fa-lg"></i><?php echo t('register');?></a>
                <?php endif; ?>

                <?php if($lang_ul!=''):?>
                <span class="lang-container">
                    <?php echo $lang_ul; ?>
                </span>
                <?php endif;?>
            </div>
            </div>
        </div>

    <?php endif;?>
</div>
<!-- /row -->
