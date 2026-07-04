Vue.component('vue-duplicate-project-dialog', {
    props: ['value', 'project_id', 'project'],
    data() {
        return {
            is_processing: false,
        };
    },
    methods: {
        duplicateProject: function() {
            const vm = this;
            vm.is_processing = true;
            const url = CI.site_url + '/api/editor/duplicate/' + vm.project_id;

            axios.post(url, {})
                .then(function() {
                    vm.is_processing = false;
                    vm.dialog = false;
                    alert(vm.$t('project_duplicated_successfully'));
                    vm.$emit('project-duplicated', 1);
                })
                .catch(function(error) {
                    alert(vm.errorResponseMessage(error));
                    vm.is_processing = false;
                });
        },
        errorResponseMessage: function(error) {
            if (error.response && error.response.data) {
                if (error.response.data.message) {
                    return error.response.data.message;
                }
                return JSON.stringify(error.response.data);
            }
            return JSON.stringify(error);
        },
    },
    computed: {
        dialog: {
            get: function() {
                return this.value;
            },
            set: function(newValue) {
                this.$emit('input', newValue);
            },
        },
        projectTitle: function() {
            return (this.project && this.project.title) ? this.project.title : '';
        },
    },
    template: `
        <div class="vue-duplicate-project-dialog">
            <v-dialog v-model="dialog" width="480" scrollable>
                <v-card>
                    <v-card-title class="text-h6 grey lighten-2">
                        {{$t('duplicate_project')}}
                    </v-card-title>
                    <v-card-text class="pt-4">
                        <div v-if="projectTitle" class="mb-3"><strong>{{projectTitle}}</strong></div>
                        <div class="text-muted text-small">{{$t('duplicate_project_help')}}</div>
                    </v-card-text>
                    <v-divider></v-divider>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn color="secondary" text @click="dialog = false" :disabled="is_processing">
                            {{$t('close')}}
                        </v-btn>
                        <v-btn color="primary" text @click="duplicateProject" :disabled="is_processing">
                            <span v-if="!is_processing">{{$t('duplicate_project')}}</span>
                            <span v-else>{{$t('processing_please_wait')}}</span>
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </div>
    `,
});
