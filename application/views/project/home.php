<!DOCTYPE html>
<html>

<head>
  <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@mdi/font@6.x/css/materialdesignicons.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">

  <script src="https://adminlte.io/themes/v3/plugins/jquery/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment-with-locales.min.js"></script>
  <script src="https://unpkg.com/vue-i18n@8"></script>

  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">
</head>

<style>
  <?php //echo $this->load->view('metadata_editor/bootstrap-forms.css',null,true); 
  ?>
  <?php echo $this->load->view('metadata_editor/styles.css', null, true); ?>
  
  .text-xs {
    font-size: small;
    color: gray;
  }

  .cursor-pointer {
    cursor: pointer;
  }

  .v-text-field--filled.v-input--dense.v-text-field--single-line .v-label, .v-text-field--full-width.v-input--dense.v-text-field--single-line .v-label
  {
    font-weight:normal;
  }

  table th {
    white-space: nowrap;
  }
</style>

<body class="layout-top-nav">

  <script>
    var CI = {
      'base_url': '<?php echo site_url(); ?>'
    };
  </script>

  <div id="app" data-app >    
    <v-app >

    <div class="wrapper">

      <?php echo $this->load->view('editor_common/global-header', null, true); ?>


      <div class="content-wrapperx" v-cloak>
        <section class="content">

          <div class="container-fluid" >

            <div class="row">

              <!--sidebar -->
              <div class="sidebar col-md-2 col-sm-3">

                <div class="mr-4 mt-5">
                  <v-expansion-panels v-model="facet_panel" multiple class="">

                    <v-expansion-panel v-for="(facet_values,facet_key) in facets">
                      <v-expansion-panel-header class="capitalize">
                        {{$t(facet_key)}}
                      </v-expansion-panel-header>
                      <v-expansion-panel-content>
                        <div class="form-check" v-for="facet in facet_values">
                          <input class="form-check-input" @click="onFilterClick(facet_key,facet)" type="checkbox" v-model="search_filters[facet_key]" :value="facet.id" :id="facet_key+facet.id">
                          <label class="form-check-label" :for="facet_key+facet.id">{{facet.title}}</label>
                        </div>
                      </v-expansion-panel-content>
                    </v-expansion-panel>
                  </v-expansion-panels>
                </div>

              </div>
              <!-- end sidebar -->

              <div class="projects col">
                <div class="mt-5 mb-5">
                  <h3>{{$t("my_projects")}}</h3>

                  <div class="d-flex">
                    <div class="flex-grow-1 flex-shrink-0 mr-auto">
                      <div class="mb-5">
                        <v-tabs background-color="transparent">
                          <v-tab><v-icon>mdi-text-box</v-icon> Projects</v-tab>
                          <v-tab><v-icon>mdi-folder-text</v-icon> <a href="<?php echo site_url('collections');?>">{{$t("collections")}}</a> </v-tab>
                          <!--<v-tab>Archives</v-tab>-->
                          <v-tab><v-icon>mdi-alpha-t-box</v-icon> <a href="<?php echo site_url('templates');?>">{{$t("templates")}}</a></v-tab>
                        </v-tabs>
                      </div>
                    </div>
                    <div class="">
                      <v-btn color="primary" @click="dialog_create_project=true">{{$t("create_project")}}</v-btn>        
                      <v-btn color="primary" @click="dialog_import_project=true">{{$t("import")}}</v-btn>
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
                                prepend-inner-icon="mdi-magnify" 
                                label="Search..." 
                                single-line dense outlined clearable 
                                @click:append="search" 
                                @keyup.enter="search" 
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
                    <template v-for="(filter_values, filter_type) in search_filters">
                      <template v-for="(filter_value,idx) in filter_values">                        
                        <v-chip @click:close="removeFilter(filter_type,idx)" small color="primary" close class="mr-1">
                        {{getFacetTitleById(filter_type,filter_value)}}                                     
                        </v-chip>
                      </template>
                    </template>
                  </div>

                  <div class="mt-5 p-3 border  text-danger" v-if="errors && errors.length>0"> 
                    <div><strong>Error:</strong> <a href="<?php echo site_url('editor');?>">Refresh page</a></div>
                    <div v-for="error in errors">{{error}}</div>
                  </div>

                  

                  <template>

                  <div class="bg-white shadow rounded p-3 pt-1 mt-2" elevation="10">


                    <div class="mt-5 mb-3 p-3 border text-center text-danger" v-if="!errors && !Projects || projects.found<1"> No projects found!</div>

                      <div v-if="!Projects || projects.found>0" class="row mb-2 border-bottom  mt-3">
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


                    <div class="row mb-1">
                      
                        <div class=" p-1" v-if="ProjectsCount>0">
                          <span style="padding-left:25px;padding-right:30px;" > <input type="checkbox" v-model="select_all_projects" @change="toggleProjectSelection" /></span>
                          
                          <v-tooltip bottom>
                            <template v-slot:activator="{ on, attrs }">
                            <v-btn icon @click="addProjectsToCollection"  :disabled="selected_projects.length==0">
                              <v-icon
                                color="primary"
                                dark
                                v-bind="attrs"
                                v-on="on"
                              >
                                mdi-folder-plus
                              </v-icon>
                            </v-btn>
                            </template>
                            <span>Add projects to collections</span>
                          </v-tooltip>

                        </div>
                      
                    </div>


                    <table class="table table-hover table-projects" v-if="projects && projects.found>0">
                      <thead>
                        <tr>
                          <th style="width:30px;"></th>
                          <th style="width:80px;"></th>
                          <th style="width:17px;"></th>
                          <th class="project-title-col">Title</th>
                          <th>Owner</th>
                          <th style="width:120px;">Modified</th>
                          <th>Created by</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="project in Projects" @click.prevent="EditProject(project.id)">
                          <td><input type="checkbox" v-model="selected_projects" :value="project.id" @click="checkboxOnClick" /></td>
                          <td><template v-if="project.thumbnail">
                                <img style="width:60px;height:60px;" :src="'<?php echo site_url('api/editor/thumbnail'); ?>/' + project.id" alt="" class=" border img-fluid img-thumbnail rounded shadow-sm project-card-thumbnail">
                              </template>
                              <template v-else>
                                <img style="width:60px;height:60px;" src="<?php echo base_url(); ?>files/icon-blank.png" alt="" class=" border img-fluid img-thumbnail rounded shadow-sm project-card-thumbnail">
                              </template>
                            </td>
                          <td style="vertical-align:top"><i :title="project.type" :class="project_types_icons[project.type]"></i></td>
                          <td>
                            <div class="project-title">
                              <a href="#" :title="project.title" class="d-flex" @click="EditProject(project.id)">                                
                                <span v-if="project.title.length>1">{{project.title}}</span>
                                <span v-else>Untitled</span>
                              </a>
                            </div>
                            <div class="text-secondary text-small">
                              {{project.type}} {{project.idno}}
                            </div>
                            <template v-for="collection in project.collections">
                                <v-chip small color="#dce3f7" class="mr-1" close @click:close="removeFromCollection(collection.sid,collection.id)">
                                  {{collection.title}}                                      
                                </v-chip>
                              </template>

                          </td>
                          <td class="capitalize">{{project.username}}</td>
                          <td>{{momentDate(project.changed)}}</td>
                          <td class="capitalize">{{project.username_cr}}</td>
                          <td class="text-right">
                            
                          <v-icon @click.stop.prevent="showProjectMenu($event, project.id, true)">mdi-dots-vertical</v-icon> 
                          </td>
                        </tr>
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

    
    <vue-project-access-dialog v-model="dialog_access_project" v-bind="dialog_access_options">
    </vue-project-access-dialog>

    <vue-project-share v-model="dialog_share_project" v-bind="dialog_share_options" v-on:share-project="ShareProjectWithUser" v-on:remove-access="UnshareProjectWithUser">
    </vue-project-share>

    <vue-collection-share v-model="dialog_share_collection" v-bind="dialog_share_collection_options" v-on:share-with-collection="AddProjectsToCollection">
    </vue-collection-share>

    <template class="create-new-project">
      <div class="text-center">
        <v-dialog v-model="dialog_create_project" width="500">

          <v-card>
            <v-card-title class="text-h5 grey lighten-2">
              {{$t("create_project")}}
            </v-card-title>

            <v-card-text>
              <div>
                <a class="dropdown-item" href="#" @click="createProject('survey')"><i :class="project_types_icons['survey']"></i> {{$t("microdata")}}</a>
                <a class="dropdown-item" href="#" @click="createProject('timeseries')"><i :class="project_types_icons['timeseries']"></i> {{$t("timeseries")}}</a>
                <a class="dropdown-item" href="#" @click="createProject('timeseries-db')"><i :class="project_types_icons['timeseries-db']"></i> {{$t("timeseries-db")}}</a>
                <a class="dropdown-item" href="#" @click="createProject('document')"><i :class="project_types_icons['document']"></i> {{$t("document")}}</a>
                <a class="dropdown-item" href="#" @click="createProject('table')"><i :class="project_types_icons['table']"></i> {{$t("table")}}</a>
                <a class="dropdown-item" href="#" @click="createProject('image')"><i :class="project_types_icons['image']"></i> {{$t("image")}}</a>
                <a class="dropdown-item" href="#" @click="createProject('script')"><i :class="project_types_icons['script']"></i> {{$t("script")}}</a>
                <a class="dropdown-item" href="#" @click="createProject('video')"><i :class="project_types_icons['video']"></i> {{$t("video")}}</a>
                <a class="dropdown-item" href="#" @click="createProject('geospatial')"><i :class="project_types_icons['geospatial']"></i> {{$t("geospatial")}}</a>
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
        <v-dialog v-model="dialog_import_project" width="500">

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
                    label="Select"
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
            <v-list-item-title @click="ShareProject(menu_active_project_id)"><v-btn text>{{$t('Share')}}</v-btn></v-list-item-title>
          </v-list-item>
          <v-list-item>
            <v-list-item-title @click="viewAccessPermissions(menu_active_project_id)"><v-btn text>{{$t('View access')}}</v-btn></v-list-item-title>
          </v-list-item>
          
          <v-list-item>
            <v-list-item-title @click="DeleteProject(menu_active_project_id)"><v-btn text>{{$t('delete')}}</v-btn></v-list-item-title>
          </v-list-item>

          <v-divider></v-divider>
          
          <v-list-item>
            <v-list-item-title @click="ExportProjectJSON(menu_active_project_id)"><v-btn text>{{$t('Export JSON')}}</v-btn></v-list-item-title>
          </v-list-item>

          <v-list-item>
            <v-list-item-title @click="ExportProjectPackage(menu_active_project_id)"><v-btn text>{{$t('Export package (ZIP)')}}</v-btn></v-list-item-title>
          </v-list-item>
        <!--
          <v-list-item>
            <v-list-item-title @click="previewTemplate(menu_active_project_id)"><v-btn text>{{$t('preview')}}</v-btn></v-list-item-title>
          </v-list-item>
          <v-list-item>
            <v-list-item-title @click="pdfTemplate(menu_active_project_id)"><v-btn text>{{$t('pdf')}}</v-btn></v-list-item-title>
          </v-list-item>          
  -->
        </v-list>
      </v-menu>
    </template>


    </v-app>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/vue@2.x/dist/vue.js"></script>
  <!--
  <script src="https://unpkg.com/vue-router@3"></script>
  <script src="https://unpkg.com/vuex@3.4.0/dist/vuex.js"></script>
-->

  <script src="<?php echo base_url(); ?>javascript/vue-router.min.js"></script>
  <script src="<?php echo base_url(); ?>javascript/vuex.min.js"></script>
  <script src="<?php echo base_url(); ?>javascript/axios.min.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.js"></script>
  <!--<script src="https://unpkg.com/axios/dist/axios.min.js"></script>-->
  <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.20/lodash.min.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/vue-deepset@0.6.3/vue-deepset.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/ajv/6.12.2/ajv.bundle.js" integrity="sha256-u9xr+ZJ5hmZtcwoxwW8oqA5+MIkBpIp3M2a4AgRNH1o=" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/deepdash/browser/deepdash.standalone.min.js"></script>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css" crossorigin="anonymous" />

  <style>
    .control-border-top .v-input__control {
      border-top: 1px solid #e0e0e0;
    }
  </style>

  <script>

    <?php
    echo $this->load->view("project/vue-project-share-component.js", null, true);
    echo $this->load->view("project/vue-collection-share-component.js", null, true);
    echo $this->load->view("project/vue-project-access-component.js", null, true);
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
      //{ path: '/editor/trash/vue-search.php', component: SearchComp, name:"search" },
      {
        path: '/share',
        component: ShareProject,
        name: 'share'
      }
    ]

    const router = new VueRouter({
    routes, // short for `routes: routes`
    mode: 'history'
  })

  const vuetify = new Vuetify({
    theme: {
      themes: {
        light: {
          primary: '#526bc7',
          secondary: '#b0bec5',
          accent: '#8c9eff',
          error: '#b71c1c',
        },
      },
    },
  })


    /*router.beforeEach((to, from, next) => {
      console.log("router beforeEach", to, from);
    })*/

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
        pagination_page: 0,
        dialog_create_project: false,
        dialog_import_project: false,
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
        users_list: null,
        errors:[],
        projects_shared: [],
        search_keywords: '',
        search_filters: {},
        collapsible_list: [], //show/hide project details
        show_project_menu: false,        
        menu_x: 0,
        menu_y: 0,
        menu_active_project_id: null,
        data_types: {
          "survey": "Microdata",
          "document": "Document",
          "table": "Table",
          "geospatial": "Geospatial",
          "image": "Image",
          "script": "Script",
          "video": "Video",
          "timeseries": "Timeseries",
          "timeseries-db": "Timeseries DB",
        },
        project_types_icons: {
          "document": "fas fa-file-alt",
          "survey": "fa fa-database",
          "geospatial": "fa fa-globe-americas",
          "table": "fa fa-database",
          "timeseries": "fa fa-chart-line",
          "timeseries-db": "fas fa-project-diagram",
          "image": "fa fa-image",
          "video": "fa fa-video",
          "script": "fa fa-file-code"
        },
        /*sort_by_options:{
          "title_asc":"Title (A-Z)",
          "title_desc":"Title (Z-A)",
          "updated_asc":"Oldest ↓",
          "updated_desc":"Recent ↑" 
        },*/
        sort_by_options:[],            
        sort_by:"updated_desc",


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
        $route: {
          handler: function(newRouteValue){
            console.log("route changed",newRouteValue);
            this.ReadFilterQS();            
          },
          deep: true
        }
      },
      methods: {
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
          console.log(this.Projects[projectIndex]);
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
            "video": this.$t("video")
          }
        },
        initSortOptions: function(){
          /*this.sort_by_options={
            "title_asc":this.$t("title_az"),
            "title_desc":this.$t("title_za"),
            "updated_asc":this.$t("oldest"),
            "updated_desc":this.$t("recent") 
          }*/
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
          this.$router.push({ path: '', query: search_filters})
        },
        ReadFilterQS: function()
        {
          let urlParams = new URLSearchParams(window.location.search);

          //get from querystring
          let search_filters = {};
          for(i=0;i<Object.keys(this.search_filters).length;i++){
            let filter_name=Object.keys(this.search_filters)[i];
            let values=urlParams.get(filter_name);
            console.log("filter_name",filter_name,"values",values);
            if (values && values.length>0){
              search_filters[filter_name]=values.split(",");
            }
          }

            //this.searchText=urlParams.get('searchText');

          //apply filters
          for(f=0;f<Object.keys(search_filters).length;f++){
            let filter_name=Object.keys(search_filters)[f];
            this.search_filters[filter_name]=search_filters[filter_name];
          }

          //keyword search
          this.search_keywords=urlParams.get('keywords');

          //set sort
          let sort_=urlParams.get('sort_by');
          if (!sort_==''){
            if (this.sort_by_options.find(x => x.value == sort_)){
              this.sort_by=sort_;
            }
          }

          /*this.filter1=search_filters.filter1;
          this.filter2=search_filters.filter2;
          this.filter3=search_filters.filter3;
          this.countries=search_filters.countries.split(",");*/
        },
        onWindowFocus: function() {
          this.search();
        },
        projectEditUrl: function(project_id) {
          return CI.base_url + '/editor/edit/' + project_id;
        },
        momentDate(date) {
          //gmt to utc
          let utc_date = moment(date, "YYYY-MM-DD HH:mm:ss").toDate();
          return moment.utc(utc_date).format("YYYY-MM-DD")
        },
        momentShortDate(date) {
          let utc_date = moment(date, "YYYY-MM-DD HH:mm:ss").toDate();
          let year=moment.utc(utc_date).format("YYYY");
          let current_year=moment.utc().format("YYYY");

          if (year==current_year){
            return moment.utc(utc_date).format("MMM DD");
          }else{
            return moment.utc(utc_date).format("MMM DD, YYYY");
          }
        },
        momentAgo(date) {
          //moment.locale('fr');
          let utc_date = moment(date, "YYYY-MM-DD HH:mm:ss").toDate();
          return moment.utc(date).fromNow();
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
        getFacetTitleById: function(facet_name, facet_id) {
          if (!this.facets[facet_name]) {
            return '';
          }

          //find facet by id
          let facet = this.facets[facet_name].find(x => x.id == facet_id);

          if (facet) {
            return facet.title;
          }

          return facet_id;
        },
        loadFacets: function() {
          vm = this;
          let url = CI.base_url + '/api/editor/facets';
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
          let url = CI.base_url + '/api/files/size/' + projectId;
          return axios
            .get(url)
            .then(function(response) {
              Vue.set(vm.projects.projects[projectIndex], 'size', response.data.result);
              console.log("project size",response.data.result);
              
            })
            .catch(function(error) {
              console.log("error", error);
            });
        },
        loadProjects: function() {
          vm = this;

          let urlParams = new URLSearchParams(window.location.search);          
          
          /*if (!this.search_keywords) {
            this.search_keywords = '';
          }

          let url = CI.base_url + '/api/editor/?offset=' + this.PaginationOffset +
            '&' + 'keywords=' + this.search_keywords +
            '&' + this.SearchFiltersQuerystring;
          */

          let url = CI.base_url + '/api/editor/?offset=' + this.PaginationOffset +
            '&' + urlParams.toString();

          this.loading_status = "Loading projects...";
          this.errors = [];

          return axios
            .get(url)
            .then(function(response) {
              
              if (!response.data.projects){
                vm.errors.push(response.data);
                throw new Error(response.data);
              }

              console.log("success", response);
              vm.projects = response.data;
              vm.pagination_page = vm.PaginationCurrentPage;
            })
            .catch(function(error) {
              console.log("error", error);
            })
            .then(function() {
              console.log("request completed");
              this.loading_status = "";
            });
        },
        createProject: function(type) {
          vm = this;
          let form_data = {};
          let url = CI.base_url + '/api/editor/create/' + type;
          this.loading_status = this.$t("processing_please_wait");
          this.dialog_create_project = false;

          axios.post(url,
              form_data
              /*headers: {
                  "xname" : "value"
              }*/
            )
            .then(function(response) {
              if (response.data.id) {
                vm.EditProject(response.data.id);
              }
              vm.loadProjects();
            })
            .catch(function(error) {
              console.log("error", error);
              alert("Failed", error);
            })
            .then(function() {
              // always executed
              console.log("request completed");
            });
        },
        EditProject: function(id) 
        {
          let window_ = window.open(CI.base_url + '/editor/edit/' + id, 'project-' + id);
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
        ExportProjectPackage: function(id) {
          let url = CI.base_url + '/api/packager/download_zip/' + id + '/1';
          window.open(url, '_blank');
        },
        ExportProjectJSON: function(id) {
          let url = CI.base_url + '/api/editor/json/' + id;
          window.open(url, '_blank');
        },
        ShareProject: async function(id) { 
          try {
            let hasPermissionsToShare = await this.hasProjectAdminAccess(id);

            if (!hasPermissionsToShare){
              alert("You don't have permissions to share this project");
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
            alert("Failed", JSON.stringify(e));
          }
        },
        DeleteProject: function(id) {
          if (!confirm(this.$t("confirm_delete"))) {
            return false;
          }

          vm = this;
          let url = CI.base_url + '/api/editor/delete/' + id;

          axios.post(url)
            .then(function(response) {
              vm.loadProjects();
            })
            .catch(function(error) {
              console.log("error", error);
              alert("Failed", error);
            });
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
        getUsersList: async function() {
          vm = this;
          let url = CI.base_url + '/api/share/users';
          let response = await axios.get(url);

          if (response.status == 200) {
            return response.data.users;
          }

          return response.data;
        },
        getProjectSharedUsers: async function(project_id) {
          let vm = this;
          let url = CI.base_url + '/api/share/list/' + project_id;

          let response = await axios.get(url);

          if (response.status == 200) {
            return response.data.users;
          }

          throw new Error(response);
        },
        hasProjectAdminAccess: async function(project_id) {
          let vm = this;
          let url = CI.base_url + '/api/editor/has_admin_access/' + project_id;

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
          let url = CI.base_url + '/api/editor/access_permissions/' + project_id;

          let response = await axios.get(url);

          if (response.status == 200) {
            return response.data.access;
          }

          throw new Error(response);
        },
        ShareProjectWithUser: async function(obj) {
          try {
            let vm = this;
            console.log("share project", obj);

            let response = await this._shareProject(obj.project_id, obj.user_id, obj.permissions);
            this.dialog_share_project = false;
            this.loadProjects();
          } catch (e) {
            console.log("shareProject error", e);
            alert("Failed", JSON.stringify(e));
          }

        },
        _shareProject: async function(project_id, user_id, permissions) {
          vm = this;
          let form_data = {
            'permissions': permissions
          };
          let url = CI.base_url + '/api/share/' + project_id + '/' + user_id;

          let response = await axios.post(url,
            form_data
          );

          return response;
        },
        _unshareProject: async function(project_id, user_id) {
          vm = this;
          let form_data = {};
          let url = CI.base_url + '/api/share/delete/' + project_id + '/' + user_id;

          try{
          let response = await axios.post(url,
            form_data
          );
          return response;
        } catch (e) {
            alert("Error:" +  e.response.data.message);            
        }
        return false;          
        },
        UnshareProjectWithUser: async function(obj) {
            let vm = this;
            let result = this._unshareProject(obj.project_id, obj.user_id);
            this.loadProjects();
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
        addProjectsToCollection: async function() {
          try {

            if (this.selected_projects.length == 0) {
              alert("Please select at least one project");
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
            alert("Failed", JSON.stringify(e));
          }

        },
        getCollectionsList: async function() {
          vm = this;
          let url = CI.base_url + '/api/collections';
          let response = await axios.get(url);

          if (response.status == 200) {
            return response.data.collections;
          }

          return response.data;
        },
        AddProjectsToCollection: async function(obj) {
          try {
            let vm = this;
            console.log("add projects to collection", obj);

            let form_data = obj;
            let url = CI.base_url + '/api/collections/add_projects';

            let response = await axios.post(url,
              form_data
            );

            console.log("completed addprojectstocollections", response);
            this.dialog_share_collection = false;
            this.loadProjects();
          } catch (e) {
            console.log("addProjectsToCollection error", e);
            alert("Failed", JSON.stringify(e));
          }
        },
        removeFromCollection: async function(project_id, collection_id) {
          if (!confirm("Are you sure you want to remove this collection from the project?")) {
            return false;
          }

          try {
            let vm = this;
            console.log("remove collection", project_id, collection_id);

            let form_data = {
              'projects': project_id,
              'collection_id': collection_id
            };
            let url = CI.base_url + '/api/collections/remove_projects/';

            let response = await axios.post(url,
              form_data
            );

            this.loadProjects();
          } catch (e) {
            console.log("removeCollection error", e);
            let message = (e.response.data.message) ? e.response.data.message : JSON.stringify(e.response.data);
            alert("Failed: " + message);
          }
        },
        importProject: function(){
            let formData = new FormData();
            formData.append('file', this.import_file);
            
            if (this.import_project_type && this.import_project_type.value){
              formData.append('type', this.import_project_type.value);
            }
            else{
              alert("Please select a project type");
              return false;
            }

            if (!this.import_file)
            {
                alert("Please select a file to import");
                return false;
            }

            vm=this;
            this.import_file_errors=null;
            this.import_project_loading=true;
            let url=CI.base_url + '/api/importproject/';

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
            })
            .catch(function(response){
                vm.import_file_errors=response;
                vm.import_project_loading=false;
                console.log("error", response);
            }); 
        },

      }
    })
  </script>
</body>

</html>