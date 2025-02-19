//admin metadata selection dialog
Vue.component('vue-dialog-admin-metadata-component', {
    props:['value'],
    data() {
        return {            
            admin_metadata_templates:[],
            errors: '',
        }
    }, 
    mounted: function () {
        this.loadAdminMetadataTemplates();
    },      
    methods: {
        loadAdminMetadataTemplates: function(){
            vm=this;
            let url=CI.base_url + '/api/admin-metadata/templates_by_project/' + this.ProjectID;
            axios.get( url
            ).then(function(response){
                console.log("MetadataType",response.data);
                vm.admin_metadata_templates=response.data;
            })
            .catch(function(response){
                vm.errors=response;
                //alert("Failed: " + vm.erorrMessageToText(response));
                console.log("failed", response);
            });            
        },
        enableAdminMetadata: function(template_uid){
            vm=this;            
            let url=CI.base_url + '/api/admin-metadata/attach/';
            let json_data={
                'project_id': this.ProjectID,
                'template_uid': template_uid
            };
            
            axios.post( url, json_data
            ).then(function(response){
                alert("Metadata enabled");
            })
            .catch(function(response){
                vm.errors=response;
                console.log("failed", response);
            });            
        },
        disableAdminMetadata: function(template_uid){
            vm=this;
            let url=CI.base_url + '/api/admin-metadata/detach/';
            let json_data={
                'project_id': this.ProjectID,
                'template_uid': template_uid
            };
            axios.post( url, json_data
            ).then(function(response){
                alert("Metadata removed");
            })
            .catch(function(response){
                vm.errors=response;
                console.log("failed", response);
            });
        },
        toggleMetadata: function(template){
            vm=this;
            if (template.is_active){
                vm.enableAdminMetadata(template.uid);
            }else{
                vm.disableAdminMetadata(template.uid);
            }
        },
        dialogClose: function(){
            //emit
            this.$emit('dialog-close', true);
            this.dialog=false;
        },
    },
    computed: {
        dialog: {
            get () {
                return this.value
            },
            set (val) {
                this.$emit('input', val)
            }
        },
        ProjectID(){
            return this.$store.state.project_id;
        },
        ProjectIDNO(){
            return this.$store.state.idno;
        },
        AdminMetadataTemplates(){
            if (this.admin_metadata_templates && this.admin_metadata_templates.result){
                //loop result and add field 'is_active' if `is_enabled` or 'has_data' is true
                let result=this.admin_metadata_templates.result;
                for (let i=0; i<result.length; i++){
                    let template=result[i];
                    if (template.is_enabled || template.has_data){
                        template.is_active=true;
                    }
                }
                return result;
            }

            return [];
        },
    },
    template: `
        <div class="vue-dialog-component">

            <!-- dialog -->
            <v-dialog v-model="dialog" width="500" height="300" persistent scrollable>
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        {{$t("administrative_metadata_templates")}}
                    </v-card-title>

                    <v-card-text>

                        <div v-if="AdminMetadataTemplates.length==0">
                            
                            <div class="text-start">
                                {{$t("No metadata templates available")}}
                            </div>
                        </div>

                        <v-simple-table>
                            <template v-slot:default>
                                <tbody>
                                    <tr v-for="template in AdminMetadataTemplates">
                                        <td>
                                            <v-checkbox @click="toggleMetadata(template)" color="blue" v-model="template.is_active" :disabled="template.has_data"></v-checkbox>
                                        </td>
                                        <td>
                                            <div>{{template.name}}</div>
                                            <div class="text-muted">{{template.description}}</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </template>
                        </v-simple-table>
                    </v-card-text>

                    <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="primary" text @click="dialogClose">
                        Close
                    </v-btn>
                    </v-card-actions>
                </v-card>
                </v-dialog>
            <!-- end dialog -->
        
        </div>
    `
});

