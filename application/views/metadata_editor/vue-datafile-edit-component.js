/// datafile add/edit form
const VueDatafileEdit= Vue.component('datafile-edit', {
    data: function () {    
        return {
            form_local: {},
            is_dirty: false,
            is_loading: true
        }
    },
    mounted: function () {
        window.onbeforeunload = function () {
            if (this.is_dirty) {
                return "You have unsaved changes. Are you sure you want to leave?";
            }
        }.bind(this);
        
        this.loadFile();
    },
    beforeRouteLeave(to, from, next) {
        if (!this.showUnsavedMessage()){
            return false;
        }
        next();
    },
    beforeRouteUpdate(to, from, next) {
        if (!this.showUnsavedMessage()){
            return false;
        }
        next();
    },
    watch: {
        form_local: {
            handler: function (newVal, oldVal) {                
                if (this.is_loading){return;}
                if (!oldVal.file_id){return;}                
                this.is_dirty=true;
            },
            deep: true
        },
    },
    methods:{
        showUnsavedMessage: function(){
            if (this.is_dirty){
                if (!confirm("You have unsaved changes. Are you sure you want to leave this page?")){
                    return false;
                }
            }
            return true;
        },
        saveForm: function (){    
            this.saveFile();
        },
        cancelForm: function (){
            if (this.is_dirty){
                if (!confirm("You have unsaved changes. Are you sure you want to leave this page?")){
                    return false;
                }
            }
            this.is_dirty=false;
            router.push('/datafiles/');
        },
        loadFile: function(){
            //load file data
            vm=this;
            this.is_loading=true;
            let url=CI.base_url + '/api/datafiles/'+ this.ProjectID + '/' + this.ActiveDatafileIndex;
            axios.get( url
            ).then(function(response){
                vm.form_local=response.data.datafile;
                vm.is_loading=false;
            })
            .catch(function(response){
                vm.errors=response;
            });
        },
        saveFile: function(){
            vm=this;
            let url=CI.base_url + '/api/datafiles/'+ this.ProjectID;
            axios.post( url,
                this.form_local
            ).then(function(response){
                vm.$store.dispatch('loadDataFiles',{dataset_id:vm.ProjectID});
                vm.is_dirty=false;
                router.push('/datafiles/');
            })
            .catch(function(response){
                vm.errors=response;
            });
        }
    },
    computed:{
        ActiveDatafileIndex(){
            return this.$route.params.file_id;
        },
        ProjectID(){
            return this.$store.state.project_id;
        }        
    },  
    template: `
            <div class="datafile-edit-component container-fluid" >


            <section style="display: flex; flex-flow: column;height: calc(100vh - 140px);">
             
            
                <v-card class="mt-4 mb-2">                    
                    <v-card-title class="d-flex justify-space-between">
                        <div style="font-weight:normal">{{$t("Data file")}}: {{form_local.file_name}}</div>

                        <div>
                            <v-btn color="primary" small  @click="saveForm">{{$t("Save")}} <span v-if="is_dirty">*</span></v-btn>
                            <v-btn  @click="cancelForm" small>{{$t("cancel")}}</v-btn>
                        </div>
                    </v-card-title>
                </v-card>


                <v-card style="flex: 1;overflow:auto;">                    
                    <v-card-text>
            

                        <div class="row">
                            <div class="col-md-8">

                            <div class="form-group form-field">
                                <label for="filename">File name</label> 
                                <span><input readonly type="text" id="filename" class="form-control" v-model="form_local.file_name"/></span> 
                            </div>

                            <div class="form-group form-field">
                                <label for="description">Description</label> 
                                <span><textarea id="description" class="form-control" v-model="form_local.description"/></span> 
                            </div>

                            <div class="form-group form-field">
                                <label for="description">Producer</label> 
                                <span><textarea id="description" class="form-control" v-model="form_local.producer"/></span> 
                            </div>

                            <div class="form-group form-field">
                                <label for="description">Data checks</label> 
                                <span><textarea id="description" class="form-control" v-model="form_local.data_checks"/></span> 
                            </div>

                            <div class="form-group form-field">
                                <label for="description">Missing data</label> 
                                <span><textarea id="description" class="form-control" v-model="form_local.missing_data"/></span> 
                            </div>

                            <div class="form-group form-field">
                                <label for="description">Version</label> 
                                <span><textarea id="description" class="form-control" v-model="form_local.version"/></span> 
                            </div>

                            <div class="form-group form-field">
                                <label for="description">Notes</label> 
                                <span><textarea id="description" class="form-control" v-model="form_local.notes"/></span> 
                            </div>

                            </div>
                            <div class="col-md-4" style="display:none;">
                                <div><strong>{{$t("file_information")}}</strong></div>
                                <div class="mt-2">
                                    <div>
                                        <label>{{$t("physical_name")}}:</label>
                                        <div>{{form_local.file_physical_name}}</div>
                                    </div>                            
                                    <div class="mt-2">
                                        <label>{{$t("rows")}}:</label>
                                        <div>{{form_local.case_count}}</div>
                                    </div>
                                    <div class="mt-2">
                                        <label>{{$t("variables")}}:</label>
                                        <div>{{form_local.var_count}}</div>
                                    </div>
                                    <div class="mt-2" v-if="form_local.file_info">
                                        <label>{{$t("file_size")}}:</label>
                                        <div v-if="form_local && form_local.file_info && form_local.file_info.original ">{{form_local.file_info.original.file_size}}</div>                        
                                    </div>
                                </div> 
                            </div>
                            </div>

                </v-card-text>
                </v-card>


                </section>

            </div>          
            `    
})