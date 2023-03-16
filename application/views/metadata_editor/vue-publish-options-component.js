/// import options
Vue.component('publish-options', {
    props:['value'],
    data: function () {    
        return {
            field_data: this.value,
            resources_selected:[],
            toggle_resources_selected:false,
            resources_overwrite:"no",
            catalog_connections:[],
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
            publish_processing_status:false,
            publish_processing:'',
            publish_errors:[],
            publish_messages:[],
            publish_response:{},
            file:'',
            update_status:'',
            errors:'',
            is_processing:false,
            project_export_status:'',
            collections:[]
        }
    },
    created: async function(){
        this.loadCatalogConnections();
    },
    methods:{
        toggleSelectedResources: function(){
            this.resources_selected = [];
            if (this.toggle_resources_selected == true) {                
                for (i = 0; i < this.ExternalResources.length; i++) {
                this.resources_selected.push(i);
                }
            }
        },
        publishToCatalog: async function(){
            let formData=this.PublishOptions;
            vm=this;
            await this.prepareProjectExport();
            await this.publishProjectMetadata();
            await this.publishProjectThumbnail();
            await this.publishExternalResoures();
            console.log("publish done");
            //await this.publishExternalResourcesFiles();
        },
        publishProjectMetadata: async function(){
            let formData=this.PublishOptions;
            vm=this;            
            this.publish_processing="Publishing study to the catalog...";
            this.publish_messages=[];
            this.publish_errors=[];
            this.publish_response={};
            this.publish_processing_status=true;
            let url=CI.base_url + '/api/publish/' +this.ProjectID +'/' + this.catalog;
        
            return axios.post(url,
                formData,
                {}
            ).then(function(response){
                vm.publish_response=response;
                vm.publish_processing="Publishing completed";
                console.log("published done", response);
                window.response_=response;
                vm.publish_messages.push(response.data);
                vm.publish_processing_status=false;
            })
            .catch(function(error){
                console.log("published failed", error);
                vm.publish_processing="Publishing failed";
                vm.publish_errors.push(error.response.data);
                vm.publish_processing_status=false;
            }); 
        },
        publishExternalResoures:  async function() 
        {
            try{
                if (this.resources_selected.length==0){
                    return;
                }

                let formData=this.PublishOptions;
                vm=this;
                this.publish_processing="Publishing external resources...";
                this.publish_messages=[];
                this.publish_errors=[];
                this.publish_response={};
                this.publish_processing_status=true;

                for(i=0;i<this.resources_selected.length;i++){
                    console.log("publishing resource",this.resources_selected, i)

                    try {
                        const { data } = await vm.publishSingleResource(this.ExternalResources[this.resources_selected[i]]);
                        console.log(`Request ${i+1}:`, data);
                      } catch (error) {
                        console.error(`Request ${i+1} failed:`, error.message);
                    }

                    console.log("publishing resource result");
                }

                console.log("completed all resources");
                this.publish_processing="Publishing external resources completed";
                this.publish_processing="";
                this.publish_processing_status=false;
            }
            catch(error){
                console.log("publishExternalResoures failed", error, error.response);
                vm.publish_processing="Publishing external resources failed";
                vm.publish_errors.push(error.response);
                vm.publish_processing_status=false;
            }
        },
        publishSingleResource: async function(resource)
        {
            let formData={
                "overwrite": "no",
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
            alert("start");
            let formData={
            }

            vm=this;            
            let url=CI.base_url + '/api/publish/thumbnail/'+this.ProjectID +'/' + this.catalog;

            return axios.post(url,
                formData,
                {}            
            );        
        },

        publishExternalResourcesFiles: async function()
        {
            let formData=this.PublishOptions;
            vm=this;
            this.publish_processing="Uploading resources files...";
            this.publish_messages=[];
            this.publish_errors=[];
            this.publish_response={};
            this.publish_processing_status=true;
            let url=CI.base_url + '/api/publish/external_resources_files/'+this.ProjectID +'/' + this.catalog;

            return axios.post(url,
                formData,
                {}
            ).then(function(response){
                vm.publish_response=response;
                vm.publish_processing="Uploading external resources completed";
                vm.publish_messages.push(response.data);
                vm.publish_processing_status=false;
            })
            .catch(function(error){
                console.log("failed to add external resources", error);
                vm.publish_processing="Publishing external resources failed";
                vm.publish_errors.push(error.response.data);
                vm.publish_processing_status=false;
            }); 
        },

        /*publishToCatalog: function(){
            //let formData = new FormData();
            let formData=this.ProjectMetadata;
            //formData.append('fileid', this.file_id);
            //formData.append("filename",this.file.name)

            vm=this;

            let url=this.catalog.url + 'index.php/api/datasets/create/survey';
        
            return axios.post(url,
                formData,
                {
                    headers: {
                        'x-api-key': vm.catalog.api_key
                    }
                }
            ).then(function(response){
                console.log("published done", response);
            })
            .catch(function(response){
                console.log("published failed", response);
            }); 
        },
        async processPublishToCatalog(){
            //publish study level metadata
            await this.publishStudyMetadata();

            if (this.ProjectType=='survey'){
                //publish data files
                await this.publishDatafiles();
            //publish variables
            }

            //external resources
            //upload external resources files
        },
        publishStudyMetadata: function(){
            let formData=this.ProjectMetadata;
            vm=this;

            let url=this.catalog.url + 'index.php/api/datasets/create/'+this.projectType;
        
            return axios.post(url,
                formData,
                {
                    headers: {
                        'x-api-key': vm.catalog.api_key
                    }
                }
            ).then(function(response){
                console.log("published done", response);
            })
            .catch(function(response){
                console.log("published failed", response);
            }); 
        },
        publishDatafiles: function(){
            let formData=this.ProjectMetadata;
            vm=this;

            let url=this.catalog.url + 'index.php/api/datafiles/';
        
            return axios.post(url,
                formData,
                {
                    headers: {
                        'x-api-key': vm.catalog.api_key
                    }
                }
            ).then(function(response){
                console.log("published done", response);
            })
            .catch(function(response){
                console.log("published failed", response);
            }); 
        },*/
        
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
            let url=CI.base_url + '/api/editor/write_resources_json/'+this.ProjectID;
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
            let url=CI.base_url + '/api/editor/write_resources_rdf/'+this.ProjectID;
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
                            

                <h3>Publish project</h3>
                <p>Publish project directly to a NADA catalog</p>
                <div>

                        <div class="form-group">
                            <label for="catalog_id">Select Catalog <router-link class="btn btn-sm btn-link" to="/configure-catalog">Configure new catalog</router-link></label>
                            <select class="form-control" id="catalog_id" v-model="catalog" @change="getCollections">
                                <option value="">-Select-</option>
                                <option v-for="option in catalog_connections" v-bind:value="option.id">
                                    {{ option.title }} - {{option.url}}
                                </option>                            
                            </select>
                            <div v-if="catalog!=''" class="text-muted">{{getCollectionByID(catalog).url}}</div>                            
                        </div>

                        <div class="mb-4">
                        <label>Options</label>
                        <table class="table table-sm table-bordered table-hover table-striped mb-0 pb-0" style="font-size:small;">
                            <tr>
                                <th>Option</th>
                                <th>Value</th>
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
                                <td>Collection</td>
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
                            <!-- <button type="button" class="btn btn-sm btn-link" @click="AddKvRow">Add row</button> -->
                        </div>
                    
                <h3 class="mt-5">External resources</h3>
                <div class="mt-3">
                        <v-switch
                        v-model="resources_overwrite"
                        value="yes"
                        label="Overwrite resources"
                    ></v-switch>
                </div>
                
                <div v-if="ExternalResources.length>0" >
                    <div>
                        <strong>{{ExternalResources.length}}</strong> resources found
                        <span class="ml-2"><strong>{{resources_selected.length}}</strong> selected</span>
                    </div>
                    <div class="border" style="max-height:300px;overflow:auto;">                    
                        <table class="table table-sm table-striped">
                            <thead>
                            <tr class="bg-light">
                                <th><input type="checkbox" v-model="toggle_resources_selected" @change="toggleSelectedResources"></th>
                                <th>Title</th>
                                <th>Type</th>
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
                    No external resources found
                </div>


                    <div class=" mb-3"></div>

                    <button :disabled="publish_processing_status==true" type="button" class="btn btn-primary" @click="publishToCatalog()">Publish</button>

                    <div v-if="publish_processing!=''">
                        <div class="border p-3 mt-5 mb-5">
                            <div><strong>Update status</strong></div>
                            <template>
                                <div>{{publish_processing}}...</div>
                                <v-progress-linear
                                indeterminate
                                color="green"
                                ></v-progress-linear>
                            </template>
                        </div>                        
                    </div>

                    <div v-if="publish_messages.length>0" style="color:green">
                        {{publish_messages}}
                    </div>

                    <div v-if="publish_errors.length>0" style="color:red">
                        {{publish_errors}}
                    </div>
                    
                </div>

                
            </div>          
            `    
});

