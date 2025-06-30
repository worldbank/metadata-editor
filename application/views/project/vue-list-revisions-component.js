Vue.component('vue-list-revisions', {
    props: ['value', 'revisions'],
    data() {
        return {
        }
    },
    methods: {
        EditProject(id){
            this.$emit('edit-project', id);
        },
        DeleteProject(id){
            this.$emit('delete-project', id);
        }
    },   
    template: `
        <div class="vue-project-list-revisions">

            <div v-if="revisions && revisions.length>0" style="background:white;overflow:auto;max-height:300px;"> 

                <template>
                    <v-expansion-panels>
                        <v-expansion-panel
                        v-for="revision in revisions"
                        :key="revision.id"
                        >
                        <v-expansion-panel-header>
                            <div><v-icon small>mdi-lock-outline</v-icon> v{{revision.version_number}}</div>
                            {{momentDateLong(revision.version_created)}}
                        </v-expansion-panel-header>
                        <v-expansion-panel-content>
                            <div class="p-3">
                                <div class="mt-2 mb-3 bg-light p-2">
                                    <div><strong>Version notes</strong></div>
                                        <div class="text-white-space mt-1" style="max-height:500px;overflow:auto;white-space: pre-wrap;"  v-if="revision.version_notes">{{revision.version_notes}}</div>
                                        <div v-else>N/A</div>
                                </div>

                                <div class="mb-2 text-muted" style="font-size:small;">
                                    <strong>IDNO: </strong>{{revision.idno}}                                    
                                    <strong class="ml-2">{{$t('created_by')}}: </strong>{{revision.version_created_by_name}}                                
                                </div>
                                                                    
                                <div class="border-top pt-2">
                                    <v-btn color="primary" outlined x-small @click="EditProject(revision.id)">{{$t('view')}}</v-btn>
                                    <v-btn color="primary" outlined x-small @click="DeleteProject(revision.id)">{{$t('delete')}}</v-btn>
                                </div>                        
                            </div>                        
                        </v-expansion-panel-content>
                        </v-expansion-panel>
                    </v-expansion-panels>
                </template>
                
            </div>
        
    </div>
    `
});

