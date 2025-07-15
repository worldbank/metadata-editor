Vue.component('project-history', {
    props: [],
    data() {
        return {
            is_loading: false,
            history: [],
            deep: 0
        }
    },
    mounted:function(){      
        this.loadEditHistory();
    },
    methods: {
        loadEditHistory: async function()
        {
            vm=this;            
            vm.is_loading=true;
            let url=CI.base_url + '/api/editor/history/'+this.ProjectID;
            
            let resp = await axios.get(url);

            console.log(resp.data);
            
            vm.history = resp.data;
            vm.is_loading=false;
        },
        momentDate(date) {
            return moment(date).format("YYYY/MM/DD hh:mm A");
        },
                
    },
    computed: {    
        ProjectID(){
            return this.$store.state.project_id;
        },
        ProjectTemplate(){
            return this.$store.state.formTemplate;
        },
        TemplateItems()
        {
            return this.ProjectTemplate.template.items;
        }
        
    },
    template: `
        <div class="vue-project-history-component m-3 mt-5 ">

            <div v-if="is_loading" class="text-center">
                <v-progress-circular
                    indeterminate
                    color="primary"
                ></v-progress-circular>
            </div>

            <div v-else>
                <div class="bg-light p-3">
                {{$t('Change log')}}   
                </div>
            </div>


            <v-simple-table v-if="history && history.history && history.history.length>0">
                <template v-slot:default>
                    <thead>
                        <tr>
                            <th class="text-left" style="width:200px">Date</th>    
                            <th class="text-left" style="width:100px">User</th>
                            <th class="text-left"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="revision in history.history">
                            <td>{{momentDate(revision.created)}}</td>
                            <td>{{revision.username}}</td>
                            <td>
                                <div style="max-height:500px;overflow:auto">                                                
                                    <vue-json-pretty :data="revision.metadata" :deep="deep" />
                                </div>                                            
                            </td>
                        </tr>
                    </tbody>
                </template>
            </v-simple-table>
            <div v-else>
                <v-alert outlined color="red">
                    {{$t("no_revisions_found")}}.
                </v-alert>
            </div>
            


        </div>
    `
});

