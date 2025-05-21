//external resources
const VueExternalResourcesEdit= Vue.component('external-resources-edit', {
    props: ['index'],
    data() {
        return {
            file:'',
            errors:[],
            is_dirty:false,
            attachment_type:'',
            attachment_url:'',
            resource_template:'',
            resource_template_custom_fields:[ "filename" ], //fields not to render
            file_exists:false,
            dc_types:{                
                "doc/adm":"Document, Administrative [doc/adm]",
                "doc/anl":"Document, Analytical [doc/anl]",
                "doc/oth":"Document, Other [doc/oth]",
                "doc/qst":"Document, Questionnaire [doc/qst]",
                "doc/ref":"Document, Reference [doc/ref]",
                "doc/rep":"Document, Report [doc/rep]",
                "doc/tec":"Document, Technical [doc/tec]",
                "aud":"Audio [aud]",
                "dat":"Database [dat]",
                "map":"Map [map]",
                "dat/micro":"Microdata File [dat/micro]",
                "pic":"Photo [pic]",
                "prg":"Program [prg]",
                "tbl":"Table [tbl]",
                "vid":"Video [vid]",
                "web":"Web Site [web]"
            }
        }
    },
    mounted: function(){
        this.loadResourceTemplate();
    }, 
    watch: {
        Resource: {
            handler: function (val, oldVal) {
                if (!oldVal){return;}
                this.is_dirty=true;
            },
            deep: true
        },
        attachment_url: function(val){
            this.is_dirty=true;
        },
        file: function(val){
            this.is_dirty=true;
        }
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
    methods: {
        localValue: function(key)
        {
            console.log("searching for local value path",key,this.Resource, _.get(this.Resource,key));
            //remove 'variable_groups.' from key
            //key=key.replace('variable_groups.','');
            return _.get(this.Resource,key);
        },
        showUnsavedMessage: function(){
            if (this.is_dirty){
                if (!confirm("You have unsaved changes. Are you sure you want to leave this page?")){
                    return false;
                }
            }
            return true;
        },
        getResourceByID: function(){
            this.ExternalResources.forEach((resource, index) => {                
                if (resource.id==this.ActiveResourceIndex){
                    console.log(":resource",resource, this.ActiveResourceIndex);
                    return this.ExternalResources[index];
                }
            });
        },
        loadResourceTemplate: function(){
            vm=this;
            let url=CI.base_url + '/api/templates/default/resource';

            axios.get( url
            ).then(function(response){
                vm.resource_template=response.data.result;
            })
            .catch(function(response){
                console.log("loadResourceTemplate",response);
                alert("Failed to load template");
            });
        },
        saveResource: function()
        {
            let formData = new FormData();

            if (this.attachment_type=='url'){
                this.Resource.filename=this.attachment_url;
            }else if (this.attachment_type=='file'){
                this.Resource.filename=this.file.name;
            }

            formData=this.Resource;

            if (this.errors!=''){
                return false;
            }            

            vm=this;
            let url=CI.base_url + '/api/resources/'+ this.ProjectID + '/' + this.Resource['id'];

            axios.post( url,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }
            ).then(function(response){
                vm.$store.dispatch('loadExternalResources',{dataset_id:vm.ProjectID});
                vm.is_dirty=false;
                router.push('/external-resources/');
            })
            .catch(function(response){
                vm.errors=response;
            });    
        },
        cancelSave: function(){
            this.$store.dispatch('loadExternalResources',{dataset_id:this.ProjectID});
            router.push('/external-resources/');
        },
        uploadFile: function ()
        {
            if (this.attachment_type!='file' || !this.file){
                this.saveResource();
                return;
            }

            let formData = new FormData();
            formData.append('file', this.file);

            this.errors!=''

            vm=this;
            let url=CI.base_url + '/api/files/'+ this.ProjectID + '/documentation';

            axios.post( url,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }
            ).then(function(response){
                vm.Resource.filename=vm.file.name;
                vm.saveResource();                
            })
            .catch(function(response){
                vm.errors=response;
                alert("Failed to upload file");
            });            
        }, 
        handleFileUpload( event ){
            this.file = event;
            this.errors='';
            this.resourceFileExists();            
        },
        isValidUrl: function(string) {
            let url;
            
            try {
              url = new URL(string);
            } catch (_) {
              return false;  
            }
          
            return url.protocol === "http:" || url.protocol === "https:";
        },
        resourceFileExists: function()
        {
            if (!this.file){
                return false;
            }

            formData= new FormData();
            formData.append('file_name', this.file.name);
            formData.append('doc_type', 'documentation');

            vm=this;
            let url=CI.base_url + '/api/files/exists/'+ this.ProjectID;

            axios.post( url,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }
            ).then(function(response){
                if (response.data.exists){
                    vm.file_exists=response.data.exists;
                }
            })
            .catch(function(response){
                console.log("resourceFileExists",response);
            });    
        },
        resourceDeleteFile: function()
        {
            if (!confirm("Are you sure you want to delete this file?")){
                return false;
            }

            vm=this;
            let formData= new FormData();
            let url=CI.base_url + '/api/files/delete_resource_file/'+ this.ProjectID + '/' + this.Resource['id'];

            axios.post( url,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }
            ).then(function(response){
                vm.Resource.filename='';
            })
            .catch(function(response){
                console.log("resourceFileDeleted",response);
            });    
        },
        findTemplateByItemKey: function (items,key){
            let item=null;
            let found=false;
            let i=0;

            while(!found && i<items.length){
                console.log("searching", items[i].key, key);
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
        updateSection: function (obj)
        {            
            if (obj.key.indexOf(".") !== -1 && this.Resource[obj.key]){
                delete this.Resource[obj.key];
            }
            Vue.set(this.Resource,obj.key,obj.value);
        },
    },
    computed: {
        isProjectEditable(){
            return this.$store.getters.getUserHasEditAccess;
        },
        ExternalResources(){
          return this.$store.state.external_resources;
        },
        ActiveResourceIndex(){
            return this.$route.params.index;
        },
        Resource(){
            return this.$store.state.external_resources.find(resource => {
                return resource.id == this.ActiveResourceIndex
            });
        },        
        ResourceAttachmentType()
        {
            if (this.isValidUrl(this.Resource.filename)){
                return 'url';
            }

            return 'file';
        },
        ProjectID(){
            return this.$store.state.project_id;
        },
        ResourceFileExists(){
            return this.resourceFileExists();
        },
        ResourceTemplate(){
            let key='resource_container';                
            //let items=this.$store.state.formTemplate.template.items;
            let items=[]
            if (this.resource_template && this.resource_template.template && this.resource_template.template.items){
                items= this.resource_template.template.items;
            }
            
            let item=this.findTemplateByItemKey(items,key);
            return item;        
        },

    },
    template: `
        <div class="container-fluid edit-resource-container">

            <section style="display: flex; flex-flow: column;height: calc(100vh - 140px);" v-if="Resource">

            <v-card class="mt-4 mb-2">                    
                    <v-card-title class="d-flex justify-space-between">
                        <div style="font-weight:normal">{{$t("Edit resource")}}</div>

                        <div>
                            <v-btn color="primary" small @click="uploadFile" :disabled="file_exists==true || !isProjectEditable">{{$t("Save")}} <span v-if="is_dirty">*</span></v-btn>
                            <v-btn @click="cancelSave" small>Cancel</v-btn>
                        </div>
                    </v-card-title>
                </v-card>


            <v-card style="flex: 1;overflow:auto;">
            <v-card-text class="mb-5" v-if="ResourceTemplate && ResourceTemplate.items">

            <div  v-for="(column,idx_col) in ResourceTemplate.items" scope="row" :key="column.key"  >
            
                <template v-if="column.type=='section'">
                
                    <form-section
                        :parentElement="Resource"
                        :value="localValue(column.key)"
                        :columns="column.items"
                        :title="column.title"
                        :path="column.key"
                        :field="column"                            
                        @sectionUpdate="updateSection($event)"
                    ></form-section>  
                    
                </template>
                <template v-else>
                                          {{column.key}}      
                    <form-input
                        :value="localValue(column.key)"
                        :field="column"
                        @input="update(column.key, $event)"
                    ></form-input>                              
                    
                </template>
            </div>
            
            

            <v-card class="mt-2">
                <v-card-title class="d-flex justify-space-between">
                    <div style="font-weight:normal">Resource attachment</div>
                </v-card-title>

            <v-card-text>
            <div>                
                <div class="bg-light border p-2 text-small" style="font-size:12px;">
                    <span v-if="ResourceAttachmentType=='file'">File:</span>
                    <span v-if="ResourceAttachmentType=='url'">Link:</span>
                    {{Resource.filename}}
                    <span v-if="Resource.filename">
                        <button type="button" class="btn btn-link btn-sm" @click="resourceDeleteFile">Remove</button>
                    </span>
                    <span v-else>No file attached</span>

                    <div v-if="file_exists && file" class="border bg-danger text-light p-2 m-2"><strong>{{file.name}}</strong> File already exists, use a different file!</div>
                </div>

                <div class="form-check mt-2" >
                    <input class="form-check-input" type="radio" name="gridRadios" id="gridRadios1" value="file" v-model="attachment_type" >
                    <label class="form-check-label" for="gridRadios1">
                    Upload file
                    </label>
                </div>

                <div class="file-group form-field m-1 p-3 border-bottom">
                    <div class="bg-white">
                    
                        <v-file-input                            
                            label=""
                            outlined
                            truncate-length="50"
                            dense
                            prepend-icon=""
                            prepend-inner-icon="mdi-paperclip"
                            @change="handleFileUpload( $event )"
                            @click="attachment_type='file'"
                            ref="fileUpload"
                         ></v-file-input>
                        
                    </div>     
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="radio" name="gridRadios" id="gridRadios2" value="url" v-model="attachment_type">
                    <label class="form-check-label" for="gridRadios2">
                    URL
                    </label>
                </div>

                <div class="form-group form-field  m-1 p-3 ">
                    <span><input type="text" id="url" class="form-control" v-model="attachment_url" @click="attachment_type='url'"/></span> 
                </div>

            </div>
            </v-card-text>
            </v-card>

            

            </v-card-text>

            </v-card>
            
        </section>
        </div>
    `
});


