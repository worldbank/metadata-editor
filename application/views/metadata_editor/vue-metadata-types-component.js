//metadata types
const VueMetadataTypes = Vue.component('metadata-types', {
    props: [],
    data() {
        return {
            metadata_types:[],            
        }
    }, 
    mounted () {
        this.loadMetadataTypes();
    },
    watch: {
    },
    methods: {
        momentDateUnix(date) {
            if (!date){
                return '';
            }

            return moment.unix(date).format("YYYY-MM-DD H:mm:ss");
        },

       loadMetadataTypes: function(){
            vm=this;
            let url=CI.base_url + '/api/admin-metadata/templates/';
            axios.get( url
            ).then(function(response){
                console.log("MetadataType",response.data);
                vm.metadata_types=response.data;
            })
            .catch(function(response){
                vm.errors=response;
                //alert("Failed: " + vm.erorrMessageToText(response));
                console.log("failed", response);
            });            
        },
        deleteMetadata: function(){
            vm=this;
            if (!confirm("Are you sure you want to delete this metadata?")){
                return;
            }

            let json_data={
                'project_id': this.ProjectIDNO,
                'metadata_type_name': this.MetadataType.name
            };

            let url=CI.base_url + '/api/metadata/data_remove/';
            axios.post( url, json_data
            ).then(function(response){
                vm.metadata_model={};
                alert("Metadata deleted");
                vm.$router.push({name: 'metadata-types'});
            })
            .catch(function(response){
                vm.errors=response;
                alert("Failed: " + vm.erorrMessageToText(response));
            });
        },

        erorrMessageToText: function(error){
            let error_text = '';
            if (error.response.data.errors) {
                for (let key in error.response.data.errors) {
                    error_text += error.response.data.errors[key] + '\n';
                }
            } else {
                error_text = error.response.data.message;
            }
            return error_text;
        },
    },
    computed: {        
        ProjectID(){
            return this.$store.state.project_id;
        },
        ProjectIDNO(){
            return this.$store.state.idno;
        },
        MetadataTypes(){
            if (this.metadata_types && this.metadata_types.result){
                return this.metadata_types.result;
            }

            return [];
        },
    },
    template: `
        <div class="metadata-types-container container-fluid pt-5 mt-5"" >

            <v-card class="mt-4 mb-2" v-if="MetadataTypes">                    
                    <v-card-title>                    
                        <div style="font-weight:normal">{{$t("Administrative metadata")}}</div>
                    </v-card-title>
                
            <v-card-text class="mb-5" >

            <v-simple-table>
                <template v-slot:default>
                    <thead>
                        <tr>
                            <th class="text-left">{{$t("UID")}}</th>
                            <th class="text-left">{{$t("Name")}}</th>
                            <th class="text-left">{{$t("Description")}}</th>
                            <th class="text-left">{{$t("Actions")}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="metadata_type in MetadataTypes">
                            <td><router-link :to="'/metadata-types/' + metadata_type.uid" >{{metadata_type.uid}}</router-link></td>
                            <td>{{metadata_type.name}}</td>
                            <td>{{metadata_type.description}}</td>
                            <td>
                                <router-link :to="'/metadata-types/' + metadata_type.uid" >{{$t("Edit")}}</router-link>                                
                            </td>
                        </tr>
                    </tbody>
                </template>
            
            </v-card-text>
            </v-card>
        


        </div>
    `
});


