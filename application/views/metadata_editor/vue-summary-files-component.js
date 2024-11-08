///Project files summary
Vue.component('summary-files', {
    props:[],
    
    data: function() {
        return {
            files: {},
            resources: {}
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
    },
    methods:{
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
        }
    },    
    template: `
    <div class="project-summary-files-component">
        
        <div class="component-container">

        <v-simple-table>
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

        
<!--
            <div v-for="doc in files.documentation">
                <div class="border-bottom small">{{doc.file}}</div>
            </div>

            <div class="mt-3">
                <div><strong>Data files</strong></div>
                <div v-for="data in files.data">
                    <div class="border-bottom small">{{data}}</div>
                </div>
            </div>

            <div class="mt-3" v-if="files && files.external_resources">
                <div><strong>External resources</strong> ({{files.external_resources.length}})</div>
                <div v-for="resource in files.external_resources">
                    <div class="border-bottom p-1 small">{{resource.title}}</div>
                </div>
            </div>

            -->

        </div>

    </div>         
    `
});