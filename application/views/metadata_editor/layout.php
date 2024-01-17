<v-app style="position:relative;height: 100vh">

    


    <!--header-->

    <?php //echo $this->load->view('metadata_editor/header', array(), true); ?>

    <!--end-header-->

    <?php /* <div class="row no-gutters">
        <div class="col-md-3 col-xl-2 col-xs-4 "> */?>
    <splitpanes class="default-theme splitpanes splitpanes--vertical editor-split-panes" style="min-height: 100px"> 
        <pane min-size="15" max-size="35" size="20" class="editor-sidebar">
            <!--left -->

            <div class="container-fluid-x  pt-2 pb-3 editor-sidebar editor-sidebar-container" >

                <div class="p-1 mb-3 pl-2 sticky-top" style="border-bottom:1px solid #343a40; color:#343a40">
                    <a href="<?php echo site_url('projects');?>" class="navbar-brand">
                        <i class="fas fa-compass" ></i> 
                        <span class="brand-text font-weight-light color-white">Metadata Editor</span>
                    </a>
                </div>

                <!-- icons -->
                <div class="pb-2 mb-3 sidebar-menu-bar d-flex justify-content-center" style="border-bottom:1px solid #343a40; color:#343a40">

                

                    <button type="button" title="Expand/Collapse" class="btn btn-xs btn-link" @click="toggleTree">
                        <i class="icon fas fa-compress-arrows-alt"></i>
                    </button>

                    <button type="button" title="Show mandatory fields" class="btn btn-xs btn-link" :class="{ active: show_fields_mandatory }" @click="toggleFields('mandatory')">
                        <v-icon class="icon">mdi-check-circle</v-icon>
                    </button>

                    <button type="button" title="Show recommended fields" class="btn btn-xs btn-link" :class="{ active: show_fields_recommended }" @click="toggleFields('recommended')">
                        <v-icon class="icon">mdi-circle-half-full</v-icon>
                    </button>

                    <button type="button" title="Show empty fields" class="btn btn-xs btn-link" :class="{ active: show_fields_empty }" @click="toggleFields('empty')">
                        <v-icon class="icon">mdi-circle-outline</v-icon>
                    </button>

                    <button type="button" title="Expand/Collapse" class="btn btn-xs btn-link" :class="{ active: show_fields_nonempty }" @click="toggleFields('nonempty')">
                        <v-icon class="icon">mdi-checkbox-blank-circle</v-icon>
                    </button>

                </div>
                <!-- end-icons -->

                <?php /*
                <div>
                    <!--search box -->
                <v-text-field
                    min-height="6px"
                    filled
                    rounded dense
                    label=""
                    append-icon="mdi-magnify"
                 ></v-text-field>
                 <!--end search box -->
                </div>
                */ ?>



                <div class="mb-5" >
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
        <?php /* 
        </div>*/ ?>
        <?php /* <div class="col col-md-9 col-xl-10" style="height:100vh;overflow-y:scroll;">*/?>
        <pane size="80" class="pane-main-content">
            <!-- right -->

            
            <?php echo $this->load->view('metadata_editor/header', array(), true); ?>

            <div class="content-wrapper" style="margin-left:0px;">

                <section class="content-main-container mt-3">

                    <div class="container-fluid-x">

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
                            <div class="container-app">
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
    <?php /*</div>
    </div>*/?>

<v-toast></v-toast>
</v-app>