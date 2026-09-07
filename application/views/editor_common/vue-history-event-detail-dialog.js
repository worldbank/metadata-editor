/**
 * Shared helpers and dialog for publication / queue history events.
 */
(function (global) {
    function formatTimestamp(ts) {
        if (!ts) {
            return '—';
        }
        if (typeof moment !== 'undefined') {
            return moment.unix(ts).format('YYYY-MM-DD HH:mm');
        }
        return String(ts);
    }

    function translate(vm, key, fallback) {
        if (vm && typeof vm.$t === 'function') {
            var translated = vm.$t(key);
            if (translated && translated !== key) {
                return translated;
            }
        }
        return fallback;
    }

    function eventLabel(row, vm) {
        var event = row && row.event ? row.event : '';
        var key = 'publication_event_' + (event || 'updated');
        var translated = translate(vm, key, '');
        if (translated) {
            return translated;
        }
        var labels = {
            ready: 'Ready',
            ready_cleared: 'Clear',
            returned: 'Return',
            queue_closed: 'Close',
            queue_cancelled: 'Cancel',
            published: 'Publish',
            updated: 'Update',
            submitted: 'Submit',
            withdrawn: 'Withdraw',
        };
        return labels[event] || event || '—';
    }

    function eventColor(row) {
        var colors = {
            ready: 'orange',
            ready_cleared: 'grey',
            returned: 'error',
            queue_closed: 'blue-grey',
            queue_cancelled: 'blue-grey',
            published: 'success',
            submitted: 'blue',
            withdrawn: 'blue-grey',
        };
        return colors[row && row.event ? row.event : ''] || 'grey lighten-2';
    }

    function noteText(row, vm) {
        var payload = row && row.payload ? row.payload : {};
        if (payload.note) {
            return String(payload.note);
        }
        if (row && row.event === 'published' && payload.source === 'nada_publish') {
            return translate(vm, 'queue_history_nada_publish', 'Published via NADA');
        }
        return '';
    }

    function requestOptions(row) {
        if (row && row.request && row.request.options && typeof row.request.options === 'object') {
            return row.request.options;
        }
        if (row && row.payload && row.payload.options && typeof row.payload.options === 'object') {
            return row.payload.options;
        }
        if (row && row.publication_options && typeof row.publication_options === 'object') {
            return row.publication_options;
        }
        return {};
    }

    function metadataSummary(row, vm) {
        var parts = [];
        var payload = row && row.payload ? row.payload : {};
        if (payload.status) {
            parts.push(translate(vm, 'status', 'Status') + ': ' + payload.status);
        }
        if (payload.remote_id) {
            parts.push('ID: ' + payload.remote_id);
        }
        var options = requestOptions(row);
        if (options.access_policy) {
            parts.push(translate(vm, 'data_access', 'Data access') + ': ' + options.access_policy);
        }
        if (options.repositoryid) {
            parts.push(translate(vm, 'collection', 'Collection') + ': ' + options.repositoryid);
        }
        if (payload.source) {
            parts.push(translate(vm, 'source', 'Source') + ': ' + payload.source);
        }
        return parts.join(' · ');
    }

    function tableNoteText(row, vm) {
        var note = noteText(row, vm);
        if (note) {
            return note;
        }
        var summary = metadataSummary(row, vm);
        return summary || '—';
    }

    function notePreview(row, vm, maxLen) {
        maxLen = maxLen || 120;
        var full = tableNoteText(row, vm);
        if (!full || full === '—') {
            return '—';
        }
        if (full.length <= maxLen) {
            return full;
        }
        return full.substring(0, maxLen).trim() + '…';
    }

    function isNoteTruncated(row, vm, maxLen) {
        maxLen = maxLen || 120;
        var full = tableNoteText(row, vm);
        return !!(full && full !== '—' && full.length > maxLen);
    }

    function payloadField(row, key) {
        var payload = row && row.payload ? row.payload : {};
        if (payload[key] !== undefined && payload[key] !== null && payload[key] !== '') {
            return String(payload[key]);
        }
        return '';
    }

    function payloadOption(row, key) {
        var options = requestOptions(row);
        if (options[key] !== undefined && options[key] !== null && options[key] !== '') {
            return String(options[key]);
        }
        return '';
    }

    global.HistoryEventDisplay = {
        formatTimestamp: formatTimestamp,
        eventLabel: eventLabel,
        eventColor: eventColor,
        noteText: noteText,
        tableNoteText: tableNoteText,
        notePreview: notePreview,
        isNoteTruncated: isNoteTruncated,
        metadataSummary: metadataSummary,
        payloadField: payloadField,
        payloadOption: payloadOption,
        requestOptions: requestOptions,
    };
})(window);

Vue.component('history-event-detail-dialog', {
    props: {
        value: {
            type: Boolean,
            default: false,
        },
        row: {
            type: Object,
            default: null,
        },
        variant: {
            type: String,
            default: 'project',
            validator: function (value) {
                return value === 'project' || value === 'global';
            },
        },
    },
    computed: {
        dialogVisible: {
            get: function () {
                return this.value;
            },
            set: function (val) {
                this.$emit('input', val);
            },
        },
        display: function () {
            return window.HistoryEventDisplay;
        },
        requestInfo: function () {
            return this.row && this.row.request ? this.row.request : null;
        },
        hasRequestSection: function () {
            return !!this.requestInfo;
        },
        requestSubmittedBy: function () {
            if (!this.requestInfo) {
                return '—';
            }
            if (this.requestInfo.submitted_by_username) {
                return this.requestInfo.submitted_by_username;
            }
            if (this.variant === 'global' && this.row && this.row.requester_username) {
                return this.row.requester_username;
            }
            return '—';
        },
        requestAccessPolicy: function () {
            return this.display.payloadOption(this.row, 'access_policy');
        },
        requestRepositoryId: function () {
            return this.display.payloadOption(this.row, 'repositoryid');
        },
        actionReviewer: function () {
            if (this.variant === 'global') {
                return (this.row && this.row.reviewer_username) ? this.row.reviewer_username : '—';
            }
            return (this.row && this.row.actor_username) ? this.row.actor_username : '—';
        },
        actionNote: function () {
            return this.display.noteText(this.row, this);
        },
        remoteUrl: function () {
            return this.display.payloadField(this.row, 'remote_url');
        },
        remoteId: function () {
            return this.display.payloadField(this.row, 'remote_id');
        },
        publishStatus: function () {
            return this.display.payloadField(this.row, 'status');
        },
        publishSource: function () {
            return this.display.payloadField(this.row, 'source');
        },
        projectEditUrl: function () {
            if (!this.row || !this.row.sid) {
                return null;
            }
            var base = (typeof CI !== 'undefined' && CI.site_url) ? CI.site_url : '';
            return String(base).replace(/\/?$/, '/') + 'editor/edit/' + this.row.sid + '#/publish?tab=history';
        },
        hasPublishMetadata: function () {
            return !!(this.publishStatus || this.remoteId || this.remoteUrl || this.publishSource);
        },
    },
    methods: {
        close: function () {
            this.dialogVisible = false;
        },
        formatTimestamp: function (ts) {
            return this.display.formatTimestamp(ts);
        },
        eventLabel: function (row) {
            return this.display.eventLabel(row || this.row, this);
        },
        eventColor: function (row) {
            return this.display.eventColor(row || this.row);
        },
    },
    template: `
    <v-dialog v-model="dialogVisible" max-width="720" scrollable @click:outside="close">
        <v-card v-if="row">
            <v-card-title class="text-h6 py-3">
                {{ $t('publication_history_details') || 'Publication history details' }}
            </v-card-title>
            <v-card-text class="pt-0">
                <v-card outlined class="mb-4">
                    <v-card-subtitle class="pb-1 pt-3 font-weight-medium">
                        {{ $t('publication_history_request_section') || 'Publish request' }}
                    </v-card-subtitle>
                    <v-card-text class="pt-0">
                        <v-simple-table dense class="history-event-detail-table">
                            <tbody>
                                <tr v-if="variant === 'global'">
                                    <td class="text-muted" style="width:160px;">{{ $t('project') || 'Project' }}</td>
                                    <td>
                                        <a
                                            v-if="projectEditUrl"
                                            :href="projectEditUrl"
                                            target="_blank"
                                            rel="noopener"
                                            class="text-decoration-none font-weight-medium"
                                        >{{ row.project_title || ('Project #' + row.sid) }}</a>
                                        <span v-else>{{ row.project_title || '—' }}</span>
                                        <div v-if="row.project_idno" class="text-caption grey--text">{{ row.project_idno }}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">{{ $t('catalog') || 'Catalog connection' }}</td>
                                    <td>
                                        <div>{{ row.catalog_title || ('Catalog #' + row.catalog_id) }}</div>
                                        <a
                                            v-if="row.catalog_url"
                                            :href="row.catalog_url"
                                            target="_blank"
                                            rel="noopener"
                                            class="text-caption grey--text text--darken-1"
                                        >{{ row.catalog_url }}</a>
                                    </td>
                                </tr>
                                <tr v-if="hasRequestSection">
                                    <td class="text-muted">{{ $t('submitted_at') || 'Submitted' }}</td>
                                    <td>{{ formatTimestamp(requestInfo.submitted_at) }}</td>
                                </tr>
                                <tr v-if="hasRequestSection">
                                    <td class="text-muted">{{ $t('submitted_by') || 'Submitted by' }}</td>
                                    <td>{{ requestSubmittedBy }}</td>
                                </tr>
                                <tr v-else>
                                    <td class="text-muted">{{ $t('submitted_at') || 'Submitted' }}</td>
                                    <td class="text-muted">{{ $t('publication_history_no_request') || 'No linked publish request' }}</td>
                                </tr>
                                <tr v-if="requestAccessPolicy">
                                    <td class="text-muted">{{ $t('data_access') || 'Data access' }}</td>
                                    <td>{{ requestAccessPolicy }}</td>
                                </tr>
                                <tr v-if="requestRepositoryId">
                                    <td class="text-muted">{{ $t('collection') || 'Collection' }}</td>
                                    <td>{{ requestRepositoryId }}</td>
                                </tr>
                            </tbody>
                        </v-simple-table>
                    </v-card-text>
                </v-card>

                <v-card outlined>
                    <v-card-subtitle class="pb-1 pt-3 font-weight-medium d-flex align-center">
                        <span>{{ $t('publication_history_action_section') || 'Reviewer action' }}</span>
                        <v-spacer></v-spacer>
                        <v-chip x-small :color="eventColor()" dark>{{ eventLabel() }}</v-chip>
                    </v-card-subtitle>
                    <v-card-text class="pt-0">
                        <v-simple-table dense class="history-event-detail-table mb-4">
                            <tbody>
                                <tr>
                                    <td class="text-muted" style="width:160px;">{{ $t('date') || 'Date' }}</td>
                                    <td>{{ formatTimestamp(row.created) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">{{ $t('queue_history_reviewer') || 'Reviewer' }}</td>
                                    <td>{{ actionReviewer }}</td>
                                </tr>
                                <tr v-if="actionNote">
                                    <td class="text-muted align-top">{{ $t('note') || 'Note' }}</td>
                                    <td>
                                        <div class="history-event-detail-note text-body-2" style="white-space: pre-wrap; word-break: break-word;">{{ actionNote }}</div>
                                    </td>
                                </tr>
                            </tbody>
                        </v-simple-table>

                        <div v-if="hasPublishMetadata">
                            <div class="text-subtitle-2 mb-2">{{ $t('publication_history_metadata') || 'Publication details' }}</div>
                            <v-simple-table dense class="history-event-detail-table">
                                <tbody>
                                    <tr v-if="publishStatus">
                                        <td class="text-muted" style="width:160px;">{{ $t('status') || 'Status' }}</td>
                                        <td>{{ publishStatus }}</td>
                                    </tr>
                                    <tr v-if="remoteId">
                                        <td class="text-muted">{{ $t('remote_study_id') || 'Catalog study ID' }}</td>
                                        <td>{{ remoteId }}</td>
                                    </tr>
                                    <tr v-if="remoteUrl">
                                        <td class="text-muted">{{ $t('url') || 'URL' }}</td>
                                        <td>
                                            <a :href="remoteUrl" target="_blank" rel="noopener">{{ remoteUrl }}</a>
                                        </td>
                                    </tr>
                                    <tr v-if="publishSource">
                                        <td class="text-muted">{{ $t('source') || 'Source' }}</td>
                                        <td>{{ publishSource }}</td>
                                    </tr>
                                </tbody>
                            </v-simple-table>
                        </div>
                    </v-card-text>
                </v-card>
            </v-card-text>
            <v-card-actions class="pa-3">
                <v-spacer></v-spacer>
                <v-btn text @click="close">{{ $t('close') || 'Close' }}</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
    `,
});
