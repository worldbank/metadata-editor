///Project files summary
Vue.component('summary-files', {
    props:[],
    
    data: function() {
        return {
            files: {},
            resources: {},
            activeTab: 0,
        };
    },
    
    created: function(){    
        this.loadResources();
        this.loadData();    
    },
    
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        },
        ProjectType(){
            return this.$store.state.project_type;
            }
    },
    methods:{
        momentDate(date, compact=false){
            if (compact){
                return moment.utc(date).format("YYYY-MM-DD");
            }
            return moment.utc(date).format("YYYY-MM-DD HH:mm:ss");
        },
         
        loadData: function() {
            let vm = this;
            let url = CI.base_url + '/api/files/' + this.ProjectID;
            axios.get(url)
            .then(function (response) {
                if(response.data){               
                    vm.files = response.data.files;
                }
            })
            .catch(function (error) {
                console.log(error);
            });
        },
        loadResources: function(){
            let vm=this;
            let url=CI.base_url + '/api/resources/' + this.ProjectID + '?resources';
            axios.get(url)
            .then(function(response){
                if (response.data && response.data.resources){
                    vm.resources=response.data.resources;
                }
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
        fileTypeTitle: function(file){
            if (file.dir_path=='data/tmp' || file.dir_path=='.'){
                return 'Temporary';
            }
            return this.$t(file.dir_path);
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
                vm.loadData();
                vm.$emit('file-deleted', file);
            })
            .catch(function(response){
                vm.errors=response;
            });
        },
    },    
    template: `
    <div class="project-summary-files-component">

        <v-tabs v-model="activeTab">
                <v-tab>Documentation</v-tab>
                <v-tab>Files</v-tab>
            </v-tabs>
            <v-tabs-items v-model="activeTab">
                <v-tab-item>
                    <v-simple-table v-if="resources && resources.length>0">
                            <template v-slot:default>
                                <thead>
                                    <tr>
                                        <th class="text-left"></th>
                                        <th class="text-left">Title</th>
                                        <th class="text-left">Type</th>                        
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="resource in resources">
                                        <td class="text-top" ><v-icon>mdi-file-outline</v-icon></td>
                                        <td><a :href="'#/external-resources/' + resource.id">{{resource.title}}</a></td>
                                        <td>{{resource.dctype}}</td>
                                        
                                    </tr>
                                </tbody>
                            </template>
                        </v-simple-table>
                    <div v-else>
                        <div class="text-muted text-secondary">
                            None
                        </div>
                    </div>                
                </v-tab-item>
                <v-tab-item>
                    <v-simple-table class="mb-5" style="font-size:smaller;">
                        <template v-slot:default>
                            <thead>
                                <tr>                            
                                    <th></th>
                                    <th class="text-left">
                                    {{$t('Name')}}
                                    </th>                            
                                    <th class="text-left" style="width:100px;">
                                        {{$t('Size')}}
                                    </th>
                                    <th class="text-left" style="width:100px;">
                                        {{$t('Created')}}
                                    </th>
                                    <th class="text-left">
                                        
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(file, index) in files" class="resource-row" v-if="!file.is_dir" >
                                <td style="width:50px;">
                                    <div :title="fileTypeTitle(file)">
                                    <v-icon v-if="file.is_dir==true">mdi-folder</v-icon>
                                    <v-icon style="font-size:24px;" v-if="file.is_dir==false" :color="colorByFolderType(file.dir_path)">mdi-file-document-outline</v-icon>
                                </td>                        
                                <td style="font-size:small;">
                                    {{file.name}} 
                                </td>
                                
                                <td style="font-size:small;">{{file.size_human}}</td>
                                <td><span style="font-size:small;" :title="momentDate(file.timestamp)" >{{momentDate(file.timestamp, true)}}</span></td>
                                
                                <td>
                                    <v-btn small danger text @click="deleteFile(file)">
                                        <v-icon>mdi-delete-outline</v-icon> 
                                    </v-btn>                            
                                </td>
                            </tr>
                            </tbody>
                        </template>
                    </v-simple-table>
                </v-tab-item>
            </v-tabs-items>
        
        
            
    </div>         
    `
});