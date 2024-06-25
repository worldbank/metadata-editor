<!DOCTYPE html>
<html>

<head>
  <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@mdi/font@6.x/css/materialdesignicons.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.min.css" rel="stylesheet">
  
  <link href="<?php echo base_url()?>themes/nada52/fontawesome/css/all.css" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo base_url(); ?>themes/nada52/css/bootstrap.min.css">

  <script src="https://adminlte.io/themes/v3/plugins/jquery/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-fQybjgWLrvvRgtW6bFlB7jaZrFsaBXjsOMm/tB9LTS58ONXgqbR9W8oWht/amnpF" crossorigin="anonymous"></script>
  <script src="https://unpkg.com/moment@2.26.0/moment.js"></script>
  <script src="https://unpkg.com/vue-i18n@8"></script>


  <script src="https://cdn.jsdelivr.net/npm/vue@2.x/dist/vue.js"></script>
  <script src="<?php echo base_url(); ?>javascript/vue-router.min.js"></script>
  <script src="<?php echo base_url(); ?>javascript/vuex.min.js"></script>
  <script src="<?php echo base_url(); ?>javascript/axios.min.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.20/lodash.min.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/vue-deepset@0.6.3/vue-deepset.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/ajv/6.12.2/ajv.bundle.js" integrity="sha256-u9xr+ZJ5hmZtcwoxwW8oqA5+MIkBpIp3M2a4AgRNH1o=" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/deepdash/browser/deepdash.standalone.min.js"></script>


  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">
</head>

<style>
  <?php //echo $this->load->view('metadata_editor/bootstrap-forms.css',null,true); 
  ?><?php echo $this->load->view('metadata_editor/styles.css', null, true); ?>

  .navigation-tabs .v-tabs-bar{
    background-color: transparent!important;    
  }
</style>

<body class="layout-top-nav">

  <script>
    var CI = {
      'base_url': '<?php echo site_url(); ?>'
    };
  </script>

  <div id="app" data-app>
  <v-app>

    <div class="wrapper">

      <?php echo $this->load->view('editor_common/global-header', null, true); ?>

      <div class="content-wrapper" style="overflow:auto;height:100vh">
        <section class="content">
          <!-- Provides the application the proper gutter -->
          <div class="container" style="overflow:auto;">

            <div class="row">

              <div class="projects col">

                <div class="mt-3 mb-5">
                  <h3>{{$t('template_manager')}}</h3>

                  <div class="d-flex">

                  <v-tabs background-color="transparent" v-model="nav_tabs_model">
                      <v-tab @click="pageLink('projects')"><v-icon>mdi-text-box</v-icon> <a :href="site_base_url + '/editor'">{{$t("projects")}}</a></v-tab>
                      <v-tab @click="pageLink('collections')" active><v-icon>mdi-folder-text</v-icon> <a :href="site_base_url + '/collections'">{{$t("collections")}}</a> </v-tab>
                      <!--<v-tab>Archives</v-tab>-->
                      <v-tab @click="pageLink('templates')"><v-icon>mdi-alpha-t-box</v-icon> <a :href="site_base_url + '/templates'">{{$t("templates")}}</a></v-tab>
                  </v-tabs>

                  <div class="justify-content-end">
                    <v-btn class="primary" @click="showImportTemplateDialog">{{$t('import_template')}}</v-btn>
                  </div>

                  </div>
                  
                </div>
               
                <div>
                  <div v-if="!templates"> {{$t('no_templates_found')}}</div>

                  <div v-for="(data_type_label,data_type) in data_types" class="mb-3">
                    
                      <v-card-title>
                        <i :class="getProjectIcon(data_type)"></i>&nbsp;{{data_type_label}}
                      </v-card-title>
                      
                    <v-data-table                      
                      :headers="[
                        { text: $t('type'), value: 'template_type' },
                        { text: $t('default'), value: 'default'},
                        { text: $t('title'), value: 'name' },
                        { text: $t('language'), value: 'lang' },
                        { text: $t('version'), value: 'version' },
                        { text: $t('last_updated'), value: 'changed' },
                        { text: '', value: 'actions' }
                      ]"
                      :items="getTemplatesByType(data_type)"
                      class="elevation-1 pt-3"
                      :disable-pagination="true"
                      :items-per-page="100"
                      :hide-default-footer="true"


                    >
                      <!--
                      <template v-slot:top>
                            <div class="d-flex pl-6 pb-4 align-center">                                
                                <div class="v-data-table--title"><i :class="getProjectIcon(data_type)"></i>&nbsp;{{data_type_label}}</div>                                
                            </div>
                        </template>    
                      -->                                    
                      <template v-slot:item.default="{ item }">
                        <span class="btn btn-sm btn-link" @click="setDefaultTemplate(item.data_type,item.uid)">
                          <v-icon v-if="item.default">mdi-radiobox-marked</v-icon>
                          <v-icon v-else>mdi-radiobox-blank</v-icon>
                        </span>                        
                      </template>
                      <template v-slot:item.actions="{ item }">
                        <v-icon @click="showMenu($event, item.uid, item.template_type=='core')">mdi-dots-vertical</v-icon>
                      </template>
                      <template v-slot:item.changed="{ item }">
                        <span v-if="item.changed">{{momentDate(item.changed)}}</span>
                      </template>
                      <template v-slot:item.name="{ item }">
                        <div v-if="item.template_type=='core'">                          
                          <span style="font-weight:bold;" href="#">{{item.name}}</span>
                          <div>{{item.uid}}</div>
                        </div>
                        <div v-else>
                          <a target="_blank" :href="getTemplateEditLink(item)" @click="editTemplate(item.uid)">{{item.name}}</a>
                        </div>
                      </template>
                      

                    </v-data-table>

                  </div>

                </div>

              </div>

            </div>

          </div>
        </section>
      </div>    
    </div>

  </v-app>

    <template class="import-template">
      <div class="text-center">
        <v-dialog v-model="dialog_import_template" width="500">

          <v-card>
            <v-card-title class="text-h5 grey lighten-2">
              {{$t('import_template')}}
            </v-card-title>

            <v-card-text>
              <div>
                <div class="file-group form-field mb-3">
                  <label class="l" for="customFile">
                    <span>{{$t('select_file')}}: [JSON] </span>
                  </label>
                  <input type="file" accept="application/json" class="form-control p-1" @change="handleTemplateUpload( $event )">
                </div>

                <div v-if="!importJSON && templateFile" style="color:red;">{{$t('invalid_file_failed_to_read')}}</div>

              </div>
            </v-card-text>

            <v-divider></v-divider>

            <v-card-actions>
              <v-spacer></v-spacer>

              <v-btn :disabled="!importJSON" small color="primary" text @click="importTemplate">
                {{$t('import')}}
              </v-btn>
              <v-btn small text @click="dialog_import_template = false">
                {{$t('cancel')}}
              </v-btn>

            </v-card-actions>
          </v-card>
        </v-dialog>
      </div>
    </template>


    <template>
      <v-menu
        v-model="showTemplateMenu"
        :position-x="menu_x"
        :position-y="menu_y"
        absolute
        offset-y
      >

        <v-list>
          <!--<v-list-item>
            <v-list-item-title @click="editTemplate(menu_active_template_id)"><v-btn text>{{$t('edit')}}</v-btn></v-list-item-title>
          </v-list-item> -->
          <v-list-item>
            <v-list-item-title @click="duplicateTemplate(menu_active_template_id)"><v-btn text>{{$t('duplicate')}}</v-btn></v-list-item-title>
          </v-list-item>
          <v-list-item>
            <v-list-item-title @click="exportTemplate(menu_active_template_id)"><v-btn text>{{$t('export')}}</v-btn></v-list-item-title>
          </v-list-item>
          <template v-if="!menu_active_template_core">
            <v-list-item>
              <v-list-item-title @click="deleteTemplate(menu_active_template_id)"><v-btn text>{{$t('delete')}}</v-btn></v-list-item-title>
            </v-list-item>            
          </template>
          <v-list-item>
            <v-list-item-title @click="previewTemplate(menu_active_template_id)"><v-btn text>{{$t('preview')}}</v-btn></v-list-item-title>
          </v-list-item>
          <v-list-item>
            <v-list-item-title @click="pdfTemplate(menu_active_template_id)"><v-btn text>{{$t('pdf')}}</v-btn></v-list-item-title>
          </v-list-item>          
        </v-list>
      </v-menu>
    </template>


  </div>

  <script>  
    const translation_messages = {
      default: <?php echo json_encode($translations,JSON_HEX_APOS);?>
    }

    const i18n = new VueI18n({
      locale: 'default', // set locale
      messages: translation_messages, // set locale messages
    })

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

    vue_app = new Vue({
      i18n,
      el: '#app',
      vuetify: vuetify,
      router: router,
      data: {
        site_base_url: CI.base_url,
        templates: [],
        data_types: {},
        is_loading: false,
        loading_status: null,
        form_errors: [],
        facet_panel: [],
        pagination_page: 1,
        dialog_create_project: false,
        search_keywords: '',
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
        },
        dialog_import_template: false,
        template_import_errors:[],
        dialog_import: {},
        templateFile: '',
        importJSON: '',
        showTemplateMenu: false,        
        menu_x: 0,
        menu_y: 0,
        menu_active_template_id: null,
        menu_active_template_core: false,
        nav_tabs_active:2,
        nav_tabs_model:2
      },
      created: async function() {
        //await this.$store.dispatch('initData',{dataset_idno:this.dataset_idno});
        //this.init_tree_data();
      },
      mounted: function() {
        this.loadTemplates();
        this.loadDataTypes();
      },
      computed: {
        Title() {
          return 'title';
        },
        Projects() {
          return this.projects.projects;
        }
      },
      watch: {},
      methods: {
        getTemplateEditLink: function(template) {
          return CI.base_url + '/templates/edit/' + template.uid;
        },
        getTemplatesByType: function(type) {
          if (!this.templates.core || !this.templates.custom){
            return [];
          }
          
          return this.templates.core.filter(template => template.data_type == type).concat(this.templates.custom.filter(template => template.data_type == type));
        },
        pageLink: function(page){
          window.location.href = CI.base_url + '/'+page;
        },
        showMenu (e, templateId, isCore=false) {
          e.preventDefault()
          this.showTemplateMenu = false
          this.menu_x = e.clientX
          this.menu_y = e.clientY
          this.menu_active_template_id = templateId
          this.menu_active_template_core = isCore
          console.log("showMenu", e.clientX, e.clientY, templateId, isCore);
          this.$nextTick(() => {
            this.showTemplateMenu = true
          })
        },
        loadDataTypes: function()
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
            "video": this.$t("video"),
            "resource": this.$t("external-resources")
          }
        },
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
        initDataTypes: function() {
          this.templates.core.forEach((template, index) => {
            this.data_types.push(template.data_type);
          });
        },
        setDefaultTemplate: function(template_type, uid) {
          vm = this;
          let form_data = {};
          let url = CI.base_url + '/api/templates/default/' + template_type + '/' + uid;

          axios.post(url,
              form_data
              /*headers: {
                  "xname" : "value"
              }*/
            )
            .then(function(response) {
              console.log(response);
              vm.loadTemplates();
            })
            .catch(function(error) {
              console.log("error", error);
              alert("Failed", error);
            })
            .then(function() {
              console.log("request completed");
            });
        },
        deleteTemplate: function(uid) {
          if (!confirm("Confirm to delete?")) {
            return false;
          }

          vm = this;
          let form_data = {};
          let url = CI.base_url + '/api/templates/delete/' + uid;

          axios.post(url,
              form_data
              /*headers: {
                  "xname" : "value"
              }*/
            )
            .then(function(response) {
              console.log(response);
              vm.loadTemplates();
            })
            .catch(function(error) {
              console.log("error", error);
              if (error.response.data.error){
                alert ("Failed: " + error.response.data.error);
              }else{
                alert("Failed", error);
              }
            })
            .then(function() {
              console.log("request completed");
            });
        },
        exportTemplate: function(uid) {
          window.open(CI.base_url + '/api/templates/' + uid);
        },
        previewTemplate: function(uid) {
          window.open(CI.base_url + '/templates/preview/' + uid);
        },
        pdfTemplate: function(uid) {
          window.open(CI.base_url + '/templates/pdf/' + uid);
        },
        duplicateTemplate: function(uid) {
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
                window.open(CI.base_url + '/templates/edit/' + response.data.template.uid);
              }
            })
            .catch(function(error) {
              console.log("error", error);
              if (error.response.data.error){
                alert ("Failed: " + error.response.data.error);
              }else{
                alert("Failed", error);
              }
            })
            .then(function() {
              // always executed
              console.log("request completed");
            });
        },
        editTemplate: function(uid) {
          window.open(CI.base_url + '/templates/edit/' + uid);
        },
        getProjectIcon: function(type) {
          projectIcon = this.project_types_icons[type];
          return projectIcon;
        },
        showImportTemplateDialog: function(){
          this.dialog_import_template=true;
          this.template_import_errors=[];
        },
        importTemplate: function() {
          let formData = this.importJSON;

          vm = this;
          this.template_import_errors = [];
          let url = CI.base_url + '/api/templates/create'

          axios.post(url,
              formData, {}
            ).then(function(response) {
              vm.loadTemplates();
              alert(vm.$t("imported_successfully"));
              vm.dialog_import_template = false;
            })
            .catch(function(response) {
              console.log("failed",response);
              let error_message=vm.$t('failed');
              if (response.response.data.message){
                error_message+=" - " + response.response.data.message
              }
              alert(error_message);
              vm.template_import_errors = response;
            });
        },
        handleTemplateUpload(event) {
          this.templateFile = event.target.files[0];
          if (!this.templateFile) return;
          this.readFile(this.templateFile); //results are stored in this.importJSON
        },
        readFile(file) {
          let vm = this;
          let reader = new FileReader();
          reader.onload = e => {
            console.log(e.target.result);
            vm.importJSON = JSON.parse(e.target.result);
          };
          reader.readAsText(file);
        }
      }
    })
  </script>
</body>

</html>