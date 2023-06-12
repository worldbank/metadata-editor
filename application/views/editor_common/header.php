
    <nav class="main-header shadow sticky-top navbar navbar-expand-md navbar-light navbar-white">

    
        <a href="<?php echo site_url(''); ?>" class="navbar-brand"><i class="fas fa-compass"></i> <span class="brand-text font-weight-light">Metadata Editor</span></a>


        <ul class="navbar-nav ml-auto">

            <li class="nav-item">
                <a class="nav-link" href="<?php echo site_url('editor'); ?>" role="button">
                <i class="mdi mdi-folder-multiple-outline"></i> My projects
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="<?php echo site_url('collections'); ?>" role="button">
                Collections
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="<?php echo site_url('editor/templates'); ?>" role="button">
                    <i class="mdi mdi-alpha-t-box-outline"></i> Templates                
                </a>
            </li>
        </ul>


        <ul class="navbar-nav ml-auto">
          <li class="nav-item">
              <?php echo $this->load->view('user_menu/user-menu',null,true);?>                        
          </li>
        </ul>

        
      </nav>
