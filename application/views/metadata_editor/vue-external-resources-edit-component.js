//external resources
Vue.component('external-resources-edit', {
    props: ['index'],
    data() {
        return {
            file:'',
            errors:[],
            attachment_type:'',
            attachment_url:'',
            resource_template:'',
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
    created () {
        //this.loadDataFiles();
        //this.loadResourceTemplate();
    },   
    methods: {        
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
            let url=CI.base_url + '/api/templates/resource-system-en';

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
            //formData.append('file', this.file);            

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
            if (this.attachment_type!='file'){
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
            this.file = event.target.files[0];
            this.errors='';
        },
        isValidUrl: function(string) {
            let url;
            
            try {
              url = new URL(string);
            } catch (_) {
              return false;  
            }
          
            return url.protocol === "http:" || url.protocol === "https:";
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
        ActiveResourceIndexByID(){

        },
        Resource(){
            //return this.ExternalResources[this.ActiveResourceIndex];            

            return this.$store.state.external_resources.find(resource => {
                return resource.id == this.ActiveResourceIndex
            });

            return this.$store.state.external_resources.forEach((resource, index) => {
                if (resource.id==this.ActiveResourceIndex){
                    console.log(":resource",resource, this.ActiveResourceIndex);
                    return this.ExternalResources[index];
                }
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
        }

    },
    template: `
        <div class="container-fluid edit-resource-container mt-5">
            <div v-if="Resource">
            <h3>Edit resource</h3>
            
            <div class="form-group form-field" >
                <label>File type *</label>
                <select 
                    v-model="Resource.dctype" 
                    class="form-control  form-control-sm form-field-dropdown"
                    id="dctype">

                    <option value="">Select</option>
                    <option v-for="(option_key,option_value) in dc_types" v-bind:value="option_key">
                        {{ option_key }}
                    </option>
                </select>
                <small class="help-text form-text text-muted">{{Resource.dc_type}}</small>                    
            </div>

            <div class="form-group form-field">
                <label for="title">Title</label> 
                <span><input type="text" id="title" class="form-control" v-model="Resource.title"/></span> 
            </div>

            <div class="form-group form-field">
                <label for="subtitle">Subtitle</label> 
                <span><input type="text" id="subtitle" class="form-control" v-model="Resource.subtitle"/></span> 
            </div>

            <div class="form-group form-field">
                <label for="author">Author</label> 
                <span><input type="text" id="author" class="form-control" v-model="Resource.author"/></span> 
            </div>

            <div class="form-group form-field">
                <label for="date">Date (YYYY-MM-DD)</label> 
                <span><input type="text" id="date" class="form-control" v-model="Resource.dcdate"/></span> 
            </div>

            <div class="form-group form-field">
                <label for="country">Country</label> 
                <span><input type="text" id="country" class="form-control" v-model="Resource.country"/></span> 
            </div>

            <div class="form-group form-field">
                <label for="language">Language</label> 
                <span><input type="text" id="language" class="form-control" v-model="Resource.language"/></span> 
            </div>

            <div class="form-group form-field">
                <label for="contributor">Contributor</label> 
                <span><input type="text" id="contributor" class="form-control" v-model="Resource.contributor"/></span> 
            </div>

            <div class="form-group form-field">
                <label for="publisher">Publisher</label> 
                <span><input type="text" id="publisher" class="form-control" v-model="Resource.publisher"/></span> 
            </div>

            <div class="form-group form-field">
                <label for="rights">Rights</label> 
                <span><input type="text" id="rights" class="form-control" v-model="Resource.rights"/></span> 
            </div>

            <div class="form-group form-field">
                <label for="description">Description</label> 
                <span><textarea style="height:200px;" id="description" class="form-control" v-model="Resource.description"/></span> 
            </div>

            <div class="form-group form-field">
                <label for="abstract">Abstract</label> 
                <span><textarea style="height:200px;" id="abstract" class="form-control" v-model="Resource.abstract"/></span> 
            </div>

            <div class="form-group form-field">
                <label for="toc">Table of contents</label> 
                <span><textarea style="height:200px;" id="toc" class="form-control" v-model="Resource.toc"/></span> 
            </div>

            <div class="form-group form-field">
                <label for="subjects">Subjects</label> 
                <span><textarea style="height:200px;" id="subjects" class="form-control" v-model="Resource.subjects"/></span> 
            </div>

            <div class="form-group form-field">
                <label for="dcformat">Format</label> 
                <span><input type="text" id="dcformat" class="form-control" v-model="Resource.dcformat"/></span> 
            </div>


            <div class="bg-white border mb-2 mt-2 p-1">
                <div class="p1 mb-2"><strong>Resource attachment</strong> (Upload file or URL)</div>
                <div class="bg-light border p-2 text-small" style="font-size:12px;">
                    <span v-if="ResourceAttachmentType=='file'">File:</span>
                    <span v-if="ResourceAttachmentType=='url'">Link:</span>
                    {{Resource.filename}}
                </div>

                <div class="form-check mt-2" >
                    <input class="form-check-input" type="radio" name="gridRadios" id="gridRadios1" value="file" v-model="attachment_type" >
                    <label class="form-check-label" for="gridRadios1">
                    Upload file
                    </label>
                </div>

                <div class="file-group form-field m-1 p-3 border-bottom">
                    <label class="l" for="customFile">Upload file</label>
                    <div class="bg-white border p-1">
                        <input type="file" class="form-control-file" id="customFile" @click="attachment_type='file'" @change="handleFileUpload( $event )">                    
                    </div>     
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="radio" name="gridRadios" id="gridRadios2" value="url" v-model="attachment_type">
                    <label class="form-check-label" for="gridRadios2">
                    URL
                    </label>
                </div>

                <div class="form-group form-field  m-1 p-3 ">
                    <label for="url">URL</label> 
                    <span><input type="text" id="url" class="form-control" v-model="attachment_url" @click="attachment_type='url'"/></span> 
                </div>

            </div>


            <button type="button" class="btn btn-primary" @click="uploadFile">Save</button>
            <button type="button" class="btn btn-secondary" @click="cancelSave">Cancel</button>

            
        </div>
        </div>
    `
})


