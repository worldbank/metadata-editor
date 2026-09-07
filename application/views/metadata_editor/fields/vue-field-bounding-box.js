//bounding box field control
Vue.component('editor-bounding-box-field', {
    props: ['value','field'],
    data: function () {    
        return {
            dialog: false,
            map: null,
            rectangle: null,
            cornerMarkers: [],
            drawing: false,
            drawingMode: false,
            resizing: false,
            calculating: false,
            west: '',
            east: '',
            south: '',
            north: '',
            mapContainerId: 'bounding-box-map-' + Math.random().toString(36).substr(2, 9)
        }
    },
    computed: {
        isFieldReadOnly() {
            if (!this.$store.getters.getUserHasEditAccess) {
                return true;
            }
            return this.field.is_readonly;
        },
        projectId() {
            return this.$store.state.project_id;
        },
        boundingBoxOptions() {
            // Get the field mapping options, with defaults
            const opts = this.field.bounding_box_options || {
                'west': 'westBoundLongitude',
                'east': 'eastBoundLongitude',
                'south': 'southBoundLatitude',
                'north': 'northBoundLatitude'
            };
            
            // Convert full paths (e.g., "geographicBoundingBox.westBoundLongitude") 
            // to local paths (e.g., "westBoundLongitude")
            const result = {};
            for (const [key, path] of Object.entries(opts)) {
                if (path.includes('.')) {
                    // Extract the last part after the dot
                    result[key] = path.split('.').pop();
                } else {
                    result[key] = path;
                }
            }
            return result;
        },
        displayValue() {
            if (!this.value || typeof this.value !== 'object' || this.value === null) return '';
            
            const opts = this.boundingBoxOptions;
            const west = _.get(this.value, opts.west) || '';
            const east = _.get(this.value, opts.east) || '';
            const south = _.get(this.value, opts.south) || '';
            const north = _.get(this.value, opts.north) || '';
            
            if (!west && !east && !south && !north) return '';
            
            return `West: ${west}, East: ${east}, South: ${south}, North: ${north}`;
        },
        crossesAntimeridian() {
            const west = parseFloat(this.west);
            const east = parseFloat(this.east);
            return !isNaN(west) && !isNaN(east) && west > east;
        }
    },
    watch: {
        dialog: function(newVal) {
            if (newVal) {
                this.loadExistingBoundingBox();
                // Wait for dialog to fully render before initializing map
                this.$nextTick(() => {
                    // Add a small delay
                    setTimeout(() => {
                        this.initializeMap();
                    }, 300);
                });
            } else {
                this.destroyMap();
            }
        },
        value: {
            handler: function(newVal) {
                if (newVal && this.dialog) {
                    this.loadExistingBoundingBox();
                    if (this.map) {
                        this.updateMapFromCoordinates();
                    }
                }
            },
            deep: true
        }
    },
    methods: {
        loadExistingBoundingBox() {
            if (!this.value || typeof this.value !== 'object' || this.value === null) {
                this.west = '';
                this.east = '';
                this.south = '';
                this.north = '';
                return;
            }
            
            const opts = this.boundingBoxOptions;
            this.west = _.get(this.value, opts.west) || '';
            this.east = _.get(this.value, opts.east) || '';
            this.south = _.get(this.value, opts.south) || '';
            this.north = _.get(this.value, opts.north) || '';
            this.wrapCoordinateFields();
            
            // Update map if we have valid coordinates
            if (this.map && this.west && this.east && this.south && this.north) {
                this.updateMapFromCoordinates();
            }
        },
        initializeMap: function() {
            const mapContainer = document.getElementById(this.mapContainerId);
            if (!mapContainer) {
                // Retry after a short delay if container not ready
                setTimeout(() => this.initializeMap(), 100);
                return;
            }
            
            if (this.map) {
                // Map already exists, just invalidate size in case dialog was resized
                this.map.invalidateSize();
                return;
            }
            
            // Check if Leaflet is available
            if (typeof L === 'undefined') {
                console.error('Leaflet is not loaded');
                setTimeout(() => this.initializeMap(), 200);
                return;
            }
            
            // Check if container already has a map instance
            if (mapContainer._leaflet_id) {
                this.destroyMap();
            }
            
            try {
                // Initialize Leaflet map with better default view
                // Limit max zoom to prevent super zoomed in views
                this.map = L.map(this.mapContainerId, {
                    center: [20, 0],
                    zoom: 2,
                    minZoom: 1,
                    maxZoom: 10, // Limit max zoom to prevent excessive zooming
                    worldCopyJump: true
                });
                
                // Add OpenStreetMap tiles (limit max zoom to match map settings)
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 10 // Match map maxZoom to prevent excessive zooming
                }).addTo(this.map);
                
                // Wait for map to be ready
                this.map.whenReady(() => {
                    // Invalidate size to ensure proper rendering
                    this.map.invalidateSize();
                    
                    // If we have existing coordinates, show them on the map
                    if (this.west && this.east && this.south && this.north) {
                        this.updateMapFromCoordinates();
                    } else {
                        // Set a better default view to show more of the world
                        this.map.setView([20, 0], 2);
                    }
                    
                    // Ensure zoom never exceeds max or goes below min after any operation
                    this.map.on('zoomend', () => {
                        const currentZoom = this.map.getZoom();
                        if (currentZoom > 10) {
                            this.map.setZoom(10);
                        } else if (currentZoom < 1) {
                            this.map.setZoom(1);
                        }
                    });
                    
                    // Also check on zoom start to prevent excessive zooming
                    this.map.on('zoomstart', () => {
                        const currentZoom = this.map.getZoom();
                        if (currentZoom >= 10) {
                            // Prevent zooming in further if already at max
                            this.map.options.maxZoom = 10;
                        }
                    });
                });
            } catch (error) {
                console.error('Error initializing map:', error);
            }
        },
        toggleDrawingMode: function() {
            if (!this.map) return;
            
            // If currently drawing, cancel it first
            if (this.drawing) {
                this.cancelDrawing();
            }
            
            this.drawingMode = !this.drawingMode;
            
            if (this.drawingMode) {
                this.enableDrawing();
            } else {
                this.disableDrawing();
            }
        },
        cancelDrawing: function() {
            if (this.rectangle && this.drawing) {
                this.removeRectangle();
            }
            this.drawing = false;
        },
        enableDrawing: function() {
            if (!this.map) return;
            
            // Remove any existing event listeners to prevent duplicates
            this.map.off('mousedown');
            this.map.off('mousemove');
            this.map.off('mouseup');
            
            // Disable map dragging to prevent panning while drawing
            this.map.dragging.disable();
            
            let startLatLng = null;
            
            // Change cursor to crosshair to indicate drawing mode
            this.map.getContainer().style.cursor = 'crosshair';
            
            // Add ESC key listener to cancel drawing
            this.escKeyHandler = (e) => {
                if (e.key === 'Escape' && this.drawingMode) {
                    if (this.drawing) {
                        this.cancelDrawing();
                    } else {
                        this.toggleDrawingMode();
                    }
                }
            };
            document.addEventListener('keydown', this.escKeyHandler);
            
            // Mouse down - start drawing
            this.map.on('mousedown', (e) => {
                if (this.isFieldReadOnly || !this.drawingMode) return;
                
                e.originalEvent.preventDefault();
                e.originalEvent.stopPropagation();
                
                this.drawing = true;
                startLatLng = e.latlng;
                
                // Remove existing rectangle
                if (this.rectangle) {
                    this.map.removeLayer(this.rectangle);
                    this.rectangle = null;
                }
                
                // Create new rectangle starting from click point
                this.rectangle = L.rectangle([startLatLng, startLatLng], {
                    color: '#3388ff',
                    weight: 3,
                    fillColor: '#3388ff',
                    fillOpacity: 0.3,
                    dashArray: '5, 5'
                }).addTo(this.map);
            });
            
            // Mouse move - update rectangle while drawing
            this.map.on('mousemove', (e) => {
                if (!this.drawing || !startLatLng || !this.rectangle || this.isFieldReadOnly || !this.drawingMode) return;
                
                e.originalEvent.preventDefault();
                e.originalEvent.stopPropagation();
                
                const bounds = L.latLngBounds([startLatLng, e.latlng]);
                this.rectangle.setBounds(bounds);
            });
            
            // Mouse up - finish drawing
            this.map.on('mouseup', (e) => {
                if (!this.drawing || !startLatLng || !this.rectangle || this.isFieldReadOnly || !this.drawingMode) return;
                
                e.originalEvent.preventDefault();
                e.originalEvent.stopPropagation();
                
                this.drawing = false;
                const bounds = this.rectangle.getBounds();
                this.updateCoordinatesFromBounds(bounds);
                
                // Update rectangle style to indicate it's complete
                this.rectangle.setStyle({
                    dashArray: null,
                    weight: 2,
                    fillOpacity: 0.2
                });
                
                // Add resizable corner markers (reuse bounds variable)
                this.addCornerMarkers([[bounds.getSouth(), bounds.getWest()], [bounds.getNorth(), bounds.getEast()]]);
                
                // Automatically exit drawing mode after completing a rectangle
                // User can re-enable if they want to draw another
                this.drawingMode = false;
                this.disableDrawing();
            });
        },
        disableDrawing: function() {
            if (!this.map) return;
            
            // Cancel any active drawing
            this.cancelDrawing();
            
            // Remove event listeners
            this.map.off('mousedown');
            this.map.off('mousemove');
            this.map.off('mouseup');
            
            // Re-enable map dragging
            this.map.dragging.enable();
            
            // Reset cursor
            this.map.getContainer().style.cursor = '';
            
            // Remove ESC key listener
            if (this.escKeyHandler) {
                document.removeEventListener('keydown', this.escKeyHandler);
                this.escKeyHandler = null;
            }
            
            this.drawing = false;
        },
        resetMapView: function() {
            if (!this.map) return;
            
            // Remove existing rectangle and markers
            this.removeRectangle();
            
            // Reset coordinates
            this.west = '';
            this.east = '';
            this.south = '';
            this.north = '';
            
            // Clear the value first (emit empty object instead of null)
            this.$emit('input', {});
            
            // Reset map view to wide world view
            try {
                this.map.setView([20, 0], 2);
                
                // Ensure zoom doesn't exceed max
                if (this.map.getZoom() > 10) {
                    this.map.setZoom(10);
                }
            } catch (error) {
                console.error('Error resetting map view:', error);
            }
        },
        updateMapFromCoordinates: function() {
            if (!this.map || !this.west || !this.east || !this.south || !this.north) return;
            
            const west = parseFloat(this.west);
            const east = parseFloat(this.east);
            const south = parseFloat(this.south);
            const north = parseFloat(this.north);
            
            if (isNaN(west) || isNaN(east) || isNaN(south) || isNaN(north)) return;
            
            // Unwrap east when the ISO box crosses the antimeridian (west > east)
            const bounds = BoundingBoxUtil.leafletBoundsFromIso(west, east, south, north);
            
            // Remove existing rectangle and corner markers
            this.removeRectangle();
            
            // Create rectangle
            this.rectangle = L.rectangle(bounds, {
                color: '#3388ff',
                weight: 2,
                fillColor: '#3388ff',
                fillOpacity: 0.2
            }).addTo(this.map);
            
            // Add resizable corner markers
            this.addCornerMarkers(bounds);
            
            // Fit map to bounds with padding and max zoom limit
            this.map.fitBounds(bounds, {
                padding: [20, 20], // Add padding around the bounds
                maxZoom: 10 // Prevent zooming in too much
            });
            
            // Ensure we don't exceed max zoom after fitting
            if (this.map.getZoom() > 10) {
                this.map.setZoom(10);
            }
        },
        addCornerMarkers: function(bounds) {
            // Remove existing corner markers
            this.removeCornerMarkers();
            
            // Get the four corners
            const corners = [
                [bounds[0][0], bounds[0][1]], // Southwest
                [bounds[0][0], bounds[1][1]], // Southeast
                [bounds[1][0], bounds[1][1]], // Northeast
                [bounds[1][0], bounds[0][1]]  // Northwest
            ];
            
            // Create draggable markers for each corner
            corners.forEach((corner, index) => {
                const marker = L.marker(corner, {
                    draggable: !this.isFieldReadOnly,
                    icon: L.divIcon({
                        className: 'bounding-box-corner-marker',
                        html: '<div style="width: 16px; height: 16px; background-color: #3388ff; border: 2px solid #fff; border-radius: 50%; cursor: move;"></div>',
                        iconSize: [16, 16],
                        iconAnchor: [8, 8]
                    })
                }).addTo(this.map);
                
                // Store corner position (0=SW, 1=SE, 2=NE, 3=NW)
                marker.cornerIndex = index;
                
                // Handle drag to resize rectangle
                marker.on('drag', (e) => {
                    this.onCornerDrag(e);
                });
                
                marker.on('dragend', (e) => {
                    this.onCornerDragEnd(e);
                });
                
                // Change cursor on hover
                marker.on('mouseover', () => {
                    if (!this.isFieldReadOnly) {
                        this.map.getContainer().style.cursor = 'move';
                    }
                });
                
                marker.on('mouseout', () => {
                    if (!this.resizing) {
                        this.map.getContainer().style.cursor = '';
                    }
                });
                
                this.cornerMarkers.push(marker);
            });
        },
        removeCornerMarkers: function() {
            if (!this.map) return;
            
            this.cornerMarkers.forEach(marker => {
                try {
                    if (marker && this.map.hasLayer(marker)) {
                        this.map.removeLayer(marker);
                    }
                } catch (error) {
                    console.error('Error removing corner marker:', error);
                }
            });
            this.cornerMarkers = [];
        },
        onCornerDrag: function(e) {
            if (this.isFieldReadOnly || !this.rectangle) {
                e.target.setLatLng(e.target._latlng); // Prevent movement
                return;
            }
            
            this.resizing = true;
            
            // Get current marker position
            const pos = e.target.getLatLng();
            const cornerIndex = e.target.cornerIndex;
            
            // Get current bounds
            const currentBounds = this.rectangle.getBounds();
            const sw = currentBounds.getSouthWest();
            const ne = currentBounds.getNorthEast();
            
            // Update bounds based on which corner is being dragged
            let newSouth = sw.lat;
            let newWest = sw.lng;
            let newNorth = ne.lat;
            let newEast = ne.lng;
            
            switch(cornerIndex) {
                case 0: // Southwest
                    newSouth = pos.lat;
                    newWest = pos.lng;
                    break;
                case 1: // Southeast
                    newSouth = pos.lat;
                    newEast = pos.lng;
                    break;
                case 2: // Northeast
                    newNorth = pos.lat;
                    newEast = pos.lng;
                    break;
                case 3: // Northwest
                    newNorth = pos.lat;
                    newWest = pos.lng;
                    break;
            }
            
            // Ensure valid bounds (south < north, west < east)
            if (newSouth >= newNorth) {
                // Swap if needed
                const temp = newSouth;
                newSouth = newNorth;
                newNorth = temp;
            }
            
            if (newWest >= newEast) {
                // Swap if needed
                const temp = newWest;
                newWest = newEast;
                newEast = temp;
            }
            
            // Update rectangle bounds using Leaflet's LatLngBounds
            const newBounds = L.latLngBounds(
                [newSouth, newWest],  // Southwest
                [newNorth, newEast]   // Northeast
            );
            this.rectangle.setBounds(newBounds);
            
            // Update other corner markers positions (but not the one being dragged)
            this.cornerMarkers.forEach((marker, index) => {
                if (index !== cornerIndex) {
                    const corners = [
                        [newSouth, newWest], // Southwest
                        [newSouth, newEast], // Southeast
                        [newNorth, newEast], // Northeast
                        [newNorth, newWest]  // Northwest
                    ];
                    marker.setLatLng(corners[index]);
                }
            });
        },
        onCornerDragEnd: function(e) {
            this.resizing = false;
            this.map.getContainer().style.cursor = '';
            
            // Update coordinates from new bounds
            const bounds = this.rectangle.getBounds();
            this.updateCoordinatesFromBounds(bounds);
        },
        updateCornerMarkers: function(bounds) {
            // bounds format: [[south, west], [north, east]]
            const south = bounds[0][0];
            const west = bounds[0][1];
            const north = bounds[1][0];
            const east = bounds[1][1];
            
            const corners = [
                [south, west], // Southwest
                [south, east], // Southeast
                [north, east], // Northeast
                [north, west]  // Northwest
            ];
            
            this.cornerMarkers.forEach((marker, index) => {
                marker.setLatLng(corners[index]);
            });
        },
        removeRectangle: function() {
            if (this.rectangle && this.map) {
                try {
                    this.map.removeLayer(this.rectangle);
                } catch (error) {
                    console.error('Error removing rectangle:', error);
                }
                this.rectangle = null;
            }
            this.removeCornerMarkers();
        },
        updateCoordinatesFromBounds: function(bounds) {
            const southWest = bounds.getSouthWest();
            const northEast = bounds.getNorthEast();
            const lng = BoundingBoxUtil.isoLongitudesFromUnwrapped(southWest.lng, northEast.lng);
            
            this.west = lng.west.toFixed(6);
            this.east = lng.east.toFixed(6);
            this.south = southWest.lat.toFixed(6);
            this.north = northEast.lat.toFixed(6);
            
            this.updateValue();
        },
        wrapCoordinateFields: function() {
            if (this.west !== '' && this.west != null) {
                const west = parseFloat(this.west);
                if (!isNaN(west)) {
                    this.west = BoundingBoxUtil.wrapLongitude(west).toFixed(6);
                }
            }
            if (this.east !== '' && this.east != null) {
                const east = parseFloat(this.east);
                if (!isNaN(east)) {
                    this.east = BoundingBoxUtil.wrapLongitude(east).toFixed(6);
                }
            }
        },
        updateValue: function() {
            // Get the original full-path options from field config
            const fullPathOpts = this.field.bounding_box_options || {
                'west': 'westBoundLongitude',
                'east': 'eastBoundLongitude',
                'south': 'southBoundLatitude',
                'north': 'northBoundLatitude'
            };
            
            const newValue = {};
            
            // Use _.set() with the original (potentially nested) paths to maintain structure
            if (this.west) _.set(newValue, fullPathOpts.west, parseFloat(this.west));
            if (this.east) _.set(newValue, fullPathOpts.east, parseFloat(this.east));
            if (this.south) _.set(newValue, fullPathOpts.south, parseFloat(this.south));
            if (this.north) _.set(newValue, fullPathOpts.north, parseFloat(this.north));
            
            // Only emit if we have at least one coordinate
            if (Object.keys(newValue).length > 0) {
                this.$emit('input', newValue);
            } else {
                // Emit empty object instead of null to avoid processing errors
                this.$emit('input', {});
            }
        },
        onCoordinateChange: function() {
            // When user manually changes coordinates, update the map
            if (this.map && this.west && this.east && this.south && this.north) {
                this.updateMapFromCoordinates();
            }
            this.updateValue();
        },
        clearBoundingBox: function() {
            this.west = '';
            this.east = '';
            this.south = '';
            this.north = '';
            
            this.removeRectangle();
            
            // Emit empty object instead of null to avoid processing errors
            this.$emit('input', {});
        },
        saveBoundingBox: function() {
            this.wrapCoordinateFields();

            const west = parseFloat(this.west);
            const east = parseFloat(this.east);
            const south = parseFloat(this.south);
            const north = parseFloat(this.north);
            
            if (this.south && this.north && (south >= north)) {
                alert('South latitude must be less than North latitude');
                return;
            }
            
            if (this.west && (west < -180 || west > 180)) {
                alert('West longitude must be between -180 and 180');
                return;
            }
            
            if (this.east && (east < -180 || east > 180)) {
                alert('East longitude must be between -180 and 180');
                return;
            }
            
            if (this.south && (south < -90 || south > 90)) {
                alert('South latitude must be between -90 and 90');
                return;
            }
            
            if (this.north && (north < -90 || north > 90)) {
                alert('North latitude must be between -90 and 90');
                return;
            }
            
            this.updateValue();
            this.dialog = false;
        },
        onDialogOpened: function() {
            // Ensure map is properly sized when dialog opens
            if (this.map) {
                this.$nextTick(() => {
                    this.map.invalidateSize();
                });
            }
        },
        calculateGlobalBoundingBox: function() {
            if (!this.projectId) {
                alert('Project ID not available');
                return;
            }
            
            this.calculating = true;
            
            const url = CI.base_url + '/api/geospatial-features/' + this.projectId + '/global-bounds';
            
            axios.get(url)
                .then((response) => {
                    this.calculating = false;
                    
                    if (response.data.status === 'success') {
                        if (response.data.global_bounds) {
                            const bounds = response.data.global_bounds;
                            const opts = this.boundingBoxOptions;
                            
                            // Update coordinates
                            this.west = bounds[opts.west] || '';
                            this.east = bounds[opts.east] || '';
                            this.south = bounds[opts.south] || '';
                            this.north = bounds[opts.north] || '';
                            
                            // Update the value
                            this.updateValue();
                            
                            // Update map if it's initialized
                            if (this.map) {
                                this.updateMapFromCoordinates();
                            }
                            
                            // Show success message (using simple alert for now)
                            // The bounding box has been updated and will be visible on the map
                        } else {
                            alert(response.data.message || 'No valid bounding boxes found in project features');
                        }
                    } else {
                        alert('Failed to calculate global bounding box: ' + (response.data.message || 'Unknown error'));
                    }
                })
                .catch((error) => {
                    this.calculating = false;
                    console.error('Error calculating global bounding box:', error);
                    const errorMessage = error.response?.data?.message || error.message || 'Failed to calculate global bounding box';
                    alert('Error: ' + errorMessage);
                });
        },
        destroyMap: function() {
            // Remove ESC key listener if it exists
            if (this.escKeyHandler) {
                document.removeEventListener('keydown', this.escKeyHandler);
                this.escKeyHandler = null;
            }
            
            this.disableDrawing();
            
            if (this.map) {
                this.map.remove();
                this.map = null;
            }
            
            this.removeRectangle();
            
            const mapContainer = document.getElementById(this.mapContainerId);
            if (mapContainer && mapContainer._leaflet_id) {
                delete mapContainer._leaflet_id;
            }
            
            this.drawingMode = false;
        }
    },
    beforeDestroy: function() {
        this.destroyMap();
    },
    template: `
    <div class="bounding-box-field">    
        <v-text-field
            :value="displayValue"
            readonly
            dense
            solo
            prepend-inner-icon="mdi-map"
            placeholder="Click to set bounding box on map"
            @click="dialog = true"
            :disabled="isFieldReadOnly"
            clearable
            @click:clear="clearBoundingBox"
        ></v-text-field>
        
        <v-dialog
            v-model="dialog"
            max-width="900px"
            persistent
            @opened="onDialogOpened"
        >
            <v-card>
                <v-card-title class="d-flex justify-space-between align-center">
                    <div>
                        <v-icon left>mdi-map</v-icon>
                        {{field.title || 'Geographic Bounding Box'}}
                    </div>
                    <v-btn icon @click="dialog = false">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </v-card-title>
                
                <v-card-text>
                    
                    <!-- Map Controls -->
                    <div class="d-flex justify-space-between align-center mb-2" v-if="map">
                        <div>
                            <v-btn 
                                :color="drawingMode ? 'primary' : 'default'"
                                :outlined="!drawingMode"
                                small
                                @click="toggleDrawingMode"
                                :disabled="isFieldReadOnly"
                            >
                                <v-icon left small>{{drawingMode ? 'mdi-draw' : 'mdi-draw-pen'}}</v-icon>
                                {{drawingMode ? 'Drawing Mode Active' : 'Enable Drawing'}}
                            </v-btn>
                            <v-btn 
                                v-if="drawingMode"
                                color="error"
                                outlined
                                small
                                @click="toggleDrawingMode"
                                :disabled="isFieldReadOnly"
                                class="ml-2"
                            >
                                <v-icon left small>mdi-close</v-icon>
                                Exit Drawing
                            </v-btn>
                            <v-btn 
                                text
                                small
                                @click="resetMapView"
                                :disabled="isFieldReadOnly"
                                class="ml-2"
                            >
                                <v-icon left small>mdi-refresh</v-icon>
                                Reset View
                            </v-btn>
                            <v-btn 
                                color="primary"
                                outlined
                                small
                                @click="calculateGlobalBoundingBox"
                                :disabled="isFieldReadOnly || calculating || !projectId"
                                :loading="calculating"
                                class="ml-2"
                            >
                                <v-icon left small>mdi-calculator</v-icon>
                                Calculate from Features
                            </v-btn>
                        </div>
                    </div>
                    
                    <!-- Map Container -->
                    <div 
                        :id="mapContainerId" 
                        style="height: 400px; width: 100%; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 16px; background-color: #f0f0f0; position: relative;"
                    >
                        <div v-if="!map" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; color: #666;">
                            <v-progress-circular indeterminate color="primary" size="48"></v-progress-circular>
                            <div class="mt-2">Loading map...</div>
                        </div>
                    </div>
                    
                    <!-- Coordinate Input Fields -->
                    <v-row>
                        <v-col cols="6">
                            <v-text-field
                                v-model="west"
                                label="West Bound Longitude"
                                type="number"
                                step="0.000001"
                                min="-180"
                                max="180"
                                outlined
                                dense
                                :disabled="isFieldReadOnly"
                                @input="onCoordinateChange"
                                hint="Range: -180 to 180. West may be greater than East across the dateline."
                                persistent-hint
                            ></v-text-field>
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model="east"
                                label="East Bound Longitude"
                                type="number"
                                step="0.000001"
                                min="-180"
                                max="180"
                                outlined
                                dense
                                :disabled="isFieldReadOnly"
                                @input="onCoordinateChange"
                                hint="Range: -180 to 180. West may be greater than East across the dateline."
                                persistent-hint
                            ></v-text-field>
                        </v-col>
                    </v-row>
                    <div v-if="crossesAntimeridian" class="caption grey--text mb-3">
                        This box crosses the antimeridian (dateline). West greater than East is valid.
                    </div>
                    
                    <v-row>
                        <v-col cols="6">
                            <v-text-field
                                v-model="south"
                                label="South Bound Latitude"
                                type="number"
                                step="0.000001"
                                min="-90"
                                max="90"
                                outlined
                                dense
                                :disabled="isFieldReadOnly"
                                @input="onCoordinateChange"
                                hint="Range: -90 to 90"
                                persistent-hint
                            ></v-text-field>
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model="north"
                                label="North Bound Latitude"
                                type="number"
                                step="0.000001"
                                min="-90"
                                max="90"
                                outlined
                                dense
                                :disabled="isFieldReadOnly"
                                @input="onCoordinateChange"
                                hint="Range: -90 to 90"
                                persistent-hint
                            ></v-text-field>
                        </v-col>
                    </v-row>
                </v-card-text>
                
                <v-divider></v-divider>
                
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn 
                        text 
                        @click="dialog = false"
                        :disabled="isFieldReadOnly"
                    >
                        Cancel
                    </v-btn>
                    <v-btn 
                        color="primary" 
                        @click="saveBoundingBox"
                        :disabled="isFieldReadOnly"
                    >
                        Save
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
    `
});

