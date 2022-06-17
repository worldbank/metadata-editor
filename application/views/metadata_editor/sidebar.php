<?php //source: https://www.codeply.com/go/7XYosZ7VH5 ?>
<div id="sidebar-container" class="sidebar-expanded d-none d-md-block col-2 mt-3">
        <!-- d-* hiddens the Sidebar in smaller devices. Its itens can be kept on the Navbar 'Menu' -->
        <!-- Bootstrap List Group -->
        <ul class="list-group sticky-top sticky-offset">
            <!-- Separator with title -->
            
            <!-- /END Separator -->


            <a href="<?php echo site_url('admin/catalog/edit/'.$survey['id']);?>" class="bg-light list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-start align-items-center">                    
                    <span class="fab fa-buffer fa-fw mr-3"></span>
                    <span class="menu-collapsed">Overview</span>
                </div>
            </a>

            <!-- Menu with submenu -->
            <a href="#submenu1x" data-toggle="collapse" aria-expanded="true" class="bg-dark text-white list-group-item list-group-item-action flex-column align-items-start">
                <div class="d-flex w-100 justify-content-start align-items-center">
                    <span class="far fa-list-alt mr-3"></span>
                    <span class="menu-collapsed">Edit</span>
                    <span class="submenu-icon ml-auto">
                        <i class="fas fa-caret-right"></i>
                    </span>
                </div>
            </a>
            <!-- Submenu content -->
            <div id="submenu1" class=" sidebar-submenu" style="max-height:300px;overflow:auto;font-size:smaller;">


            
            
                <div class="sidebar" @tree-node-click="treeNodeClickHandler()">
                <form-tree  
                    :node="form_template" 
                    :title="form_template.title"  
                    :depth="0" 
                    :css_class="'lvl-0'"                    
                    @tree-node-click="treeNodeClickHandler()"                    
                ></form-tree>

                </div>
                
            </div>
            
            <router-link to="/datafiles/<?php echo $survey['id'];?>">
                <div class="d-flex w-100 justify-content-start align-items-center">                    
                    <span class="fas fa-file-alt mr-3"></span>
                    <span class="menu-collapsed">Data files</span>
                     <!--<span class="ml-auto badge badge-pill badge-secondary ml-2">5</span>-->
                </div>
            </router-link>

            <a href="<?php echo site_url('admin/catalog/edit/'.$survey['id'].'/resources');?>" class="bg-light list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-start align-items-center">                    
                    <span class="fas fa-file-alt mr-3"></span>
                    <span class="menu-collapsed">Resources</span>
                     <!--<span class="ml-auto badge badge-pill badge-secondary ml-2">5</span>-->
                </div>
            </a>

            <a href="<?php echo site_url('admin/catalog/edit/'.$survey['id'].'/files');?>" class="bg-light list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-start align-items-center">                    
                    <span class="fas fa-upload mr-3"></span>
                    <span class="menu-collapsed">Files</span>
                </div>
            </a>

            
            <!-- /END Separator -->
            <a href="<?php echo site_url('admin/catalog/edit/'.$survey['id'].'/citations');?>" class="bg-light list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-start align-items-center">
                    <span class="fas fa-book mr-3"></span>
                    <span class="menu-collapsed">Citations</span>                    
                </div>
            </a>

            <a href="<?php echo site_url('admin/catalog/edit/'.$survey['id'].'/related-data');?>" class="bg-light list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-start align-items-center">
                    <span class="fas fa-network-wired  mr-3"></span>
                    <span class="menu-collapsed">Related</span>
                </div>
            </a>

            <a href="<?php echo site_url('admin/catalog/edit/'.$survey['id'].'/notes');?>" class="bg-light list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-start align-items-center">
                    <span class="fas fa-comment-alt mr-3"></span>
                    <span class="menu-collapsed">Notes</span>                    
                </div>
            </a>
            
            
        </ul>
        <!-- List Group END-->
    </div>
    <!-- sidebar-container END -->