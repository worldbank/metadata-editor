// Projects using a global data structure (editor_project_dsd).
Vue.component('data-structure-projects', {
    props: {
        id: { type: [String, Number], required: true }
    },
    data: function () {
        return {
            loading: false,
            projects: [],
            total: 0,
            page: 1,
            itemsPerPage: 25,
            structureLabel: '',
            headers: [
                { text: 'ID', value: 'id', sortable: false, width: '72px' },
                { text: 'Title', value: 'title', sortable: false },
                { text: 'Type', value: 'type', sortable: false, width: '100px' },
                { text: 'Published', value: 'published', sortable: false, width: '100px' }
            ],
            tableOptions: { page: 1, itemsPerPage: 25 }
        };
    },
    computed: {
        numericId: function () {
            return parseInt(this.id, 10);
        }
    },
    watch: {
        id: function () {
            this.bootstrap();
        },
        tableOptions: {
            deep: true,
            handler: function () {
                this.loadProjects();
            }
        }
    },
    mounted: function () {
        this.bootstrap();
    },
    methods: {
        apiBase: function () {
            return ((typeof CI !== 'undefined' && CI.site_url) ? CI.site_url.replace(/\/$/, '') : '')
                + '/api/data_structures';
        },
        notifyFail: function (err) {
            var m = 'Request failed';
            if (err.response && err.response.data && err.response.data.message) {
                m = err.response.data.message;
            }
            if (typeof EventBus !== 'undefined') {
                EventBus.$emit('onFail', m);
            }
        },
        bootstrap: function () {
            var vm = this;
            if (!vm.numericId || isNaN(vm.numericId)) {
                return;
            }
            axios.get(vm.apiBase() + '/single/' + vm.numericId)
                .then(function (res) {
                    if (res.data && res.data.status === 'success' && res.data.data_structure) {
                        var ds = res.data.data_structure;
                        vm.structureLabel = (ds.title || ds.name || '') + ' (' + (ds.agency || '') + ' / ' + (ds.version || '') + ')';
                    }
                })
                .catch(function () { /* ignore */ });
            vm.loadProjects();
        },
        loadProjects: function () {
            var vm = this;
            var page = (vm.tableOptions && vm.tableOptions.page) ? vm.tableOptions.page : 1;
            var perPage = (vm.tableOptions && vm.tableOptions.itemsPerPage) ? vm.tableOptions.itemsPerPage : 25;
            vm.page = page;
            vm.itemsPerPage = perPage;
            vm.loading = true;
            axios.get(vm.apiBase() + '/projects/' + vm.numericId, {
                params: { page: page, per_page: perPage }
            })
                .then(function (res) {
                    vm.loading = false;
                    if (res.data && res.data.status === 'success') {
                        vm.projects = res.data.projects || [];
                        vm.total = res.data.total != null ? res.data.total : vm.projects.length;
                    }
                })
                .catch(function (err) {
                    vm.loading = false;
                    vm.notifyFail(err);
                });
        },
        back: function () {
            this.$router.push('/');
        },
        projectUrl: function (item) {
            var base = (typeof CI !== 'undefined' && CI.site_url) ? CI.site_url.replace(/\/$/, '') : '';
            return base + '/editor/edit/' + item.id;
        }
    },
    template: `
        <div>
            <v-btn text class="mb-2" @click="back"><v-icon left>mdi-arrow-left</v-icon> Back</v-btn>
            <v-card class="mb-4">
                <v-card-title class="text-subtitle-1">
                    Projects using this data structure
                    <span v-if="structureLabel" class="text-body-2 grey--text ml-2">{{ structureLabel }}</span>
                </v-card-title>
                <v-data-table
                    :headers="headers"
                    :items="projects"
                    :loading="loading"
                    :options.sync="tableOptions"
                    :server-items-length="total"
                    :footer-props="{ 'items-per-page-options': [10, 25, 50, 100] }"
                    disable-sort
                    class="elevation-0"
                >
                    <template v-slot:item.title="{ item }">
                        <a :href="projectUrl(item)" class="text-decoration-none" target="_blank" rel="noopener">
                            {{ item.title || item.id }}
                        </a>
                    </template>
                    <template v-slot:item.published="{ item }">
                        <v-chip x-small :color="Number(item.published) === 1 ? 'green' : 'grey'" dark>
                            {{ Number(item.published) === 1 ? 'Yes' : 'No' }}
                        </v-chip>
                    </template>
                </v-data-table>
            </v-card>
        </div>
    `
});
