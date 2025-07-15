Vue.component('vue-template-revision-history', {
    props: ['value','template_id'],
    data() {
        return {            
            is_loading: false,
            revisions: [],
            deep:0
        }
    },
    created:function(){
        this.loadRevisions();
    },
    methods: {     
        loadRevisions: function(){
            let vm = this;
            let url = CI.site_url + '/api/templates/revisions/' + this.template_id;
            console.log(url);
            axios.get(url)
            .then(response => {
                vm.revisions = response.data.data;
            })
            .catch(function (error) {
                console.log(error);
            })
            .finally(() => (this.is_loading = false));
        },
        momentDate(date) {
            return moment(date).format("MM/DD/YYYY hh:mm A");
        },
        toggleJson: function(){
            this.deep = this.deep == 0 ? 4 : 0;
        }
    },
    computed:{
        dialog: {
            get: function () {
                return this.value;
            },
            set: function (newValue) {
                this.$emit('input', newValue);               
            }
       },
    },
    template: `
        <div class="vue-project-revision-history">

        <template>        
            <div class="text-center">
                <v-dialog
                v-model="dialog"
                width="100%"              
                height="100%"  
                              
                >

                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                    {{$t('revision_history')}}
                    </v-card-title>
                    <v-card-text>
                        <v-simple-table v-if="revisions && revisions.history && revisions.history.length>0">
                            <template v-slot:default>
                                <thead>
                                    <tr>
                                        <th class="text-left" style="width:200px">{{$t('date')}}</th>    
                                        <th class="text-left" style="width:100px">{{$t('user')}}</th>
                                        <th class="text-left"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="revision in revisions.history">
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
                                {{$t('no_revisions_found')}}
                            </v-alert>
                        </div>

                        
                    </v-card-text>

                    <v-divider></v-divider>

                    <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn
                        class="ma-2"
                        outlined
                        color="indigo"
                        small
                        @click=";dialog = false"
                    >
                        {{$t('close')}}
                    </v-btn>
                    </v-card-actions>
                    
                </v-card>
                </v-dialog>
            </div>
        </template>
        
    </div>
    `
});

