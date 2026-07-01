Vue.component('admin-metadata-history', {
    props: [],
    data() {
        return {
            is_loading: false,
            history: [],
            deep: 0,
            pagination: {
                page: 1,
                limit: 15,
                total: 0,
            },
        }
    },
    mounted:function(){
        this.$nextTick(() => {
            this.loadEditHistory();
        });
    },
    watch: {
        '$route'(to, from) {
            this.pagination.page = 1;
            this.loadEditHistory();
        }
    },
    methods: {
        loadEditHistory: async function()
        {
            const vm = this;
            vm.is_loading = true;

            const projectId = this.ProjectID;
            const templateUid = this.MetadataTemplateUID;

            if (projectId === null || projectId === undefined || projectId === '' ||
                templateUid === null || templateUid === undefined || templateUid === '') {
                vm.is_loading = false;
                vm.history = [];
                vm.pagination.total = 0;
                return;
            }

            const offset = (vm.pagination.page - 1) * vm.pagination.limit;
            const url = CI.base_url + '/api/admin-metadata/edit_history/' + projectId + '/' + templateUid;

            try {
                const resp = await axios.get(url, {
                    params: {
                        limit: vm.pagination.limit,
                        offset: offset,
                    },
                });

                if (resp.data && resp.data.status === 'success') {
                    if (Array.isArray(resp.data.data)) {
                        vm.history = resp.data.data;
                        vm.pagination.total = resp.data.total || 0;
                        if (resp.data.limit) {
                            vm.pagination.limit = resp.data.limit;
                        }
                    } else {
                        vm.history = [];
                        vm.pagination.total = 0;
                    }
                } else {
                    vm.history = [];
                    vm.pagination.total = 0;
                }
            } catch (error) {
                console.error('Error loading admin metadata history:', error);
                vm.history = [];
                vm.pagination.total = 0;
            } finally {
                vm.is_loading = false;
            }
        },
        onPageChange: function(page) {
            this.pagination.page = page;
            this.loadEditHistory();
        },
        momentDate(date) {
            return moment(date).format("YYYY/MM/DD hh:mm A");
        },
    },
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        },
        MetadataTemplateUID(){
            return this.$route.params.type_id;
        },
        totalPages() {
            if (!this.pagination.limit) {
                return 0;
            }
            return Math.ceil(this.pagination.total / this.pagination.limit);
        },
    },
    template: `
        <div class="vue-admin-metadata-history-component m-3 mt-5 ">
            <div class="bg-light p-3 mb-3 d-flex align-center justify-space-between">
                <div>
                    <h3 class="mb-0">{{$t('Change log')}} - Admin Metadata</h3>
                    <div v-if="MetadataTemplateUID">
                        <small>Template UID: {{MetadataTemplateUID}}</small>
                    </div>
                </div>
                <span v-if="pagination.total > 0" class="text-caption text--secondary">
                    {{ pagination.total }} {{$t('entries') || 'entries'}}
                </span>
            </div>

            <div v-if="is_loading" class="text-center">
                <v-progress-circular
                    indeterminate
                    color="primary"
                ></v-progress-circular>
                <div class="mt-2">Loading history...</div>
            </div>


            <div v-if="!is_loading">
                <v-simple-table v-if="history && history.length>0">
                    <template v-slot:default>
                        <thead>
                            <tr>
                                <th class="text-left" style="width:200px">Date</th>
                                <th class="text-left" style="width:100px">User</th>
                                <th class="text-left"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="revision in history" :key="revision.id">
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

                <div v-if="totalPages > 1" class="d-flex justify-center mt-4">
                    <v-pagination
                        v-model="pagination.page"
                        :length="totalPages"
                        :total-visible="7"
                        @input="onPageChange"
                    ></v-pagination>
                </div>

                <v-alert v-else-if="history.length === 0" outlined type="info" class="mt-3">
                    <v-icon left>mdi-information</v-icon>
                    {{$t("no_revisions_found")}}. History will appear here after you make changes to this admin metadata.
                </v-alert>
            </div>


        </div>
    `
});
