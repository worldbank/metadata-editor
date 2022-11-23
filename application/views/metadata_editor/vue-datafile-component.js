/// datafile view form
Vue.component('datafile', {
    props:['file_id','value'],
    data: function () {    
        return {
            field_data: this.value,
            form_local:{},
            fid:this.file_id
        }
    },
    created: async function(){
        this.fid=this.$route.params.file_id;
    },
    
    computed: {
        dataFiles(){
            return this.$store.getters.getDataFiles;
        },
        ProjectID(){
            return this.$store.state.project_id;
        }
    },
    methods:{
        exitEditMode: function()
        {
            router.push('/datafiles');
        },
        saveFile: function(data)
        {
            vm=this;
            let url=CI.base_url + '/api/editor/datafiles/'+vm.ProjectID;
            form_data=data;

            axios.post(url, 
                form_data
                /*headers: {
                    "name" : "value"
                }*/
            )
            .then(function (response) {
                vm.updateVuexDataFile(data);
                router.push('/datafiles');
            })
            .catch(function (error) {
                alert("Failed to add data file: "+ error.message);
            })
            .then(function () {
                console.log("request completed");
            });
        },
        updateVuexDataFile: function(data)
        {
            for(i=0;i<this.dataFiles.length;i++){
                if (this.dataFiles[i].file_id==this.fid){
                    this.dataFiles[i]
                    vm.$set(this.dataFiles, i, data);
                    return;
                }
            }
        }
    },
    template: `
            <div class="datafile-component">
            <div v-for="file in dataFiles">
                <div v-if="file.file_id==fid" class="mt-3 p-2">
                    <h2>Edit file - {{file.file_name}} [{{file.file_id}}]</h2>
                    <datafile-edit :value="file" @input="saveFile" @exit-edit="exitEditMode"></datafile-edit>                
                </div>
            </div>

            </div>          
            `    
});

