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
            /** auto | line | bar | hbar */
            chartType: 'auto',
            /** Type actually drawn (line | bar | hbar) after resolving 'auto' */
            renderedChartType: 'line',
            /** Data table: horizontal = row per time period; vertical = row per series */
            tableLayout: 'horizontal',
            /** Dimensions with one value across all series: [{label, value}] */
            chartContext: [],
            /** Series without a numeric value in single-period bars */
            chartHiddenCount: 0,
            chartNotes: [],
            /** A measure-type dimension varies, so different units may share one axis */
            chartMixedMeasures: false,
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
        },
        chartType: function() {
            this.$nextTick(() => {
                this.updateChart();
            });
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
            .indicator-dsd-chart-component .indicator-note-marker {
                font-size: 0.7em;
                margin-left: 2px;
                color: #1565c0;
            }
            .indicator-dsd-chart-component .indicator-noted-cell {
                cursor: help;
                border-bottom: 1px dotted rgba(0, 0, 0, 0.35);
            }
            .indicator-dsd-chart-component .indicator-notes-list {
                margin-top: 16px !important;
                padding-top: 12px;
                border-top: 1px solid rgba(0, 0, 0, 0.12);
                font-size: 0.875rem;
                line-height: 1.55;
                max-width: 960px;
            }
            .indicator-dsd-chart-component .indicator-notes-list .text-subtitle-2 {
                margin-bottom: 8px !important;
            }
            .indicator-dsd-chart-component .indicator-notes-list .indicator-table-note {
                margin: 0 0 10px !important;
            }
            .indicator-dsd-chart-component .indicator-notes-list ol {
                padding-left: 1.75rem;
                margin: 0;
            }
            .indicator-dsd-chart-component .indicator-notes-list li + li {
                margin-top: 4px;
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
            .indicator-dsd-chart-component .chart-toolbar {
                flex: 0 0 auto;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                margin-bottom: 8px;
            }
            .indicator-dsd-chart-component .chart-series-summary {
                font-size: 0.8125rem;
                color: rgba(0, 0, 0, 0.6);
            }
            .indicator-dsd-chart-component .chart-context {
                margin-left: 12px;
            }
            .indicator-dsd-chart-component .chart-context strong {
                color: rgba(0, 0, 0, 0.87);
                font-weight: 500;
            }
            .indicator-dsd-chart-component .table-toolbar {
                display: flex;
                justify-content: flex-end;
                margin-bottom: 8px;
            }
            .indicator-dsd-chart-component .chart-notice {
                flex: 0 0 auto;
                font-size: 0.75rem;
                color: rgba(0, 0, 0, 0.6);
                margin-bottom: 6px;
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
            .indicator-dsd-chart-component .chart-legend-swatch--box {
                width: 12px;
                height: 12px;
                margin-top: 3px;
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
            this.chartNotes = [];
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
        /** Numeric observation value or null (accepts numeric strings such as "1,234"). */
        toChartNumber: function(value) {
            if (value === null || value === undefined) {
                return null;
            }
            if (typeof value === 'number') {
                return isFinite(value) ? value : null;
            }
            const s = String(value).replace(/[,\s]/g, '');
            if (s === '' || !/^[-+]?(\d+\.?\d*|\.\d+)([eE][-+]?\d+)?$/.test(s)) {
                return null;
            }
            return parseFloat(s);
        },
        /**
         * Split series into slice dimensions: which dimensions vary across the loaded series
         * (used for short labels / grouping) and which are constant (shown once as context).
         */
        buildSeriesDimensions: function(records) {
            const metaDims = (this.rawData && this.rawData.metadata && Array.isArray(this.rawData.metadata.slice_dimensions))
                ? this.rawData.metadata.slice_dimensions
                : [];
            const partsBySid = {};
            let partCount = null;
            let consistent = true;

            records.forEach(record => {
                const sid = this.chartSeriesId(record);
                if (!sid || partsBySid[sid]) {
                    return;
                }
                let parts = Array.isArray(record.series_parts)
                    ? record.series_parts.map(p => (p === null || p === undefined) ? '' : String(p))
                    : this.chartSeriesDisplay(record).split(' | ');
                if (partCount === null) {
                    partCount = parts.length;
                } else if (parts.length !== partCount) {
                    consistent = false;
                }
                partsBySid[sid] = parts;
            });

            if (!consistent || !partCount) {
                return { dims: [], partsBySid: {}, varying: [], constant: [] };
            }

            const dims = [];
            for (let i = 0; i < partCount; i++) {
                const m = metaDims.length === partCount ? metaDims[i] : null;
                dims.push({
                    key: m && m.name ? String(m.name) : ('dim_' + i),
                    label: m && m.label ? String(m.label) : ((this.$t('dimension') || 'Dimension') + ' ' + (i + 1)),
                    column_type: m && m.column_type ? String(m.column_type) : ''
                });
            }

            const varying = [];
            const constant = [];
            dims.forEach((dim, i) => {
                const values = new Set(Object.keys(partsBySid).map(sid => partsBySid[sid][i]));
                if (values.size > 1) {
                    varying.push(i);
                } else {
                    const v = values.values().next().value;
                    if (v !== undefined && v !== '') {
                        constant.push({ label: dim.label, value: v });
                    }
                }
            });

            return { dims: dims, partsBySid: partsBySid, varying: varying, constant: constant };
        },
        /** Series label using only the dimensions that vary; falls back to the full label. */
        shortSeriesLabel: function(dimInfo, sid, fallback) {
            const parts = dimInfo && dimInfo.partsBySid ? dimInfo.partsBySid[sid] : null;
            if (!parts || !dimInfo.varying.length) {
                return fallback;
            }
            return dimInfo.varying.map(i => parts[i]).join(' · ');
        },
        /**
         * One time period: bars per series. With 2+ varying dimensions, the non-measure dimension with
         * the fewest values becomes the bar color (datasets); the remaining varying dimensions form the categories.
         */
        buildSinglePeriodBars: function(chartData, dimInfo) {
            const period = chartData.labels[0];
            const rows = chartData.datasets.map(ds => ({
                sid: ds.sid,
                label: ds.label,
                value: ds.data[0],
                noteIds: ds.noteIds ? ds.noteIds[0] : []
            }));
            const hidden = rows.filter(r => r.value === null).length;
            const valid = rows.filter(r => r.value !== null);
            const varying = dimInfo.varying;

            if (varying.length < 2) {
                valid.sort((a, b) => b.value - a.value);
                return {
                    labels: valid.map(r => r.label),
                    datasets: [{
                        label: String(period),
                        data: valid.map(r => r.value),
                        noteIds: valid.map(r => r.noteIds),
                        backgroundColor: INDICATOR_CHART_LINE_COLORS[0],
                        borderColor: INDICATOR_CHART_LINE_COLORS[0],
                        borderWidth: 0
                    }],
                    legendItems: [],
                    hidden: hidden
                };
            }

            const distinctCount = idx => new Set(valid.map(r => dimInfo.partsBySid[r.sid][idx])).size;
            const nonMeasure = varying.filter(i => dimInfo.dims[i].column_type !== 'measure');
            const colorCandidates = nonMeasure.length ? nonMeasure : varying;
            const colorIdx = colorCandidates.slice().sort((a, b) => distinctCount(a) - distinctCount(b) || b - a)[0];
            const categoryDims = varying.filter(i => i !== colorIdx);

            const categories = [];
            const groups = [];
            const values = {};
            const noteIdsByKey = {};
            valid.forEach(r => {
                const parts = dimInfo.partsBySid[r.sid];
                const cat = categoryDims.map(i => parts[i]).join(' · ');
                const grp = parts[colorIdx];
                if (categories.indexOf(cat) === -1) {
                    categories.push(cat);
                }
                if (groups.indexOf(grp) === -1) {
                    groups.push(grp);
                }
                values[cat + '\u0000' + grp] = r.value;
                noteIdsByKey[cat + '\u0000' + grp] = r.noteIds;
            });

            const datasets = groups.map((grp, gi) => {
                const color = INDICATOR_CHART_LINE_COLORS[gi % INDICATOR_CHART_LINE_COLORS.length];
                return {
                    label: grp,
                    data: categories.map(cat => {
                        const v = values[cat + '\u0000' + grp];
                        return v === undefined ? null : v;
                    }),
                    noteIds: categories.map(cat => noteIdsByKey[cat + '\u0000' + grp] || []),
                    backgroundColor: color,
                    borderColor: color,
                    borderWidth: 0
                };
            });

            return {
                labels: categories,
                datasets: datasets,
                legendItems: datasets.map((ds, idx) => ({
                    id: idx + '-' + ds.label,
                    label: ds.label,
                    color: ds.backgroundColor
                })),
                hidden: hidden
            };
        },
        transformToChartData: function(records, dimInfo) {
            if (!records || !Array.isArray(records) || records.length === 0) {
                return { labels: [], datasets: [], legendItems: [] };
            }

            const seriesData = {};
            const seriesNotes = {};
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
                    seriesNotes[sid] = {};
                    seriesDisplay[sid] = this.chartSeriesDisplay(record);
                }
                seriesData[sid][timePeriod] = value;
                seriesNotes[sid][timePeriod] = IndicatorNotesUtil.cellNoteIds(record);
                timePeriods.add(timePeriod);
            });

            const labels = Array.from(timePeriods).sort();

            const datasets = [];
            let colorIdx = 0;

            Object.keys(seriesData).sort((a, b) =>
                String(seriesDisplay[a] || a).localeCompare(String(seriesDisplay[b] || b), undefined, { sensitivity: 'base' })
            ).forEach(sid => {
                const data = labels.map(timePeriod => this.toChartNumber(seriesData[sid][timePeriod]));
                const color = INDICATOR_CHART_LINE_COLORS[colorIdx % INDICATOR_CHART_LINE_COLORS.length];

                datasets.push({
                    sid: sid,
                    label: this.shortSeriesLabel(dimInfo, sid, seriesDisplay[sid] || sid),
                    data: data,
                    noteIds: labels.map(timePeriod => seriesNotes[sid][timePeriod] || []),
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
        /**
         * Horizontal layout: one row per time period, a column per series.
         * Cells referenced by notes get `_notes[column] = "1,3"`; noted cells without a value show '..'.
         */
        transformToTableData: function(records, numbering) {
            if (!records || !Array.isArray(records) || records.length === 0) {
                return [];
            }

            const seriesIds = new Set();
            const timePeriods = new Set();
            const dataMap = {};
            const notesMap = {};
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
                    notesMap[timePeriod] = {};
                }
                dataMap[timePeriod][sid] = value;
                notesMap[timePeriod][sid] = IndicatorNotesUtil.cellNoteIds(record);
            });

            const sortedTimePeriods = Array.from(timePeriods).sort();
            const sortedSeriesIds = Array.from(seriesIds).sort((a, b) =>
                String(idToDisplay[a] || a).localeCompare(String(idToDisplay[b] || b), undefined, { sensitivity: 'base' })
            );

            return sortedTimePeriods.map(timePeriod => {
                const row = {
                    time_period: timePeriod,
                    _notes: {},
                    _noteNums: {}
                };
                sortedSeriesIds.forEach(sid => {
                    const hasRecord = !!(dataMap[timePeriod] && Object.prototype.hasOwnProperty.call(dataMap[timePeriod], sid));
                    row[sid] = this.formatTableValue(hasRecord ? dataMap[timePeriod][sid] : undefined, hasRecord);
                    if (hasRecord && numbering) {
                        const nums = numbering.markers(notesMap[timePeriod][sid]);
                        if (nums.length) {
                            row._notes[sid] = IndicatorNotesUtil.formatMarkers(nums);
                            row._noteNums[sid] = nums;
                        }
                    }
                });
                return row;
            });
        },
        /** Horizontal layout headers: time period, then one column per series (sorted by label). */
        horizontalTableHeaders: function(records) {
            const seriesIds = new Set();
            const idToDisplay = {};
            records.forEach(record => {
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
        },
        /** hasRecord: the observation exists but has no value (kept because it carries notes) -> '..'. */
        formatTableValue: function(value, hasRecord) {
            if (value === null || value === undefined) {
                return hasRecord ? '..' : '-';
            }
            if (typeof value === 'number') {
                return value.toLocaleString(undefined, {maximumFractionDigits: 2});
            }
            return String(value);
        },
        /** Vertical layout: one row per series, a column per slice dimension, then a column per time period. */
        transformToVerticalTable: function(records, numbering) {
            if (!records || !Array.isArray(records) || records.length === 0) {
                return { headers: [], items: [] };
            }

            const dimInfo = this.buildSeriesDimensions(records);
            const values = {};
            const cellNotes = {};
            const idToDisplay = {};
            const timePeriods = new Set();

            records.forEach(record => {
                const sid = this.chartSeriesId(record);
                if (!sid) {
                    return;
                }
                if (!values[sid]) {
                    values[sid] = {};
                    cellNotes[sid] = {};
                    idToDisplay[sid] = this.chartSeriesDisplay(record);
                }
                values[sid][record.time_period] = record.observation_value;
                cellNotes[sid][record.time_period] = IndicatorNotesUtil.cellNoteIds(record);
                timePeriods.add(record.time_period);
            });

            const periods = Array.from(timePeriods).sort();
            const sids = Object.keys(values).sort((a, b) =>
                String(idToDisplay[a] || a).localeCompare(String(idToDisplay[b] || b), undefined, { sensitivity: 'base' })
            );

            const headers = dimInfo.dims.length
                ? dimInfo.dims.map((dim, i) => ({ text: dim.label, value: 'dim_' + i, sortable: true }))
                : [{ text: this.$t('series') || 'Series', value: 'series', sortable: true }];
            periods.forEach((p, i) => {
                headers.push({ text: String(p), value: 'p_' + i, sortable: true, align: 'right' });
            });

            const items = sids.map(sid => {
                const row = { _notes: {}, _noteNums: {} };
                if (dimInfo.dims.length) {
                    dimInfo.partsBySid[sid].forEach((part, i) => {
                        row['dim_' + i] = part;
                    });
                } else {
                    row.series = idToDisplay[sid] || sid;
                }
                periods.forEach((p, i) => {
                    const hasRecord = Object.prototype.hasOwnProperty.call(values[sid], p);
                    row['p_' + i] = this.formatTableValue(values[sid][p], hasRecord);
                    if (hasRecord && numbering) {
                        const nums = numbering.markers(cellNotes[sid][p]);
                        if (nums.length) {
                            row._notes['p_' + i] = IndicatorNotesUtil.formatMarkers(nums);
                            row._noteNums['p_' + i] = nums;
                        }
                    }
                });
                return row;
            });

            return { headers: headers, items: items };
        },
        /**
         * Number the notes of the plotted points (by category, then series) and store per-point markers on
         * each dataset (`noteMarkers`), shown in the tooltip and listed under the chart.
         */
        applyChartNotes: function(datasets, labels, chartData) {
            const numbering = IndicatorNotesUtil.createNumbering(this.rawData ? this.rawData.notes : null);
            datasets.forEach(ds => {
                ds.noteMarkers = (ds.noteIds || []).map(() => []);
            });
            labels.forEach((label, i) => {
                datasets.forEach(ds => {
                    const ids = ds.noteIds && ds.noteIds[i];
                    // Only plotted points; notes on values without data are shown in the Table tab.
                    if (ids && ids.length && ds.data[i] !== null && ds.data[i] !== undefined) {
                        ds.noteMarkers[i] = numbering.markers(ids);
                    }
                });
            });
            return { notes: numbering.list() };
        },
        /** Tooltip footer: text of the notes on the hovered points, once each. */
        chartTooltipNoteLines: function(items) {
            const seen = [];
            (items || []).forEach(item => {
                const nums = item.dataset.noteMarkers && item.dataset.noteMarkers[item.dataIndex];
                (nums || []).forEach(n => {
                    if (seen.indexOf(n) === -1) {
                        seen.push(n);
                    }
                });
            });
            seen.sort((a, b) => a - b);
            return IndicatorNotesUtil.noteLines(seen, this.chartNotes, this.noteMissingText(), 90);
        },
        /** Table cell tooltip: full text of the cell's notes. */
        tableCellNoteLines: function(numbers) {
            return IndicatorNotesUtil.noteLines(numbers, this.tableNotes, this.noteMissingText(), 0);
        },
        noteMissingText: function() {
            return this.$t('data_note_missing_text') || 'No text defined for this note.';
        },
        /** Concrete chart type for the selected mode; 'auto' picks by number of periods and series. */
        resolveChartType: function(periodCount, seriesCount) {
            if (this.chartType !== 'auto') {
                return this.chartType;
            }
            if (periodCount <= 1) {
                return 'hbar';
            }
            if (periodCount <= 5 && seriesCount <= 8) {
                return 'bar';
            }
            return 'line';
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
            const dimInfo = this.buildSeriesDimensions(this.rawData.records);
            const chartData = this.transformToChartData(this.rawData.records, dimInfo);
            const periods = chartData.labels || [];
            const type = this.resolveChartType(periods.length, chartData.datasets.length);
            const isBar = type === 'bar' || type === 'hbar';
            const isHorizontal = type === 'hbar';
            const transposed = isBar && periods.length === 1;

            let labels = periods;
            let datasets = chartData.datasets;
            let legendItems = chartData.legendItems || [];
            let hiddenCount = 0;

            if (type === 'line') {
                datasets.forEach(ds => {
                    ds.pointRadius = periods.length <= 3 ? 5 : 3;
                    ds.pointHoverRadius = ds.pointRadius + 2;
                });
            }

            if (transposed) {
                const bars = this.buildSinglePeriodBars(chartData, dimInfo);
                labels = bars.labels;
                datasets = bars.datasets;
                legendItems = bars.legendItems;
                hiddenCount = bars.hidden;
            }

            const notesView = this.applyChartNotes(datasets, labels, chartData);
            this.chartNotes = notesView.notes;

            this.renderedChartType = type;
            this.chartLegendItems = legendItems;
            this.chartContext = dimInfo.constant;
            this.chartHiddenCount = hiddenCount;
            this.chartMixedMeasures = dimInfo.varying.some(i => dimInfo.dims[i].column_type === 'measure');

            const timeAxisTitle = vm.$t('field_time_period') || 'Time period';
            const valueAxisTitleBase = vm.$t('dsd_role_measure') || 'Observation value';
            const valueAxisTitle = transposed ? valueAxisTitleBase + ' (' + periods[0] + ')' : valueAxisTitleBase;
            const categoryAxis = {
                title: {
                    display: !transposed,
                    text: timeAxisTitle
                },
                grid: {
                    display: false
                }
            };
            const valueAxis = {
                title: {
                    display: true,
                    text: valueAxisTitle
                },
                beginAtZero: isBar,
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                }
            };

            const ctx = canvas.getContext('2d');
            
            // Set explicit dimensions on canvas parent
            const canvasParent = canvas.parentElement;
            if (canvasParent) {
                canvasParent.style.position = 'relative';
                canvasParent.style.width = '100%';
                const barCount = isHorizontal ? labels.length * datasets.length : 0;
                canvasParent.style.height = barCount > 14 ? (barCount * 26 + 80) + 'px' : '100%';
            }
            
            this.chart = new Chart(ctx, {
                type: isBar ? 'bar' : 'line',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    indexAxis: isHorizontal ? 'y' : 'x',
                    spanGaps: true,
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            bottom: 4
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index',
                        axis: isHorizontal ? 'y' : 'x'
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
                            bodyFont: { size: 13 },
                            footerFont: { size: 12, weight: 'normal' },
                            callbacks: {
                                label: function(item) {
                                    let line = (item.dataset.label || '') + ': ' + item.formattedValue;
                                    const nums = item.dataset.noteMarkers && item.dataset.noteMarkers[item.dataIndex];
                                    if (nums && nums.length) {
                                        line += '  [' + IndicatorNotesUtil.formatMarkers(nums) + ']';
                                    }
                                    return line;
                                },
                                footer: function(items) {
                                    return vm.chartTooltipNoteLines(items);
                                }
                            }
                        }
                    },
                    scales: {
                        x: isHorizontal ? valueAxis : categoryAxis,
                        y: isHorizontal ? categoryAxis : valueAxis
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
        chartTabIcon: function() {
            if (this.renderedChartType === 'bar') {
                return 'mdi-chart-bar';
            }
            if (this.renderedChartType === 'hbar') {
                return 'mdi-align-horizontal-left';
            }
            return 'mdi-chart-line';
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
        /** Table tab: headers, rows and the notes used (numbered in row/column order of the current layout). */
        tableModel: function() {
            const records = this.rawData && Array.isArray(this.rawData.records) ? this.rawData.records : [];
            if (records.length === 0) {
                return { headers: [], items: [], notes: [] };
            }
            const numbering = IndicatorNotesUtil.createNumbering(this.rawData.notes);
            if (this.tableLayout === 'vertical') {
                const vt = this.transformToVerticalTable(records, numbering);
                return { headers: vt.headers, items: vt.items, notes: numbering.list() };
            }
            const headers = this.horizontalTableHeaders(records);
            const items = this.transformToTableData(records, numbering);
            return { headers: headers, items: items, notes: numbering.list() };
        },
        tableData: function() {
            return this.tableModel.items;
        },
        tableHeaders: function() {
            return this.tableModel.headers;
        },
        tableNotes: function() {
            return this.tableModel.notes;
        },
        /** Table notes (scope = table): shown under every chart and table view, without markers. */
        indicatorTableNotes: function() {
            return this.rawData && Array.isArray(this.rawData.table_notes) ? this.rawData.table_notes : [];
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
                            <v-icon left small>{{ chartTabIcon }}</v-icon>
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
                                <div class="chart-toolbar">
                                    <div class="chart-series-summary">
                                        {{ seriesCount }} {{ $t('series') || 'series' }}
                                        <span v-if="chartContext.length" class="chart-context">
                                            <span v-for="(c, ci) in chartContext" :key="'ctx-' + ci">
                                                <span v-if="ci > 0"> · </span>{{ c.label }}: <strong>{{ c.value }}</strong>
                                            </span>
                                        </span>
                                    </div>
                                    <v-btn-toggle v-model="chartType" mandatory dense>
                                        <v-btn small value="auto" :title="$t('chart_type_auto')">
                                            {{ $t('chart_type_auto') }}
                                        </v-btn>
                                        <v-btn small value="line" :title="$t('chart_type_line')">
                                            <v-icon small>mdi-chart-line</v-icon>
                                        </v-btn>
                                        <v-btn small value="bar" :title="$t('chart_type_bar')">
                                            <v-icon small>mdi-chart-bar</v-icon>
                                        </v-btn>
                                        <v-btn small value="hbar" :title="$t('chart_type_hbar')">
                                            <v-icon small>mdi-align-horizontal-left</v-icon>
                                        </v-btn>
                                    </v-btn-toggle>
                                </div>
                                <div v-if="chartMixedMeasures" class="chart-notice">
                                    <v-icon x-small color="orange darken-2">mdi-alert</v-icon>
                                    {{ $t('chart_mixed_measures_notice') }}
                                </div>
                                <div v-if="chartHiddenCount > 0" class="chart-notice">
                                    <v-icon x-small color="grey">mdi-information-outline</v-icon>
                                    {{ chartHiddenCount }} {{ $t('chart_series_without_value') }}
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
                                            :class="{ 'chart-legend-swatch--box': renderedChartType !== 'line' }"
                                            :style="{ backgroundColor: item.color }"
                                        ></span>
                                        <span class="chart-legend-label">{{ item.label }}</span>
                                    </div>
                                </div>
                                <div v-if="chartNotes.length || indicatorTableNotes.length" class="indicator-notes-list mt-2">
                                    <div class="text-subtitle-2 mb-1">{{ $t('data_notes_title') || 'Notes' }}</div>
                                    <p v-for="tn in indicatorTableNotes" :key="'tn-' + tn.id" class="mb-1 indicator-table-note">{{ tn.note }}</p>
                                    <ol v-if="chartNotes.length" class="mb-0">
                                        <li v-for="n in chartNotes" :key="n.id" :value="n.number">
                                            <span v-if="!n.missing">{{ n.text }}</span>
                                            <span v-else class="warning--text">{{ $t('data_note_missing_text') || 'No text defined for this note.' }}</span>
                                            <span class="grey--text text-caption ml-1">[{{ n.id }}]</span>
                                        </li>
                                    </ol>
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
                                    <div class="table-toolbar">
                                        <v-btn-toggle v-model="tableLayout" mandatory dense>
                                            <v-btn small value="horizontal" :title="$t('table_layout_horizontal_help')">
                                                <v-icon small left>mdi-table-arrow-right</v-icon>
                                                {{ $t('table_layout_horizontal') }}
                                            </v-btn>
                                            <v-btn small value="vertical" :title="$t('table_layout_vertical_help')">
                                                <v-icon small left>mdi-table-arrow-down</v-icon>
                                                {{ $t('table_layout_vertical') }}
                                            </v-btn>
                                        </v-btn-toggle>
                                    </div>
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
                                        <template v-slot:item="{ item, headers }">
                                            <tr>
                                                <td v-for="h in headers" :key="h.value" :class="h.align === 'right' ? 'text-right' : ''">
                                                    <v-tooltip v-if="item._noteNums && item._noteNums[h.value]" bottom max-width="420">
                                                        <template v-slot:activator="{ on, attrs }">
                                                            <span v-bind="attrs" v-on="on" class="indicator-noted-cell">{{ item[h.value] }}<sup class="indicator-note-marker">{{ item._notes[h.value] }}</sup></span>
                                                        </template>
                                                        <div v-for="line in tableCellNoteLines(item._noteNums[h.value])" :key="line">{{ line }}</div>
                                                    </v-tooltip>
                                                    <strong v-else-if="h.value === 'time_period'">{{ item.time_period }}</strong>
                                                    <template v-else>{{ item[h.value] }}</template>
                                                </td>
                                            </tr>
                                        </template>
                                    </v-data-table>
                                    <div v-if="tableNotes.length || indicatorTableNotes.length" class="indicator-notes-list mt-3">
                                        <div class="text-subtitle-2 mb-1">{{ $t('data_notes_title') || 'Notes' }}</div>
                                        <p v-for="tn in indicatorTableNotes" :key="'tn-' + tn.id" class="mb-1 indicator-table-note">{{ tn.note }}</p>
                                        <ol v-if="tableNotes.length" class="mb-0">
                                            <li v-for="n in tableNotes" :key="n.id" :value="n.number">
                                                <span v-if="!n.missing">{{ n.text }}</span>
                                                <span v-else class="warning--text">{{ $t('data_note_missing_text') || 'No text defined for this note.' }}</span>
                                                <span class="grey--text text-caption ml-1">[{{ n.id }}]</span>
                                            </li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </v-tab-item>
                    </v-tabs-items>
                </div>
            </div>
        </div>
    `
})
