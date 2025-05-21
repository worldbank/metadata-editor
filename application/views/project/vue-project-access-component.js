Vue.component('vue-project-access-dialog', {
    props: ['value', 'project_access'],
    data() {
        return {            
            selected: [],
            user_access: 'view',
            user_selected:''
        }
    },
    created:function(){
    },
    methods: {        
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
        <div class="vue-project-share">

        <template>        
            <div class="text-center">
                <v-dialog
                v-model="dialog"
                width="600px"
                scrollable
                >

                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        {{ $t('project_permissions') }}
                    </v-card-title>
                    <v-card-text>
                        <div v-if="project_access">

                            <strong>{{ $t('project_owner') }}</strong>: {{project_access.owner.username}}
                            
                            <table class="table table-sm table-striped mt-3">
                                <tr>                                    
                                    <td>
                                        <div>
                                        <strong>{{ $t('collaborators') }} <span v-if="project_access.collaborators && project_access.collaborators.length > 0">({{project_access.collaborators.length}})</span> </strong>
                                        </div>
                                    </td>
                                    <td>
                                        <div v-if="project_access.collaborators && project_access.collaborators.length==0">
                                            <em>None</em>
                                        </div>
                                    </td>
                                    <td></td>                                    
                                </tr>
                                <tr  v-for="(user, index) in project_access.collaborators" :key="index">
                                    <td><span :title="user.email">{{user.username}}</span></td>
                                    <td>{{user.permissions}}</td>
                                    <td></td>
                                </tr>                                
                                <tr  class="pt-2">                                    
                                    <td>
                                        <div>
                                            <strong>{{$t('access_by_collections')}} <span v-if="project_access.collections && project_access.collections.length > 0">({{project_access.collections.length}})</span></strong>
                                        </div>
                                    </td>                                        
                                    <td>
                                        <div v-if="project_access.collections && project_access.collections.length==0">
                                            <em>None</em>
                                        </div>
                                    </td>
                                    <td></td>
                                    
                                </tr>
                                <tr v-for="(user_) in project_access.collections" >
                                    <td><span :title="user_.email">{{user_.username}}</span></td>
                                    <td>{{user_.permissions}}</td>
                                    <td>{{user_.title}}</td>
                                </tr>
                            </table>
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
                        @click="selected=[];dialog = false"
                    >
                        {{ $t('close') }}
                    </v-btn>
                    </v-card-actions>
                    
                </v-card>
                </v-dialog>
            </div>
        </template>
        
    </div>
    `
});

