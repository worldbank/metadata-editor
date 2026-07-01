Vue.component('vue-template-revision-history', {
    props: ['value','template_id'],
    data() {
        return {
            is_loading: false,
            revisions: {
                history: [],
                total: 0,
                limit: 15,
                offset: 0,
            },
            deep: 0,
            pagination: {
                page: 1,
                limit: 15,
            },
        }
    },
    created:function(){
        this.loadRevisions();
    },
    methods: {
        loadRevisions: function(){
            const vm = this;
            vm.is_loading = true;
            const offset = (vm.pagination.page - 1) * vm.pagination.limit;
            const url = CI.site_url + '/api/templates/revisions/' + this.template_id;

            axios.get(url, {
                params: {
                    limit: vm.pagination.limit,
                    offset: offset,
                },
            })
            .then(response => {
                if (response.data && response.data.data) {
                    vm.revisions = response.data.data;
                    vm.pagination.limit = vm.revisions.limit || vm.pagination.limit;
                } else {
                    vm.revisions = { history: [], total: 0, limit: vm.pagination.limit, offset: 0 };
                }
            })
            .catch(function (error) {
                console.log(error);
                vm.revisions = { history: [], total: 0, limit: vm.pagination.limit, offset: 0 };
            })
            .finally(() => {
                vm.is_loading = false;
            });
        },
        onPageChange: function(page) {
            this.pagination.page = page;
            this.loadRevisions();
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
       totalPages() {
            const total = this.revisions.total || 0;
            const limit = this.revisions.limit || this.pagination.limit || 15;
            if (!limit) {
                return 0;
            }
            return Math.ceil(total / limit);
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
                    <v-card-title class="grey lighten-2 py-3" style="display: flex; align-items: center; width: 100%;">
                    <span class="text-h5">{{$t('revision_history')}}</span>
                    <v-spacer></v-spacer>
                    <span v-if="revisions.total > 0" class="text-caption text--secondary">{{ revisions.total }} {{$t('entries') || 'entries'}}</span>
                    </v-card-title>
                    <v-card-text>
                        <div v-if="is_loading" class="text-center py-6">
                            <v-progress-circular indeterminate color="primary"></v-progress-circular>
                        </div>

                        <v-simple-table v-else-if="revisions && revisions.history && revisions.history.length>0">
                            <template v-slot:default>
                                <thead>
                                    <tr>
                                        <th class="text-left" style="width:200px">{{$t('date')}}</th>
                                        <th class="text-left" style="width:100px">{{$t('user')}}</th>
                                        <th class="text-left"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="revision in revisions.history" :key="revision.id">
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

                        <div v-if="!is_loading && totalPages > 1" class="d-flex justify-center mt-4">
                            <v-pagination
                                v-model="pagination.page"
                                :length="totalPages"
                                :total-visible="7"
                                @input="onPageChange"
                            ></v-pagination>
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
