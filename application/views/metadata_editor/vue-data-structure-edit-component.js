// Create / edit global data structure (DSD) and components (master–detail).
Vue.component('data-structure-edit', {
    props: {
        id: { type: [String, Number], default: null }
    },
    data: function () {
        return {
            loading: false,
            saving: false,
            savingComponents: false,
            savingActiveComponent: false,
            savingProgress: { current: 0, total: 0, label: '' },
            saveComponentErrors: [],
            form: {
                agency: 'NADA',
                name: '',
                version: '1.0.0',
                idno: '',
                title: '',
                description: '',
                notes: '',
                status: 'draft'
            },
            structureStatus: 'draft',
            headerBaseline: '',
            componentRows: [],
            componentBaseline: {},
            pendingComponentDeletes: [],
            selectedComponentIndex: null,
            selectedComponentIndices: [],
            componentSearch: '',
            splitListWidth: 300,
            codelistCache: {},
            codelistSearchResults: [],
            codelistSearchLoading: false,
            codelistSearchQuery: '',
            _codelistsPreloaded: false,
            _rowKeyCounter: 0,
            _codelistSearchSeq: 0,
            _codelistExplicitClear: false,
            activeCodelistCodes: [],
            activeCodelistCodesLoading: false,
            activeCodelistCodesTotal: 0,
            activeCodelistCodesPage: 1,
            activeCodelistCodesPerPage: 25,
            activeCodelistCodesSearchInput: '',
            activeCodelistCodesSearch: '',
            _activeCodelistCodesForId: null,
            _activeCodelistCodesLoadKey: null,
            copyComponentDialog: false,
            copyComponentSearch: '',
            copyComponentList: [],
            copyComponentLoading: false,
            copyComponentTotal: 0,
            copyComponentPage: 1,
            copyComponentPerPage: 50,
            copyComponentRequestSeq: 0,
            copyComponentSelected: {}
        };
    },
    computed: {
        isCreate: function () {
            return this.id == null || this.id === '' || this.id === undefined;
        },
        numericId: function () {
            var n = parseInt(this.id, 10);
            return isNaN(n) ? null : n;
        },
        isReadOnly: function () {
            if (this.isCreate) {
                return false;
            }
            var s = (this.structureStatus || 'draft').toLowerCase();
            return s === 'published' || s === 'archived';
        },
        canEditIdentity: function () {
            if (this.isCreate) {
                return true;
            }
            if (this.isReadOnly) {
                return false;
            }
            var s = (this.form.status || this.structureStatus || 'draft').toLowerCase();
            return s === 'draft';
        },
        identityLocked: function () {
            return !this.canEditIdentity;
        },
        columnTypes: function () {
            return {
                dimension: this.$t('dimension') || 'Dimension',
                time_period: this.$t('time_period') || 'Time Period',
                measure: this.$t('measure') || 'Measure',
                attribute: this.$t('attribute') || 'Attribute',
                indicator_id: this.$t('indicator_id') || 'Indicator ID',
                indicator_name: this.$t('indicator_name') || 'Indicator Name',
                annotation: this.$t('annotation') || 'Annotation',
                geography: this.$t('geography') || 'Geography',
                observation_value: this.$t('observation_value') || 'Observation Value',
                periodicity: this.$t('periodicity') || 'Periodicity'
            };
        },
        columnTypeItems: function () {
            var labels = this.columnTypes;
            return Object.keys(labels).map(function (value) {
                return { value: value, text: labels[value] };
            });
        },
        dataTypeItems: function () {
            return [
                { value: '', text: '(none)' },
                { value: 'string', text: 'String' },
                { value: 'integer', text: 'Integer' },
                { value: 'float', text: 'Float' },
                { value: 'double', text: 'Double' },
                { value: 'date', text: 'Date' },
                { value: 'boolean', text: 'Boolean' }
            ];
        },
        statusSelectItems: function () {
            return [
                { value: 'draft', text: 'Draft' },
                { value: 'review', text: 'Review' },
                { value: 'published', text: 'Published' },
                { value: 'deprecated', text: 'Deprecated' },
                { value: 'archived', text: 'Archived' }
            ];
        },
        canEditComponents: function () {
            return !this.isCreate && this.numericId && !this.isReadOnly;
        },
        sortedComponentIndices: function () {
            var vm = this;
            var indices = vm.componentRows.map(function (_, i) { return i; });
            indices.sort(function (a, b) {
                var sa = parseInt(vm.componentRows[a].sort_order, 10);
                var sb = parseInt(vm.componentRows[b].sort_order, 10);
                if (isNaN(sa)) { sa = 0; }
                if (isNaN(sb)) { sb = 0; }
                if (sa !== sb) { return sa - sb; }
                return a - b;
            });
            return indices;
        },
        filteredComponentIndices: function () {
            var vm = this;
            var q = (vm.componentSearch || '').trim().toLowerCase();
            return vm.sortedComponentIndices.filter(function (i) {
                var r = vm.componentRows[i];
                if (!q) {
                    return true;
                }
                var name = (r.name || '').toLowerCase();
                var label = (r.label || '').toLowerCase();
                var ct = vm.columnTypeLabel(r.column_type).toLowerCase();
                return name.indexOf(q) >= 0 || label.indexOf(q) >= 0 || ct.indexOf(q) >= 0;
            });
        },
        activeComponent: function () {
            if (this.selectedComponentIndex === null || this.selectedComponentIndex === undefined) {
                return null;
            }
            return this.componentRows[this.selectedComponentIndex] || null;
        },
        activeCodelistId: function () {
            var row = this.activeComponent;
            if (!row || !row.codelist_id) {
                return null;
            }
            var id = parseInt(row.codelist_id, 10);
            return isNaN(id) ? null : id;
        },
        activeCodelistMeta: function () {
            var id = this.activeCodelistId;
            if (!id) {
                return null;
            }
            return this.codelistCache[id] || null;
        },
        activeCodelistPreviewTitle: function () {
            var row = this.activeComponent;
            if (row && row.codelist_display) {
                return row.codelist_display;
            }
            if (this.activeCodelistMeta) {
                return this.codelistItemText(this.activeCodelistMeta);
            }
            if (this.activeCodelistId) {
                return 'Codelist #' + this.activeCodelistId;
            }
            return '';
        },
        activeCodelistCodesOffset: function () {
            return (this.activeCodelistCodesPage - 1) * this.activeCodelistCodesPerPage;
        },
        activeCodelistCodeHeaders: function () {
            return [
                { text: 'Code', value: 'code', sortable: false, width: '140px' },
                { text: 'Label', value: 'label', sortable: false }
            ];
        },
        activeCodelistCodeRows: function () {
            return (this.activeCodelistCodes || []).map(function (c, idx) {
                return {
                    id: c.id != null ? String(c.id) : String((c.code || 'row') + '-' + idx),
                    code: c.code || '',
                    label: (c.label && String(c.label).trim()) ? c.label : '—'
                };
            });
        },
        isActiveComponentDirty: function () {
            var row = this.activeComponent;
            return row ? this.isRowChanged(row) : false;
        },
        isHeaderDirty: function () {
            return this.headerSnapshot() !== this.headerBaseline;
        },
        isComponentsDirty: function () {
            var vm = this;
            if (vm.pendingComponentDeletes.length > 0) {
                return true;
            }
            for (var i = 0; i < vm.componentRows.length; i++) {
                if (vm.isRowChanged(vm.componentRows[i])) {
                    return true;
                }
            }
            return false;
        },
        dirtyComponentCount: function () {
            var vm = this;
            var n = vm.pendingComponentDeletes.length;
            vm.componentRows.forEach(function (r) {
                if (vm.isRowChanged(r)) {
                    n++;
                }
            });
            return n;
        },
        codelistAutocompleteItems: function () {
            var vm = this;
            var byId = {};
            (vm.codelistSearchResults || []).forEach(function (cl) {
                if (cl && cl.id != null) {
                    var nid = parseInt(cl.id, 10);
                    if (!isNaN(nid)) {
                        byId[nid] = Object.assign({}, cl, { id: nid });
                    }
                }
            });
            var active = vm.activeComponent;
            if (active && active.codelist_id) {
                var cid = parseInt(active.codelist_id, 10);
                if (!isNaN(cid)) {
                    if (vm.codelistCache[cid]) {
                        byId[cid] = vm.codelistCache[cid];
                    } else if (!byId[cid]) {
                        byId[cid] = {
                            id: cid,
                            title: active.codelist_display || ('Codelist #' + cid),
                            agency: active.codelist_agency || '',
                            name: active.codelist_name || ''
                        };
                    }
                }
            }
            return Object.keys(byId).map(function (k) { return byId[k]; });
        },
        savingProgressPercent: function () {
            if (!this.savingProgress.total) {
                return 0;
            }
            return Math.round((this.savingProgress.current / this.savingProgress.total) * 100);
        },
        allFilteredComponentsSelected: function () {
            var vm = this;
            var filtered = vm.filteredComponentIndices;
            if (!filtered.length) {
                return false;
            }
            return filtered.every(function (i) {
                return vm.selectedComponentIndices.indexOf(i) >= 0;
            });
        },
        someFilteredComponentsSelected: function () {
            return this.selectedComponentIndices.length > 0 && !this.allFilteredComponentsSelected;
        },
        selectedComponentCount: function () {
            return this.selectedComponentIndices.length;
        },
        copyComponentPickerHeaders: function () {
            return [
                { text: '', value: 'select', sortable: false, width: '40px' },
                { text: 'Name', value: 'name' },
                { text: 'Label', value: 'label' },
                { text: 'Type', value: 'column_type' },
                { text: 'Data structure', value: 'data_structure' },
                { text: 'Codelist', value: 'codelist' }
            ];
        },
        copyComponentSelectedCount: function () {
            return Object.keys(this.copyComponentSelected).length;
        },
        copyComponentPageCount: function () {
            if (!this.copyComponentTotal || !this.copyComponentPerPage) {
                return 1;
            }
            return Math.max(1, Math.ceil(this.copyComponentTotal / this.copyComponentPerPage));
        },
        copyComponentCanSearch: function () {
            return (this.copyComponentSearch || '').trim().length >= 2;
        }
    },
    watch: {
        id: function () { this.bootstrap(); },
        selectedComponentIndex: function () {
            this.ensureActiveCodelistCached();
        }
    },
    mounted: function () {
        var vm = this;
        vm.bootstrap();
        vm.searchCodelistsDebounced = _.debounce(function (q) {
            vm.searchCodelists(q);
        }, 300);
        vm.loadCopyComponentPickerDebounced = _.debounce(function () {
            vm.loadCopyComponentPickerList();
        }, 300);
    },
    beforeDestroy: function () {
        if (this.searchCodelistsDebounced && this.searchCodelistsDebounced.cancel) {
            this.searchCodelistsDebounced.cancel();
        }
        if (this.loadCopyComponentPickerDebounced && this.loadCopyComponentPickerDebounced.cancel) {
            this.loadCopyComponentPickerDebounced.cancel();
        }
    },
    methods: {
        apiBase: function () {
            return ((typeof CI !== 'undefined' && CI.site_url) ? CI.site_url.replace(/\/$/, '') : '') + '/api/data_structures';
        },
        codelistsApiBase: function () {
            return ((typeof CI !== 'undefined' && CI.site_url) ? CI.site_url.replace(/\/$/, '') : '') + '/api/codelists';
        },
        notifySuccess: function (msg) {
            if (typeof EventBus !== 'undefined') {
                EventBus.$emit('onSuccess', msg);
            }
        },
        notifyFail: function (err) {
            var m = 'Request failed';
            if (err && err.response && err.response.data && err.response.data.message) {
                m = err.response.data.message;
            } else if (typeof err === 'string') {
                m = err;
            }
            if (typeof EventBus !== 'undefined') {
                EventBus.$emit('onFail', m);
            } else {
                alert(m);
            }
        },
        extractApiError: function (err) {
            if (err && err.response && err.response.data && err.response.data.message) {
                return err.response.data.message;
            }
            if (err && err.message) {
                return err.message;
            }
            return 'Request failed';
        },
        columnTypeLabel: function (value) {
            if (!value) {
                return '';
            }
            return this.columnTypes[value] || String(value).replace(/_/g, ' ');
        },
        columnTypeColor: function (type) {
            var colors = {
                geography: 'green darken-1',
                time_period: 'green darken-1',
                indicator_id: 'deep-purple',
                observation_value: 'purple darken-1',
                dimension: 'blue darken-1',
                attribute: 'cyan darken-1',
                annotation: 'blue-grey',
                periodicity: 'amber darken-2',
                indicator_name: 'indigo',
                measure: 'brown'
            };
            return colors[type] || 'blue-grey darken-1';
        },
        headerSnapshot: function () {
            var f = this.form;
            var snap = {
                title: (f.title || '').trim() || null,
                description: (f.description || '').trim() || null,
                notes: (f.notes || '').trim() || null,
                status: f.status || 'draft',
                idno: (f.idno || '').trim() || null
            };
            snap.agency = (f.agency || 'NADA').trim();
            snap.name = (f.name || '').trim();
            snap.version = (f.version || '1.0.0').trim();
            return JSON.stringify(snap);
        },
        rebuildHeaderBaseline: function () {
            this.headerBaseline = this.headerSnapshot();
        },
        applyHeaderBaseline: function () {
            var p = JSON.parse(this.headerBaseline);
            this.form.title = p.title || '';
            this.form.description = p.description || '';
            this.form.notes = p.notes || '';
            this.form.status = p.status || 'draft';
            this.form.idno = p.idno || '';
            this.form.agency = p.agency || 'NADA';
            this.form.name = p.name || '';
            this.form.version = p.version || '1.0.0';
        },
        cancelHeader: function () {
            var vm = this;
            if (vm.isReadOnly || !vm.isHeaderDirty) {
                return;
            }
            if (!confirm('Discard unsaved changes to the data structure?')) {
                return;
            }
            if (vm.isCreate) {
                vm.$router.push('/');
                return;
            }
            vm.applyHeaderBaseline();
            vm.notifySuccess('Changes discarded');
        },
        rowSnapshot: function (row) {
            var p = this.componentPayload(row);
            return JSON.stringify(p);
        },
        isRowChanged: function (row) {
            if (!row) {
                return false;
            }
            if (!row.id) {
                return true;
            }
            var base = this.componentBaseline[row.id];
            if (base === undefined) {
                return true;
            }
            return this.rowSnapshot(row) !== base;
        },
        rebuildBaseline: function () {
            var vm = this;
            vm.componentBaseline = {};
            vm.componentRows.forEach(function (row) {
                if (row.id) {
                    vm.componentBaseline[row.id] = vm.rowSnapshot(row);
                }
            });
        },
        nextLocalKey: function () {
            this._rowKeyCounter += 1;
            return 'local-' + this._rowKeyCounter;
        },
        bootstrap: function () {
            var vm = this;
            vm.pendingComponentDeletes = [];
            vm.selectedComponentIndex = null;
            vm.saveComponentErrors = [];
            vm.componentBaseline = {};
            if (vm.isCreate) {
                vm.form = {
                    agency: 'NADA',
                    name: '',
                    version: '1.0.0',
                    idno: '',
                    title: '',
                    description: '',
                    notes: '',
                    status: 'draft'
                };
                vm.structureStatus = 'draft';
                vm.componentRows = [];
                vm.rebuildHeaderBaseline();
                return;
            }
            vm.loadStructure();
        },
        loadStructure: function (options) {
            var vm = this;
            options = options || {};
            if (!vm.numericId) {
                return Promise.resolve();
            }
            vm.loading = true;
            vm.saveComponentErrors = [];
            return axios.get(vm.apiBase() + '/single/' + vm.numericId + '?with_components=1')
                .then(function (res) {
                    vm.loading = false;
                    if (!res.data || res.data.status !== 'success' || !res.data.data_structure) {
                        return;
                    }
                    var ds = res.data.data_structure;
                    vm.form = {
                        agency: ds.agency || 'NADA',
                        name: ds.name || '',
                        version: ds.version || '',
                        idno: ds.idno || '',
                        title: ds.title || '',
                        description: ds.description || '',
                        notes: ds.notes || '',
                        status: ds.status || 'draft'
                    };
                    vm.structureStatus = ds.status || 'draft';
                    vm.rebuildHeaderBaseline();
                    vm.componentRows = (ds.components || []).map(function (c, idx) {
                        return vm.mapApiComponentToRow(c, idx);
                    });
                    vm.rebuildBaseline();
                    vm.pendingComponentDeletes = [];
                    vm.selectedComponentIndices = [];
                    if (vm.componentRows.length && vm.selectedComponentIndex === null) {
                        vm.selectedComponentIndex = 0;
                    } else if (vm.selectedComponentIndex >= vm.componentRows.length) {
                        vm.selectedComponentIndex = vm.componentRows.length ? vm.componentRows.length - 1 : null;
                    }
                    vm.ensureActiveCodelistCached();
                    vm.refreshActiveCodelistCodesIfNeeded();
                    if (options.refreshValidation) {
                        vm.refreshStructureValidation();
                    }
                })
                .catch(function (err) {
                    vm.loading = false;
                    vm.notifyFail(err);
                });
        },
        mapApiComponentToRow: function (c, idx) {
            var codelistId = null;
            if (c.codelist_id) {
                codelistId = parseInt(c.codelist_id, 10);
            } else if (c.codelist_reference && c.codelist_reference.id) {
                codelistId = parseInt(c.codelist_reference.id, 10);
            }
            var codelistDisplay = '';
            var codelistAgency = '';
            var codelistName = '';
            if (codelistId && !isNaN(codelistId) && c.codelist_reference) {
                var ref = c.codelist_reference;
                codelistAgency = ref.agency || '';
                codelistName = ref.name || '';
                codelistDisplay = (ref.title && String(ref.title).trim()) || '';
                this.cacheCodelist({
                    id: codelistId,
                    idno: ref.idno,
                    agency: codelistAgency,
                    name: codelistName,
                    version: ref.version,
                    title: ref.title
                });
            }
            return {
                _localKey: this.nextLocalKey(),
                id: c.id || null,
                sort_order: c.sort_order != null ? String(c.sort_order) : String(idx),
                name: c.name || '',
                label: c.label || '',
                description: c.description || '',
                column_type: c.column_type || 'dimension',
                data_type: c.data_type || '',
                codelist_id: codelistId && !isNaN(codelistId) ? codelistId : null,
                codelist_display: codelistDisplay,
                codelist_agency: codelistAgency,
                codelist_name: codelistName
            };
        },
        emptyComponentRow: function () {
            var n = this.componentRows.length;
            return {
                _localKey: this.nextLocalKey(),
                id: null,
                sort_order: String(n),
                name: '',
                label: '',
                description: '',
                column_type: 'dimension',
                data_type: '',
                codelist_id: null,
                codelist_display: '',
                codelist_agency: '',
                codelist_name: ''
            };
        },
        cacheCodelist: function (cl) {
            if (!cl || cl.id == null) {
                return;
            }
            var id = parseInt(cl.id, 10);
            if (isNaN(id)) {
                return;
            }
            Vue.set(this.codelistCache, id, Object.assign({}, cl, { id: id }));
        },
        codelistItemText: function (cl) {
            if (!cl) {
                return '';
            }
            var title = (cl.title && String(cl.title).trim()) || '';
            var agency = String(cl.agency || '');
            var name = String(cl.name || '');
            var suffix = agency && name ? ' (' + agency + ':' + name + ')' : '';
            return (title || name || String(cl.id)) + suffix;
        },
        normalizeCodelistId: function (val) {
            if (val == null || val === '') {
                return null;
            }
            if (typeof val === 'object' && val.id != null) {
                val = val.id;
            }
            var n = parseInt(val, 10);
            return isNaN(n) ? null : n;
        },
        setRowCodelist: function (row, id) {
            if (!row) {
                return;
            }
            Vue.set(row, 'codelist_id', id);
            if (!id) {
                Vue.set(row, 'codelist_display', '');
                Vue.set(row, 'codelist_agency', '');
                Vue.set(row, 'codelist_name', '');
                return;
            }
            var cl = this.codelistCache[id];
            if (cl) {
                Vue.set(row, 'codelist_display', cl.title || '');
                Vue.set(row, 'codelist_agency', cl.agency || '');
                Vue.set(row, 'codelist_name', cl.name || '');
            }
        },
        onCodelistIdInput: function (val) {
            var vm = this;
            var row = vm.activeComponent;
            if (!row) {
                return;
            }
            if (val == null || val === '') {
                if (vm._codelistExplicitClear) {
                    vm.setRowCodelist(row, null);
                    vm._codelistExplicitClear = false;
                    vm.refreshActiveCodelistCodesIfNeeded();
                }
                return;
            }
            vm._codelistExplicitClear = false;
            if (typeof val === 'object' && val.id != null) {
                vm.cacheCodelist(val);
            }
            var id = vm.normalizeCodelistId(val);
            if (id == null) {
                return;
            }
            if (Number(row.codelist_id) === id) {
                return;
            }
            var list = vm.codelistSearchResults || [];
            for (var i = 0; i < list.length; i++) {
                if (parseInt(list[i].id, 10) === id) {
                    vm.cacheCodelist(list[i]);
                    break;
                }
            }
            vm.setRowCodelist(row, id);
            vm.refreshActiveCodelistCodesIfNeeded();
        },
        onCodelistAutocompleteClear: function () {
            this._codelistExplicitClear = true;
        },
        onCodelistSearchInput: function (q) {
            var vm = this;
            vm.codelistSearchQuery = q == null ? '' : String(q);
            var trimmed = vm.codelistSearchQuery.trim();
            if (trimmed.length < 2) {
                vm.codelistSearchLoading = false;
                return;
            }
            var active = vm.activeComponent;
            if (active && active.codelist_id && vm.codelistCache[active.codelist_id]) {
                var label = vm.codelistItemText(vm.codelistCache[active.codelist_id]);
                if (trimmed === label) {
                    vm.codelistSearchLoading = false;
                    return;
                }
            }
            if (vm.searchCodelistsDebounced) {
                vm.searchCodelistsDebounced(trimmed);
            }
        },
        searchCodelists: function (q) {
            var vm = this;
            q = (q || '').trim();
            if (q.length < 2) {
                vm.codelistSearchLoading = false;
                return;
            }
            var seq = ++vm._codelistSearchSeq;
            vm.codelistSearchLoading = true;
            axios.get(vm.codelistsApiBase(), {
                params: {
                    search: q,
                    page: 1,
                    per_page: 40,
                    order_by: 'name',
                    order_dir: 'ASC',
                    exclude_archived: 1
                }
            })
                .then(function (res) {
                    if (seq !== vm._codelistSearchSeq) {
                        return;
                    }
                    if (res.data && res.data.status === 'success') {
                        vm.codelistSearchResults = res.data.codelists || [];
                        vm.codelistSearchResults.forEach(function (cl) { vm.cacheCodelist(cl); });
                    }
                })
                .catch(function () { /* ignore */ })
                .finally(function () {
                    if (seq === vm._codelistSearchSeq) {
                        vm.codelistSearchLoading = false;
                    }
                });
        },
        preloadCodelistsIfNeeded: function () {
            var vm = this;
            if (vm._codelistsPreloaded) {
                return;
            }
            vm._codelistsPreloaded = true;
            axios.get(vm.codelistsApiBase(), {
                params: {
                    page: 1,
                    per_page: 50,
                    order_by: 'name',
                    order_dir: 'ASC',
                    exclude_archived: 1
                }
            })
                .then(function (res) {
                    if (res.data && res.data.status === 'success') {
                        var list = res.data.codelists || [];
                        list.forEach(function (cl) { vm.cacheCodelist(cl); });
                        if (!vm.codelistSearchResults.length) {
                            vm.codelistSearchResults = list;
                        }
                    }
                })
                .catch(function () { /* ignore */ });
        },
        onCodelistAutocompleteFocus: function () {
            this._codelistExplicitClear = false;
            this.codelistSearchLoading = false;
            this.preloadCodelistsIfNeeded();
            this.ensureActiveCodelistCached();
        },
        ensureActiveCodelistCached: function () {
            var row = this.activeComponent;
            if (!row || !row.codelist_id) {
                return;
            }
            var id = parseInt(row.codelist_id, 10);
            if (isNaN(id) || this.codelistCache[id]) {
                return;
            }
            var vm = this;
            axios.get(vm.codelistsApiBase() + '/single/' + id)
                .then(function (res) {
                    if (res.data && res.data.codelist) {
                        var cl = res.data.codelist;
                        vm.cacheCodelist(cl);
                        if (vm.activeComponent && Number(vm.activeComponent.codelist_id) === id) {
                            Vue.set(vm.activeComponent, 'codelist_display', cl.title || '');
                            Vue.set(vm.activeComponent, 'codelist_agency', cl.agency || '');
                            Vue.set(vm.activeComponent, 'codelist_name', cl.name || '');
                        }
                    }
                })
                .catch(function () { /* ignore */ });
        },
        clearActiveCodelistCodesView: function () {
            this.activeCodelistCodes = [];
            this.activeCodelistCodesTotal = 0;
            this.activeCodelistCodesPage = 1;
            this.activeCodelistCodesSearch = '';
            this.activeCodelistCodesSearchInput = '';
            this._activeCodelistCodesForId = null;
            this._activeCodelistCodesLoadKey = null;
            this.activeCodelistCodesLoading = false;
        },
        activeCodelistCodesLoadKey: function () {
            var cid = this.activeCodelistId;
            if (!cid) {
                return '';
            }
            return cid + '|' + this.activeCodelistCodesPage + '|' + this.activeCodelistCodesSearch + '|' + this.activeCodelistCodesOffset;
        },
        loadActiveCodelistCodes: function () {
            var vm = this;
            var cid = vm.activeCodelistId;
            if (!cid) {
                vm.clearActiveCodelistCodesView();
                return;
            }
            var loadKey = vm.activeCodelistCodesLoadKey();
            vm._activeCodelistCodesLoadKey = loadKey;
            vm.activeCodelistCodesLoading = true;
            vm._activeCodelistCodesForId = cid;
            var params = {
                offset: vm.activeCodelistCodesOffset,
                limit: vm.activeCodelistCodesPerPage,
                compact: 1
            };
            if (vm.activeCodelistCodesSearch) {
                params.search = vm.activeCodelistCodesSearch;
            }
            axios.get(vm.codelistsApiBase() + '/codes/' + cid, { params: params, timeout: 30000 })
                .then(function (res) {
                    if (vm._activeCodelistCodesLoadKey !== loadKey) {
                        return;
                    }
                    vm.activeCodelistCodesLoading = false;
                    var payload = res && res.data ? res.data : {};
                    if (payload.status === 'success') {
                        vm.activeCodelistCodes = Array.isArray(payload.codes) ? payload.codes.slice() : [];
                        vm.activeCodelistCodesTotal = payload.total != null ? payload.total : vm.activeCodelistCodes.length;
                    } else {
                        vm.activeCodelistCodes = [];
                        vm.activeCodelistCodesTotal = 0;
                        if (payload.message) {
                            vm.notifyFail(payload.message);
                        }
                    }
                })
                .catch(function (err) {
                    if (vm._activeCodelistCodesLoadKey !== loadKey) {
                        return;
                    }
                    vm.activeCodelistCodesLoading = false;
                    vm.notifyFail(err);
                });
        },
        refreshActiveCodelistCodesIfNeeded: function () {
            var vm = this;
            var cid = vm.activeCodelistId;
            if (!cid) {
                vm.clearActiveCodelistCodesView();
                return;
            }
            vm.ensureActiveCodelistCached();
            if (Number(vm._activeCodelistCodesForId) !== Number(cid)) {
                vm.activeCodelistCodesPage = 1;
                vm.activeCodelistCodesSearch = '';
                vm.activeCodelistCodesSearchInput = '';
            }
            var loadKey = vm.activeCodelistCodesLoadKey();
            if (loadKey === vm._activeCodelistCodesLoadKey && vm.activeCodelistCodes.length > 0 && !vm.activeCodelistCodesLoading) {
                return;
            }
            vm.loadActiveCodelistCodes();
        },
        onActiveCodelistCodesSearchSubmit: function () {
            this.activeCodelistCodesSearch = (this.activeCodelistCodesSearchInput || '').trim();
            this.activeCodelistCodesPage = 1;
            this.loadActiveCodelistCodes();
        },
        onActiveCodelistCodesSearchClear: function () {
            this.activeCodelistCodesSearchInput = '';
            this.activeCodelistCodesSearch = '';
            this.activeCodelistCodesPage = 1;
            this.loadActiveCodelistCodes();
        },
        prevActiveCodelistCodesPage: function () {
            if (this.activeCodelistCodesPage > 1) {
                this.activeCodelistCodesPage--;
                this.loadActiveCodelistCodes();
            }
        },
        nextActiveCodelistCodesPage: function () {
            if (this.activeCodelistCodesOffset + this.activeCodelistCodesPerPage < this.activeCodelistCodesTotal) {
                this.activeCodelistCodesPage++;
                this.loadActiveCodelistCodes();
            }
        },
        selectComponent: function (index) {
            if (this.selectedComponentIndex === index) {
                return;
            }
            this.codelistSearchLoading = false;
            this.codelistSearchQuery = '';
            this._codelistSearchSeq += 1;
            this._codelistExplicitClear = false;
            this.selectedComponentIndex = index;
            this.ensureActiveCodelistCached();
            this.refreshActiveCodelistCodesIfNeeded();
        },
        listRowClass: function (index) {
            var classes = ['ds-comp-list-item'];
            if (this.selectedComponentIndex === index) {
                classes.push('ds-comp-list-item--active');
            }
            if (this.canEditComponents && this.isRowChanged(this.componentRows[index])) {
                classes.push('ds-comp-list-item--dirty');
            }
            return classes.join(' ');
        },
        addComponentRow: function () {
            if (!this.canEditComponents) {
                this.notifyFail('Save the data structure header first.');
                return;
            }
            var row = this.emptyComponentRow();
            this.componentRows.push(row);
            this.selectedComponentIndex = this.componentRows.length - 1;
        },
        openCsvBootstrap: function () {
            if (!this.canEditComponents) {
                this.notifyFail('Save the data structure header first.');
                return;
            }
            if (!this.numericId) {
                return;
            }
            this.$router.push('/edit/' + this.numericId + '/bootstrap-csv');
        },
        openCopyComponentDialog: function () {
            if (!this.canEditComponents) {
                this.notifyFail('Save the data structure header first.');
                return;
            }
            if (!this.numericId) {
                return;
            }
            this.copyComponentDialog = true;
            this.copyComponentSearch = '';
            this.copyComponentList = [];
            this.copyComponentTotal = 0;
            this.copyComponentPage = 1;
            this.copyComponentLoading = false;
            this.copyComponentSelected = {};
        },
        closeCopyComponentDialog: function () {
            this.copyComponentRequestSeq += 1;
            this.copyComponentDialog = false;
            this.copyComponentSearch = '';
            this.copyComponentList = [];
            this.copyComponentTotal = 0;
            this.copyComponentPage = 1;
            this.copyComponentLoading = false;
            this.copyComponentSelected = {};
        },
        onCopyComponentSearchInput: function () {
            if (!this.copyComponentDialog) {
                return;
            }
            this.copyComponentPage = 1;
            if (!this.copyComponentCanSearch) {
                this.copyComponentList = [];
                this.copyComponentTotal = 0;
                this.copyComponentLoading = false;
                return;
            }
            if (this.loadCopyComponentPickerDebounced) {
                this.loadCopyComponentPickerDebounced();
            } else {
                this.loadCopyComponentPickerList();
            }
        },
        loadCopyComponentPickerList: function () {
            var vm = this;
            if (!vm.copyComponentDialog || !vm.copyComponentCanSearch) {
                return;
            }
            var requestId = ++vm.copyComponentRequestSeq;
            vm.copyComponentLoading = true;
            var params = {
                page: vm.copyComponentPage,
                per_page: vm.copyComponentPerPage,
                search: (vm.copyComponentSearch || '').trim(),
                exclude_structure_id: vm.numericId,
                order_by: 'name',
                order_dir: 'ASC'
            };
            axios.get(vm.apiBase() + '/components', { params: params, timeout: 30000 })
                .then(function (res) {
                    vm.copyComponentLoading = false;
                    if (requestId !== vm.copyComponentRequestSeq || !vm.copyComponentDialog) {
                        return;
                    }
                    if (res.data && res.data.status === 'success') {
                        vm.copyComponentList = res.data.components || [];
                        vm.copyComponentTotal = res.data.total != null ? res.data.total : vm.copyComponentList.length;
                    } else {
                        vm.copyComponentList = [];
                        vm.copyComponentTotal = 0;
                        vm.notifyFail((res.data && res.data.message) ? res.data.message : 'Could not search components.');
                    }
                })
                .catch(function (err) {
                    vm.copyComponentLoading = false;
                    if (requestId !== vm.copyComponentRequestSeq) {
                        return;
                    }
                    vm.copyComponentList = [];
                    vm.copyComponentTotal = 0;
                    var msg = 'Could not search components.';
                    if (err.code === 'ECONNABORTED') {
                        msg = 'Component search timed out. Try a shorter search term.';
                    } else if (err.response && err.response.data && err.response.data.message) {
                        msg = err.response.data.message;
                    }
                    vm.notifyFail(msg);
                });
        },
        copyComponentStructureLabel: function (item) {
            var ds = (item && item.data_structure) ? item.data_structure : {};
            var title = (ds.title && String(ds.title).trim()) || '';
            var agency = String(ds.agency || '');
            var name = String(ds.name || '');
            var version = String(ds.version || '');
            var identity = agency && name ? agency + ':' + name : (name || agency);
            if (version) {
                identity += '@' + version;
            }
            if (title) {
                return title + (identity ? ' (' + identity + ')' : '');
            }
            return identity || ('DSD #' + (ds.id || ''));
        },
        copyComponentCodelistLabel: function (item) {
            if (!item || !item.codelist_id) {
                return '—';
            }
            if (item.codelist_reference) {
                var ref = item.codelist_reference;
                var agency = ref.agency || '';
                var name = ref.name || '';
                if (agency && name) {
                    return agency + ':' + name;
                }
                return name || ref.idno || String(item.codelist_id);
            }
            return '#' + item.codelist_id;
        },
        isCopyComponentSelected: function (item) {
            return !!(item && item.id && this.copyComponentSelected[item.id]);
        },
        toggleCopyComponentSelection: function (item) {
            if (!item || item.id == null) {
                return;
            }
            if (this.copyComponentSelected[item.id]) {
                Vue.delete(this.copyComponentSelected, item.id);
            } else {
                Vue.set(this.copyComponentSelected, item.id, item);
            }
        },
        prevCopyComponentPage: function () {
            if (this.copyComponentPage <= 1 || this.copyComponentLoading) {
                return;
            }
            this.copyComponentPage -= 1;
            this.loadCopyComponentPickerList();
        },
        nextCopyComponentPage: function () {
            if (this.copyComponentPage >= this.copyComponentPageCount || this.copyComponentLoading) {
                return;
            }
            this.copyComponentPage += 1;
            this.loadCopyComponentPickerList();
        },
        mapCatalogComponentToNewRow: function (c) {
            var row = this.mapApiComponentToRow(c, this.componentRows.length);
            row.id = null;
            row._localKey = this.nextLocalKey();
            row.sort_order = String(this.componentRows.length);
            return row;
        },
        applyCopyComponents: function () {
            var vm = this;
            var selected = Object.keys(vm.copyComponentSelected).map(function (id) {
                return vm.copyComponentSelected[id];
            });
            if (!selected.length) {
                vm.notifyFail('Select at least one component to copy.');
                return;
            }
            var existing = {};
            vm.componentRows.forEach(function (row) {
                var key = (row.name || '').trim().toLowerCase();
                if (key) {
                    existing[key] = true;
                }
            });
            var added = 0;
            var skipped = 0;
            var firstAddedIndex = null;
            selected.sort(function (a, b) {
                var an = (a.name || '').toLowerCase();
                var bn = (b.name || '').toLowerCase();
                if (an < bn) {
                    return -1;
                }
                if (an > bn) {
                    return 1;
                }
                return 0;
            });
            selected.forEach(function (c) {
                var nameKey = (c.name || '').trim().toLowerCase();
                if (!nameKey || existing[nameKey]) {
                    skipped += 1;
                    return;
                }
                var row = vm.mapCatalogComponentToNewRow(c);
                row.sort_order = String(vm.componentRows.length);
                vm.componentRows.push(row);
                existing[nameKey] = true;
                added += 1;
                if (firstAddedIndex === null) {
                    firstAddedIndex = vm.componentRows.length - 1;
                }
            });
            if (firstAddedIndex !== null) {
                vm.selectedComponentIndex = firstAddedIndex;
            }
            vm.closeCopyComponentDialog();
            if (added && skipped) {
                vm.notifySuccess('Copied ' + added + ' component(s); skipped ' + skipped + ' duplicate name(s).');
            } else if (added) {
                vm.notifySuccess('Copied ' + added + ' component(s). Save all to persist.');
            } else {
                vm.notifyFail('No components copied. Selected name(s) already exist in this DSD.');
            }
        },
        confirmRemoveActiveComponent: function () {
            if (this.selectedComponentIndex === null) {
                return;
            }
            this.confirmRemoveComponentRow(this.selectedComponentIndex);
        },
        reindexSelectedAfterDelete: function (deletedIndices) {
            var deleted = (deletedIndices || []).slice().sort(function (a, b) { return a - b; });
            var vm = this;
            vm.selectedComponentIndices = vm.selectedComponentIndices
                .filter(function (i) { return deleted.indexOf(i) < 0; })
                .map(function (i) {
                    var shift = deleted.filter(function (d) { return d < i; }).length;
                    return i - shift;
                });
        },
        confirmRemoveComponentRow: function (index) {
            this.removeComponentRowsAtIndices([index]);
        },
        isComponentRowSelected: function (index) {
            return this.selectedComponentIndices.indexOf(index) >= 0;
        },
        toggleComponentRowSelection: function (index) {
            var pos = this.selectedComponentIndices.indexOf(index);
            if (pos >= 0) {
                this.selectedComponentIndices.splice(pos, 1);
            } else {
                this.selectedComponentIndices.push(index);
            }
        },
        toggleSelectAllFilteredComponents: function () {
            if (this.allFilteredComponentsSelected) {
                this.selectedComponentIndices = [];
            } else {
                this.selectedComponentIndices = this.filteredComponentIndices.slice();
            }
        },
        confirmDeleteSelectedComponents: function () {
            if (!this.canEditComponents || !this.selectedComponentIndices.length) {
                return;
            }
            this.removeComponentRowsAtIndices(this.selectedComponentIndices.slice());
        },
        removeComponentRowsAtIndices: function (indices) {
            var vm = this;
            if (!indices || !indices.length) {
                return;
            }
            var unique = indices.filter(function (i, pos, arr) {
                return arr.indexOf(i) === pos && vm.componentRows[i];
            });
            if (!unique.length) {
                return;
            }
            unique.sort(function (a, b) { return a - b; });
            var msg = unique.length === 1
                ? 'Remove component "' + ((vm.componentRows[unique[0]].name || '').trim() || ('row ' + (unique[0] + 1))) + '"?'
                : 'Remove ' + unique.length + ' selected components?';
            if (!confirm(msg)) {
                return;
            }
            var deletedSet = {};
            unique.forEach(function (i) { deletedSet[i] = true; });
            unique.slice().sort(function (a, b) { return b - a; }).forEach(function (index) {
                var row = vm.componentRows[index];
                if (row && row.id) {
                    vm.pendingComponentDeletes.push(row.id);
                }
                vm.componentRows.splice(index, 1);
            });
            vm.reindexSelectedAfterDelete(unique);
            if (vm.selectedComponentIndex === null || vm.selectedComponentIndex === undefined) {
                return;
            }
            if (deletedSet[vm.selectedComponentIndex]) {
                var next = null;
                for (var j = 0; j < vm.componentRows.length; j++) {
                    next = j;
                    break;
                }
                vm.selectedComponentIndex = next;
            } else {
                var shift = unique.filter(function (i) { return i < vm.selectedComponentIndex; }).length;
                vm.selectedComponentIndex -= shift;
            }
        },
        navigateComponent: function (direction) {
            var indices = this.filteredComponentIndices;
            if (!indices.length || this.selectedComponentIndex === null) {
                return;
            }
            var pos = indices.indexOf(this.selectedComponentIndex);
            if (pos < 0) {
                this.selectedComponentIndex = indices[0];
                return;
            }
            if (direction === 'prev' && pos > 0) {
                this.selectedComponentIndex = indices[pos - 1];
            } else if (direction === 'next' && pos < indices.length - 1) {
                this.selectedComponentIndex = indices[pos + 1];
            } else if (direction === 'first') {
                this.selectedComponentIndex = indices[0];
            } else if (direction === 'last') {
                this.selectedComponentIndex = indices[indices.length - 1];
            }
        },
        componentPayload: function (row) {
            var sortOrder = row.sort_order !== '' && row.sort_order != null ? parseInt(row.sort_order, 10) : 0;
            if (isNaN(sortOrder)) {
                sortOrder = 0;
            }
            var p = {
                sort_order: sortOrder,
                name: (row.name || '').trim(),
                label: (row.label || '').trim() || null,
                description: (row.description || '').trim() || null,
                column_type: row.column_type,
                data_type: row.data_type || null,
                codelist_id: this.normalizeCodelistId(row.codelist_id)
            };
            if (p.data_type === '') {
                p.data_type = null;
            }
            return p;
        },
        applyComponentPayloadToRow: function (row) {
            if (!row) {
                return;
            }
            var p = this.componentPayload(row);
            row.sort_order = p.sort_order != null ? String(p.sort_order) : '0';
            row.name = p.name || '';
            row.label = p.label || '';
            row.description = p.description || '';
            row.column_type = p.column_type || 'dimension';
            row.data_type = p.data_type || '';
            Vue.set(row, 'codelist_id', p.codelist_id || null);
        },
        refreshStructureValidation: function () {
            var panel = this.$refs.validationPanel;
            if (panel && typeof panel.refresh === 'function') {
                panel.refresh();
            }
        },
        validateActiveComponent: function (row) {
            if (!row) {
                return 'No component selected.';
            }
            var label = (row.name || '').trim() || 'component';
            if (!(row.name || '').trim()) {
                return 'Component "' + label + '": name is required.';
            }
            if (!row.column_type) {
                return 'Component "' + label + '": column type is required.';
            }
            var key = (row.name || '').trim().toLowerCase();
            for (var i = 0; i < this.componentRows.length; i++) {
                var other = this.componentRows[i];
                if (other === row) {
                    continue;
                }
                if ((other.name || '').trim().toLowerCase() === key) {
                    return 'Duplicate component name "' + (row.name || '').trim() + '".';
                }
            }
            return null;
        },
        validateComponentRows: function () {
            var seen = {};
            for (var i = 0; i < this.componentRows.length; i++) {
                var r = this.componentRows[i];
                var err = this.validateActiveComponent(r);
                if (err) {
                    return err;
                }
                var key = (r.name || '').trim().toLowerCase();
                if (seen[key]) {
                    return 'Duplicate component name "' + (r.name || '').trim() + '".';
                }
                seen[key] = true;
            }
            return null;
        },
        markRowBaseline: function (row) {
            if (row && row.id) {
                Vue.set(this.componentBaseline, row.id, this.rowSnapshot(row));
            }
        },
        finalizeComponentSave: function (row) {
            if (!row) {
                return;
            }
            this.applyComponentPayloadToRow(row);
            this.markRowBaseline(row);
            this.refreshStructureValidation();
        },
        saveActiveComponent: function () {
            var vm = this;
            if (!vm.canEditComponents || !vm.activeComponent) {
                return;
            }
            var row = vm.activeComponent;
            if (!vm.isRowChanged(row)) {
                return;
            }
            var errMsg = vm.validateActiveComponent(row);
            if (errMsg) {
                vm.notifyFail(errMsg);
                return;
            }
            vm.savingActiveComponent = true;
            var req;
            if (!row.id) {
                req = axios.post(vm.apiBase() + '/components/' + vm.numericId, vm.componentPayload(row))
                    .then(function (res) {
                        if (res.data && res.data.id) {
                            row.id = res.data.id;
                        }
                    });
            } else {
                req = axios.post(vm.apiBase() + '/component_update/' + row.id, vm.componentPayload(row));
            }
            req.then(function () {
                vm.savingActiveComponent = false;
                vm.finalizeComponentSave(row);
                vm.notifySuccess('Component saved');
            }).catch(function (err) {
                vm.savingActiveComponent = false;
                vm.notifyFail(err);
            });
        },
        applySnapshotToRow: function (row, snapshotJson) {
            var p = JSON.parse(snapshotJson);
            row.sort_order = p.sort_order != null ? String(p.sort_order) : '0';
            row.name = p.name || '';
            row.label = p.label || '';
            row.description = p.description || '';
            row.column_type = p.column_type || 'dimension';
            row.data_type = p.data_type || '';
            row.codelist_id = p.codelist_id || null;
            if (row.codelist_id && this.codelistCache[row.codelist_id]) {
                var cl = this.codelistCache[row.codelist_id];
                row.codelist_display = cl.title || '';
                row.codelist_agency = cl.agency || '';
                row.codelist_name = cl.name || '';
            } else {
                row.codelist_display = '';
                row.codelist_agency = '';
                row.codelist_name = '';
            }
        },
        cancelActiveComponent: function () {
            var vm = this;
            if (!vm.canEditComponents || !vm.activeComponent || !vm.isActiveComponentDirty) {
                return;
            }
            if (!confirm('Discard unsaved changes to this component?')) {
                return;
            }
            var idx = vm.selectedComponentIndex;
            var row = vm.activeComponent;
            if (!row.id) {
                vm.componentRows.splice(idx, 1);
                vm.selectedComponentIndex = vm.componentRows.length
                    ? Math.min(idx, vm.componentRows.length - 1)
                    : null;
                vm.notifySuccess('Changes discarded');
                return;
            }
            var base = vm.componentBaseline[row.id];
            if (base === undefined) {
                vm.loadStructure();
                return;
            }
            vm.applySnapshotToRow(row, base);
            vm.codelistSearchLoading = false;
            vm.codelistSearchQuery = '';
            vm.notifySuccess('Changes discarded');
        },
        buildSavePlan: function () {
            var vm = this;
            var plan = { deletes: [], creates: [], updates: [] };
            vm.pendingComponentDeletes.forEach(function (id) {
                plan.deletes.push({ id: id, label: 'id ' + id });
            });
            vm.componentRows.forEach(function (row) {
                var label = (row.name || '').trim() || ('row ' + (row.sort_order || '?'));
                if (!row.id) {
                    plan.creates.push({ row: row, label: label });
                } else if (vm.isRowChanged(row)) {
                    plan.updates.push({ row: row, id: row.id, label: label });
                }
            });
            return plan;
        },
        revertComponents: function () {
            var vm = this;
            if (!vm.canEditComponents) {
                return;
            }
            if (vm.isComponentsDirty) {
                if (!confirm('Discard unsaved component changes?')) {
                    return;
                }
            }
            vm.selectedComponentIndex = null;
            vm.loadStructure();
        },
        saveStructure: function () {
            var vm = this;
            if (vm.isReadOnly) {
                return;
            }
            if (!(vm.form.name || '').trim()) {
                vm.notifyFail('Name is required.');
                return;
            }
            var payload;
            if (vm.isCreate) {
                payload = {
                    agency: (vm.form.agency || 'NADA').trim(),
                    name: vm.form.name.trim(),
                    version: (vm.form.version || '1.0.0').trim(),
                    title: vm.form.title || null,
                    description: vm.form.description || null,
                    notes: vm.form.notes || null,
                    status: vm.form.status || 'draft'
                };
                if ((vm.form.idno || '').trim()) {
                    payload.idno = vm.form.idno.trim();
                }
            } else {
                payload = {
                    title: vm.form.title || null,
                    description: vm.form.description || null,
                    notes: vm.form.notes || null,
                    status: vm.form.status || 'draft',
                    idno: vm.form.idno || null
                };
                if (vm.canEditIdentity) {
                    payload.agency = (vm.form.agency || 'NADA').trim();
                    payload.name = vm.form.name.trim();
                    payload.version = (vm.form.version || '1.0.0').trim();
                }
            }
            vm.saving = true;
            var req = vm.isCreate
                ? axios.post(vm.apiBase() + '/create', payload)
                : axios.post(vm.apiBase() + '/update/' + vm.numericId, payload);
            req.then(function (res) {
                vm.saving = false;
                if (vm.isCreate && res.data && res.data.id) {
                    vm.notifySuccess('Data structure created');
                    vm.$router.replace('/edit/' + res.data.id);
                } else {
                    vm.structureStatus = (vm.form.status || 'draft').toLowerCase();
                    vm.rebuildHeaderBaseline();
                    vm.notifySuccess('Saved');
                }
            }).catch(function (err) {
                vm.saving = false;
                vm.notifyFail(err);
            });
        },
        saveComponents: function () {
            var vm = this;
            if (!vm.canEditComponents || !vm.isComponentsDirty) {
                return;
            }
            var errMsg = vm.validateComponentRows();
            if (errMsg) {
                vm.notifyFail(errMsg);
                return;
            }
            var plan = vm.buildSavePlan();
            var total = plan.deletes.length + plan.creates.length + plan.updates.length;
            if (total === 0) {
                return;
            }
            vm.savingComponents = true;
            vm.saveComponentErrors = [];
            vm.savingProgress = { current: 0, total: total, label: '' };

            var runStep = function (label, promiseFactory) {
                vm.savingProgress.label = label;
                return promiseFactory().then(function (res) {
                    vm.savingProgress.current += 1;
                    return res;
                });
            };

            var chain = Promise.resolve();
            plan.deletes.forEach(function (item) {
                chain = chain.then(function () {
                    return runStep('Deleting ' + item.label, function () {
                        return axios.post(vm.apiBase() + '/component_delete/' + item.id);
                    });
                }).catch(function (err) {
                    vm.saveComponentErrors.push({ label: item.label, message: vm.extractApiError(err) });
                    vm.savingProgress.current += 1;
                    return Promise.resolve();
                });
            });
            plan.creates.forEach(function (item) {
                chain = chain.then(function () {
                    return runStep('Creating ' + item.label, function () {
                        return axios.post(vm.apiBase() + '/components/' + vm.numericId, vm.componentPayload(item.row));
                    }).then(function (res) {
                        if (res.data && res.data.id) {
                            item.row.id = res.data.id;
                        }
                        return res;
                    });
                }).catch(function (err) {
                    vm.saveComponentErrors.push({ label: item.label, message: vm.extractApiError(err) });
                    vm.savingProgress.current += 1;
                    return Promise.resolve();
                });
            });
            plan.updates.forEach(function (item) {
                chain = chain.then(function () {
                    return runStep('Updating ' + item.label, function () {
                        return axios.post(vm.apiBase() + '/component_update/' + item.id, vm.componentPayload(item.row));
                    });
                }).catch(function (err) {
                    vm.saveComponentErrors.push({ label: item.label, message: vm.extractApiError(err) });
                    vm.savingProgress.current += 1;
                    return Promise.resolve();
                });
            });

            chain.then(function () {
                vm.savingComponents = false;
                vm.savingProgress = { current: 0, total: 0, label: '' };
                if (vm.saveComponentErrors.length) {
                    vm.notifyFail(vm.saveComponentErrors.length + ' component(s) failed to save. See details below.');
                    return;
                }
                vm.pendingComponentDeletes = [];
                vm.notifySuccess('Components saved');
                vm.loadStructure({ refreshValidation: true });
            }).catch(function (err) {
                vm.savingComponents = false;
                vm.savingProgress = { current: 0, total: 0, label: '' };
                vm.notifyFail(err);
            });
        },
        cancel: function () {
            if (this.isHeaderDirty) {
                if (!confirm('You have unsaved data structure changes. Leave anyway?')) {
                    return;
                }
            }
            if (this.isComponentsDirty) {
                if (!confirm('You have unsaved component changes. Leave anyway?')) {
                    return;
                }
            }
            if (this.isCreate) {
                this.$router.push('/');
            } else {
                this.$router.push('/view/' + this.numericId);
            }
        },
        listItemKey: function (index) {
            var row = this.componentRows[index];
            return row.id != null ? 'c' + row.id : (row._localKey || 'n' + index);
        }
    },
    template: `
        <div class="ds-edit-form">
        <div class="mb-2 d-flex align-center flex-wrap" style="gap:8px;">
            <v-btn text @click="cancel"><v-icon left>mdi-arrow-left</v-icon> Back</v-btn>
        </div>
        <v-progress-linear v-if="loading" indeterminate class="mb-4"></v-progress-linear>
        <v-progress-linear v-if="savingComponents && savingProgress.total" :value="savingProgressPercent"
            height="4" class="mb-2" color="primary"></v-progress-linear>
        <div v-if="savingComponents && savingProgress.label" class="text-caption grey--text mb-2">
            {{ savingProgress.label }} ({{ savingProgress.current }} / {{ savingProgress.total }})
        </div>
        <v-alert v-if="saveComponentErrors.length" type="error" dense outlined class="mb-3">
            <div class="text-subtitle-2 mb-1">Some components could not be saved:</div>
            <ul class="mb-0 pl-4">
                <li v-for="(e, ei) in saveComponentErrors" :key="'err-' + ei">
                    <strong>{{ e.label }}</strong>: {{ e.message }}
                </li>
            </ul>
        </v-alert>
        <v-alert v-if="isReadOnly" type="info" dense outlined class="mb-4">
            This data structure is {{ structureStatus }} and cannot be edited. Change status to draft or review to edit.
        </v-alert>
        <v-card class="mb-3">
            <v-card-title class="text-subtitle-2 py-2">{{ isCreate ? 'New data structure' : 'Data structure' }}</v-card-title>
            <v-card-text class="ds-edit-card-body pt-0">
                <v-row dense class="ma-0">
                    <v-col cols="12" md="4" class="py-1">
                        <div class="ds-field-label">Agency</div>
                        <v-text-field v-model="form.agency" dense outlined
                            :disabled="identityLocked || isReadOnly" hide-details class="ds-header-control ds-field-stack"></v-text-field>
                    </v-col>
                    <v-col cols="12" md="4" class="py-1">
                        <div class="ds-field-label">Name</div>
                        <v-text-field v-model="form.name" dense outlined required
                            :disabled="identityLocked || isReadOnly" hide-details class="ds-header-control ds-field-stack"></v-text-field>
                    </v-col>
                    <v-col cols="12" md="4" class="py-1">
                        <div class="ds-field-label">Version</div>
                        <v-text-field v-model="form.version" dense outlined
                            :disabled="identityLocked || isReadOnly" hide-details class="ds-header-control ds-field-stack"></v-text-field>
                        <div class="ds-field-hint">Semver, e.g. 1.0.0</div>
                    </v-col>
                    <v-col cols="12" md="6" class="py-1">
                        <div class="ds-field-label">Idno (optional)</div>
                        <v-text-field v-model="form.idno" dense outlined
                            :disabled="isReadOnly" hide-details class="ds-header-control ds-field-stack"></v-text-field>
                    </v-col>
                    <v-col cols="12" md="6" class="py-1">
                        <div class="ds-field-label">Status</div>
                        <v-select v-model="form.status" :items="statusSelectItems" item-value="value" item-text="text"
                            dense outlined :disabled="isReadOnly && !isCreate" hide-details class="ds-header-control ds-field-stack"></v-select>
                    </v-col>
                    <v-col cols="12" class="py-1">
                        <div class="ds-field-label">Title</div>
                        <v-text-field v-model="form.title" dense outlined
                            :disabled="isReadOnly" hide-details class="ds-header-control ds-field-stack"></v-text-field>
                    </v-col>
                    <v-col cols="12" md="6" class="py-1">
                        <div class="ds-field-label">Description</div>
                        <v-textarea v-model="form.description" dense outlined rows="2" auto-grow
                            :disabled="isReadOnly" hide-details class="ds-header-control ds-field-stack"></v-textarea>
                    </v-col>
                    <v-col cols="12" md="6" class="py-1">
                        <div class="ds-field-label">Notes</div>
                        <v-textarea v-model="form.notes" dense outlined rows="2" auto-grow
                            :disabled="isReadOnly" hide-details class="ds-header-control ds-field-stack"></v-textarea>
                    </v-col>
                </v-row>
            </v-card-text>
            <v-card-actions v-if="!isReadOnly" class="ds-header-card-actions px-3 pb-2 pt-1">
                <v-btn small text :disabled="!isHeaderDirty || saving" @click="cancelHeader">Cancel</v-btn>
                <v-btn small color="primary" :loading="saving" :disabled="!isHeaderDirty" @click="saveStructure">
                    {{ isCreate ? 'Create' : 'Save' }}
                </v-btn>
            </v-card-actions>
        </v-card>
        <data-structure-validation-panel ref="validationPanel" v-if="!isCreate && numericId" :structure-id="numericId" class="mb-3"></data-structure-validation-panel>
        <v-card>
            <v-card-title class="d-flex align-center text-subtitle-2 py-2 flex-wrap" style="gap:8px;">
                <span>Components</span>
                <span v-if="canEditComponents && isComponentsDirty" class="text-caption orange--text text--darken-2">
                    {{ dirtyComponentCount }} unsaved change(s)
                </span>
                <v-spacer></v-spacer>
                <v-btn v-if="canEditComponents" small outlined color="primary" class="mr-1"
                    :disabled="loading || savingComponents || savingActiveComponent"
                    @click="openCsvBootstrap">
                    <v-icon left small>mdi-file-delimited</v-icon> Import components from CSV
                </v-btn>
                <v-btn v-if="canEditComponents && isComponentsDirty" text small
                    :disabled="savingComponents || savingActiveComponent || loading"
                    @click="revertComponents">
                    Discard changes
                </v-btn>
                <v-btn v-if="canEditComponents" small outlined color="primary"
                    :loading="savingComponents" :disabled="!isComponentsDirty || loading || savingActiveComponent"
                    @click="saveComponents">
                    Save all
                </v-btn>
            </v-card-title>
            <v-card-text v-if="isCreate" class="grey--text text-caption py-1 px-3">
                Save the header first, then add components.
            </v-card-text>
            <v-card-text v-else-if="!isCreate && !loading" class="pa-2 pt-0">
                <div class="ds-components-split" style="display:flex; align-items:flex-start; gap:0;">
                    <div class="ds-components-list" :style="{ flex: '0 0 ' + splitListWidth + 'px', width: splitListWidth + 'px' }">
                        <div class="ds-comp-list-search">
                            <v-text-field v-model="componentSearch" dense outlined hide-details clearable
                                placeholder="Search components" prepend-inner-icon="mdi-magnify"
                                class="ds-header-control"></v-text-field>
                        </div>
                        <div v-if="canEditComponents" class="ds-comp-list-actions">
                            <v-checkbox
                                :input-value="allFilteredComponentsSelected"
                                :indeterminate="someFilteredComponentsSelected"
                                hide-details dense class="ma-0 pa-0 flex-shrink-0"
                                @change="toggleSelectAllFilteredComponents"
                                @click.stop></v-checkbox>
                            <v-btn icon x-small color="error" class="ma-0"
                                :disabled="selectedComponentCount === 0"
                                title="Delete selected"
                                @click="confirmDeleteSelectedComponents">
                                <v-icon small>mdi-delete</v-icon>
                            </v-btn>
                            <span v-if="selectedComponentCount" class="ds-comp-list-actions-count grey--text text-caption">
                                {{ selectedComponentCount }} selected
                            </span>
                        </div>
                        <div class="ds-comp-list-scroll">
                            <div v-if="!filteredComponentIndices.length" class="pa-4 text-center grey--text text-caption">
                                No components{{ componentSearch ? ' match your search' : '' }}.
                            </div>
                            <div v-for="idx in filteredComponentIndices" :key="listItemKey(idx)"
                                :class="listRowClass(idx)" @click="selectComponent(idx)">
                                <div class="d-flex align-center" style="gap:6px;">
                                    <v-checkbox v-if="canEditComponents"
                                        :input-value="isComponentRowSelected(idx)"
                                        hide-details dense class="ma-0 pa-0 flex-shrink-0"
                                        @change="toggleComponentRowSelection(idx)"
                                        @click.stop></v-checkbox>
                                    <v-icon v-if="canEditComponents && isRowChanged(componentRows[idx])" x-small color="amber darken-2" title="Unsaved changes">mdi-circle</v-icon>
                                    <span class="grey--text text-caption" style="min-width:20px;">{{ componentRows[idx].sort_order }}</span>
                                    <div style="flex:1; min-width:0;">
                                        <div class="ds-comp-list-name text-truncate">{{ componentRows[idx].name || '(unnamed)' }}</div>
                                        <div v-if="componentRows[idx].label" class="ds-comp-list-label grey--text text-truncate">{{ componentRows[idx].label }}</div>
                                    </div>
                                    <v-chip x-small label class="ma-0 font-weight-medium white--text text-capitalize flex-shrink-0"
                                        :color="columnTypeColor(componentRows[idx].column_type)">
                                        {{ columnTypeLabel(componentRows[idx].column_type) }}
                                    </v-chip>
                                </div>
                            </div>
                        </div>
                        <div v-if="canEditComponents" class="pa-2" style="border-top:1px solid rgba(0,0,0,.12);">
                            <v-btn small color="primary" block @click="addComponentRow">
                                <v-icon left small>mdi-plus</v-icon> Add component
                            </v-btn>
                            <v-btn small outlined color="primary" block class="mt-2"
                                :disabled="loading || savingComponents || savingActiveComponent"
                                @click="openCopyComponentDialog">
                                <v-icon left small>mdi-content-copy</v-icon> Copy from DSD
                            </v-btn>
                        </div>
                    </div>
                    <div class="ds-components-detail flex-grow-1" style="min-height:420px;">
                        <div v-if="!activeComponent" class="pa-8 text-center grey--text">
                            <v-icon size="48" color="grey lighten-1">mdi-table-column</v-icon>
                            <div class="mt-2">Select a component or add a new one</div>
                        </div>
                        <template v-else>
                            <div class="ds-components-detail-header pa-2 d-flex align-center">
                                <strong>{{ activeComponent.name || '(unnamed)' }}</strong>
                                <v-chip v-if="canEditComponents && isActiveComponentDirty" x-small color="amber lighten-4" class="ml-2">Unsaved</v-chip>
                                <v-spacer></v-spacer>
                                <v-btn v-if="canEditComponents" small text class="mr-1"
                                    :disabled="!isActiveComponentDirty || savingActiveComponent || savingComponents"
                                    @click="cancelActiveComponent">
                                    Cancel
                                </v-btn>
                                <v-btn v-if="canEditComponents" small color="primary" class="mr-2"
                                    :loading="savingActiveComponent" :disabled="!isActiveComponentDirty || savingComponents"
                                    @click="saveActiveComponent">
                                    Save
                                </v-btn>
                                <v-btn icon x-small :disabled="filteredComponentIndices.indexOf(selectedComponentIndex) <= 0" @click="navigateComponent('prev')"><v-icon small>mdi-chevron-left</v-icon></v-btn>
                                <v-btn icon x-small :disabled="filteredComponentIndices.indexOf(selectedComponentIndex) >= filteredComponentIndices.length - 1" @click="navigateComponent('next')"><v-icon small>mdi-chevron-right</v-icon></v-btn>
                                <v-btn v-if="canEditComponents" icon x-small color="error" class="ml-1" @click="confirmRemoveActiveComponent"><v-icon small>mdi-delete</v-icon></v-btn>
                            </div>
                            <div class="pa-3">
                                <v-row dense>
                                    <v-col cols="12" sm="4">
                                        <div class="ds-field-label">Sort order</div>
                                        <v-text-field v-model="activeComponent.sort_order" type="number"
                                            dense outlined hide-details :disabled="!canEditComponents"
                                            class="ds-compact-control ds-field-stack"></v-text-field>
                                    </v-col>
                                    <v-col cols="12" sm="8">
                                        <div class="ds-field-label">Name</div>
                                        <v-text-field v-model="activeComponent.name" dense outlined
                                            hide-details :disabled="!canEditComponents"
                                            class="ds-compact-control ds-field-stack"></v-text-field>
                                    </v-col>
                                    <v-col cols="12">
                                        <div class="ds-field-label">Label</div>
                                        <v-text-field v-model="activeComponent.label" dense outlined
                                            hide-details :disabled="!canEditComponents"
                                            class="ds-compact-control ds-field-stack"></v-text-field>
                                    </v-col>
                                    <v-col cols="12" sm="6">
                                        <div class="ds-field-label">Column type</div>
                                        <v-select v-model="activeComponent.column_type" :items="columnTypeItems"
                                            item-value="value" item-text="text" dense outlined
                                            hide-details :disabled="!canEditComponents"
                                            class="ds-compact-control ds-field-stack"></v-select>
                                    </v-col>
                                    <v-col cols="12" sm="6">
                                        <div class="ds-field-label">Data type</div>
                                        <v-select v-model="activeComponent.data_type" :items="dataTypeItems"
                                            item-value="value" item-text="text" dense outlined
                                            hide-details :disabled="!canEditComponents"
                                            class="ds-compact-control ds-field-stack"></v-select>
                                    </v-col>
                                    <v-col cols="12">
                                        <div class="ds-field-label">Codelist</div>
                                        <v-autocomplete :value="activeCodelistId" :items="codelistAutocompleteItems"
                                            item-value="id" :item-text="codelistItemText" dense outlined
                                            hide-details clearable cache-items :disabled="!canEditComponents"
                                            :loading="codelistSearchLoading && codelistSearchQuery.trim().length >= 2"
                                            no-filter class="ds-compact-control ds-field-stack"
                                            @input="onCodelistIdInput"
                                            @click:clear="onCodelistAutocompleteClear"
                                            @focus="onCodelistAutocompleteFocus"
                                            @update:search-input="onCodelistSearchInput"></v-autocomplete>
                                        <div v-if="activeCodelistId" class="ds-comp-codelist-preview mt-3">
                                            <div class="ds-comp-codelist-preview-header d-flex align-center flex-wrap mb-2" style="gap:8px;">
                                                <div class="text-caption font-weight-medium">
                                                    {{ activeCodelistPreviewTitle || 'Codelist items' }}
                                                    <span v-if="activeCodelistCodesTotal" class="grey--text font-weight-regular">({{ activeCodelistCodesTotal }} items)</span>
                                                </div>
                                                <v-spacer></v-spacer>
                                                <v-text-field
                                                    v-model="activeCodelistCodesSearchInput"
                                                    dense outlined hide-details clearable
                                                    prepend-inner-icon="mdi-magnify"
                                                    append-icon="mdi-arrow-right"
                                                    placeholder="Search codes"
                                                    class="ds-comp-codelist-preview-search"
                                                    @keyup.enter="onActiveCodelistCodesSearchSubmit"
                                                    @click:append="onActiveCodelistCodesSearchSubmit"
                                                    @click:clear="onActiveCodelistCodesSearchClear"
                                                ></v-text-field>
                                            </div>
                                            <v-progress-linear v-if="activeCodelistCodesLoading" indeterminate height="2" class="mb-1"></v-progress-linear>
                                            <div v-if="activeCodelistCodeRows.length || activeCodelistCodesLoading"
                                                class="elevation-1 ds-comp-codelist-preview-table">
                                                <table class="ds-comp-codelist-preview-table-grid">
                                                    <thead>
                                                        <tr>
                                                            <th>Code</th>
                                                            <th>Label</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr v-for="row in activeCodelistCodeRows" :key="row.id">
                                                            <td><code>{{ row.code }}</code></td>
                                                            <td>{{ row.label }}</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div v-else-if="!activeCodelistCodesLoading" class="text-caption grey--text pa-3 text-center">
                                                {{ activeCodelistCodesSearch ? 'No codes match your search.' : 'No items in this codelist.' }}
                                            </div>
                                            <div v-if="activeCodelistCodesTotal > activeCodelistCodesPerPage || activeCodelistCodesPage > 1"
                                                class="ds-comp-codelist-preview-pager d-flex align-center justify-end pa-2">
                                                <span class="text-caption grey--text mr-2">
                                                    {{ activeCodelistCodesOffset + 1 }}–{{ Math.min(activeCodelistCodesOffset + activeCodelistCodesPerPage, activeCodelistCodesTotal) }}
                                                    of {{ activeCodelistCodesTotal }}
                                                </span>
                                                <v-btn icon x-small :disabled="activeCodelistCodesPage <= 1 || activeCodelistCodesLoading"
                                                    @click="prevActiveCodelistCodesPage">
                                                    <v-icon small>mdi-chevron-left</v-icon>
                                                </v-btn>
                                                <v-btn icon x-small
                                                    :disabled="activeCodelistCodesOffset + activeCodelistCodesPerPage >= activeCodelistCodesTotal || activeCodelistCodesLoading"
                                                    @click="nextActiveCodelistCodesPage">
                                                    <v-icon small>mdi-chevron-right</v-icon>
                                                </v-btn>
                                            </div>
                                        </div>
                                    </v-col>
                                    <v-col cols="12">
                                        <div class="ds-field-label">Description</div>
                                        <v-textarea v-model="activeComponent.description" dense outlined
                                            rows="3" auto-grow hide-details :disabled="!canEditComponents"
                                            class="ds-compact-control ds-field-stack"></v-textarea>
                                    </v-col>
                                </v-row>
                            </div>
                        </template>
                    </div>
                </div>
            </v-card-text>
        </v-card>

        <v-dialog v-model="copyComponentDialog" max-width="920" persistent content-class="ds-copy-component-dialog">
            <v-card class="ds-copy-component-card">
                <v-card-title class="text-subtitle-1 py-3 flex-shrink-0">
                    Copy from DSD
                    <v-spacer></v-spacer>
                    <v-btn icon small @click="closeCopyComponentDialog"><v-icon>mdi-close</v-icon></v-btn>
                </v-card-title>
                <v-divider></v-divider>
                <div class="ds-copy-component-body">
                    <div class="ds-copy-component-search mb-4">
                        <v-text-field
                            v-model="copyComponentSearch"
                            dense outlined hide-details clearable
                            prepend-inner-icon="mdi-magnify"
                            append-icon="mdi-arrow-right"
                            placeholder="Search by component name, label, or DSD…"
                            @keyup.enter="loadCopyComponentPickerList()"
                            @click:append="loadCopyComponentPickerList()"
                            @click:clear="onCopyComponentSearchInput()"
                            @input="onCopyComponentSearchInput"
                        ></v-text-field>
                    </div>
                    <div class="ds-copy-component-table-area">
                        <div class="ds-copy-component-count text-caption grey--text">
                            <span v-if="copyComponentLoading">Loading…</span>
                            <span v-else-if="copyComponentCanSearch">
                                Showing {{ copyComponentList.length }}<span v-if="copyComponentTotal > copyComponentList.length"> of {{ copyComponentTotal }}</span> component(s)
                                <span v-if="copyComponentSelectedCount"> · {{ copyComponentSelectedCount }} selected</span>
                            </span>
                        </div>
                        <div class="ds-copy-component-table-wrap elevation-1">
                            <v-data-table
                                :headers="copyComponentPickerHeaders"
                                :items="copyComponentList"
                                :items-per-page="-1"
                                item-key="id"
                                dense
                                hide-default-footer
                                disable-sort
                                mobile-breakpoint="0"
                                class="ds-copy-component-pick-table"
                            >
                                <template v-slot:item.select="{ item }">
                                    <v-checkbox
                                        :input-value="isCopyComponentSelected(item)"
                                        hide-details dense class="ma-0 pa-0"
                                        @change="toggleCopyComponentSelection(item)"
                                        @click.stop
                                    ></v-checkbox>
                                </template>
                                <template v-slot:item.name="{ item }">
                                    <span class="font-weight-medium">{{ item.name }}</span>
                                </template>
                                <template v-slot:item.label="{ item }">
                                    <span class="grey--text text--darken-1">{{ item.label || '—' }}</span>
                                </template>
                                <template v-slot:item.column_type="{ item }">
                                    <v-chip x-small label class="ma-0 font-weight-medium white--text text-capitalize"
                                        :color="columnTypeColor(item.column_type)">
                                        {{ columnTypeLabel(item.column_type) }}
                                    </v-chip>
                                </template>
                                <template v-slot:item.data_structure="{ item }">
                                    <span class="text-caption">{{ copyComponentStructureLabel(item) }}</span>
                                </template>
                                <template v-slot:item.codelist="{ item }">
                                    <span class="text-caption">{{ copyComponentCodelistLabel(item) }}</span>
                                </template>
                                <template v-slot:no-data>
                                    <div class="pa-4 text-center grey--text text-caption">
                                        {{ copyComponentCanSearch ? 'No components match your search.' : 'Search to browse components from other DSDs.' }}
                                    </div>
                                </template>
                            </v-data-table>
                        </div>
                        <div v-if="copyComponentCanSearch && (copyComponentTotal > copyComponentPerPage || copyComponentPage > 1)"
                            class="ds-copy-component-pager d-flex align-center justify-end pa-2">
                            <span class="text-caption grey--text mr-2">
                                Page {{ copyComponentPage }} of {{ copyComponentPageCount }}
                            </span>
                            <v-btn icon x-small :disabled="copyComponentPage <= 1 || copyComponentLoading"
                                @click="prevCopyComponentPage">
                                <v-icon small>mdi-chevron-left</v-icon>
                            </v-btn>
                            <v-btn icon x-small
                                :disabled="copyComponentPage >= copyComponentPageCount || copyComponentLoading"
                                @click="nextCopyComponentPage">
                                <v-icon small>mdi-chevron-right</v-icon>
                            </v-btn>
                        </div>
                    </div>
                </div>
                <v-divider></v-divider>
                <v-card-actions class="px-4 py-3">
                    <v-spacer></v-spacer>
                    <v-btn text small @click="closeCopyComponentDialog">Cancel</v-btn>
                    <v-btn color="primary" depressed small
                        :disabled="!copyComponentSelectedCount"
                        @click="applyCopyComponents">
                        Copy selected
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        </div>
    `
});
