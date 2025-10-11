/// Geospatial feature edit component
Vue.component('geospatial-feature-edit', {
    props: ['feature_name', 'feature_id', 'value'],
    data: function () {    
        return {
            loading: false,
            feature: null,
            form_data: {},
            errors: '',
            success_message: '',
            map: null,
            mapContainer: null
        }
    },
    mounted: function() {
        this.loadFeature();
    },
    beforeDestroy: function() {
        this.destroyMap();
    },
    watch: {
        hasMapData: function(newVal) {
            if (newVal) {
                this.$nextTick(() => {
                    this.initializeMap();
                });
            } else {
                this.destroyMap();
            }
        }
    },
    computed: {
        boundingBox() {
            if (!this.form_data.metadata || !this.form_data.metadata.layer_info) {
                return null;
            }
            const bbox = this.form_data.metadata.layer_info.geographicBoundingBox;
            if (!bbox) return null;
            
            return [
                [bbox.southBoundLatitude, bbox.westBoundLongitude], // Southwest
                [bbox.northBoundLatitude, bbox.eastBoundLongitude]  // Northeast
            ];
        },
        crsInfo() {
            if (!this.form_data.metadata || !this.form_data.metadata.crs) {
                return null;
            }
            return this.form_data.metadata.crs;
        },
        hasMapData() {
            return this.boundingBox !== null;
        }
    },
    methods: {
        loadFeature: function() {
            this.loading = true;
            this.errors = '';
            
            // Check if we have a value prop (inline editing)
            if (this.value && Object.keys(this.value).length > 0) {
                this.feature = this.value;
                this.form_data = { ...this.value };
                this.loading = false;
                
                // Parse metadata if it's a string
                if (typeof this.form_data.metadata === 'string') {
                    try {
                        this.form_data.metadata = JSON.parse(this.form_data.metadata);
                    } catch (e) {
                        console.warn('Could not parse metadata JSON:', e);
                    }
                }
                return;
            }
            
            // Get feature ID or name from props or route params
            const featureId = this.feature_id || this.$route?.params?.id;
            const featureName = this.feature_name || this.$route?.params?.feature_name;
            
            if (!featureId && !featureName) {
                this.errors = 'Feature ID or name not provided';
                this.loading = false;
                return;
            }
            
            // Load feature data from the store or API
            if (this.$store && this.$store.state.geospatial_features && this.$store.state.geospatial_features.length > 0) {
                const features = this.$store.state.geospatial_features;
                
                if (featureId) {
                    this.feature = features.find(f => f.id == featureId);
                } else {
                    this.feature = features.find(f => f.name === featureName || f.code === featureName);
                }
                
                if (this.feature) {
                    this.form_data = { ...this.feature };
                    // Parse metadata if it's a string
                    if (typeof this.form_data.metadata === 'string') {
                        try {
                            this.form_data.metadata = JSON.parse(this.form_data.metadata);
                        } catch (e) {
                            console.warn('Could not parse metadata JSON:', e);
                        }
                    }
                    // Initialize map after loading feature data
                    this.$nextTick(() => {
                        this.initializeMap();
                    });
                } else {
                    // Feature not found in store, try loading from API
                    this.loadFeatureFromAPI(featureId);
                    return;
                }
            } else {
                // Store is empty, load from API
                this.loadFeatureFromAPI(featureId);
                return;
            }
            
            this.loading = false;
        },
        
        loadFeatureFromAPI: function(featureId) {
            if (!featureId) {
                this.errors = 'Feature ID not provided';
                this.loading = false;
                return;
            }
            
            const projectId = this.$store.state.project_id;
            const url = CI.base_url + '/api/geospatial-features/' + projectId + '/' + featureId;
            
            axios.get(url)
            .then(response => {
                if (response.data && response.data.status === 'success' && response.data.feature) {
                    this.feature = response.data.feature;
                    this.form_data = { ...this.feature };
                    
                    // Parse metadata if it's a string
                    if (typeof this.form_data.metadata === 'string') {
                        try {
                            this.form_data.metadata = JSON.parse(this.form_data.metadata);
                        } catch (e) {
                            console.warn('Could not parse metadata JSON:', e);
                        }
                    }
                    
                    // Initialize map after loading feature data
                    this.$nextTick(() => {
                        this.initializeMap();
                    });
                } else {
                    this.errors = 'Feature not found';
                }
                this.loading = false;
            })
            .catch(error => {
                console.error('Error loading feature:', error);
                this.errors = 'Error loading feature: ' + (error.response?.data?.message || error.message);
                this.loading = false;
            });
        },
        
        initializeMap: function() {
            if (!this.hasMapData) return;
            
            this.$nextTick(() => {
                const mapContainer = document.getElementById('geospatial-map');
                if (!mapContainer) return;
                
                // Check if container already has a map instance
                if (mapContainer._leaflet_id) {
                    this.destroyMap();
                }
                
                // Initialize Leaflet map
                this.map = L.map('geospatial-map').setView([0, 0], 2);
                
                // Add OpenStreetMap tiles
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(this.map);
                
                // Add bounding box rectangle
                if (this.boundingBox) {
                    const rectangle = L.rectangle(this.boundingBox, {
                        color: '#ff7800',
                        weight: 2,
                        fillColor: '#ff7800',
                        fillOpacity: 0.2
                    }).addTo(this.map);
                    
                    // Fit map to bounding box
                    this.map.fitBounds(this.boundingBox);
                    
                    // Add popup with bounding box info
                    const bbox = this.form_data.metadata.layer_info.geographicBoundingBox;
                    const popupContent = `
                        <div>
                            <strong>Geographic Bounding Box</strong><br>
                            North: ${bbox.northBoundLatitude.toFixed(6)}°<br>
                            South: ${bbox.southBoundLatitude.toFixed(6)}°<br>
                            East: ${bbox.eastBoundLongitude.toFixed(6)}°<br>
                            West: ${bbox.westBoundLongitude.toFixed(6)}°<br>
                            <br>
                            <strong>Layer Info</strong><br>
                            Rows: ${this.form_data.metadata.layer_info.rows}<br>
                            Columns: ${this.form_data.metadata.layer_info.columns}
                        </div>
                    `;
                    rectangle.bindPopup(popupContent);
                }
            });
        },
        
        destroyMap: function() {
            if (this.map) {
                this.map.remove();
                this.map = null;
            }
            
            // Also clean up the container's Leaflet ID
            const mapContainer = document.getElementById('geospatial-map');
            if (mapContainer && mapContainer._leaflet_id) {
                delete mapContainer._leaflet_id;
            }
        },
        
        
        saveFeature: function() {
            this.loading = true;
            this.errors = '';
            this.success_message = '';
            
            // Prepare data for saving
            const saveData = { ...this.form_data };
            
            // Convert metadata back to string if it's an object
            if (typeof saveData.metadata === 'object') {
                saveData.metadata = JSON.stringify(saveData.metadata, null, 2);
            }
            
            // Call API to update feature
            const url = CI.base_url + '/api/geospatial-features/' + this.form_data.id;
            
            axios.put(url, saveData)
            .then(response => {
                this.success_message = 'Feature updated successfully';
                this.loading = false;
                
                // Refresh the features list
                if (this.$store && this.$store.dispatch) {
                    this.$store.dispatch('loadGeospatialFeatures', { dataset_id: this.$route?.params?.index });
                }
            })
            .catch(error => {
                this.errors = error.response?.data?.message || 'Failed to update feature';
                this.loading = false;
            });
        },
        
        exitEdit: function() {
            // Navigate back to features list
            this.$router.push('/geospatial-features');
        },
        
        formatFileSize: function(bytes) {
            if (!bytes) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        getFileTypeIcon: function(fileType) {
            const iconMap = {
                'geojson': 'mdi-map-marker',
                'shp': 'mdi-vector-polygon',
                'gpkg': 'mdi-database',
                'tiff': 'mdi-image',
                'geotiff': 'mdi-image',
                'kml': 'mdi-earth',
                'kmz': 'mdi-earth',
                'gpx': 'mdi-map-marker-path',
                'zip': 'mdi-archive'
            };
            return iconMap[fileType] || 'mdi-file';
        },
        
        formatMetadataJson: function(metadata) {
            if (!metadata) return '';
            
            try {
                // If it's already an object, stringify it
                if (typeof metadata === 'object') {
                    return JSON.stringify(metadata, null, 2);
                }
                // If it's a string, try to parse and re-stringify for formatting
                const parsed = JSON.parse(metadata);
                return JSON.stringify(parsed, null, 2);
            } catch (e) {
                // If parsing fails, return the original string
                return metadata;
            }
        }
    },
    template: `
        <div class="geospatial-feature-edit-component">
            <style>
                .metadata-json-display {
                    background-color: #f5f5f5;
                    border: 1px solid #e0e0e0;
                    border-radius: 4px;
                    padding: 16px;
                    max-height: 400px;
                    overflow-y: auto;
                    font-family: 'Courier New', monospace;
                    font-size: 12px;
                    line-height: 1.4;
                    white-space: pre-wrap;
                    word-wrap: break-word;
                }
            </style>
            <div class="m-3">
                <v-row>
                    <v-col cols="12">
                        <v-card>
                            <v-card-title class="d-flex justify-space-between">
                                <div>
                                    <v-icon class="mr-2">{{getFileTypeIcon(form_data.file_type)}}</v-icon>
                                    {{$t("Edit Geospatial Feature")}}: {{form_data.name || 'Loading...'}}
                                </div>
                                <div>
                                    <v-btn color="primary" @click="saveFeature" :loading="loading" :disabled="!form_data.id">
                                        <v-icon left>mdi-content-save</v-icon>
                                        {{$t("Save")}}
                                    </v-btn>
                                    <v-btn @click="exitEdit" class="ml-2">
                                        <v-icon left>mdi-arrow-left</v-icon>
                                        {{$t("Back to Features")}}
                                    </v-btn>
                                </div>
                            </v-card-title>
                            
                            <v-card-text>
                                <v-alert v-if="errors" type="error" class="mb-4">
                                    {{errors}}
                                </v-alert>
                                
                                <v-alert v-if="success_message" type="success" class="mb-4">
                                    {{success_message}}
                                </v-alert>
                                
                                <v-progress-linear v-if="loading" indeterminate></v-progress-linear>
                                
                                <div v-if="!loading && feature">
                                    <v-row>
                                        <v-col cols="12" md="6">
                                            <v-text-field
                                                v-model="form_data.name"
                                                :label="$t('Feature Name')"
                                                required
                                                outlined
                                                dense
                                            ></v-text-field>
                                        </v-col>
                                        <v-col cols="12" md="6">
                                            <v-text-field
                                                v-model="form_data.code"
                                                :label="$t('Feature Code')"
                                                outlined
                                                dense
                                            ></v-text-field>
                                        </v-col>
                                    </v-row>
                                    
                                    <v-row>
                                        <v-col cols="12" md="6">
                                            <v-text-field
                                                v-model="form_data.file_name"
                                                :label="$t('File Name')"
                                                readonly
                                                outlined
                                                dense
                                            ></v-text-field>
                                        </v-col>
                                        <v-col cols="12" md="6">
                                            <v-text-field
                                                v-model="form_data.file_type"
                                                :label="$t('File Type')"
                                                readonly
                                                outlined
                                                dense
                                            ></v-text-field>
                                        </v-col>
                                    </v-row>
                                    
                                    <v-row>
                                        <v-col cols="12" md="6">
                                            <v-text-field
                                                v-model="form_data.layer_name"
                                                :label="$t('Layer Name')"
                                                readonly
                                                outlined
                                                dense
                                            ></v-text-field>
                                        </v-col>
                                        <v-col cols="12" md="6">
                                            <v-text-field
                                                v-model="form_data.feature_count"
                                                :label="$t('Feature Count')"
                                                readonly
                                                outlined
                                                dense
                                            ></v-text-field>
                                        </v-col>
                                    </v-row>
                                    
                                    <v-row>
                                        <v-col cols="12" md="6">
                                            <v-text-field
                                                v-model="form_data.geometry_type"
                                                :label="$t('Geometry Type')"
                                                readonly
                                                outlined
                                                dense
                                            ></v-text-field>
                                        </v-col>
                                        <v-col cols="12" md="6">
                                            <v-text-field
                                                :value="formatFileSize(form_data.file_size)"
                                                :label="$t('File Size')"
                                                readonly
                                                outlined
                                                dense
                                            ></v-text-field>
                                        </v-col>
                                    </v-row>                                                                        
                                    
                                    <!-- Map Visualization -->
                                    <v-row v-if="hasMapData">
                                        <v-col cols="12">
                                            <v-card outlined>
                                                <v-card-title>
                                                    <v-icon class="mr-2">mdi-map</v-icon>
                                                    {{$t('Geographic Extent')}}
                                                </v-card-title>
                                                <v-card-text>
                                                    <div id="geospatial-map" style="height: 400px; width: 100%;"></div>
                                                    
                                                    <!-- Bounding Box Table -->
                                                    <div v-if="boundingBox" class="mt-4">
                                                        <div class="text-subtitle-2 mb-2">
                                                            <v-icon small class="mr-1">mdi-vector-square</v-icon>
                                                            {{$t('Bounding Box Coordinates')}}
                                                        </div>
                                                        <v-simple-table dense>
                                                            <template v-slot:default>
                                                                <tbody>
                                                                    <tr>
                                                                        <td class="font-weight-medium">{{$t('North Latitude')}}</td>
                                                                        <td>{{form_data.metadata.layer_info.geographicBoundingBox.northBoundLatitude.toFixed(6)}}°</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="font-weight-medium">{{$t('South Latitude')}}</td>
                                                                        <td>{{form_data.metadata.layer_info.geographicBoundingBox.southBoundLatitude.toFixed(6)}}°</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="font-weight-medium">{{$t('East Longitude')}}</td>
                                                                        <td>{{form_data.metadata.layer_info.geographicBoundingBox.eastBoundLongitude.toFixed(6)}}°</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="font-weight-medium">{{$t('West Longitude')}}</td>
                                                                        <td>{{form_data.metadata.layer_info.geographicBoundingBox.westBoundLongitude.toFixed(6)}}°</td>
                                                                    </tr>
                                                                </tbody>
                                                            </template>
                                                        </v-simple-table>
                                                    </div>
                                                </v-card-text>
                                            </v-card>
                                        </v-col>
                                    </v-row>
                                    
                                    <!-- CRS Information Panel -->
                                    <v-row v-if="crsInfo">
                                        <v-col cols="12">
                                            <v-card outlined>
                                                <v-card-title class="text-subtitle-1">
                                                    <v-icon class="mr-2">mdi-information</v-icon>
                                                    {{$t('Coordinate Reference System (CRS)')}}
                                                </v-card-title>
                                                <v-card-text>
                                                    <v-row>
                                                        <v-col cols="12" md="6">
                                                            <v-text-field
                                                                :value="crsInfo.name"
                                                                label="CRS Name"
                                                                readonly
                                                                outlined
                                                                dense
                                                            ></v-text-field>
                                                        </v-col>
                                                        <v-col cols="12" md="6">
                                                            <v-text-field
                                                                :value="crsInfo.type"
                                                                label="CRS Type"
                                                                readonly
                                                                outlined
                                                                dense
                                                            ></v-text-field>
                                                        </v-col>
                                                    </v-row>
                                                    
                                                    <v-row v-if="crsInfo.id">
                                                        <v-col cols="12" md="6">
                                                            <v-text-field
                                                                :value="crsInfo.id.code"
                                                                label="EPSG Code"
                                                                readonly
                                                                outlined
                                                                dense
                                                            ></v-text-field>
                                                        </v-col>
                                                        <v-col cols="12" md="6">
                                                            <v-text-field
                                                                :value="crsInfo.id.authority"
                                                                label="Authority"
                                                                readonly
                                                                outlined
                                                                dense
                                                            ></v-text-field>
                                                        </v-col>
                                                    </v-row>
                                                    
                                                    <v-row v-if="crsInfo.area">
                                                        <v-col cols="12">
                                                            <v-textarea
                                                                :value="crsInfo.area"
                                                                label="Coverage Area"
                                                                readonly
                                                                outlined
                                                                dense
                                                                rows="2"
                                                            ></v-textarea>
                                                        </v-col>
                                                    </v-row>
                                                    
                                                    <v-row v-if="crsInfo.scope">
                                                        <v-col cols="12">
                                                            <v-textarea
                                                                :value="crsInfo.scope"
                                                                label="Scope"
                                                                readonly
                                                                outlined
                                                                dense
                                                                rows="2"
                                                            ></v-textarea>
                                                        </v-col>
                                                    </v-row>
                                                </v-card-text>
                                            </v-card>
                                        </v-col>
                                    </v-row>
                                    
                                    <v-row v-if="form_data.metadata">
                                        <v-col cols="12">
                                            <v-card outlined style="height: 400px; overflow-y: auto;">
                                                <v-card-title class="text-subtitle-1">
                                                    <v-icon class="mr-2">mdi-code-json</v-icon>
                                                    {{$t('Metadata')}}
                                                </v-card-title>
                                                <v-card-text>
                                                    <pre class="metadata-json-display">{{formatMetadataJson(form_data.metadata)}}</pre>
                                                </v-card-text>
                                            </v-card>
                                        </v-col>
                                    </v-row>
                                </div>
                                
                                <div v-else-if="!loading && !feature" class="text-center py-8">
                                    <v-icon size="64" color="grey">mdi-alert-circle</v-icon>
                                    <div class="text-h6 mt-4">{{$t("Feature not found")}}</div>
                                    <div class="text-body-2 text--secondary">{{$t("The requested geospatial feature could not be found.")}}</div>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </div>
        </div>
    `
});
