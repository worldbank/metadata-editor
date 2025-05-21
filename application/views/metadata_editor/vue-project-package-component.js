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
            let url=CI.base_url + '/api/packager/download_zip/'+this.ProjectID;
            window.open(url, '_blank');
        },
        async prepareProjectExport()
        {
            this.project_export_status=this.$t("exporting_to_json");
            await this.exportProjectJSON();

            if (this.ProjectType=='survey'){
                this.project_export_status=this.$t("exporting_ddi");
                await this.exportProjectDDI();
                //this.project_export_status="Exporting data files";
                //await this.exportProjectDatafiles();
            }
            
            this.project_export_status=this.$t("processing_please_wait");
            await this.exportExternalResourcesJSON();
            await this.exportExternalResourcesRDF();
            this.project_export_status= this.$t("writing_zip");
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
            let url=CI.base_url + '/api/packager/generate_zip/'+this.ProjectID;
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
            <div class="import-options-component p-3 mt-5">
            
                <v-card>
                    <v-card-title>
                    {{$t("project_package")}}
                    </v-card-title>
                    <v-card-text>
                        <div class="mb-3">{{$t("project_package_note")}}</div>
                        <v-btn color="primary" :disabled="project_export_status!=''" @click="downloadZip()">{{$t("download_zip_package")}}</v-btn>
                        <span v-if="project_export_status!='done' && project_export_status!=''"><i class="fas fa-circle-notch fa-spin"></i> {{project_export_status}}</span>
                    </v-card-text>
                </v-card>
                

            </div>          
            `    
});

