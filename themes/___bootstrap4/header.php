<header class="site-header">

    <div class="container">
        <!--  /***** Login Bar Start *****/ -->
        <?php $this->load->view('users/user_bar'); ?>
        <!--  /***** Login Bar Close *****/ -->
        <div class="row">
            <div class="col-12">
                <nav class="navbar navbar-expand-md navbar-light rounded navbar-toggleable-md wb-navbar">
                    

                    <div class="navbar-brand">
                        <a href="http://www.worldbank.org" class="g01v1-logo wb-logo">
                            <img title="The World Bank Working for a World Free of Poverty" alt="The World Bank Working for a World Free of Poverty" src="<?php echo base_url().$bootstrap_theme; ?>/images/logo-wb-header-en.svg" class="img-responsive">
                        </a>
                        <strong><a class="nada-sitename" href="<?php echo site_url();?>">Microdata Library</a></strong>
                    </div>

                    <button class="navbar-toggler navbar-toggler-right collapsed wb-navbar-button-toggler" type="button" data-toggle="collapse" data-target="#containerNavbar" aria-controls="containerNavbar" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <!-- Start menus -->
                    <div class="navbar-collapse collapse" id="containerNavbar" aria-expanded="false">
                        <?php if ($menu_horizontal===TRUE):?>
                            <?php echo isset($sidebar) ? $sidebar : '';?>
                        <?php endif;?>
                    </div>
                    <!-- Close Menus -->

                </nav>
            </div>

        </div>
        <!-- /row -->

    </div>

</header>