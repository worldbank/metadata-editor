///Project sharing summary component - users + collections
Vue.component('vue-summary-sharing-stats', {
    props:[],
    data: function () {    
        return {
            project_users: [],
            dialog_share_project: false,            
        }
    },
    created: function(){    
        this.loadProjectUsers();
    },

    watch:{
        dialog_share_project: function(){
            if(this.dialog_share_project == false){
                this.loadProjectUsers();
            }
        }
    },
    
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        },
    },
    methods:{
        loadProjectUsers: function(){
            let vm = this;
            let url = CI.base_url + '/api/share/list/' + this.ProjectID;

            axios.get(url)
            .then(response => {
                vm.project_users = response.data.users;
            })
            .catch(function (error) {
                console.log(error);
            });            
        },
    },    
    template: `
    <div class="project-summary-sharing-component">
        
        <div class="component-container">

            <v-card>
            <v-card-title class="d-flex justify-space-between">
                <h6>{{$t("Collaborators")}}</h6>
                    <v-btn icon @click="dialog_share_project=true">
                    <v-icon>mdi-account-supervisor</v-icon>
                </v-btn>
            </v-card-title>

            <v-card-text>
                <div v-if="project_users.length==0" class="text-muted text-secondary">
                    {{$t("None")}}
                </div>
                
                <v-simple-table style="font-size:small;" v-if="project_users.length>0">
                    <thead>
                    <tr>
                        <th>Username</th>
                        <th>Role</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(user,index) in project_users" :key="index">
                        <td>
                            <div class="capitalize">{{user.username}}</div>
                            <div class="text-muted text-secondary">{{user.email}}</div>
                        </td>
                        <td class="text-muted text-secondary">
                            {{user.permissions}}
                        </td>                    
                    </tr>
                    </tbody>
                </v-simple-table>


            </v-card-text>
            </v-card>

            

        </div>

        <vue-project-share v-model="dialog_share_project" :project_id="ProjectID"></vue-project-share>

    </div>          
    `    
});

