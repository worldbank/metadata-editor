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
      <?php //echo $this->load->view('metadata_editor/bootstrap-forms.css',null,true); ?>
      <?php echo $this->load->view('metadata_editor/styles.css',null,true); ?>
</style>
<body class="layout-top-nav">

    <script>
        var CI = {'base_url': '<?php echo site_url();?>'}; 
    </script>

  <div id="app" data-app>
    
  <div class="wrapper">

      <nav class="main-header navbar navbar-expand-md navbar-light navbar-white">

      <a href="<?php echo site_url('admin/metadata_editor');?>" class="navbar-brand"><i class="fas fa-compass"></i> <span class="brand-text font-weight-light">Metadata Editor</span></a>

        

        <ul class="navbar-nav ml-auto">
            <li class="nav-item">                
                <a class="nav-link" href="<?php echo site_url('admin/metadata_editor/templates');?>" role="button">
                 Templates
                </a>
            </li>
            <li class="nav-item">                
                <a class="nav-link capitalize" href="#" role="button">
                  <i class="fas fa-user"></i> <?php echo $user=strtoupper($this->session->userdata('username'));?>
                </a>
            </li>
        </ul>
      </nav>


      

      <div class="content-wrapper">
          <section class="content">
            <!-- Provides the application the proper gutter -->
            <div class="container-fluid" style="overflow:auto;">


            

              <div class="row">

                <!--sidebar -->
                <div class="sidebar col-3">

                  <div class="mr-4 mt-5">
                    <v-expansion-panels 
                      v-model="facet_panel"
                      multiple class="">
                      <v-expansion-panel>
                        <v-expansion-panel-header>
                          Project types
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

                      <v-expansion-panel>
                        <v-expansion-panel-header>
                          Collections
                        </v-expansion-panel-header>
                        <v-expansion-panel-content>
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" value="" id="defaultCheck1">
                          <label class="form-check-label" for="defaultCheck1">Collection 1</label>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" value="" id="defaultCheck1">
                          <label class="form-check-label" for="defaultCheck1">Collection 2</label>
                        </div>
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
                  <?php echo $this->load->view('metadata_editor/home_buttons',null,true); ?>



                  <div>
                    <div v-if="!Projects"> There are no projects!</div>

                    <div class="row mb-2 border-bottom border-top" >
                      <div class="col-2">
                        <div class="p-2" v-if="Projects">
                          <strong>{{parseInt(projects.offset) +1}}</strong> - <strong>{{parseInt(projects.found)}}</strong> of <strong>{{projects.total}}</strong> projects
                        </div>
                      </div>

                      <div class="col-4">
                        <div class="search-box">

                        <div class="col-12 col-md-12 text-center">
                          <div class="input-group mb-3">
                              <input type="text" class="form-control" placeholder="Keywords..." aria-label="Search" aria-describedby="search-box" v-model="search_keywords">
                              <span class="input-group-text" id="search-box" @click="search(true)">Search</span>            
                          </div>
                        </div>

                        </div>
                      </div>

                      <div class="col">
                      <template>
                        <div class="float-right">
                          <v-pagination
                            v-model="pagination_page"
                            :length="6"
                          ></v-pagination>
                        </div>
                      </template>
                      </div>

                    </div>



                    <div v-for="project in Projects">
                      <div class="info-box shadow-none" @click="EditProject(project.id)" >
                        <span class="info-box-icon bg-light" :title="project.type"><i :class="project_types_icons[project.type]"></i></span>
                        <div class="info-box-content">
                          <a href="#" class="info-box-number" @click="EditProject(project.id)">
                            <span v-if="project.title.length>1">{{project.title}}</span>
                            <span v-else>Untitled</span>
                          </a>
                          <span class="info-box-text">{{project.idno}}</span>
                            <div class="text-secondary" style="font-size:small;">
                                <span class="mr-2">Last updated: {{momentDate(project.changed)}}</span>
                                <span class="mr-2 capitalize">By: {{project.username}}</span>
                                <a @click="EditProject(project.id)" href="#" >Edit</a> |
                                <a @click="DeleteProject(project.id)" href="#" >Delete</a>
                            </div>
                        </div>                        
                      </div>
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
        <v-dialog
          v-model="dialog_create_project"
          width="500"
        >

          <v-card>
            <v-card-title class="text-h5 grey lighten-2">
              Create new Project
            </v-card-title>

            <v-card-text>
              <div>
                    <a class="dropdown-item" href="#" @click="createProject('survey')">Microdata</a>
                    <a class="dropdown-item" href="#" @click="createProject('document')">Document</a>
                    <a class="dropdown-item" href="#" @click="createProject('table')">Table</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="#">Import from URL</a>
                </div>
            </v-card-text>

            <v-divider></v-divider>

            <v-card-actions>
              <v-spacer></v-spacer>
              <v-btn
                color="primary"
                text
                @click="dialog_create_project = false"
              >
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

  <script src="<?php echo base_url();?>javascript/vue-router.min.js"></script>
  <script src="<?php echo base_url();?>javascript/vuex.min.js"></script>
  <script src="<?php echo base_url();?>javascript/axios.min.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.js"></script>
    <!--<script src="https://unpkg.com/axios/dist/axios.min.js"></script>-->
    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.20/lodash.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/vue-deepset@0.6.3/vue-deepset.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ajv/6.12.2/ajv.bundle.js" integrity="sha256-u9xr+ZJ5hmZtcwoxwW8oqA5+MIkBpIp3M2a4AgRNH1o=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/deepdash/browser/deepdash.standalone.min.js"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css" crossorigin="anonymous" />   
    
  
  <script>

        // 1. Define route components.        
        const Home = { template: '<div>Home -todo </div>' }        

        // 2. Define some routes
        // Each route should map to a component. The "component" can
        // either be an actual component constructor created via
        // `Vue.extend()`, or just a component options object.
        const routes = [
            {path: '/', component: Home, name: 'home'}            
        ]

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

    
    vue_app=new Vue({
      el: '#app',
      vuetify: new Vuetify(),
      router:router,
      data:{          
          projects:[],
          is_loading:false,
          loading_status:null,
          form_errors:[],
          facet_panel:[],
          pagination_page:1,
          dialog_create_project:false,
          search_keywords:'',
          project_types_icons:
            {
            "document":"fa fa-file-code",
            "survey":"fa fa-database",

            }
          
      },
      created: async function(){
       //await this.$store.dispatch('initData',{dataset_idno:this.dataset_idno});
       //this.init_tree_data();
      }
      ,
      mounted: function(){
        this.loadProjects();
      },
      computed:{
        Title(){
          return 'title';
        },
        Projects(){
          return this.projects.projects;
        }
      },
      watch: {
        /*
        ProjectMetadata: 
        {
            deep:true,
            handler(val){
              this.saveProjectDebounce(val);
            }
        }*/
      },
      methods:{      
        momentDate(date) {
          return moment.utc(date).format("MMM d, YYYY")
        },  
        loadProjects: function() {
            vm=this;

            let url=CI.base_url + '/api/editor/';
            this.loading_status="Loading projects...";
            
            return axios
            .get(url)
            .then(function (response) {
                console.log("success",response);
                vm.projects=response.data;
            })
            .catch(function (error) {
                console.log("error",error);
            })
            .then(function () {
                console.log("request completed");
                this.loading_status="";
            });
        },
        createProject: function(type)
        {
          vm=this;
          let form_data={};
          let url=CI.base_url + '/api/editor/create/' + type;
          this.loading_status="Creating project...";


          axios.post(url, 
              form_data
              /*headers: {
                  "xname" : "value"
              }*/
          )
          .then(function (response) {
              console.log(response);
              vm.loadProjects();
              if(response.data.project){
                window.open(CI.base_url + '/admin/metadata_editor/edit/' + response.data.project.id);
              }
          })
          .catch(function (error) {
              console.log("error", error);
              alert("Failed", error);
          })
          .then(function () {
              // always executed
              console.log("request completed");
          });
        },
        EditProject: function(id){
          window.open(CI.base_url + '/admin/metadata_editor/edit/' + id);
        },
        getProjectIcon: function (type){
          projectIcon=this.project_types_icons[type];
          return projectIcon;
        }
    }
    })
  </script>
</body>
</html>
