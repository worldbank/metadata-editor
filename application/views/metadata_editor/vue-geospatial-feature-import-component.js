/// Geospatial feature import component
Vue.component('geospatial-feature-import', {
    props: ['index'],
    data: function () {    
        return {
            files: [],
            is_processing: false,
            update_status: '',
            errors: '',
            has_errors: false,
            dialog_process: false,
            upload_report: [],
            supported_formats: ['geojson', 'shp', 'tiff', 'geotiff', 'tif', 'kml', 'kmz', 'gpx', 'csv', 'json', 'zip', 'gpkg', 'nc', 'hdf', 'hdf5', 'grib', 'grb', 'jpg', 'jpeg', 'png', 'img', 'ecw', 'sid', 'jp2', 'asc', 'dem', 'bil', 'bip', 'bsq', 'dt0', 'dt1', 'dt2'],
            project_id: null,
            show_layer_dialog: false,
            pendingLayerData: null,
            selectedLayers: [],
            pendingJobs: null,
            pollInterval: null,
            is_cancelled: false
        }
    },
    mounted: function() {
        this.project_id = this.$store.state.project_id;
    },
    beforeDestroy: function() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }
    },
    methods: {
        addFile: function(event) {
            const files = event.dataTransfer ? event.dataTransfer.files : event.target.files;
            if (!files) return;
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                if (this.isValidGeospatialFile(file)) {
                    this.files.push({
                        file: file,
                        name: file.name,
                        size: file.size,
                        type: this.getFileExtension(file.name),
                        status: 'pending'
                    });
                }
            }
        },
        
        removeFile: function(index) {
            this.files.splice(index, 1);
        },
        
        isValidGeospatialFile: function(file) {
            const extension = this.getFileExtension(file.name).toLowerCase();
            return this.supported_formats.includes(extension);
        },
        
        getFileExtension: function(filename) {
            return filename.split('.').pop().toLowerCase();
        },
        
        isRasterFile: function(filename) {
            const extension = this.getFileExtension(filename);
            const rasterExtensions = ['tif', 'tiff', 'jpg', 'jpeg', 'png', 'img', 'hdf', 'nc', 'grib', 'grb', 'ecw', 'sid', 'jp2', 'asc', 'dem', 'bil', 'bip', 'bsq', 'dt0', 'dt1', 'dt2'];
            return rasterExtensions.includes(extension);
        },
        
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        getFileTypeIcon: function(fileType) {
            const iconMap = {
                'geojson': 'mdi-map-marker-path',
                'shp': 'mdi-map-marker',
                'tiff': 'mdi-image',
                'geotiff': 'mdi-image',
                'tif': 'mdi-image',
                'kml': 'mdi-earth',
                'kmz': 'mdi-earth',
                'gpx': 'mdi-map-marker-path',
                'csv': 'mdi-table',
                'json': 'mdi-code-json',
                'zip': 'mdi-folder-zip',
                'gpkg': 'mdi-database',
                'nc': 'mdi-grid',
                'hdf': 'mdi-grid',
                'hdf5': 'mdi-grid',
                'grib': 'mdi-weather-cloudy',
                'grb': 'mdi-weather-cloudy',
                'jpg': 'mdi-image',
                'jpeg': 'mdi-image',
                'png': 'mdi-image',
                'img': 'mdi-image',
                'ecw': 'mdi-image',
                'sid': 'mdi-image',
                'jp2': 'mdi-image',
                'asc': 'mdi-elevation-rise',
                'dem': 'mdi-elevation-rise',
                'bil': 'mdi-image',
                'bip': 'mdi-image',
                'bsq': 'mdi-image',
                'dt0': 'mdi-elevation-rise',
                'dt1': 'mdi-elevation-rise',
                'dt2': 'mdi-elevation-rise'
            };
            return iconMap[fileType] || 'mdi-file-document';
        },
        
        getFileTypeColor: function(fileType) {
            const colorMap = {
                'geojson': 'green',
                'shp': 'blue',
                'tiff': 'orange',
                'geotiff': 'orange',
                'tif': 'orange',
                'kml': 'red',
                'kmz': 'red',
                'gpx': 'purple',
                'csv': 'teal',
                'json': 'indigo',
                'zip': 'amber',
                'gpkg': 'deep-purple',
                'nc': 'cyan',
                'hdf': 'cyan',
                'hdf5': 'cyan',
                'grib': 'light-blue',
                'grb': 'light-blue',
                'jpg': 'orange',
                'jpeg': 'orange',
                'png': 'orange',
                'img': 'deep-orange',
                'ecw': 'deep-orange',
                'sid': 'deep-orange',
                'jp2': 'orange',
                'asc': 'brown',
                'dem': 'brown',
                'bil': 'orange',
                'bip': 'orange',
                'bsq': 'orange',
                'dt0': 'brown',
                'dt1': 'brown',
                'dt2': 'brown'
            };
            return colorMap[fileType] || 'grey';
        },
        
        
        processImport: function() {
            if (this.files.length === 0) return;
            
            this.is_processing = true;
            this.dialog_process = true;
            this.upload_report = [];
            this.has_errors = false;
            this.is_cancelled = false; // Reset cancellation flag
            this.update_status = 'Uploading files...';
            
            // Process all files
            this.processAllFiles();
        },
        
        processAllFiles: function() {
            let completedFiles = 0;
            const totalFiles = this.files.length;
            
            this.files.forEach((fileData, index) => {
                fileData.status = 'processing';
                
                this.uploadFile(fileData.file)
                .then(uploadResult => {
                    if (uploadResult.status === 'success') {
                        fileData.status = 'uploaded';
                        fileData.uploadedFiles = uploadResult.files || [];
                        this.update_status = `Files uploaded. Starting layer analysis...`;
                        
                        // Start layer analysis for all files at once
                        if (completedFiles === totalFiles - 1) {
                            this.startLayerAnalysisForAllFiles();
                        }
                    } else {
                        fileData.status = 'failed';
                        fileData.error = uploadResult.message || 'Upload failed';
                        this.has_errors = true;
                        this.errors = uploadResult.message || 'Upload failed';
                        this.update_status = 'Upload failed';
                        this.is_processing = false;
                        
                        // Add to upload report for visibility
                        this.upload_report.push({
                            file_name: fileData.name,
                            status: 'failed',
                            error: uploadResult.message || 'Upload failed',
                            context: 'File Upload'
                        });
                    }
                    completedFiles++;
                })
                .catch(error => {
                    console.error('Upload error:', error);
                    fileData.status = 'failed';
                    // Extract error message from axios error response
                    const errorMessage = error.response?.data?.message || error.message || 'Upload failed';
                    fileData.error = errorMessage;
                    this.has_errors = true;
                    this.errors = errorMessage;
                    this.update_status = 'Upload failed';
                    this.is_processing = false;
                    
                    // Add to upload report for visibility
                    this.upload_report.push({
                        file_name: fileData.name,
                        status: 'failed',
                        error: errorMessage,
                        context: 'File Upload'
                    });
                    
                    completedFiles++;
                });
            });
        },
        
        startLayerAnalysisForAllFiles: function() {
            // Collect all uploaded files
            const allFilePaths = [];
            this.files.forEach(fileData => {
                if (fileData.status === 'uploaded' && fileData.uploadedFiles) {
                    fileData.uploadedFiles.forEach(file => {
                        allFilePaths.push(file.path);
                    });
                }
            });
            
            if (allFilePaths.length === 0) {
                this.update_status = 'No files to analyze';
                this.is_processing = false;
                return;
            }
            
            this.startLayerAnalysis(allFilePaths)
            .then(result => {
                if (result.status === 'success' && result.jobs && result.jobs.length > 0) {
                    this.update_status = 'Layer analysis started. Please wait...';
                    this.startJobPolling(result.jobs);
                } else {
                    this.update_status = 'Layer analysis completed. Please select layers to import.';
                    this.showLayerSelectionDialog(result);
                }
            })
            .catch(error => {
                this.update_status = 'Error starting layer analysis: ' + error.message;
                this.has_errors = true;
                this.is_processing = false;
            });
        },
        
        startLayerAnalysis: function(filePaths) {
            return new Promise((resolve, reject) => {
                const url = CI.base_url + '/api/geospatial_features/geospatial_analyze/' + this.project_id;
                
                axios.post(url, { file_paths: filePaths })
                .then(response => {
                    resolve(response.data);
                })
                .catch(error => {
                    reject(error);
                });
            });
        },
        
        startJobPolling: function(jobs) {
            this.pendingJobs = {
                jobs: jobs,
                completedJobs: [],
                failedJobs: [],
                allLayers: []
            };
            
            this.update_status = `Waiting for layer analysis to complete (${jobs.length} job(s) running)...`;
            
            // Start polling every 2 seconds
            this.pollInterval = setInterval(() => {
                this.pollJobStatus();
            }, 2000);
        },
        
        pollJobStatus: async function() {
            if (!this.pendingJobs) return;
            
            // Check if user cancelled the import
            if (this.is_cancelled) {
                console.log('Import cancelled, stopping job polling');
                clearInterval(this.pollInterval);
                this.pollInterval = null;
                return;
            }
            
            try {
                const pendingJobs = this.pendingJobs.jobs.filter(job => 
                    job.job_id && !this.pendingJobs.completedJobs.find(completed => completed.job_id === job.job_id)
                );
                
                if (pendingJobs.length === 0) {
                    // All jobs completed
                    clearInterval(this.pollInterval);
                    
                    // Check if there are any failed jobs
                    const failedJobs = this.pendingJobs.failedJobs || [];
                    if (failedJobs.length > 0) {
                        console.warn(`${failedJobs.length} job(s) failed during processing`, failedJobs);
                        // Show errors to user
                        const errorMessages = failedJobs.map(job => {
                            const fileName = job.file_path ? job.file_path.split('/').pop() : 'Unknown file';
                            return `${fileName}: ${job.error}`;
                        }).join('\n');
                        
                        const errorText = `Some files failed to process:\n${errorMessages}`;
                        console.error('Setting error message:', errorText);
                        this.errors = errorText;
                        this.has_errors = true;
                        console.error('Error state set - has_errors:', this.has_errors, 'errors:', this.errors);
                    }
                    
                    // Show layer selection if there are any successful layers
                    if (this.pendingJobs.allLayers && this.pendingJobs.allLayers.length > 0) {
                        this.update_status = 'Layer analysis complete. Please select layers to import.';
                        this.showLayerSelectionDialog(this.pendingJobs.allLayers);
                    } else {
                        // No layers to import
                        this.update_status = 'No layers were successfully analyzed.';
                        this.is_processing = false;
                        if (!this.has_errors) {
                            this.errors = 'No layers could be extracted from the uploaded files.';
                            this.has_errors = true;
                        }
                    }
                    return;
                }
                
                // Check status of pending jobs
                for (const job of pendingJobs) {
                    try {
                        const statusResult = await this.checkJobStatus(job.job_id);
                        
                        // Check if the API call itself failed
                        if (statusResult.success === false) {
                            console.error(`Job ${job.job_id} failed:`, statusResult);
                            this.pendingJobs.completedJobs.push(job);
                            this.pendingJobs.failedJobs = this.pendingJobs.failedJobs || [];
                            this.pendingJobs.failedJobs.push({
                                job_id: job.job_id,
                                file_path: job.file_path,
                                error: statusResult.message || 'Job failed',
                                errors: statusResult.errors || []
                            });
                            continue;
                        }
                        
                        // Check if job is completed (status is "done")
                        const jobStatus = statusResult.data && statusResult.data.status ? statusResult.data.status : statusResult.status;
                        const jobData = statusResult.data && statusResult.data.data ? statusResult.data.data : statusResult.data;
                        
                        if (jobStatus === 'done') {
                            this.pendingJobs.completedJobs.push(job);
                            
                            // Extract layers from data.layers array
                            if (jobData && jobData.layers && jobData.layers.length > 0) {
                                // Create layer objects with file path and other metadata
                                const layers = jobData.layers.map(layerName => ({
                                    name: layerName,
                                    file_path: job.file_path,
                                    file_info: jobData.file_info,
                                    bounding_box: jobData.bounding_box,
                                    type: jobData.type,
                                    processing_recommendations: jobData.processing_recommendations
                                }));
                                
                                this.pendingJobs.allLayers = this.pendingJobs.allLayers.concat(layers);
                            }
                        } else if (jobStatus === 'failed' || jobStatus === 'error') {
                            console.error(`Job ${job.job_id} failed with status:`, jobStatus);
                            this.pendingJobs.completedJobs.push(job);
                            this.pendingJobs.failedJobs = this.pendingJobs.failedJobs || [];
                            this.pendingJobs.failedJobs.push({
                                job_id: job.job_id,
                                file_path: job.file_path,
                                error: jobData && jobData.error ? jobData.error : 'Job failed',
                                status: jobStatus
                            });
                        }
                        // Continue polling if status is 'processing'
                    } catch (error) {
                        console.error(`Error checking job ${job.job_id}:`, error);
                        this.pendingJobs.completedJobs.push(job);
                        this.pendingJobs.failedJobs = this.pendingJobs.failedJobs || [];
                        this.pendingJobs.failedJobs.push({
                            job_id: job.job_id,
                            file_path: job.file_path,
                            error: error.message || 'Unknown error'
                        });
                    }
                }
            } catch (error) {
                console.error('Job polling failed:', error);
                clearInterval(this.pollInterval);
                this.update_status = 'Error during layer analysis';
                this.has_errors = true;
                this.is_processing = false;
            }
        },
        
        checkJobStatus: function(jobId) {
            return new Promise((resolve, reject) => {
                const url = CI.base_url + '/api/geospatial_features/job_status/' + this.project_id + '?job_id=' + jobId;
                
                axios.get(url)
                .then(response => {
                    resolve(response.data);
                })
                .catch(error => {
                    reject(error);
                });
            });
        },
        
        showLayerSelectionDialog: function(layers) {
            console.log('=== SHOWING LAYER SELECTION DIALOG ===');
            console.log('Available layers:', layers);
            
            // Store data for layer selection
            this.pendingLayerData = {
                layers: layers
            };
            this.show_layer_dialog = true;
            
            // Update status to show layer analysis is complete
            this.update_status = 'Layer analysis complete. Please select layers to import.';
            this.is_processing = false;
            
            console.log('Layer selection dialog opened');
        },
        
        createFeaturesFromFiles: async function(processedFiles, fileData) {
            // Create features for each processed file
            for (const processedFile of processedFiles) {
                const fileExtension = processedFile.name.split('.').pop().toLowerCase();
                const featureData = {
                    sid: this.project_id,
                    name: processedFile.name.replace(/\.[^/.]+$/, ""), // Remove extension for feature name
                    code: this.generateFeatureCode(processedFile.name),
                    file_name: processedFile.name,
                    file_type: fileExtension,
                    file_size: fileData.size, // Use original file size
                    upload_status: 'pending',
                    metadata: {
                        uploaded_file: fileData.name,
                        processed_file: processedFile.name,
                        file_type: fileExtension,
                        file_size: fileData.size,
                        upload_date: new Date().toISOString(),
                        processing_type: processedFile.type // 'direct' or 'extracted'
                    }
                };
                
                // Add original ZIP info if this was extracted
                if (processedFile.type === 'extracted' && processedFile.original_zip) {
                    featureData.metadata.original_zip = processedFile.original_zip;
                }
                
                const createResult = await this.createFeature(featureData);
                
                if (createResult.status === 'success') {
                    this.upload_report.push({
                        file_name: processedFile.name,
                        status: 'success',
                        feature_id: createResult.feature_id,
                        type: processedFile.type,
                        original_zip: processedFile.original_zip || null
                    });
                } else {
                    throw new Error(createResult.message || 'Failed to create feature');
                }
            }
        },
        
        uploadFile: function(file) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('file', file);
                
                const url = CI.base_url + '/api/geospatial_features/upload/' + this.project_id;
                
                axios.post(url, formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                })
                .then(response => {
                    resolve(response.data);
                })
                .catch(error => {
                    reject(error);
                });
            });
        },
        
        
        generateFeatureCode: function(filename) {
            // Generate a simple code from filename
            const name = filename.replace(/\.[^/.]+$/, ""); // Remove extension
            return name.replace(/[^a-zA-Z0-9]/g, '_').toUpperCase();
        },
        
        dialogClose: function() {
            this.dialog_process = false;
            if (!this.has_errors) {
                this.$router.push('/geospatial-features');
            }
        },
        
        clearFiles: function() {
            this.files = [];
            this.errors = '';
            this.has_errors = false;
        },
        
        cancelImport: function() {
            const confirmed = confirm('Are you sure you want to cancel the import process? This will stop all ongoing operations.');
            
            if (confirmed) {
                this.is_cancelled = true;
                this.is_processing = false;
                this.update_status = 'Import cancelled by user';
                this.has_errors = true;
                
                // Clear any ongoing polling
                if (this.pollInterval) {
                    clearInterval(this.pollInterval);
                    this.pollInterval = null;
                }
                
                // Close layer dialog if open
                this.show_layer_dialog = false;
                this.pendingLayerData = null;
                this.selectedLayers = [];
                
                console.log('Import cancelled by user');
            }
        },
        
        selectAllLayers: function() {
            console.log('=== SELECT ALL LAYERS ===');
            if (this.pendingLayerData && this.pendingLayerData.layers) {
                this.selectedLayers = this.pendingLayerData.layers.map(layer => layer.id || layer.name);
                console.log('Selected layers:', this.selectedLayers);
            }
        },
        
        deselectAllLayers: function() {
            console.log('=== DESELECT ALL LAYERS ===');
            this.selectedLayers = [];
            console.log('Deselected all layers');
        },
        
        confirmLayerSelection: async function() {
            console.log('=== CONFIRM LAYER SELECTION ===');
            console.log('Selected layers count:', this.selectedLayers.length);
            console.log('Selected layers:', this.selectedLayers);
            
            if (this.selectedLayers.length === 0) {
                console.log('No layers selected, showing alert');
                alert(this.$t('Please select at least one layer'));
                return;
            }
            
            // Set loading state
            this.is_processing = true;
            this.update_status = 'Creating features from selected layers...';
            
            try {
                console.log('Starting to create features for selected layers...');
                // Create features for selected layers
                await this.createFeaturesFromSelectedLayers();
                console.log('Successfully created features for all selected layers');
                this.show_layer_dialog = false;
                this.pendingLayerData = null;
                this.selectedLayers = [];
            } catch (error) {
                console.error('Error creating features from selected layers:', error);
                alert(this.$t('Error creating features: ') + error.message);
                this.is_processing = false;
            }
        },
        
        cancelLayerSelection: function() {
            console.log('=== CANCEL LAYER SELECTION ===');
            
            // If currently processing layers, ask for confirmation
            if (this.is_processing) {
                const confirmed = confirm('Are you sure you want to cancel the layer processing? This will stop all ongoing operations.');
                if (!confirmed) {
                    return; // User chose not to cancel
                }
                
                // Cancel the processing
                this.is_cancelled = true;
                this.is_processing = false;
                this.update_status = 'Layer processing cancelled by user';
                this.has_errors = true;
                console.log('Layer processing cancelled by user');
            }
            
            // Close the dialog and reset state
            this.show_layer_dialog = false;
            this.pendingLayerData = null;
            this.selectedLayers = [];
            
            if (!this.is_processing) {
                this.update_status = 'Import cancelled';
                this.is_processing = false;
            }
            
            console.log('Layer selection cancelled');
        },
        
        createFeaturesFromSelectedLayers: async function() {
            if (!this.pendingLayerData) {
                return;
            }
            
            const { layers } = this.pendingLayerData;
            
            // Set processing status
            this.is_processing = true;
            this.update_status = 'Creating features from selected layers...';
            
            // Process each selected layer
            for (let i = 0; i < this.selectedLayers.length; i++) {
                // Check if user cancelled the processing
                if (this.is_cancelled) {
                    console.log('Layer processing cancelled by user, stopping...');
                    this.is_processing = false;
                    this.update_status = 'Layer processing cancelled';
                    return;
                }
                
                const layerId = this.selectedLayers[i];
                const layer = layers.find(l => (l.id || l.name) === layerId);
                if (!layer) {
                    continue;
                }
                
                this.update_status = `Processing layer ${i + 1}/${this.selectedLayers.length}: ${layer.name}...`;
                
                try {
                    // Start metadata extraction job for this single layer
                    const jobResult = await this.startMetadataJob(layer.file_path, layer.name);
                    
                    if (jobResult.success) {
                        // Use the new metadata_import endpoint to poll and create features
                        const importResult = await this.pollMetadataImport(jobResult.job_id);
                        
                        if (importResult.status === 'success') {
                            this.upload_report.push({
                                file_name: layer.name,
                                status: 'success',
                                feature_id: importResult.feature_id,
                                layer_name: layer.name
                            });
                            
                            // CSV generation disabled for geospatial features
                            
                            /* DISABLED: CSV Generation
                            // Check if file is a raster - skip CSV generation for raster files
                            const fileName = layer.file_path ? layer.file_path.split('/').pop() : '';
                            const isRaster = this.isRasterFile(fileName);
                            
                            if (!isRaster) {
                                // Start CSV generation only for vector files
                                try {
                                    console.log(`Starting CSV generation for vector file: ${fileName}`);
                                    const csvJobResult = await this.startCsvGeneration(importResult.feature_id);
                                    await this.pollCsvJobStatus(csvJobResult.job_id, importResult.feature_id);
                                } catch (csvError) {
                                    console.error(`CSV generation failed for feature ${importResult.feature_id}:`, csvError);
                                }
                            } else {
                                console.log(`Skipping CSV generation for raster file: ${fileName}`);
                            }
                            */
                        } else {
                            this.upload_report.push({
                                file_name: layer.name,
                                status: 'failed',
                                error: importResult.message
                            });
                        }
                    } else {
                        this.upload_report.push({
                            file_name: layer.name,
                            status: 'failed',
                            error: jobResult.message
                        });
                    }
                } catch (error) {
                    this.upload_report.push({
                        file_name: layer.name,
                        status: 'failed',
                        error: error.message
                    });
                }
            }
            
            // Set final status and refresh features list
            this.update_status = 'completed';
            this.is_processing = false;
            
            // Refresh the features list
            if (this.$store.dispatch) {
                this.$store.dispatch('loadGeospatialFeatures', { dataset_id: this.project_id });
            }
            
            console.log('Import process completed successfully');
        },
        
        startCsvGeneration: function(featureId) {
            console.log(`=== STARTING CSV GENERATION ===`);
            console.log(`Feature ID: ${featureId}`);
            
            return new Promise((resolve, reject) => {
                const url = CI.base_url + '/api/geospatial_features/csv_generate/' + this.project_id;
                console.log(`CSV API URL: ${url}`);
                
                const payload = { 
                    feature_id: featureId
                };
                console.log(`CSV Request payload:`, payload);
                
                axios.post(url, payload)
                .then(response => {
                    console.log(`CSV generation response:`, response.data);
                    if (response.data.status === 'success') {
                        resolve({
                            success: true,
                            job_id: response.data.job_id,
                            message: response.data.message
                        });
                    } else {
                        reject(new Error(response.data.message || 'Failed to start CSV generation'));
                    }
                })
                .catch(error => {
                    console.error(`CSV generation error:`, error);
                    reject(new Error(error.response?.data?.message || 'Failed to start CSV generation'));
                });
            });
        },
        
        pollCsvJobStatus: function(jobId, featureId) {
            console.log(`=== POLLING CSV JOB STATUS ===`);
            console.log(`Job ID: ${jobId}`);
            console.log(`Feature ID: ${featureId}`);
            
            return new Promise((resolve, reject) => {
                const pollInterval = 2000; // 2 seconds
                const maxAttempts = 300; // Extended timeout: 300 attempts (10 minutes)
                let attempts = 0;
                
                const poll = () => {
                    // Check if user cancelled the import
                    if (this.is_cancelled) {
                        console.log(`Import cancelled, stopping CSV polling for job ${jobId}`);
                        reject(new Error('Import cancelled by user'));
                        return;
                    }
                    
                    attempts++;
                    console.log(`CSV job status check attempt ${attempts}/${maxAttempts} for job ${jobId}`);
                    
                    const url = CI.base_url + '/api/geospatial_features/csv_download/' + this.project_id + '?job_id=' + jobId + '&feature_id=' + featureId;
                    console.log(`CSV status API URL: ${url}`);
                    
                    axios.get(url)
                    .then(response => {
                        console.log(`CSV job status response:`, response.data);
                        
                        if (response.data.status === 'success') {
                            console.log(`CSV generation completed successfully for feature ${featureId}`);
                            resolve(response.data);
                        } else if (response.data.status === 'processing') {
                            console.log(`CSV job still processing, will check again in ${pollInterval}ms`);
                            if (attempts < maxAttempts) {
                                setTimeout(poll, pollInterval);
                            } else {
                                reject(new Error('CSV generation timeout - job did not complete within expected time'));
                            }
                        } else {
                            reject(new Error(response.data.message || 'CSV generation failed'));
                        }
                    })
                    .catch(error => {
                        console.error(`CSV job status error:`, error);
                        reject(new Error(error.response?.data?.message || 'Failed to check CSV job status'));
                    });
                };
                
                // Start polling
                poll();
            });
        },
        
        startMetadataJob: function(filePath, layerName) {
            console.log(`=== STARTING METADATA JOB ===`);
            console.log(`File path: ${filePath}`);
            console.log(`Layer name: ${layerName}`);
            
            return new Promise((resolve, reject) => {
                const url = CI.base_url + '/api/geospatial_features/metadata_queue/' + this.project_id;
                console.log(`API URL: ${url}`);
                
                const payload = { 
                    file_path: filePath,
                    layer_name: layerName
                };
                console.log(`Request payload:`, payload);
                
                axios.post(url, payload)
                .then(response => {
                    console.log(`Metadata job response:`, response.data);
                    resolve(response.data);
                })
                .catch(error => {
                    console.error(`Metadata job error:`, error);
                    reject(error);
                });
            });
        },
        
        pollMetadataImport: function(jobId) {
            console.log(`=== POLLING METADATA IMPORT ===`);
            console.log(`Job ID: ${jobId}`);
            
            return new Promise((resolve, reject) => {
                let pollCount = 0;
                const maxPolls = 300; // Extended timeout: 300 polls (10 minutes)
                
                const pollImport = () => {
                    // Check if user cancelled the import
                    if (this.is_cancelled) {
                        console.log(`Import cancelled, stopping polling for job ${jobId}`);
                        reject(new Error('Import cancelled by user'));
                        return;
                    }
                    
                    pollCount++;
                    console.log(`Polling metadata_import for job ${jobId}... (attempt ${pollCount}/${maxPolls})`);
                    
                    if (pollCount > maxPolls) {
                        console.error(`Job ${jobId} polling timeout after ${maxPolls} attempts`);
                        reject(new Error('Job polling timeout'));
                        return;
                    }
                    
                    const url = CI.base_url + '/api/geospatial_features/metadata_import/' + this.project_id + '?job_id=' + jobId;
                    console.log(`Import polling URL: ${url}`);
                    
                    axios.get(url)
                    .then(response => {
                        const importStatus = response.data;
                        console.log(`Import status for job ${jobId}:`, importStatus);
                        
                        if (importStatus.status === 'success') {
                            // Feature created successfully
                            console.log(`Feature created successfully for job ${jobId}:`, importStatus);
                            resolve({
                                status: 'success',
                                feature_id: importStatus.feature_id,
                                message: importStatus.message,
                                feature_data: importStatus.feature_data
                            });
                        } else if (importStatus.status === 'processing') {
                            // Job still processing, poll again in 2 seconds
                            console.log(`Job ${jobId} still processing (status: ${importStatus.job_status}), polling again in 2 seconds...`);
                            setTimeout(pollImport, 2000);
                        } else {
                            // Import failed
                            console.error(`Import failed for job ${jobId}:`, importStatus);
                            reject(new Error('Metadata import failed: ' + (importStatus.message || 'Unknown error')));
                        }
                    })
                    .catch(error => {
                        console.error(`Error polling import for job ${jobId}:`, error);
                        reject(error);
                    });
                };
                
                // Start polling
                console.log(`Starting to poll metadata_import for job ${jobId}`);
                pollImport();
            });
        }
    },
    computed: {
        canProcess: function() {
            return this.files.length > 0 && !this.is_processing;
        },
        hasCompletedFiles: function() {
            return this.files.some(f => f.status === 'completed');
        },
        hasFailedFiles: function() {
            return this.files.some(f => f.status === 'failed');
        }
    },
    template: `
        <div class="geospatial-feature-import-component">
            <div class="container-fluid pt-5 mt-5 mb-5 pb-5">
                <v-card>
                    <v-card-title class="d-flex justify-space-between">
                        <div>{{$t("Import Geospatial Files")}}</div>
                        <v-btn @click="$router.push('/geospatial-features')" outlined small>
                            <v-icon left>mdi-arrow-left</v-icon>
                            {{$t("Back to Features")}}
                        </v-btn>
                    </v-card-title>
                    <v-card-text>
                        <div class="form-container-x">
                            <p>{{$t("Upload one or more geospatial files to create features")}}</p>
                            <p class="text-muted small">{{$t("Supported formats")}}: {{supported_formats.join(', ').toUpperCase()}}</p>
                            
                            <v-card @drop.prevent="addFile" @dragover.prevent class="elevation-2 border p-2 mb-2 bg-light text-center">
                                <div class="p-2">
                                    <v-icon x-large>mdi-upload</v-icon>
                                    <strong>{{$t("Drag and drop geospatial files here")}}</strong>
                                </div>
                                
                                <div class="custom-file" style="max-width:300px;">
                                    <input type="file" class="custom-file-input" id="customFile" multiple @change="addFile($event)">
                                    <label class="custom-file-label" for="customFile">{{$t("Choose files")}}</label>
                                </div>
                            </v-card>
                            
                            <v-card class="files-container mt-3 mb-3 elevation-2" v-if="files.length>0">
                                <v-card-title class="d-flex justify-space-between">
                                    <div>{{files.length}} {{$t("selected")}}</div>
                                    <v-btn @click="clearFiles" text small color="error">
                                        <v-icon left>mdi-delete</v-icon>
                                        {{$t("Clear All")}}
                                    </v-btn>
                                </v-card-title>
                                <v-simple-table class="table-striped">
                                    <template v-slot:default>
                                        <thead>
                                            <tr>
                                                <th class="text-left">{{$t("File")}}</th>
                                                <th class="text-left">{{$t("Type")}}</th>
                                                <th class="text-left">{{$t("Size")}}</th>
                                                <th class="text-left">{{$t("Status")}}</th>
                                                <th class="text-left">{{$t("Actions")}}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(file, file_index) in files" :key="file.name">
                                                <td>
                                                    <div class="d-flex align-center">
                                                        <v-icon :color="getFileTypeColor(file.type)" class="mr-2">
                                                            {{getFileTypeIcon(file.type)}}
                                                        </v-icon>
                                                        {{file.name}}
                                                    </div>
                                                </td>
                                                <td>
                                                    <v-chip :color="getFileTypeColor(file.type)" small outlined>
                                                        {{file.type.toUpperCase()}}
                                                    </v-chip>
                                                </td>
                                                <td>{{formatFileSize(file.size)}}</td>
                                                <td>
                                                    <v-chip v-if="file.status === 'pending'" color="grey" small>
                                                        <v-icon left small>mdi-clock-outline</v-icon>
                                                        {{$t("Pending")}}
                                                    </v-chip>
                                                    <v-chip v-else-if="file.status === 'processing'" color="blue" small>
                                                        <v-icon left small>mdi-sync</v-icon>
                                                        {{$t("Processing")}}
                                                    </v-chip>
                                                    <v-chip v-else-if="file.status === 'completed'" color="success" small>
                                                        <v-icon left small>mdi-check-circle</v-icon>
                                                        {{$t("Completed")}}
                                                    </v-chip>
                                                    <v-chip v-else-if="file.status === 'failed'" color="error" small>
                                                        <v-icon left small>mdi-alert-circle</v-icon>
                                                        {{$t("Failed")}}
                                                    </v-chip>
                                                </td>
                                                <td>
                                                    <v-btn icon small @click="removeFile(file_index)" color="error">
                                                        <v-icon>mdi-delete</v-icon>
                                                    </v-btn>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </template>
                                </v-simple-table>
                            </v-card>
                            
                            <div class="d-flex justify-space-between">
                                <v-btn @click="$router.push('/geospatial-features')" outlined>
                                    {{$t("Cancel")}}
                                </v-btn>
                                <v-btn 
                                    color="primary" 
                                    :disabled="!canProcess" 
                                    @click="processImport"
                                    :loading="is_processing">
                                    <v-icon left>mdi-upload</v-icon>
                                    {{$t("Import Files")}}
                                </v-btn>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </div>

            <!-- Processing Dialog -->
            <v-dialog v-model="dialog_process" width="800" height="700" persistent style="z-index:5000">
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        {{$t("Import Geospatial Files")}}
                    </v-card-title>

                    <v-card-text>
                        <div>
                            <!-- Success State -->
                            <v-row class="mt-3 text-center" v-if="update_status=='completed' && !has_errors">
                                <v-col class="text-center">
                                    <v-icon large color="success">mdi-check-circle</v-icon>
                                    <div class="mt-2">{{$t("Import completed successfully")}}</div>
                                </v-col>
                            </v-row>
                            
                            <!-- Error State -->
                            <v-alert type="error" v-if="has_errors" class="mt-3">
                                <div class="text-h6">{{$t("Import Failed")}}</div>
                                <div class="mt-2">{{update_status}}</div>
                                <div v-if="errors" class="mt-2" style="white-space: pre-wrap;">{{errors}}</div>
                            </v-alert>
                            
                            <!-- Processing State -->
                            <v-container v-if="update_status!='completed' && !has_errors">
                                <div class="text-center">
                                    <v-progress-circular indeterminate color="primary" size="64"></v-progress-circular>
                                    <div class="mt-3 text-h6">{{update_status}}</div>
                                    <div class="mt-4" v-if="is_processing">
                                        <v-btn 
                                            color="error" 
                                            outlined 
                                            @click="cancelImport">
                                            <v-icon left>mdi-cancel</v-icon>
                                            {{$t("Cancel Import")}}
                                        </v-btn>
                                    </div>
                                </div>
                            </v-container>

                            <div v-if="upload_report.length > 0" class="mt-3">
                                <h6>{{$t("Processing Report")}}</h6>
                                <v-simple-table dense>
                                    <template v-slot:default>
                                        <thead>
                                            <tr>
                                                <th class="text-left">{{$t("File/Layer")}}</th>
                                                <th class="text-left">{{$t("Status")}}</th>
                                                <th class="text-left">{{$t("Details")}}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="report in upload_report" :key="report.file_name + (report.layer_name || '')">
                                                <td>
                                                    <div class="d-flex align-center">
                                                        <v-icon :color="report.status === 'success' ? 'success' : 'error'" small class="mr-2">
                                                            {{report.status === 'success' ? 'mdi-check-circle' : 'mdi-alert-circle'}}
                                                        </v-icon>
                                                        <div>
                                                            <div class="font-weight-medium">{{report.file_name}}</div>
                                                            <div v-if="report.layer_name" class="text-caption text-grey">
                                                                Layer: {{report.layer_name}}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <v-chip :color="report.status === 'success' ? 'success' : 'error'" small>
                                                        {{report.status}}
                                                    </v-chip>
                                                </td>
                                                <td>
                                                    <div v-if="report.status === 'success'">
                                                        <div class="text-body-2">
                                                            {{$t("Feature created")}}: {{report.feature_id}}
                                                        </div>
                                                        <div v-if="report.type === 'extracted'" class="text-caption text-grey">
                                                            {{$t("Extracted from")}}: {{report.original_zip}}
                                                        </div>
                                                    </div>
                                                    <div v-else-if="report.error" class="text-error">
                                                        <div class="font-weight-medium">{{report.context || 'Error'}}:</div>
                                                        <div class="text-body-2">{{report.error}}</div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </template>
                                </v-simple-table>
                            </div>
                        </div>
                    </v-card-text>

                    <v-divider></v-divider>

                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn color="primary" text @click="dialogClose">
                            {{$t("Close")}}
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <!-- Layer Selection Dialog -->
            <v-dialog v-model="show_layer_dialog" max-width="800px" persistent>
                <v-card>
                    <v-card-title>
                        <div class="d-flex justify-space-between align-center">
                            <div>{{$t("Select Layers to Import")}}</div>                            
                        </div>
                    </v-card-title>

                    <v-card-text>
                        <div v-if="pendingLayerData && pendingLayerData.layers">
                            <div class="mb-3">
                                <v-btn small @click="selectAllLayers" class="mr-2">
                                    {{$t("Select All")}}
                                </v-btn>
                                <v-btn small @click="deselectAllLayers">
                                    {{$t("Deselect All")}}
                                </v-btn>
                            </div>

                            <v-list>
                                <v-list-item-group v-model="selectedLayers" multiple>
                                    <v-list-item 
                                        v-for="layer in pendingLayerData.layers" 
                                        :key="layer.id || layer.name"
                                        :value="layer.id || layer.name"
                                    >
                                        <template v-slot:default="{ active }">
                                            <v-list-item-action>
                                                <v-checkbox :input-value="active"></v-checkbox>
                                            </v-list-item-action>

                                            <v-list-item-content>
                                                <v-list-item-title>{{layer.name || layer.layer_name}}</v-list-item-title>
                                                <v-list-item-subtitle>
                                                    <div class="text-caption">
                                                        <strong>{{$t("File")}}:</strong> {{layer.file_path ? layer.file_path.split('/').pop() : 'Unknown'}}
                                                        <span v-if="layer.file_info && layer.file_info.file_size">
                                                            | <strong>{{$t("File Size")}}:</strong> {{layer.file_info.file_size.size.toFixed(2)}} {{layer.file_info.file_size.unit}}
                                                        </span>
                                                        <span v-else>
                                                            | <strong>{{$t("Status")}}:</strong> {{$t("Metadata will be loaded after selection")}}
                                                        </span>
                                                    </div>
                                                </v-list-item-subtitle>
                                            </v-list-item-content>
                                        </template>
                                    </v-list-item>
                                </v-list-item-group>
                            </v-list>                           
                        </div>
                    </v-card-text>

                    <v-divider></v-divider>

                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn text @click="cancelLayerSelection">
                            {{is_processing ? $t("Cancel Processing") : $t("Cancel")}}
                        </v-btn>
                        <v-btn color="primary" @click="confirmLayerSelection" :disabled="selectedLayers.length === 0 || is_processing" :loading="is_processing">
                            {{$t("Import Selected Layers")}} ({{selectedLayers.length}})
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </div>
    `
});
