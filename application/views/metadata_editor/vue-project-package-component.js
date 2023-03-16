/// project export package component
Vue.component('project-package', {
    props:['value'],
    data: function () {    
        return {
            field_data: this.value,
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
    },
    methods:{
        downloadZip: function()
        {
            this.exportProjectMetadata();            
        },        
        async exportProjectMetadata()
        {
            await this.prepareProjectExport();

            //download
            let url=CI.base_url + '/api/editor/download_zip/'+this.ProjectID;
            window.open(url, '_blank');
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
        }
    },  
    template: `
            <div class="import-options-component mt-5">
            
                <h3>Project package</h3>
                                
                <div>Create a zip package with all documentation</div>

                <button :disabled="project_export_status!=''" type="button" class="mt-3 btn btn-primary" @click="downloadZip()">Download zip package</button>
                <span v-if="project_export_status!='done' && project_export_status!=''"><i class="fas fa-circle-notch fa-spin"></i> {{project_export_status}}</span>


            </div>          
            `    
});

