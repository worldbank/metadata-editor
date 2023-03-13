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

  .text-xs{
    font-size:small;
    color:gray;
  }

  .cursor-pointer{
    cursor:pointer;
  }
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


      <div class="content-wrapperx">
        <section class="content">
          <!-- Provides the application the proper gutter -->
          <div class="container-fluid">


            <div class="row">

              <!--sidebar -->
              <div class="sidebar col-md-2 col-sm-3">

                <div class="mr-4 mt-5">
                  <v-expansion-panels v-model="facet_panel" multiple class="">

                    <v-expansion-panel v-for="(facet_values,facet_key) in facets">
                      <v-expansion-panel-header class="capitalize">
                        {{facet_key}}
                      </v-expansion-panel-header>
                      <v-expansion-panel-content>
                        <div class="form-check" v-for="facet in facet_values">
                          <input class="form-check-input" type="checkbox" v-model="search_filters[facet_key]"  :value="facet.id" :id="facet_key+facet.id">
                          <label class="form-check-label" :for="facet_key+facet.id">{{facet.title}}</label>
                        </div>
                      </v-expansion-panel-content>
                    </v-expansion-panel>

                    <!-- <v-expansion-panel>
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
                    </v-expansion-panel> -->


                  </v-expansion-panels>
                </div>


              </div>
              <!-- end sidebar -->

              <div class="projects col">
                <?php echo $this->load->view('project/home_buttons', null, true); ?>

                <div>

                  <div class="rowx">
                      <div class="search-box" style="max-width:600px">

                        <div class="text-center">
                          <div class="input-group">
                            <v-text-field
                              v-model="search_keywords"
                              append-icon="mdi-magnify"
                              label="keywords"
                              single-line
                              filled
                              rounded
                              dense
                              clearable
                              @click:append="search"
                              @keyup.enter="search"
                            ></v-text-field>                            
                          </div>
                        </div>

                      </div>
                  </div>

                  <div v-if="SearchFiltersQuerystring" class="mt-2">
                        Filters:
                        <template v-for="(filter_values, filter_type) in search_filters">
                          <template v-for="(filter_value,idx) in filter_values">
                            <span class="badge badge-primary mr-1" @click="removeFilter(filter_type,idx)">{{getFacetTitleById(filter_type,filter_value)}}
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

                  <div class=" p-1">
                      <button @click="addProjectsToCollection" :disabled="selected_projects.length==0" title="Add to collection" class="btn btn-xs btn-outline-primary" ><span  class="mdi mdi-folder-plus"></span> Add to collection</button>                      
                  </div>

                  <template v-if="page_layout=='detail'">
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
                                  <span class="ml-4 float-right"> 
                                    <a class="btn btn-xs btn-outline-primary" @click="EditProject(project.id)" href="#">Edit</a>
                                    <a class="btn btn-xs btn-outline-danger" @click="DeleteProject(project.id)" href="#">Delete</a>
                                    <a v-if="project.is_shared>0" class="btn btn-xs btn-primary"  @click="ShareProject(project.id)" href="#">
                                      Shared
                                    </a>
                                    <a v-else class="btn btn-xs btn-outline-primary"  @click="ShareProject(project.id)" href="#">
                                      Share
                                    </a>  
                                  </span>
                            </div>
                          
                          </div>

                          <div class="col-2  card-thumbnail-col  border-bottom">
                            <a href="#" @click="EditProject(project.id)">
                              <img :src="'<?php echo site_url('api/editor/thumbnail');?>/' + project.id" alt="" class="img-fluid img-thumbnail rounded shadow-sm project-card-thumbnail">
                            </a>
                          </div>
                  </div>
                </template>
                <template v-else class="bg-light">
                  <table class="table table-sm" style="font-size:small;"> 
                    <thead>
                      <tr>
                        <th><input type="checkbox" v-model="select_all_projects" @change="toggleProjectSelection"/></th>
                        <th></th>
                        <th>Title</th>
                        <th>Updated by</th>
                        <th>Updated</th>
                        <th></th>
                      </tr>
                    <tr v-for="(project,index) in Projects" class="project-row" :key="index">
                          
                            <td><input type="checkbox" v-model="selected_projects" :value="project.id"/></td>
                            <td style="vertical-align:top"><i :title="project.type" :class="project_types_icons[project.type]"></i></td>
                            <td>
                              <div class="wb-card-title title">                                
                                <a href="#" :title="project.title" class="d-flex" @click="EditProject(project.id)">
                                
                                  <span v-if="project.title.length>1">{{project.title}}</span>
                                  <span v-else>Untitled</span>
                                </a>
                              </div>
                              <div class="text-secondary text-small">
                              {{project.type}} {{project.idno}}
                              </div>

                              <div v-if="project.collections.length>0" class="mt-2">
                                <span class="text-secondary">Collections: </span>
                                <template v-for="collection in project.collections">
                                    <span @click="removeCollection(project.id,collection.id)" class="cursor-pointer badge font-weight-normal text-secondary border border-warning rounded mr-1">
                                    {{collection.title}}                                  
                                      <span aria-hidden="true">&times;</span>
                                    </span>
                                </template>
                              </div>
                            </td>


                            <td><span class="wb-value capitalize text-truncate">{{project.username}}</span></td>
                            <td>{{momentAgo(project.changed)}} <br/> <span class="text-xs">{{momentDate(project.changed)}}</span></td>

                            <td>
                            
                                  <span class="ml-4"> 
                                    <a class="btn btn-xs btn-outline-primary" @click="EditProject(project.id)" href="#">Edit</a>
                                    <a class="btn btn-xs btn-outline-danger" @click="DeleteProject(project.id)" href="#">Delete</a>
                                    <a v-if="project.is_shared>0" class="btn btn-xs btn-primary"  @click="ShareProject(project.id)" href="#">
                                      Shared
                                    </a>
                                    <a v-else class="btn btn-xs btn-outline-primary"  @click="ShareProject(project.id)" href="#">
                                      Share
                                    </a>  
                                  </span>
                            
                            </td>
                          
                          
                            
                      </tr>
                </template>

              </div>

            </div>

          </div>
        </section>
      </div>

    </div>

    <vue-project-share v-model="dialog_share_project"  v-bind="dialog_share_options" v-on:share-project="ShareProjectWithUser" v-on:remove-access="UnshareProjectWithUser" >
    </vue-project-share>

    <vue-collection-share v-model="dialog_share_collection"  v-bind="dialog_share_collection_options" v-on:share-with-collection="AddProjectsToCollection" >
    </vue-collection-share>

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

  <style>
  
  .control-border-top .v-input__control{
    border-top: 1px solid #e0e0e0;
  }

  </style>

  <script>

    <?php 
        echo $this->load->view("project/vue-project-share-component.js",null,true);
        echo $this->load->view("project/vue-collection-share-component.js",null,true);
    ?>

    // 1. Define route components.        
    const Home = {template: '<div>Home -todo </div>'}
    const ShareProject = {props: ['value'],template: '<div><vue-project-share /> </div>'}


    //routes
    const routes = [
      {path: '/', component: Home, name: 'home'},
      {path: '/share', component: ShareProject, name: 'share'}
    ]

    //router instance
    const router = new VueRouter({
      routes // short for `routes: routes`
    })


    router.beforeEach((to, from, next)=>{
      console.log("router",to,from);
    })
    
    vue_app = new Vue({
      el: '#app',
      vuetify: new Vuetify(),
      router: router,
      data: {
        page_layout: 'list',
        projects: [],
        selected_projects:[],
        select_all_projects: false,
        is_loading: false,
        loading_status: null,
        form_errors: [],
        facets:[],
        facet_panel: [0],
        pagination_page: 0,
        dialog_create_project: false,
        dialog_share_project: false,
        dialog_share_options:[],
        dialog_share_collection:false,
        dialog_share_collection_options:[],
        users_list:null,
        projects_shared:[],
        search_keywords: '',        
        search_filters:{
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
        this.loadFacets();
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
          //gmt to utc
          let utc_date=moment(date,"YYYY-MM-DD HH:mm:ss").toDate();
          return moment.utc(utc_date).format("YYYY-MM-DD")
        },
        momentAgo(date){
          let utc_date=moment(date,"YYYY-MM-DD HH:mm:ss").toDate();
          return moment.utc(date).fromNow();
        },
        search: function(){
          this.pagination_page=1;
          this.loadProjects();
        },
        getFacetTitleById: function(facet_name,facet_id)
        {
          if (!this.facets[facet_name]){
            return '';
          }


          //find facet by id
          let facet=this.facets[facet_name].find(x=>x.id==facet_id);

          if (facet){
            return facet.title;
          }

          return facet_id;            
        },
        loadFacets: function() 
        {
          vm = this;
          let url = CI.base_url + '/api/editor/facets';
          return axios
            .get(url)
            .then(function(response) {
              vm.facets = response.data.facets;
              let facet_types=Object.keys(vm.facets);

              for(i=0;i<facet_types.length;i++){
                let facet_name=facet_types[i];
                Vue.set(vm.search_filters,facet_name,[]);
              }
            })
            .catch(function(error) {
              console.log("error", error);
            });
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
        ShareProject: async function(id) 
        {
          try{
            let users=await this.getUsersList();
            let SharedUsers=await this.getProjectSharedUsers(id);

            this.dialog_share_options = {
            'users':users,
            'shared_users':SharedUsers,
            'key': id,
            'project_id': id
          };
          this.dialog_share_project = true;
          this.loadProjects();

          }catch(e){
            console.log("shareProject error",e);
            alert("Failed",JSON.stringify(e));
          }
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
        },
        getUsersList: async function() 
        {
            vm = this;
            let url = CI.base_url + '/api/share/users';
            let response = await axios.get(url);

            if (response.status == 200) {
              return response.data.users;
            }
            
            return response.data;
          },
          getProjectSharedUsers: async function(project_id) 
          {
            let vm = this;
            let url = CI.base_url + '/api/share/list/' + project_id;

            let response = await axios.get(url);

            if (response.status == 200) {              
              return response.data.users;
            }
            
            throw new Error(response);
        },
        ShareProjectWithUser: async function(obj)
        {
          try{
            let vm = this;
            console.log("share project", obj);

            let response=await this._shareProject(obj.project_id,obj.user_id,obj.permissions);
            this.dialog_share_project=false;
            this.loadProjects();
          }
          catch(e){
            console.log("shareProject error",e);
            alert("Failed",JSON.stringify(e));
          }

        },
        _shareProject: async function(project_id, user_id, permissions) {
            vm = this;
            let form_data = {
              'permissions':permissions
            };
            let url = CI.base_url + '/api/share/' + project_id + '/' + user_id;
  
            let response= await axios.post(url,
                form_data
            );

            return response;
        },
        _unshareProject: async function(project_id, user_id) {
            vm = this;
            let form_data = {};
            let url = CI.base_url + '/api/share/delete/' + project_id + '/' + user_id;
  
            let response= await axios.post(url,
                form_data
            );

            return response;
        },
        UnshareProjectWithUser: async function(obj)
        {
          try{
            let vm = this;
            console.log("unshare project", obj);
            let result=this._unshareProject(obj.project_id,obj.user_id);
            this.loadProjects();

          }
          catch(e){
            console.log("UnshareProject error",e);
            alert("Failed",JSON.stringify(e));
          }
        },
        toggleProjectSelection: function()
        {
          this.selected_projects=[];
          if (this.select_all_projects==true){
            console.log("is true/false",this.select_all_projects);
            for(i=0;i<this.Projects.length;i++){
              this.selected_projects.push(this.Projects[i].id);
            }
          }
        },
        addProjectsToCollection: async function() 
        {
          try{
            
            if (this.selected_projects.length==0){
              alert("Please select at least one project");
              return false;
            }

            let collections=await this.getCollectionsList();

            this.dialog_share_collection_options = {
              'collections':collections,
              'projects':this.selected_projects
            };

            this.dialog_share_collection = true;
            console.log(this.dialog_share_collection);

          }catch(e){
            console.log("shareProject error",e);
            alert("Failed",JSON.stringify(e));
          }
                    
        },
        getCollectionsList: async function() 
        {
            vm = this;
            let url = CI.base_url + '/api/collections';
            let response = await axios.get(url);

            if (response.status == 200) {
              return response.data.collections;
            }
            
            return response.data;
          },
        AddProjectsToCollection: async function(obj)
        {
          try{
            let vm = this;
            console.log("add projects to collection", obj);

            let form_data = obj;
            let url = CI.base_url + '/api/collections/add_projects';
  
            let response= await axios.post(url,
                form_data
            );

            console.log("completed addprojectstocollections",response);
            this.dialog_share_collection=false;
            this.loadProjects();
          }
          catch(e){
            console.log("addProjectsToCollection error",e);
            alert("Failed",JSON.stringify(e));
          }
        },
        removeCollection: async function(project_id,collection_id) 
        {
          if (!confirm("Are you sure you want to remove this collection from the project?")){
            return false;
          }

          try{
            let vm = this;
            console.log("remove collection", project_id,collection_id);

            let form_data = {
              'project_id':project_id,
              'collection_id':collection_id
            };
            let url = CI.base_url + '/api/collections/remove_project/'+collection_id+'/'+project_id;
  
            let response= await axios.post(url,
                form_data
            );

            console.log("completed removecollection",response);
            this.loadProjects();
          }
          catch(e){
            console.log("removeCollection error",e);
            alert("Failed",JSON.stringify(e));
          }
        },
              
      }
    })
  </script>
</body>

</html>