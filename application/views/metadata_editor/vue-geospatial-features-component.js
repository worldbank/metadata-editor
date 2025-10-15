/// Geospatial features list component
Vue.component('geospatial-features', {
    props: ['index'],
    data: function () {    
        return {
            selected_features: [],
            select_all_features: false,
            dialog: {
                show: false,
                title: '',
                loading_message: '',
                message_success: '',
                message_error: '',
                is_loading: false
            },
            refresh_dialog: {
                show: false,
                feature_name: '',
                is_refreshing: false,
                status_message: '',
                error_message: '',
                job_id: null,
                feature_id: null
            },
            search_keywords: '',
            sort_by: 'name',
            sort_order: 'ASC'
        }
    },
    mounted: function(){
        if (this.isGeospatialProject) {
            this.loadGeospatialFeatures();
        }
    },
    methods: {
        loadGeospatialFeatures: function() {
            vm = this;
            let url = CI.base_url + '/api/geospatial-features/' + this.ProjectID;
            
            axios.get(url)
            .then(function (response) {
                if (response.data.status === 'success') {
                    vm.$store.commit('setGeospatialFeatures', response.data.features);
                }
            })
            .catch(function (error) {
                console.error('Failed to load geospatial features:', error);
            });
        },
        
        
        editFeature: function(index) {
            const feature = this.geospatialFeatures[index];
            if (feature && feature.id) {
                this.$router.push('/geospatial-features/edit/' + feature.id);
            }
        },
        
        
        deleteFeature: function(index) {
            if (!confirm(this.$t("confirm_delete") + ' ' + this.geospatialFeatures[index].name)) {
                return;
            }
            
            vm = this;
            let feature = this.geospatialFeatures[index];
            let url = CI.base_url + '/api/geospatial-features/' + feature.id;
            
            axios.delete(url)
            .then(function (response) {
                if (response.data.status === 'success') {
                    vm.loadGeospatialFeatures();
                }
            })
            .catch(function (error) {
                console.error('Failed to delete geospatial feature:', error);
            });
        },
        
        refreshFeatureMetadata: function(index) {
            const feature = this.geospatialFeatures[index];
            
            if (!feature || !feature.id) {
                return;
            }
            
            if (!feature.file_name) {
                alert(this.$t('Cannot refresh metadata: Feature has no associated file'));
                return;
            }
            
            if (!confirm(this.$t('Refresh metadata for') + ' "' + feature.name + '"? ' + this.$t('This will reload all metadata and characteristics from the geospatial file.'))) {
                return;
            }
            
            // Show refresh dialog
            this.refresh_dialog.show = true;
            this.refresh_dialog.feature_name = feature.name;
            this.refresh_dialog.is_refreshing = true;
            this.refresh_dialog.status_message = this.$t('Starting metadata refresh...');
            this.refresh_dialog.error_message = '';
            this.refresh_dialog.feature_id = feature.id;
            this.refresh_dialog.job_id = null;
            
            // Start metadata refresh job
            this.startMetadataRefresh(feature.id);
        },
        
        startMetadataRefresh: function(featureId) {
            const vm = this;
            const url = CI.base_url + '/api/geospatial_features/metadata_refresh/' + this.ProjectID;
            
            axios.post(url, { feature_id: featureId })
            .then(function (response) {
                if (response.data.status === 'success') {
                    vm.refresh_dialog.job_id = response.data.job_id;
                    vm.refresh_dialog.status_message = vm.$t('Metadata extraction in progress...');
                    
                    // Start polling for completion
                    vm.pollRefreshStatus();
                } else {
                    vm.refresh_dialog.is_refreshing = false;
                    vm.refresh_dialog.error_message = response.data.message || vm.$t('Failed to start metadata refresh');
                }
            })
            .catch(function (error) {
                console.error('Failed to start metadata refresh:', error);
                vm.refresh_dialog.is_refreshing = false;
                vm.refresh_dialog.error_message = error.response?.data?.message || vm.$t('Failed to start metadata refresh');
            });
        },
        
        pollRefreshStatus: function() {
            const vm = this;
            const url = CI.base_url + '/api/geospatial_features/metadata_refresh_status/' + this.ProjectID + 
                       '?job_id=' + this.refresh_dialog.job_id + 
                       '&feature_id=' + this.refresh_dialog.feature_id;
            
            axios.get(url)
            .then(function (response) {
                if (response.data.status === 'success') {
                    // Refresh completed successfully
                    vm.refresh_dialog.is_refreshing = false;
                    vm.refresh_dialog.status_message = vm.$t('Metadata refreshed successfully!') + ' ' + 
                                                       vm.$t('Characteristics updated') + ': ' + 
                                                       response.data.characteristics_updated;
                    
                    // Reload features list
                    vm.loadGeospatialFeatures();
                } else if (response.data.status === 'processing') {
                    // Still processing, poll again after 2 seconds
                    vm.refresh_dialog.status_message = vm.$t('Processing metadata...') + ' (' + response.data.job_status + ')';
                    setTimeout(function() {
                        vm.pollRefreshStatus();
                    }, 2000);
                } else {
                    // Error occurred
                    vm.refresh_dialog.is_refreshing = false;
                    vm.refresh_dialog.error_message = response.data.message || vm.$t('Metadata refresh failed');
                }
            })
            .catch(function (error) {
                console.error('Failed to check refresh status:', error);
                vm.refresh_dialog.is_refreshing = false;
                vm.refresh_dialog.error_message = error.response?.data?.message || vm.$t('Failed to check refresh status');
            });
        },
        
        closeRefreshDialog: function() {
            this.refresh_dialog.show = false;
            this.refresh_dialog.is_refreshing = false;
            this.refresh_dialog.status_message = '';
            this.refresh_dialog.error_message = '';
            this.refresh_dialog.job_id = null;
            this.refresh_dialog.feature_id = null;
        },
        
        batchDelete: function() {
            if (this.selected_features.length === 0) {
                alert(this.$t("Please select features to delete"));
                return;
            }
            
            if (!confirm(this.$t("confirm_delete") + ' ' + this.selected_features.length + ' ' + this.$t("selected"))) {
                return;
            }
            
            vm = this;
            let deleted_count = 0;
            let errors = [];
            
            // Delete each feature individually
            this.selected_features.forEach(function(feature_id, index) {
                let url = CI.base_url + '/api/geospatial-features/' + feature_id;
                
                axios.delete(url)
                .then(function (response) {
                    deleted_count++;
                    if (deleted_count === vm.selected_features.length) {
                        // All deletions completed
                        vm.loadGeospatialFeatures();
                        vm.selected_features = [];
                        vm.select_all_features = false;
                        
                        if (errors.length > 0) {
                            alert('Deleted ' + deleted_count + ' features. Errors: ' + errors.join(', '));
                        } else {
                            alert('Successfully deleted ' + deleted_count + ' features');
                        }
                    }
                })
                .catch(function (error) {
                    errors.push('Feature ' + feature_id + ': ' + (error.response?.data?.message || 'Delete failed'));
                    deleted_count++;
                    
                    if (deleted_count === vm.selected_features.length) {
                        // All deletions completed (with some errors)
                        vm.loadGeospatialFeatures();
                        vm.selected_features = [];
                        vm.select_all_features = false;
                        
                        if (errors.length > 0) {
                            alert('Deleted ' + (vm.selected_features.length - errors.length) + ' features. Errors: ' + errors.join(', '));
                        } else {
                            alert('Successfully deleted ' + deleted_count + ' features');
                        }
                    }
                });
            });
        },
        
        toggleFeaturesSelection: function() {
            if (this.select_all_features) {
                this.selected_features = this.geospatialFeatures.map(feature => feature.id);
            } else {
                this.selected_features = [];
            }
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
        
        getStatusColor: function(status) {
            const colorMap = {
                'pending': 'warning',
                'processing': 'info',
                'completed': 'success',
                'failed': 'error'
            };
            return colorMap[status] || 'grey';
        },
        
        getStatusIcon: function(status) {
            const iconMap = {
                'pending': 'mdi-clock-outline',
                'processing': 'mdi-sync',
                'completed': 'mdi-check-circle',
                'failed': 'mdi-alert-circle'
            };
            return iconMap[status] || 'mdi-help-circle';
        },
        
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        momentDate: function(date) {
            return moment.utc(date).format("YYYY-MM-DD HH:mm:ss");
        }
    },
    computed: {
        geospatialFeatures() {
            return this.$store.state.geospatial_features || [];
        },
        ProjectID() {
            return this.$store.state.project_id;
        },
        ProjectType() {
            return this.$store.state.project_type;
        },
        isGeospatialProject() {
            return this.ProjectType === 'geospatial';
        },
        isProjectEditable() {
            return this.$store.getters.getUserHasEditAccess;
        }
    },
    template: `
        <div class="geospatial-features-component">
            <div v-if="!isGeospatialProject" class="container-fluid pt-5 mt-5 mb-5 pb-5">
                <v-card>
                    <v-card-title>{{$t("geospatial_features")}}</v-card-title>
                    <v-card-text>
                        <v-alert text outlined color="warning" icon="mdi-alert">
                            {{$t("geospatial_features_only_available_for_geospatial_projects")}}
                        </v-alert>
                    </v-card-text>
                </v-card>
            </div>
            
            <div v-else class="container-fluid pt-5 mt-5 mb-5 pb-5">
                <v-card>
                    <v-card-title>{{$t("geospatial_features")}}</v-card-title>
                    <v-card-text>
                        <div>
                            <strong>{{geospatialFeatures.length}}</strong> {{$t("features")}}
                            
                            <v-row>
                                <v-col md="8">
                                    <button v-if="selected_features.length>0" 
                                            type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            @click="batchDelete">
                                        {{$t("Delete")}} {{selected_features.length}} {{$t("selected")}}
                                    </button>
                                </v-col>
                                <v-col md="4" align="right" class="mb-2">
                                    <v-btn color="primary" 
                                           :to="'geospatial-features/import'" 
                                           :disabled="!isProjectEditable"
                                           outlined small>
                                        {{$t("import_files")}}
                                    </v-btn>
                                </v-col>
                            </v-row>
                            
                            <table class="table table-striped" v-if="geospatialFeatures.length>0">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" 
                                                   v-model="select_all_features" 
                                                   @change="toggleFeaturesSelection" />
                                        </th>
                                        <th style="width:80px;">{{$t("feature")}}#</th>
                                        <th>{{$t("feature_name")}}</th>
                                        <th>{{$t("file_type")}}</th>
                                        <th>{{$t("file_size")}}</th>
                                        <th>{{$t("status")}}</th>
                                        <th>{{$t("Modified")}}</th>
                                        <th style="width: 50px;">{{$t("Actions")}}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(feature, index) in geospatialFeatures" :key="feature.id">
                                        <td>
                                            <input type="checkbox" 
                                                   v-model="selected_features" 
                                                   :value="feature.id" />
                                        </td>
                                        <td>
                                            <v-icon color="primary">mdi-map-marker</v-icon> 
                                            {{feature.id}}
                                        </td>
                                        <td>
                                            <div style="cursor:pointer;color:#0D47A1;font-weight:500" 
                                                 @click="editFeature(index)">
                                                {{feature.name}}
                                            </div>
                                            <!-- File name under feature name -->
                                            <div class="text-muted text-small mt-2" v-if="feature.file_name">                                                
                                                {{feature.file_name}}
                                            </div>
                                            <div v-else class="text-muted text-small mt-2">
                                                <v-icon color="grey" small class="mr-1">mdi-file-remove</v-icon>
                                                {{$t("no_file")}}
                                            </div>
                                        </td>
                                        <td>
                                            <v-chip v-if="feature.file_type" 
                                                   :color="getFileTypeColor(feature.file_type)" 
                                                   small outlined>
                                                {{feature.file_type.toUpperCase()}}
                                            </v-chip>
                                        </td>
                                        <td>
                                            <span v-if="feature.file_size">
                                                {{formatFileSize(feature.file_size)}}
                                            </span>
                                        </td>
                                        <td>
                                            <v-chip v-if="feature.upload_status" 
                                                   :color="getStatusColor(feature.upload_status)" 
                                                   small>
                                                <v-icon left small>{{getStatusIcon(feature.upload_status)}}</v-icon>
                                                {{feature.upload_status}}
                                            </v-chip>
                                        </td>
                                        <td>{{momentDate(feature.changed)}}</td>
                                        <td>
                                            <div class="zxaction-buttons-hover">
                                                <v-menu offset-y>
                                                    <template v-slot:activator="{ on, attrs }">
                                                        <v-btn small icon v-on="on" v-bind="attrs" 
                                                               :title="$t('More options')" 
                                                               color="primary">
                                                            <v-icon>mdi-dots-vertical</v-icon>
                                                        </v-btn>
                                                    </template>
                                                    
                                                    <v-list dense>
                                                        <v-list-item @click="editFeature(index)">
                                                            <v-list-item-icon>
                                                                <v-icon>mdi-file-edit</v-icon>
                                                            </v-list-item-icon>
                                                            <v-list-item-title>{{$t("edit")}}</v-list-item-title>
                                                        </v-list-item>
                                                        
                                                        <v-list-item :to="'/geospatial-features/' + feature.id + '/characteristics'"
                                                                   :title="$t('View characteristics')">
                                                            <v-list-item-icon>
                                                                <v-icon>mdi-table</v-icon>
                                                            </v-list-item-icon>
                                                            <v-list-item-title>{{$t("characteristics")}}</v-list-item-title>
                                                        </v-list-item>
                                                        
                                                        <v-list-item v-if="feature.file_name" 
                                                                   :to="'/geospatial-features/' + feature.id + '/view'"
                                                                   :title="$t('View feature')">
                                                            <v-list-item-icon>
                                                                <v-icon>mdi-eye</v-icon>
                                                            </v-list-item-icon>
                                                            <v-list-item-title>{{$t("view")}}</v-list-item-title>
                                                        </v-list-item>
                                                        
                                                        <v-divider></v-divider>
                                                        
                                                        <v-list-item v-if="feature.file_name" 
                                                                   @click="refreshFeatureMetadata(index)"
                                                                   :disabled="!isProjectEditable"
                                                                   :title="$t('Refresh metadata from file')">
                                                            <v-list-item-icon>
                                                                <v-icon color="blue">mdi-refresh</v-icon>
                                                            </v-list-item-icon>
                                                            <v-list-item-title class="blue--text">{{$t("Refresh")}}</v-list-item-title>
                                                        </v-list-item>
                                                        
                                                        <v-divider></v-divider>
                                                        
                                                        <v-list-item @click="deleteFeature(index)" class="red--text">
                                                            <v-list-item-icon>
                                                                <v-icon color="red">mdi-delete-outline</v-icon>
                                                            </v-list-item-icon>
                                                            <v-list-item-title class="red--text">{{$t("Delete")}}</v-list-item-title>
                                                        </v-list-item>
                                                    </v-list>
                                                </v-menu>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <div v-if="geospatialFeatures.length === 0" class="text-center mt-5">
                                <v-alert text outlined color="info" icon="mdi-information">
                                    {{$t("no_geospatial_features")}}
                                </v-alert>
                            </div>
                        </div>


                    </v-card-text>
                </v-card>
            </div>

            <!-- Dialog -->
            <v-dialog v-model="dialog.show" width="500" height="300" persistent>
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        {{dialog.title}}
                    </v-card-title>

                    <v-card-text>
                        <div>
                            <div v-if="dialog.is_loading">{{dialog.loading_message}}</div>
                            <v-app>
                                <v-progress-linear v-if="dialog.is_loading"
                                    indeterminate
                                    color="green">
                                </v-progress-linear>
                            </v-app>

                            <div class="alert alert-success" v-if="dialog.message_success" type="success">
                                {{dialog.message_success}}
                            </div>

                            <div class="alert alert-danger" v-if="dialog.message_error" type="error">
                                {{dialog.message_error}}
                            </div>
                        </div>
                    </v-card-text>

                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn color="primary" text @click="dialog.show=false" v-if="dialog.is_loading==false">
                            {{$t("close")}}
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <!-- Refresh Metadata Dialog -->
            <v-dialog v-model="refresh_dialog.show" width="600" persistent>
                <v-card>
                    <v-card-title class="text-h5 blue lighten-4">
                        <v-icon left color="blue">mdi-refresh</v-icon>
                        {{$t("Refresh Metadata")}}
                    </v-card-title>

                    <v-card-text class="pt-4">
                        <div>
                            <div class="mb-3">
                                <strong>{{$t("Feature")}}:</strong> {{refresh_dialog.feature_name}}
                            </div>
                            
                            <!-- Loading State -->
                            <div v-if="refresh_dialog.is_refreshing" class="text-center py-4">
                                <v-progress-circular indeterminate color="blue" size="64"></v-progress-circular>
                                <div class="mt-3 text-body-1">{{refresh_dialog.status_message}}</div>
                            </div>
                            
                            <!-- Success State -->
                            <v-alert v-if="!refresh_dialog.is_refreshing && !refresh_dialog.error_message && refresh_dialog.status_message" 
                                   type="success" 
                                   outlined 
                                   class="mt-3">
                                <div class="d-flex align-center">
                                    <v-icon left color="success" large>mdi-check-circle</v-icon>
                                    <div>{{refresh_dialog.status_message}}</div>
                                </div>
                            </v-alert>
                            
                            <!-- Error State -->
                            <v-alert v-if="refresh_dialog.error_message" 
                                   type="error" 
                                   outlined 
                                   class="mt-3">
                                <div class="d-flex align-center">
                                    <v-icon left color="error" large>mdi-alert-circle</v-icon>
                                    <div>
                                        <div class="text-h6">{{$t("Error")}}</div>
                                        <div class="mt-2">{{refresh_dialog.error_message}}</div>
                                    </div>
                                </div>
                            </v-alert>
                        </div>
                    </v-card-text>

                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn color="primary" 
                               text 
                               @click="closeRefreshDialog" 
                               :disabled="refresh_dialog.is_refreshing">
                            {{$t("Close")}}
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </div>
    `
});
