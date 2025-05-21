//Edit admin metadata
const VueAdminMetadataEdit = Vue.component('admin-metadata-edit', {
    props: ['index', 'id'],
    data() {
        return {
            form_valid:false,
            is_dirty:false,
            is_metadata_loading:false,
            metadata_template:{},

            metadata_model:{},
            metadata_info:{},            
        }
    }, 
    mounted () {
        this.is_metadata_loading=true;
        this.loadMetadataTemplate();
        this.is_metadata_loading=false;
    },
    watch: {
        metadata_model: {
            handler: function (val, oldVal) {                
                if (this.is_metadata_loading){return;}
                if (_.isEmpty(oldVal)){return;}
                this.is_dirty=true;
            },
            deep: true
        }
    },
    methods: {        
        localValue: function(key){
            return _.get(this.metadata_model,key);
        },
        updateModelJson: function(val){
            this.$set(this,'metadata_model',val);
        },
        update: function (key, value){
            if (key.indexOf(".") !== -1 && this.metadata_model[key]){
                delete this.metadata_model[key];
            }

            _.set(this.metadata_model,key,value);
            this.metadata_model= _.cloneDeep(this.metadata_model);//need this to trigger reactivity
        },
        updateSection: function (obj){
            this.update(obj.key,obj.value);
        },
        findTemplateByItemKey: function (items,key){
            let item=null;
            let found=false;
            let i=0;

            while(!found && i<items.length){                
                if (items[i].key==key){
                    item=items[i];
                    found=true;
                }else{
                    if (items[i].items){
                        item=this.findTemplateByItemKey(items[i].items,key);
                        if (item){
                            found=true;
                        }
                    }
                }
                i++;                        
            }
            return item;
        },
        /*onModelInput: function(val){
            console.log("onModelInput", val);
            this.metadata_model=val;
        },*/

        momentDateUnix(date) {
            if (!date){
                return '';
            }

            return moment.unix(date).format("YYYY-MM-DD H:mm:ss");
        },
        removeEmpty: function(obj) {
            for (let key in obj){
                if (_.isEmpty(obj[key])){
                    delete obj[key];
                }
            }
        },
        removeEmptyValues: function (obj) {
            if (typeof obj !== "object" || obj === null) {
              return obj;
            }

            let vm=this;
          
            const newObj = Array.isArray(obj) ? [] : {};
          
            for (const key in obj) {
              if (obj.hasOwnProperty(key)) {
                const value = vm.removeEmptyValues(obj[key]);
                if (value !== null && value !== undefined && !vm.isEmptyObject(value) && value !== "") {
                  newObj[key] = value;
                }
              }
            }
          
            return newObj;
          },
          
          isEmptyObject: function(obj) {
            return typeof obj === "object" && Object.keys(obj).length === 0;
          },

       
        loadMetadataTemplate: function(){
            vm=this;
            let url=CI.base_url + '/api/admin-metadata/templates/'+ this.MetadataTypeNameParam;
            axios.get( url
            ).then(function(response){
                vm.metadata_template=response.data;
                vm.loadMetadata();
            })
            .catch(function(response){
                vm.errors=response;
                //alert("Failed: " + vm.erorrMessageToText(response));
                console.log("failed", response);
            });            
        },
        loadMetadata: function(){
            vm=this;
            this.is_metadata_loading=true;
            let url=CI.base_url + '/api/admin-metadata/data/'+ this.ProjectID + '/'+this.MetadataTemplateRaw.uid;
            axios.get( url
            ).then(function(response){
                if (response.data && response.data.data && response.data.data.metadata){
                    if (vm.isEmptyObject(response.data.data.metadata)){
                        vm.metadata_model={};
                    }else{
                        vm.metadata_info=response.data.data;
                        vm.metadata_model=response.data.data.metadata;
                    }
                    vm.is_dirty=false;                    
                }
                vm.is_metadata_loading=false;
            })
            .catch(function(response){
                vm.errors=response;
                vm.is_dirty=false;
                vm.is_metadata_loading=false;
                //alert("Failed: " + vm.erorrMessageToText(response));
                console.log("failed", response);
            });
        },
        deleteMetadata: function(){
            vm=this;
            if (!confirm("Are you sure you want to delete this metadata?")){
                return;
            }

            let json_data={
                'project_id': this.ProjectID,
                'template_uid': this.MetadataTemplateRaw.uid
            };

            let url=CI.base_url + '/api/admin-metadata/data_remove/';
            axios.post( url, json_data
            ).then(function(response){
                vm.metadata_model={};
                alert("Metadata deleted");
                vm.$router.push({name: 'metadata-types'});
            })
            .catch(function(response){
                vm.errors=response;
                alert("Failed: " + vm.erorrMessageToText(response));
            });
        },
        saveMetadata:function(){
            vm=this;
            let url=CI.base_url + '/api/admin-metadata/data/';

            let json_data={
                'metadata': this.removeEmptyValues(this.metadata_model),
                'project_id': this.ProjectID,
                'template_uid': this.MetadataTemplateRaw.uid
            };
            axios.post( url, json_data)
            .then(function(response){
                alert("Saved");
                vm.is_dirty=false;
                vm.loadMetadata();
            })
            .catch(function(response){
                vm.errors=response;
                alert("Failed: " + vm.erorrMessageToText(response));
            });
        },


        erorrMessageToText: function(error){
            let error_text = '';
            if (error.response.data.errors) {
                for (let key in error.response.data.errors) {
                    error_text += error.response.data.errors[key] + '\n';
                }
            } else {
                error_text = error.response.data.message;
            }
            return error_text;
        },
    },
    computed: {        
        ProjectID(){
            return this.$store.state.project_id;
        },
        ProjectIDNO(){
            return this.$store.state.idno;
        },
        isProjectEditable(){
            return this.$store.getters.getUserHasEditAccess;
        },
        MetadataTemplateUidParam(){
            return this.$route.params.type_id;
        },
        MetadataTypeNameParam(){
            return this.$route.params.type_id;
        },
        MetadataTemplateRaw(){
            if (this.metadata_template && this.metadata_template.result){
                return this.metadata_template.result;
            }

            return {};
        },
        
        MetadataTemplate(){
            let key='metadata_container';
            let items=[]
            if (this.metadata_template && this.metadata_template.result && this.metadata_template.result.template && this.metadata_template.result.template.items){
                items= this.metadata_template.result.template.items;
            }
            
            let item=this.findTemplateByItemKey(items,key);
            return item;        
        },
        MetadataTemplateACL(){
            console.log("MetadataTemplateRaw", this.MetadataTemplateRaw);
            if (this.MetadataTemplateRaw && this.MetadataTemplateRaw.permissions){
                return this.MetadataTemplateRaw.permissions;
            }
            return [];
        },
        
        HasEditAccess(){            
            if (this.MetadataTemplateRaw && this.MetadataTemplateRaw.permissions){
                let permissions=this.MetadataTemplateRaw.permissions;
                for (let i=0; i<permissions.length; i++){
                    let perm=permissions[i];
                    if (perm.permissions=='edit' || perm.permissions=='admin'){
                        return true;
                    }
                }
            }
            return false;
        }        
    },
    template: `
        <div class="admin-metadata-edit-container container-fluid">
        
        <section style="display: flex; flex-flow: column;height: calc(100vh - 140px);" v-if="MetadataTemplate">

            <v-card class="mt-4 mb-2">                    
                    <v-card-title class="d-flex justify-space-between">                    
                        <div style="font-weight:normal">{{$t("Edit")}} - <span v-if="MetadataTemplateRaw">{{MetadataTemplateRaw.name}}</span></div>

                        <div v-if="HasEditAccess">
                            <v-btn  small outlined color="red" class="mr-5" @click="deleteMetadata" >Delete</v-btn>
                            <v-btn color="primary" small @click="saveMetadata" >{{$t("Save")}} <span v-if="is_dirty">*</span></v-btn>
                            <v-btn  small>Cancel</v-btn>
                        </div>
                        <div v-else>
                            <v-btn  small outlined color="red">READ-ONLY</v-btn>
                        </div>
                    </v-card-title>
                </v-card>

            <v-card style="flex: 1;overflow:auto;">
            <v-card-text class="mb-5" >
            <v-row>
                <v-col md="8">
                <div  v-for="(column,idx_col) in MetadataTemplate.items" scope="row" :key="column.key"  >            
                    <template v-if="column.type=='section'">
                    
                        <form-section
                            :parentElement="metadata_model"
                            :value="localValue(column.key)"
                            :columns="column.items"
                            :title="column.title"
                            :path="column.key"
                            :field="column"                            
                            @sectionUpdate="updateSection($event)"
                        ></form-section>  
                    
                    </template>
                    <template v-else>

                        <form-input
                            :value="localValue(column.key)"
                            :field="column"
                            @input="update(column.key, $event)"
                        ></form-input>                              
                        
                    </template>
                </div>
              
                </v-col>
                <v-col md="4">
                    <div class="text-secondary border-left mt-2 p-2">
                        <div class="border-bottom p-2 mb-5">
                            <strong>UID:</strong> {{MetadataTemplateRaw.uid}}<br/>
                            <strong>Name:</strong> {{MetadataTemplateRaw.name}} <br/>                            
                            <strong>Created:</strong> {{momentDateUnix(metadata_info.created)}}<br/>
                            <span v-if="metadata_info.changed"> 
                                <strong>Updated:</strong> {{momentDateUnix(metadata_info.changed)}}<br/>
                            </span>
                            <span v-if="metadata_info.cr_username">
                            <strong>Created By:</strong> {{metadata_info.cr_username}}<br/>
                            </span>

                            <span v-if="metadata_info.ch_username">
                            <strong>Updated By:</strong> {{metadata_info.ch_username}}
                            </span>
                            
                        </div>

                        <div>Metadata</div>
                        <json-edit :value="metadata_model" @input="updateModelJson($event)" ></json-edit>                        
                    </div>
                </v-col>
            </v-row>
            
            </v-card-text>
            </v-card>
        </section>


        </div>
    `
})


