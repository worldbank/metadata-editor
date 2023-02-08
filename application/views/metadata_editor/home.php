<!DOCTYPE html>
<html>

<head>
  <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@mdi/font@6.x/css/materialdesignicons.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

  <script src="https://adminlte.io/themes/v3/plugins/jquery/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-fQybjgWLrvvRgtW6bFlB7jaZrFsaBXjsOMm/tB9LTS58ONXgqbR9W8oWht/amnpF" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
  <script src="https://unpkg.com/moment@2.26.0/moment.js"></script>

  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">
</head>

<style>
  <?php //echo $this->load->view('metadata_editor/bootstrap-forms.css',null,true); 
  ?><?php echo $this->load->view('metadata_editor/styles.css', null, true); ?>
</style>

<body class="layout-top-nav">

  <script>
    var CI = {
      'base_url': '<?php echo site_url(); ?>'
    };
  </script>

  <div id="app" data-app>

    <div class="wrapper">

      <?php echo $this->load->view('editor_common/header',null,true);?>




      <div class="content-wrapper">
        <section class="content">
          <!-- Provides the application the proper gutter -->
          <div class="container" style="overflow:auto;">




            <div class="row">

              <!--sidebar -->
              <div class="sidebar col-3">

                <div class="mr-4 mt-5">
                  <v-expansion-panels v-model="facet_panel" multiple class="">
                    <v-expansion-panel>
                      <v-expansion-panel-header>
                        Project types
                      </v-expansion-panel-header>
                      <v-expansion-panel-content>
                        <template v-for="(type_name, type_key) in DataTypes">
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" v-model="search_filters['data_type']" :value="type_key" :id="'filter-type-'+type_key">
                            <label class="form-check-label" :for="'filter-type-'+type_key">{{type_name}}</label>
                          </div>
                        </template>                        
                      </v-expansion-panel-content>
                    </v-expansion-panel>                    

                    <v-expansion-panel>
                      <v-expansion-panel-header>
                        Tags
                      </v-expansion-panel-header>
                      <v-expansion-panel-content>
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" value="" id="defaultCheck1">
                          <label class="form-check-label" for="defaultCheck1">Microdata</label>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" value="" id="defaultCheck1">
                          <label class="form-check-label" for="defaultCheck1">Document</label>
                        </div>
                      </v-expansion-panel-content>
                    </v-expansion-panel>


                  </v-expansion-panels>
                </div>


              </div>
              <!-- end sidebar -->

              <div class="projects col">
                <?php echo $this->load->view('metadata_editor/home_buttons', null, true); ?>

                <div>

                  <div class="rowx">
                      <div class="search-box">

                        <div class="text-center">
                          <div class="input-group">
                            <input type="text" class="form-control" placeholder="Keywords..." aria-label="Search" aria-describedby="search-box" v-model="search_keywords">
                            <span class="input-group-text" id="search-box" @click="search()">Search</span>
                          </div>
                        </div>

                      </div>
                  </div>

                  <div v-if="SearchFiltersQuerystring" class="mt-2">
                        Filters:
                        <template v-for="(filter_values, filter_type) in search_filters">
                          <template v-for="(filter_value,idx) in filter_values">
                            <span class="badge badge-primary mr-1" @click="removeFilter(filter_type,idx)">{{filter_value}}                            
                              <span aria-hidden="true">&times;</span>                            
                            </span>
                          </template>
                        </template>                     
                  </div>

                  <div class="mt-5 p-3 border text-center text-danger" v-if="!Projects || projects.found<1"> No projects found!</div>

                  <div  v-if="!Projects || projects.found>0" class="row mb-2 border-bottom  mt-3">
                    <div class="col-md-6">
                      <div class="p-2" v-if="Projects">
                        <strong>{{parseInt(projects.offset) +1}}</strong> - <strong>{{parseInt(projects.offset + projects.projects.length)}}</strong> of <strong>{{projects.total}}</strong> projects
                      </div>
                    </div>

                    <div class="col-md-6">
                      <template>
                        <div class="float-right" v-if="PaginationTotalPages">
                          <v-pagination v-model="pagination_page" :length="PaginationTotalPages" :total-visible="6"  @input="PaginatePage"></v-pagination>
                        </div>
                      </template>
                    </div>

                  </div>

                  <div v-for="project in Projects" class="row" >
                      <div class="col  border-bottom">
                          <h5 class="wb-card-title title">
                            <a href="#" :title="project.title" class="d-flex" @click="EditProject(project.id)">
                              <i :title="project.type" :class="project_types_icons[project.type]"></i>&nbsp;
                              <span v-if="project.title.length>1">{{project.title}}</span>
                              <span v-else>Untitled</span>
                            </a>
                          </h5>
                          <div class="text-secondary text-small">
                          {{project.type}} {{project.idno}}
                          </div>
                                                  
                          <div class="survey-stats mt-3 text-small">
                                <span class="mr-3"><span class="wb-label">Last modified:</span> <span class="wb-value">{{momentDate(project.changed)}}</span></span>
                                <span><span class="wb-label">Created by:</span> <span class="wb-value capitalize">{{project.username}}</span></span>
                                <span class="ml-4"> <a @click="EditProject(project.id)" href="#">Edit</a> |
                                <a @click="DeleteProject(project.id)" href="#">Delete</a>
                                </span>
                          </div>
                        
                        </div>

                        <div class="col-2  card-thumbnail-col  border-bottom">
                          <a href="#" @click="EditProject(project.id)">
                            <img :src="'<?php echo site_url('api/editor/thumbnail');?>/' + project.id" alt="" class="img-fluid img-thumbnail rounded shadow-sm project-card-thumbnail">
                          </a>
                        </div>


                </div>

              </div>

            </div>

          </div>
        </section>
      </div>

    </div>


    <template class="create-new-project">
      <div class="text-center">
        <v-dialog v-model="dialog_create_project" width="500">

          <v-card>
            <v-card-title class="text-h5 grey lighten-2">
              Create new Project
            </v-card-title>

            <v-card-text>
              <div>
                <a class="dropdown-item" href="#" @click="createProject('survey')"><i :class="project_types_icons['survey']"></i> Microdata</a>
                <a class="dropdown-item" href="#" @click="createProject('timeseries')"><i :class="project_types_icons['timeseries']"></i> Timeseries</a>
                <a class="dropdown-item" href="#" @click="createProject('timeseries-db')"><i :class="project_types_icons['timeseries-db']"></i> Timeseries database</a>
                <a class="dropdown-item" href="#" @click="createProject('document')"><i :class="project_types_icons['document']"></i> Document</a>
                <a class="dropdown-item" href="#" @click="createProject('table')"><i :class="project_types_icons['table']"></i> Table</a>
                <a class="dropdown-item" href="#" @click="createProject('image')"><i :class="project_types_icons['image']"></i> Image</a>
                <a class="dropdown-item" href="#" @click="createProject('script')"><i :class="project_types_icons['script']"></i> Script</a>
                <a class="dropdown-item" href="#" @click="createProject('video')"><i :class="project_types_icons['video']"></i> Video</a>
                <a class="dropdown-item" href="#" @click="createProject('geospatial')"><i :class="project_types_icons['geospatial']"></i> Geospatial</a>
              </div>
            </v-card-text>

            <v-divider></v-divider>

            <v-card-actions>
              <v-spacer></v-spacer>
              <v-btn color="primary" text @click="dialog_create_project = false">
                Close
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>
      </div>
    </template>


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


  <script>
    // 1. Define route components.        
    const Home = {
      template: '<div>Home -todo </div>'
    }

    // 2. Define some routes
    // Each route should map to a component. The "component" can
    // either be an actual component constructor created via
    // `Vue.extend()`, or just a component options object.
    const routes = [{
      path: '/',
      component: Home,
      name: 'home'
    }]

    // 3. Create the router instance and pass the `routes` option
    const router = new VueRouter({
      routes // short for `routes: routes`
    })


    /* router.beforeEach((to, from, next)=>{
            console.log("router",to,from);

            route_path=to.path.replace('/study/','');
            //store.commit('tree_active_node',route_path);
            if (store.state.formTemplateParts[route_path] !== undefined){
                store.commit('tree_active_node',route_path);
            }

            if (route_path=='/'){}
            //store.commit('tree_active_node','study_desc.title_statement.idno');            
            next();
        })
      */


    vue_app = new Vue({
      el: '#app',
      vuetify: new Vuetify(),
      router: router,
      data: {
        projects: [],
        is_loading: false,
        loading_status: null,
        form_errors: [],
        facet_panel: [0],
        pagination_page: 0,
        dialog_create_project: false,
        search_keywords: '',
        search_filters:{
          "data_type":[],
          "tag":[]
        },
        data_types:{
          "survey":"Microdata",
          "document":"Document",
          "table":"Table",
          "geospatial":"Geospatial",
          "image":"Image",
          "script": "Script",
          "video":"Video",
          "timeseries":"Timeseries",
          "timeseries-db":"Timeseries DB",
        },
        project_types_icons: {
          "document": "fa fa-file-code",
          "survey": "fa fa-database",
          "geospatial": "fa fa-globe-americas",
          "table": "fa fa-database",
          "timeseries": "fa fa-chart-line",
          "timeseries-db": "fa fa-chart-line",
          "image": "fa fa-image",
          "video": "fa fa-video",
          "script": "fa fa-file-code"
        }


      },
      created: async function() {
        //await this.$store.dispatch('initData',{dataset_idno:this.dataset_idno});
        //this.init_tree_data();
        //Vue.set(this.search_filters,"data_type",[]);
      },
      mounted: function() {
        this.loadProjects();
      },
      computed: {
        Title() {
          return 'title';
        },
        DataTypes()
        {
          let sorted={};          
          let sorted_keys=Object.keys(this.data_types).sort();
          for (k in sorted_keys){
            sorted[sorted_keys[k]]=this.data_types[sorted_keys[k]];
          }
          return sorted;
        },
        Projects() {
          return this.projects.projects;
        },
        PaginationTotalPages()
        {
          return Math.ceil(this.projects.total/this.projects.limit);
        },
        PaginationOffset()
        {
          let pageSize=this.projects.limit;
          let currentPage=this.pagination_page-1;
          return pageSize * currentPage;
        },
        PaginationCurrentPage()
        {
          let offset=this.projects.offset;
          let limit =this.projects.limit;
          return Math.ceil(offset / limit) +1;
        },
        SearchFiltersQuerystring()
        {
          return jQuery.param(this.search_filters);
          /*let qs='';
          for(i=0;i<Object.keys(this.search_filters).length;i++){
            let filter_name=Object.keys(this.search_filters)[i];
            for (k=0;this.search_filters[filter_name]
          }*/
        }
      },
      
      watch: {
        SearchFiltersQuerystring: function (new_, old_) {
          this.search();
        }
      },
      methods: {
        momentDate(date) {
          return moment.utc(date).format("MMM d, YYYY")
        },
        search: function(){
          this.pagination_page=1;
          this.loadProjects();
        },
        loadProjects: function() {
          vm = this;

          let url = CI.base_url + '/api/editor/?offset='+this.PaginationOffset
                    + '&' + 'keywords=' + this.search_keywords
                    + '&' + this.SearchFiltersQuerystring ;
                      
          this.loading_status = "Loading projects...";

          return axios
            .get(url)
            .then(function(response) {
              console.log("success", response);
              vm.projects = response.data;
              vm.pagination_page=vm.PaginationCurrentPage;
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
          this.loading_status = "Creating project...";

          axios.post(url,
              form_data
              /*headers: {
                  "xname" : "value"
              }*/
            )
            .then(function(response) {
              console.log(response);
              vm.loadProjects();
              if (response.data.project) {
                window.open(CI.base_url + '/editor/edit/' + response.data.project.id);
              }
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
        EditProject: function(id) {
          window.open(CI.base_url + '/editor/edit/' + id);
        },
        DeleteProject: function (id)
        {
          if (!confirm("Are you sure you want to delete the project?")){
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
        PaginatePage: function (page){
          this.loadProjects();
        },
        removeFilter: function(filter_type,value_idx){
            this.$delete (this.search_filters[filter_type],value_idx);
        }
      }
    })
  </script>
</body>

</html>