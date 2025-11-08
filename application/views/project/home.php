<!DOCTYPE html>
<html>

<head>
  <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
  <link href="<?php echo base_url();?>vue-app/assets/mdi.min.css" rel="stylesheet">
  <link href="<?php echo base_url();?>vue-app/assets/vuetify.min.css" rel="stylesheet">
  <link href="<?php echo base_url();?>vue-app/assets/bootstrap.min.css" rel="stylesheet" >

  <script src="<?php echo base_url();?>vue-app/assets/jquery.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/bootstrap.bundle.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/moment-with-locales.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/vue-i18n.min.js"></script>

  <link href="<?php echo base_url();?>vue-app/assets/styles.css" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">


<?php
  $user=$this->session->userdata('username');

  $user_info=[
    'username'=> $user,
    'is_logged_in'=> !empty($user),
    'is_admin'=> $this->ion_auth->is_admin(),
  ];
  
?>


</head>

<style>  
  table th {
    white-space: nowrap;
  }

  /*v-tree spacing */
  .v-treeview-node__root {
    height: auto;
    min-height: 30px;
  }

  .v-treeview-node.v-treeview-node--leaf {
    margin-left: 14px;
  }  
</style>

<body class="layout-top-nav">

  <script>
    var CI = {
      'site_url': '<?php echo site_url(); ?>',
      'base_url': '<?php echo base_url(); ?>',
      'user_info': <?php echo json_encode($user_info); ?>
    };
  </script>

  <div id="app" data-app >    
    <v-app >

    <alert-dialog></alert-dialog>
    <confirm-dialog></confirm-dialog>

    <div class="wrapper">

      <vue-global-site-header></vue-global-site-header>


      <div class="content-wrapperx" v-cloak>
        <section class="content">

          <div class="container-fluid">

            <div class="row">

              <!--sidebar -->
              <div class="sidebar col-md-3 col-sm-4">

                <div class="mr-4 mt-5">
                  <v-expansion-panels v-model="facet_panel" multiple class="">

                    <v-expansion-panel v-for="(facet_values,facet_key) in facets" :key="facet_key">
                      <v-expansion-panel-header class="capitalize">
                        <div v-if="facet_key=='collection'" style="display: flex; justify-content: space-between; align-items: center; width: 100%; padding-right: 12px;">
                          <span>{{$t(facet_key)}}</span>
                          <v-switch
                              v-model="exclude_collections_filter"
                              :label="exclude_collections_filter ? 'Exclude' : 'Include'"
                              dense
                              small
                              hide-details
                              color="warning"
                              class="mt-0 pt-0 exclude-collections-filter-switch"
                              style="flex-grow: 0;font-weight: normal;"
                              @click.stop
                          ></v-switch>
                        </div>
                        <span v-else>{{$t(facet_key)}}</span>
                      </v-expansion-panel-header>
                      <v-expansion-panel-content>
                        <div v-if="facet_key=='collection'">
                          
                          <v-text-field
                              v-model="collection_search"
                              placeholder="Search..."
                              dense
                              outlined
                              clearable
                              hide-details
                              class="mt-0 mb-3 collection-search-small custom-xs"
                              prepend-inner-icon="mdi-magnify"
                          ></v-text-field>
                          
                          <v-treeview
                              :items="facet_values"
                              :search="collection_search"
                              item-children="items"
                              activatable
                              item-key="id"
                              item-text="title"                            
                              v-model="search_filters[facet_key]"                              
                              selectable
                              selection-type="independent"
                              class="treeview-collection-filter tree-with-lines"
                              >
                              <template v-slot:label="{ item }">
                                <div style="display: flex; justify-content: space-between; width: 100%; align-items: center; gap: 8px;">
                                  <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0;">{{ item.title }}</span>
                                  <span v-if="item.projects" class="text-muted" style="font-size: 0.85em; padding-left: 5px; padding-right: 5px; border-radius: 3px; flex-shrink: 0;">{{ item.projects }}</span>
                                </div>
                              </template>
                          </v-treeview>

                        </div>
                        <div v-else>
                          <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 4px;" v-for="facet in facet_values">
                            <v-checkbox
                                v-model="search_filters[facet_key]"
                                :value="facet.id"
                                hide-details
                                dense
                                class="mt-0 pt-0 facet-checkbox"
                                style="flex: 1; min-width: 0;"
                            >
                              <template v-slot:label>
                                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{$t(facet.title)}}</span>
                              </template>
                            </v-checkbox>
                            <span v-if="facet.count !== undefined" class="text-muted" style="font-size: 0.7em; padding-left: 5px; padding-right: 5px; border-radius: 3px; flex-shrink: 0;">{{ facet.count }}</span>
                          </div>
                          
                          <!-- Add user button for users_filter facet -->
                          <div v-if="facet_key=='users_filter'" class="mt-2">
                            <v-btn
                                @click="openUserFilterDialog"
                                small
                                outlined
                                color="primary"
                                block
                            >
                              <v-icon small left>mdi-account-plus</v-icon>
                              {{$t('add_user')}}
                            </v-btn>
                          </div>
                        </div>
                      </v-expansion-panel-content>
                    </v-expansion-panel>
                  </v-expansion-panels>
                </div>

              </div>
              <!-- end sidebar -->

              <!-- User filter dialog component -->
              <vue-user-filter v-model="dialog_user_filter" @apply="onApplyUserFilter"></vue-user-filter>

              <div class="projects col-md-9 col-sm-8">
                <div class="mt-5 mb-5">                  

                      <div class="mb-5">
                        <vue-navigation-tabs></vue-navigation-tabs>
                      </div>

                  <div class="d-flex">
                    <div class="flex-grow-1 flex-shrink-0 mr-auto">
                    <h3 class="mt-3">{{$t("my_projects")}}</h3>                      
                    </div>
                    <div class="">
                      <v-btn small color="primary"  @click="dialog_create_project=true">{{$t("create_project")}}</v-btn>        
                      <v-btn small color="primary"  @click="dialog_import_project=true">{{$t("import")}}</v-btn>
                    </div>
                  </div>

                  
                </div>

                <div>

                  <div class="d-flex search-box">
                    
                    <div                        
                        style="min-width: 100px; max-width: 100%;"
                        class="flex-grow-1 flex-shrink-0"
                      >
                        <div class="">
                              <v-text-field 
                                background-color="white"
                                v-model="search_keywords" 
                                :prepend-inner-icon="is_searching ? 'mdi-loading mdi-spin' : 'mdi-magnify'"
                                :label="$t('search')"
                                single-line dense outlined clearable 
                                :loading="is_searching"
                                @click:append="search" 
                                @keyup.enter="search" 
                                @input="onSearchInput"
                                @click:clear="clearSearch">
                              </v-text-field>                            
                        </div>
                      </div>
                      <div class="flex-grow-0 flex-shrink-0">
                        <div class="ml-3" style="width:135px;">
                          <v-select
                            :items="sort_by_options"
                            v-model="sort_by"
                            item-text="text"
                            item-value="value"
                            label=""
                            background-color="white"
                            dense
                            outlined
                          ></v-select>
                        </div>
                  </div>                    
                  </div>

                  <div v-if="SearchFiltersQuerystring" class="mt-3 mb-5">
                    <!-- Show exclude mode indicator if enabled -->
                    <v-chip v-if="exclude_collections_filter && search_filters.collection && search_filters.collection.length > 0" 
                            @click:close="exclude_collections_filter = false"
                            small 
                            color="warning" 
                            text-color="white"
                            close
                            class="mr-1 mb-1">
                      Exclude Collections
                    </v-chip>
                    
                    <template v-for="(filter_values, filter_type) in search_filters">
                      <template v-for="(filter_value,idx) in filter_values">                        
                        <v-chip @click:close="removeFilter(filter_type,idx)" small :color="getFilterChipColor(filter_type)" close class="mr-1 mb-1">
                        {{getFacetTitleById(filter_type,filter_value)}}                                     
                        </v-chip>
                      </template>
                    </template>
                  </div>

                  <div class="mt-5 p-3 border  text-danger" v-if="errors && errors.length>0"> 
                    <div><strong>{{$t('error')}}:</strong> <a href="<?php echo site_url('editor');?>">{{$t('refresh_page')}}</a></div>
                    <div v-for="error in errors">{{error}}</div>
                  </div>

                  

                  <template>

                  <div class="bg-white shadow rounded p-3 pt-1 mt-2" elevation="10">

                      <div v-if="is_loading" class="mt-5 mb-3 p-3 text-center">
                        <v-progress-circular indeterminate color="primary" class="mr-2"></v-progress-circular>
                        <span>{{$t('loading_projects') || 'Loading projects...'}}</span>
                      </div>
                      <div class="mt-5 mb-3 p-3 border text-center text-danger" v-if="errors.length === 0 && !is_loading && (!Projects || projects.found<1)"> {{$t('no_projects_found')}}</div>

                      <div v-if="!Projects || projects.found>0" class="row mb-2 mt-3">
                        <div class="col-md-5">
                          <div class="p-2" v-if="Projects">
                            <strong>{{$t("showing_range_of_n", { row: parseInt(projects.offset) +1, page_size: parseInt(projects.offset + projects.projects.length), total:projects.total })}}</strong>
                          </div>
                        </div>

                        <div class="col-md-7">
                          <template>
                            <div class="float-right" v-if="PaginationTotalPages">
                              <v-pagination v-model="pagination_page" :length="PaginationTotalPages" :total-visible="6" @input="PaginatePage"></v-pagination>
                            </div>
                          </template>
                        </div>

                      </div>


                    <table class="table table-hover border-bottom table-projects" v-if="projects && projects.found>0">
                      <thead style="font-size:small;">
                        <tr>
                          <th style="width:30px;">
                            <div v-if="ProjectsCount>0">
                              <input type="checkbox" v-model="select_all_projects" @change="toggleProjectSelection" />
                            </div>
                          </th>
                          <th style="width:80px;">
                            <v-menu offset-y :disabled="selected_projects.length==0">
                              <template v-slot:activator="{ on, attrs }">
                                <v-btn icon v-bind="attrs" v-on="on">
                                  <v-icon>mdi-dots-vertical</v-icon>
                                </v-btn>
                              </template>
                              <v-list>
                                <v-list-item @click="addProjectsToCollection">
                                  <v-icon left small>mdi-folder-plus</v-icon>
                                  {{$t('add_to_collection')}}</v-list-item>
                                <v-list-item v-if="selected_projects.length === 2" @click="compareSelectedProjects">
                                  <v-icon left small>mdi-compare</v-icon>
                                  {{$t('compare_projects')}}
                                </v-list-item>                                
                              </v-list>
                            </v-menu>
                          </th>
                          <th style="width:17px;"></th>
                          <th class="project-title-col">{{$t('title')}}</th>
                          <th>{{$t('owner')}}</th>
                          <th>{{$t('last_modified_by')}}</th>
                          <th style="width:120px;">{{$t('modified')}}</th>                          
                          <th>{{$t('actions')}}</th>
                        </tr>
                      </thead>
                      <tbody>
                        <template v-for="project in Projects" @click.prevent="EditProject(project.id)">
                        <tr>
                          <td><input type="checkbox" v-model="selected_projects" :value="project.id" @click="checkboxOnClick" /></td>
                          <td><template v-if="project.thumbnail">
                                <img style="width:60px;height:60px;" :src="'<?php echo site_url('api/editor/thumbnail'); ?>/' + project.id" alt="" class=" border img-fluid img-thumbnail rounded shadow-sm project-card-thumbnail">
                              </template>
                              <template v-else>
                                <img style="width:60px;height:60px;" src="<?php echo base_url(); ?>files/icon-blank.png" alt="" class=" border img-fluid img-thumbnail rounded shadow-sm project-card-thumbnail">
                              </template>
                            </td>
                          <td style="vertical-align:top"><v-icon :title="project.type">{{project_types_icons[project.type]}}</v-icon></td>
                          <td>
                            <div class="project-title">
                              <a :href="'editor/edit/' + project.id" :title="project.title" class="d-flex xtext-title" @click.prevent="EditProject(project.id)">                                
                                <span v-if="project.title.length>1">{{project.title}}</span>
                                <span v-else>{{$t('untitled')}}</span>
                              </a>
                            </div>
                            <div class="text-secondary text-small">
                                <span v-if="projectSubInfo(project)">{{projectSubInfo(project)}} </span>
                               <!-- {{project.idno}} | <span :title="project.template_uid">{{project.template_uid}} </span>-->
                            </div>
                            
                            <div class="text-small mt-2" v-if="project.collections && project.collections.length>0">
                              <template v-for="(collection,idx) in project.collections" v-if="idx<3">
                                <v-chip outlined small color="primary"  @click.stop="manageProjectCollections(project.id)" class="mr-1" >
                                  {{collection.title}}                                      
                                </v-chip>
                              </template>
                              <template v-if="project.collections.length>3">
                                <v-chip outlined small color="primary" class="mr-1" @click.stop="manageProjectCollections(project.id)">
                                  +{{project.collections.length-3}} {{$t('more')}}
                                </v-chip>
                              </template>
                            </div>

                            <div class="mt-2" v-if="project.versions && project.versions.length>0">
                              <v-btn color="primary" outlined x-small dark @click.stop="toggleRevisions(project.id)" :title="project.versions.length">
                                <v-icon x-small left>mdi-content-copy</v-icon> {{$t('versions')}} <span class="ml-1">{{project.versions.length}}</span>
                              </v-btn>
                            </div>

                          </td>
                          <td class="capitalize text-small">{{project.username_cr}}</td>
                          <td class="capitalize text-small">{{project.username}}</td>
                          <td class="text-small">{{momentDate(project.changed)}}</td>                          
                          <td class="text-right">
                            
                          <v-icon @click.stop.prevent="showProjectMenu($event, project.id, true)">mdi-dots-vertical</v-icon> 
                          </td>
                        </tr>
                        <tr v-if="project.versions && project.versions.length>0 && project.versions_show==1" style="background:white;">
                          <td colspan="3"></td>
                          <td colspan="5">                          
                            <vue-list-revisions :revisions="project.versions" v-on:edit-project="EditProject($event)" v-on:delete-project="DeleteProjectRevision($event)" ></vue-list-revisions>
                          </td>
                        </tr>
                        </template>

                      </tbody>
                    </table>


                    <template>
                      <div class="mb-5 mt-2" v-if="PaginationTotalPages">
                        <v-pagination v-model="pagination_page" :length="PaginationTotalPages" :total-visible="6" @input="PaginatePage"></v-pagination>
                      </div>
                    </template>
                    
                  </template>
                  </div>
                  
                </div>

              </div>

            </div>
        </section>
      </div>

    </div>

    <vue-transfer-ownership v-model="dialog_transfer_ownership" v-bind="dialog_transfer_ownership_options" v-on:transfer-ownership="search">
    </vue-transfer-ownership>
    
    <vue-project-access-dialog v-model="dialog_access_project" v-bind="dialog_access_options">
    </vue-project-access-dialog>

    <vue-project-share v-model="dialog_share_project" v-bind="dialog_share_options">
    </vue-project-share>

    <vue-collection-share v-model="dialog_share_collection" v-bind="dialog_share_collection_options" v-on:share-with-collection="OnAddProjectsToCollection">
    </vue-collection-share>

    <vue-collection-remove-dialog v-model="dialog_manage_collections" v-bind="dialog_manage_collections_options" v-on:collection-removed="search">
    </vue-collection-remove-dialog>

    <vue-create-revision-dialog v-model="dialog_project_revision" v-bind="dialog_project_revision_options" v-on:revision-created="search" :key="dialog_project_revision_key">
    </vue-create-revision-dialog>


    

    <template class="create-new-project">
      <div class="text-center">
        <v-dialog v-model="dialog_create_project" width="500">

          <v-card>
            <v-card-title class="text-h5 grey lighten-2">
              {{$t("create_project")}}
            </v-card-title>

            <v-card-text>
              <div>
                <a class="dropdown-item" href="#" @click="createProject('survey')"><v-icon>{{project_types_icons['survey']}}</v-icon> {{$t("microdata")}}</a>
                <a class="dropdown-item" href="#" @click="createProject('timeseries')"><v-icon>{{project_types_icons['timeseries']}}</v-icon> {{$t("timeseries")}}</a>
                <a class="dropdown-item" href="#" @click="createProject('timeseries-db')"><v-icon>{{project_types_icons['timeseries-db']}}</v-icon> {{$t("timeseries-db")}}</a>
                <a class="dropdown-item" href="#" @click="createProject('document')"><v-icon>{{project_types_icons['document']}}</v-icon> {{$t("document")}}</a>
                <a class="dropdown-item" href="#" @click="createProject('table')"><v-icon>{{project_types_icons['table']}}</v-icon> {{$t("table")}}</a>
                <a class="dropdown-item" href="#" @click="createProject('image')"><v-icon>{{project_types_icons['image']}}</v-icon> {{$t("image")}}</a>
                <a class="dropdown-item" href="#" @click="createProject('script')"><v-icon>{{project_types_icons['script']}}</v-icon> {{$t("script")}}</a>
                <a class="dropdown-item" href="#" @click="createProject('video')"><v-icon>{{project_types_icons['video']}}</v-icon> {{$t("video")}}</a>
                <a class="dropdown-item" href="#" @click="createProject('geospatial')"><v-icon>{{project_types_icons['geospatial']}}</v-icon> {{$t("geospatial")}}</a>
              </div>
            </v-card-text>

            <v-divider></v-divider>

            <v-card-actions>
              <v-spacer></v-spacer>
              <v-btn color="primary" text @click="dialog_create_project = false">
                {{$t("close")}}
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>
      </div>
    </template>


    <template class="import-project">
      <div class="text-center">
        <v-dialog v-model="dialog_import_project" width="500" :key="dialog_import_project_key">

          <v-card>
            <v-card-title class="text-h5 grey lighten-2">
              {{$t("import_project")}}
            </v-card-title>

            <v-card-text style="min-height:200px;">
              <div class="mb-2">
                <div  class="pb-1">{{$t("select_project_type")}}</div>
                <v-select
                    :items="ProjectTypes"
                    label=""
                    item-text="text"
                    item-value="value"
                    label=""
                    persistent-hint
                    return-object                    
                    dense
                    outlined
                    v-model="import_project_type"
                ></v-select>
                
              </div>
              <div class="mb-2">
                <div class="pb-1">
                {{$t("upload_file")}}
                  <?php /* 
                  <span><button type="button" class="btn btn-sm btn-link" @click="upload_type='file'">Upload file</button></span>
                  <span><button type="button" class="btn btn-sm btn-link" @click="upload_type='url'">URL</button></span>
                  */ ?>
                </div>
                <v-file-input v-if="upload_type=='file'"
                  accept=".json,.xml,.zip"
                  label=""                  
                  truncate-length="50"                  
                  dense
                  outlined
                  v-model="import_file"
                  prepend-icon=""
                  prepend-inner-icon="mdi-file-upload"
                ></v-file-input>

                <v-text-field v-if="upload_type=='url'"
                  label=""
                  dense
                  outlined
                  v-model="import_url"
                  prepend-icon=""
                  prepend-inner-icon="mdi-link">
                </v-text-field>
                
              </div>


              <div v-if="import_project_loading">
                <div class="mb-2 mt-3 pl-4 pr-4">
                  <v-app>
                  <v-progress-linear
                    indeterminate
                    color="primary"
                  ></v-progress-linear>
                  </v-app>
                </div>
              </div>
              <div v-if="import_file_errors">
                <div class="mb-2 text-color-danger text-danger">
                  <div class="pb-1">{{$t("failed")}}</div>
                  <div>{{import_file_errors.response.data}}</div>
                </div>
              </div>

            </v-card-text>

            <v-divider></v-divider>

            <v-card-actions>
              <v-spacer></v-spacer>
              <v-btn color="secondary" text @click="dialog_import_project = false">
              {{$t("close")}}
              </v-btn>
              <v-btn color="primary" text @click="importProject" :disabled="!this.import_file || this.import_project_loading">
              {{$t("import")}}
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>
      </div>
    </template>


    <template>
      <v-menu
        v-model="show_project_menu"
        :position-x="menu_x-150"
        :position-y="menu_y"
        absolute
        offset-y
      >

        <v-list>          
          <v-list-item>
            <v-list-item-title @click="ShareProject(menu_active_project_id)"><v-btn text>{{$t('share')}}</v-btn></v-list-item-title>
          </v-list-item>          
          <v-list-item>
            <v-list-item-title @click="addProjectToCollection(menu_active_project_id)"><v-btn text>{{$t('add_to_collection')}}</v-btn></v-list-item-title>
          </v-list-item>
          <v-list-item>
            <v-list-item-title @click="viewAccessPermissions(menu_active_project_id)"><v-btn text>{{$t('view_access')}}</v-btn></v-list-item-title>
          </v-list-item>

          <v-list-item>
            <v-list-item-title @click="createProjectRevision(menu_active_project_id)"><v-btn text>{{$t('Create version')}}</v-btn></v-list-item-title>
          </v-list-item>

          <v-list-item>
            <v-list-item-title @click="transferOwnership(menu_active_project_id)" ><v-btn text>{{$t('transfer_ownership')}}</v-btn></v-list-item-title>
          </v-list-item>
          
          <v-list-item>
            <v-list-item-title @click="DeleteProject(menu_active_project_id)"><v-btn text>{{$t('delete')}}</v-btn></v-list-item-title>
          </v-list-item>

          <v-divider></v-divider>
          
          <v-list-item>
            <v-list-item-title @click="ExportProjectJSON(menu_active_project_id)"><v-btn text>{{$t('export_json')}}</v-btn></v-list-item-title>
          </v-list-item>

          <v-list-item>
            <v-list-item-title @click="ExportProjectPackage(menu_active_project_id)"><v-btn text>{{$t('export_package_zip')}}</v-btn></v-list-item-title>
          </v-list-item>

        </v-list>
      </v-menu>
    </template>


    </v-app>
  </div>

  <script src="<?php echo base_url();?>vue-app/assets/vue.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/vue-router.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/vuex.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/axios.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/vuetify.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/lodash.min.js"></script>
  <!--
  <script src="https://cdn.jsdelivr.net/npm/vue-deepset@0.6.3/vue-deepset.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/deepdash/browser/deepdash.standalone.min.js"></script>
  -->

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css" crossorigin="anonymous" />

  <style>
    .control-border-top .v-input__control {
      border-top: 1px solid #e0e0e0;
    }
  </style>

  <script>

    <?php
    echo $this->load->view("vue/vue-global-eventbus.js", null, true);
    echo $this->load->view("vue/vue-alert-dialog-component.js", null, true);
    echo $this->load->view("vue/vue-confirm-dialog-component.js", null, true);
    echo $this->load->view("project/vue-project-share-component.js", null, true);
    echo $this->load->view("project/vue-collection-remove-component.js", null, true);
    echo $this->load->view("project/vue-collection-share-component.js", null, true);
    echo $this->load->view("project/vue-project-access-component.js", null, true);
    echo $this->load->view("project/vue-transfer-ownership-component.js", null, true);
    echo $this->load->view("editor_common/navigation-tabs-component.js", null, true);
    echo $this->load->view("editor_common/global-site-header-component.js", null, true);
    echo $this->load->view("project/vue-create-revision-component.js", null, true);
    echo $this->load->view("project/vue-list-revisions-component.js", null, true);
    echo $this->load->view("project/vue-user-filter-component.js", null, true);

    ?>

    const translation_messages = {
      default: <?php echo json_encode($translations,JSON_HEX_APOS);?>
    }

    const i18n = new VueI18n({
      locale: 'default', // set locale
      messages: translation_messages, // set locale messages
    })

    // 1. Define route components.        
    const Home = {
      template: '<div>Home -todo </div>'
    }
    const ShareProject = {
      props: ['value'],
      template: '<div><vue-project-share /> </div>'
    }


    //routes
    const routes = [{
        path: '<?php echo site_url("editor");?>',
        component: Home,
        name: 'home'
      },
      {
        path: '/share',
        component: ShareProject,
        name: 'share'
      }
    ]

    const router = new VueRouter({
      routes, 
      mode: 'history'
    })

    const vuetify = new Vuetify({
      theme: {
        themes: {
          light: {
            primary: '#526bc7',
            "primary-dark": '#0c1a4d',
            secondary: '#b0bec5',
            accent: '#8c9eff',
            error: '#b71c1c',
          },
        },
      },
    });


    const momentMixin = {
      methods: {
          momentDate(date) {
            let utc_date = moment(date, "YYYY-MM-DD HH:mm:ss").toDate();
            return moment.utc(utc_date).format("YYYY-MM-DD");
          },
          momentDateLong(date) {
            let utc_date = moment(date, "YYYY-MM-DD HH:mm:ss").toDate();
            return moment.utc(utc_date).format("YYYY-MM-DD HH:mm:ss");
          },
          momentShortDate(date) {
            let utc_date = moment(date, "YYYY-MM-DD HH:mm:ss").toDate();
            let year = moment.utc(utc_date).format("YYYY");
            let current_year = moment.utc().format("YYYY");

            if (year == current_year) {
              return moment.utc(utc_date).format("MMM DD");
            } else {
              return moment.utc(utc_date).format("MMM DD, YYYY");
            }
          },
          momentAgo(date) {
            let utc_date = moment(date, "YYYY-MM-DD HH:mm:ss").toDate();
            return moment.utc(date).fromNow();
          }
        }
      }

    Vue.mixin(momentMixin);



    vue_app = new Vue({
      el: '#app',
      i18n,
      vuetify: vuetify,
      router: router,
      data: {
        page_layout: 'list',
        projects: [],
        project_size_info:[],
        selected_projects: [],
        select_all_projects: false,
        is_loading: false,
        loading_status: null,
        form_errors: [],
        facets: [],
        facet_panel: [0,1,2,3,4,5],
        collection_search: '',
        exclude_collections_filter: false,
        dialog_user_filter: false,
        updating_route: false,
        pagination_page: 0,
        dialog_create_project: false,
        dialog_import_project: false,
        dialog_import_project_key: 0,
        import_file: null,
        import_url:null,
        import_project_type: null,
        upload_type:'file',
        import_project_loading: false,
        import_file_errors:null,
        dialog_share_project: false,
        dialog_share_options: [],
        dialog_access_project:false,
        dialog_access_options:[],
        dialog_share_collection: false,
        dialog_share_collection_options: [],
        dialog_transfer_ownership: false,
        dialog_transfer_ownership_options: [],
        dialog_manage_collections: false,
        dialog_manage_collections_options: {},
        users_list: null,
        errors:[],
        projects_shared: [],
        search_keywords: '',
        search_filters: {},
        collapsible_list: [], //show/hide project details
        search_debounce_timer: null,
        is_searching: false,
        show_project_menu: false,        
        menu_x: 0,
        menu_y: 0,
        menu_active_project_id: null,
        data_types: {
          "survey": "Microdata",
          "timeseries": "Timeseries",
          "timeseries-db": "Timeseries (Database)",
          "script": "Script",
          "geospatial": "Geospatial",
          "document": "Document",
          "table": "Table",
          "image": "Image",
          "video": "Video",
        },
        project_types_icons: {
          "document": "mdi-file-document",
          "survey": "mdi-database", 
          "geospatial": "mdi-earth",
          "table": "mdi-table",
          "timeseries": "mdi-chart-line",
          "timeseries-db": "mdi-resistor-nodes",
          "image": "mdi-file-image",
          "video": "mdi-video",
          "script": "mdi-file-code",
        },        
        sort_by_options:[],            
        sort_by:"updated_desc",
        collections_flat_list:[],
        dialog_project_revision: false,
        dialog_project_revision_options: {},
        dialog_project_revision_key: 0,
      },
      created: async function() {
        //reload projects on window focus
        document.addEventListener("visibilitychange", function() {
              if (!document.hidden){
                vue_app.onWindowFocus();
              }               
        });
      },

      mounted: function() {        
        this.is_loading = true;
        this.loadProjects();
        this.loadFacets();
        this.initDataTypes();
        this.initSortOptions();
        //this.ReadFilterQS();        
      },
      computed: {
        Title() {
          return 'title';
        },
        DataTypes() {
          let sorted = {};
          let sorted_keys = Object.keys(this.data_types).sort();
          for (k in sorted_keys) {
            sorted[sorted_keys[k]] = this.data_types[sorted_keys[k]];
          }
          return sorted;
        },
        ProjectTypes() {
          let types = [];
          for (k in this.data_types) {
            types.push(
              {
                value: k,
                text: this.data_types[k]                
              }
            );
          }
          return types;
        },        
        
        Projects() {
          return this.projects.projects;
        },
        ProjectsCount() {
          if (this.projects && this.projects.total) {
            return this.projects.total;
          }
          return 0;
        },
        PaginationTotalPages() {
          return Math.ceil(this.projects.total / this.projects.limit);
        },
        PaginationOffset() {
          let pageSize = this.projects.limit;
          let currentPage = this.pagination_page - 1;
          let result= pageSize * currentPage;
          if (!result){
            return 0;
          }
          return result;
        },
        PaginationCurrentPage() {
          let offset = this.projects.offset;
          let limit = this.projects.limit;
          return Math.ceil(offset / limit) + 1;
        },
        SearchFiltersQuerystring() {
          return jQuery.param(this.search_filters);
        }
      },

      watch: {
        SearchFiltersQuerystring: function(new_, old_) {
            this.search();
        },
        sort_by: function(new_, old_) {
            this.search();
        },
        exclude_collections_filter: function(newVal, oldVal) {
            this.search();
        },
        $route: {
          handler: function(newRouteValue, oldRouteValue){
            // Only read filters if route actually changed (not programmatic update)
            if (!oldRouteValue || newRouteValue.fullPath !== oldRouteValue.fullPath) {
              // Don't reload if we just updated the route ourselves
              if (!this.updating_route) {
                this.ReadFilterQS();
              }
            }
          },
          deep: true
        }
      },
      methods: {
        toggleRevisions: function(project_id) {
          let project = this.Projects.find(x => x.id == project_id);
          if (project) {
            Vue.set(project, 'versions_show', !project.versions_show);
          }
        },
        createProjectRevision: function(project_id) {

          let project = this.Projects.find(x => x.id == project_id);

          this.dialog_project_revision_options = {
            'project_id': project_id,
            'project': project || []
          };
          this.dialog_project_revision_key++;
          this.dialog_project_revision = true;
        },


        pageLink: function(page) {
          window.location.href = CI.site_url + '/' + page;
        },
        compareSelectedProjects: async function() {
          if (this.selected_projects.length === 2) {
            // Find the selected projects in the existing Projects array
            const project1 = this.Projects.find(p => p.id == this.selected_projects[0]);
            const project2 = this.Projects.find(p => p.id == this.selected_projects[1]);
            
            if (!project1 || !project2) {
              await this.$alert(this.$t('projects_not_found'), { color: 'error' });
              return;
            }
            
            if (project1.type !== project2.type) {
              await this.$alert(this.$t('cannot_compare_different_types'), { color: 'warning' });
              return;
            }
            
            // If types match, proceed with comparison
            window.open(CI.site_url + '/editor/compare?project1=' + this.selected_projects[0] + '&project2=' + this.selected_projects[1], '_blank');
          }
        },
        showProjectMenu (e, projectId) {
          e.preventDefault()
          this.show_project_menu = false
          this.menu_x = e.clientX
          this.menu_y = e.clientY
          this.menu_active_project_id = projectId          
          this.$nextTick(() => {
            this.show_project_menu = true
          })
        },
        onProjectPanelClick: function (projectIndex)
        {
          if (!this.Projects[projectIndex].size){
            this.getProjectSize(this.Projects[projectIndex].id, projectIndex);
          }
        },

        checkboxOnClick: function(e) {
          e.cancelBubble = true;
        },
        initDataTypes: function()
        {
          this.data_types={
            "survey": this.$t("microdata"),
            "timeseries": this.$t("timeseries"),
            "timeseries-db": this.$t("timeseries-db"),
            "script": this.$t("script"),
            "geospatial": this.$t("geospatial"),
            "document": this.$t("document"),
            "table": this.$t("table"),
            "image": this.$t("image"),
            
          }
        },
        initSortOptions: function(){
          this.sort_by_options=[
            {value:"title_asc",text:this.$t("title_az")},
            {value:"title_desc",text:this.$t("title_za")},
            {value:"updated_asc",text:this.$t("oldest")},
            {value:"updated_desc",text:this.$t("recent")}
          ];
        },
        onFilterClick: function(facet_key, facet) {
        },
        CreateFilterQS: function(){
          let search_filters = {};
          for(i=0;i<Object.keys(this.search_filters).length;i++){
            let filter_name=Object.keys(this.search_filters)[i];
            search_filters[filter_name]=this.search_filters[filter_name].join(",");
          }

          //sort
          search_filters.sort_by=this.sort_by;

          //keyword search
          search_filters.keywords=this.search_keywords;

          //exclude collections filter
          if(this.exclude_collections_filter){
            search_filters.exclude_collections='true';
          }

          //users filter (now using users_filter facet)
          if(this.search_filters.users_filter && this.search_filters.users_filter.length > 0){
            search_filters.users=this.search_filters.users_filter.join(",");
          }

          this.updating_route = true;
          this.$router.replace({ path: '', query: search_filters}).catch(() => {});
          this.$nextTick(() => {
            this.updating_route = false;
          });
        },
        ReadFilterQS: function()
        {
          let urlParams = new URLSearchParams(window.location.search);

          //get from querystring
          let search_filters = {};
          for(i=0;i<Object.keys(this.search_filters).length;i++){
            let filter_name=Object.keys(this.search_filters)[i];
            let values=urlParams.get(filter_name);
            
            if (values && values.length>0){
              search_filters[filter_name]=values.split(",");
            }
          }

          //apply filters
          for(f=0;f<Object.keys(search_filters).length;f++){
            let filter_name=Object.keys(search_filters)[f];
            this.search_filters[filter_name]=search_filters[filter_name];
          }

          //keyword search
          this.search_keywords=urlParams.get('keywords');

          //exclude collections filter
          let exclude_param = urlParams.get('exclude_collections');
          this.exclude_collections_filter = (exclude_param === 'true');


          //set sort
          let sort_=urlParams.get('sort_by');
          if (!sort_==''){
            if (this.sort_by_options.find(x => x.value == sort_)){
              this.sort_by=sort_;
            }
          }
        },
        onWindowFocus: function() {
          this.search();
        },
        projectEditUrl: function(project_id) {
          return CI.site_url + '/editor/edit/' + project_id;
        },        
        search: function() {
          this.pagination_page = 1;
          this.CreateFilterQS();
          this.loadProjects();
        },
        clearSearch: function() {
          var self = this;
          setTimeout(function() {
            self.search()
          }, 1000)
        },
        onSearchInput: function() {
          // Show immediate feedback that search is being prepared
          this.is_searching = true;
          
          // Clear existing timer
          if (this.search_debounce_timer) {
            clearTimeout(this.search_debounce_timer);
          }
          
          // Set new timer for debounced search
          var self = this;
          this.search_debounce_timer = setTimeout(function() {
            self.search();
          }, 500); // 500ms delay
        },
        getFacetTitleById: function(facet_name, facet_id) {
          if (!this.facets[facet_name]) {
            return '';
          }

          //find facet by id
          let facet = this.facets[facet_name].find(x => x.id == facet_id);

          if (facet_name == 'collection') {
            facet=this.searchNestedCollectionsFacet(facet_id);
          }

          if (facet) {
            return facet.title;
          }

          return facet_id;
        },
        searchNestedCollectionsFacet: function(facet_id) {

          let searchCollections=function(collections){
            
            for (let i = 0; i < collections.length; i++) {
              let collection = collections[i];

              if (collection.id == facet_id) {
                return collection;
              }

              if (collection.items){
                let found=searchCollections(collection.items);
                if (found){
                  return found;
                }
              }
            }
                
          }

          let facet=searchCollections(this.facets.collection);
          return facet;         
        },
        loadFacets: function() {
          vm = this;
          
          // Build URL with current query parameters to get filter_users
          let urlParams = new URLSearchParams(window.location.search);
          let url = CI.site_url + '/api/editor/facets?' + urlParams.toString();
          
          return axios
            .get(url)
            .then(function(response) {
              vm.facets = response.data.facets;
              let facet_types = Object.keys(vm.facets);

              for (i = 0; i < facet_types.length; i++) {
                let facet_name = facet_types[i];
                Vue.set(vm.search_filters, facet_name, []);
              }
              
              vm.ReadFilterQS();
            })
            .catch(function(error) {
              console.log("error", error);
            });
        },        
        getProjectSize: function(projectId,projectIndex) {
          vm = this;
          let url = CI.site_url + '/api/files/size/' + projectId;
          return axios
            .get(url)
            .then(function(response) {
              Vue.set(vm.projects.projects[projectIndex], 'size', response.data.result);
              
            })
            .catch(function(error) {
              console.log("error", error);
            });
        },
        loadProjects: function() {
          vm = this;

          let urlParams = new URLSearchParams(window.location.search);          
          let keywords= urlParams.get('keywords');
          urlParams.delete('keywords');
          
          let url = CI.site_url + '/api/editor/?offset=' + this.PaginationOffset +
            '&' + urlParams.toString();

          if (keywords && keywords.length>0){
            url += '&keywords=' + keywords;
          }

          // Ensure exclude_collections parameter is passed
          if (this.exclude_collections_filter) {
            if (url.indexOf('exclude_collections') === -1) {
              url += '&exclude_collections=true';
            }
          }

          console.log('Loading projects with URL:', url);

          // Track search/filter event
          let searchData = null;
          if (typeof Analytics !== 'undefined') {
            const searchParams = new URLSearchParams(window.location.search);
            const collectedParams = {};
            
            // Only include non-empty parameters
            if (searchParams.get('type')) collectedParams.types = searchParams.get('type');
            if (searchParams.get('keywords')) collectedParams.keywords = searchParams.get('keywords');
            if (searchParams.get('collection')) collectedParams.collection = searchParams.get('collection');
            if (searchParams.get('ownership')) collectedParams.ownership = searchParams.get('ownership');
            if (searchParams.get('sort_by')) collectedParams.sort_by = searchParams.get('sort_by');
            
            // Track if there are any filters/search
            if (Object.keys(collectedParams).length > 0) {
              searchData = collectedParams;
              Analytics.trackSearch(searchData);
            }
          }

          this.loading_status = this.$t('loading_projects');
          this.is_searching = true;
          this.errors = [];

          return axios
            .get(url)
            .then(function(response) {
              
              if (!response.data.projects){
                vm.errors.push(response.data);
                throw new Error(response.data);
              }

              vm.projects = response.data;
              vm.pagination_page = vm.PaginationCurrentPage;
              
              // Track search results count
              if (typeof Analytics !== 'undefined' && searchData) {
                Analytics.trackEvent('search_results', {
                  results_count: response.data.found || 0
                });
              }
            })
            .catch(function(error) {
              console.log("error", error);
            })
            .then(function() {
              vm.loading_status = "";
              vm.is_searching = false;
              vm.is_loading = false;
            });
        },
        createProject: async function(type) {
          vm = this;
          let form_data = {};
          let url = CI.site_url + '/api/editor/create/' + type;
          this.loading_status = this.$t("processing_please_wait");
          this.dialog_create_project = false;

          try {
            let response = await axios.post(url, form_data);
            if (response.data.id) {
              vm.EditProject(response.data.id);
            }
            vm.loadProjects();
          } catch (error) {
            console.log("error", error);
            await vm.$alert(vm.$extractErrorMessage(error), { 
              title: "Failed to create project",
              color: 'error' 
            });
          }
        },
        EditProject: function(id) 
        {
          let window_ = window.open(CI.site_url + '/editor/edit/' + id, 'project-' + id);
          if (window_){
            window_.focus();
          }
        },
        viewAccessPermissions: async function (id)
        {
          let ProjectAccessPermissions= await this.getProjectAccessPermissions(id);
          this.dialog_access_options = {
              'project_access':ProjectAccessPermissions,
            };
            this.dialog_access_project = true;
        },
        transferOwnership: function(id) {
          this.dialog_transfer_ownership_options = {
            'projects': [id]
          };
          this.dialog_transfer_ownership = true;
        },
        ExportProjectPackage: function(id) {
          let url = CI.site_url + '/api/packager/download_zip/' + id + '/1';
          window.open(url, '_blank');
        },
        ExportProjectJSON: function(id) {
          let url = CI.site_url + '/api/editor/json/' + id;
          window.open(url, '_blank');
        },
        ShareProject: async function(id) { 
          try {
            let vm = this;
            let hasPermissionsToShare = await this.hasProjectAdminAccess(id);

            if (!hasPermissionsToShare){
              await this.$alert(this.$t("no_permissions_to_share"), { color: 'error'});              
              return false;
            }

            let users = await this.getUsersList();
            let SharedUsers = await this.getProjectSharedUsers(id);

            this.dialog_share_options = {
              'users': users,
              'shared_users': SharedUsers,
              'key': id,
              'project_id': id
            };
            this.dialog_share_project = true;
            this.loadProjects();

          } catch (e) {
            console.log("shareProject error", e);
            let message = vm.$extractErrorMessage(e);
            await vm.$alert(message, { color: 'error'});
          }
        },
        DeleteProject: async function(id) {
          let confirmed = await this.$confirm(this.$t("confirm_delete"));
          if (!confirmed) {
            return false;
          }

          vm = this;
          let url = CI.site_url + '/api/editor/delete/' + id;

          try {
            await axios.post(url);
            vm.loadProjects();
          } catch (error) {
            console.log("error", error);
            await vm.$alert(vm.$extractErrorMessage(error), { 
              title: "Failed to delete",
              color: 'error' 
            });
          }
        },
        DeleteProjectRevision: async function(id) {
          let confirmed = await this.$confirm(this.$t("confirm_delete"));
          if (!confirmed) {
            return false;
          }

          vm = this;
          let url = CI.site_url + '/api/versions/delete_by_id';
          let options = {
            id: id
          };

          try {
            await axios.post(url, options);
            vm.loadProjects();
          } catch (error) {
            console.log("error", error);
            await vm.$alert(vm.$extractErrorMessage(error), { 
              title: "Failed to delete",
              color: 'error' 
            });
          }
        },
        getProjectIcon: function(type) {
          projectIcon = this.project_types_icons[type];
          return projectIcon;
        },
        PaginatePage: function(page) {
          this.loadProjects();
        },
        removeFilter: function(filter_type, value_idx) {
         this.$delete(this.search_filters[filter_type], value_idx);
        },
        getFilterChipColor: function(filter_type) {
          const colorMap = {
            'collection': '#c6d4ff',
            'type': '#b0bec5',
            'ownership': '#98d7c2',
            'users_filter': '#68bbe3'
          };
          return colorMap[filter_type] || '#526bc7';
        },
        onApplyUserFilter: function(selected_users) {
            if (!this.facets.users_filter) {
                Vue.set(this.facets, 'users_filter', []);
            }
            
            if (!this.search_filters.users_filter) {
                Vue.set(this.search_filters, 'users_filter', []);
            }
            
            selected_users.forEach(user => {
                if (!this.facets.users_filter.find(u => u.id === user.id)) {
                    this.facets.users_filter.push({
                        id: user.id,
                        title: user.username
                    });
                }
                
                if (!this.search_filters.users_filter.includes(user.id)) {
                    this.search_filters.users_filter.push(user.id);
                }
            });
        },
        openUserFilterDialog: function() {
            this.dialog_user_filter = true;
        },
        getUsersList: async function() {
          vm = this;
          let url = CI.site_url + '/api/share/users';
          let response = await axios.get(url);

          if (response.status == 200) {
            return response.data.users;
          }

          return response.data;
        },
        getProjectSharedUsers: async function(project_id) {
          let vm = this;
          let url = CI.site_url + '/api/share/list/' + project_id;

          let response = await axios.get(url);

          if (response.status == 200) {
            return response.data.users;
          }

          throw new Error(response);
        },
        hasProjectAdminAccess: async function(project_id) {
          let vm = this;
          let url = CI.site_url + '/api/editor/has_admin_access/' + project_id;

          try{
            let response = await axios.get(url);

            if (response.status == 200) {
              if (response.data.access=='admin'){
                return true;
              }
            }
        } catch (e) {}
          return false;

        },
        getProjectAccessPermissions: async function(project_id) {
          let vm = this;
          let url = CI.site_url + '/api/editor/access_permissions/' + project_id;

          let response = await axios.get(url);

          if (response.status == 200) {
            return response.data.access;
          }

          throw new Error(response);
        },                
        toggleProjectSelection: function() {
          this.selected_projects = [];
          if (this.select_all_projects == true) {
            for (i = 0; i < this.Projects.length; i++) {
              this.selected_projects.push(this.Projects[i].id);
            }
          }
        },
        toggleProjectDetails: function(project_id) {
          let idx = this.collapsible_list.indexOf(project_id);
          if (idx == -1) {
            this.collapsible_list.push(project_id);
          } else {
            this.collapsible_list.splice(idx, 1);
          }
        },
        isProjectDetailsOpen: function(project_id) {
          let idx = this.collapsible_list.indexOf(project_id);
          if (idx == -1) {
            return false;
          }
          return true;
        },
        addProjectToCollection: async function(project_id) {
          try {
            let collections = await this.getCollectionsList();
            this.dialog_share_collection_options = {
              'collections': collections,
              'projects': [project_id]
            };
            this.dialog_share_collection = true;
          } catch (e) {
            console.log("shareProject error", e);
            await this.$alert(this.$extractErrorMessage(e), { 
              title: "Failed",
              color: 'error' 
            });
          }
        },
        manageProjectCollections: async function(project_id) 
        {
          //get collections for the project
          let project = this.Projects.find(x => x.id == project_id);
          if (!project){
            return false;
          }

          if (!project.collections){
            await this.$alert("No collections found for this project", { color: 'info' });
            return false;
          }

          this.dialog_manage_collections_options = {
            'project_id': project_id,
            'collections': project.collections
          };
          this.dialog_manage_collections = true;
        },
        addProjectsToCollection: async function() {
          try {
            if (this.selected_projects.length == 0) {
              await this.$alert(this.$t("select_atleast_one_project"), { color: 'warning' });
              return false;
            }

            let collections = await this.getCollectionsList();

            this.dialog_share_collection_options = {
              'collections': collections,
              'projects': this.selected_projects
            };

            this.dialog_share_collection = true;
            console.log(this.dialog_share_collection);

          } catch (e) {
            console.log("shareProject error", e);
            await this.$alert(this.$extractErrorMessage(e), { 
              title: "Failed",
              color: 'error' 
            });
          }
        },
        getCollectionsList: async function() {
          vm = this;
          let url = CI.site_url + '/api/collections/tree';
          let response = await axios.get(url);

          if (response.status == 200) {
            return response.data.collections;
          }

          return response.data;
        },
        OnAddProjectsToCollection: async function(obj) {
          try {
            let vm = this;

            let form_data = obj;
            let url = CI.site_url + '/api/collections/add_projects';

            let response = await axios.post(url,
              form_data
            );
            
            this.dialog_share_collection = false;
            this.loadProjects();
          } catch (e) {
            console.log("addProjectsToCollection error", e);
            await this.$alert(this.$extractErrorMessage(e), { 
              title: "Failed to add projects",
              color: 'error' 
            });
          }
        },
        removeFromCollection: async function(project_id, collection_id) {
          let confirmed = await this.$confirm(this.$t("confirm_remove_project_from_collection"));
          if (!confirmed) {
            return false;
          }

          try {
            let vm = this;

            let form_data = {
              'projects': project_id,
              'collections': collection_id
            };
            let url = CI.site_url + '/api/collections/remove_projects/';

            let response = await axios.post(url,
              form_data
            );

            this.loadProjects();
          } catch (e) {
            console.log("removeCollection error", e);
            await this.$alert(this.$extractErrorMessage(e), { 
              title: "Failed to remove",
              color: 'error' 
            });
          }
        },
        importProject: async function(){
            let formData = new FormData();
            formData.append('file', this.import_file);
            
            if (this.import_project_type && this.import_project_type.value){
              formData.append('type', this.import_project_type.value);
            }
            else{
              await this.$alert(this.$t("select_project_type"), { color: 'warning' });
              return false;
            }

            if (!this.import_file)
            {
                await this.$alert(this.$t("select_file_to_import"), { color: 'warning' });
                return false;
            }

            vm=this;
            this.import_file_errors=null;
            this.import_project_loading=true;
            let url=CI.site_url + '/api/importproject/';

            axios.post( url,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }
            ).then(function(response){
                vm.import_project_loading=false;
                if (response.data.sid){
                  vm.EditProject(response.data.sid);
                }
                vm.dialog_import_project=false;
                vm.loadProjects();
                vm.dialog_import_project_key++;
                vm.import_file=null;
            })
            .catch(function(response){
                vm.import_file_errors=response;
                vm.import_project_loading=false;
                console.log("error", response);
            }); 
        },
        projectSubInfo: function(project){
          let info=[];
          if (project.nation){
            info.push(project.nation);
          }
          
          if (project.year_start && project.year_end && 
              project.year_start!=project.year_end && 
              project.year_start!=0 && project.year_end!=0
            ){
            info.push(project.year_start + "-" + project.year_end);
          }
          else if (project.year_start && project.year_start!=0){
            info.push(project.year_start);
          }
          else if (project.year_end && project.year_end!=0){
            info.push(project.year_end);
          }
          return info.join(", ");
        }

      }
    })
  </script>

  <?php $this->load->view('common/analytics'); ?>
</body>

</html>