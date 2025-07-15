Vue.component('vue-create-revision-dialog', {
    props: ['value', 'project_id', 'project'],
    data() {
        return {
            revision: {},
            is_processing: false
        }
    },
    created:function(){
        this.revision = {
            sid: this.project_id,
            version_type: 'minor',
            version_notes: ''
        }
    },
    methods: {    
        CreateRevision: function(){
            this.is_processing = true;
            vm=this;
            let url=CI.site_url + '/api/versions/create';
            let options={
                "sid": vm.project_id,
                "version_type": vm.revision.version_type,
                "version_notes": vm.revision.version_notes
            }

            axios.post( url,
                options
            ).then(function(response){                
                vm.is_processing = false;
                vm.dialog=false;
                alert(vm.$t('version_created_successfully'));
                vm.$emit('revision-created',1);
            })
            .catch(function(response){
                vm.errors=response;
                alert(vm.errorResponseMessage(response));
                vm.is_processing = false;
            }); 
        },
        errorResponseMessage: function(error) {
            if (error.response.data.error) {
                return error.response.data.error;
            }
    
            if (error.response){
                return JSON.stringify(error.response.data);
            }
    
            return JSON.stringify(error);
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
        <div class="vue-project-create-revision">

        <v-app>
        <template>        
            <div class="text-center">
                <v-dialog
                v-model="dialog"
                fullscreen
                scrollable
                >

                <v-card class="no-radius">
                    <v-card-title class="text-h5 white--text primary" >
                        {{project.title}}
                    </v-card-title>
                    <v-card-text class="mt-3">
                        <h6 class="h3 mb-2">{{$t('create_version')}}</h6>
                        <v-row>
                            <v-col cols="9">
                                <label class="mt-3">{{$t('version_type')}}</label>
                                <v-select
                                    v-model="revision.version_type"
                                    :items="['major','minor', 'patch']"
                                    label=""
                                    outlined
                                    dense
                                ></v-select>

                                <label class="mt-3">{{$t('version_notes')}}</label>
                                <v-textarea
                                    v-model="revision.version_notes"
                                    label=""
                                    outlined
                                    dense
                                    rows="10"
                                ></v-textarea>

                                <v-spacer class="mt-3"></v-spacer>

                                <v-btn
                                    class="ma-2 mr-1"
                                    color="primary"
                                    small
                                    
                                    @click="CreateRevision"
                                >
                                    <span v-if="!is_processing">{{$t('create_version')}}</span>
                                    <span v-else>Processing...</span>

                                </v-btn>
                                <v-btn
                                    class="ma-2"
                                    color="default"
                                    small
                                    v-if="!is_processing"
                                    @click="selected=[];dialog = false"
                                >
                                    {{$t('close')}}
                                </v-btn>


                                <v-divider v-if="project.versions.length > 0" class="mt-5"></v-divider>

                                <!-- show all versions for this project -->
                                <v-card-text v-if="project.versions.length > 0">
                                    <h6 class="h3 mb-5">{{$t('version_history')}}</h6>
                                    <v-row v-for="version in project.versions" :key="version.id" class="mb-3">
                                        <v-col cols="2">
                                            <div class="h6">
                                                {{momentDate(version.version_created)}}
                                            </div>
                                            <div class="text-caption text-muted">
                                                {{version.version_created_by_name}}
                                            </div>
                                        </v-col>
                                        <v-col cols="10">
                                            <div class="d-flex align-start border p-3 rounded">
                                                <div class="flex-grow-1">
                                                    <div class="h4 mb-1">V{{version.version_number}}</div>
                                                    <div class="text-body-2" v-if="version.version_notes" style="white-space: pre-wrap;">{{version.version_notes}}</div>
                                                    <div class="text-caption text-muted" v-else>-</div>
                                                </div>
                                            </div>
                                        </v-col>
                                    </v-row>
                                </v-card-text>


                            </v-col>
                            <v-col cols="3">
                                <div class="pa-4">
                                    <h6 class="text-h6 mb-3">{{$t('version_information')}}</h6>
                                    <div class="mb-3">
                                        <strong><v-icon small>mdi-information</v-icon> {{$t('version_type')}}</strong><br>
                                        <span class="text-caption text-muted">
                                            <div v-html="$t('version_type_help')"></div>
                                        </span>
                                    </div>
                                </div>                                

                            </v-col>
                        </v-row>
                    </v-card-text>

                </v-card>
                </v-dialog>
            </div>
        </template>
        </v-app>
        
    </div>
    `
});

