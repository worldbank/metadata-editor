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
            project_export_error:'',
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
            this.project_export_error = '';
            this.project_export_status = this.$t('processing_please_wait');

            const url = CI.base_url + '/api/packager/generate/' + this.ProjectID;

            try {
                const response = await axios.post(url);

                if (!response.data || response.data.status !== 'success') {
                    const message = response.data && response.data.message
                        ? response.data.message
                        : this.$t('package_export_failed');
                    throw new Error(message);
                }

                const downloadUrl = CI.base_url + '/api/packager/download_zip/' + this.ProjectID;
                window.open(downloadUrl, '_blank');
            } catch (error) {
                this.project_export_error = this.packageExportErrorMessage(error);
            } finally {
                this.project_export_status = '';
            }
        },
        packageExportErrorMessage: function(error)
        {
            const status = error.response && error.response.status;
            if (status === 504 || status === 502 || status === 503) {
                return this.$t('package_export_server_timeout');
            }

            if (error.response && error.response.data) {
                const data = error.response.data;
                if (typeof data === 'string' && data.trim() !== '') {
                    return data;
                }
                if (data.message) {
                    return data.message;
                }
            }

            if (error.message) {
                return error.message;
            }

            return this.$t('package_export_failed');
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
                        <span v-if="project_export_status!=''"><i class="fas fa-circle-notch fa-spin"></i> {{project_export_status}}</span>
                        <div v-if="project_export_error" class="error--text mt-2">{{project_export_error}}</div>
                    </v-card-text>
                </v-card>
                

            </div>          
            `    
});

