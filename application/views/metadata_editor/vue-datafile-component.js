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
                <div v-if="file.file_id==fid" class="mt-3 p-2">

                <div>
                    File name: <strong>{{file.file_name}}</strong>
                </div>

                <div>
                    {{file.file_id}}
                </div>

                <div>
                    {{file.description}}
                </div>

                <div class="mt-3">
                    <router-link :to="'/variables/' + file.file_id"><button type="button" class="btn btn-sm btn-outline-primary"><i class="fas fa-table"></i> Variables <span v-if="file.var_count>0">({{file.var_count}})</span></button></router-link>
                </div>

                </div>
            </div>

            </div>          
            `    
});

