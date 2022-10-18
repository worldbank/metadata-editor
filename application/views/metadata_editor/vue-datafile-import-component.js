/// datafile import form
Vue.component('datafile-import', {
    data: function () {    
        return {
            files:[],
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
            },
            allowed_file_types:["dta","sav","csv"]
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
        FilesCount: function(){            
            return this.files.length;
        },
    },
    methods:{
        validateFilename: function(file){
            return /^[a-zA-Z0-9\.\-_ ()]*$/.test(file.name);
        },   
        checkFileDuplicate(name){
            for(i=0;i<this.files.length;i++){
                if (this.files[i].name==name){
                    return true;
                }
            }
            return false;
        }, 
        addFile(e) {
            let droppedFiles = e.dataTransfer.files;
            if(!droppedFiles) return;
            // this tip, convert FileList to array, credit: https://www.smashingmagazine.com/2018/01/drag-drop-file-uploader-vanilla-js/
            ([...droppedFiles]).forEach(f => {
                if (!this.checkFileDuplicate(f.name) && this.isAllowedFileType(f.name)){
                    this.files.push(f);
                }
            });
        },
        isAllowedFileType(name){
            if (_.includes(this.allowed_file_types,this.fileExtension(name))){
                return true;
            }

            return false;
        },
        removeFile(file_idx){
            /*this.files = this.files.filter(f => {
              return f != file;
            });*/
            Vue.delete(this.files,file_idx);      
            this.files.splice(file_idx, 0);
        },
        processImport: async function(){
            await this.uploadDatafiles();

            this.update_status="completed";
            this.is_processing=false;
            vm.$store.dispatch('initData',{dataset_id:this.ProjectID});
        },
        uploadDatafiles: async function(){
            this.errors='';

            for(i=0;i<this.files.length;)
            {
                await this.uploadDataFile(i);
                i++;

                if (this.errors!=''){
                    return false;
                }
            }
        },
        uploadDataFile: async function (fileIdx)
        {            
            let formData = new FormData();
            formData.append('file', this.files[fileIdx]);

            if (this.errors!=''){
                return false;
            }

            let vm=this;
            let url=CI.base_url + '/api/editor/files/'+ this.ProjectID + '/data';

            await axios.post( url,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }
            ).then(function(response){
                vm.importDataFile(fileIdx);
                return true;
            })
            .catch(function(response){
                vm.errors=response;
                return false;
            });            
        }, 
        importDataFile: async function(fileIdx){
            this.is_processing=true;
            this.update_status="Importing data dictionary " + this.files[fileIdx].name;

            let formData = {
                "filename":this.files[fileIdx].name
            }
            
            vm=this;            
            let url=CI.base_url + '/api/R/import_data_file/'+this.ProjectID;
            
            try{
                let resp = await axios.post(url, formData,{
                    headers: {
                        'Content-Type': 'application/json'
                      }
                });

                this.update_status="";

            }catch(error){
                vm.errors=error;
                console.log(Object.keys(error), error.message);
            }
        },
        generateCSVFromR: async function (file)
        {
            let formData = new FormData();
            //formData.append('fileid', this.file_id);
            formData.append("filename",file.name)

            vm=this;
            this.update_status="Generating CSV...";
            let url=CI.base_url + '/api/R/generate_csv/'+this.ProjectID + '/' +  file.name;

            axios.get( url,formData,{}
            ).then(function(response){
                console.log('SUCCESS!!',response);
                vm.update_status="CSV file generated...";
            })
            .catch(function(){
                console.log('FAILURE!!');
            });            
        },  
        handleFileUpload(event)
        {
            this.file = event.target.files[0];
            this.errors='';
            this.checkFileExists();
        },
        handleMultiFileUpload(event)
        {
            let files = event.target.files;
            for(let i = 0; i<files.length; i++)
            {
                if (!this.checkFileDuplicate(files[i].name) && this.isAllowedFileType(files[i].name)){
                    this.files.push(files[i]);
                }
            }
        },
        checkFileExists: async function()
        {
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
        fileExtension: function(name){
            return name.split(".").pop().toLowerCase();
        }
    },
    template: `
            <div class="datafile-import-component">
            <v-container>

                <h3>Import data</h3>

                <div class="bg-white p-3" >

                    <div class="form-container-x" >

                        
                        <h5>Upload data</h5>
                        <p>Upload one or more data files. Supported file types are: Stata(.dta), SPSS(.sav) and CSV</p>

                        <div @drop.prevent="addFile" @dragover.prevent class="border p-2 mb-2 bg-light text-center">
                            <div class="p-4"><i style="font-size:40px;color:gray;" class="fas fa-upload"></i></div>
                            <strong>Drag and drop data files here</strong>

                            <div class="mt-3">or</div>
                            
                            <div class="custom-file" style="max-width:300px;">
                                <input type="file" class="custom-file-input" id="customFile" multiple @change="handleMultiFileUpload( $event )">
                                <label class="custom-file-label" for="customFile">Choose files</label>
                            </div>

                        </div>

                        <div class="files-container mt-3 mb-3 p-2" v-if="files.length>0" >
                            <h5 class="mb-1">{{files.length}} files selected</h5>
                            <div class="border row mt-2" v-for="(file,file_index) in files" :key="file.name">
                                <div class="col-10" style="font-size:small;"><strong>{{ file.name }}</strong>  <div class="text-secondary">{{ file.size | kbmb }}</div> {{file.type}}</div>
                                <div class="col-2"><button class="float-right" @click="removeFile(file_index)" title="Remove"><i class="fas fa-trash"></i></button> </div>
                            </div>

                        </div>
                                                
                        <div xv-if="update_status==''">
                            <button type="button" :disabled="!FilesCount>0" class="btn btn-primary" @click="processImport">Import files</button>
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
            
{{update_status}} {{errors}}
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
            `    
})