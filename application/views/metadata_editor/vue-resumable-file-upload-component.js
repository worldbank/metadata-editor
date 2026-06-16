/**
 * Resumable File Upload Component
 * 
 * Handles chunked file uploads with progress tracking and resume capability
 * 
 * Props:
 * - projectId: Project ID (required)
 * - fileType: File type ('documentation' | 'data') - default: 'documentation'
 * - maxFileSize: Maximum file size in bytes (optional, uses server limit if not provided)
 * - allowedTypes: Comma-separated list of allowed file extensions (optional)
 * - disabled: Boolean to disable the upload component
 * - maxRetries: Maximum number of retry attempts per chunk (default: 3)
 * - retryDelay: Base delay between retries in milliseconds (default: 1000)
 * - exponentialBackoff: Use exponential backoff for retry delays (default: true)
 * 
 * Events:
 * - @upload-complete: Emitted when upload completes successfully
 *   - payload: { upload_id, filename, file_path, file_size }
 * - @upload-error: Emitted when upload fails
 *   - payload: { error, message }
 * - @upload-progress: Emitted during upload with progress updates
 *   - payload: { progress, uploaded_chunks, total_chunks, status }
 * - @upload-cancelled: Emitted when upload is cancelled
 * - @chunk-retry: Emitted when a chunk upload is being retried
 *   - payload: { chunkNumber, attempt, maxAttempts, delay, error }
 */
const VueResumableFileUpload = Vue.component('resumable-file-upload', {
    props: {
        projectId: {
            type: [String, Number],
            required: true
        },
        fileType: {
            type: String,
            default: 'documentation',
            validator: function(value) {
                return ['documentation', 'data'].indexOf(value) !== -1;
            }
        },
        maxFileSize: {
            type: Number,
            default: null
        },
        allowedTypes: {
            type: String,
            default: null
        },
        disabled: {
            type: Boolean,
            default: false
        },
        maxRetries: {
            type: Number,
            default: 3
        },
        retryDelay: {
            type: Number,
            default: 1000
        },
        exponentialBackoff: {
            type: Boolean,
            default: true
        }
    },
    data() {
        return {
            file: null,
            uploadId: null,
            uploadProgress: 0,
            isUploading: false,
            isInitializing: false,
            uploadStatus: 'idle', // idle, initializing, uploading, completed, error, cancelled, retryable_error
            error: null,
            chunkSize: 10485760, // 10MB default, will be updated from server
            maxChunkSize: 10485760,
            totalChunks: 0,
            uploadedChunks: 0,
            uploadLimits: null,
            cancelRequested: false,
            retryAttempts: {},
            currentRetryDelay: null,
            serverFilename: null // Stores the sanitized filename from server
        };
    },
    mounted() {
        this.loadUploadLimits();
    },
    methods: {
        /**
         * Load upload limits from server
         */
        loadUploadLimits() {
            const vm = this;
            axios.get(CI.base_url + '/api/uploads/limits')
                .then(function(response) {
                    if (response.data.status === 'success') {
                        vm.uploadLimits = response.data;
                        // Use recommended chunk size, but not more than max
                        vm.chunkSize = Math.min(
                            response.data.recommended_chunk_size || 10485760,
                            response.data.max_chunk_size || 10485760
                        );
                        vm.maxChunkSize = response.data.max_chunk_size || 10485760;
                    }
                })
                .catch(function(error) {
                    console.error('Failed to load upload limits:', error);
                    // Use defaults if API fails
                });
        },
        
        /**
         * Handle file selection
         */
        handleFileSelect(event) {
            // v-file-input in Vuetify passes the File object directly, not an event with target.files
            // Handle both cases: event object with target.files or direct File object
            let selectedFile = null;
            
            // Check if event is null/undefined/empty (file cleared)
            // v-file-input passes null when cleared, or sometimes an empty array
            if (!event || event === null || (Array.isArray(event) && event.length === 0)) {
                this.clearFile();
                return;
            }
            
            // Check if event is already a File object (Vuetify v-file-input behavior)
            if (event instanceof File) {
                selectedFile = event;
            }
            // Check for standard HTML file input event
            else if (event && event.target && event.target.files && event.target.files.length > 0) {
                selectedFile = event.target.files[0];
            }
            // Check for alternative event format
            else if (event && event.files && event.files.length > 0) {
                selectedFile = event.files[0];
            }
            // Check if event is an array with a File object
            else if (Array.isArray(event) && event.length > 0 && event[0] instanceof File) {
                selectedFile = event[0];
            }
            
            // Validate that we have a File object
            if (!selectedFile || !(selectedFile instanceof File)) {
                // Clear file if invalid
                this.clearFile();
                return;
            }
            
            // Reset previous upload state (but keep file-related data)
            this.resetUploadState();
            
            // Validate file
            if (!this.validateFile(selectedFile)) {
                return;
            }
            
            this.file = selectedFile;
            this.calculateChunks();
            
            // Emit file-selected event
            this.$emit('file-selected', {
                file: selectedFile,
                filename: selectedFile.name,
                size: selectedFile.size
            });
        },
        
        /**
         * Validate file before upload
         */
        validateFile(file) {
            this.error = null;
            
            // Check file size
            if (this.maxFileSize && file.size > this.maxFileSize) {
                this.error = 'File size exceeds maximum allowed size of ' + this.formatBytes(this.maxFileSize);
                this.$emit('upload-error', {
                    error: 'FILE_TOO_LARGE',
                    message: this.error
                });
                return false;
            }
            
            // Check file type
            if (this.allowedTypes) {
                const extension = file.name.split('.').pop().toLowerCase();
                const allowed = this.allowedTypes.split(',').map(t => t.trim().toLowerCase());
                if (!allowed.includes(extension)) {
                    this.error = 'File type not allowed. Allowed types: ' + this.allowedTypes;
                    this.$emit('upload-error', {
                        error: 'FILE_TYPE_NOT_ALLOWED',
                        message: this.error
                    });
                    return false;
                }
            }
            
            return true;
        },
        
        /**
         * Calculate number of chunks needed
         */
        calculateChunks() {
            if (!this.file) {
                return;
            }
            
            this.totalChunks = Math.ceil(this.file.size / this.chunkSize);
        },
        
        /**
         * Start upload process
         */
        async startUpload() {
            if (!this.file || this.isUploading || this.disabled) {
                return;
            }
            if (typeof ResumableChunkUploader === 'undefined') {
                this.uploadStatus = 'error';
                this.error = 'ResumableChunkUploader is not loaded';
                return;
            }

            this.calculateChunks();
            const savedTotalChunks = this.totalChunks;
            this.resetUploadState();
            this.totalChunks = savedTotalChunks;

            this.isUploading = true;
            this.uploadStatus = 'initializing';
            this.error = null;
            this.cancelRequested = false;

            const vm = this;
            try {
                const result = await ResumableChunkUploader.uploadFileChunks(this.file, {
                    projectId: this.projectId,
                    fileType: this.fileType,
                    chunkSize: this.chunkSize,
                    maxChunkSize: this.maxChunkSize,
                    maxRetries: this.maxRetries,
                    retryDelay: this.retryDelay,
                    exponentialBackoff: this.exponentialBackoff,
                    cancelRequested: function () {
                        return vm.cancelRequested;
                    },
                    onUploadInitialized: function (id) {
                        vm.uploadId = id;
                        vm.uploadStatus = 'uploading';
                    },
                    onInitializing: function (v) {
                        vm.isInitializing = v;
                    },
                    onProgress: function (p) {
                        vm.uploadedChunks = p.uploaded_chunks;
                        vm.uploadProgress = p.progress;
                        vm.$emit('upload-progress', {
                            progress: p.progress,
                            uploaded_chunks: p.uploaded_chunks,
                            total_chunks: p.total_chunks,
                            status: 'uploading'
                        });
                    }
                });

                if (result.serverFilename) {
                    vm.serverFilename = result.serverFilename;
                }

                await this.finalizeUpload();

                this.uploadStatus = 'completed';
                this.uploadProgress = 100;

                this.$emit('upload-complete', {
                    upload_id: this.uploadId,
                    filename: this.serverFilename || this.file.name,
                    file_path: null,
                    file_size: this.file.size
                });
            } catch (error) {
                if (!this.cancelRequested) {
                    if (error.error === 'CHUNK_UPLOAD_FAILED' || error.error === 'CHUNK_READ_FAILED') {
                        this.uploadStatus = 'retryable_error';
                    } else {
                        this.uploadStatus = 'error';
                    }
                    this.error = error.message || 'Upload failed';
                    this.$emit('upload-error', {
                        error: error.error || 'UPLOAD_FAILED',
                        message: this.error,
                        retryable: this.uploadStatus === 'retryable_error'
                    });
                }
            } finally {
                this.isUploading = false;
            }
        },

        /**
         * Finalize upload by moving file to final location
         */
        finalizeUpload() {
            const vm = this;
            
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('upload_id', this.uploadId);
                
                axios.post(
                    CI.base_url + '/api/files/' + this.projectId + '/' + this.fileType,
                    formData,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }
                )
                .then(function(response) {
                    if (response.data.status === 'success') {
                        // Store the final filename from server if provided
                        if (response.data.filename) {
                            vm.serverFilename = response.data.filename;
                        }
                        resolve(response.data);
                    } else {
                        reject(new Error(response.data.message || 'Failed to finalize upload'));
                    }
                })
                .catch(function(error) {
                    const errorMsg = error.response?.data?.message || error.message || 'Failed to finalize upload';
                    reject({ error: 'FINALIZE_FAILED', message: errorMsg });
                });
            });
        },
        
        /**
         * Cancel upload
         */
        cancelUpload() {
            if (!this.isUploading || !this.uploadId) {
                return;
            }
            
            this.cancelRequested = true;
            this.uploadStatus = 'cancelled';
            this.isUploading = false;
            
            // Optionally delete the upload on server
            if (this.uploadId) {
                axios.delete(CI.base_url + '/api/uploads/' + this.uploadId)
                    .catch(function(error) {
                        console.error('Failed to cancel upload on server:', error);
                    });
            }
            
            this.$emit('upload-cancelled');
        },
        
        /**
         * Retry the entire upload after failure
         */
        retryUpload() {
            if (this.uploadStatus !== 'retryable_error' || !this.file) {
                return;
            }
            
            // Reset error state and retry the upload
            this.error = null;
            this.uploadStatus = 'idle';
            this.startUpload();
        },
        
        /**
         * Clear file selection and reset all related state
         */
        clearFile() {
            this.file = null;
            this.totalChunks = 0;
            this.resetUploadState();
            // Emit file-cleared event to parent
            this.$emit('file-cleared');
        },
        
        /**
         * Reset upload state
         * Note: totalChunks is not reset here as it's file-related, not upload state
         */
        resetUploadState() {
            this.uploadId = null;
            this.uploadProgress = 0;
            this.uploadStatus = 'idle';
            this.error = null;
            this.uploadedChunks = 0;
            // Don't reset totalChunks - it's calculated from file and chunkSize
            this.cancelRequested = false;
            this.retryAttempts = {};
            this.currentRetryDelay = null;
            this.serverFilename = null;
        },
        
        /**
         * Format bytes to human-readable string
         */
        formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
    },
    computed: {
        canUpload() {
            return this.file && !this.isUploading && !this.disabled && 
                   this.uploadStatus !== 'retryable_error' && this.uploadStatus !== 'completed';
        },
        showProgress() {
            return this.isUploading || this.uploadStatus === 'completed';
        },
        progressPercentage() {
            return Math.min(this.uploadProgress, 100);
        },
        canRetry() {
            return this.uploadStatus === 'retryable_error' && this.file && !this.disabled;
        }
    },
    template: `
        <div class="resumable-file-upload">
            <div v-if="error" class="alert mb-2" :class="uploadStatus === 'retryable_error' ? 'alert-warning' : 'alert-danger'">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <strong v-if="uploadStatus === 'retryable_error'">Upload Failed:</strong>
                        <strong v-else>Error:</strong>
                        {{ error }}
                    </div>
                    <div v-if="uploadStatus === 'retryable_error'" class="ml-2">
                        <v-btn color="primary" x-small @click="retryUpload" :disabled="disabled">
                            <v-icon left x-small>mdi-refresh</v-icon>
                            Retry
                        </v-btn>
                        <v-btn color="error" x-small @click="clearFile" class="ml-1">
                            <v-icon left x-small>mdi-cancel</v-icon>
                            Cancel
                        </v-btn>
                    </div>
                </div>
            </div>
            
            <div class="file-input-container mb-2">
                <v-file-input
                    label=""
                    outlined
                    dense
                    :disabled="disabled || isUploading"
                    @change="handleFileSelect($event)"
                    :value="file"
                    :truncate-length="50"
                ></v-file-input>
            </div>
            
            <div v-if="file" class="file-info mb-2">
                <small class="text-muted">
                    File: {{ file.name }} ({{ formatBytes(file.size) }})                    
                </small>
            </div>
            
            <div v-if="showProgress" class="upload-progress mb-2">
                <v-progress-linear
                    :value="progressPercentage"
                    :color="uploadStatus === 'completed' ? 'success' : 'primary'"
                    height="25"                    
                    :indeterminate="isInitializing"
                >
                    <template v-slot:default="{ value }">
                        <strong v-if="!isInitializing">{{ Math.ceil(value) }}%</strong>
                        <strong v-else>Initializing...</strong>
                    </template>
                </v-progress-linear>                
            </div>
            
            <div class="upload-actions">
                <v-btn
                    v-if="canUpload"
                    color="primary"
                    small
                    @click="startUpload"
                    :disabled="disabled"
                >
                    <v-icon left small>mdi-upload</v-icon>
                    Upload
                </v-btn>
                
                <v-btn
                    v-if="isUploading"
                    color="error"
                    small
                    @click="cancelUpload"
                >
                    <v-icon left small>mdi-cancel</v-icon>
                    Cancel
                </v-btn>
                
                <v-btn
                    v-if="canRetry"
                    color="warning"
                    small
                    @click="retryUpload"
                    :disabled="disabled"
                >
                    <v-icon left small>mdi-refresh</v-icon>
                    Retry Upload
                </v-btn>
                
                <v-btn
                    v-if="uploadStatus === 'completed'"
                    color="success"
                    small
                    disabled
                >
                    <v-icon left small>mdi-check</v-icon>
                    Completed
                </v-btn>
            </div>
        </div>
    `
});
