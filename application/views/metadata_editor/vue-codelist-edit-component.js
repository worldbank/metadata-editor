// Create / edit codelist, header translations (codelist_labels), and items with labels.
// Item label languages are limited to languages configured in the codelist translations block.
// All deletes use POST (delete, codes_delete, labels_delete, translation_delete) — not HTTP DELETE.
Vue.component('codelist-edit', {
    props: {
        id: { type: [String, Number], default: null }
    },
    data: function () {
        return {
            loading: false,
            saving: false,
            form: {
                idno: '',
                agency: '',
                name: '',
                version: '',
                title: '',
                description: '',
                uri: '',
                status: 'active'
            },
            codelistStatus: 'active',
            codesLoading: false,
            savingItems: false,
            deletingItems: false,
            itemRows: [],
            itemsTotal: 0,
            itemsPage: 1,
            itemsPerPage: 50,
            itemsSearchInput: '',
            itemsSearch: '',
            itemRowsSnapshot: [],
            itemCodeIdToCode: {},
            itemCodeToId: {},
            selectedItemIds: [],
            csvImportDialog: false,
            csvImportFile: null,
            csvImportDryRun: false,
            importingCsv: false,
            itemsErrorMessage: '',
            translationRows: [],
            pendingTranslationDeletes: [],
            savingTranslations: false,
            translationsLoading: false
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
        isoMap: function () {
            return typeof ISO_LANGUAGES !== 'undefined' && ISO_LANGUAGES ? ISO_LANGUAGES : {};
        },
        isoSelectItems: function () {
            var m = this.isoMap;
            return Object.keys(m).sort(function (a, b) {
                var na = ((m[a] && m[a].name) || a).toLowerCase();
                var nb = ((m[b] && m[b].name) || b).toLowerCase();
                return na.localeCompare(nb);
            }).map(function (code) {
                var meta = m[code] || {};
                return { value: code, text: (meta.name || code) + ' (' + code + ')' };
            });
        },
        /** Languages enabled for this codelist (from translation rows with a selected code). */
        enabledLangCodes: function () {
            var codes = [];
            var seen = {};
            (this.translationRows || []).forEach(function (r) {
                var l = (r.language || '').trim();
                if (l && !seen[l]) {
                    seen[l] = true;
                    codes.push(l);
                }
            });
            codes.sort();
            return codes;
        },
        itemLanguageSelectItems: function () {
            var m = this.isoMap;
            return this.enabledLangCodes.map(function (code) {
                var meta = m[code] || {};
                return { value: code, text: (meta.name || code) + ' (' + code + ')' };
            });
        },
        itemsOffset: function () {
            return (this.itemsPage - 1) * this.itemsPerPage;
        },
        multiLanguage: function () {
            return this.enabledLangCodes.length > 1;
        },
        isItemsDirty: function () {
            var vm = this;
            return vm.itemRows.some(function (row) {
                return vm.isRowDirty(row);
            });
        },
        dirtyItemsCount: function () {
            var vm = this;
            return vm.itemRows.filter(function (row) {
                return vm.isRowDirty(row);
            }).length;
        },
        newItemsCount: function () {
            return this.itemRows.filter(function (row) {
                return row && !row.id;
            }).length;
        },
        modifiedItemsCount: function () {
            var vm = this;
            return vm.itemRows.filter(function (row) {
                return row && row.id && vm.isRowDirty(row);
            }).length;
        },
        itemsChangeSummary: function () {
            var parts = [];
            if (this.newItemsCount > 0) {
                parts.push(this.newItemsCount + ' new');
            }
            if (this.modifiedItemsCount > 0) {
                parts.push(this.modifiedItemsCount + ' modified');
            }
            return parts.join(' · ');
        },
        allItemsSelected: function () {
            var vm = this;
            var selectable = vm.itemRows.filter(function (r) {
                return r.id != null;
            });
            if (!selectable.length) {
                return false;
            }
            return selectable.every(function (r) {
                return vm.selectedItemIds.indexOf(r.id) !== -1;
            });
        },
        someItemsSelected: function () {
            return this.selectedItemIds.length > 0;
        },
        isPageDirty: function () {
            return this.isItemsDirty;
        },
        isAdmin: function () {
            return CI && CI.user_info && CI.user_info.is_admin === true;
        },
        isReadOnly: function () {
            if (this.isCreate) {
                return false;
            }
            var s = (this.codelistStatus || 'active').toLowerCase();
            return s === 'locked' || s === 'archived';
        },
        statusSelectItems: function () {
            return [
                { value: 'draft', text: this.$t('codelist_status_draft') || 'Draft' },
                { value: 'active', text: this.$t('codelist_status_active') || 'Active' },
                { value: 'locked', text: this.$t('codelist_status_locked') || 'Locked' },
                { value: 'archived', text: this.$t('codelist_status_archived') || 'Archived' }
            ];
        }
    },
    watch: {
        id: function () {
            this.bootstrap();
        },
        '$route.params.id': function () {
            this.bootstrap();
        }
    },
    mounted: function () {
        this.bootstrap();
    },
    methods: {
        apiBase: function () {
            var base = (typeof CI !== 'undefined' && CI.site_url) ? CI.site_url.replace(/\/$/, '') : '';
            return base + '/api/codelists';
        },
        notifySuccess: function (msg) {
            if (typeof EventBus !== 'undefined') {
                EventBus.$emit('onSuccess', msg);
            }
        },
        notifyFail: function (err) {
            var m = this.errorMessageFrom(err);
            if (typeof EventBus !== 'undefined') {
                EventBus.$emit('onFail', m);
            } else {
                alert(m);
            }
        },
        errorMessageFrom: function (err) {
            if (typeof err === 'string') {
                return err;
            }
            if (err && err.response && err.response.data) {
                var d = err.response.data;
                if (d.message) {
                    return d.message;
                }
                if (d.errors && d.errors.length) {
                    return d.errors.join('\n');
                }
            }
            return 'Request failed';
        },
        setItemsError: function (err) {
            this.itemsErrorMessage = this.errorMessageFrom(err);
        },
        clearItemsError: function () {
            this.itemsErrorMessage = '';
        },
        onItemsErrorDismiss: function (visible) {
            if (!visible) {
                this.clearItemsError();
            }
        },
        bootstrap: function () {
            var vm = this;
            this.pendingTranslationDeletes = [];
            this.itemRowsSnapshot = [];
            this.selectedItemIds = [];
            this.itemCodeIdToCode = {};
            this.itemCodeToId = {};
            this._codeLookupLoaded = false;
            this.itemsPage = 1;
            this.itemsSearch = '';
            this.itemsSearchInput = '';
            this.itemsTotal = 0;
            this.itemsErrorMessage = '';
            if (this.isCreate) {
                this.form = { idno: '', agency: '', name: '', version: '', title: '', description: '', uri: '', status: 'active' };
                this.codelistStatus = 'active';
                this.itemRows = [];
                this.translationRows = [];
                return;
            }
            this.loadCodelist();
            this.loadCodelistTranslations().then(function () {
                vm.loadCodes();
            });
        },
        emptyLabelRow: function () {
            var codes = this.enabledLangCodes;
            var first = codes.length ? codes[0] : '';
            return { labelId: null, language: first, label: '', description: '' };
        },
        resolveParentCode: function (parentId) {
            var pid = parseInt(parentId, 10);
            if (isNaN(pid) || pid <= 0) {
                return '';
            }
            return this.itemCodeIdToCode[pid] || '';
        },
        ensureItemCodeLookup: function () {
            var vm = this;
            if (!vm.numericId) {
                return Promise.resolve();
            }
            if (vm._codeLookupLoaded) {
                return Promise.resolve();
            }
            return axios.get(vm.apiBase() + '/codes/' + vm.numericId + '?limit=0&compact=1')
                .then(function (res) {
                    var map = {};
                    var reverse = {};
                    (res.data && res.data.codes ? res.data.codes : []).forEach(function (c) {
                        if (c.id != null && c.code) {
                            map[c.id] = c.code;
                            reverse[c.code] = c.id;
                        }
                    });
                    vm.itemCodeIdToCode = map;
                    vm.itemCodeToId = reverse;
                    vm._codeLookupLoaded = true;
                })
                .catch(function () {
                    vm.itemCodeIdToCode = {};
                    vm.itemCodeToId = {};
                });
        },
        snapshotItems: function (rows) {
            var vm = this;
            (rows || []).forEach(function (row) {
                row._snapshot = vm.normalizeItemForCompare(row);
            });
            return (rows || []).map(function (row) {
                return row._snapshot;
            });
        },
        normalizeItemForCompare: function (row) {
            var labels = (row.labelRows || []).map(function (lr) {
                return {
                    labelId: lr.labelId || null,
                    language: (lr.language || '').trim(),
                    label: (lr.label || '').trim(),
                    description: (lr.description || '').trim()
                };
            });
            labels.sort(function (a, b) {
                return a.language.localeCompare(b.language);
            });
            return JSON.stringify({
                id: row.id || null,
                code: (row.code || '').trim(),
                sort_order: (row.sort_order != null && row.sort_order !== '') ? String(row.sort_order) : '',
                parent_code: (row.parent_code || '').trim(),
                labels: labels,
                deletedLabelIds: (row._deletedLabelIds || []).slice().sort()
            });
        },
        isRowDirty: function (row) {
            if (!row || !row.id) {
                return true;
            }
            if (!row._snapshot) {
                return true;
            }
            return this.normalizeItemForCompare(row) !== row._snapshot;
        },
        isRowNew: function (row) {
            return !!(row && !row.id);
        },
        isRowModified: function (row) {
            return !!(row && row.id && this.isRowDirty(row));
        },
        itemChangeLabel: function (row) {
            if (this.isRowNew(row)) {
                return 'New';
            }
            if (this.isRowModified(row)) {
                return 'Modified';
            }
            return '';
        },
        itemChangeColor: function (row) {
            if (this.isRowNew(row)) {
                return 'green';
            }
            if (this.isRowModified(row)) {
                return 'orange darken-2';
            }
            return '';
        },
        getDirtyRows: function () {
            var vm = this;
            return vm.itemRows.filter(function (row) {
                return vm.isRowDirty(row);
            });
        },
        mapApiCodesToRows: function (codes) {
            var self = this;
            return (codes || []).map(function (c) {
                var labels = c.labels || [];
                var labelRows = labels.length
                    ? labels.map(function (l) {
                        return {
                            labelId: l.id,
                            language: l.language || '',
                            label: l.label || '',
                            description: l.description || ''
                        };
                    })
                    : [self.emptyLabelRow()];
                return {
                    id: c.id,
                    code: c.code || '',
                    sort_order: c.sort_order != null && c.sort_order !== '' ? String(c.sort_order) : '',
                    parent_code: self.resolveParentCode(c.parent_id),
                    labelRows: labelRows,
                    _deletedLabelIds: []
                };
            });
        },
        rowKey: function (row, index) {
            return row.id != null ? 'i' + row.id : 'n' + index;
        },
        /** Label sub-rows (min 1) + optional “+ Language” row. */
        itemLabelBodyRowCount: function (row) {
            var n = (row.labelRows && row.labelRows.length) ? row.labelRows.length : 1;
            var extra = this.multiLanguage ? 1 : 0;
            return Math.max(1, n) + extra;
        },
        itemLabelRowsSliceFrom: function (row, start) {
            return (row.labelRows || []).slice(start);
        },
        loadCodelist: function () {
            var vm = this;
            if (!vm.numericId) return;
            vm.loading = true;
            axios.get(vm.apiBase() + '/single/' + vm.numericId)
                .then(function (res) {
                    vm.loading = false;
                    if (res.data && res.data.status === 'success' && res.data.codelist) {
                        var c = res.data.codelist;
                        vm.codelistStatus = (c.status || 'active').toLowerCase();
                        vm.form = {
                            idno: c.idno || '',
                            agency: c.agency || '',
                            name: c.name || '',
                            version: c.version || '',
                            title: c.title || '',
                            description: c.description || '',
                            uri: c.uri || '',
                            status: vm.codelistStatus
                        };
                    }
                })
                .catch(function (err) {
                    vm.loading = false;
                    vm.notifyFail(err);
                });
        },
        loadCodelistTranslations: function () {
            var vm = this;
            return new Promise(function (resolve) {
                if (!vm.numericId) {
                    resolve();
                    return;
                }
                vm.translationsLoading = true;
                axios.get(vm.apiBase() + '/codelist_translations/' + vm.numericId)
                    .then(function (res) {
                        vm.translationsLoading = false;
                        if (res.data && res.data.status === 'success') {
                            vm.translationRows = (res.data.translations || []).map(function (t) {
                                return {
                                    id: t.id,
                                    language: t.language || '',
                                    label: t.label || '',
                                    description: t.description || ''
                                };
                            });
                        } else {
                            vm.translationRows = [];
                        }
                        resolve();
                    })
                    .catch(function (err) {
                        vm.translationsLoading = false;
                        vm.notifyFail(err);
                        resolve();
                    });
            });
        },
        loadCodes: function () {
            var vm = this;
            if (!vm.numericId) return;
            vm.codesLoading = true;
            vm.selectedItemIds = [];
            var params = [
                'offset=' + vm.itemsOffset,
                'limit=' + vm.itemsPerPage
            ];
            if (vm.itemsSearch) {
                params.push('search=' + encodeURIComponent(vm.itemsSearch));
            }
            vm.ensureItemCodeLookup().then(function () {
                return axios.get(vm.apiBase() + '/codes/' + vm.numericId + '?' + params.join('&'));
            })
                .then(function (res) {
                    vm.codesLoading = false;
                    if (res.data && res.data.status === 'success') {
                        vm.itemRows = vm.mapApiCodesToRows(res.data.codes || []);
                        vm.itemRowsSnapshot = vm.snapshotItems(vm.itemRows);
                        vm.itemsTotal = res.data.total != null ? res.data.total : vm.itemRows.length;
                        vm.clearItemsError();
                    } else {
                        vm.itemRows = [];
                        vm.itemRowsSnapshot = [];
                        vm.itemsTotal = 0;
                    }
                })
                .catch(function (err) {
                    vm.codesLoading = false;
                    vm.setItemsError(err);
                });
        },
        checkDirtyBeforeNavigate: function () {
            if (this.isItemsDirty) {
                return confirm('You have unsaved item changes on this page. They will be lost if you continue. Continue anyway?');
            }
            return true;
        },
        checkDirtyBeforeCsvImport: function () {
            if (this.isItemsDirty) {
                return confirm('You have unsaved item changes. Importing CSV will replace all saved items and discard unsaved grid edits. Continue?');
            }
            return true;
        },
        prevItemsPage: function () {
            if (this.itemsPage <= 1) return;
            if (!this.checkDirtyBeforeNavigate()) return;
            this.itemsPage--;
            this.loadCodes();
        },
        nextItemsPage: function () {
            if (this.itemsOffset + this.itemsPerPage >= this.itemsTotal) return;
            if (!this.checkDirtyBeforeNavigate()) return;
            this.itemsPage++;
            this.loadCodes();
        },
        onItemsSearchInput: function () {
            var vm = this;
            if (vm._itemSearchTimer) clearTimeout(vm._itemSearchTimer);
            vm._itemSearchTimer = setTimeout(function () {
                if (!vm.checkDirtyBeforeNavigate()) {
                    vm.itemsSearchInput = vm.itemsSearch; // revert input
                    return;
                }
                vm.itemsSearch = (vm.itemsSearchInput || '').trim();
                vm.itemsPage = 1;
                vm.loadCodes();
            }, 400);
        },
        onItemsSearchSubmit: function () {
            if (this._itemSearchTimer) clearTimeout(this._itemSearchTimer);
            if (!this.checkDirtyBeforeNavigate()) return;
            this.itemsSearch = (this.itemsSearchInput || '').trim();
            this.itemsPage = 1;
            this.loadCodes();
        },
        onItemsSearchClear: function () {
            if (this._itemSearchTimer) clearTimeout(this._itemSearchTimer);
            if (!this.checkDirtyBeforeNavigate()) {
                this.itemsSearchInput = this.itemsSearch; // revert input
                return;
            }
            this.itemsSearchInput = '';
            this.itemsSearch = '';
            this.itemsPage = 1;
            this.loadCodes();
        },
        saveMeta: function () {
            var vm = this;
            var payload;
            if (vm.isReadOnly) {
                if (!vm.isAdmin) {
                    return;
                }
                payload = { status: vm.form.status || 'active' };
            } else {
                payload = {
                    title: vm.form.title,
                    description: vm.form.description,
                    uri: vm.form.uri || null
                };
                if (vm.isAdmin && vm.form.status) {
                    payload.status = vm.form.status;
                }
            }
            vm.saving = true;
            if (vm.isCreate) {
                payload.agency = vm.form.agency;
                payload.name = vm.form.name;
                payload.version = vm.form.version;
                payload.title = vm.form.title;
                if ((vm.form.idno || '').trim()) {
                    payload.idno = vm.form.idno.trim();
                }
                axios.post(vm.apiBase(), payload)
                    .then(function (res) {
                        vm.saving = false;
                        if (res.data && res.data.id) {
                            vm.notifySuccess('Codelist created');
                            vm.$router.replace('/edit/' + res.data.id);
                        }
                    })
                    .catch(function (err) {
                        vm.saving = false;
                        vm.notifyFail(err);
                    });
            } else {
                axios.post(vm.apiBase() + '/update/' + vm.numericId, payload)
                    .then(function () {
                        vm.saving = false;
                        vm.codelistStatus = (vm.form.status || 'active').toLowerCase();
                        vm.notifySuccess('Saved');
                    })
                    .catch(function (err) {
                        vm.saving = false;
                        vm.notifyFail(err);
                    });
            }
        },
        cancel: function () {
            this.$router.push('/');
        },
        addTranslationRow: function () {
            this.translationRows.push({ id: null, language: '', label: '', description: '' });
        },
        confirmRemoveTranslationRow: function (index) {
            var row = this.translationRows[index];
            if (!row) return;
            if (this.translationRows.length === 1 && row.id) {
                this.notifyFail({
                    response: {
                        data: {
                            message:
                                'Cannot remove the last codelist language. Add another language first, then remove this one.'
                        }
                    }
                });
                return;
            }
            if (!confirm('Remove this language row? Unsaved changes are lost; saved rows are deleted when you save translations.')) {
                return;
            }
            if (row.id) {
                this.pendingTranslationDeletes.push(row.id);
            }
            this.translationRows.splice(index, 1);
        },
        validateTranslationRows: function () {
            var vm = this;
            var seen = {};
            var completeCount = 0;
            for (var i = 0; i < this.translationRows.length; i++) {
                var r = this.translationRows[i];
                var lang = (r.language || '').trim();
                var lab = (r.label || '').trim();
                if (!lang && !lab && !r.description) {
                    continue;
                }
                if (!lang) {
                    return 'Select a language for each non-empty translation row (row ' + (i + 1) + ').';
                }
                if (!Object.prototype.hasOwnProperty.call(vm.isoMap, lang)) {
                    return 'Invalid language code: ' + lang;
                }
                if (!lab) {
                    return 'Label is required for language ' + lang + '.';
                }
                if (seen[lang]) {
                    return 'Duplicate language: ' + lang + '.';
                }
                seen[lang] = true;
                completeCount++;
            }
            if (completeCount < 1) {
                return 'At least one codelist language with a label is required (new lists start with English from the codelist name).';
            }
            return null;
        },
        saveTranslations: function () {
            var vm = this;
            if (!vm.numericId) return;
            var err = vm.validateTranslationRows();
            if (err) {
                vm.notifyFail({ response: { data: { message: err } } });
                return;
            }
            vm.savingTranslations = true;
            var p = Promise.resolve();

            vm.pendingTranslationDeletes.forEach(function (tid) {
                p = p.then(function () {
                    return axios.post(vm.apiBase() + '/translation_delete/' + tid);
                });
            });
            vm.pendingTranslationDeletes = [];

            vm.translationRows.forEach(function (row) {
                var lang = (row.language || '').trim();
                var lab = (row.label || '').trim();
                if (!lang || !lab) {
                    return;
                }
                p = p.then(function () {
                    return axios
                        .post(vm.apiBase() + '/codelist_translations/' + vm.numericId, {
                            language: lang,
                            label: lab,
                            description: (row.description && String(row.description).trim()) || null
                        })
                        .then(function (res) {
                            if (res.data && res.data.id && !row.id) {
                                row.id = res.data.id;
                            }
                        });
                });
            });

            p.then(function () {
                vm.savingTranslations = false;
                vm.notifySuccess('Translations saved');
                return vm.loadCodelistTranslations();
            })
                .then(function () {
                    vm.loadCodes();
                })
                .catch(function (e) {
                    vm.savingTranslations = false;
                    vm.notifyFail(e);
                });
        },
        revertTranslations: function () {
            var vm = this;
            if (vm.pendingTranslationDeletes.length || vm.translationRows.some(function (r) { return !r.id; })) {
                if (!confirm('Discard unsaved translation changes?')) return;
            }
            vm.pendingTranslationDeletes = [];
            vm.loadCodelistTranslations().then(function () {
                vm.loadCodes();
            });
        },
        toggleSelectAllItems: function () {
            var vm = this;
            if (vm.allItemsSelected) {
                vm.selectedItemIds = [];
                return;
            }
            vm.selectedItemIds = vm.itemRows.filter(function (r) {
                return r.id != null;
            }).map(function (r) {
                return r.id;
            });
        },
        toggleItemSelected: function (row) {
            if (!row || row.id == null) {
                return;
            }
            var idx = this.selectedItemIds.indexOf(row.id);
            if (idx === -1) {
                this.selectedItemIds.push(row.id);
            } else {
                this.selectedItemIds.splice(idx, 1);
            }
        },
        isItemSelected: function (row) {
            return row && row.id != null && this.selectedItemIds.indexOf(row.id) !== -1;
        },
        batchDeleteSelectedItems: function () {
            var vm = this;
            if (!vm.selectedItemIds.length || vm.deletingItems) {
                return;
            }
            var ids = vm.selectedItemIds.slice();
            if (!confirm('Permanently delete ' + ids.length + ' selected item(s)?')) {
                return;
            }
            vm.deleteItemsByIds(ids);
        },
        addItemRow: function () {
            this.itemRows.push({
                id: null,
                code: '',
                sort_order: '',
                parent_code: '',
                labelRows: [this.emptyLabelRow()],
                _deletedLabelIds: []
            });
        },
        addLabelSlot: function (row) {
            if (!this.enabledLangCodes.length) {
                this.setItemsError('Add languages in Codelist translations first.');
                return;
            }
            row.labelRows.push({ labelId: null, language: '', label: '', description: '' });
        },
        removeLabelSlot: function (row, idx) {
            var lr = row.labelRows[idx];
            if (lr.labelId) {
                if (!row._deletedLabelIds) row._deletedLabelIds = [];
                row._deletedLabelIds.push(lr.labelId);
            }
            row.labelRows.splice(idx, 1);
            if (row.labelRows.length === 0) {
                row.labelRows.push(this.emptyLabelRow());
            }
        },
        confirmRemoveItemRow: function (row) {
            var vm = this;
            var index = vm.itemRows.indexOf(row);
            if (index === -1 || vm.deletingItems) {
                return;
            }
            var msg = row.id
                ? ('Permanently delete item "' + (row.code || row.id) + '"?')
                : 'Remove this unsaved row?';
            if (!confirm(msg)) {
                return;
            }
            if (!row.id) {
                vm.itemRows.splice(index, 1);
                return;
            }
            vm.deleteItemsByIds([row.id]);
        },
        deleteItemsByIds: function (ids) {
            var vm = this;
            if (!ids.length) {
                return Promise.resolve();
            }
            vm.deletingItems = true;
            vm.clearItemsError();
            var p = Promise.resolve();
            ids.forEach(function (id) {
                p = p.then(function () {
                    return axios.post(vm.apiBase() + '/codes_delete/' + id);
                });
            });
            return p.then(function () {
                var idSet = {};
                ids.forEach(function (id) {
                    idSet[id] = true;
                });
                vm.itemRows = vm.itemRows.filter(function (row) {
                    return !row.id || !idSet[row.id];
                });
                ids.forEach(function (id) {
                    var code = vm.itemCodeIdToCode[id];
                    delete vm.itemCodeIdToCode[id];
                    if (code) {
                        delete vm.itemCodeToId[code];
                    }
                });
                vm.itemsTotal = Math.max(0, vm.itemsTotal - ids.length);
                vm.selectedItemIds = vm.selectedItemIds.filter(function (id) {
                    return !idSet[id];
                });
                vm._codeLookupLoaded = false;
                vm.deletingItems = false;
                vm.notifySuccess(ids.length === 1 ? 'Item deleted' : ids.length + ' items deleted');
                if (vm.itemRows.length === 0 && vm.itemsTotal > 0) {
                    var maxPage = Math.max(1, Math.ceil(vm.itemsTotal / vm.itemsPerPage));
                    if (vm.itemsPage > maxPage) {
                        vm.itemsPage = maxPage;
                    }
                    return vm.loadCodes();
                }
            }).catch(function (err) {
                vm.deletingItems = false;
                vm.setItemsError(err);
            });
        },
        revertItems: function () {
            if (!this.isCreate && this.isItemsDirty) {
                if (!confirm('Discard unsaved item changes on this page?')) return;
            }
            this.selectedItemIds = [];
            this._codeLookupLoaded = false;
            this.clearItemsError();
            this.loadCodes();
        },
        validateItemRows: function (rows) {
            var vm = this;
            var enabled = vm.enabledLangCodes;
            var seenCodes = {};
            var list = rows || this.itemRows;
            for (var i = 0; i < list.length; i++) {
                var row = list[i];
                var code = (row.code || '').trim();
                if (!code) {
                    return 'Each item must have a code (row ' + (i + 1) + ').';
                }
                if (seenCodes[code]) {
                    return 'Duplicate code "' + code + '".';
                }
                seenCodes[code] = true;

                var langsSeen = {};
                var lrList = row.labelRows || [];
                for (var j = 0; j < lrList.length; j++) {
                    var lr = lrList[j];
                    var lang = (lr.language || '').trim();
                    var lab = (lr.label || '').trim();
                    if (!lang && !lab) continue;
                    if (!lang || !lab) {
                        return 'Item "' + code + '": each translation needs both language and label.';
                    }
                    if (enabled.indexOf(lang) === -1) {
                        return 'Item "' + code + '": language "' + lang + '" is not enabled for this codelist.';
                    }
                    if (langsSeen[lang]) {
                        return 'Item "' + code + '": duplicate language "' + lang + '".';
                    }
                    langsSeen[lang] = true;
                }
            }
            return null;
        },
        saveAllItems: function () {
            var vm = this;
            if (!vm.numericId) return;
            var dirtyRows = vm.getDirtyRows();
            if (!dirtyRows.length) {
                vm.notifySuccess('No item changes to save');
                return;
            }
            var err = vm.validateItemRows(dirtyRows);
            if (err) {
                vm.setItemsError(err);
                return;
            }
            vm.clearItemsError();
            vm.savingItems = true;
            var p = Promise.resolve();

            dirtyRows.forEach(function (row) {
                p = p.then(function () {
                    return vm.persistRow(row);
                });
            });

            p.then(function () {
                vm.savingItems = false;
                vm._codeLookupLoaded = false;
                vm.notifySuccess('Items saved');
                vm.loadCodes();
            }).catch(function (e) {
                vm.savingItems = false;
                vm.setItemsError(e);
            });
        },
        flushDeletedLabels: function (row) {
            var vm = this;
            var ids = row._deletedLabelIds || [];
            row._deletedLabelIds = [];
            var p = Promise.resolve();
            ids.forEach(function (lid) {
                p = p.then(function () {
                    return axios.post(vm.apiBase() + '/labels_delete/' + lid);
                });
            });
            return p;
        },
        persistRow: function (row) {
            var vm = this;
            return vm.flushDeletedLabels(row).then(function () {
                return vm.persistRowBody(row);
            });
        },
        persistRowBody: function (row) {
            var vm = this;
            var body = { code: (row.code || '').trim() };
            if (row.sort_order !== '' && row.sort_order != null) {
                var so = parseInt(row.sort_order, 10);
                if (!isNaN(so)) body.sort_order = so;
            }
            var parentCode = (row.parent_code || '').trim();
            if (parentCode !== '') {
                var pid = vm.itemCodeToId[parentCode];
                if (pid) {
                    body.parent_id = pid;
                }
            } else if (row.id) {
                body.parent_id = null;
            }

            if (!row.id) {
                return axios.post(vm.apiBase() + '/codes/' + vm.numericId, body).then(function (res) {
                    if (res.data && res.data.id) {
                        row.id = res.data.id;
                        vm.itemCodeIdToCode[row.id] = body.code;
                        vm.itemCodeToId[body.code] = row.id;
                    }
                    return vm.persistLabelsForRow(row);
                });
            }
            return axios.post(vm.apiBase() + '/code_update/' + row.id, body).then(function () {
                if (body.code) {
                    vm.itemCodeIdToCode[row.id] = body.code;
                    vm.itemCodeToId[body.code] = row.id;
                }
                return vm.persistLabelsForRow(row);
            });
        },
        persistLabelsForRow: function (row) {
            if (!row.id) return Promise.resolve();
            var vm = this;
            var snap = row._snapshot ? JSON.parse(row._snapshot) : null;
            var p = Promise.resolve();
            (row.labelRows || []).forEach(function (lr) {
                var lang = (lr.language || '').trim();
                var lab = (lr.label || '').trim();
                if (!lang || !lab) return;
                if (snap && lr.labelId) {
                    var prev = null;
                    var snapLabels = snap.labels || [];
                    for (var k = 0; k < snapLabels.length; k++) {
                        if (snapLabels[k].labelId === lr.labelId) {
                            prev = snapLabels[k];
                            break;
                        }
                    }
                    if (prev && prev.language === lang && prev.label === lab
                        && prev.description === (lr.description || '').trim()) {
                        return;
                    }
                }
                p = p.then(function () {
                    return axios
                        .post(vm.apiBase() + '/code_label/' + row.id, {
                            language: lang,
                            label: lab,
                            description: (lr.description && String(lr.description).trim()) || null
                        })
                        .then(function (res) {
                            if (res.data && res.data.id && !lr.labelId) {
                                lr.labelId = res.data.id;
                            }
                        });
                });
            });
            return p;
        },
        exportItemsCsvUrl: function () {
            return this.apiBase() + '/export_csv/' + this.numericId + '?download=1';
        },
        openCsvImportDialog: function () {
            if (!this.checkDirtyBeforeCsvImport()) {
                return;
            }
            this.csvImportFile = null;
            this.csvImportDryRun = false;
            this.csvImportDialog = true;
        },
        closeCsvImportDialog: function () {
            if (this.importingCsv) {
                return;
            }
            this.csvImportDialog = false;
            this.csvImportFile = null;
        },
        submitCsvImport: function () {
            var vm = this;
            if (!vm.csvImportFile) {
                vm.setItemsError('Choose a .csv file');
                return;
            }
            var url = vm.apiBase() + '/import_csv/' + vm.numericId;
            var q = ['replace=1'];
            if (vm.csvImportDryRun) {
                q.push('dry_run=1');
            }
            url += '?' + q.join('&');
            var fd = new FormData();
            fd.append('file', vm.csvImportFile);
            vm.importingCsv = true;
            axios.post(url, fd)
                .then(function (res) {
                    vm.importingCsv = false;
                    var d = res.data || {};
                    var msg;
                    if (d.dry_run) {
                        msg = 'Preview: ' + (d.codes_count != null ? d.codes_count : 0) + ' code(s), '
                            + (d.rows_parsed != null ? d.rows_parsed : 0) + ' row(s) — nothing saved';
                    } else {
                        msg = 'Imported ' + (d.codes_imported != null ? d.codes_imported : d.codes_count || 0) + ' code(s)';
                    }
                    if (d.warnings && d.warnings.length) {
                        msg += ' — ' + d.warnings.slice(0, 3).join(' · ');
                    }
                    if (d.status === 'failed') {
                        vm.setItemsError(d.message || msg || 'Import failed');
                        return;
                    }
                    vm.notifySuccess(msg || 'Import finished');
                    if (!d.dry_run) {
                        vm.selectedItemIds = [];
                        vm._codeLookupLoaded = false;
                        vm.clearItemsError();
                        vm.loadCodes();
                    }
                    vm.closeCsvImportDialog();
                })
                .catch(function (err) {
                    vm.importingCsv = false;
                    vm.setItemsError(err);
                });
        },
        itemRowClass: function (row, index) {
            var classes = [];
            if (index % 2 === 1 && !this.isRowNew(row) && !this.isRowModified(row)) {
                classes.push('cl-grid-row-alt');
            }
            if (this.isRowNew(row)) {
                classes.push('cl-grid-row-new');
            } else if (this.isRowModified(row)) {
                classes.push('cl-grid-row-modified');
            }
            return classes.join(' ');
        },
        deleteCodelist: function () {
            var vm = this;
            if (!vm.numericId) return;
            if (!confirm('Delete this codelist and all items?')) return;
            axios.post(vm.apiBase() + '/delete/' + vm.numericId)
                .then(function () {
                    vm.notifySuccess('Codelist deleted');
                    vm.$router.push('/');
                })
                .catch(function (err) {
                    vm.notifyFail(err);
                });
        }
    },
    template: `
        <div>
            <v-btn text class="mb-2" @click="cancel"><v-icon left>mdi-arrow-left</v-icon> Back to list</v-btn>

            <v-alert v-if="isReadOnly" type="info" dense outlined class="mb-4">
                {{ $t('codelist_read_only_banner') || 'This codelist is read-only. Unlock it (admin) to edit codes and metadata.' }}
            </v-alert>

            <v-card class="mb-4">
                <v-card-title>{{ isCreate ? 'New codelist' : (isReadOnly ? 'View codelist' : 'Edit codelist') }}</v-card-title>
                <v-card-text>
                    <v-row>
                        <v-col cols="12" md="4">
                            <v-text-field v-model="form.agency" label="Agency" outlined dense
                                :disabled="!isCreate || isReadOnly" required></v-text-field>
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-text-field v-model="form.name" label="Name" outlined dense
                                :disabled="!isCreate || isReadOnly" required></v-text-field>
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-text-field v-model="form.version" label="Version" outlined dense
                                :disabled="!isCreate || isReadOnly" required></v-text-field>
                        </v-col>
                        <v-col v-if="!isCreate && isAdmin" cols="12" md="4">
                            <v-select v-model="form.status" :items="statusSelectItems" item-text="text" item-value="value"
                                :label="$t('codelist_status') || 'Status'" outlined dense></v-select>
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="form.title" label="Title" outlined dense required :disabled="isReadOnly"></v-text-field>
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="form.idno" label="Idno (catalogue handle)" outlined dense
                                :disabled="!isCreate || isReadOnly"
                                hint="Leave blank to auto-generate from agency, name, and version"></v-text-field>
                        </v-col>
                        <v-col cols="12">
                            <v-textarea v-model="form.description" label="Description" outlined dense rows="3" :disabled="isReadOnly"></v-textarea>
                        </v-col>
                        <v-col cols="12">
                            <v-text-field v-model="form.uri" label="URI" outlined dense :disabled="isReadOnly"></v-text-field>
                        </v-col>
                    </v-row>
                </v-card-text>
                <v-card-actions>
                    <v-btn v-if="!isReadOnly || isAdmin" color="primary" :loading="saving" @click="saveMeta">{{ isReadOnly ? 'Save status' : 'Save' }}</v-btn>
                    <v-btn text @click="cancel">Cancel</v-btn>
                    <v-spacer></v-spacer>
                    <v-btn v-if="!isCreate && !isReadOnly" color="error" text @click="deleteCodelist">Delete codelist</v-btn>
                </v-card-actions>
            </v-card>

            <v-card v-if="!isCreate" class="mb-4">
                <v-card-title class="d-flex flex-wrap align-center">
                    <span>Codelist translations</span>
                    <v-spacer></v-spacer>
                    <v-btn small class="mr-2" :loading="translationsLoading" @click="revertTranslations" :disabled="savingTranslations || isReadOnly">Revert</v-btn>
                    <v-btn small color="primary" class="mr-2" :loading="savingTranslations" :disabled="translationsLoading || isReadOnly" @click="saveTranslations">Save translations</v-btn>
                    <v-btn small color="primary" outlined :disabled="translationsLoading || savingTranslations || isReadOnly" @click="addTranslationRow">Add language</v-btn>
                </v-card-title>
                <v-progress-linear v-if="translationsLoading" indeterminate></v-progress-linear>
                <div v-else class="px-4 pb-4 codelist-edit-tables" style="overflow-x: auto;">
                    <table class="translations-grid" style="width: 100%; border-collapse: collapse; min-width: 560px; font-size: 0.8125rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid rgba(0,0,0,.12);">
                                <th class="text-left pa-1" style="width: 200px;">Language</th>
                                <th class="text-left pa-1">Label</th>
                                <th class="text-left pa-1" style="min-width: 140px;">Description</th>
                                <th class="text-right pa-1" style="width: 40px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(trow, ti) in translationRows" :key="'t-' + (trow.id || 'n') + '-' + ti"
                                :class="ti % 2 === 1 ? 'cl-grid-row-alt' : ''"
                                style="border-bottom: 1px solid rgba(0,0,0,.08); vertical-align: middle;">
                                <td class="pa-1">
                                    <v-select v-model="trow.language" :items="isoSelectItems" item-value="value" item-text="text"
                                        dense hide-details outlined clearable class="cl-compact-control"></v-select>
                                </td>
                                <td class="pa-1">
                                    <v-text-field v-model="trow.label" dense hide-details outlined single-line class="cl-compact-control"></v-text-field>
                                </td>
                                <td class="pa-1">
                                    <v-text-field v-model="trow.description" dense hide-details outlined single-line class="cl-compact-control"></v-text-field>
                                </td>
                                <td class="pa-1 text-right">
                                    <v-btn icon x-small color="error" @click="confirmRemoveTranslationRow(ti)" title="Remove row">
                                        <v-icon dense small>mdi-delete-outline</v-icon>
                                    </v-btn>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!translationRows.length" class="grey--text text-body-2 mb-0">Loading translations… If this stays empty, use <strong>Revert</strong> or reload the page.</p>
                </div>
            </v-card>

            <v-card v-if="!isCreate">
                <v-card-title class="d-flex flex-wrap align-start pt-4">
                    <div class="items-search-block mr-4" style="flex: 0 1 280px; min-width: 200px;">
                        <div class="d-flex align-center flex-wrap">
                            <span>Items ({{ itemsTotal }})</span>
                            <span v-if="itemsChangeSummary" class="text-caption orange--text text--darken-2 ml-2">
                                {{ itemsChangeSummary }}
                            </span>
                        </div>
                        <v-text-field
                            v-model="itemsSearchInput"
                            append-icon="mdi-magnify"
                            label="Search items"
                            single-line
                            hide-details
                            dense
                            outlined
                            clearable
                            class="mt-1 mb-0 items-search-field"
                            @input="onItemsSearchInput"
                            @keyup.enter="onItemsSearchSubmit"
                            @click:clear="onItemsSearchClear"
                        ></v-text-field>
                    </div>
                    <v-spacer></v-spacer>
                    <div class="d-flex flex-wrap align-center justify-end">
                    <v-btn small class="mr-2 mb-1" outlined :href="exportItemsCsvUrl()" target="_blank" rel="noopener noreferrer" :disabled="codesLoading || isReadOnly">
                        Export CSV
                    </v-btn>
                    <v-btn small class="mr-2 mb-1" outlined :disabled="codesLoading || savingItems || isReadOnly" @click="openCsvImportDialog">
                        Import CSV
                    </v-btn>
                    <v-btn small class="mr-2 mb-1" :loading="codesLoading" @click="revertItems" :disabled="savingItems || deletingItems || isReadOnly || !isItemsDirty">Revert</v-btn>
                    <v-btn small color="primary" class="mr-2 mb-1" :loading="savingItems" :disabled="codesLoading || deletingItems || isReadOnly || !isItemsDirty" @click="saveAllItems">Save items</v-btn>
                    </div>
                </v-card-title>
                <div v-if="itemsErrorMessage" class="px-4 pt-0 pb-2">
                    <v-alert type="error" dense outlined dismissible @input="onItemsErrorDismiss">
                        <span class="items-error-message">{{ itemsErrorMessage }}</span>
                    </v-alert>
                </div>
                <v-card-text v-if="someItemsSelected && !isReadOnly" class="py-2 px-4 grey lighten-4">
                    <span class="text-body-2 mr-3">{{ selectedItemIds.length }} selected</span>
                    <v-btn small color="error" depressed :loading="deletingItems" :disabled="deletingItems || savingItems" @click="batchDeleteSelectedItems">
                        <v-icon small left>mdi-delete</v-icon>
                        Delete selected
                    </v-btn>
                </v-card-text>
                <v-progress-linear v-if="deletingItems" indeterminate></v-progress-linear>
                <v-progress-linear v-else-if="codesLoading" indeterminate></v-progress-linear>
                <div v-if="!codesLoading" class="items-grid-wrap px-4 pb-4 codelist-edit-tables" style="overflow-x: auto;">
                    <table class="items-grid" style="width: 100%; border-collapse: collapse; min-width: 720px; font-size: 0.8125rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid rgba(0,0,0,.12);">
                                <th v-if="!isReadOnly" class="pa-1" style="width: 36px;">
                                    <v-simple-checkbox :value="allItemsSelected" :indeterminate="someItemsSelected && !allItemsSelected" @input="toggleSelectAllItems"></v-simple-checkbox>
                                </th>
                                <th class="text-left pa-1" style="width: 76px;" v-if="!isReadOnly">Status</th>
                                <th class="text-left pa-1" style="width: 64px;">Sort</th>
                                <th class="text-left pa-1" style="width: 100px;">Parent code</th>
                                <th v-if="multiLanguage" class="text-left pa-1" style="width: 120px;">Language</th>
                                <th class="text-left pa-1" style="width: 100px;">Code</th>
                                <th class="text-left pa-1">Label</th>
                                <th class="text-left pa-1" style="min-width: 120px;">Description</th>
                                <th v-if="multiLanguage" class="text-center pa-1" style="width: 32px;"></th>
                                <th class="text-right pa-1" style="width: 40px;"></th>
                            </tr>
                        </thead>
                        <tbody v-for="(row, ri) in itemRows" :key="rowKey(row, ri)" :class="itemRowClass(row, ri)">
                                <tr style="border-bottom: 1px solid rgba(0,0,0,.08); vertical-align: middle;">
                                    <td v-if="!isReadOnly" class="pa-1" :rowspan="itemLabelBodyRowCount(row)">
                                        <v-simple-checkbox v-if="row.id" :value="isItemSelected(row)" @input="toggleItemSelected(row)"></v-simple-checkbox>
                                    </td>
                                    <td v-if="!isReadOnly" class="pa-1 cl-grid-status-cell" :rowspan="itemLabelBodyRowCount(row)">
                                        <v-chip v-if="itemChangeLabel(row)" x-small :color="itemChangeColor(row)" dark class="font-weight-medium">
                                            {{ itemChangeLabel(row) }}
                                        </v-chip>
                                    </td>
                                    <td class="pa-1" :rowspan="itemLabelBodyRowCount(row)">
                                        <v-text-field v-model="row.sort_order" dense hide-details outlined type="number" single-line class="cl-compact-control" :disabled="isReadOnly"></v-text-field>
                                    </td>
                                    <td class="pa-1" :rowspan="itemLabelBodyRowCount(row)">
                                        <v-text-field v-model="row.parent_code" dense hide-details outlined single-line class="cl-compact-control" :disabled="isReadOnly"></v-text-field>
                                    </td>
                                    <td v-if="multiLanguage" class="pa-1">
                                        <v-select v-model="row.labelRows[0].language" :items="itemLanguageSelectItems" item-value="value" item-text="text"
                                            dense hide-details outlined class="cl-compact-control"
                                            :disabled="!enabledLangCodes.length || isReadOnly"></v-select>
                                    </td>
                                    <td class="pa-1" :rowspan="itemLabelBodyRowCount(row)">
                                        <v-text-field v-model="row.code" dense hide-details outlined single-line class="cl-compact-control" :disabled="isReadOnly"></v-text-field>
                                    </td>
                                    <td class="pa-1">
                                        <v-text-field v-model="row.labelRows[0].label" dense hide-details outlined single-line class="cl-compact-control" :disabled="isReadOnly"></v-text-field>
                                    </td>
                                    <td class="pa-1">
                                        <v-text-field v-model="row.labelRows[0].description" dense hide-details outlined single-line class="cl-compact-control" :disabled="isReadOnly"></v-text-field>
                                    </td>
                                    <td v-if="multiLanguage" class="pa-1 text-center">
                                        <v-btn icon x-small @click="removeLabelSlot(row, 0)" title="Remove label" :disabled="isReadOnly">
                                            <v-icon dense small>mdi-close</v-icon>
                                        </v-btn>
                                    </td>
                                    <td class="pa-1 text-right" :rowspan="itemLabelBodyRowCount(row)">
                                        <v-btn icon x-small color="error" @click="confirmRemoveItemRow(row)" title="Remove item" :disabled="isReadOnly || deletingItems || savingItems">
                                            <v-icon dense small>mdi-delete-outline</v-icon>
                                        </v-btn>
                                    </td>
                                </tr>
                                <tr v-for="(lr, li) in itemLabelRowsSliceFrom(row, 1)" :key="rowKey(row, ri) + '-lr-' + (li + 1)"
                                    style="border-bottom: 1px solid rgba(0,0,0,.08); vertical-align: middle;">
                                    <td v-if="multiLanguage" class="pa-1">
                                        <v-select v-model="lr.language" :items="itemLanguageSelectItems" item-value="value" item-text="text"
                                            dense hide-details outlined class="cl-compact-control"
                                            :disabled="!enabledLangCodes.length || isReadOnly"></v-select>
                                    </td>
                                    <td class="pa-1">
                                        <v-text-field v-model="lr.label" dense hide-details outlined single-line class="cl-compact-control" :disabled="isReadOnly"></v-text-field>
                                    </td>
                                    <td class="pa-1">
                                        <v-text-field v-model="lr.description" dense hide-details outlined single-line class="cl-compact-control" :disabled="isReadOnly"></v-text-field>
                                    </td>
                                    <td v-if="multiLanguage" class="pa-1 text-center">
                                        <v-btn icon x-small @click="removeLabelSlot(row, li + 1)" title="Remove label" :disabled="isReadOnly">
                                            <v-icon dense small>mdi-close</v-icon>
                                        </v-btn>
                                    </td>
                                </tr>
                                <tr v-if="multiLanguage" style="border-bottom: 1px solid rgba(0,0,0,.12); vertical-align: middle;">
                                    <td class="pa-1" colspan="4">
                                        <v-btn x-small text color="primary" class="px-0" height="24" :disabled="!enabledLangCodes.length || isReadOnly" @click="addLabelSlot(row)">
                                            <v-icon x-small left>mdi-plus</v-icon> Language
                                        </v-btn>
                                    </td>
                                </tr>
                        </tbody>
                    </table>
                    <div v-if="!isReadOnly" class="d-flex justify-center py-2">
                        <v-btn small text color="primary" :disabled="codesLoading || savingItems" @click="addItemRow">
                            <v-icon small left>mdi-plus</v-icon>
                            Add row
                        </v-btn>
                    </div>
                </div>
                <v-card-text v-if="!codesLoading && itemRows.length === 0" class="grey--text">
                    {{ itemsSearch ? 'No items match \u201c' + itemsSearch + '\u201d.' : 'No items yet. Click Add row, import CSV, or Save items.' }}
                </v-card-text>
                <div v-if="itemsTotal > itemsPerPage || itemsPage > 1"
                    class="d-flex align-center justify-end pa-2"
                    style="border-top: 1px solid rgba(0,0,0,.12);">
                    <span class="text-caption grey--text mr-3">
                        {{ itemsOffset + 1 }}–{{ Math.min(itemsOffset + itemsPerPage, itemsTotal) }} of {{ itemsTotal }}
                    </span>
                    <v-btn icon small :disabled="itemsPage <= 1 || codesLoading || savingItems" @click="prevItemsPage">
                        <v-icon>mdi-chevron-left</v-icon>
                    </v-btn>
                    <v-btn icon small :disabled="itemsOffset + itemsPerPage >= itemsTotal || codesLoading || savingItems" @click="nextItemsPage">
                        <v-icon>mdi-chevron-right</v-icon>
                    </v-btn>
                </div>
            </v-card>

            <v-dialog v-model="csvImportDialog" max-width="520" @click:outside="closeCsvImportDialog">
                <v-card>
                    <v-card-title>Import items (CSV)</v-card-title>
                    <v-card-text>
                        <p class="text-body-2 grey--text text--darken-1 mb-3">
                            Columns: sort, parent_code, language, code, label, description. UTF-8 CSV (Excel-compatible).
                            All existing items are replaced.
                        </p>
                        <v-file-input
                            v-model="csvImportFile"
                            dense
                            outlined
                            accept=".csv,text/csv"
                            label="CSV file"
                            prepend-icon="mdi-file-delimited"
                            :disabled="importingCsv"
                            show-size
                        ></v-file-input>
                        <v-checkbox v-model="csvImportDryRun" hide-details dense class="mt-0"
                            label="Preview only (dry run — do not save)" :disabled="importingCsv"></v-checkbox>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn text :disabled="importingCsv" @click="closeCsvImportDialog">Cancel</v-btn>
                        <v-btn color="primary" :loading="importingCsv" :disabled="!csvImportFile" @click="submitCsvImport">Import</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </div>
    `
});
