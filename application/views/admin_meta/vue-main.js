///vue admin metadata component
Vue.component('vue-main-app', {
    props:['value'],
    data: function () {    
        return {
            metadata_types:[],
            schemas:[],
            activeMetaId:null,
            menu_model:false,
            menu_x:0,
            menu_y:0,
            menu_id:null,
            menu_schema_model:false,
            menu_schema_x:0,
            menu_schema_y:0,
            menu_schema_id:null,
            dialog_json_viewer:{
                show:false,
                title:'',
                data:'',
                loading_message:'',
                message_success:'',
                message_error:'',
                is_loading:false
            },
            dialog_edit_schema:{
                show:false,
                title:'',
                data:'',
                loading_message:'',
                message_success:'',
                message_error:'',
                is_loading:false
            },
            dialog_edit_meta:{
                show:false,
                title:'',
                data:'',
                loading_message:'',
                message_success:'',
                message_error:'',
                is_loading:false
            },
            dialog:{
                show:false,
                title:'',
                loading_message:'',
                message_success:'',
                message_error:'',
                is_loading:false
            },
            dialog_share_meta:{
                show:false,
                is_loading:false,
                meta_type_id:null
            }
        }
    },
    mounted: function(){
        this.getMetadataTypes();
        this.getSchemas();
    },
    
    computed: {
      MetadataTypes(){
          if (this.metadata_types && this.metadata_types.result){
              return this.metadata_types.result;
          }
      },
      Schemas(){
          if (this.schemas && this.schemas.result){
              return this.schemas.result;
          }
      },
    },
    methods:{
      showMenu (e, metaTypeId) {
        e.preventDefault()
        this.menu_model = false
        this.menu_x = e.clientX
        this.menu_y = e.clientY
        this.menu_id = metaTypeId
        this.$nextTick(() => {
          this.menu_model = true
        })
      },
      showMenuSchema (e, schemaId) {
        e.preventDefault()
        this.menu_schema_model = false
        this.menu_schema_x = e.clientX
        this.menu_schema_y = e.clientY
        this.menu_schema_id = schemaId
        this.$nextTick(() => {
          this.menu_schema_model = true
        })
      },
      showMessageDialog: function(message, type){
          if (type=='error'){
              this.dialog.title='Error';
              this.dialog.message_error=message;
          }
          else{
              this.dialog.message_success=message;
          }
          this.dialog.show=true;
      }, 
      getSchemas: function(){
          vm=this;
          let url=CI.base_url + '/api/admin-metadata/schemas';
          axios.get( url
          ).then(function(response){
              console.log("Schemas",response.data);
              vm.schemas=response.data;
          })
          .catch(function(response){
              vm.errors=response;
              //alert("Failed: " + vm.erorrMessageToText(response));
              console.log("failed", response);
          });            
      },
      onSchemaUpdated: function(){
        this.getSchemas();
      },
      onMetaUpdated: function(){
        this.getMetadataTypes();
      },
      viewSchema: function(schemaID){
        vm=this;
        let url=CI.base_url + '/api/admin-metadata/schema_by_id/'+schemaID;
        axios.get( url
        ).then(function(response){
            console.log("Schema",response.data);
            vm.dialog_json_viewer.show=true;
            vm.dialog_json_viewer.title="Schema";
            vm.dialog_json_viewer.data=response.data;
        })
        .catch(function(response){
            vm.errors=response;
            //alert("Failed: " + vm.erorrMessageToText(response));
            console.log("failed", response);
        });            
      },      
      createSchemaDialog: function()
      {
          this.dialog_edit_schema.show=true;
          this.dialog_edit_schema.title="Create schema";
          this.dialog_edit_schema.data={};
      },
      editSchemaDialog: function(schemaID)
      {
        vm=this;
        let url=CI.base_url + '/api/admin-metadata/schema_by_id/'+schemaID;
        axios.get( url
        ).then(function(response){
            console.log("Schema",response.data);
            vm.dialog_edit_schema.show=true;
            vm.dialog_edit_schema.title="Edit schema";
            vm.dialog_edit_schema.data=response.data;            
        })
        .catch(function(response){
            vm.errors=response;
            //alert("Failed: " + vm.erorrMessageToText(response));
            console.log("failed", response);
        });  
      },
      deleteSchema: async function(schemaID)
      {
          let confirmed= await this.$confirm("Are you sure you want to delete?");

          if (!confirmed){
              return;
          }

          vm=this;
          let url=CI.base_url + '/api/admin-metadata/schema_delete/'+schemaID;
          axios.post( url
          ).then(function(response){
              console.log("Schema deleted",response.data);
              vm.getSchemas();
          })
          .catch(function(err){              
              vm.showMessageDialog(err.response.data, 'error');
              console.log("failed", err);
          });
      },
      getMetadataTypes: function(){
          vm=this;
          let url=CI.base_url + '/api/admin-metadata/type';
          axios.get( url
          ).then(function(response){
              console.log("MetadataTypes",response.data);
              vm.metadata_types=response.data;
          })
          .catch(function(response){
              vm.errors=response;
              //alert("Failed: " + vm.erorrMessageToText(response));
              console.log("failed", response);
          });            
      },
      createMetaDialog: function(){
          this.dialog_edit_meta.show=true;
          this.dialog_edit_meta.title="Create metadata type";
          this.dialog_edit_meta.data={};
          this.dialog_edit_meta.schema_list=this.Schemas;
      },
      editMetaDialog: function(metaID){
        vm=this;
        let url=CI.base_url + '/api/admin-metadata/type/'+metaID;
        this.activeMetaId=metaID;
        axios.get( url
        ).then(function(response){
            vm.dialog_edit_meta.show=true;
            vm.dialog_edit_meta.title="Edit metadata type";
            Vue.set(vm.dialog_edit_meta, 'data', response.data);            
            vm.dialog_edit_meta.schema_list=vm.Schemas;
        })
        .catch(function(response){
            vm.errors=response;
            //alert("Failed: " + vm.erorrMessageToText(response));
            console.log("failed", response);
        });
      },
      shareMetaDialog: function(metaID){
          this.activeMetaId=metaID;
          this.dialog_share_meta.show=true;
          this.dialog_share_meta.meta_type_id=metaID;
      },
      deleteMetadataType: async function(metaTypeID)
      {
        let confirmed= await this.$confirm("Are you sure you want to delete?");

          if (!confirmed){
              return;
          }

          vm=this;
          let url=CI.base_url + '/api/admin-metadata/type_delete/'+metaTypeID;
          axios.post( url
          ).then(function(response){
              console.log("Metadata type deleted",response.data);
              vm.getMetadataTypes();
          })
          .catch(function(err){
              vm.showMessageDialog(err.response.data, 'error');
              console.log("failed", err);
          });
      },
    },
    template: `
            <div class="vue-admin-metadata-component">

            <div class="container-fluid">
                <v-card>
                  <v-card-text>
                    
                    <v-tabs>
                      <v-tab href="#metadata-types">Metadata types</v-tab>
                      <v-tab-item id="metadata-types" key="metadata-types">

                        <div class="mt-2 d-flex flex-row-reverse">
                          <v-btn outlined small color="primary" @click="createMetaDialog">Create new type</v-btn>
                        </div>
                      
                          <v-simple-table>
                            <template v-slot:default>
                              <thead>
                                <tr>
                                  <th class="text-left">Metadata type</th>
                                  <th class="text-left">Title</th>
                                  <th class="text-left">Schema</th>                                                        
                                  <th class="text-left">Created by</th>
                                  <th class="text-left">Created on</th>
                                  <th class="text-left">Last updated by</th>
                                  <th class="text-left">Last updated</th>
                                  <th class="text-left">Actions</th>
                                </tr>
                              </thead>
                              <tbody>
                                <tr v-for="metadata_type in MetadataTypes">
                                  <td>{{metadata_type.name}}</td>                            
                                  <td><div>{{metadata_type.title}}</div>
                                    <div class="text-muted">{{metadata_type.description}}</div>
                                    </td>
                                  <td>{{metadata_type.schema_name}}</td>
                                  <td>{{metadata_type.cr_username}}</td>
                                  <td>{{momentDateUnix(metadata_type.created)}}</td>
                                  <td>{{metadata_type.ch_username}}</td>
                                  <td>{{momentDateUnix(metadata_type.changed)}}</td>
                                  <td>
                                    <v-icon @click="showMenu($event, metadata_type.id)">mdi-dots-vertical</v-icon>
                                  </td>                                  
                                  
                                </tr>
                              </tbody>
                            </template>
                          </v-simple-table>

                          </v-tab-item>

                      <v-tab href="#schemas">Schemas</v-tab>     
                      <v-tab-item id="schemas" key="schemas">
                        
                        <div class="mt-2 d-flex flex-row-reverse">
                          <v-btn outlined small color="primary" @click="createSchemaDialog">Create schema</v-btn>
                        </div>
                      
                          <v-simple-table>
                            <template v-slot:default>
                              <thead>
                                <tr>
                                  <th class="text-left">Schema name</th>
                                  <th class="text-left">Title</th>
                                  <th class="text-left">Version</th>
                                  <th class="text-left">Created by</th>
                                  <th class="text-left">Created on</th>
                                  <th class="text-left">Last updated by</th>
                                  <th class="text-left">Last updated</th>
                                  <th class="text-left">Actions</th>
                                </tr>
                              </thead>
                              <tbody>
                                <tr v-for="schema in Schemas">
                                  <td>{{schema.name}}</td>                            
                                  <td>
                                    {{schema.title}}
                                    <div class="text-muted">{{schema.description}}</div>
                                  </td>
                                  <td>{{schema.version}}</td>
                                  <td>{{schema.cr_username}}</td>
                                  <td>{{momentDateUnix(schema.created)}}</td>
                                  <td>{{schema.ch_username}}</td>
                                  <td>{{momentDateUnix(schema.changed)}}</td>
                                  <td>
                                    <v-icon @click="showMenuSchema($event, schema.id)">mdi-dots-vertical</v-icon>                                    
                                  </td>
                                </tr>
                              </tbody>
                            </template>
                          </v-simple-table>
                          </v-tab-item>                 
                    </v-tabs>

                    
                  </v-card-text>
                </v-card>
            </div>


              <vue-dialog-json-viewer-component v-model="dialog_json_viewer"></vue-dialog-json-viewer-component>
              <vue-dialog-edit-schema-component v-model="dialog_edit_schema" v-on:schema-updated="onSchemaUpdated" v-on:schema-created="onSchemaUpdated" ></vue-dialog-edit-schema-component>
              <vue-dialog-edit-meta-component v-model="dialog_edit_meta" v-on:meta-updated="onMetaUpdated" v-on:meta-created="onMetaUpdated" :key="activeMetaId" ></vue-dialog-edit-meta-component>
              <vue-dialog-component v-model="dialog"></vue-dialog-component>
              <vue-meta-type-share v-model="dialog_share_meta" :meta_type_id="activeMetaId" :key="'meta-' + activeMetaId"></vue-meta-type-share>


              <!-- menu options -->
              <template>
                <v-menu
                  v-model="menu_model"
                  :position-x="menu_x"
                  :position-y="menu_y"
                  absolute
                  offset-y
                >                

                  <v-list>
                    <v-list-item>
                      <v-list-item-icon>
                        <v-icon>mdi-edit</v-icon>
                      </v-list-item-icon>
                      <v-list-item-title @click="editMetaDialog(menu_id)"><v-btn text> {{$t('edit')}}</v-btn></v-list-item-title>
                    </v-list-item>

                    <v-list-item>
                      <v-list-item-icon>
                        <v-icon>mdi-lock</v-icon>
                      </v-list-item-icon>
                      <v-list-item-title @click="shareMetaDialog(menu_id)"><v-btn text> {{$t('permissions')}}</v-btn></v-list-item-title>
                    </v-list-item>

                    <v-list-item>
                      <v-list-item-icon>
                        <v-icon>mdi-trash</v-icon>
                      </v-list-item-icon>
                      <v-list-item-title @click="deleteMetadataType(menu_id)"><v-btn text> {{$t('delete')}}</v-btn></v-list-item-title>
                    </v-list-item>

                  </v-list>
                </v-menu>
              </template>
              <!-- end menu options -->


              <!-- schema menu options -->
              <template>
                <v-menu
                  v-model="menu_schema_model"
                  :position-x="menu_schema_x"
                  :position-y="menu_schema_y"
                  absolute
                  offset-y
                >                
                
                  <v-list>
                    <v-list-item>
                      <v-list-item-icon>
                        <v-icon>mdi-edit</v-icon>
                      </v-list-item-icon>
                      <v-list-item-title @click="editSchemaDialog(menu_schema_id)"><v-btn text> {{$t('edit')}}</v-btn></v-list-item-title>
                    </v-list-item>

                    <v-list-item>
                      <v-list-item-icon>
                        <v-icon>mdi-preview</v-icon>
                      </v-list-item-icon>
                      <v-list-item-title @click="viewSchema(menu_schema_id)"><v-btn text> {{$t('view')}}</v-btn></v-list-item-title>
                    </v-list-item>

                    <v-list-item>
                      <v-list-item-icon>
                        <v-icon>mdi-trash</v-icon>
                      </v-list-item-icon>
                      <v-list-item-title @click="deleteSchema(menu_schema_id)"><v-btn text> {{$t('delete')}}</v-btn></v-list-item-title>
                    </v-list-item>

                  </v-list>
                </v-menu>
              </template>
              <!-- end menu options -->

            </div>          
            `    
});

