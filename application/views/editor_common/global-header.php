<nav class="main-header sticky-top navbar navbar-expand navbar-dark navbar-dark editor-header">

    <ul class="navbar-nav">
        <div class="">
            <a href="<?php echo site_url('projects');?>" class="navbar-brand">
                <i class="fas fa-compass" ></i> 
                <span class="brand-text font-weight-light color-white">Metadata Editor</span>
            </a>
        </div>
    </ul>

    <ul class="navbar-nav ml-auto">    

    <li class="nav-item">
        <a class="nav-link" href="<?php echo site_url('about'); ?>" role="button">
            <i class="mdi mdi-text-box"></i> <?php echo t('About'); ?>
        </a>
    </li>
    <li class="nav-item"><span class="nav-link"><div class="border-left-x">|</div></span></li>

    <li class="nav-item">
        <?php echo $this->load->view('user_menu/lang-bar',null,true);?>
    </li>
    <li class="nav-item">
        <?php echo $this->load->view('user_menu/user-menu',null,true);?>
    </li>
</ul>

</nav>