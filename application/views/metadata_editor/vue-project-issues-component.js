/**
 * Project Issues Component
 * 
 * Main component for managing project issues
 * Integrates issue list, detail dialog, and create dialog
 * 
 * Props:
 *   - projectId: Number - Project ID (required)
 *   - canEdit: Boolean - Whether user has edit permission (default: false)
 */
Vue.component('project-issues', {
    props: {
        projectId: {
            type: Number,
            required: true
        },
        canEdit: {
            type: Boolean,
            default: false
        }
    },
    data() {
        return {
            issueListKey: 0, // For forcing list refresh
            activeTab: 0,
            assessmentSubmitting: false,
            assessmentPhase: 'idle', // 'idle' | 'submitting' | 'running'
            assessmentJobUuid: null,
            assessmentJobStatus: null,
            assessmentJobError: null,
            assessmentWorkerStatus: null,
            workerStatus: null,
            fastapiOnline: null,
            readinessLoading: false,
            assessmentPollIntervalMs: 5000,
            assessmentPollMaxWaitMs: 600000, // 10 minutes
            showAssessConfirm: false,
            showAssessmentStatusDialog: false,
            assessmentStatusLoading: false,
            assessmentCancelLoading: false,
            assessmentLastCheckedAt: null,
            assessmentStatusTimer: null,
            assessmentUsage: null
        };
    },
    computed: {
        isAdmin: function () {
            return !!(CI && CI.user_info && CI.user_info.is_admin);
        },
        metadataAssessmentEnabled: function () {
            return !!(CI && CI.user_info && CI.user_info.metadata_assessment_enabled);
        },
        hasActiveAssessmentJob: function () {
            return !!(this.assessmentJobUuid && this.isRunningStatus(this.assessmentJobStatus));
        },
        showAssessButton: function () {
            if (!this.canEdit) {
                return false;
            }
            if (this.hasActiveAssessmentJob) {
                return true;
            }
            return this.metadataAssessmentEnabled;
        },
        workerIsRunning: function () {
            return this.workerStatus === 'running';
        },
        fastApiIsOnline: function () {
            return this.fastapiOnline === true;
        },
        canStartNewAssessment: function () {
            if (!this.metadataAssessmentEnabled
                || !this.workerIsRunning
                || !this.fastApiIsOnline
                || this.assessmentSubmitting) {
                return false;
            }
            if (this.assessmentUsage && !this.assessmentUsage.unlimited && this.assessmentUsage.limit > 0) {
                return this.assessmentUsage.remaining_this_month > 0;
            }
            return true;
        },
        assessmentIsQueued: function () {
            return this.assessmentPhase === 'running'
                && this.assessmentJobStatus === 'pending'
                && this.assessmentWorkerStatus
                && this.assessmentWorkerStatus !== 'running';
        },
        assessmentButtonLabel: function () {
            if (this.assessmentSubmitting && this.assessmentPhase === 'running') {
                if (this.assessmentIsQueued) {
                    return this.$t('assessment_queued_view_status') || 'Assessment queued - View status';
                }
                return this.$t('assessment_running_view_status') || 'Assessment running - View status';
            }
            if (this.assessmentSubmitting && this.assessmentPhase === 'submitting') {
                return 'Submitting…';
            }
            return this.$t('assess_metadata') || 'Assess metadata';
        },
        assessButtonTooltip: function () {
            if (this.assessmentSubmitting && this.assessmentPhase === 'running') {
                return 'Click to view live status and cancel the job';
            }
            if (!this.workerIsRunning) {
                return this.$t('assessment_worker_offline') || 'Background worker is not running. Start the worker before running an assessment.';
            }
            if (!this.fastApiIsOnline) {
                return this.$t('assessment_fastapi_offline') || 'Assessment service is unavailable. Ensure the FastAPI backend is running.';
            }
            if (this.assessmentUsage && !this.assessmentUsage.unlimited && this.assessmentUsage.limit > 0 && this.assessmentUsage.remaining_this_month <= 0) {
                return this.$t('assessment_monthly_limit_reached') || 'Monthly assessment limit reached for this site.';
            }
            return 'Run metadata assessment';
        },
        assessConfirmWarnings: function () {
            var warnings = [];
            if (!this.fastApiIsOnline) {
                warnings.push(this.$t('assessment_fastapi_offline') || 'Assessment service is unavailable. Ensure the FastAPI backend is running.');
            }
            if (!this.workerIsRunning) {
                warnings.push(this.$t('assessment_worker_offline') || 'Background worker is not running. Start the worker before running an assessment.');
            }
            if (this.assessmentUsage && !this.assessmentUsage.unlimited && this.assessmentUsage.limit > 0) {
                warnings.push(
                    (this.$t('assessment_monthly_usage') || 'Site usage this month: {used} of {limit}.')
                        .replace('{used}', this.assessmentUsage.used_this_month)
                        .replace('{limit}', this.assessmentUsage.limit)
                );
                if (this.assessmentUsage.remaining_this_month <= 0) {
                    warnings.push(this.$t('assessment_monthly_limit_reached') || 'Monthly assessment limit reached for this site.');
                }
            }
            return warnings;
        }
    },
    mounted() {
        if (this.canEdit && this.metadataAssessmentEnabled) {
            this.loadAssessmentReadiness();
        }
        this.checkAssessmentStatus();
    },
    beforeDestroy() {
        this.stopAssessmentStatusAutoRefresh();
    },
    watch: {
        showAssessmentStatusDialog(isOpen) {
            if (isOpen) {
                this.startAssessmentStatusAutoRefresh();
            } else {
                this.stopAssessmentStatusAutoRefresh();
            }
        },
        showAssessConfirm(isOpen) {
            if (isOpen) {
                this.loadAssessmentReadiness();
            }
        }
    },
    methods: {
        isRunningStatus(status) {
            return status === 'pending' || status === 'processing';
        },
        async loadAssessmentReadiness() {
            if (!this.canEdit || !this.metadataAssessmentEnabled) {
                return;
            }
            this.readinessLoading = true;
            var baseUrl = CI && CI.base_url ? CI.base_url : '';
            try {
                var response = await axios.get(baseUrl + '/api/jobs/assessment_readiness').catch(function () { return null; });
                if (response && response.data && response.data.status === 'success') {
                    this.workerStatus = response.data.worker_running ? 'running' : 'stopped';
                    this.fastapiOnline = response.data.fastapi_online === true;
                    this.assessmentUsage = response.data.usage || null;
                } else {
                    this.workerStatus = 'unknown';
                    this.fastapiOnline = false;
                    this.assessmentUsage = null;
                }
            } catch (err) {
                console.error('Load assessment readiness:', err);
                this.workerStatus = this.workerStatus || 'unknown';
                this.fastapiOnline = false;
                this.assessmentUsage = null;
            } finally {
                this.readinessLoading = false;
            }
        },
        async checkAssessmentStatus() {
            if (!this.projectId) return;
            try {
                const url = (CI && CI.base_url ? CI.base_url : '') + '/api/issues/project/' + this.projectId + '/assessment_status';
                const response = await axios.get(url);
                if (response.data.status !== 'success' || !response.data.assessment_job) return;
                const job = response.data.assessment_job;
                if (!this.isRunningStatus(job.status)) return;
                this.assessmentJobUuid = job.uuid;
                this.assessmentJobStatus = job.status;
                this.assessmentJobError = job.error_message || null;
                this.assessmentSubmitting = true;
                this.assessmentPhase = 'running';
                const result = await this.pollJobUntilDone(job.uuid);
                if (result.status === 'completed' && result.job && result.job.result) {
                    console.log('Metadata assessment result:', result.job.result);
                    if (result.job.result.issues && result.job.result.issues.length) {
                        console.log('Issues from assessment:', result.job.result.issues);
                    }
                    EventBus.$emit('onSuccess', 'Assessment complete. Result logged to console and log file.');
                    this.refreshIssueList();
                    this.assessmentSubmitting = false;
                    this.assessmentPhase = 'idle';
                    this.assessmentJobStatus = 'completed';
                    this.assessmentJobError = null;
                    this.assessmentWorkerStatus = result.workerStatus || this.assessmentWorkerStatus;
                } else if (result.status === 'failed') {
                    const msg = (result.job && result.job.error_message) ? result.job.error_message : 'Assessment failed';
                    EventBus.$emit('onFail', msg);
                    this.assessmentSubmitting = false;
                    this.assessmentPhase = 'idle';
                    this.assessmentJobStatus = 'failed';
                    this.assessmentJobError = msg;
                    this.assessmentWorkerStatus = result.workerStatus || this.assessmentWorkerStatus;
                } else if (result.status === 'timeout') {
                    EventBus.$emit('onFail', 'Assessment is taking longer than expected. Check job status later.');
                    this.assessmentSubmitting = true;
                    this.assessmentPhase = 'running';
                }
            } catch (err) {
                console.error('Check assessment status:', err);
            }
        },
        createIssue() {
            this.$router.push('/issues/create');
        },
        refreshIssueList() {
            // Force re-render of issue list by changing key
            this.issueListKey++;
            // Refresh Vuex open-issues summary so field-level badges/indicators update without page reload
            if (this.$store && this.projectId) {
                this.$store.dispatch('fetchOpenIssuesSummary', { projectId: this.projectId });
            }
            // Notify field-issues components to drop local cache so they use fresh store summary
            if (typeof EventBus !== 'undefined') {
                EventBus.$emit('project-issues-refreshed', this.projectId);
            }
        },
        openAssessConfirm() {
            if (this.assessmentSubmitting && this.assessmentPhase === 'running') {
                this.openAssessmentStatusDialog();
                return;
            }
            this.showAssessConfirm = true;
        },
        onAssessButtonClick() {
            if (this.assessmentSubmitting && this.assessmentPhase === 'running') {
                this.openAssessmentStatusDialog();
                return;
            }
            this.openAssessConfirm();
        },
        async submitForReview() {
            await this.loadAssessmentReadiness();
            if (!this.canStartNewAssessment) {
                EventBus.$emit(
                    'onFail',
                    this.$t('assessment_submit_blocked') || 'Assessment cannot be started until all required services are available.'
                );
                return;
            }
            this.showAssessConfirm = false;
            if (this.assessmentSubmitting) return;
            this.assessmentSubmitting = true;
            this.assessmentPhase = 'submitting';
            this.assessmentJobError = null;
            try {
                const url = (CI && CI.base_url ? CI.base_url : '') + '/api/jobs/metadata_assessment';
                const response = await axios.post(url, { project_id: this.projectId });
                if (response.data.status !== 'success' || !response.data.uuid) {
                    throw new Error(response.data.message || 'Failed to submit for assessment');
                }
                const uuid = response.data.uuid;
                this.assessmentJobUuid = uuid;
                this.assessmentJobStatus = 'pending';
                this.assessmentPhase = 'running';
                const result = await this.pollJobUntilDone(uuid);
                if (result.status === 'completed' && result.job && result.job.result) {
                    console.log('Metadata assessment result:', result.job.result);
                    if (result.job.result.issues && result.job.result.issues.length) {
                        console.log('Issues from assessment:', result.job.result.issues);
                    }
                    EventBus.$emit('onSuccess', 'Assessment complete. Result logged to console and log file.');
                    this.refreshIssueList();
                    this.assessmentSubmitting = false;
                    this.assessmentPhase = 'idle';
                    this.assessmentJobStatus = 'completed';
                    this.assessmentJobError = null;
                } else if (result.status === 'failed') {
                    const msg = (result.job && result.job.error_message) ? result.job.error_message : 'Assessment failed';
                    EventBus.$emit('onFail', msg);
                    this.assessmentSubmitting = false;
                    this.assessmentPhase = 'idle';
                    this.assessmentJobStatus = 'failed';
                    this.assessmentJobError = msg;
                } else if (result.status === 'timeout') {
                    EventBus.$emit('onFail', 'Assessment is taking longer than expected. Check job status later.');
                    this.assessmentSubmitting = true;
                    this.assessmentPhase = 'running';
                }
            } catch (error) {
                console.error('Assess metadata error:', error);
                EventBus.$emit(
                    'onFail',
                    error.response && error.response.data && error.response.data.message
                        ? error.response.data.message
                        : (error.message || 'Failed to assess metadata')
                );
                this.assessmentSubmitting = false;
                this.assessmentPhase = 'idle';
                this.assessmentJobStatus = 'failed';
                this.assessmentJobError = error && error.message ? error.message : 'Failed to assess metadata';
            }
        },
        pollJobUntilDone(uuid) {
            const start = Date.now();
            const poll = () => {
                if (Date.now() - start > this.assessmentPollMaxWaitMs) {
                    return Promise.resolve({ status: 'timeout' });
                }
                const url = (CI && CI.base_url ? CI.base_url : '') + '/api/jobs/' + uuid;
                return axios.get(url).then((response) => {
                    const job = response.data.job;
                    const status = job ? job.status : response.data.status;
                    const workerStatus = response.data.worker_status && response.data.worker_status.status
                        ? response.data.worker_status.status
                        : null;
                    this.assessmentJobStatus = status;
                    this.assessmentWorkerStatus = workerStatus;
                    this.assessmentLastCheckedAt = new Date().toISOString();
                    this.assessmentJobError = job && job.error_message ? job.error_message : null;
                    if (status === 'completed' || status === 'failed') {
                        return { status: status, job: job, workerStatus: workerStatus };
                    }
                    return new Promise((resolve) => {
                        setTimeout(() => poll().then(resolve), this.assessmentPollIntervalMs);
                    });
                });
            };
            return poll();
        },
        async refreshAssessmentStatus() {
            if (!this.assessmentJobUuid) return;
            if (this.assessmentStatusLoading) return;
            this.assessmentStatusLoading = true;
            try {
                const url = (CI && CI.base_url ? CI.base_url : '') + '/api/jobs/' + this.assessmentJobUuid;
                const response = await axios.get(url);
                const job = response.data.job || {};
                const status = job.status || response.data.status || this.assessmentJobStatus;
                const workerStatus = response.data.worker_status && response.data.worker_status.status
                    ? response.data.worker_status.status
                    : null;
                this.assessmentJobStatus = status;
                this.assessmentWorkerStatus = workerStatus;
                this.assessmentJobError = job.error_message || null;
                this.assessmentLastCheckedAt = new Date().toISOString();

                if (status === 'completed' || status === 'failed' || status === 'cancelled') {
                    this.assessmentSubmitting = false;
                    this.assessmentPhase = 'idle';
                } else if (this.isRunningStatus(status)) {
                    this.assessmentSubmitting = true;
                    this.assessmentPhase = 'running';
                }
            } catch (error) {
                console.error('Refresh assessment status failed:', error);
                EventBus.$emit('onFail', 'Failed to refresh assessment status');
            } finally {
                this.assessmentStatusLoading = false;
            }
        },
        async openAssessmentStatusDialog() {
            if (!this.assessmentJobUuid) return;
            this.showAssessmentStatusDialog = true;
            await this.refreshAssessmentStatus();
        },
        startAssessmentStatusAutoRefresh() {
            this.stopAssessmentStatusAutoRefresh();
            this.assessmentStatusTimer = setInterval(() => {
                if (!this.showAssessmentStatusDialog || !this.assessmentJobUuid) {
                    return;
                }
                this.refreshAssessmentStatus();
            }, this.assessmentPollIntervalMs);
        },
        stopAssessmentStatusAutoRefresh() {
            if (this.assessmentStatusTimer) {
                clearInterval(this.assessmentStatusTimer);
                this.assessmentStatusTimer = null;
            }
        },
        async cancelAssessmentJob() {
            if (!this.assessmentJobUuid || this.assessmentCancelLoading) return;
            this.assessmentCancelLoading = true;
            try {
                const url = (CI && CI.base_url ? CI.base_url : '') + '/api/jobs/' + this.assessmentJobUuid + '/cancel';
                const response = await axios.post(url, {});
                if (response.data.status !== 'success') {
                    throw new Error(response.data.message || 'Failed to cancel assessment job');
                }
                this.assessmentSubmitting = false;
                this.assessmentPhase = 'idle';
                this.assessmentJobStatus = 'cancelled';
                this.assessmentJobError = null;
                this.assessmentWorkerStatus = this.assessmentWorkerStatus || null;
                this.showAssessmentStatusDialog = false;
                EventBus.$emit('onSuccess', 'Assessment job cancelled');
            } catch (error) {
                console.error('Cancel assessment job failed:', error);
                EventBus.$emit(
                    'onFail',
                    error.response && error.response.data && error.response.data.message
                        ? error.response.data.message
                        : (error.message || 'Failed to cancel assessment job')
                );
            } finally {
                this.assessmentCancelLoading = false;
            }
        }
    },
    template: `
        <div class="project-issues">
            <div class="m-4">
                <v-row>
                    <v-col cols="12">
                        <div class="d-flex align-center mb-4">
                            <div>
                                <h2 class="text-h5">
                                    <v-icon left color="primary">mdi-alert-circle-outline</v-icon>
                                    Issues
                                </h2>                                
                            </div>
                            <v-spacer></v-spacer>
                            <v-tooltip v-if="showAssessButton" bottom>
                                <template v-slot:activator="{ on, attrs }">
                                    <v-btn
                                        color="primary"
                                        small
                                        :loading="(assessmentSubmitting && assessmentPhase === 'submitting') || readinessLoading"
                                        :disabled="!hasActiveAssessmentJob && (readinessLoading || !canStartNewAssessment)"
                                        @click="onAssessButtonClick"
                                        class="mr-2"
                                        v-bind="attrs"
                                        v-on="on"
                                    >
                                        <v-progress-circular
                                            v-if="assessmentSubmitting && assessmentPhase === 'running'"
                                            indeterminate
                                            size="20"
                                            width="2"
                                            class="mr-2"
                                        ></v-progress-circular>
                                        <v-icon v-else left>mdi-auto-fix</v-icon>
                                        {{ assessmentButtonLabel }}
                                        <v-icon v-if="assessmentSubmitting && assessmentPhase === 'running'" right small>mdi-chevron-right</v-icon>
                                    </v-btn>
                                </template>
                                <span>{{ assessButtonTooltip }}</span>
                            </v-tooltip>
                            <v-btn
                                v-if="canEdit"
                                color="primary"
                                small
                                @click="createIssue"
                            >
                                <v-icon left>mdi-plus</v-icon>
                                New Issue
                            </v-btn>
                        </div>

                        <!-- Issue Tabs -->
                        <v-tabs v-model="activeTab" class="mb-2">
                            <v-tab>Open</v-tab>
                            <v-tab>Closed</v-tab>
                        </v-tabs>

                        <v-tabs-items v-model="activeTab">
                            <v-tab-item>
                                <issue-list
                                    :key="'open-' + issueListKey"
                                    :project-id="projectId"
                                    :can-edit="canEdit"
                                    status-scope="open"
                                ></issue-list>
                            </v-tab-item>
                            <v-tab-item>
                                <issue-list
                                    :key="'closed-' + issueListKey"
                                    :project-id="projectId"
                                    :can-edit="canEdit"
                                    status-scope="closed"
                                ></issue-list>
                            </v-tab-item>
                        </v-tabs-items>
                    </v-col>
                </v-row>
            </div>

            <!-- Assess metadata confirm dialog -->
            <v-dialog v-model="showAssessConfirm" max-width="480" persistent>
                <v-card>
                    <v-card-title class="text-h6">
                        <v-icon left color="primary">mdi-auto-fix</v-icon>
                        Assess metadata
                    </v-card-title>
                    <v-card-text class="pt-2">
                        This will send the project metadata to the quality assessment service. Detected issues will be added to the Issues list and shown next to the relevant fields.
                        <p class="mt-3 mb-0">You do not have to wait for the assessment to finish. You can leave this page and come back later; the issues will appear when the assessment completes.</p>
                        <v-alert
                            v-for="(warning, idx) in assessConfirmWarnings"
                            :key="'assess-warn-' + idx"
                            type="warning"
                            dense
                            outlined
                            class="mt-3 mb-0"
                        >
                            {{ warning }}
                        </v-alert>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn text @click="showAssessConfirm = false">Cancel</v-btn>
                        <v-btn
                            color="primary"
                            :disabled="!canStartNewAssessment || readinessLoading"
                            :loading="readinessLoading"
                            @click="submitForReview"
                        >
                            Assess metadata
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <v-dialog v-model="showAssessmentStatusDialog" max-width="600">
                <v-card>
                    <v-card-title class="text-h6">
                        <v-icon left color="primary">mdi-progress-clock</v-icon>
                        Assessment status
                    </v-card-title>
                    <v-card-text>
                        <div><strong>Job UUID:</strong> {{ assessmentJobUuid || 'N/A' }}</div>
                        <div class="mt-2"><strong>Status:</strong> {{ assessmentJobStatus || 'unknown' }}</div>
                        <div class="mt-2"><strong>Worker:</strong> {{ assessmentWorkerStatus || 'unknown' }}</div>
                        <v-alert
                            v-if="isRunningStatus(assessmentJobStatus) && assessmentWorkerStatus && assessmentWorkerStatus !== 'running'"
                            type="warning"
                            dense
                            outlined
                            class="mt-3 mb-0"
                        >
                            {{ $t('assessment_worker_warning') || 'Worker is not running. The assessment job may not progress until the worker starts.' }}
                        </v-alert>
                        <div v-if="assessmentLastCheckedAt" class="mt-2"><strong>Last checked:</strong> {{ assessmentLastCheckedAt }}</div>
                        <div v-if="assessmentJobError" class="mt-3 red--text text--darken-2">
                            <strong>Error:</strong> {{ assessmentJobError }}
                        </div>
                    </v-card-text>
                    <v-card-actions>
                        <span class="text-caption grey--text">
                            Auto-refreshing every {{ Math.round(assessmentPollIntervalMs / 1000) }}s
                        </span>
                        <v-spacer></v-spacer>
                        <v-btn
                            v-if="isRunningStatus(assessmentJobStatus)"
                            color="error"
                            dark
                            depressed
                            :loading="assessmentCancelLoading"
                            @click="cancelAssessmentJob"
                        >
                            {{ $t('assessment_cancel') || 'Cancel job' }}
                        </v-btn>
                        <v-btn text @click="showAssessmentStatusDialog = false">Close</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </div>
    `
});
