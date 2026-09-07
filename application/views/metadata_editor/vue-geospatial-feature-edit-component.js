/// Geospatial feature edit component
Vue.component('geospatial-feature-edit', {
    props: ['feature_name', 'feature_id', 'value'],
    data: function () {    
        return {
            loading: false,
            feature: null,
            form_data: {},
            original_data: {},
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

            if (typeof BoundingBoxUtil !== 'undefined') {
                return BoundingBoxUtil.leafletBoundsFromIso(
                    bbox.westBoundLongitude,
                    bbox.eastBoundLongitude,
                    bbox.southBoundLatitude,
                    bbox.northBoundLatitude
                );
            }
            
            return [
                [bbox.southBoundLatitude, bbox.westBoundLongitude], // Southwest
                [bbox.northBoundLatitude, bbox.eastBoundLongitude]  // Northeast
            ];
        },
        crsInfo() {
            if (!this.form_data.metadata) {
                return null;
            }
            
            // Check for PROJ JSON format (vector files)
            if (this.form_data.metadata.crs) {
                return this.form_data.metadata.crs;
            }
            
            // Check for WKT format (raster files)
            if (this.form_data.metadata.projection) {
                return this.parseWKT(this.form_data.metadata.projection);
            }
            
            return null;
        },
        hasMapData() {
            return this.boundingBox !== null;
        },
        hasUnsavedChanges() {
            if (!this.original_data || !this.form_data) return false;
            
            // Compare the editable fields only
            const editableFields = ['name', 'code', 'definition', 'is_abstract', 'aliases'];
            
            return editableFields.some(field => {
                const original = this.original_data[field];
                const current = this.form_data[field];
                
                // Handle arrays (aliases) separately
                if (field === 'aliases') {
                    const origArray = Array.isArray(original) ? original : [];
                    const currArray = Array.isArray(current) ? current : [];
                    return JSON.stringify(origArray.sort()) !== JSON.stringify(currArray.sort());
                }
                
                // Handle other fields
                return original !== current;
            });
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
                
                // Initialize new fields if not present
                this.initializeNewFields();
                
                // Store original data for comparison
                this.storeOriginalData();
                
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
                    
                    // Initialize new fields if not present
                    this.initializeNewFields();
                    
                    // Store original data for comparison
                    this.storeOriginalData();
                    
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
                    
                    // Initialize new fields if not present
                    this.initializeNewFields();
                    
                    // Store original data for comparison
                    this.storeOriginalData();
                    
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
                this.errors = this.$t('error_loading_feature') + ': ' + (error.response?.data?.message || error.message);
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
        
        /**
         * Parse WKT (Well-Known Text) format CRS string into a structured format
         * similar to PROJ JSON for consistent display
         */
        parseWKT: function(wktString) {
            if (!wktString || typeof wktString !== 'string') {
                return null;
            }
            
            try {
                const result = {
                    type: 'WKT',
                    name: null,
                    id: null,
                    datum: null,
                    units: null,
                    area: null,
                    scope: null,
                    original_wkt: wktString
                };
                
                // Extract CRS name (first quoted string after GEOGCS or PROJCS)
                const nameMatch = wktString.match(/^(GEOGCS|PROJCS)\["([^"]+)"/);
                if (nameMatch) {
                    result.name = nameMatch[2];
                    result.type = nameMatch[1] === 'GEOGCS' ? 'GeographicCRS' : 'ProjectedCRS';
                }
                
                // Extract EPSG code from AUTHORITY sections
                const authorityMatch = wktString.match(/AUTHORITY\["EPSG","(\d+)"\]\s*\]$/);
                if (authorityMatch) {
                    result.id = {
                        authority: 'EPSG',
                        code: parseInt(authorityMatch[1])
                    };
                }
                
                // Extract datum name
                const datumMatch = wktString.match(/DATUM\["([^"]+)"/);
                if (datumMatch) {
                    result.datum = datumMatch[1];
                }
                
                // Extract units
                const unitMatch = wktString.match(/UNIT\["([^"]+)",([^,\]]+)/);
                if (unitMatch) {
                    result.units = {
                        name: unitMatch[1],
                        conversion_factor: parseFloat(unitMatch[2])
                    };
                }
                
                // Extract spheroid/ellipsoid information
                const spheroidMatch = wktString.match(/SPHEROID\["([^"]+)",([^,]+),([^,\]]+)/);
                if (spheroidMatch) {
                    result.ellipsoid = {
                        name: spheroidMatch[1],
                        semi_major_axis: parseFloat(spheroidMatch[2]),
                        inverse_flattening: parseFloat(spheroidMatch[3])
                    };
                }
                
                // Add helpful descriptions based on common EPSG codes
                if (result.id && result.id.code) {
                    const epsgDescriptions = {
                        4326: {
                            area: 'World.',
                            scope: 'Horizontal component of 3D system. Used by GPS satellite navigation system.'
                        },
                        3857: {
                            area: 'World between 85.06°S and 85.06°N.',
                            scope: 'Web mapping and visualization. Used by Google Maps, OpenStreetMap, and other web mapping services.'
                        },
                        32601: {
                            area: 'Between 180°W and 174°W, northern hemisphere between equator and 84°N.',
                            scope: 'Navigation and medium accuracy spatial referencing.'
                        }
                        // Add more as needed
                    };
                    
                    // For UTM zones (32601-32660 for North, 32701-32760 for South)
                    const code = result.id.code;
                    if (code >= 32601 && code <= 32660) {
                        const zone = code - 32600;
                        const westLon = (zone - 1) * 6 - 180;
                        const eastLon = zone * 6 - 180;
                        result.area = `Between ${westLon}°E and ${eastLon}°E, northern hemisphere between equator and 84°N.`;
                        result.scope = 'Navigation and medium accuracy spatial referencing.';
                    } else if (code >= 32701 && code <= 32760) {
                        const zone = code - 32700;
                        const westLon = (zone - 1) * 6 - 180;
                        const eastLon = zone * 6 - 180;
                        result.area = `Between ${westLon}°E and ${eastLon}°E, southern hemisphere between 80°S and equator.`;
                        result.scope = 'Navigation and medium accuracy spatial referencing.';
                    } else if (epsgDescriptions[code]) {
                        result.area = epsgDescriptions[code].area;
                        result.scope = epsgDescriptions[code].scope;
                    }
                }
                
                return result;
            } catch (error) {
                console.error('Error parsing WKT:', error);
                return {
                    type: 'WKT',
                    name: this.$t('unknown_crs'),
                    original_wkt: wktString,
                    parse_error: error.message
                };
            }
        },
        
        saveFeature: function() {
            this.loading = true;
            this.errors = '';
            this.success_message = '';
            
            // Validate required fields
            if (!this.form_data.name || this.form_data.name.trim() === '') {
                this.errors = 'Type Name is required';
                this.loading = false;
                return;
            }
            
            if (!this.form_data.definition || this.form_data.definition.trim() === '') {
                this.errors = 'Definition is required';
                this.loading = false;
                return;
            }
            
            if (this.form_data.is_abstract === undefined || this.form_data.is_abstract === null) {
                this.errors = 'Is Abstract field is required';
                this.loading = false;
                return;
            }
            
            // Prepare data for saving
            const saveData = { ...this.form_data };
            
            // Convert metadata back to string if it's an object
            if (typeof saveData.metadata === 'object') {
                saveData.metadata = JSON.stringify(saveData.metadata, null, 2);
            }
            
            // Ensure aliases is properly formatted as JSON array
            if (saveData.aliases && Array.isArray(saveData.aliases)) {
                // Keep as array, the API will handle JSON encoding
            } else if (saveData.aliases && typeof saveData.aliases === 'string') {
                // Handle case where it might be a string
                try {
                    saveData.aliases = JSON.parse(saveData.aliases);
                } catch (e) {
                    saveData.aliases = [saveData.aliases];
                }
            } else {
                saveData.aliases = [];
            }
            
            // Ensure is_abstract is boolean
            saveData.is_abstract = saveData.is_abstract ? 1 : 0;
            
            // Call API to update feature
            const url = CI.base_url + '/api/geospatial-features/update/' + this.form_data.id;

            let vm = this;
            
            axios.post(url, saveData)
            .then(response => {
                this.success_message = 'Feature updated successfully';
                this.loading = false;
                
                // Update original data to reflect saved state
                this.storeOriginalData();
                
                // Refresh the features list
                if (this.$store && this.$store.dispatch) {
                    this.$store.dispatch('loadGeospatialFeatures', { dataset_id: vm.getProjectId() });
                }
                
                // Reload map
                this.$nextTick(() => {
                    this.destroyMap();
                    if (this.hasMapData) {
                        this.$nextTick(() => this.initializeMap());
                    }
                });
            })
            .catch(error => {
                this.errors = error.response?.data?.message || 'Failed to update feature';
                this.loading = false;
            });
        },

        getProjectId: function() {
            return this.$store.state.project_id;
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
        },
        
        initializeNewFields: function() {
            // Initialize new editable fields with default values if not present
            if (this.form_data.definition === undefined) {
                this.form_data.definition = '';
            }
            if (this.form_data.is_abstract === undefined || this.form_data.is_abstract === null) {
                this.form_data.is_abstract = false;
            }
            if (this.form_data.aliases === undefined || this.form_data.aliases === null) {
                this.form_data.aliases = [];
            } else if (typeof this.form_data.aliases === 'string') {
                // Handle JSON string from database
                try {
                    this.form_data.aliases = JSON.parse(this.form_data.aliases);
                } catch (e) {
                    this.form_data.aliases = [];
                }
            }
            // Ensure aliases is always an array
            if (!Array.isArray(this.form_data.aliases)) {
                this.form_data.aliases = [];
            }
        },
        
        storeOriginalData: function() {
            // Store a deep copy of the current form data as original data
            this.original_data = {
                name: this.form_data.name,
                code: this.form_data.code,
                definition: this.form_data.definition,
                is_abstract: this.form_data.is_abstract,
                aliases: Array.isArray(this.form_data.aliases) ? [...this.form_data.aliases] : []
            };
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
                
                .editable-section {
                    border-left: 4px solid #1976d2;
                    padding-left: 16px;
                }
                
                .readonly-section {
                    border-left: 4px solid #9e9e9e;
                    padding-left: 16px;
                }
                
                .editable-section .v-card {
                    border-left: 3px solid #1976d2 !important;
                }
                
                .readonly-section .v-card {
                    border-left: 3px solid #9e9e9e !important;
                }
            </style>
            <div class="m-3">
                <v-row>
                    <v-col cols="12">
                        <v-card>
                            <v-card-title class="d-flex justify-space-between">
                                <div>
                                    <v-icon class="mr-2">{{getFileTypeIcon(form_data.file_type)}}</v-icon>
                                    {{$t("edit_geospatial_feature")}}: {{form_data.name || $t('loading')}}
                                </div>
                                <div>
                                    <v-btn color="primary" @click="saveFeature" :loading="loading" :disabled="!form_data.id || !hasUnsavedChanges">
                                        <v-icon left>mdi-content-save</v-icon>
                                        {{$t("Save")}}
                                    </v-btn>
                                    <v-btn @click="exitEdit" class="ml-2">
                                        <v-icon left>mdi-arrow-left</v-icon>
                                        {{$t("back_to_features")}}
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
                                    <!-- Feature Catalogue Section - Editable Fields -->
                                    <v-row class="mb-4">
                                        <v-col cols="12">
                                            <div class="editable-section">
                                                <v-card outlined  class="mb-4 elevation-1">
                                                    <v-card-title class="text-h6 ">
                                                        <v-icon class="mr-2" color="white">mdi-pencil</v-icon>
                                                        {{$t('feature_catalogue')}}
                                                    </v-card-title>
                                                    <v-card-text class="pt-4">
                                                        <v-row>
                                                            <v-col cols="12" md="6">
                                                                <v-text-field
                                                                    v-model="form_data.name"
                                                                    :label="$t('type_name') + ' *'"
                                                                    :hint="$t('type_name_hint')"
                                                                    persistent-hint
                                                                    required
                                                                    :rules="[v => !!v || 'Type Name is required']"
                                                                    outlined
                                                                    dense
                                                                    color="primary"
                                                                    :prepend-icon="'mdi-pencil'"
                                                                ></v-text-field>
                                                            </v-col>
                                                            <v-col cols="12" md="6">
                                                                <v-text-field
                                                                    v-model="form_data.code"
                                                                    :label="$t('feature_code')"
                                                                    :hint="$t('feature_code_hint')"
                                                                    persistent-hint
                                                                    outlined
                                                                    dense
                                                                    color="primary"
                                                                    :prepend-icon="'mdi-pencil'"
                                                                ></v-text-field>
                                                            </v-col>
                                                        </v-row>
                                                        
                                                        <v-row>
                                                            <v-col cols="12">
                                                                <v-textarea
                                                                    v-model="form_data.definition"
                                                                    :label="$t('definition') + ' *'"
                                                                    :hint="$t('definition_hint')"
                                                                    persistent-hint
                                                                    required
                                                                    :rules="[v => !!v || 'Definition is required']"
                                                                    outlined
                                                                    dense
                                                                    rows="3"
                                                                    color="primary"
                                                                    :prepend-icon="'mdi-pencil'"
                                                                ></v-textarea>
                                                            </v-col>
                                                        </v-row>
                                                        
                                                        <v-row>
                                                            <v-col cols="12" md="6">
                                                                <v-switch
                                                                    v-model="form_data.is_abstract"
                                                                    :label="$t('is_abstract') + ' *'"
                                                                    :hint="$t('is_abstract_hint')"
                                                                    persistent-hint
                                                                    color="primary"
                                                                    :prepend-icon="'mdi-pencil'"
                                                                ></v-switch>
                                                            </v-col>
                                                            <v-col cols="12" md="6">
                                                                <v-combobox
                                                                    v-model="form_data.aliases"
                                                                    :label="$t('aliases')"
                                                                    :hint="$t('aliases_hint')"
                                                                    persistent-hint
                                                                    chips
                                                                    multiple
                                                                    deletable-chips
                                                                    outlined
                                                                    dense
                                                                    color="primary"
                                                                    :prepend-icon="'mdi-pencil'"
                                                                ></v-combobox>
                                                            </v-col>
                                                        </v-row>
                                                    </v-card-text>
                                                </v-card>
                                            </div>
                                        </v-col>
                                    </v-row>

                                    <!-- File Information Section - Read-only Fields -->
                                    <v-row class="mb-4">
                                        <v-col cols="12">
                                            <div class="readonly-section">
                                                <v-card outlined  class="mb-4">
                                                    <v-card-title class="text-h6">
                                                        <v-icon class="mr-2">mdi-lock</v-icon>
                                                        {{$t('file_information')}}
                                                    </v-card-title>
                                                    <v-card-text class="pt-4">
                                                        <v-row>
                                                            <v-col cols="12" md="6">
                                                                <div class="mb-4">
                                                                    <div class="text-subtitle-2 mb-1 grey--text text--darken-1">
                                                                        {{$t('file_name')}}
                                                                    </div>
                                                                    <div class="text-body-1">{{form_data.file_name || 'N/A'}}</div>
                                                                </div>
                                                            </v-col>
                                                            <v-col cols="12" md="6">
                                                                <div class="mb-4">
                                                                    <div class="text-subtitle-2 mb-1 grey--text text--darken-1">
                                                                        {{$t('file_type')}}
                                                                    </div>
                                                                    <div class="text-body-1">{{form_data.file_type || 'N/A'}}</div>
                                                                </div>
                                                            </v-col>
                                                        </v-row>
                                                        
                                                        <v-row>
                                                            <v-col cols="12" md="6">
                                                                <div class="mb-4">
                                                                    <div class="text-subtitle-2 mb-1 grey--text text--darken-1">
                                                                        {{$t('layer_name')}}
                                                                    </div>
                                                                    <div class="text-body-1">{{form_data.layer_name || 'N/A'}}</div>
                                                                </div>
                                                            </v-col>
                                                            <v-col cols="12" md="6">
                                                                <div class="mb-4">
                                                                    <div class="text-subtitle-2 mb-1 grey--text text--darken-1">
                                                                        {{$t('count')}}
                                                                    </div>
                                                                    <div class="text-body-1">{{form_data.feature_count || 'N/A'}}</div>
                                                                </div>
                                                            </v-col>
                                                        </v-row>
                                                        
                                                        <v-row>
                                                            <v-col cols="12" md="6">
                                                                <div class="mb-4">
                                                                    <div class="text-subtitle-2 mb-1 grey--text text--darken-1">
                                                                        {{$t('geometry_type')}}
                                                                    </div>
                                                                    <div class="text-body-1">{{form_data.geometry_type || 'N/A'}}</div>
                                                                </div>
                                                            </v-col>
                                                            <v-col cols="12" md="6">
                                                                <div class="mb-4">
                                                                    <div class="text-subtitle-2 mb-1 grey--text text--darken-1">
                                                                        {{$t('file_size')}}
                                                                    </div>
                                                                    <div class="text-body-1">{{formatFileSize(form_data.file_size) || 'N/A'}}</div>
                                                                </div>
                                                            </v-col>
                                                        </v-row>
                                                    </v-card-text>
                                                </v-card>
                                            </div>
                                        </v-col>
                                    </v-row>
                                    
                                    <!-- Map Visualization -->
                                    <v-row v-if="hasMapData">
                                        <v-col cols="12">
                                            <v-card outlined>
                                                <v-card-title>
                                                    <v-icon class="mr-2">mdi-map</v-icon>
                                                    {{$t('geographic_extent')}}
                                                </v-card-title>
                                                <v-card-text>
                                                    <div id="geospatial-map" style="height: 400px; width: 100%;"></div>
                                                    
                                                    <!-- Bounding Box Table -->
                                                    <div v-if="boundingBox" class="mt-4">
                                                        <div class="text-subtitle-2 mb-2">
                                                            <v-icon small class="mr-1">mdi-vector-square</v-icon>
                                                            {{$t('bounding_box_coordinates')}}
                                                        </div>
                                                        <v-simple-table dense>
                                                            <template v-slot:default>
                                                                <tbody>
                                                                    <tr>
                                                                        <td class="font-weight-medium">{{$t('north_latitude')}}</td>
                                                                        <td>{{form_data.metadata.layer_info.geographicBoundingBox.northBoundLatitude.toFixed(6)}}°</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="font-weight-medium">{{$t('south_latitude')}}</td>
                                                                        <td>{{form_data.metadata.layer_info.geographicBoundingBox.southBoundLatitude.toFixed(6)}}°</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="font-weight-medium">{{$t('east_longitude')}}</td>
                                                                        <td>{{form_data.metadata.layer_info.geographicBoundingBox.eastBoundLongitude.toFixed(6)}}°</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="font-weight-medium">{{$t('west_longitude')}}</td>
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
                                                    {{$t('coordinate_reference_system_crs')}}
                                                </v-card-title>
                                                <v-card-text class="pt-4">
                                                    <v-row>
                                                        <v-col cols="12" md="6">
                                                            <div class="mb-4">
                                                                <div class="text-subtitle-2 mb-1 grey--text text--darken-1">
                                                                    {{$t('crs_name')}}
                                                                </div>
                                                                <div class="text-body-1">{{crsInfo.name || 'N/A'}}</div>
                                                            </div>
                                                        </v-col>
                                                        <v-col cols="12" md="6">
                                                            <div class="mb-4">
                                                                <div class="text-subtitle-2 mb-1 grey--text text--darken-1">
                                                                    {{$t('crs_type')}}
                                                                </div>
                                                                <div class="text-body-1">{{crsInfo.type || 'N/A'}}</div>
                                                            </div>
                                                        </v-col>
                                                    </v-row>
                                                    
                                                    <v-row v-if="crsInfo.id">
                                                        <v-col cols="12" md="6">
                                                            <div class="mb-4">
                                                                <div class="text-subtitle-2 mb-1 grey--text text--darken-1">
                                                                    {{$t('epsg_code')}}
                                                                </div>
                                                                <div class="text-body-1">{{crsInfo.id.code || 'N/A'}}</div>
                                                            </div>
                                                        </v-col>
                                                        <v-col cols="12" md="6">
                                                            <div class="mb-4">
                                                                <div class="text-subtitle-2 mb-1 grey--text text--darken-1">
                                                                    Authority
                                                                </div>
                                                                <div class="text-body-1">{{crsInfo.id.authority || 'N/A'}}</div>
                                                            </div>
                                                        </v-col>
                                                    </v-row>
                                                    
                                                    <!-- WKT-specific fields: Datum -->
                                                    <v-row v-if="crsInfo.datum">
                                                        <v-col cols="12">
                                                            <div class="mb-4">
                                                                <div class="text-subtitle-2 mb-1 grey--text text--darken-1">
                                                                    Datum
                                                                </div>
                                                                <div class="text-body-1">{{crsInfo.datum || 'N/A'}}</div>
                                                            </div>
                                                        </v-col>
                                                    </v-row>
                                                    
                                                    <!-- WKT-specific fields: Ellipsoid -->
                                                    <v-row v-if="crsInfo.ellipsoid">
                                                        <v-col cols="12" md="4">
                                                            <div class="mb-4">
                                                                <div class="text-subtitle-2 mb-1 grey--text text--darken-1">
                                                                    Ellipsoid
                                                                </div>
                                                                <div class="text-body-1">{{crsInfo.ellipsoid.name || 'N/A'}}</div>
                                                            </div>
                                                        </v-col>
                                                        <v-col cols="12" md="4">
                                                            <div class="mb-4">
                                                                <div class="text-subtitle-2 mb-1 grey--text text--darken-1">
                                                                    Semi-Major Axis (m)
                                                                </div>
                                                                <div class="text-body-1">{{crsInfo.ellipsoid.semi_major_axis || 'N/A'}}</div>
                                                            </div>
                                                        </v-col>
                                                        <v-col cols="12" md="4">
                                                            <div class="mb-4">
                                                                <div class="text-subtitle-2 mb-1 grey--text text--darken-1">
                                                                    {{$t('inverse_flattening')}}
                                                                </div>
                                                                <div class="text-body-1">{{crsInfo.ellipsoid.inverse_flattening || 'N/A'}}</div>
                                                            </div>
                                                        </v-col>
                                                    </v-row>
                                                    
                                                    <!-- WKT-specific fields: Units -->
                                                    <v-row v-if="crsInfo.units">
                                                        <v-col cols="12" md="6">
                                                            <div class="mb-4">
                                                                <div class="text-subtitle-2 mb-1 grey--text text--darken-1">
                                                                    Units
                                                                </div>
                                                                <div class="text-body-1">{{crsInfo.units.name || 'N/A'}}</div>
                                                            </div>
                                                        </v-col>
                                                        <v-col cols="12" md="6">
                                                            <div class="mb-4">
                                                                <div class="text-subtitle-2 mb-1 grey--text text--darken-1">
                                                                    {{$t('conversion_factor')}}
                                                                </div>
                                                                <div class="text-body-1">{{crsInfo.units.conversion_factor || 'N/A'}}</div>
                                                            </div>
                                                        </v-col>
                                                    </v-row>
                                                    
                                                    <v-row v-if="crsInfo.area">
                                                        <v-col cols="12">
                                                            <div class="mb-4">
                                                                <div class="text-subtitle-2 mb-1 grey--text text--darken-1">
                                                                    {{$t('coverage_area')}}
                                                                </div>
                                                                <div class="text-body-1">{{crsInfo.area || 'N/A'}}</div>
                                                            </div>
                                                        </v-col>
                                                    </v-row>
                                                    
                                                    <v-row v-if="crsInfo.scope">
                                                        <v-col cols="12">
                                                            <div class="mb-4">
                                                                <div class="text-subtitle-2 mb-1 grey--text text--darken-1">
                                                                    Scope
                                                                </div>
                                                                <div class="text-body-1">{{crsInfo.scope || 'N/A'}}</div>
                                                            </div>
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
                                    <div class="text-h6 mt-4">{{$t("error")}}</div>
                                    <div class="text-body-2 text--secondary">{{$t("feature_not_found")}}</div>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </div>
        </div>
    `
});
