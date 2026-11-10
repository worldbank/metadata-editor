// Data notes (cell footnotes): import dialog and result report.
//
// <indicator-notes-report>: summary of a notes import (POST indicator_dsd/notes_import) or of the notes
// read from the data CSV during a data import (data_notes in data_upload_import), plus reference checks.
// <indicator-notes-import>: dialog to upload a notes CSV (note_id, note [, indicator_id, scope, column]).

Vue.component('indicator-notes-report', {
    props: {
        /** { added[], updated[{id,before,after}], unchanged[], removed[], conflicts[], skipped_* } */
        summary: { type: Object, default: null },
        /** { errors[], warnings[], unknown[], unused[], wrong_column[] } */
        checks: { type: Object, default: null },
        notesCount: { type: Number, default: null },
        dryRun: { type: Boolean, default: false },
        /** 'notes_csv' | 'data_csv' */
        source: { type: String, default: 'notes_csv' },
        warning: { type: String, default: '' },
        /** Data import only: { generated: [{id, note, column}], reused, added_columns[], warning? } */
        generated: { type: Object, default: null }
    },
    computed: {
        counts: function() {
            var s = this.summary || {};
            var len = function(v) { return Array.isArray(v) ? v.length : 0; };
            return {
                added: len(s.added),
                updated: len(s.updated),
                unchanged: len(s.unchanged),
                removed: len(s.removed),
                conflicts: len(s.conflicts),
                skippedScope: len(s.skipped_scope),
                skippedOther: s.skipped_other_indicator || 0,
                skippedMulti: s.skipped_multi_id || 0
            };
        },
        updatedIds: function() {
            return ((this.summary && this.summary.updated) || []).map(function(u) { return u.id; });
        },
        generatedList: function() {
            return (this.generated && Array.isArray(this.generated.generated)) ? this.generated.generated : [];
        },
        generatedIds: function() {
            return this.generatedList.map(function(g) { return g.id; });
        },
        unknownIds: function() {
            var out = [];
            ((this.checks && this.checks.unknown) || []).forEach(function(u) {
                if (out.indexOf(u.id) === -1) {
                    out.push(u.id);
                }
            });
            return out;
        },
        errors: function() {
            return (this.checks && this.checks.errors) || [];
        },
        warnings: function() {
            return (this.checks && this.checks.warnings) || [];
        },
        title: function() {
            if (this.source === 'data_csv') {
                return 'Data notes';
            }
            return this.dryRun ? 'Notes import preview (nothing saved)' : 'Notes imported';
        },
        textColumnsLabel: function() {
            var tc = this.summary && this.summary.text_columns;
            if (!tc || typeof tc !== 'object') {
                return '';
            }
            return Object.keys(tc).map(function(k) { return tc[k] + ' → ' + k; }).join(', ');
        }
    },
    template: `
        <div class="indicator-notes-report">
            <v-alert v-if="warning" type="warning" dense outlined class="mb-2">{{ warning }}</v-alert>
            <v-alert
                v-else
                :type="errors.length ? 'warning' : 'success'"
                dense outlined class="mb-2"
            >
                <div class="font-weight-medium">{{ title }}</div>
                <div v-if="summary" class="text-body-2">
                    <span v-if="source === 'data_csv' && textColumnsLabel">From text column {{ textColumnsLabel }}: </span>
                    {{ counts.added }} added, {{ counts.updated }} updated, {{ counts.unchanged }} unchanged<span v-if="counts.removed">, {{ counts.removed }} removed</span>.
                    <span v-if="notesCount !== null">The project has {{ notesCount }} note(s).</span>
                </div>
                <div v-else-if="notesCount !== null" class="text-body-2">The project has {{ notesCount }} note(s).</div>
                <div v-if="counts.skippedOther" class="text-caption">{{ counts.skippedOther }} row(s) for other indicators skipped.</div>
                <div v-if="counts.skippedScope" class="text-caption">Not imported (unknown scope): {{ summary.skipped_scope.join(', ') }}.</div>
                <div v-if="counts.skippedMulti" class="text-caption">{{ counts.skippedMulti }} cell(s) with several note IDs were not used for note text.</div>
                <div v-if="updatedIds.length" class="text-caption">Updated: {{ updatedIds.join(', ') }}.</div>
                <div v-if="generated && generated.warning" class="text-caption">{{ generated.warning }}</div>
                <div v-if="generatedList.length" class="text-caption">
                    Note IDs generated from note text: {{ generatedIds.join(', ') }}<span v-if="generated.added_columns && generated.added_columns.length"> (column {{ generated.added_columns.join(', ') }} added to the data)</span>.
                </div>
                <div v-if="generated && generated.reused" class="text-caption">{{ generated.reused }} cell(s) reused the ID of a note with the same text.</div>
                <div v-for="c in (summary && summary.conflicts) || []" :key="'c-' + c.id + c.used" class="text-caption">
                    {{ c.id }} has different texts in the file; the last one was used.
                </div>

                <template v-if="errors.length || warnings.length">
                    <v-divider class="my-2"></v-divider>
                    <div v-for="(m, i) in errors" :key="'e-' + i" class="text-caption error--text">{{ m }}</div>
                    <div v-for="(m, i) in warnings" :key="'w-' + i" class="text-caption">{{ m }}</div>
                    <div v-if="unknownIds.length" class="text-caption font-weight-medium mt-1">
                        {{ unknownIds.length }} note ID(s) used in the data have no text ({{ unknownIds.join(', ') }}).
                        Import a notes file or add them under Data notes.
                    </div>
                </template>
            </v-alert>
        </div>
    `
});

Vue.component('indicator-notes-import', {
    props: {
        value: { type: Boolean, default: false }
    },
    data: function() {
        return {
            dataset_id: project_sid,
            file: null,
            replace: false,
            loading: false,
            error: '',
            result: null
        };
    },
    watch: {
        value: function(open) {
            if (open) {
                this.file = null;
                this.replace = false;
                this.error = '';
                this.result = null;
            }
        }
    },
    methods: {
        close: function() {
            this.$emit('input', false);
        },
        submit: function(dryRun) {
            var vm = this;
            if (!vm.file) {
                vm.error = 'Choose a notes CSV file.';
                return;
            }
            var form = new FormData();
            form.append('file', vm.file);
            form.append('dry_run', dryRun ? '1' : '0');
            form.append('replace', vm.replace ? '1' : '0');
            vm.loading = true;
            vm.error = '';
            axios.post(CI.base_url + '/api/indicator_dsd/notes_import/' + vm.dataset_id, form)
                .then(function(res) {
                    var d = res.data || {};
                    if (d.status !== 'success') {
                        throw { response: { data: d } };
                    }
                    vm.result = d;
                    if (!dryRun) {
                        vm.$emit('imported', d);
                        if (typeof EventBus !== 'undefined') {
                            EventBus.$emit('onSuccess', 'Data notes imported');
                        }
                    }
                })
                .catch(function(err) {
                    var d = err && err.response && err.response.data ? err.response.data : {};
                    var msg = d.message || (err && err.message) || 'Notes import failed';
                    if (Array.isArray(d.errors) && d.errors.length) {
                        msg += ' ' + d.errors.join(' ');
                    }
                    vm.error = msg;
                    vm.result = null;
                })
                .then(function() {
                    vm.loading = false;
                });
        }
    },
    template: `
        <v-dialog :value="value" @input="$emit('input', $event)" max-width="640" persistent>
            <v-card>
                <v-card-title class="text-h6">Import data notes</v-card-title>
                <v-card-text>
                    <p class="text-body-2 mb-2">
                        Upload a CSV with columns <code>note_id</code> and <code>note</code>
                        (optional: <code>indicator_id</code>, <code>scope</code>, <code>column</code>).
                        <code>scope</code> is <code>cell</code> (default) or <code>table</code>; table notes are shown under every chart and table.
                        Rows for other indicators are skipped. Notes are added or updated by ID.
                    </p>
                    <v-file-input
                        v-model="file"
                        accept=".csv,text/csv"
                        label="Notes CSV"
                        outlined dense show-size
                        prepend-icon="mdi-file-delimited-outline"
                        :disabled="loading"
                    ></v-file-input>
                    <v-checkbox
                        v-model="replace"
                        dense hide-details class="mt-0 mb-3"
                        :disabled="loading"
                        label="Replace existing notes (remove notes that are not in the file)"
                    ></v-checkbox>
                    <v-alert v-if="error" type="error" dense outlined class="mb-2">{{ error }}</v-alert>
                    <indicator-notes-report
                        v-if="result"
                        :summary="result.summary"
                        :checks="result.checks"
                        :notes-count="result.notes_count"
                        :dry-run="!!result.dry_run"
                        source="notes_csv"
                    ></indicator-notes-report>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn text @click="close" :disabled="loading">Close</v-btn>
                    <v-btn outlined color="primary" :loading="loading" :disabled="!file" @click="submit(true)">Preview</v-btn>
                    <v-btn depressed color="primary" :loading="loading" :disabled="!file" @click="submit(false)">Import</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    `
});
