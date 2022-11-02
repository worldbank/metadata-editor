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

      <nav class="main-header navbar navbar-expand-md navbar-light navbar-white">
        <a href="<?php echo site_url('admin/metadata_editor');?>" class="navbar-brand"><i class="fas fa-compass"></i> <span class="brand-text font-weight-light">Metadata Editor</span></a>
        <ul class="navbar-nav ml-auto">

          <li class="nav-item">
            <a class="nav-link" href="#" role="button">
              <i class="fas fa-user"></i> <?php echo $user = strtoupper($this->session->userdata('username')); ?>
            </a>
          </li>
        </ul>
      </nav>

      <div class="content-wrapper">
        <section class="content">
          <!-- Provides the application the proper gutter -->
          <div class="container-fluid" style="overflow:auto;">

            <div class="row">

              <div class="projects col">

                <div class="mt-3 mb-5">
                    <h1>Templates</h1>
                </div>

                <div>
                  <div v-if="!templates"> There are no templates!</div>

                  <div v-for="(data_type_label,data_type) in data_types" class="mb-3">
                    <h2 class="p-2">{{data_type_label}}</h2>

                    <table class="table table-bordered">
                      <tr>
                        <th>Type</th>
                        <th>Title</th>
                        <th>Version</th>
                        <th>Last updated</th>
                        <th></th>
                      </tr>
                      <template v-for="template in templates.core">                      
                        <template v-if="data_type==template.data_type">
                          <tr>                           
                            <td>{{template.template_type}}</td>
                            <td>
                              <v-icon>mdi-star</v-icon>
                              <span style="font-weight:bold;" href="#" >{{template.name}}</span>
                              <div>{{template.uid}}</div>
                            </td>
                            <td>{{template.version}}</td>
                            <td>-</td>
                            <td><button type="button" class="btn btn-sm btn-link" @click="duplicateTemplate(template.uid)" >Duplicate</button></td>
                          </tr>  
                        </template>
                      </template>

                      <template v-for="template in templates.custom">                      
                        <template v-if="data_type==template.data_type">
                          <tr>
                            <td>{{template.template_type}}</td>
                            <td>
                              <a href="#"  @click="editTemplate(template.uid)">{{template.name}}</a>
                              <div>{{template.uid}}</div>
                            </td>
                            <td>{{template.version}}</td>
                            <td>{{momentDate(template.changed)}}</td>
                            <td>
                              <button type="button" class="btn btn-sm btn-link" @click="duplicateTemplate(template.uid)" >Duplicate</button>
                              <button type="button" class="btn btn-sm btn-link" @click="editTemplate(template.uid)" >Edit</button>
                              <button type="button" class="btn btn-sm btn-link" @click="deleteTemplate(template.uid)" >Delete</button>    
                            </td>
                          </tr>  
                        </template>
                      </template>
                    </table>

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
              Create new Template
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
    const Home = {
      template: '<div>Home -todo </div>'
    }

    const routes = [{
      path: '/',
      component: Home,
      name: 'home'
    }]

    const router = new VueRouter({
      routes
    })

    vue_app = new Vue({
      el: '#app',
      vuetify: new Vuetify(),
      router: router,
      data: {
        templates:[],
        data_types:{ 
          "survey": "Microdata", 
          "timeseries": "Timeseries", 
          "timeseriesdb": "Timeseries Database", 
          "script": "Script", 
          "geospatial": "Geospatial", 
          "document": "Document", 
          "table": "Table", 
          "image": "Image", 
          "visualization": "Visualization", 
          "video": "Video"
        },
        is_loading: false,
        loading_status: null,
        form_errors: [],
        facet_panel: [],
        pagination_page: 1,
        dialog_create_project: false,
        search_keywords: '',
        project_types_icons: {
          "survey": "fas fa-database",
          "document": "fas fa-file-alt",
        }

      },
      created: async function() {
        //await this.$store.dispatch('initData',{dataset_idno:this.dataset_idno});
        //this.init_tree_data();
      },
      mounted: function() {
        this.loadTemplates();
      },
      computed: {
        Title() {
          return 'title';
        },
        Projects() {
          return this.projects.projects;
        }
      },
      watch: {
      },
      methods: {
        momentDate(date) {
          return moment.unix(date).format("MM/DD/YYYY")
        },
        loadTemplates: function() {
          vm = this;

          let url = CI.base_url + '/api/templates/';
          this.loading_status = "Loading templates...";

          return axios
            .get(url)
            .then(function(response) {
              console.log("success", response);
              vm.templates = response.data.templates;
              //vm.initDataTypes();
            })
            .catch(function(error) {
              console.log("error", error);
            })
            .then(function() {
              console.log("request completed");
              this.loading_status = "";
            });
        },
        initDataTypes: function()
        {
          this.templates.core.forEach((template, index) => {
            this.data_types.push(template.data_type);
          });
        },        
        duplicateTemplate: function(uid) 
        {
          vm = this;
          let form_data = {};
          let url = CI.base_url + '/api/templates/duplicate/' + uid;
          this.loading_status = "Creating template...";

          axios.post(url,
              form_data
              /*headers: {
                  "xname" : "value"
              }*/
            )
            .then(function(response) {
              console.log(response);
              vm.loadTemplates();
              if (response.data.template.uid) {
                window.open(CI.base_url + '/admin/metadata_editor/templates/' + response.data.template.uid);
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
        editTemplate: function(uid) {
          window.open(CI.base_url + '/admin/metadata_editor/templates/' + uid);
        },
        getProjectIcon: function(type) {
          projectIcon = this.project_types_icons[type];
          return projectIcon;
        }
      }
    })
  </script>
</body>

</html>