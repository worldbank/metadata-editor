Vue.component('vue-jobs-component', {
    data: function () {
        return {
            jobs: [],
            job_types: [],
            users: [],
            queue_stats: null,
            worker_status: null,
            stale_count: 0,
            stale_config: null,
            loading: false,
            loading_detail: false,
            loading_summary: false,
            error_message: '',
            pagination: {
                page: 1,
                itemsPerPage: 25,
                total: 0
            },
            view_tab: 0,
            filter_panel: [0, 1, 2],
            filters: {
                status: null,
                job_type: null,
                user_id: null,
                stale_only: null
            },
            selected_job: null,
            detail_dialog: false,
            poll_timer: null,
            highlight_uuid: null,
            selected: [],
            action_loading: false
        };
    },

    mounted: function () {
        this.highlight_uuid = this.getHighlightUuid();
        this.loadJobTypes();
        this.loadSummary();
        if (this.isAdmin) {
            this.loadUsers();
        }
        this.loadJobs();
        if (this.highlight_uuid) {
            this.openJobDetail(this.highlight_uuid);
        }
        this.startPolling();
    },

    beforeDestroy: function () {
        this.stopPolling();
    },

    computed: {
        isAdmin: function () {
            return CI && CI.user_info && CI.user_info.is_admin === true;
        },

        pageTitle: function () {
            return this.isAdmin
                ? (this.$t('background_jobs') || 'Background jobs')
                : (this.$t('my_jobs') || 'My jobs');
        },

        apiBase: function () {
            return (CI.site_url || '').replace(/\/?$/, '/') + 'api/jobs';
        },

        table_headers: function () {
            var headers = [
                { text: this.$t('status') || 'Status', value: 'status', sortable: false, width: '8%' },
                { text: this.$t('type') || 'Type', value: 'job_type', sortable: false, width: '14%' },
                { text: 'UUID', value: 'uuid', sortable: false },
                { text: this.$t('created') || 'Created', value: 'created_at', sortable: false, width: '12%', cellClass: 'text-no-wrap' },
                { text: this.$t('attempts') || 'Attempts', value: 'attempts', sortable: false, width: '7%', align: 'center' }
            ];

            if (this.isAdmin) {
                headers.splice(3, 0, {
                    text: this.$t('user') || 'User',
                    value: 'user_id',
                    sortable: false,
                    width: '10%'
                });
            }

            headers.push({
                text: '',
                value: 'actions',
                sortable: false,
                align: 'end',
                width: '48px'
            });

            return headers;
        },

        isActiveTab: function () {
            return this.view_tab === 0;
        },

        isHistoryTab: function () {
            return this.view_tab === 1;
        },

        filterStatusOptions: function () {
            if (this.isActiveTab) {
                return [
                    { value: null, text: this.$t('all_active') || 'All active' },
                    { value: 'pending', text: this.$t('pending') || 'Pending' },
                    { value: 'held', text: this.$t('held') || 'Held' },
                    { value: 'processing', text: this.$t('processing') || 'Processing' }
                ];
            }
            return [
                { value: null, text: this.$t('all_history') || 'All finished' },
                { value: 'completed', text: this.$t('completed') || 'Completed' },
                { value: 'failed', text: this.$t('failed') || 'Failed' },
                { value: 'cancelled', text: this.$t('cancelled') || 'Cancelled' }
            ];
        },

        emptyJobsMessage: function () {
            return this.isActiveTab
                ? (this.$t('no_active_jobs_found') || 'No active jobs found')
                : (this.$t('no_history_jobs_found') || 'No finished jobs found');
        },

        status_options: function () {
            return this.filterStatusOptions;
        },

        has_active_jobs: function () {
            return this.jobs.some(function (job) {
                return job.status === 'pending' || job.status === 'held' || job.status === 'processing';
            });
        },

        worker_banner: function () {
            if (!this.isAdmin || !this.worker_status) {
                return null;
            }
            var status = this.worker_status.status || 'unknown';
            if (status === 'running') {
                return {
                    color: 'success',
                    text: this.$t('worker_running') || 'Background worker is running'
                };
            }
            if (status === 'stopped') {
                return {
                    color: 'error',
                    text: this.$t('worker_stopped') || 'Background worker is not running — pending jobs will not be processed'
                };
            }
            return {
                color: 'warning',
                text: this.$t('worker_unknown') || 'Background worker status is unknown'
            };
        },

        current_offset: function () {
            return (this.pagination.page - 1) * this.pagination.itemsPerPage;
        },

        selected_pending_count: function () {
            return this.selected.filter(function (j) { return j.status === 'pending'; }).length;
        },

        selected_failed_count: function () {
            return this.selected.filter(function (j) { return j.status === 'failed'; }).length;
        },

        selected_terminal_count: function () {
            return this.selected.filter(function (j) {
                return j.status === 'completed' || j.status === 'failed' || j.status === 'cancelled';
            }).length;
        },

        can_batch_cancel: function () {
            return this.selected_pending_count > 0;
        },

        can_batch_retry: function () {
            return this.selected_failed_count > 0;
        },

        can_batch_delete: function () {
            return this.isAdmin && this.selected_terminal_count > 0;
        },

        selected_held_count: function () {
            return this.selected.filter(function (j) { return j.status === 'held'; }).length;
        },

        can_batch_hold: function () {
            return this.isAdmin && this.selected_pending_count > 0;
        },

        can_batch_release: function () {
            return this.isAdmin && this.selected_held_count > 0;
        },

        can_hold_all_pending: function () {
            return this.isAdmin && this.queue_stats && (this.queue_stats.pending || 0) > 0;
        },

        can_release_all_held: function () {
            return this.isAdmin && this.queue_stats && (this.queue_stats.held || 0) > 0;
        },

        can_hold_selected_job: function () {
            return this.isAdmin && this.selected_job && this.selected_job.status === 'pending';
        },

        can_release_selected_job: function () {
            return this.isAdmin && this.selected_job && this.selected_job.status === 'held';
        },

        can_retry_selected_job: function () {
            return this.selected_job && this.selected_job.status === 'failed';
        },

        can_cancel_selected_job: function () {
            return this.selected_job && this.selected_job.status === 'pending';
        },

        can_delete_selected_job: function () {
            return this.isAdmin && this.selected_job &&
                (this.selected_job.status === 'completed' || this.selected_job.status === 'failed' ||
                 this.selected_job.status === 'cancelled');
        },

        stale_filter_options: function () {
            return [
                { value: null, text: this.$t('all') || 'All' },
                { value: true, text: this.$t('stale_jobs') || 'Stale only' }
            ];
        },

        hasActiveFilters: function () {
            return !!(this.filters.status || this.filters.job_type || this.filters.user_id || this.filters.stale_only);
        },

        activeFilterChips: function () {
            var vm = this;
            var chips = [];

            if (vm.filters.status) {
                var statusOpt = vm.filterStatusOptions.find(function (option) {
                    return option.value === vm.filters.status;
                });
                chips.push({
                    key: 'status',
                    label: (statusOpt && statusOpt.text) || vm.filters.status,
                    color: vm.statusColor(vm.filters.status)
                });
            }

            if (vm.filters.job_type) {
                chips.push({
                    key: 'job_type',
                    label: vm.jobTypeLabel(vm.filters.job_type),
                    color: 'primary'
                });
            }

            if (vm.isAdmin && vm.filters.user_id) {
                chips.push({
                    key: 'user_id',
                    label: vm.userLabel(vm.filters.user_id),
                    color: 'info'
                });
            }

            if (vm.isActiveTab && vm.filters.stale_only) {
                chips.push({
                    key: 'stale_only',
                    label: vm.$t('stale_jobs') || 'Stale only',
                    color: 'warning'
                });
            }

            return chips;
        },

        dashboardStatCards: function () {
            return [
                { key: 'pending', label: this.$t('pending') || 'Pending', color: 'grey' },
                { key: 'processing', label: this.$t('processing') || 'Processing', color: 'primary' },
                { key: 'held', label: this.$t('held') || 'Held', color: 'deep-orange' },
                { key: 'stale', label: this.$t('stale_jobs') || 'Stale', color: 'warning', value: this.stale_count }
            ];
        }
    },

    watch: {
        'pagination.page': function () {
            this.loadJobs();
        },
        'pagination.itemsPerPage': function () {
            this.pagination.page = 1;
            this.loadJobs();
        }
    },

    methods: {
        getHighlightUuid: function () {
            try {
                var params = new URLSearchParams(window.location.search);
                return params.get('highlight') || null;
            } catch (e) {
                return null;
            }
        },

        loadJobs: function () {
            var vm = this;
            vm.loading = true;
            vm.error_message = '';

            var params = new URLSearchParams();
            params.append('limit', vm.pagination.itemsPerPage);
            params.append('offset', vm.current_offset);

            if (vm.filters.job_type) {
                params.append('job_type', vm.filters.job_type);
            }
            if (vm.isActiveTab) {
                if (vm.filters.stale_only) {
                    params.append('stale', '1');
                } else if (vm.filters.status === 'pending' || vm.filters.status === 'held' || vm.filters.status === 'processing') {
                    params.append('status', vm.filters.status);
                } else {
                    params.append('active', '1');
                }
            } else if (vm.filters.status === 'completed' || vm.filters.status === 'failed' || vm.filters.status === 'cancelled') {
                params.append('status', vm.filters.status);
            } else {
                params.append('history', '1');
            }
            if (vm.isAdmin && vm.filters.user_id) {
                params.append('user_id', vm.filters.user_id);
            }

            fetch(vm.apiBase + '?' + params.toString(), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.status === 'success') {
                    vm.jobs = data.jobs || [];
                    vm.pagination.total = data.total != null ? data.total : vm.jobs.length;
                    if (data.stale_config) {
                        vm.stale_config = data.stale_config;
                    }
                } else {
                    vm.error_message = data.message || (vm.$t('error_loading_jobs') || 'Failed to load jobs');
                    vm.jobs = [];
                    vm.pagination.total = 0;
                }
            })
            .catch(function () {
                vm.error_message = vm.$t('error_loading_jobs') || 'Failed to load jobs';
                vm.jobs = [];
                vm.pagination.total = 0;
            })
            .finally(function () {
                vm.loading = false;
            });
        },

        loadJobTypes: function () {
            var vm = this;
            fetch(vm.apiBase + '/types', {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.status === 'success' && data.job_types) {
                    vm.job_types = data.job_types;
                }
            })
            .catch(function () {});
        },

        loadUsers: function () {
            var vm = this;
            fetch(CI.site_url + '/api/users', {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.status === 'success' && data.users) {
                    vm.users = data.users.map(function (user) {
                        return {
                            value: user.id,
                            text: user.username || user.email || ('User #' + user.id)
                        };
                    });
                }
            })
            .catch(function () {});
        },

        loadSummary: function () {
            var vm = this;
            vm.loading_summary = true;

            var requests = [
                fetch(vm.apiBase + '/status', {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (r) { return r.json(); })
            ];

            if (vm.isAdmin) {
                requests.push(
                    fetch(vm.apiBase + '/worker_status', {
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(function (r) { return r.json(); })
                );
            }

            Promise.all(requests)
            .then(function (results) {
                if (results[0].status === 'success') {
                    vm.queue_stats = results[0].queue || null;
                    vm.stale_count = (results[0].stale && results[0].stale.count != null)
                        ? results[0].stale.count
                        : 0;
                    if (results[0].stale_config) {
                        vm.stale_config = results[0].stale_config;
                    }
                }
                if (vm.isAdmin && results[1] && results[1].status === 'success' && results[1].worker) {
                    var worker = results[1].worker;
                    vm.worker_status = {
                        status: worker.is_running && worker.is_alive
                            ? 'running'
                            : (worker.is_running === false || worker.is_alive === false ? 'stopped' : 'unknown'),
                        raw: worker
                    };
                }
            })
            .catch(function () {})
            .finally(function () {
                vm.loading_summary = false;
            });
        },

        applyFilters: function () {
            this.pagination.page = 1;
            this.loadJobs();
        },

        clearFilters: function () {
            this.filters = {
                status: null,
                job_type: null,
                user_id: null,
                stale_only: null
            };
            this.pagination.page = 1;
            this.loadJobs();
        },

        removeFilterChip: function (key) {
            if (key === 'status') {
                this.filters.status = null;
            } else if (key === 'job_type') {
                this.filters.job_type = null;
            } else if (key === 'user_id') {
                this.filters.user_id = null;
            } else if (key === 'stale_only') {
                this.filters.stale_only = null;
            }
            this.applyFilters();
        },

        onStaleFilterChange: function () {
            if (this.filters.stale_only) {
                this.filters.status = null;
            }
            this.applyFilters();
        },

        onViewTabChange: function () {
            this.filters.status = null;
            this.filters.stale_only = null;
            this.clearSelection();
            this.pagination.page = 1;
            this.loadJobs();
            if (this.isActiveTab) {
                this.startPolling();
            } else {
                this.stopPolling();
            }
        },

        refresh: function () {
            this.loadSummary();
            this.loadJobs();
        },

        isDashboardStatActive: function (key) {
            if (!this.isActiveTab) {
                return false;
            }
            if (key === 'stale') {
                return this.filters.stale_only === true;
            }
            return this.filters.status === key;
        },

        dashboardStatCount: function (item) {
            if (item.value != null) {
                return item.value;
            }
            return (this.queue_stats && this.queue_stats[item.key]) || 0;
        },

        applyDashboardFilter: function (key) {
            var vm = this;
            var toggleOff = vm.isDashboardStatActive(key);
            var switchTab = vm.view_tab !== 0;

            if (switchTab) {
                vm.view_tab = 0;
            }

            var apply = function () {
                if (toggleOff && vm.view_tab === 0) {
                    vm.filters.status = null;
                    vm.filters.stale_only = null;
                } else if (key === 'stale') {
                    vm.filters.stale_only = true;
                    vm.filters.status = null;
                } else {
                    vm.filters.status = key;
                    vm.filters.stale_only = null;
                }
                vm.clearSelection();
                vm.applyFilters();
                vm.startPolling();
            };

            if (switchTab) {
                vm.$nextTick(apply);
            } else {
                apply();
            }
        },

        onRowClick: function (item, slot) {
            var event = (slot && slot.nativeEvent) || window.event;
            if (event && event.target && event.target.closest('.v-input--selection-controls, .v-data-table__checkbox, .v-simple-checkbox')) {
                return;
            }
            if (!item || !item.uuid) {
                return;
            }
            this.openJobDetail(item.uuid);
        },

        openJobDetail: function (uuid) {
            var vm = this;
            if (!uuid) {
                return;
            }
            vm.loading_detail = true;
            vm.detail_dialog = true;
            vm.selected_job = null;

            fetch(vm.apiBase + '/' + encodeURIComponent(uuid), {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.status === 'success') {
                    vm.selected_job = data.job;
                } else {
                    vm.error_message = data.message || (vm.$t('error_loading_job') || 'Failed to load job details');
                    vm.detail_dialog = false;
                }
            })
            .catch(function () {
                vm.error_message = vm.$t('error_loading_job') || 'Failed to load job details';
                vm.detail_dialog = false;
            })
            .finally(function () {
                vm.loading_detail = false;
            });
        },

        closeDetail: function () {
            this.detail_dialog = false;
            this.selected_job = null;
        },

        copyUuid: function (uuid) {
            if (!uuid || !navigator.clipboard) {
                return;
            }
            navigator.clipboard.writeText(uuid);
        },

        jobTypeLabel: function (jobType) {
            if (!jobType) {
                return '';
            }
            var match = this.job_types.find(function (item) {
                return item.job_type === jobType;
            });
            if (match && match.description) {
                return match.description;
            }
            return jobType.replace(/_/g, ' ');
        },

        statusColor: function (status) {
            var colors = {
                pending: 'grey',
                held: 'deep-orange',
                processing: 'primary',
                completed: 'success',
                failed: 'error',
                cancelled: 'blue-grey'
            };
            return colors[status] || 'grey';
        },

        staleChipColor: function (item) {
            if (!item || !item.is_stale) {
                return 'warning';
            }
            return item.stale_level === 'critical' ? 'error' : 'warning';
        },

        formatDate: function (value) {
            if (!value) {
                return '—';
            }
            if (typeof moment !== 'undefined') {
                return moment(value).format('YYYY-MM-DD HH:mm:ss');
            }
            return value;
        },

        formatJson: function (value) {
            if (value === null || value === undefined || value === '') {
                return '—';
            }
            if (typeof value === 'string') {
                return value;
            }
            try {
                return JSON.stringify(value, null, 2);
            } catch (e) {
                return String(value);
            }
        },

        userLabel: function (userId) {
            if (!userId) {
                return '—';
            }
            var match = this.users.find(function (u) {
                return String(u.value) === String(userId);
            });
            return match ? match.text : ('#' + userId);
        },

        payloadSummary: function (job) {
            if (!job || !job.payload) {
                return '';
            }
            var payload = job.payload;
            var parts = [];
            if (payload.project_id) {
                parts.push('project ' + payload.project_id);
            }
            if (payload.indicator_value) {
                parts.push('indicator ' + payload.indicator_value);
            }
            if (payload.file_id) {
                parts.push('file ' + payload.file_id);
            }
            return parts.join(', ');
        },

        startPolling: function () {
            var vm = this;
            vm.stopPolling();
            vm.poll_timer = setInterval(function () {
                if (vm.isActiveTab && vm.has_active_jobs) {
                    vm.loadJobs();
                    vm.loadSummary();
                }
            }, 8000);
        },

        stopPolling: function () {
            if (this.poll_timer) {
                clearInterval(this.poll_timer);
                this.poll_timer = null;
            }
        },

        postJson: function (url, body) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify(body || {})
            }).then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, status: response.status, data: data };
                });
            });
        },

        handleActionError: function (result, fallback) {
            var msg = fallback || 'Request failed';
            if (result && result.data && result.data.message) {
                msg = result.data.message;
            }
            this.error_message = msg;
            if (typeof EventBus !== 'undefined') {
                EventBus.$emit('alert', { message: msg });
            }
        },

        clearSelection: function () {
            this.selected = [];
        },

        confirmAction: function (message, onConfirm) {
            if (typeof EventBus === 'undefined') {
                if (window.confirm(message)) {
                    onConfirm(true);
                }
                return;
            }
            EventBus.$emit('confirm', {
                message: message,
                resolve: function (ok) {
                    if (ok) {
                        onConfirm(true);
                    }
                },
                reject: function () {}
            });
        },

        retryJob: function (uuid) {
            var vm = this;
            vm.action_loading = true;
            vm.error_message = '';
            vm.postJson(vm.apiBase + '/' + encodeURIComponent(uuid) + '/retry', {})
                .then(function (result) {
                    if (result.data && result.data.status === 'success') {
                        vm.loadJobs();
                        vm.loadSummary();
                        if (result.data.job) {
                            vm.selected_job = result.data.job;
                        }
                        if (typeof EventBus !== 'undefined') {
                            EventBus.$emit('alert', { message: vm.$t('job_retry_created') || 'Retry job created' });
                        }
                    } else {
                        vm.handleActionError(result, vm.$t('job_retry_failed') || 'Retry failed');
                    }
                })
                .catch(function () {
                    vm.handleActionError(null, vm.$t('job_retry_failed') || 'Retry failed');
                })
                .finally(function () {
                    vm.action_loading = false;
                });
        },

        cancelJob: function (uuid) {
            var vm = this;
            vm.action_loading = true;
            vm.error_message = '';
            vm.postJson(vm.apiBase + '/' + encodeURIComponent(uuid) + '/cancel', {})
                .then(function (result) {
                    if (result.data && result.data.status === 'success') {
                        vm.loadJobs();
                        vm.loadSummary();
                        if (result.data.job) {
                            vm.selected_job = result.data.job;
                        }
                        if (typeof EventBus !== 'undefined') {
                            EventBus.$emit('alert', { message: vm.$t('job_cancelled') || 'Job cancelled' });
                        }
                    } else {
                        vm.handleActionError(result, vm.$t('job_cancel_failed') || 'Cancel failed');
                    }
                })
                .catch(function () {
                    vm.handleActionError(null, vm.$t('job_cancel_failed') || 'Cancel failed');
                })
                .finally(function () {
                    vm.action_loading = false;
                });
        },

        deleteJob: function (uuid) {
            var vm = this;
            vm.action_loading = true;
            vm.error_message = '';
            vm.postJson(vm.apiBase + '/' + encodeURIComponent(uuid) + '/delete', {})
                .then(function (result) {
                    if (result.data && result.data.status === 'success') {
                        vm.detail_dialog = false;
                        vm.selected_job = null;
                        vm.loadJobs();
                        vm.loadSummary();
                        vm.clearSelection();
                        if (typeof EventBus !== 'undefined') {
                            EventBus.$emit('alert', { message: vm.$t('job_deleted') || 'Job deleted' });
                        }
                    } else {
                        vm.handleActionError(result, vm.$t('job_delete_failed') || 'Delete failed');
                    }
                })
                .catch(function () {
                    vm.handleActionError(null, vm.$t('job_delete_failed') || 'Delete failed');
                })
                .finally(function () {
                    vm.action_loading = false;
                });
        },

        confirmRetryJob: function (uuid) {
            var vm = this;
            vm.confirmAction(vm.$t('confirm_retry_job') || 'Retry this job?', function () {
                vm.retryJob(uuid);
            });
        },

        confirmCancelJob: function (uuid) {
            var vm = this;
            vm.confirmAction(vm.$t('confirm_cancel_job') || 'Cancel this pending job?', function () {
                vm.cancelJob(uuid);
            });
        },

        confirmDeleteJob: function (uuid) {
            var vm = this;
            vm.confirmAction(vm.$t('confirm_delete_job') || 'Delete this job permanently?', function () {
                vm.deleteJob(uuid);
            });
        },

        holdJob: function (uuid) {
            var vm = this;
            vm.action_loading = true;
            vm.error_message = '';
            vm.postJson(vm.apiBase + '/' + encodeURIComponent(uuid) + '/hold', {})
                .then(function (result) {
                    if (result.data && result.data.status === 'success') {
                        vm.loadJobs();
                        vm.loadSummary();
                        if (vm.detail_dialog && vm.selected_job) {
                            vm.openJobDetail(vm.selected_job.uuid);
                        }
                        if (typeof EventBus !== 'undefined') {
                            EventBus.$emit('alert', { message: vm.$t('job_held') || 'Job held' });
                        }
                    } else {
                        vm.handleActionError(result, vm.$t('job_hold_failed') || 'Hold failed');
                    }
                })
                .catch(function () {
                    vm.handleActionError(null, vm.$t('job_hold_failed') || 'Hold failed');
                })
                .finally(function () {
                    vm.action_loading = false;
                });
        },

        releaseJob: function (uuid) {
            var vm = this;
            vm.action_loading = true;
            vm.error_message = '';
            vm.postJson(vm.apiBase + '/' + encodeURIComponent(uuid) + '/release', {})
                .then(function (result) {
                    if (result.data && result.data.status === 'success') {
                        vm.loadJobs();
                        vm.loadSummary();
                        if (vm.detail_dialog && vm.selected_job) {
                            vm.openJobDetail(vm.selected_job.uuid);
                        }
                        if (typeof EventBus !== 'undefined') {
                            EventBus.$emit('alert', { message: vm.$t('job_released') || 'Job released' });
                        }
                    } else {
                        vm.handleActionError(result, vm.$t('job_release_failed') || 'Release failed');
                    }
                })
                .catch(function () {
                    vm.handleActionError(null, vm.$t('job_release_failed') || 'Release failed');
                })
                .finally(function () {
                    vm.action_loading = false;
                });
        },

        holdAllPending: function () {
            var vm = this;
            vm.confirmAction(vm.$t('confirm_hold_all_pending') || 'Hold all pending jobs? They will not run until released.', function () {
                vm.action_loading = true;
                vm.error_message = '';
                vm.postJson(vm.apiBase + '/hold_all', {})
                    .then(function (result) {
                        if (result.data && result.data.status === 'success') {
                            var n = result.data.held_count != null ? result.data.held_count : 0;
                            if (typeof EventBus !== 'undefined') {
                                EventBus.$emit('alert', {
                                    message: (vm.$t('jobs_held_count') || '{n} job(s) held').replace('{n}', n)
                                });
                            }
                            vm.loadJobs();
                            vm.loadSummary();
                        } else {
                            vm.handleActionError(result, vm.$t('job_hold_failed') || 'Hold failed');
                        }
                    })
                    .catch(function () {
                        vm.handleActionError(null, vm.$t('job_hold_failed') || 'Hold failed');
                    })
                    .finally(function () {
                        vm.action_loading = false;
                    });
            });
        },

        releaseAllHeld: function () {
            var vm = this;
            vm.confirmAction(vm.$t('confirm_release_all_held') || 'Release all held jobs back to the pending queue?', function () {
                vm.action_loading = true;
                vm.error_message = '';
                vm.postJson(vm.apiBase + '/release_all', {})
                    .then(function (result) {
                        if (result.data && result.data.status === 'success') {
                            var n = result.data.released_count != null ? result.data.released_count : 0;
                            if (typeof EventBus !== 'undefined') {
                                EventBus.$emit('alert', {
                                    message: (vm.$t('jobs_released_count') || '{n} job(s) released').replace('{n}', n)
                                });
                            }
                            vm.loadJobs();
                            vm.loadSummary();
                        } else {
                            vm.handleActionError(result, vm.$t('job_release_failed') || 'Release failed');
                        }
                    })
                    .catch(function () {
                        vm.handleActionError(null, vm.$t('job_release_failed') || 'Release failed');
                    })
                    .finally(function () {
                        vm.action_loading = false;
                    });
            });
        },

        confirmHoldJob: function (uuid) {
            var vm = this;
            vm.confirmAction(vm.$t('confirm_hold_job') || 'Hold this pending job?', function () {
                vm.holdJob(uuid);
            });
        },

        confirmReleaseJob: function (uuid) {
            var vm = this;
            vm.confirmAction(vm.$t('confirm_release_job') || 'Release this job back to the pending queue?', function () {
                vm.releaseJob(uuid);
            });
        },

        batchAction: function (action) {
            var vm = this;
            var uuids = vm.selected.map(function (j) { return j.uuid; }).filter(Boolean);
            if (!uuids.length) {
                return;
            }

            var confirmMsg = vm.$t('confirm_batch_' + action) || ('Proceed with ' + action + '?');
            vm.confirmAction(confirmMsg, function () {
                vm.action_loading = true;
                vm.error_message = '';
                vm.postJson(vm.apiBase + '/batch/' + action, { uuids: uuids })
                    .then(function (result) {
                        if (result.data && result.data.status === 'success') {
                            var n = result.data.succeeded_count != null ? result.data.succeeded_count : 0;
                            var skipped = result.data.skipped_count != null ? result.data.skipped_count : 0;
                            var msg = (vm.$t('batch_action_done') || '{n} succeeded, {s} skipped')
                                .replace('{n}', n)
                                .replace('{s}', skipped);
                            if (typeof EventBus !== 'undefined') {
                                EventBus.$emit('alert', { message: msg });
                            }
                            vm.clearSelection();
                            vm.loadJobs();
                            vm.loadSummary();
                            if (vm.detail_dialog && vm.selected_job) {
                                vm.openJobDetail(vm.selected_job.uuid);
                            }
                        } else {
                            vm.handleActionError(result, vm.$t('batch_action_failed') || 'Batch action failed');
                        }
                    })
                    .catch(function () {
                        vm.handleActionError(null, vm.$t('batch_action_failed') || 'Batch action failed');
                    })
                    .finally(function () {
                        vm.action_loading = false;
                    });
            });
        }
    },

    template: `
        <div class="jobs-page">
            <div class="row">
                <div class="sidebar col-md-3 col-sm-4">
                    <div class="mr-4 mt-5">
                        <v-expansion-panels v-model="filter_panel" multiple>
                            <v-expansion-panel>
                                <v-expansion-panel-header class="capitalize">
                                    {{ $t('status') || 'Status' }}
                                </v-expansion-panel-header>
                                <v-expansion-panel-content>
                                    <v-select
                                        v-model="filters.status"
                                        :items="status_options"
                                        item-text="text"
                                        item-value="value"
                                        label=""
                                        background-color="white"
                                        dense
                                        outlined
                                        hide-details
                                        clearable
                                        class="jobs-filter-select mt-1"
                                        @change="function() { if (filters.status) { filters.stale_only = null; } applyFilters(); }"
                                    ></v-select>
                                </v-expansion-panel-content>
                            </v-expansion-panel>

                            <v-expansion-panel>
                                <v-expansion-panel-header class="capitalize">
                                    {{ $t('job_type') || 'Job type' }}
                                </v-expansion-panel-header>
                                <v-expansion-panel-content>
                                    <v-select
                                        v-model="filters.job_type"
                                        :items="job_types"
                                        item-text="job_type"
                                        item-value="job_type"
                                        label=""
                                        background-color="white"
                                        dense
                                        outlined
                                        hide-details
                                        clearable
                                        class="jobs-filter-select mt-1"
                                        @change="applyFilters"
                                    >
                                        <template v-slot:selection="{ item }">
                                            {{ jobTypeLabel(item.job_type || item) }}
                                        </template>
                                        <template v-slot:item="{ item }">
                                            {{ jobTypeLabel(item.job_type) }}
                                        </template>
                                    </v-select>
                                </v-expansion-panel-content>
                            </v-expansion-panel>

                            <v-expansion-panel v-if="isAdmin">
                                <v-expansion-panel-header class="capitalize">
                                    {{ $t('user') || 'User' }}
                                </v-expansion-panel-header>
                                <v-expansion-panel-content>
                                    <v-select
                                        v-model="filters.user_id"
                                        :items="users"
                                        item-text="text"
                                        item-value="value"
                                        label=""
                                        background-color="white"
                                        dense
                                        outlined
                                        hide-details
                                        clearable
                                        class="jobs-filter-select mt-1"
                                        @change="applyFilters"
                                    ></v-select>
                                </v-expansion-panel-content>
                            </v-expansion-panel>

                            <v-expansion-panel v-if="isActiveTab">
                                <v-expansion-panel-header class="capitalize">
                                    {{ $t('stale') || 'Stale' }}
                                </v-expansion-panel-header>
                                <v-expansion-panel-content>
                                    <v-select
                                        v-model="filters.stale_only"
                                        :items="stale_filter_options"
                                        item-text="text"
                                        item-value="value"
                                        label=""
                                        background-color="white"
                                        dense
                                        outlined
                                        hide-details
                                        class="jobs-filter-select mt-1"
                                        @change="onStaleFilterChange"
                                    ></v-select>
                                </v-expansion-panel-content>
                            </v-expansion-panel>
                        </v-expansion-panels>

                        <v-btn
                            small
                            outlined
                            color="primary"
                            block
                            class="mt-3 jobs-clear-filters-btn"
                            :disabled="!hasActiveFilters"
                            @click="clearFilters"
                        >
                            <v-icon left small>mdi-filter-off-outline</v-icon>
                            {{ $t('clear_filters') || 'Clear filters' }}
                        </v-btn>
                    </div>
                </div>

                <div class="col-md-9 col-sm-8">
                    <div class="mt-5 mb-5">
                        <div class="d-flex">
                            <div class="flex-grow-1 flex-shrink-0 mr-auto">
                                <h3 class="mt-3">{{ pageTitle }}</h3>
                            </div>
                            <div>
                                <v-btn small outlined color="primary" :loading="loading" @click="refresh">
                                    <v-icon left small>mdi-refresh</v-icon>
                                    {{ $t('refresh') || 'Refresh' }}
                                </v-btn>
                            </div>
                        </div>
                    </div>

                    <v-alert
                        v-if="isAdmin && worker_banner"
                        dense
                        outlined
                        :type="worker_banner.color"
                        class="mb-4"
                    >
                        {{ worker_banner.text }}
                    </v-alert>

                    <v-row v-if="isAdmin && queue_stats" class="mb-2">
                        <v-col cols="12" class="d-flex flex-wrap" style="gap: 8px;">
                            <v-btn
                                v-if="can_hold_all_pending"
                                small
                                outlined
                                color="deep-orange"
                                :loading="action_loading"
                                @click="holdAllPending"
                            >
                                {{ $t('hold_all_pending') || 'Hold all pending' }}
                            </v-btn>
                            <v-btn
                                v-if="can_release_all_held"
                                small
                                outlined
                                color="primary"
                                :loading="action_loading"
                                @click="releaseAllHeld"
                            >
                                {{ $t('release_all_held') || 'Release all held' }}
                            </v-btn>
                        </v-col>
                    </v-row>

                    <v-row v-if="isAdmin && queue_stats" class="mb-4">
                        <v-col cols="6" sm="3" v-for="item in dashboardStatCards" :key="item.key">
                            <v-card
                                outlined
                                class="jobs-stat-card"
                                :class="{ 'jobs-stat-card--active': isDashboardStatActive(item.key) }"
                                @click="applyDashboardFilter(item.key)"
                            >
                                <v-card-text class="py-3 text-center">
                                    <div class="text-caption grey--text">{{ item.label }}</div>
                                    <div class="text-h5 font-weight-bold" :class="item.color + '--text'">
                                        {{ dashboardStatCount(item) }}
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-col>
                    </v-row>

                    <div
                        v-if="selected.length > 0"
                        class="mb-3 p-2 d-flex align-center flex-wrap grey lighten-4 rounded"
                        style="gap: 8px;"
                    >
                        <span class="text-body-2 font-weight-medium">{{ selected.length }} {{ $t('selected') || 'selected' }}</span>
                        <v-spacer></v-spacer>
                        <v-btn
                            v-if="can_batch_hold"
                            small
                            outlined
                            color="deep-orange"
                            :loading="action_loading"
                            @click="batchAction('hold')"
                        >
                            {{ $t('hold_selected') || 'Hold pending' }}
                        </v-btn>
                        <v-btn
                            v-if="can_batch_release"
                            small
                            outlined
                            color="primary"
                            :loading="action_loading"
                            @click="batchAction('release')"
                        >
                            {{ $t('release_selected') || 'Release held' }}
                        </v-btn>
                        <v-btn
                            v-if="can_batch_cancel"
                            small
                            outlined
                            color="warning"
                            :loading="action_loading"
                            @click="batchAction('cancel')"
                        >
                            {{ $t('cancel_selected') || 'Cancel pending' }}
                        </v-btn>
                        <v-btn
                            v-if="can_batch_retry"
                            small
                            outlined
                            color="primary"
                            :loading="action_loading"
                            @click="batchAction('retry')"
                        >
                            {{ $t('retry_selected') || 'Retry failed' }}
                        </v-btn>
                        <v-btn
                            v-if="can_batch_delete"
                            small
                            outlined
                            color="error"
                            :loading="action_loading"
                            @click="batchAction('delete')"
                        >
                            {{ $t('delete_selected') || 'Delete' }}
                        </v-btn>
                        <v-btn small text @click="clearSelection">{{ $t('clear') || 'Clear' }}</v-btn>
                    </div>

                    <div v-if="hasActiveFilters" class="mt-3 mb-3">
                        <v-chip
                            v-for="chip in activeFilterChips"
                            :key="chip.key"
                            small
                            close
                            :color="chip.color"
                            class="mr-1 mb-1"
                            @click:close="removeFilterChip(chip.key)"
                        >
                            {{ chip.label }}
                        </v-chip>
                    </div>

                    <div class="mt-5 p-3 border text-danger" v-if="error_message">
                        <div><strong>{{ $t('error') || 'Error' }}:</strong> {{ error_message }}</div>
                    </div>

                    <div class="bg-white shadow rounded p-3 pt-1 mt-2">
                        <v-tabs v-model="view_tab" class="mb-2" @change="onViewTabChange">
                            <v-tab>{{ $t('jobs_tab_active') || 'Active' }}</v-tab>
                            <v-tab>{{ $t('jobs_tab_history') || 'History' }}</v-tab>
                        </v-tabs>

                        <div v-if="loading && jobs.length === 0" class="mt-3 mb-3 p-3 text-center">
                            <v-progress-circular indeterminate color="primary" class="mr-2"></v-progress-circular>
                            <span>{{ $t('loading') || 'Loading...' }}</span>
                        </div>

                        <div
                            v-else-if="!loading && jobs.length === 0"
                            class="mt-5 mb-3 p-3 border text-center text-muted"
                        >
                            {{ emptyJobsMessage }}
                        </div>

                        <v-data-table
                            v-else
                            v-model="selected"
                            :headers="table_headers"
                            :items="jobs"
                            :server-items-length="pagination.total"
                            :page.sync="pagination.page"
                            :items-per-page.sync="pagination.itemsPerPage"
                            :loading="loading || action_loading"
                            :footer-props="{ 'items-per-page-options': [10, 25, 50, 100] }"
                            item-key="uuid"
                            show-select
                            dense
                            class="jobs-table table-jobs elevation-0"
                            @click:row="onRowClick"
                        >
                        <template v-slot:item.status="{ item }">
                            <div class="d-flex align-center flex-wrap" style="gap: 4px;">
                                <v-chip x-small :color="statusColor(item.status)" dark>{{ item.status }}</v-chip>
                                <v-chip
                                    v-if="item.is_stale"
                                    x-small
                                    outlined
                                    :color="staleChipColor(item)"
                                >
                                    {{ $t('stale') || 'Stale' }}
                                </v-chip>
                            </div>
                        </template>
                        <template v-slot:item.job_type="{ item }">
                            {{ jobTypeLabel(item.job_type) }}
                        </template>
                        <template v-slot:item.uuid="{ item }">
                            <span class="jobs-cell-uuid" :title="item.uuid">
                                {{ item.uuid }}
                            </span>
                        </template>
                        <template v-slot:item.user_id="{ item }">
                            {{ userLabel(item.user_id) }}
                        </template>
                        <template v-slot:item.created_at="{ item }">
                            {{ formatDate(item.created_at) }}
                        </template>
                        <template v-slot:item.attempts="{ item }">
                            {{ item.attempts }} / {{ item.max_attempts }}
                        </template>
                        <template v-slot:item.actions="{ item }">
                            <v-btn icon x-small @click.stop="openJobDetail(item.uuid)">
                                <v-icon small>mdi-eye</v-icon>
                            </v-btn>
                        </template>
                        </v-data-table>
                    </div>
                </div>
            </div>

            <v-dialog v-model="detail_dialog" max-width="900" scrollable @input="function(v) { if (!v) closeDetail(); }">
                <v-card>
                    <v-card-title class="d-flex align-center justify-space-between">
                        <span>{{ $t('job_details') || 'Job details' }}</span>
                        <v-btn icon small @click="closeDetail"><v-icon>mdi-close</v-icon></v-btn>
                    </v-card-title>
                    <v-divider></v-divider>
                    <v-card-text style="max-height: 70vh;">
                        <div v-if="loading_detail" class="text-center py-8">
                            <v-progress-circular indeterminate color="primary"></v-progress-circular>
                        </div>
                        <div v-else-if="selected_job">
                            <v-row dense class="mb-3">
                                <v-col cols="12" sm="6">
                                    <div class="text-caption grey--text">UUID</div>
                                    <div class="d-flex align-center">
                                        <code class="mr-2">{{ selected_job.uuid }}</code>
                                        <v-btn x-small icon @click="copyUuid(selected_job.uuid)">
                                            <v-icon x-small>mdi-content-copy</v-icon>
                                        </v-btn>
                                    </div>
                                </v-col>
                                <v-col cols="6" sm="3">
                                    <div class="text-caption grey--text">{{ $t('status') || 'Status' }}</div>
                                    <v-chip x-small :color="statusColor(selected_job.status)" dark>{{ selected_job.status }}</v-chip>
                                </v-col>
                                <v-col cols="6" sm="3">
                                    <div class="text-caption grey--text">{{ $t('type') || 'Type' }}</div>
                                    <div>{{ jobTypeLabel(selected_job.job_type) }}</div>
                                </v-col>
                                <v-col cols="6" sm="3">
                                    <div class="text-caption grey--text">{{ $t('created') || 'Created' }}</div>
                                    <div>{{ formatDate(selected_job.created_at) }}</div>
                                </v-col>
                                <v-col cols="6" sm="3">
                                    <div class="text-caption grey--text">{{ $t('started') || 'Started' }}</div>
                                    <div>{{ formatDate(selected_job.started_at) }}</div>
                                </v-col>
                                <v-col cols="6" sm="3">
                                    <div class="text-caption grey--text">{{ $t('completed') || 'Completed' }}</div>
                                    <div>{{ formatDate(selected_job.completed_at) }}</div>
                                </v-col>
                                <v-col cols="6" sm="3">
                                    <div class="text-caption grey--text">{{ $t('attempts') || 'Attempts' }}</div>
                                    <div>{{ selected_job.attempts }} / {{ selected_job.max_attempts }}</div>
                                </v-col>
                            </v-row>

                            <v-alert v-if="selected_job.error_message" type="error" dense outlined class="mb-3">
                                {{ selected_job.error_message }}
                            </v-alert>

                            <v-alert
                                v-if="selected_job.is_stale"
                                :type="selected_job.stale_level === 'critical' ? 'error' : 'warning'"
                                dense
                                outlined
                                class="mb-3"
                            >
                                {{ selected_job.stale_reason || ($t('stale') || 'Stale') }}
                            </v-alert>

                            <div class="mb-3">
                                <div class="text-subtitle-2 mb-1">Payload</div>
                                <pre class="jobs-json-block">{{ formatJson(selected_job.payload) }}</pre>
                            </div>

                            <div v-if="selected_job.result">
                                <div class="text-subtitle-2 mb-1">Result</div>
                                <pre class="jobs-json-block">{{ formatJson(selected_job.result) }}</pre>
                            </div>
                        </div>
                    </v-card-text>
                    <v-divider v-if="selected_job && !loading_detail"></v-divider>
                    <v-card-actions v-if="selected_job && !loading_detail" class="pa-3">
                        <v-btn
                            v-if="can_hold_selected_job"
                            small
                            color="deep-orange"
                            outlined
                            :loading="action_loading"
                            @click="confirmHoldJob(selected_job.uuid)"
                        >
                            {{ $t('hold_job') || 'Hold' }}
                        </v-btn>
                        <v-btn
                            v-if="can_release_selected_job"
                            small
                            color="primary"
                            outlined
                            :loading="action_loading"
                            @click="confirmReleaseJob(selected_job.uuid)"
                        >
                            {{ $t('release_job') || 'Release' }}
                        </v-btn>
                        <v-btn
                            v-if="can_retry_selected_job"
                            small
                            color="primary"
                            outlined
                            :loading="action_loading"
                            @click="confirmRetryJob(selected_job.uuid)"
                        >
                            {{ $t('retry_job') || 'Retry' }}
                        </v-btn>
                        <v-btn
                            v-if="can_cancel_selected_job"
                            small
                            color="warning"
                            outlined
                            :loading="action_loading"
                            @click="confirmCancelJob(selected_job.uuid)"
                        >
                            {{ $t('cancel_job') || 'Cancel' }}
                        </v-btn>
                        <v-btn
                            v-if="can_delete_selected_job"
                            small
                            color="error"
                            outlined
                            :loading="action_loading"
                            @click="confirmDeleteJob(selected_job.uuid)"
                        >
                            {{ $t('delete_job') || 'Delete' }}
                        </v-btn>
                        <v-spacer></v-spacer>
                        <v-btn small text @click="closeDetail">{{ $t('close') || 'Close' }}</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </div>
    `
});
