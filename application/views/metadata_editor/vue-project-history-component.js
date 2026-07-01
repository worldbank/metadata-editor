Vue.component('project-history', {
    props: [],
    data() {
        return {
            is_loading: false,
            history_entries: [],
            deep: 0,
            pagination: {
                page: 1,
                limit: 15,
                total: 0,
            },
        }
    },
    mounted:function(){
        this.loadEditHistory();
    },
    methods: {
        loadEditHistory: async function()
        {
            const vm = this;
            vm.is_loading = true;
            const offset = (vm.pagination.page - 1) * vm.pagination.limit;
            const url = CI.base_url + '/api/editor/history/' + this.ProjectID;

            try {
                const resp = await axios.get(url, {
                    params: {
                        limit: vm.pagination.limit,
                        offset: offset,
                    },
                });

                if (resp.data && resp.data.status === 'success') {
                    vm.history_entries = resp.data.history || [];
                    vm.pagination.total = resp.data.total || 0;
                    if (resp.data.limit) {
                        vm.pagination.limit = resp.data.limit;
                    }
                } else {
                    vm.history_entries = [];
                    vm.pagination.total = 0;
                }
            } catch (error) {
                console.error('Error loading project history:', error);
                vm.history_entries = [];
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
        ProjectTemplate(){
            return this.$store.state.formTemplate;
        },
        TemplateItems()
        {
            return this.ProjectTemplate.template.items;
        },
        totalPages() {
            if (!this.pagination.limit) {
                return 0;
            }
            return Math.ceil(this.pagination.total / this.pagination.limit);
        },
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
                <div class="bg-light p-3 d-flex align-center justify-space-between">
                    <span>{{$t('Change log')}}</span>
                    <span v-if="pagination.total > 0" class="text-caption text--secondary">
                        {{ pagination.total }} {{$t('entries') || 'entries'}}
                    </span>
                </div>
            </div>


            <v-simple-table v-if="!is_loading && history_entries.length > 0">
                <template v-slot:default>
                    <thead>
                        <tr>
                            <th class="text-left" style="width:200px">Date</th>
                            <th class="text-left" style="width:100px">User</th>
                            <th class="text-left"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="revision in history_entries" :key="revision.id">
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

            <div v-if="!is_loading && totalPages > 1" class="d-flex justify-center mt-4">
                <v-pagination
                    v-model="pagination.page"
                    :length="totalPages"
                    :total-visible="7"
                    @input="onPageChange"
                ></v-pagination>
            </div>

            <div v-if="!is_loading && history_entries.length === 0">
                <v-alert outlined color="red">
                    {{$t("no_revisions_found")}}.
                </v-alert>
            </div>


        </div>
    `
});
