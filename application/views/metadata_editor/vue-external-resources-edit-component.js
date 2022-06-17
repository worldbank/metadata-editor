//external resources
Vue.component('external-resources-edit', {
    props: ['index'],
    data() {
        return {
        }
    }, 
    created () {
        //this.loadDataFiles();
    },   
    methods: {
        editFile:function(file_id){
            this.page_action="edit";
            this.edit_item=file_id;
        },
        addFile:function(){
            this.page_action="edit";
            console.log(this.data_files);
            let new_idx=this.data_files.push({file_name:""}) -1;
            this.edit_item=new_idx;
        },
        saveFile: function(data)
        {
            console.log("saving file",this.data_files[this.edit_item]);
            this.$set(this.data_files, this.edit_item, data);            
        },
        exitEditMode: function()
        {
            this.page_action="list";
            this.edit_item=null;
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
        Resource(){
            return this.ExternalResources[this.ActiveResourceIndex];
        },

    },
    template: `
        <div>
            <h1>External resources Edit</h1>            
            todo - {{ActiveResourceIndex}}
            <pre>{{ExternalResources[ActiveResourceIndex]}}</pre>

            <div class="form-group form-field">
                <label for="title">Title</label> 
                <span><input type="text" id="title" class="form-control" v-model="Resource.title"/></span> 
            </div>

            



        </div>
    `
})


