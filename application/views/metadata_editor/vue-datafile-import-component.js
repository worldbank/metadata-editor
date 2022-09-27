/// datafile import form
Vue.component('datafile-import', {
    data: function () {    
        return {
            file:'',
            file_id:'',
            file_exists:false,
            data_dictionary:{},
            is_processing:false,
            update_status:'',
            errors:'',
            file_type:'',
            file_types:{
                "DTA": "Stata (DTA)",
                "SAV": "SPSS (SAV)",
                "CSV": "CSV"
            }
        }
    },
    watch: { 
        MaxFileID(newVal){
            if ('F' + newVal==this.file_id){
                return;
            }
            
            this.file_id='F'+(newVal+1);
        }
    },    
    mounted: function () {
        if (this.file_id==''){
            this.file_id='F'+(this.MaxFileID+1);
        }        
    },
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        },
        MaxFileID(){
            return this.$store.getters["getMaxFileId"];
        },
        MaxVariableID(){
            return this.$store.getters["getMaxVariableId"];
        },
        validateFilename: function(){            
            if (!this.file){
                return true;
            }

            return /^[a-zA-Z0-9\.\-_ ()]*$/.test(this.file.name);
        },
    },  
    template: `
            <div class="datafile-import-component">
            <v-container>

                <h3>Import data file</h3>

                <div class="bg-white p-3" >

                    <div class="form-container-x" >

                        <div class="form-group form-field">
                            <label for="file">file ID*</label> 
                            <span><input type="text" id="file" class="form-control" v-model="file_id"/></span> 
                        </div>

                        <div class="form-group form-field">
                            <label>File type *</label>
                            <select 
                                v-model="file_type" 
                                class="form-control  form-control-sm form-field-dropdown"
                                id="var_format_type">

                                <option value="">Select</option>
                                <option v-for="(option_key,option_value) in file_types" v-bind:value="option_value">
                                    {{ option_key }}
                                </option>
                            </select>
                            <small class="help-text form-text text-muted">{{file_type}}</small>                    
                        </div>
                        
                        <div class="file-group form-field mb-3">
                            <label class="l" for="customFile">Choose file</label>
                            <input type="file" class="form-control" id="customFile" @change="handleFileUpload( $event )">
                            <small v-if="validateFilename==false" class="text-danger">Special characters in file names are not allowed</small>
                        </div>
                        
                        <div v-if="update_status==''">                         
                            <button type="button" :disabled="!validateFilename" class="btn btn-primary" @click="uploadDataFile">Import file</button>
                        </div>

                    </div>

                    <div v-if="errors" class="p-3" style="color:red">
                        <div><strong>Errors</strong></div>
                        {{errors}}
                        <div v-if="errors.response">{{errors.response.data.message}}</div>
                    </div>

                    <v-row class="mt-3 text-center" v-if="update_status=='completed' && errors==''">
                        <v-col class="text-center" >
                            <i class="far fa-check-circle" style="font-size:24px;color:green;"></i> Update completed,
                            <router-link :to="'/variables/' + file_id">view variables</router-link>
                        </v-col>
                    </v-row>
            

                    <v-container v-if="update_status!='completed' && errors=='' ">                    
                        <v-row v-if="is_processing"
                        class="fill-height"
                        align-content="center"
                        justify="center"
                        >
                        <v-col
                            class="text-subtitle-1 text-center"
                            cols="12"
                        >
                        {{update_status}}
                        </v-col>
                        <v-col cols="12">
                            <v-app>
                            <v-progress-linear 
                            color="deep-purple accent-4"
                            indeterminate
                            rounded
                            height="6"
                            ></v-progress-linear>
                            </v-app>
                        </v-col>
                        </v-row>
                    </v-container>


                </div>


            </v-container>

            </div>          
            `,
    methods:{        
        saveForm: function (){    
            this.field_data = Object.assign({}, this.field_data, this.form_local);
            this.$emit('input', this.field_data);
            this.$emit("exit-edit", true);
        },
        cancelForm: function (){
            this.form_local = Object.assign({}, this.field_data);
            this.$emit("exit-edit", false);
        },
        ImportData: async function(){
            this.is_processing=true;
            this.update_status="Loading data dictionary";

            await this.getDataDictionaryFromR();

            if (this.errors){
                return false;
            }

            if (this.file_type!=='CSV'){
                this.update_status="Exporting data to CSV";
                await this.generateCSVFromR();

                if (this.errors){
                    return false;
                }
            }

            this.update_status="Creating data file";
            await this.createDataFile();

            if (this.errors){
                return false;
            }

            this.update_status="Importing variables";
            await this.importVariables();

            this.update_status="completed";
            this.is_processing=false;
        },
        createDataFile: async function(){
            let url=CI.base_url + '/api/editor/datafiles/'+this.ProjectID;
            let data={
                file_id:this.file_id,
                file_name:this.file.name,
                var_count:this.data_dictionary.cnt
            };
                        
            await axios.post(url,
                data,
                {
                    /*headers: {
                        'Content-Type': 'multipart/form-data'
                    }*/
                }
            ).then(function(response){
                return true;
            })
            .catch(function(){
                return false;
            });
        },
        importVariables: async function()
        {
            let url=CI.base_url + '/api/editor/variables/'+this.ProjectID;
            this.update_status="Updating data dictionary...";
            this.UpdateVariablesVID();
            vm=this;
        
            return axios.post(url,
                this.data_dictionary.variables,
                {
                    /*headers: {
                        'Content-Type': 'multipart/form-data'
                    }*/
                }
            ).then(function(response){
                vm.update_status="Data dictionary imported!!!!";
                vm.$store.dispatch('initData',{dataset_id:vm.ProjectID});
                router.push('/variables/'+ vm.file_id);
            })
            .catch(function(){
                vm.update_status="Data dictionary import failed";
            });
        },
        UpdateVariablesVID: function()
        {
            max_var_id=this.MaxVariableID;

            for(i=0;i<this.data_dictionary.variables.length;i++){
                this.data_dictionary.variables[i].vid='V'+(max_var_id++);
            }
        },
        uploadDataFile: function ()
        {
            let formData = new FormData();
            formData.append('file', this.file);

            if (this.errors!=''){
                return false;
            }

            vm=this;
            let url=CI.base_url + '/api/editor/files/'+ this.ProjectID + '/data';

            axios.post( url,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }
            ).then(function(response){
                vm.ImportData();
            })
            .catch(function(response){
                vm.errors=response;
            });            
        }, 
        getDataDictionaryFromR: async function ()
        {
            let formData = {
                "fileid": this.file_id,
                "filename":this.file.name,
                "filetype":this.file_type
            }
            
            vm=this;
            this.update_status="Generating data dictionary...";
            let url=CI.base_url + '/api/R/data_dictionary/'+this.ProjectID;
            
            try{
                let resp = await axios.post(url, formData,{
                    headers: {
                        'Content-Type': 'application/json'
                      }
                });

                if (!resp.data.variables){
                    throw new Error("Data dictionary import failed");
                }

                vm.data_dictionary=resp.data;
                vm.update_status="Data dictionary completed...";
            }catch(error){
                vm.errors=error;
                console.log(Object.keys(error), error.message);
            }
        },
        generateCSVFromR: function ()
        {
            let formData = new FormData();
            formData.append('fileid', this.file_id);
            formData.append("filename",this.file.name)

            vm=this;
            this.update_status="Generating CSV...";
            let url=CI.base_url + '/api/R/generate_csv/'+this.ProjectID + '/' + this.file_id + '/' + this.file.name;

            axios.get( url,formData,{}
            ).then(function(response){
                console.log('SUCCESS!!',response);
                vm.update_status="CSV file generated...";
            })
            .catch(function(){
                console.log('FAILURE!!');
            });            
        },  
        handleFileUpload( event ){
            this.file = event.target.files[0];
            this.errors='';
            this.checkFileExists();
        },
        checkFileExists: async function(){
            let url=CI.base_url + '/api/editor/datafile_by_name/'+this.ProjectID+'?filename='+this.file.name;
            this.errors='';
            vm=this;

            try {
                const resp = await axios.get(url);
                console.log(resp.data);
                vm.errors="File with the name already exists.";
                return false;
            } catch (err) {
                console.error(err);
                return true;
            }
        },
    }
})