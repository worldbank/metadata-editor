//file manager
Vue.component('file-manager', {
    props: ['index', 'id'],
    data() {
        return {
            files: [],
            errors: '',
        }
    }, 
    mounted () {
        this.loadFiles();
    },   
    methods: {      
        momentDate(date) {
            return moment.utc(date).format("YYYY-MM-DD HH:mm:ss");
        },  
        addFile:function(){
            alert("TODO");
            return;
        },
        deleteFile: function(file){
            if (!confirm(this.$t("confirm_delete") + ' ' + file.name)){
                return;
            }

            vm=this;
            let url=CI.base_url + '/api/files/delete/'+ this.ProjectID;
            let formData=new FormData();
            formData.append('file_path',file.dir_path+'/'+file.name);

            axios.post( url, formData,
            ).then(function(response){
                vm.loadFiles();
            })
            .catch(function(response){
                vm.errors=response;
            });
        },
        importGeospatialMetadata: function(file){
            vm=this;
            let url=CI.base_url + '/api/geospatial/extract_metadata/'+ this.ProjectID;
            let formData=new FormData();
            formData.append('file_path',encodeURIComponent(file.parent+'/'+file.name));

            axios.post( url, formData,
            ).then(function(response){
                vm.loadFiles();
            })
            .catch(function(response){
                vm.errors=response;
            });
        },
        loadFiles: function(){
                vm=this;
                let url=CI.base_url + '/api/files/'+ this.ProjectID;
    
                axios.get(url)
                .then(function(response){
                    vm.files=response.data.files;                    
                })
                .catch(function(response){
                    vm.errors=response;
                });
        },
        getFileType: function(filename){
            let parts=filename.split('.');
            let ext=parts[parts.length-1];
            return ext;
        },
        isZip: function(filename){
            let ext=this.getFileType(filename);
            return ext=='zip';
        },
        isData: function(filepath){
            let parts=filepath.split('/');
            //if first part is data, then it is a data file
            return parts[0]=='data';
        },

        colorByFolderType: function(dir_path){
            let parts=dir_path.split('/');

            if (dir_path=='data/tmp' || dir_path=='.'){
                return 'red';
            }

            if (parts[0]=='data'){
                return 'purple';
            }

            if (parts[0]=='documentation'){
                return 'green';
            }

            return 'black';
        },

        isTypeGeospatial: function(fileName){
            let geospatial_types=['shp','tiff','geotiff','tif'];
            let ext=this.getFileExtension(fileName);
            return geospatial_types.includes(ext);
        },
        getFileExtension: function(fileName){
            let parts=fileName.split('.');
            let ext=parts[parts.length-1];
            return ext;
        },        
        extractZip:function(file)
        {
            if (!confirm(this.$t("confirm_extract"))){
                return;
            }

            vm=this;
            let url=CI.base_url + '/api/files/unzip/'+ this.ProjectID;
            let formData=new FormData();
            formData.append('file_name',encodeURIComponent(file.parent+'/'+file.name));

            axios.post( url, formData,
            ).then(function(response){
                vm.loadFiles();
            })
            .catch(function(response){
                vm.errors=response;
            });            
        }        
    },
    computed: {
        ExternalResources()
        {
          return this.$store.state.external_resources;
        },
        ActiveResourceIndex(){
            return this.$route.params.index;
        },
        ProjectID(){
            return this.$store.state.project_id;
        },
        ProjectType(){
            return this.$store.state.project_type;
            },
        FilesFlatView(){
            return this.files;
            /*let output=[];
            let vm=this;
            function flattenFiles(files,parent=''){
                console.log("files",files);
                files.forEach(function(file){
                    if (file.type=='file'){
                        file.parent=parent;
                        output.push(file);
                        console.log("file",file);
                    }
                    if (file.type=='folder'){
                        file.parent=parent;
                        flattenFiles(file.items,parent + '/' + file.name);
                    }
                });                
            }

            flattenFiles(this.files);
            return output;            */
        }
    },
    template: `
        <div class="file-manager container-fluid pt-5 mt-5">

        <v-card>
            <v-card-title>
            <h3>File manager</h3>
            </v-card-title>

            <v-card-text>

            <div v-if="errors">
            <pre>{{errors}}</pre>
            </div>

            <v-row>
                <v-col md="8"><strong>{{FilesFlatView.length}}</strong> files </v-col>
                <v-col md="4" class="mb-2">
                    <div class="float-right">
                        <!-- 
                        <v-btn outlined small color="primary" @click="addFile"><i class="fas fa-plus-square"></i> Upload file</v-btn>
                        -->
                    </div>
                </v-col>
            </v-row>

            <v-simple-table class="elevation-2 border mb-5" >
                <template v-slot:default>
                    <thead>
                        <tr>                            
                            <th></th>
                            <th class="text-left">
                            {{$t('Type')}}
                            </th>
                            <th class="text-left">
                            {{$t('Name')}}
                            </th>                            
                            <th class="text-left">
                                {{$t('Size')}}
                            </th>
                            <th class="text-left">
                                {{$t('Created')}}
                            </th>
                            <th class="text-left">
                                
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(file, index) in FilesFlatView" class="resource-row" v-if="!file.is_dir">
                        <td style="width:50px;">
                            <v-icon v-if="file.is_dir==true">mdi-folder</v-icon>
                            <v-icon style="font-size:24px;" v-if="file.is_dir==false" :color="colorByFolderType(file.dir_path)">mdi-file-document-outline</v-icon>                            
                        </td>
                        <td>
                            <v-chip :color="colorByFolderType(file.dir_path)" small outlined class="text-small text-secondary text-uppercase">
                            <span v-if="file.dir_path=='data/tmp' || file.dir_path=='.'">TEMPORARY</span>
                            <span v-else>{{file.dir_path}}</span>                            
                            </v-chip>
                        </td>
                        <td>
                            {{file.name}}                            
                        </td>
                        
                        <td>{{file.size_human}}</td>
                        <td>{{momentDate(file.timestamp)}}</td>
                        
                        <td>
                            <v-btn danger text color="red" @click="deleteFile(file)">
                                <v-icon>mdi-delete</v-icon> 
                            </v-btn>
                            <span v-if="ProjectType=='geospatial'">
                                <v-btn primary text v-if="isZip(file.name)" @click="extractZip(file)"><i class="fas fa-edit"></i> Extract zip</v-btn>
                            </span>
                        </td>
                    </tr>
                    </tbody>
                </template>
            </v-simple-table>

            </v-card-text>
        </v-card>

            
        </div>
    `
})


