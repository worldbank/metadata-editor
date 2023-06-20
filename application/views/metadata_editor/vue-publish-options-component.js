/// publish project options
Vue.component('publish-options', {
    props:['value'],
    data: function () {    
        return {
            field_data: this.value,
            resources_selected:[],
            toggle_resources_selected:false,
            resources_overwrite:"no",
            publish_metadata:true,
            dialog_process:false,
            publish_thumbnail:true,
            publish_resources:true,
            catalog_connections:[],
            panels: [0, 1,2],
            catalog:'',
            publish_options:{
                "overwrite": {
                    "title":"Overwrite if already exists?",
                    "value":"no",
                    "type":"text",
                    "enum": {
                        "yes":"Yes",
                        "no":"No"
                    }                    
                },
                "published":
                {
                    "title":"Publish",
                    "value":0,
                    "type":"text",
                    "enum":{
                        "0": "Draft",
                        "1": "Publish"
                    }
                },
                "data_access":{
                    "title":"Data access",
                    "value":6,
                    "type":"text",
                    "enum":{
                        "1": "Direct access",
                        "2": "Publich use files",
                        "3": "Licensed data files",
                        "4": "Data accessible only in data enclave",
                        "5": "Data available from external repository",
                        "6": "Data not available",
                        "7": "Open access"
                    }
                },
                "da_link":{
                    "custom":true,
                    "title":"Data access link",
                    "value":'',
                    "type":"text"
                },
                "repositoryid":{
                    "custom":true,
                    "title":"Collection",
                    "value":'',
                    "type":"text"
                },
            },            
            
            file:'',
            update_status:'',
            publish_processing_message:'',
            is_publishing:false,
            is_publishing_completed:false,
            project_export_status:'',
            collections:[],
            publish_responses:{}//all publish responses                        
        }
    },
    created: async function(){
        this.loadCatalogConnections();
    },
    methods:{
        initPublishResponses: function(){
            this.publish_responses={
                "export":[],
                "metadata":{
                    "messages":[],
                    "errors":[],
                },
                "thumbnail":{
                    "messages":[],
                    "errors":[],
                },
                "external_resources":{
                    "messages":[],
                    "errors":[],
                    //"resource.id, resource_title",
                    //"error_response"
                }
            };
        },
        toggleSelectedResources: function()
        {
            this.resources_selected = [];
            if (this.toggle_resources_selected == true) {                
                for (i = 0; i < this.ExternalResources.length; i++) {
                this.resources_selected.push(i);
                }
            }
        },
        publishToCatalog: async function()
        {
            this.dialog_process=true;
            let formData=this.PublishOptions;
            vm=this;

            if(!this.publish_metadata && !this.publish_thumbnail && !this.publish_resources){
                alert("Please select at least one option to publish");
                return;
            }

            this.initPublishResponses();
            this.is_publishing=true;
            this.is_publishing_completed=false;

            this.publish_processing_message="Preparing project export...";
            await this.prepareProjectExport();

            if (this.publish_metadata==true){
                this.publish_processing_message="Publishing project metadata...";
                await this.publishProjectMetadata();
            }

            if (this.publish_thumbnail==true){
                this.publish_processing_message="Publishing project thumbnail...";
                try{
                    await this.publishProjectThumbnail();
                    this.publish_responses.thumbnail.messages.push("Thumbnail published successfully");
                }catch(error){
                    console.log("publishing thumbnail failed", error);
                    this.publish_responses.thumbnail.errors.push(error.response.data);
                }
            }

            if (this.publish_resources==true){
                this.publish_processing_message="Publishing external resources...";
                await this.publishExternalResoures();
            }

            this.publish_processing_message="Publishing completed";
            this.is_publishing=false;
            this.is_publishing_completed=true;
            //await this.publishExternalResourcesFiles();
        },
        publishProjectMetadata: async function(){
            let formData=this.PublishOptions;
            vm=this;

            let url=CI.base_url + '/api/publish/' +this.ProjectID +'/' + this.catalog;
            this.publish_responses.metadata.messages.push("starting metadata publishing to: " + url);
        
            return axios.post(url,
                formData,
                {}
            ).then(function(response){
                vm.publish_responses.metadata.messages.push("metadata publishing updated successfully");
            })
            .catch(function(error){
                console.log("publishing project failed", error);
                vm.publish_responses.metadata.errors.push(error.response.data);
            }); 
        },
        publishExternalResoures:  async function() 
        {
            if (this.resources_selected.length==0){
                return;
            }

            let formData=this.PublishOptions;
            vm=this;

            for (const idx of this.resources_selected) {
                vm.publish_processing_message="Publishing external resource: " + vm.ExternalResources[idx].title;    
                try {
                    const { data } = await vm.publishSingleResource(this.ExternalResources[idx]);
                    vm.publish_responses.external_resources.messages.push( 
                        vm.ExternalResources[idx].title + ' published successfully'
                    );
                } catch (error) {
                    console.error(`Request ${idx+1} failed:`, error.response);
                    vm.publish_responses.external_resources.errors.push({
                        'resource_id':vm.ExternalResources[idx].id,
                        'resource_title':vm.ExternalResources[idx].title,
                        'error':{
                            'status_code': error.response.status,
                            'status_text': error.response.statusText,
                            'data': error.response.data
                        }
                    });
                }
            }
        },
        publishSingleResource: async function(resource)
        {
            let formData={
                "overwrite": this.resources_overwrite,
                "resource_id": resource.id,
                "sid": this.ProjectID,
                "catalog_id": this.catalog
            }

            vm=this;            
            let url=CI.base_url + '/api/publish/external_resource/'+this.ProjectID +'/' + this.catalog;

            return axios.post(url,
                formData,
                {}            
            );        
        },
        publishProjectThumbnail: async function()
        {
            let formData={
            }

            vm=this;            
            let url=CI.base_url + '/api/publish/thumbnail/'+this.ProjectID +'/' + this.catalog;

            return axios.post(url,
                formData,
                {}            
            );
        },        
        async prepareProjectExport()
        {
            this.project_export_status="Exporting metadata to JSON";
            await this.exportProjectJSON();

            if (this.ProjectType=='survey'){
                this.project_export_status="Exporting metadata to DDI";
                await this.exportProjectDDI();
                //this.project_export_status="Exporting data files";
                //await this.exportProjectDatafiles();
            }
            
            this.project_export_status="Exporting external resources metadata as JSON";
            await this.exportExternalResourcesJSON();
            this.project_export_status="Exporting external resources as RDF/XML ";
            await this.exportExternalResourcesRDF();
            this.project_export_status="Creating project ZIP file";
            await this.writeProjectZip();
            this.project_export_status="done";
        },
        async exportProjectJSON() {
            let url=CI.base_url + '/api/editor/generate_json/'+this.ProjectID;
            return axios
            .get(url)
            .then(function (response) {
                console.log(response);
            })
            .catch(function (error) {
                console.log(error);
            })
            .then(function () {
                console.log("writing JSON done");
            });            
        },
        async exportProjectDDI() {
            let url=CI.base_url + '/api/editor/generate_ddi/'+this.ProjectID;
            return axios
            .get(url)
            .then(function (response) {
                console.log(response);
            })
            .catch(function (error) {
                console.log(error);
            })
            .then(function () {
                console.log("writing DDI done");
            });            
        },
        async exportExternalResourcesJSON() {
            let url=CI.base_url + '/api/resources/write_json/'+this.ProjectID;
            return axios
            .get(url)
            .then(function (response) {
                console.log(response);
            })
            .catch(function (error) {
                console.log(error);
            })
            .then(function () {
                console.log("writing JSON done");
            });            
        },
        async exportExternalResourcesRDF() {
            let url=CI.base_url + '/api/resources/write_rdf/'+this.ProjectID;
            return axios
            .get(url)
            .then(function (response) {
                console.log(response);
            })
            .catch(function (error) {
                console.log(error);
            })
            .then(function () {
                console.log("writing JSON done");
            });            
        },
        async writeProjectZip() {
            let url=CI.base_url + '/api/editor/generate_zip/'+this.ProjectID;
            return axios
            .get(url)
            .then(function (response) {
                console.log(response);
            })
            .catch(function (error) {
                console.log(error);
            })
            .then(function () {
                console.log("writing ZIP done");
            });
        },
        loadCatalogConnections: function() {
            vm=this;
            let url=CI.base_url + '/api/publish/catalog_connections';
            axios.get(url)
            .then(function (response) {
                if(response.data){
                    vm.catalog_connections=response.data.connections;
                }
            })
            .catch(function (error) {
                console.log(error);
            })
            .then(function () {
                console.log("request completed");
            });
        },
        getCollections: function() {
            vm=this;
            this.collections=[];

            if (!this.catalog){
                return;
            }

            let collection=this.catalog_connections[this.catalog];

            if(!collection){
                return;
            }

            let url=collection.url + '/index.php/api/catalog/collections';
            axios.get(url)
            .then(function (response) {
                if(response.data.collections){
                    vm.collections=response.data.collections;
                }
            })
            .catch(function (error) {
                console.log(error);
            });
        },
        getCollectionByID:function(id)
        {
            for (i=0;i<this.catalog_connections.length;i++){
                if (this.catalog_connections[i].id==id){
                    return this.catalog_connections[i];
                }
            }

            return [];
        }
    },
    
    computed: {        
        ProjectID(){
            return this.$store.state.project_id;
        },
        ProjectMetadata(){
            return this.$store.state.formData;
        },
        Datafiles(){
            return this.$store.state.data_files;
        },
        Variables(){
            return this.$store.state.variables;
        },
        ProjectType(){
            return this.$store.state.project_type;
        },
        ExternalResources()
        {
          return JSON.parse(JSON.stringify(this.$store.state.external_resources));
        },
        PublishOptions(){
            let items={};
            vm=this;
            Object.keys(this.publish_options).forEach(function eachKey(key) {                 
                items[key]=vm.publish_options[key]["value"];
            });

            return items;
        }
    },  
    template: `
            <div class="import-options-component mt-5 p-5">
                            
                <h3>{{$t("publish_to_nada")}}</h3>
                <p>{{$t("publish_to_nada_note")}}</p>
                <div>

                <v-card class="p-3 mb-5"
                    elevation="2"
                >
                        <div class="form-group" elevation="10">
                            <label for="catalog_id">{{$t("catalog")}} <router-link class="btn btn-sm btn-link" to="/configure-catalog">{{$t("configure_catalog")}}</router-link></label>
                            <select class="form-control" id="catalog_id" v-model="catalog" @change="getCollections">
                                <option value="">-Select-</option>
                                <option v-for="option in catalog_connections" v-bind:value="option.id">
                                    {{ option.title }} - {{option.url}}
                                </option>                            
                            </select>
                            <div v-if="catalog!=''" class="text-muted">{{getCollectionByID(catalog).url}}</div>                            
                        </div>

                </v-card>

                <v-expansion-panels multiple v-model="panels">
                    <v-expansion-panel>
                        <v-expansion-panel-header>
                            {{$t("project_options")}}
                        </v-expansion-panel-header>
                        <v-expansion-panel-content>

                        <div class="mb-4">
                            
                            <table class="table table-sm table-bordered table-hover table-striped mb-0 pb-0" style="font-size:small;">
                                <tr>
                                    <th>{{$t("option")}}</th>
                                    <th>{{$t("value")}}</th>
                                </tr>
                                <template v-for="(kv,kv_key) in publish_options">                                            
                                <tr v-if="!kv.custom">
                                    <td>
                                        {{kv.title}}
                                        <span v-if="kv_key=='repositoryid'">
                                        <v-icon @click="getCollections">mdi-reload</v-icon>
                                        </span>
                                    </td>
                                    <td>
                                        <input v-if="!kv.enum" type="text" class="form-control" v-model="kv.value"/>
                                        <select v-if="kv.enum" class="form-control" v-model="kv.value">
                                            <option v-for="(enum_val,enum_key) in kv.enum" v-bind:value="enum_key">
                                                {{ enum_val }}
                                            </option>
                                        </select>
                                    </td>
                                </tr>                                            
                                </template>
                                <tr>
                                    <td>{{$t("collection")}}</td>
                                    <td>
                                        <select v-if="collections" class="form-control" v-model="publish_options.repositoryid.value">
                                            <option value="">N/A</option>
                                            <option v-for="(collection,collection_index) in collections" v-bind:value="collection.repositoryid">
                                                [{{ collection.repositoryid }}] {{ collection.title }}
                                            </option>
                                        </select>
                                    </td>

                                </tr>
                                
                            </table>                            
                        </div>
                        </v-expansion-panel-content>
                    </v-expansion-panel>

                    <v-expansion-panel>
                        <v-expansion-panel-header>
                            <div>{{$t("external_resources")}}
                                <div class="text-secondary text-muted text-xs text-small text-normal">{{$t("select_external_resources_to_be_published")}}</div>
                            </div>
                            
                        </v-expansion-panel-header>
                        <v-expansion-panel-content>
                            <div class="mt-3">
                                    <v-switch
                                    v-model="resources_overwrite"
                                    value="yes"
                                    :label="$t('overwrite_resources')"
                                ></v-switch>
                            </div>
                            
                            <div v-if="ExternalResources.length>0" >
                                <div>
                                    <strong>{{ExternalResources.length}}</strong> {{$t("n_resources_found")}}
                                    <span class="ml-2"><strong>{{resources_selected.length}}</strong> {{$t("n_selected")}}</span>
                                </div>
                                <div class="border" style="max-height:300px;overflow:auto;">                    
                                    <table class="table table-sm table-striped">
                                        <thead>
                                        <tr class="bg-light">
                                            <th><input type="checkbox" v-model="toggle_resources_selected" @change="toggleSelectedResources"></th>
                                            <th>{{$t("title")}}</th>
                                            <th>{{$t("type")}}</th>
                                        </tr>
                                        </thead>
                                        <tr v-for="(resource,resource_index) in ExternalResources" :key="resource.id">
                                            <td><input type="checkbox" :value="resource_index" v-model="resources_selected"></td>
                                            <td>
                                                <div>{{resource.title}}</div>
                                                <div class="text-secondary text-small">{{resource.filename}}</div>
                                            </td>
                                            <td>{{resource.dctype}}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <div v-else class="alert alert-warning">
                            {{$t("no_external_resources_found")}}
                            </div>
                        </v-expansion-panel-content>
                    </v-expansion-panel>

                </v-expansion-panels>

                                            
                    <div class=" mb-3 mt-5 switch-control">
                        <div><strong>{{$t("options")}}</strong></div>
                        
                            <v-switch
                                v-model="publish_metadata"
                                :value="true"
                                :label="$t('publish_project')"
                            ></v-switch>
                            
                            <v-switch
                                v-model="publish_thumbnail"
                                :value="true"
                                :label="$t('publish_thumbnail')"
                            ></v-switch>
                            
                            <v-switch
                                v-model="publish_resources"
                                :value="true"
                                :label="$t('external_resources') + (resources_selected.length>0?' ('+resources_selected.length+')':'')"
                            ></v-switch>
                           
                    </div>

                    <button :disabled="!catalog || is_publishing==true" type="button" class="btn btn-primary" @click="publishToCatalog()">{{$t("publish")}}</button>
                </div>

                


                <!-- dialog -->
                <v-dialog v-model="dialog_process" width="500" height="300" persistent>
                    <v-card>
                        <v-card-title class="text-h5 grey lighten-2">
                            <div class="text-h5">{{$t('publish_project')}}</div>
                        </v-card-title>

                        <v-card-text>
                        <div>
                            <!-- card text -->
                            <!-- show-status -->
                            <div v-if="is_publishing">
                                <div class="border p-3 mt-5 mb-5">
                                    <div><strong>Update status</strong></div>
                                    <template>
                                        <div>{{publish_processing_message}}...</div>
                                        <v-progress-linear
                                        indeterminate
                                        color="blue"
                                        ></v-progress-linear>
                                    </template>
                                </div>                        
                            </div>
                            <!-- end show-status --> 
                            
                            <div v-if="is_publishing_completed" class="p-2">

                                <div v-if="publish_metadata==true">
                                    <strong>{{$t('metadata')}}</strong>
                                    <div v-if="publish_responses.metadata.errors.length>0">
                                        <span class="mdi mdi-alert text-danger"></span>
                                        <span>Failed to publish project metadata</span>
                                        <div class="border-bottom m-1 text-danger" v-for="(response,response_index) in publish_responses.metadata.errors">
                                            <div>{{response.message}} - {{response.status}}</div>
                                        </div>    
                                    </div>
                                    <div v-else>
                                        <div class="border m-1 text-success" >
                                            <div>
                                                <span class="mdi mdi-check-circle text-success"></span>
                                                <span>Project metadata updated successfully</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="publish_thumbnail==true" class="mt-5">
                                    <strong>{{$t('thumbnail')}}</strong>
                                    <div v-if="publish_responses.thumbnail.errors.length>0">
                                        <span class="mdi mdi-alert text-danger"></span>
                                        <span>Failed to publish thumbnail</span>
                                        <div class="border m-1 text-danger" v-for="(response,response_index) in publish_responses.thumbnail.errors">
                                            <div>{{response.message}} - {{response.status}}</div>
                                        </div>    
                                    </div>
                                    <div v-if="publish_responses.thumbnail.messages.length>0" >                            
                                        <div class="border-bottom m-1" v-for="message in publish_responses.thumbnail.messages">
                                            <div class="text-success">
                                            <span class="mdi mdi-check-circle text-success"></span> {{message}}
                                            </div>                                
                                        </div>    
                                    </div>                       
                                </div>

                                <div v-if="resources_selected.length>0" class="mt-5">
                                    <strong>External resources</strong>
                                    <div v-if="publish_responses.external_resources.messages.length>0" >                            
                                        <div class="border-bottom m-1" v-for="message in publish_responses.external_resources.messages">
                                            <div>
                                            <span class="mdi mdi-check-circle text-success"></span> {{message}}
                                            </div>                                
                                        </div>    
                                    </div>
                                    <div v-if="publish_responses.external_resources.errors.length>0" >
                                        <div class="border-bottom m-1" v-for="(response,response_index) in publish_responses.external_resources.errors">
                                            <div><span class="mdi mdi-alert text-danger"></span>
                                            {{response.resource_title}}</div>
                                            <div class="text-danger">Error: {{response.error.data.message}}</div>                            
                                        </div>    
                                    </div>
                                </div>                    

                            </div>
                            
                            <!-- end card text -->
                        </div>
                        </v-card-text>

                        <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn color="primary" text @click="dialog_process=false" v-if="is_publishing==false">
                        {{$t('close')}}
                        </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>
                <!-- end dialog -->



                
            </div>          
            `    
});

