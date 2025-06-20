<v-app style="position:relative;height: 100vh">

    <!--header-->
    <vue-global-site-header></vue-global-site-header>
    <!--end-header-->

    <?php /* <div class="row no-gutters">
        <div class="col-md-3 col-xl-2 col-xs-4 "> */?>
    <splitpanes class="default-theme splitpanes splitpanes--vertical editor-split-panes" style="min-height: 100px"> 
        <pane min-size="15" max-size="35" size="20" class="editor-sidebar">
            <!--left -->

            <div class="container-fluid-x  pt-2 pb-3 editor-sidebar editor-sidebar-container" >


            <div class="editor-sidebar-header pa-5 pt-0">
            <div class="editor-sidebar-header-icons d-flex justify-center align-center mb-5" >
                
                <div class="text-center pa-2" >
                    <v-btn text outlined title="Show mandatory fields" :class="{ active: show_fields_mandatory }" @click="toggleFields('mandatory')">
                        <v-icon class="icon">mdi-check-circle</v-icon>
                    </v-btn>
                    <div class="text-capitalize"><small>Required</small></div>
                </div>

                <div class="text-center pa-2" >
                    <v-btn text outlined title="Show recommended fields" :class="{ active: show_fields_recommended }" @click="toggleFields('recommended')">
                        <v-icon class="icon">mdi-circle-half-full</v-icon>
                    </v-btn>
                    <div class="text-capitalize"><small>Recommended</small></div>
                </div>

                <div class="text-center pa-2" >
                    <v-btn text outlined title="Show empty fields" :class="{ active: show_fields_empty }" @click="toggleFields('empty')">
                        <v-icon class="icon">mdi-circle-outline</v-icon>
                    </v-btn>
                    <div class="text-capitalize"><small>Empty</small></div>
                </div>

            </div>

            <div class="mt-2" >
                <v-text-field outlined clearable dense label="" v-model:value="tree_search" placeholder="Search..." prepend-inner-icon="mdi-magnify" ></v-text-field>
            </div>
            </div>

                <div class="mb-5 ml-2 pr-3 side-navigation" >

                <div class="d-flex justify-center align-center ml-3 mr-3">
                <v-progress-linear
                    v-if="ProjectIsLoading"
                    indeterminate
                    color="primary"
                    height="4"                    
                    ></v-progress-linear>
                </div>

                    <v-treeview                         
                        color="warning" 
                        v-model="tree" 
                        :active.sync="tree_active_items" 
                        @update:open="treeOnUpdate" 
                        :open.sync="initiallyOpen" 
                        :items="Items" 
                        activatable dense item-key="key" 
                        item-text="title" 
                        expand-icon="mdi-chevron-down" 
                        indeterminate-icon="mdi-bookmark-minus" 
                        on-icon="mdi-bookmark" 
                        off-icon="mdi-bookmark-outline" 
                        item-children="items">

                        <template #label="{ item }">
                            <span @click="treeClick(item)" :title="item.title" class="tree-item-label">
                                <span v-if="item.type=='resource'">{{item.title}}</span>
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
                        <project-export-json-component v-model="export_json_dialog"></project-export-json-component>

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
<template-apply-defaults-component v-model="apply_defaults_dialog" :key="apply_defaults_dialog_key"></template-apply-defaults-component>
</v-app>