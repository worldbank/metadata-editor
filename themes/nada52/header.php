
<nav class="main-header shadow navbar navbar-expand-md navbar-light navbar-white">

    
<a href="<?php echo site_url(''); ?>" class="navbar-brand"><i class="fas fa-compass"></i> <span class="brand-text font-weight-light">Metadata Editor</span></a>


<ul class="navbar-nav ml-auto">

    <li class="nav-item">
        <a class="nav-link" href="<?php echo site_url('editor'); ?>" role="button">
        <i class="mdi mdi-folder-multiple-outline"></i> <?php echo t('my_projects'); ?>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link" href="<?php echo site_url('editor/templates'); ?>" role="button">
            <i class="mdi mdi-alpha-t-box-outline"></i> <?php echo t('templates'); ?>
        </a>
    </li>
</ul>


<ul class="navbar-nav ml-auto">    
    <li class="nav-item">
        <?php echo $this->load->view('user_menu/lang-bar',null,true);?>
    </li>
    <li class="nav-item">
        <?php echo $this->load->view('user_menu/user-menu',null,true);?>
    </li>
</ul>


</nav>
