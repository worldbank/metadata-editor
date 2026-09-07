Vue.component('publish-hub', {
    data: function () {
        return {
            activeTab: 0,
            queueTabSeen: false,
            historyTabSeen: false,
        };
    },
    computed: {
        routePublicationId: function () {
            var query = this.$route && this.$route.query ? this.$route.query : {};
            if (query.publication_id || query.publication_id === 0) {
                var routeId = parseInt(String(query.publication_id), 10);
                if (!isNaN(routeId) && routeId > 0) {
                    return routeId;
                }
            }
            var hash = window.location.hash || '';
            var qIndex = hash.indexOf('?');
            if (qIndex === -1) {
                return 0;
            }
            try {
                var params = new URLSearchParams(hash.substring(qIndex + 1));
                var hashId = parseInt(String(params.get('publication_id') || ''), 10);
                return isNaN(hashId) || hashId < 1 ? 0 : hashId;
            } catch (e) {
                return 0;
            }
        },
    },
    watch: {
        '$route.query.tab': function () {
            this.syncTabFromRoute();
        },
        '$route.query.publication_id': function () {
            this.triggerPublicationPrefill();
        },
        activeTab: function (tab) {
            if (tab === 1) {
                this.queueTabSeen = true;
            }
            if (tab === 2) {
                this.historyTabSeen = true;
            }
            if (tab === 0) {
                this.triggerPublicationPrefill();
            }
            this.updateRouteTab();
        },
    },
    mounted: function () {
        this.syncTabFromRoute();
        var vm = this;
        if (this.$router && typeof this.$router.onReady === 'function') {
            this.$router.onReady(function () {
                vm.triggerPublicationPrefill();
            });
        } else {
            this.$nextTick(function () {
                vm.triggerPublicationPrefill();
            });
        }
    },
    methods: {
        tabNameForIndex: function (index) {
            return ['nada', 'queue', 'history'][index] || 'nada';
        },
        tabIndexFromQuery: function (value) {
            var map = {
                nada: 0,
                publish: 0,
                queue: 1,
                publications: 1,
                history: 2,
            };
            var key = value ? String(value).toLowerCase() : 'nada';
            return Object.prototype.hasOwnProperty.call(map, key) ? map[key] : 0;
        },
        syncTabFromRoute: function () {
            var queryTab = this.$route && this.$route.query ? this.$route.query.tab : null;
            var tab = this.tabIndexFromQuery(queryTab);
            this.activeTab = tab;
            if (tab === 1) {
                this.queueTabSeen = true;
            }
            if (tab === 2) {
                this.historyTabSeen = true;
            }
        },
        updateRouteTab: function () {
            if (!this.$router || !this.$route) {
                return;
            }
            var tab = this.tabNameForIndex(this.activeTab);
            if (this.$route.query.tab === tab) {
                return;
            }
            var query = Object.assign({}, this.$route.query, { tab: tab });
            this.$router.replace({ path: '/publish', query: query }).catch(function () {});
        },
        triggerPublicationPrefill: function () {
            if (this.activeTab !== 0) {
                return;
            }
            var vm = this;
            this.$nextTick(function () {
                var panel = vm.$refs.publishOptions;
                if (panel && typeof panel.schedulePublicationPrefill === 'function') {
                    panel.schedulePublicationPrefill();
                }
            });
        },
        onHistoryChanged: function () {
            var historyPanel = this.$refs.historyPanel;
            if (historyPanel && typeof historyPanel.loadHistory === 'function') {
                historyPanel.loadHistory();
            }
            var queuePanel = this.$refs.queuePanel;
            if (queuePanel && typeof queuePanel.loadContext === 'function') {
                queuePanel.loadContext();
            }
        },
    },
    template: `
    <div class="import-options-component mt-5 p-3 publish-hub">
        <v-card>
            <v-card-title class="py-3">
                <div>{{ $t('publish_to_catalog') || 'Publish to catalog' }}</div>
            </v-card-title>
            <v-card-text class="pt-0">
                <v-tabs v-model="activeTab" class="mb-3">
                    <v-tab>{{ $t('publish_to_nada') || 'Publish to NADA' }}</v-tab>
                    <v-tab>{{ $t('publication_queue_tab') || 'Publish requests' }}</v-tab>
                    <v-tab>{{ $t('publication_history_tab') || 'History' }}</v-tab>
                </v-tabs>
                <v-tabs-items v-model="activeTab">
                    <v-tab-item eager>
                        <publish-options
                            ref="publishOptions"
                            embedded
                            :publication-id="routePublicationId"
                        ></publish-options>
                    </v-tab-item>
                    <v-tab-item>
                        <catalog-publications
                            v-if="queueTabSeen"
                            ref="queuePanel"
                            embedded-panel="queue"
                            @history-changed="onHistoryChanged"
                        ></catalog-publications>
                    </v-tab-item>
                    <v-tab-item>
                        <catalog-publications
                            v-if="historyTabSeen"
                            ref="historyPanel"
                            embedded-panel="history"
                            :panel-active="activeTab === 2"
                        ></catalog-publications>
                    </v-tab-item>
                </v-tabs-items>
            </v-card-text>
        </v-card>
    </div>
    `,
});
