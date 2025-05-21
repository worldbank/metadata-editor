<?php 
/*
* Login/logout menu items
*
*/
?>
<?php
$user=$this->session->userdata('username');

?>

<li class="nav-item dropdown">
    <?php if ($user!=''): ?>
        <div class="dropdown ml-auto">
            <a class="nav-link dropdown-toggle" style="min-width:70px;" href="#" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-user-circle fa-lg"></i>  <span class="text-capitalize"><?php echo ($user); ?></span>
            </a>

            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuLink">                
                <span class="dropdown-item text-capitalize"><?php echo $user; ?></span>
                <a class="dropdown-item" href="<?php echo site_url('auth/profile'); ?>"><?php echo t('profile');?> </a>
                <a class="dropdown-item" href="<?php echo site_url('auth/change_password'); ?>"><?php echo t('password');?></a>
                <div class="dropdown-divider"></div>
                <?php if ($this->ion_auth->can_access_site_admin()): ?>
                    <a class="dropdown-item" href="<?php echo site_url('admin'); ?>"><?php echo t('site_administration');?></a>
                    <div class="dropdown-divider"></div>
                <?php endif; ?>
                <a class="dropdown-item" href="<?php echo site_url('auth/logout'); ?>"><?php echo t('logout');?></a>
            </div>
        </div>

    <?php else: ?>
            <a class="nav-link dropdown-toggle" href="" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-user-circle fa-lg"></i> <?php echo t('login');?>
            </a>
            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuLink">
                <a class="dropdown-item" href="<?php echo site_url('auth/login'); ?>"><?php echo t('login');?></a>
            </div>
            <?php if (!$this->config->item("site_user_register")=='no' || !$this->config->item("site_password_protect")=='yes'): ?>
                <a class="dropdown-toggle" href="<?php echo site_url('auth/register'); ?>" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fas fa-user-circle fa-lg"></i><?php echo t('register');?></a>
            <?php endif; ?>
    <?php endif;?>
</li>
<!-- /row -->
