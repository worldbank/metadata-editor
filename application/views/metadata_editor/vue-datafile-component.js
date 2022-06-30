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
        }
    },  
    template: `
            <div class="datafile-component">
            <div v-for="file in dataFiles">
                <div v-if="file.file_id==fid">

                <div>
                    {{file.file_id}}
                </div>

                <div>
                    {{file.file_name}}
                </div>

                <div>
                    {{file.description}}
                </div>

                </div>
            </div>

            </div>          
            `    
});

