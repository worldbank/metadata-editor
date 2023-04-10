<v-app style="position:relative;height: 100vh">
<!--header-->

<nav class="main-header sticky-top navbar navbar-expand navbar-white navbar-light bg-light border-bottom" style="margin-left:0px;">

    <div class="navbar-brand" style="min-width:280px;">
        <a href="<?php echo site_url('editor/'); ?>" class="brand-link-editor p-2" style="display:block;">
            <i class="fas fa-compass"></i>
            <span class="brand-text font-weight-light">Metadata Editor</span>
        </a>
    </div>

    <div class="pl-2">
        <div><strong>{{Title}}</strong></div>
        <div style="font-size:small;color:gray;">{{dataset_type}} - {{StudyIDNO}}</div>
    </div>
    <div>{{this.loading_status}}</div>


    <ul class="navbar-nav ml-5 ml-auto">

        <div class="dropdown">
            <a class="btn btn-link dropdown-toggle" href="#" role="button" id="dropdownProjectMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                Project
            </a>

            <div class="dropdown-menu" aria-labelledby="dropdownProjectMenu">
                <a class="dropdown-item" href="#/project-package">
                    <span style="font-size:20px;"  class="mdi mdi-package-down"></span> Export project (zip)</a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="#/publish"><span style="font-size:20px;" class="mdi mdi-arrow-top-right-thick"></span> Publish to NADA</a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="#/generate-pdf"><span style="font-size:20px;" class="mdi mdi-file-pdf-box"></span> PDF Documentation</a>
            </div>
        </div>

        <div class="dropdown">
            <a class="btn btn-link dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-random"></i> Metadata
            </a>

            <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">
                <a class="dropdown-item" href="#/import"><i class="fas fa-file-invoice"></i> Import project metadata</a>
                <a class="dropdown-item" href="#/external-resources/import"><i class="fas fa-clone"></i> Import external resources</a>
                <div class="dropdown-divider"></div>
                <a v-if="dataset_type=='survey'" class="dropdown-item" :href="'<?php echo site_url('api/editor/ddi/'); ?>' + dataset_id" target="_blank"><i class="far fa-file-alt"></i> Export DDI CodeBook (2.5)</a>
                <a class="dropdown-item" :href="'<?php echo site_url('api/editor/json/'); ?>' + dataset_id" target="_blank"><i class="far fa-file-code"></i> Export JSON</a>
                <a class="dropdown-item" :href="'<?php echo site_url('api/editor/rdf/'); ?>' + dataset_id" target="_blank"><i class="far fa-file-alt"></i> Export External Resouces (RDF/XML)</a>
                <a class="dropdown-item" :href="'<?php echo site_url('api/editor/resources/'); ?>' + dataset_id" target="_blank"><i class="far fa-file-alt"></i> Export External Resources (JSON)</a>
            </div>
        </div>

        <li class="nav-item">
            <?php echo $this->load->view('user_menu/user-menu', null, true); ?>
        </li>
    </ul>
</nav>

<!--end-header-->

<splitpanes class="default-theme splitpanes splitpanes--vertical" style="min-height: 100px">
    <pane min-size="15" max-size="35" size="20" class="editor-sidebar">
        <!--left -->

        <div class="container-fluid bg-secondary-light pt-2 pb-3 editor-sidebar-container">

            <!-- icons -->
            <div class="bg-dark-x pb-2 sidebar-menu-bar">
                    <button type="button" title="Expand/Collapse" class="btn btn-xs btn-outline-secondary">
                        <i class="icon fas fa-compress-arrows-alt"></i>
                    </button>

                    <button type="button" title="Show mandatory fields" class="btn btn-xs btn-outline-secondary" :class="{ active: show_fields_mandatory }" @click="toggleFields('mandatory')">
                        <v-icon class="icon" >mdi-check-circle</v-icon>
                    </button>

                    <button type="button" title="Show recommended fields" class="btn btn-xs btn-outline-secondary" :class="{ active: show_fields_recommended }" @click="toggleFields('recommended')">
                        <v-icon class="icon" >mdi-circle-half-full</v-icon>
                    </button>

                    <button type="button" title="Show empty fields" class="btn btn-xs btn-outline-secondary" :class="{ active: show_fields_empty }" @click="toggleFields('empty')">
                        <v-icon class="icon" >mdi-circle-outline</v-icon>Empty
                    </button>

                    <button type="button" title="Expand/Collapse" class="btn btn-xs btn-outline-secondary" :class="{ active: show_fields_nonempty }" @click="toggleFields('nonempty')">
                        <v-icon class="icon" >mdi-checkbox-blank-circle</v-icon>Filled
                    </button>

            </div>
            <!-- end-icons -->



            <div class="mb-5">
                <v-treeview color="warning" v-model="tree" :active.sync="tree_active_items" @update:open="treeOnUpdate" :open.sync="initiallyOpen" :items="items" activatable dense item-key="key" item-text="title" expand-icon="mdi-chevron-down" indeterminate-icon="mdi-bookmark-minus" on-icon="mdi-bookmark" off-icon="mdi-bookmark-outline" item-children="items">

                    <template #label="{ item }">
                        <span @click="treeClick(item)" :title="item.title" class="tree-item-label">
                            <span v-if="item.type=='resource'">{{item.title | truncate(23, '...') }}</span>
                            <span v-else>
                                <span v-if="item.is_required"><strong>{{item.title}}</strong></span>
                                <span v-else>{{item.title}}</span>
                            </span>
                        </span>
                    </template>

                    <template v-slot:prepend="{ item, open }">
                        <v-icon v-if="item.type=='section_container'">
                            {{ open ? 'mdi-dresser' : 'mdi-dresser' }}
                        </v-icon>
                        <v-icon v-else-if="item.type=='section'">
                            {{ open ? 'mdi-folder-open' : 'mdi-folder' }}
                        </v-icon>
                        <v-icon v-else-if="item.type=='date' || item.display_type=='date'">
                            mdi-book-clock-outline
                        </v-icon>
                        <v-icon v-else-if="item.type=='nested_array'">
                            mdi-file-tree
                        </v-icon>
                        <v-icon v-else-if="item.type=='array'">
                            mdi-table
                        </v-icon>
                        <v-icon v-else-if="item.type=='simple_array'">
                            mdi-table-column
                        </v-icon>
                        <v-icon v-else-if="item.display_type=='dropdown' || item.display_type=='dropdown-custom'">
                            mdi-file-document
                        </v-icon>  
                        
                        <v-icon v-else-if="item.file">
                            {{ files[item.file] }}
                        </v-icon>                        
                        <v-icon v-else-if="item.items">
                            {{ open ? 'mdi-folder-open' : 'mdi-folder' }}
                        </v-icon>
                        <v-icon v-else>
                            {{ files['file'] }}
                        </v-icon>
                    </template>
                </v-treeview>
            </div>
        </div>
        <!--end left-->
    </pane>
    <pane size="80" class="pane-main-content">
        <!-- right -->


        <div class="content-wrapper" style="margin-left:0px;">

            <section class="content-x">

                <div class="container-fluid-">

                    <v-login v-model="login_dialog"></v-login>

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
                */ ?>

                    <validation-observer ref="form" v-slot="{ invalid }">
                        <div class="container-app pt-5 mr-2" >
                            <keep-alive include="datafiles">
                                <router-view :key="$route.fullPath" />
                            </keep-alive>
                        </div>

                    </validation-observer>
                </div>

                <?php // store_state:<pre>{{$store.state}}</pre> 
                ?>
                <?php // <pre>{{form_template}}</pre> 
                ?>

            </section>
        </div>
        <!--end right-->
    </pane>
</splitpanes>
</v-app>