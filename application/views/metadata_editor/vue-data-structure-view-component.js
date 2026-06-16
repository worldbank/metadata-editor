Vue.component('data-structure-view', {
    props: { id: { type: [String, Number], required: true } },
    data: function () {
        return {
            loading: false,
            structure: null,
            components: [],
            compHeaders: [
                { text: 'Order', value: 'sort_order' },
                { text: 'Name', value: 'name' },
                { text: 'Label', value: 'label' },
                { text: 'Column type', value: 'column_type' },
                { text: 'Data type', value: 'data_type' },
                { text: 'Codelist', value: 'codelist_ref' }
            ]
        };
    },
    computed: {
        numericId: function () {
            return parseInt(this.id, 10);
        },
        canEdit: function () {
            if (!this.structure) {
                return false;
            }
            var s = (this.structure.status || 'draft').toLowerCase();
            return s !== 'published' && s !== 'archived';
        }
    },
    watch: {
        id: function () { this.load(); }
    },
    mounted: function () { this.load(); },
    methods: {
        apiBase: function () {
            return ((typeof CI !== 'undefined' && CI.site_url) ? CI.site_url.replace(/\/$/, '') : '') + '/api/data_structures';
        },
        load: function () {
            var vm = this;
            vm.loading = true;
            axios.get(vm.apiBase() + '/single/' + vm.numericId + '?with_components=1')
                .then(function (res) {
                    vm.loading = false;
                    if (res.data && res.data.status === 'success' && res.data.data_structure) {
                        var ds = res.data.data_structure;
                        vm.structure = ds;
                        vm.components = (ds.components || []).map(function (c) {
                            var ref = '';
                            if (c.codelist_reference && c.codelist_reference.idno) {
                                ref = c.codelist_reference.idno;
                            } else if (c.codelist_reference && c.codelist_reference.name) {
                                ref = (c.codelist_reference.agency || '') + ':' + c.codelist_reference.name;
                            }
                            return Object.assign({}, c, { codelist_ref: ref });
                        });
                    }
                })
                .catch(function (err) {
                    vm.loading = false;
                    if (typeof EventBus !== 'undefined') {
                        EventBus.$emit('onFail', (err.response && err.response.data && err.response.data.message) || 'Load failed');
                    }
                });
        },
        exportUrl: function () {
            return this.apiBase() + '/export/' + this.numericId + '?download=1';
        },
        back: function () {
            this.$router.push('/');
        },
        goEdit: function () {
            this.$router.push('/edit/' + this.numericId);
        }
    },
    template: `
        <div>
            <v-btn text class="mb-2" @click="back"><v-icon left>mdi-arrow-left</v-icon> Back</v-btn>
            <v-progress-linear v-if="loading" indeterminate class="mb-4"></v-progress-linear>
            <v-card v-if="structure && !loading" class="mb-4">
                <v-card-title class="d-flex align-center">
                    <span>{{ structure.title || structure.name }}</span>
                    <v-spacer></v-spacer>
                    <v-btn v-if="canEdit" color="primary" outlined small class="mr-2" @click="goEdit">Edit</v-btn>
                    <v-btn color="primary" outlined small :href="exportUrl()" target="_blank">Export JSON</v-btn>
                </v-card-title>
                <v-card-text>
                    <v-simple-table dense>
                        <tbody>
                            <tr><td class="grey--text" width="160">Idno</td><td>{{ structure.idno }}</td></tr>
                            <tr><td class="grey--text">Agency</td><td>{{ structure.agency }}</td></tr>
                            <tr><td class="grey--text">Name</td><td>{{ structure.name }}</td></tr>
                            <tr><td class="grey--text">Version</td><td>{{ structure.version }}</td></tr>
                            <tr><td class="grey--text">Status</td><td>{{ structure.status }}</td></tr>
                            <tr v-if="structure.description"><td class="grey--text">Description</td><td>{{ structure.description }}</td></tr>
                        </tbody>
                    </v-simple-table>
                </v-card-text>
            </v-card>
            <v-card v-if="structure && !loading" class="mb-4">
                <v-card-title class="text-subtitle-1">Components ({{ components.length }})</v-card-title>
                <v-data-table :headers="compHeaders" :items="components" :items-per-page="50" dense class="elevation-0"></v-data-table>
            </v-card>
            <data-structure-validation-panel v-if="structure && !loading" :structure-id="numericId"></data-structure-validation-panel>
        </div>
    `
});
