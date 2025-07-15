//metadata type edit component
const VueMetadataTypeEdit = Vue.component('metadata-types-edit', {
    props: ['index', 'id'],
    data() {
        return {
            form_valid:false,
            MetadataType:{},
            MetadataTemplate:{},
            metadata_model:{},
            metadata_info:{},
            is_dirty:false,
            is_metadata_loading:false,
        }
    }, 
    mounted () {
        this.is_metadata_loading=true;
        this.loadMetadataTypeSchema();
        this.is_metadata_loading=false;
    },
    watch: {
        metadata_model: {
            handler: function (val, oldVal) {                
                if (this.is_metadata_loading){return;}
                //console.log("metadata changed", JSON.stringify(val), JSON.stringify(oldVal));
                if (_.isEmpty(oldVal)){return;}
                this.is_dirty=true;
            },
            deep: true
        }
    },
    methods: {
        onModelInput: function(val){
            console.log("onModelInput", val);
            this.metadata_model=val;
        },
        ObjectToKeyValueArray(data){
            if (_.isEmpty(data)){
                return [{}];
            }

            let arr=[];
            for (let key in data){
                arr.push({key:key,value:data[key]});
            }
            return arr;
        },
        ArrayToObject(data){
            if (_.isEmpty(data)){
                return {};
            }

            let obj={};
            for (let key in data){
                obj[data[key].key]=data[key].value;
            }
            return obj;
        },

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

       loadMetadataTypeSchema: function(){
            vm=this;
            let url=CI.base_url + '/api/admin-metadata/type/'+ this.MetadataTypeNameParam;
            axios.get( url
            ).then(function(response){
                console.log("MetadataType",response.data);
                vm.MetadataType=response.data;
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
            let url=CI.base_url + '/api/admin-metadata/data/'+ this.ProjectID + '/'+this.MetadataType.name;
            axios.get( url
            ).then(function(response){
                if (response.data && response.data.metadata){
                    if (vm.isEmptyObject(response.data.metadata)){
                        vm.metadata_model={};
                    }else{
                        vm.metadata_info=response.data;
                        vm.metadata_model=response.data.metadata;
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
            if (!confirm(this.$t("confirm_delete_metadata"))){
                return;
            }

            let json_data={
                'project_id': this.ProjectIDNO,
                'metadata_type_name': this.MetadataType.name
            };

            let url=CI.base_url + '/api/admin-metadata/data_remove/';
            axios.post( url, json_data
            ).then(function(response){
                vm.metadata_model={};
                alert(vm.$t("metadata_deleted"));
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
                'project_id': this.ProjectIDNO,
                'metadata_type_name': this.MetadataType.name
            };
            axios.post( url, json_data)
            .then(function(response){
                alert(vm.$t("saved"));
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
        MetadataTypeNameParam(){
            return this.$route.params.type_id;
        },
        TemplateFromSchema(){
            //convert schema to template

        },
        MetadataTypeSchema(){
            if (this.MetadataType.metadata_schema && this.MetadataType.metadata_schema.schema){
                return this.MetadataType.metadata_schema.schema;
            }
        },
        MetadataTypeHasEditAccess(){
            if (this.MetadataType && this.MetadataType.permissions){                
                if (this.MetadataType.permissions.indexOf('edit') > -1 || this.MetadataType.permissions.indexOf('admin') > -1){
                    return true;
                }
            }
            return false;
        }        
    },
    template: `
        <div class="metadata-types-edit-container container-fluid">

        <section style="display: flex; flex-flow: column;height: calc(100vh - 140px);" v-if="MetadataType">

            <v-card class="mt-4 mb-2">                    
                    <v-card-title class="d-flex justify-space-between">                    
                        <div style="font-weight:normal">{{$t("Edit")}} - <span v-if="MetadataTypeSchema">{{MetadataType.title}}</span></div>

                        <div v-if="MetadataTypeHasEditAccess">
                            <v-btn  small outlined color="red" class="mr-5" @click="deleteMetadata" >{{$t("delete")}}</v-btn>
                            <v-btn color="primary" small @click="saveMetadata" >{{$t("save")}} <span v-if="is_dirty">*</span></v-btn>
                            <v-btn  small>{{$t("cancel")}}</v-btn>
                        </div>
                        <div v-else>
                            <v-btn  small outlined color="red">{{$t("read_only")}}</v-btn>
                        </div>
                    </v-card-title>
                </v-card>

            <v-card style="flex: 1;overflow:auto;">
            <v-card-text class="mb-5" >
            <v-row>
                <v-col md="6">
                <div v-if="MetadataTypeSchema" >

                    <div v-if="MetadataTypeSchema.type=='object' && !MetadataTypeSchema.properties">                        
                        <schema-object-field class="border mb-5" :field="MetadataTypeSchema" v-model="metadata_model" :is_readonly="!MetadataTypeHasEditAccess" ></schema-object-field>
                    </div>

                    <div v-if="MetadataTypeSchema.type=='array'">
                        <schema-array-field class="border mb-5" :field="MetadataTypeSchema" v-model="metadata_model"></schema-array-field>
                    </div>    

                    <div v-for="(field, field_key) in MetadataTypeSchema.properties">
                    <v-row>
                        <v-col>
                            <div class="font-weight-bold">{{field.title}}</div>
                            <div v-if="field.type=='array'">
                                <schema-array-field v-if="field.items && field.items.type=='object'" class="border mb-5" :field="field" :is_readonly="!MetadataTypeHasEditAccess" v-model="metadata_model[field_key]"></schema-array-field>
                                <div v-else if ="field.items && field.items.type!=='object'">
                                    <div v-if="field && field.items && field.items.type!=='object'">
                                            <repeated-field
                                                    v-model="metadata_model[field_key]"
                                                    :field="field"
                                                    :key="field_key"
                                                    :is_readonly="!MetadataTypeHasEditAccess"
                                                >
                                            </repeated-field>
                                    </div>
                                </div>
                                <div v-else class="text-danger border p-2">
                                    <div class="font-weight-bold">{{field.type}} not implemented</div>
                                    <pre>{{field}}</pre>
                                </div>
                                
                            </div>
                            <div v-else>
                                <div v-if="field.enum">
                                    <v-select :disabled="!MetadataTypeHasEditAccess" dense clearable outlined v-model="metadata_model[field_key]" :items="field.enum" label="" ></v-select>
                                </div>
                                <div v-else>
                                    <v-text-field :disabled="!MetadataTypeHasEditAccess" clearable dense outlined v-model="metadata_model[field_key]" label=""  ></v-text-field>
                                </div>
                                <p class="text-muted">{{field.description}}</p>
                            </div>
                        </v-col>
                    </v-row>
                    </div>
                    
                </div>
                </v-col>
                <v-col md="6">
                    <div class="text-secondary border p-2">
                        <div class="border-bottom p-2 mb-5">
                            <strong>Name:</strong> {{MetadataType.name}}<br/>
                            <strong>Title:</strong> {{MetadataType.title}} <br/>
                            <strong>Description:</strong> {{MetadataType.description}}<br/>
                            <strong>Created:</strong> {{momentDateUnix(metadata_info.created)}}<br/>
                            <span v-if="metadata_info.changed"> 
                            <strong>Updated:</strong> {{momentDateUnix(metadata_info.changed)}}
                            </span>

                            <br/>
                            <span v-if="metadata_info.cr_username">
                            <strong>Created By:</strong> {{metadata_info.cr_username}}
                            </span>

                            <br/>
                            <span v-if="metadata_info.ch_username">
                            <strong>Updated By:</strong> {{metadata_info.ch_username}}
                            </span>
                            
                        </div>

                        <div>Metadata</div>
                        <pre>{{metadata_model}}</pre>
                    </div>
                </v-col>
            </v-row>
            
            </v-card-text>
            </v-card>
        </section>


        </div>
    `
})


