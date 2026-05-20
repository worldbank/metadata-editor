// Indicator DSD Chart Visualization Component
var INDICATOR_CHART_LINE_COLORS = ['#1976D2', '#4CAF50', '#FF9800', '#9C27B0', '#F44336', '#00BCD4', '#FFC107', '#795548'];

Vue.component('indicator-dsd-chart', {
    props: [],
    data() {
        return {
            dataset_id: project_sid,
            dataset_idno: project_idno,
            dataset_type: project_type,
            loading: false,
            chart: null,
            chartLegendItems: [],
            rawData: null, // Raw records from API
            filterOptions: {
                geography: [],
                time_period: {
                    min: null,
                    max: null,
                    values: []
                }
            },
            /** SDMX core: FREQ column when periodicity exists and has a resolved codelist */
            coreFacetFreq: null,
            /** column_type === dimension or measure — facets like SDMX slice dimensions; items may be empty (combobox) */
            facetDimensions: [],
            /** column_type === attribute, only if codelist */
            facetAttributes: [],
            /** column_type === annotation, only if codelist */
            facetAnnotations: [],
            /** Slice filters except geography (keys: FREQ + dimensions/measures + attributes + annotations) */
            dimensionFilters: {},
            filterOptionsError: null,
            /** DSD column name for geography (facet count merge); null if no geography codelist */
            geographyColumnName: null,
            filters: {
                geography: [],
                time_period_start: null,
                time_period_end: null
            },
            error: null,
            activeTab: 0, // 0 = chart, 1 = table
            suppressFilterAutoApply: false,
            _filterApplyTimer: null
        }
    },
    watch: {
        filters: {
            deep: true,
            handler: function() {
                this.scheduleAutoApplyFilters();
            }
        },
        dimensionFilters: {
            deep: true,
            handler: function() {
                this.scheduleAutoApplyFilters();
            }
        }
    },
    created: async function() {
        await this.loadChartFilterOptions();
    },
    mounted: function() {
        // Watch for tab changes and resize chart when the chart tab becomes visible
        this.$watch('activeTab', (newVal) => {
            if (newVal === 0 && this.chart) {
                this.$nextTick(() => {
                    if (this.chart) {
                        setTimeout(() => {
                            this.chart.resize();
                        }, 100);
                    }
                });
            }
        });

        // Resize chart on window resize while chart tab is active
        const resizeHandler = () => {
            if (this.chart && this.activeTab === 0) {
                this.chart.resize();
            }
        };
        window.addEventListener('resize', resizeHandler);
        this._resizeHandler = resizeHandler;

        // Inject compact tab spacing overrides for Vuetify 2 tabs
        const style = document.createElement('style');
        style.textContent = `
            .indicator-dsd-chart-component .chart-tabs-header {
                margin: 0 !important;
                padding: 0 !important;
            }
            .indicator-dsd-chart-component .chart-tabs-header .v-tabs-bar {
                margin: 0 !important;
                padding: 0 !important;
                min-height: 48px !important;
                height: 48px !important;
            }
            .indicator-dsd-chart-component .chart-tabs-header .v-tab {
                margin: 0 !important;
                padding: 0 12px !important;
                min-height: 48px !important;
                height: 48px !important;
            }
            .indicator-dsd-chart-component .chart-tabs-header .v-tabs-slider-wrapper {
                margin: 0 !important;
            }
            .indicator-dsd-chart-component .chart-tabs-items {
                margin: 0 !important;
                padding: 0 !important;
            }
            .indicator-dsd-chart-component .chart-tabs-items .v-window__container {
                height: 100% !important;
                padding: 0 !important;
            }
            .indicator-dsd-chart-component .chart-tabs-items .v-window-item {
                height: 100% !important;
            }
            .indicator-dsd-chart-component .chart-tab {
                height: 100% !important;
                padding: 0 !important;
            }
            .indicator-dsd-chart-component .tab-pane {
                height: 100% !important;
                padding: 16px;
                box-sizing: border-box;
            }
            .indicator-dsd-chart-component .tab-pane--center {
                display: grid;
                place-items: center;
                text-align: center;
            }
            .indicator-dsd-chart-component .tab-pane--scroll {
                overflow: auto;
            }
            .indicator-dsd-chart-component .tab-pane--chart {
                display: flex;
                flex-direction: column;
                height: 100%;
                min-height: 0;
                padding: 8px 16px 16px;
                box-sizing: border-box;
                overflow-y: auto;
            }
            .indicator-dsd-chart-component .chart-series-summary {
                flex: 0 0 auto;
                font-size: 0.8125rem;
                color: rgba(0, 0, 0, 0.6);
                margin-bottom: 8px;
            }
            .indicator-dsd-chart-component .chart-plot-area {
                position: relative;
                flex: 0 0 auto;
                width: 100%;
                min-height: 480px;
                height: 480px;
            }
            .indicator-dsd-chart-component .chart-plot-area canvas {
                width: 100% !important;
                height: 100% !important;
                display: block;
            }
            .indicator-dsd-chart-component .chart-legend-panel {
                flex: 0 0 auto;
                display: flex;
                flex-wrap: wrap;
                align-content: flex-start;
                gap: 10px 18px;
                margin-top: 12px;
                padding-top: 12px;
                border-top: 1px solid rgba(0, 0, 0, 0.08);
            }
            .indicator-dsd-chart-component .chart-legend-item {
                display: inline-flex;
                align-items: flex-start;
                gap: 8px;
                max-width: 100%;
            }
            .indicator-dsd-chart-component .chart-legend-swatch {
                width: 16px;
                height: 4px;
                border-radius: 2px;
                margin-top: 7px;
                flex-shrink: 0;
            }
            .indicator-dsd-chart-component .chart-legend-label {
                font-size: 0.8125rem;
                line-height: 1.35;
                color: rgba(0, 0, 0, 0.87);
                word-break: break-word;
            }
            .indicator-dsd-chart-component .facet-group-title {
                font-size: 0.75rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                color: rgba(0,0,0,0.55);
                margin-top: 12px;
                margin-bottom: 8px;
            }
            .indicator-dsd-chart-component .facet-group-title:first-of-type {
                margin-top: 0;
            }
        `;
        document.head.appendChild(style);
        this._customStyle = style;
    },
    beforeDestroy: function() {
        // Clean up resize listener and injected style
        if (this._resizeHandler) {
            window.removeEventListener('resize', this._resizeHandler);
        }
        if (this.chart) {
            this.chart.destroy();
        }
        if (this._customStyle && this._customStyle.parentNode) {
            this._customStyle.parentNode.removeChild(this._customStyle);
        }
        if (this._filterApplyTimer) {
            clearTimeout(this._filterApplyTimer);
            this._filterApplyTimer = null;
        }
    },
    methods: {
        loadChartFilterOptions: async function() {
            const vm = this;
            vm.suppressFilterAutoApply = true;
            try {
                const response = await axios.get(
                    CI.base_url + '/api/indicator_dsd/chart_filter_options/' + vm.dataset_id
                );
                const data = (response.data && response.data.data) ? response.data.data : {};
                const dimFilters = {};

                vm.geographyColumnName = data.geography_column || null;
                vm.filterOptions.geography = Array.isArray(data.geography_options) ? data.geography_options : [];

                vm.coreFacetFreq = data.periodicity_facet || null;
                if (vm.coreFacetFreq && vm.coreFacetFreq.name) {
                    dimFilters[vm.coreFacetFreq.name] = [];
                }

                vm.facetDimensions = Array.isArray(data.dimension_facets) ? data.dimension_facets : [];
                vm.facetDimensions.forEach(function(col) {
                    if (col && col.name) {
                        dimFilters[col.name] = [];
                    }
                });

                vm.facetAttributes = Array.isArray(data.attribute_facets) ? data.attribute_facets : [];
                vm.facetAttributes.forEach(function(col) {
                    if (col && col.name) {
                        dimFilters[col.name] = [];
                    }
                });

                vm.facetAnnotations = Array.isArray(data.annotation_facets) ? data.annotation_facets : [];
                vm.facetAnnotations.forEach(function(col) {
                    if (col && col.name) {
                        dimFilters[col.name] = [];
                    }
                });

                vm.dimensionFilters = dimFilters;
                vm.filterOptionsError = null;
            } catch (error) {
                console.error('Error loading chart filter options:', error);
                vm.filterOptionsError = (error.response && error.response.data && error.response.data.message)
                    || error.message
                    || 'Could not load filter options';
                vm.filterOptions.geography = [];
                vm.geographyColumnName = null;
                vm.coreFacetFreq = null;
                vm.facetDimensions = [];
                vm.facetAttributes = [];
                vm.facetAnnotations = [];
                vm.dimensionFilters = {};
            } finally {
                vm.suppressFilterAutoApply = false;
            }
        },
        scheduleAutoApplyFilters: function() {
            if (this.suppressFilterAutoApply) {
                return;
            }
            if (this._filterApplyTimer) {
                clearTimeout(this._filterApplyTimer);
            }
            const vm = this;
            this._filterApplyTimer = setTimeout(function() {
                vm._filterApplyTimer = null;
                vm.autoApplyFilters();
            }, 350);
        },
        clearChartData: function() {
            this.rawData = null;
            this.chartLegendItems = [];
            this.error = null;
            if (this.chart) {
                this.chart.destroy();
                this.chart = null;
            }
        },
        autoApplyFilters: function() {
            if (!this.hasDimensionFilterSelection) {
                this.clearChartData();
                return;
            }
            this.loadChartData();
        },
        loadChartData: async function() {
            this.loading = true;
            this.error = null;
            const vm = this;

            try {
                const url = CI.base_url + '/api/indicator_dsd/chart_data/' + vm.dataset_id;
                const dimensions = {};
                Object.keys(vm.dimensionFilters || {}).forEach(k => {
                    const arr = vm.dimensionFilters[k];
                    if (Array.isArray(arr) && arr.length > 0) {
                        dimensions[k] = arr.slice();
                    }
                });
                const body = {
                    geography: vm.filters.geography && vm.filters.geography.length > 0 ? vm.filters.geography.slice() : [],
                    dimensions,
                    time_period_start: vm.filters.time_period_start || null,
                    time_period_end: vm.filters.time_period_end || null
                };

                const response = await axios.post(url, body, {
                    headers: { 'Content-Type': 'application/json' }
                });

                if (response.data && response.data.status === 'success' && response.data.data) {
                    vm.rawData = response.data.data;
                    if (vm.rawData && !Array.isArray(vm.rawData.records)) {
                        console.warn('Records is not an array:', vm.rawData.records);
                        vm.rawData.records = [];
                    }
                    if (response.data.data.filter_options && response.data.data.filter_options.time_period) {
                        vm.filterOptions.time_period = response.data.data.filter_options.time_period;
                    }
                    vm.$nextTick(() => {
                        vm.updateChart();
                    });
                } else {
                    throw new Error(response.data?.message || 'Failed to load chart data');
                }
            } catch (error) {
                console.error('Error loading chart data:', error);
                vm.error = error.response?.data?.message || error.message || 'Failed to load chart data';
                EventBus.$emit('onFail', vm.error);
            } finally {
                this.loading = false;
            }
        },
        /** Stable series identity (codes); matches API series_key. */
        chartSeriesId: function(record) {
            if (!record) {
                return '';
            }
            if (record.series_key != null && record.series_key !== '') {
                return String(record.series_key);
            }
            return record.geography != null && record.geography !== '' ? String(record.geography) : '';
        },
        /** Legend / table header text (labels when API provides series_key_label). */
        chartSeriesDisplay: function(record) {
            if (!record) {
                return '';
            }
            if (record.series_key_label != null && String(record.series_key_label).trim() !== '') {
                return String(record.series_key_label);
            }
            return this.chartSeriesId(record);
        },
        transformToChartData: function(records) {
            if (!records || !Array.isArray(records) || records.length === 0) {
                return { labels: [], datasets: [], legendItems: [] };
            }

            const seriesData = {};
            const seriesDisplay = {};
            const timePeriods = new Set();

            records.forEach(record => {
                const sid = this.chartSeriesId(record);
                const timePeriod = record.time_period;
                const value = record.observation_value;

                if (!sid) {
                    return;
                }
                if (!seriesData[sid]) {
                    seriesData[sid] = {};
                    seriesDisplay[sid] = this.chartSeriesDisplay(record);
                }
                seriesData[sid][timePeriod] = value;
                timePeriods.add(timePeriod);
            });

            const labels = Array.from(timePeriods).sort();

            const datasets = [];
            let colorIdx = 0;

            Object.keys(seriesData).sort((a, b) =>
                String(seriesDisplay[a] || a).localeCompare(String(seriesDisplay[b] || b), undefined, { sensitivity: 'base' })
            ).forEach(sid => {
                const data = labels.map(timePeriod => ({
                    x: timePeriod,
                    y: seriesData[sid][timePeriod] !== undefined ? seriesData[sid][timePeriod] : null
                }));
                const color = INDICATOR_CHART_LINE_COLORS[colorIdx % INDICATOR_CHART_LINE_COLORS.length];

                datasets.push({
                    label: seriesDisplay[sid] || sid,
                    data: data,
                    borderColor: color,
                    backgroundColor: color,
                    borderWidth: 2,
                    tension: 0,
                    fill: false
                });
                colorIdx++;
            });

            const legendItems = datasets.map(function(ds, idx) {
                return {
                    id: idx + '-' + String(ds.label || ''),
                    label: ds.label || '',
                    color: ds.borderColor || INDICATOR_CHART_LINE_COLORS[idx % INDICATOR_CHART_LINE_COLORS.length]
                };
            });

            return { labels: labels, datasets: datasets, legendItems: legendItems };
        },
        transformToTableData: function(records) {
            if (!records || !Array.isArray(records) || records.length === 0) {
                return [];
            }

            const seriesIds = new Set();
            const timePeriods = new Set();
            const dataMap = {};
            const idToDisplay = {};

            records.forEach(record => {
                const sid = this.chartSeriesId(record);
                const timePeriod = record.time_period;
                const value = record.observation_value;

                if (!sid) {
                    return;
                }
                seriesIds.add(sid);
                if (!idToDisplay[sid]) {
                    idToDisplay[sid] = this.chartSeriesDisplay(record);
                }
                timePeriods.add(timePeriod);

                if (!dataMap[timePeriod]) {
                    dataMap[timePeriod] = {};
                }
                dataMap[timePeriod][sid] = value;
            });

            const sortedTimePeriods = Array.from(timePeriods).sort();
            const sortedSeriesIds = Array.from(seriesIds).sort((a, b) =>
                String(idToDisplay[a] || a).localeCompare(String(idToDisplay[b] || b), undefined, { sensitivity: 'base' })
            );

            return sortedTimePeriods.map(timePeriod => {
                const row = {
                    time_period: timePeriod
                };
                sortedSeriesIds.forEach(sid => {
                    const value = dataMap[timePeriod] && dataMap[timePeriod][sid] !== undefined
                        ? dataMap[timePeriod][sid]
                        : null;
                    if (value !== null && typeof value === 'number') {
                        row[sid] = value.toLocaleString(undefined, {maximumFractionDigits: 2});
                    } else {
                        row[sid] = value !== null ? String(value) : '-';
                    }
                });
                return row;
            });
        },
        updateChart: function() {
            if (!this.rawData || !this.rawData.records || !Array.isArray(this.rawData.records) || this.rawData.records.length === 0) {
                return;
            }

            const vm = this;
            const canvas = this.$refs.chartCanvas;
            if (!canvas) {
                return;
            }

            // Destroy existing chart
            if (this.chart) {
                this.chart.destroy();
            }

            // Check if Chart.js is available
            if (typeof Chart === 'undefined') {
                this.error = 'Chart.js library is not loaded';
                return;
            }

            // Transform raw data to Chart.js format
            const chartData = this.transformToChartData(this.rawData.records);
            this.chartLegendItems = chartData.legendItems || [];

            const ctx = canvas.getContext('2d');
            
            // Set explicit dimensions on canvas parent
            const canvasParent = canvas.parentElement;
            if (canvasParent) {
                canvasParent.style.position = 'relative';
                canvasParent.style.width = '100%';
                canvasParent.style.height = '100%';
            }
            
            this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels || [],
                    datasets: chartData.datasets || []
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            bottom: 4
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            enabled: true,
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: { size: 14 },
                            bodyFont: { size: 13 }
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: vm.$t('field_time_period') || 'Time period'
                            },
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: vm.$t('dsd_role_measure') || 'Observation value'
                            },
                            beginAtZero: false,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        }
                    }
                }
            });
            
            // Resize chart after a short delay to ensure DOM is ready
            this.$nextTick(() => {
                if (this.chart) {
                    this.chart.resize();
                }
            });
        },
        applyFilters: function() {
            if (this._filterApplyTimer) {
                clearTimeout(this._filterApplyTimer);
                this._filterApplyTimer = null;
            }
            this.autoApplyFilters();
        },
        resetFilters: function() {
            this.suppressFilterAutoApply = true;
            const dim = {};
            Object.keys(this.dimensionFilters || {}).forEach(k => { dim[k] = []; });
            this.dimensionFilters = dim;
            this.filters = {
                geography: [],
                time_period_start: null,
                time_period_end: null
            };
            this.clearChartData();
            this.suppressFilterAutoApply = false;
        },
        exportChart: function() {
            if (!this.chart) {
                return;
            }
            const url = this.chart.toBase64Image();
            const link = document.createElement('a');
            link.download = 'indicator-chart-' + new Date().getTime() + '.png';
            link.href = url;
            link.click();
        }
    },
    computed: {
        ProjectID() {
            return this.$store.state.project_id;
        },
        hasData: function() {
            return this.rawData && this.rawData.records && Array.isArray(this.rawData.records) && this.rawData.records.length > 0;
        },
        seriesCount: function() {
            if (!this.rawData || !Array.isArray(this.rawData.records) || this.rawData.records.length === 0) {
                return 0;
            }
            const seriesIds = new Set();
            this.rawData.records.forEach(record => {
                const sid = this.chartSeriesId(record);
                if (sid) {
                    seriesIds.add(sid);
                }
            });
            return seriesIds.size;
        },
        hasFilters: function() {
            const dimAny = this.dimensionFilters && Object.keys(this.dimensionFilters).some(k =>
                Array.isArray(this.dimensionFilters[k]) && this.dimensionFilters[k].length > 0
            );
            return (this.filters.geography && this.filters.geography.length > 0) ||
                   dimAny ||
                   this.filters.time_period_start ||
                   this.filters.time_period_end;
        },
        /** At least one geography or slice facet (FREQ, dimension/measure, attribute, annotation) selection. */
        hasDimensionFilterSelection: function() {
            if (this.filters.geography && this.filters.geography.length > 0) {
                return true;
            }
            if (!this.dimensionFilters) {
                return false;
            }
            return Object.keys(this.dimensionFilters).some(k =>
                Array.isArray(this.dimensionFilters[k]) && this.dimensionFilters[k].length > 0
            );
        },
        tableData: function() {
            if (!this.rawData || !this.rawData.records) {
                return [];
            }
            return this.transformToTableData(this.rawData.records);
        },
        tableHeaders: function() {
            if (!this.rawData || !this.rawData.records || !Array.isArray(this.rawData.records) || this.rawData.records.length === 0) {
                return [];
            }

            const seriesIds = new Set();
            const idToDisplay = {};
            this.rawData.records.forEach(record => {
                if (!record) {
                    return;
                }
                const sid = this.chartSeriesId(record);
                if (!sid) {
                    return;
                }
                seriesIds.add(sid);
                if (!idToDisplay[sid]) {
                    idToDisplay[sid] = this.chartSeriesDisplay(record);
                }
            });

            const sortedIds = Array.from(seriesIds).sort((a, b) =>
                String(idToDisplay[a] || a).localeCompare(String(idToDisplay[b] || b), undefined, { sensitivity: 'base' })
            );

            const headers = [
                { text: this.$t('time_period') || 'Time Period', value: 'time_period', sortable: true }
            ];

            sortedIds.forEach(sid => {
                headers.push({
                    text: idToDisplay[sid] || sid,
                    value: sid,
                    sortable: true,
                    align: 'right'
                });
            });

            return headers;
        }
    },
    template: `
        <div class="indicator-dsd-chart-component" style="display: flex; flex-direction: column; height: calc(100vh - 72px); min-height: 640px;">
            <!-- Page Title -->
            <v-card class="mb-2 m-2 p-2" flat>
                <v-card-title class="d-flex justify-space-between align-center">
                    <div>
                        <h4 class="mb-0">{{$t("timeseries_visualization") || "Timeseries Visualization"}}</h4>
                        <div v-if="hasData" class="caption grey--text mt-1">
                            {{ seriesCount }} {{ $t('series') || 'series' }}
                        </div>
                    </div>
                    <div>
                        <v-btn 
                            v-if="hasData"
                            color="primary" 
                            outlined 
                            small
                            @click="exportChart"
                        >
                            <v-icon left small>mdi-download</v-icon>
                            {{$t("export_chart") || "Export Chart"}}
                        </v-btn>
                    </div>
                </v-card-title>
            </v-card>

            <!-- Error Message -->
            <v-alert v-if="error" type="error" class="m-2" dismissible @input="error = null">
                {{error}}
            </v-alert>

            <!-- Two Column Layout -->
            <div style="display: flex; flex: 1; gap: 16px; overflow: hidden; background: rgb(240 240 240);" class="m-2 elevation-2">
                <!-- Left Column: Filters (30%) -->
                <div style="flex: 0 0 30%; display: flex; flex-direction: column; border: 1px solid #e0e0e0; border-radius: 4px; overflow: hidden; background: white;">
                    <div class="pa-3" style="border-bottom: 1px solid #e0e0e0; background: #f5f5f5;">
                        <h5>{{$t("filters") || "Filters"}}</h5>
                    </div>
                    
                    <div style="flex: 1; overflow-y: auto; padding: 16px;">
                        <v-alert v-if="filterOptionsError" type="error" dense text class="mb-3">
                            {{ filterOptionsError }}
                        </v-alert>
                        <div class="facet-group-title">{{$t("viz_facets_core_sdmx") || "Core"}}</div>

                        <div class="mb-4">
                            <label class="font-weight-bold mb-2">{{$t("field_geography") || "Geography"}}</label>
                            <v-combobox
                                v-if="!(filterOptions.geography && filterOptions.geography.length)"
                                v-model="filters.geography"
                                multiple
                                chips
                                small-chips
                                deletable-chips
                                dense
                                outlined
                                hide-details
                                clearable
                                :placeholder="$t('geography_codes_combobox') || 'Enter geography codes (no values in published data yet)'"
                            ></v-combobox>
                            <v-autocomplete
                                v-else
                                v-model="filters.geography"
                                :items="filterOptions.geography || []"
                                item-value="code"
                                item-text="label"
                                multiple
                                chips
                                dense
                                outlined
                                hide-details
                                clearable
                                :placeholder="$t('select_geography') || 'Select geography'"
                                :no-data-text="$t('no_geography_options') || 'No geography options.'"
                            ></v-autocomplete>
                        </div>

                        <div class="mb-4" v-if="coreFacetFreq">
                            <label class="font-weight-bold mb-2">{{$t("field_freq") || "Periodicity"}}</label>
                            <v-autocomplete
                                v-model="dimensionFilters[coreFacetFreq.name]"
                                :items="coreFacetFreq.items"
                                item-value="code"
                                item-text="label"
                                multiple
                                chips
                                dense
                                outlined
                                hide-details
                                clearable
                                :placeholder="$t('select_freq_codes') || 'Select frequency codes'"
                                :no-data-text="$t('no_options') || 'No options'"
                            ></v-autocomplete>
                        </div>

                        <div class="mb-4">
                            <label class="font-weight-bold mb-2">{{$t("field_time_period") || "Time period"}}</label>
                            <div class="mb-2">
                                <label class="text-caption">{{$t("from") || "From"}}</label>
                                <v-text-field
                                    v-model="filters.time_period_start"
                                    dense
                                    outlined
                                    hide-details
                                    :placeholder="filterOptions.time_period?.min || ''"
                                ></v-text-field>
                            </div>
                            <div>
                                <label class="text-caption">{{$t("to") || "To"}}</label>
                                <v-text-field
                                    v-model="filters.time_period_end"
                                    dense
                                    outlined
                                    hide-details
                                    :placeholder="filterOptions.time_period?.max || ''"
                                ></v-text-field>
                            </div>
                            <small class="text-muted">
                                {{$t("available_range") || "Available range"}}:
                                <span v-if="filterOptions.time_period?.min && filterOptions.time_period?.max">
                                    {{filterOptions.time_period.min}} - {{filterOptions.time_period.max}}
                                </span>
                                <span v-else>{{$t("all_data") || "All data"}}</span>
                            </small>
                        </div>

                        <div class="facet-group-title" v-if="facetDimensions.length">{{$t("viz_facets_dimensions_and_measures") || "Dimensions"}}</div>
                        <div class="mb-4" v-for="col in facetDimensions" :key="'dim-' + col.name">
                            <label class="font-weight-bold mb-2">{{ col.label }}</label>
                            <v-combobox
                                v-if="!col.items.length"
                                v-model="dimensionFilters[col.name]"
                                multiple
                                chips
                                small-chips
                                deletable-chips
                                dense
                                outlined
                                hide-details
                                clearable
                                :placeholder="$t('dimension_codes_combobox') || 'Type codes (none observed in published data yet)'"
                            ></v-combobox>
                            <v-autocomplete
                                v-else
                                v-model="dimensionFilters[col.name]"
                                :items="col.items"
                                item-value="code"
                                item-text="label"
                                multiple
                                chips
                                dense
                                outlined
                                hide-details
                                clearable
                                :placeholder="$t('select_codes') || 'Select codes'"
                                :no-data-text="$t('no_options') || 'No options'"
                            ></v-autocomplete>
                        </div>

                        <div class="facet-group-title" v-if="facetAttributes.length">{{$t("viz_facets_attributes") || "Attributes"}}</div>
                        <div class="mb-4" v-for="col in facetAttributes" :key="'attr-' + col.name">
                            <label class="font-weight-bold mb-2">{{ col.label }}</label>
                            <v-autocomplete
                                v-model="dimensionFilters[col.name]"
                                :items="col.items"
                                item-value="code"
                                item-text="label"
                                multiple
                                chips
                                dense
                                outlined
                                hide-details
                                clearable
                                :placeholder="$t('select_codes') || 'Select codes'"
                                :no-data-text="$t('no_options') || 'No options'"
                            ></v-autocomplete>
                        </div>

                        <div class="facet-group-title" v-if="facetAnnotations.length">{{$t("viz_facets_annotations") || "Annotations"}}</div>
                        <div class="mb-4" v-for="col in facetAnnotations" :key="'ann-' + col.name">
                            <label class="font-weight-bold mb-2">{{ col.label }}</label>
                            <v-autocomplete
                                v-model="dimensionFilters[col.name]"
                                :items="col.items"
                                item-value="code"
                                item-text="label"
                                multiple
                                chips
                                dense
                                outlined
                                hide-details
                                clearable
                                :placeholder="$t('select_codes') || 'Select codes'"
                                :no-data-text="$t('no_options') || 'No options'"
                            ></v-autocomplete>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex align-center" style="gap: 8px;">
                            <v-btn
                                color="primary"
                                depressed
                                small
                                @click="applyFilters"
                                :loading="loading"
                            >
                                <v-icon left small>mdi-filter</v-icon>
                                {{$t("apply_filters") || "Apply"}}
                            </v-btn>
                            <v-btn
                                v-if="hasFilters"
                                text
                                small
                                @click="resetFilters"
                            >
                                <v-icon left small>mdi-refresh</v-icon>
                                {{$t("reset") || "Reset"}}
                            </v-btn>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Chart/Table (70%) -->
                <div style="flex: 1; display: grid; grid-template-rows: 48px 1fr; border: 1px solid #e0e0e0; border-radius: 4px; overflow: hidden; background: white; min-height: 0;">
                    <!-- Tabs Header -->
                    <v-tabs
                        v-model="activeTab"
                        class="chart-tabs-header"
                        height="48"
                        style="border-bottom: 1px solid #e0e0e0; background: #f5f5f5;"
                    >
                        <v-tab>
                            <v-icon left small>mdi-chart-line</v-icon>
                            {{$t("chart") || "Chart"}}
                        </v-tab>
                        <v-tab>
                            <v-icon left small>mdi-table</v-icon>
                            {{$t("data_table") || "Data Table"}}
                        </v-tab>
                    </v-tabs>
                    
                    <!-- Tab Content -->
                    <v-tabs-items
                        v-model="activeTab"
                        class="chart-tabs-items"
                        style="overflow: hidden;"
                    >
                        <!-- Chart Tab -->
                        <v-tab-item :value="0" class="chart-tab">
                            <div v-if="loading" class="tab-pane tab-pane--center pa-8">
                                <div>
                                    <v-progress-circular indeterminate color="primary"></v-progress-circular>
                                    <div class="mt-2">{{$t("loading") || "Loading"}}...</div>
                                </div>
                            </div>
                            <div v-else-if="!hasData" class="tab-pane tab-pane--center pa-8 text-muted">
                                <v-icon size="64" color="grey lighten-1">mdi-chart-line</v-icon>
                                <div class="mt-4">
                                    {{ hasDimensionFilterSelection ? ($t("no_data_available") || "No data available") : ($t("select_dimension_filters_to_view_chart") || "Select at least one geography or dimension filter to view the chart") }}
                                </div>
                            </div>
                            <div v-else class="tab-pane tab-pane--chart">
                                <div class="chart-series-summary">
                                    {{ seriesCount }} {{ $t('series') || 'series' }}
                                </div>
                                <div class="chart-plot-area">
                                    <canvas ref="chartCanvas"></canvas>
                                </div>
                                <div v-if="chartLegendItems.length" class="chart-legend-panel">
                                    <div
                                        v-for="item in chartLegendItems"
                                        :key="item.id"
                                        class="chart-legend-item"
                                    >
                                        <span
                                            class="chart-legend-swatch"
                                            :style="{ backgroundColor: item.color }"
                                        ></span>
                                        <span class="chart-legend-label">{{ item.label }}</span>
                                    </div>
                                </div>
                            </div>
                        </v-tab-item>
                        
                        <!-- Data Table Tab -->
                        <v-tab-item :value="1" class="chart-tab">
                            <div class="tab-pane tab-pane--scroll">
                                <div v-if="loading" class="text-center pa-8">
                                    <v-progress-circular indeterminate color="primary"></v-progress-circular>
                                    <div class="mt-2">{{$t("loading") || "Loading"}}...</div>
                                </div>
                                <div v-else-if="!hasData" class="text-center pa-8 text-muted">
                                    <v-icon size="64" color="grey lighten-1">mdi-table</v-icon>
                                    <div class="mt-4">
                                        {{ hasDimensionFilterSelection ? ($t("no_data_available") || "No data available") : ($t("select_dimension_filters_to_view_chart") || "Select at least one geography or dimension filter to view the chart") }}
                                    </div>
                                </div>
                                <div v-else>
                                    <v-data-table
                                        :headers="tableHeaders"
                                        :items="tableData"
                                        :items-per-page="50"
                                        class="elevation-1"
                                        dense
                                        :footer-props="{
                                            'items-per-page-options': [25, 50, 100, -1],
                                            'items-per-page-text': $t('rows_per_page') || 'Rows per page'
                                        }"
                                    >
                                        <template v-slot:item.time_period="{ item }">
                                            <strong>{{ item.time_period }}</strong>
                                        </template>
                                    </v-data-table>
                                </div>
                            </div>
                        </v-tab-item>
                    </v-tabs-items>
                </div>
            </div>
        </div>
    `
})
