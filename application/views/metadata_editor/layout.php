<splitpanes class="default-theme splitpanes splitpanes--vertical" style="min-height: 400px">
  <pane min-size="15" max-size="35" size="20" height="100">
    <!--left -->

    <div class="bg-dark  pt-3 pl-2" >
        <?php // <div class="float-right pt-0 pr-2"><v-icon dark small>mdi-cog</v-icon></div> ?>
        <a href="<?php echo site_url('admin/metadata_editor/');?>" class="brand-link-editor pb-4" style="display:block;">
            <i class="fas fa-compass"></i>
            <span class="brand-text font-weight-light">Metadata Editor</span>
        </a>
        
        <div class="row" style="display:none;">
            <div class="col-auto">                
                <v-icon dark>mdi-form-select</v-icon><small>Form fields</small>
                <v-icon dark>mdi-alpha-t-box</v-icon><small>Template</small>
            </div>
            <div class="col">
                <div class="float-right">
                <v-icon dark>mdi-alpha-t-box</v-icon><small>Template</small>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid bg-dark pt-2" style="overflow:auto;">

    
    
        <div style="font-size:small;" class="mb-5">
            <v-treeview
                dark
                color="warning"
                v-model="tree"
                :active.sync="tree_active_items" 
                @update:open="treeOnUpdate" 
                :open.sync="initiallyOpen" 
                :items="items" 
                activatable dense 
                item-key="key" 
                item-text="title"                         
                expand-icon="mdi-chevron-down"
                indeterminate-icon="mdi-bookmark-minus"
                on-icon="mdi-bookmark"
                off-icon="mdi-bookmark-outline"
                item-children="items">

                <template #label="{ item }" >
                    <span @click="treeClick(item)" :title="item.title" class="tree-item-label">
                        <span v-if="item.type=='resource'" >{{item.title | truncate(23, '...') }}</span>
                        <span v-else>{{item.title}}</span>
                    </span>
                </template>

                <template v-slot:prepend="{ item, open }">
                    <v-icon v-if="item.type=='section_container'">
                        {{ open ? 'mdi-dresser' : 'mdi-dresser' }}
                    </v-icon> 
                    <v-icon v-else-if="item.type=='section'">
                        {{ open ? 'mdi-folder-open' : 'mdi-folder' }}
                    </v-icon> 
                    <v-icon v-else-if="item.file" style="color:#949698">
                        {{ files[item.file] }}
                    </v-icon>    
                    <v-icon v-else-if="item.items" style="color:#949698">
                        {{ open ? 'mdi-folder-open' : 'mdi-folder' }}
                    </v-icon>
                    <v-icon v-else style="color:#949698">
                        {{ files['file'] }}
                    </v-icon>
                </template>
            </v-treeview>
        </div>
    </div>
    <!--end left-->
  </pane>  
  <pane size="80">
    <!-- right -->


    <div class="content-wrapper" style="margin-left:0px;">

        <section class="content-x">
            <!-- Provides the application the proper gutter -->
            <div class="container-fluid-" style="overflow:auto;">


            <nav class="main-header navbar navbar-expand navbar-white navbar-light" style="margin-left:0px;">

                <?php /* 
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
                    </li>
                </ul>
                */ ?>

                <div>
                    <div><strong>{{Title}}</strong></div>
                    <div style="font-size:small;color:gray;">{{dataset_type}} - {{StudyIDNO}}</div>
                </div>
                <div>{{this.loading_status}}</div>


                <ul class="navbar-nav ml-5 ml-auto">

                    <li class="nav-item">
                        <a class="nav-link"  href="<?php echo site_url('admin/metadata_editor/templates');?>" role="button">
                            <i class="far fa-file-alt"></i> Templates
                        </a>
                    </li>

                    <div class="dropdown">
                        <a class="btn btn-link dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-random"></i> Metadata
                        </a>

                        <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">
                            <a class="dropdown-item" href="#/import"><i class="fas fa-file-invoice"></i> Import project metadata</a>
                            <a v-if="dataset_type!=='timeseries-db'" class="dropdown-item" href="#/external-resources/import"><i class="fas fa-clone"></i> Import external resources</a>
                            <div class="dropdown-divider"></div>
                            <a v-if="dataset_type=='survey'" class="dropdown-item" :href="'<?php echo site_url('api/editor/ddi/');?>' + dataset_id" target="_blank"><i class="far fa-file-alt"></i> Export DDI CodeBook (2.5)</a>
                            <a class="dropdown-item" :href="'<?php echo site_url('api/editor/json/');?>' + dataset_id" target="_blank"><i class="far fa-file-code"></i> Export JSON</a>
                            <a v-if="dataset_type!=='timeseries-db'" class="dropdown-item" :href="'<?php echo site_url('api/editor/rdf/');?>' + dataset_id" target="_blank"><i class="far fa-file-alt"></i> Export External Resouces (RDF/XML)</a>
                            <a v-if="dataset_type!=='timeseries-db'" class="dropdown-item" :href="'<?php echo site_url('api/editor/resources/');?>' + dataset_id" target="_blank"><i class="far fa-file-alt"></i> Export External Resources (JSON)</a>
                        </div>
                    </div>

                    <li class="nav-item">
                        <a class="nav-link"  href="#/publish" role="button">
                            <i class="fas fa-location-arrow"></i> Publish
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                            <i class="fas fa-expand-arrows-alt"></i>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#" role="button" title="<?php echo $user=strtoupper($this->session->userdata('username'));?>">
                        <i class="fas fa-user"></i> 
                        </a>
                    </li>
                </ul>
            </nav>



                <?php /*
                //route path: {{$route.fullPath}}
                <div v-if="active_form_field">active_form_field.key:{{active_form_field.key}}</div>

                <div v-if="form_errors.length>0 || schema_errors.length>0" style="margin-bottom:15px;" class="pl-2">
                    <div style="color:red;font-weight:bold;">Please correct the following errors:</div>
                        <div style="color:red;" v-if="form_errors.length>0">
                            <div v-for="error in form_errors">
                                <span v-if="error.message">
                                    <i class="fas fa-times-circle"></i> {{ error.message }}
                                    <span class="label label-warning">{{error.property}}{{error.dataPath}}</span>
                                </span>
                                <span v-if="!error.message"><i class="fas fa-times-circle"></i> {{ error }}</span>
                            </div>
                        </div>
                        <div style="color:red;" v-if="schema_errors.length>0">
                            <div v-for="error in schema_errors">
                                <span v-if="error.message">
                                    <i class="fas fa-times-circle"></i> {{ error.message }}
                                    <span class="label label-warning">{{error.property}}{{error.dataPath}}</span>
                                </span>
                                <span v-if="!error.message"><i class="fas fa-times-circle"></i> {{ error }}</span>
                            </div>
                        </div>
                </div>
                */?>


                <validation-observer ref="form" v-slot="{ invalid }">                
                <div class="container-fluid p-3">
                    <keep-alive include="datafiles">
                        <router-view :key="$route.fullPath" />
                    </keep-alive>
                </div>

                </validation-observer>
            </div>

           <?php // store_state:<pre>{{$store.state}}</pre> ?>
           <?php // <pre>{{form_template}}</pre> ?>
        </section>
    </div>
    <!--end right-->
  </pane>
</splitpanes>
<?php return;?>








<div class="wrapper">

    <nav class="main-header navbar navbar-expand navbar-white navbar-light">

        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>

        <div>
            <div><strong>{{Title}}</strong></div>
            <div style="font-size:small;color:gray;">{{dataset_type}} - {{StudyIDNO}}</div>
        </div>
        <div>{{this.loading_status}}</div>
        

        <ul class="navbar-nav ml-5 ml-auto">

            <li class="nav-item">
                <a class="nav-link"  href="<?php echo site_url('admin/metadata_editor/templates');?>" role="button">
                    <i class="far fa-file-alt"></i> Templates
                </a>
            </li>

            <div class="dropdown">
                <a class="btn btn-link dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-random"></i> Metadata
                </a>

                <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">
                    <a class="dropdown-item" href="#/import"><i class="fas fa-file-invoice"></i> Import project metadata</a>
                    <a class="dropdown-item" href="#/external-resources/import"><i class="fas fa-clone"></i> Import external resources</a>
                    <div class="dropdown-divider"></div>
                    <a v-if="dataset_type=='survey'" class="dropdown-item" :href="'<?php echo site_url('api/editor/ddi/');?>' + dataset_id" target="_blank"><i class="far fa-file-alt"></i> Export DDI CodeBook (2.5)</a>
                    <a class="dropdown-item" :href="'<?php echo site_url('api/editor/json/');?>' + dataset_id" target="_blank"><i class="far fa-file-code"></i> Export JSON</a>
                    <a class="dropdown-item" :href="'<?php echo site_url('api/editor/rdf/');?>' + dataset_id" target="_blank"><i class="far fa-file-alt"></i> Export External Resouces (RDF/XML)</a>
                    <a class="dropdown-item" :href="'<?php echo site_url('api/editor/resources/');?>' + dataset_id" target="_blank"><i class="far fa-file-alt"></i> Export External Resources (JSON)</a>
                </div>
            </div>

            <li class="nav-item">
                <a class="nav-link"  href="#/publish" role="button">
                    <i class="fas fa-location-arrow"></i> Publish
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                    <i class="fas fa-expand-arrows-alt"></i>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="#" role="button" title="<?php echo $user=strtoupper($this->session->userdata('username'));?>">
                  <i class="fas fa-user"></i> 
                </a>
            </li>


            <?php  /*
            <li class="nav-item dropdown">
                
                <div class="mt-1 btn btn-primary btn-sm" data-toggle="dropdown" href="#">
                    <v-icon>mdi-alpha-t-box</v-icon> <i class="fas fa-angle-down"></i>
                </div>

                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-users mr-2"></i> Microdata Template
                        <span class="float-right text-muted text-sm">V1.0</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-file mr-2"></i> IHSN Template
                        <span class="float-right text-muted text-sm">1.0</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item dropdown-footer">View more</a>
                </div>                
            </li>            
            
            <li class="nav-item">
                <a class="nav-link" data-widget="control-sidebar" data-slide="true" href="#" role="button">
                <i class="fas fa-wrench"></i>
                </a>
            </li>
            */ ?>
        </ul>
    </nav>


    <aside class="main-sidebar sidebar-dark-primary elevation-4">

        <a href="<?php echo site_url('admin/metadata_editor/');?>" class="brand-link">
            <i class="fas fa-compass"></i>
            <span class="brand-text font-weight-light">Metadata Editor</span>
        </a>        

        <div class="sidebar mt-3">

         <ul class="nav nav-pills nav-sidebar flex-column nav-collapse-hide-child" data-widget="treeview" role="menu" data-accordion="false">

         <div class="mb-2 pl-3">
                            <v-icon>mdi-alpha-t-box</v-icon>
                            <v-icon>mdi-alpha-t-box</v-icon>
                            <v-icon>mdi-alpha-t-box</v-icon>
                            <v-icon>mdi-alpha-t-box</v-icon>
                    </div>
                    
                <li class="nav-item menu-is-opening menu-open">
                    <router-link class="nav-link" :to="'/'" >
                        <i class="nav-icon fas fa-copy"></i>
                        <p>Editor <i class="fas fa-angle-left right"></i></p>
                    </router-link>
                    
                    <ul class="nav nav-treeview" style="display: block;">                    
                    <div style="font-size:small" class="mb-5">
                    <v-treeview 
                        dark 
                        color="warning"
                        v-model="tree"
                        :active.sync="tree_active_items" 
                        @update:open="treeOnUpdate" 
                        :open.sync="initiallyOpen" 
                        :items="items" 
                        activatable dense 
                        item-key="key" 
                        item-text="title"                         
                        expand-icon="mdi-chevron-down"
                        indeterminate-icon="mdi-bookmark-minus"
                        on-icon="mdi-bookmark"
                        off-icon="mdi-bookmark-outline"
                        item-children="items">

                        <template #label="{ item }" >
                            <span @click="treeClick(item)" :title="item.title" class="tree-item-label">
                                <span v-if="item.type=='resource'" >{{item.title | truncate(23, '...') }}</span>
                                <span v-else>{{item.title}}</span>
                            </span>
                        </template>

                        <template v-slot:prepend="{ item, open }">
                            <v-icon v-if="item.type=='section_container'">
                                {{ open ? 'mdi-dresser' : 'mdi-dresser' }}
                            </v-icon> 
                            <v-icon v-else-if="item.type=='section'">
                                {{ open ? 'mdi-folder-open' : 'mdi-folder' }}
                            </v-icon> 
                            <v-icon v-else-if="item.file" style="color:#949698">
                                {{ files[item.file] }}
                            </v-icon>    
                            <v-icon v-else-if="item.items" style="color:#949698">
                                {{ open ? 'mdi-folder-open' : 'mdi-folder' }}
                            </v-icon>
                            <v-icon v-else style="color:#949698">
                                {{ files['file'] }}
                            </v-icon>
                        </template>
                    </v-treeview>
                </div>
                    </ul>
                </li>                
            </ul>

        </div>

    </aside>

    <div class="content-wrapper">
        <section class="content">
            <!-- Provides the application the proper gutter -->
            <div class="container-fluid" style="overflow:auto;">
                <?php //route path: {{$route.fullPath}} ?>
                <div v-if="active_form_field">active_form_field.key:{{active_form_field.key}}</div>

                <div v-if="form_errors.length>0 || schema_errors.length>0" style="margin-bottom:15px;" class="pl-2">
                    <div style="color:red;font-weight:bold;">Please correct the following errors:</div>
                        <div style="color:red;" v-if="form_errors.length>0">
                            <div v-for="error in form_errors">
                                <span v-if="error.message">
                                    <i class="fas fa-times-circle"></i> {{ error.message }}
                                    <span class="label label-warning">{{error.property}}{{error.dataPath}}</span>
                                </span>
                                <span v-if="!error.message"><i class="fas fa-times-circle"></i> {{ error }}</span>
                            </div>
                        </div>
                        <div style="color:red;" v-if="schema_errors.length>0">
                            <div v-for="error in schema_errors">
                                <span v-if="error.message">
                                    <i class="fas fa-times-circle"></i> {{ error.message }}
                                    <span class="label label-warning">{{error.property}}{{error.dataPath}}</span>
                                </span>
                                <span v-if="!error.message"><i class="fas fa-times-circle"></i> {{ error }}</span>
                            </div>
                        </div>
                </div>


                <validation-observer ref="form" v-slot="{ invalid }">                

                <keep-alive include="datafiles">
                    <router-view :key="$route.fullPath" />
                </keep-alive>

                </validation-observer>
            </div>

           <?php // store_state:<pre>{{$store.state}}</pre> ?>
           <?php // <pre>{{form_template}}</pre> ?>
        </section>
    </div>

    <?php /*
    <!-- Control Sidebar -->
    <aside class="control-sidebar control-sidebar-dark">
        <!-- Control sidebar content goes here -->
        <div class="p-3">
        <!-- Content of the sidebar goes here -->
        control side bar
        </div>
    </aside>
    <!-- /.control-sidebar -->
    <div id="sidebar-overlay"></div>
    */?>
</div>