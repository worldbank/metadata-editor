///// bounding-box-preview-map — read-only extent map for page preview
Vue.component('bounding-box-preview-map', {
    props: ['value', 'field'],
    data: function () {
        return {
            map: null,
            rectangle: null,
            mapContainerId: 'bounding-box-preview-map-' + Math.random().toString(36).substr(2, 9)
        };
    },
    computed: {
        corners: function () {
            return this.extractCorners(this.value, this.field);
        }
    },
    watch: {
        corners: {
            handler: function () {
                this.$nextTick(function () {
                    if (this.corners) {
                        if (!this.map) {
                            this.initMap();
                        } else {
                            this.updateRectangle();
                        }
                    } else {
                        this.destroyMap();
                    }
                });
            },
            deep: true
        }
    },
    mounted: function () {
        if (this.corners) {
            this.$nextTick(this.initMap);
        }
    },
    beforeDestroy: function () {
        this.destroyMap();
    },
    methods: {
        extractCorners: function (box, field) {
            if (!box || typeof box !== 'object') {
                return null;
            }
            const opts = (field && field.bounding_box_options) ? field.bounding_box_options : {
                west: 'westBoundLongitude',
                east: 'eastBoundLongitude',
                south: 'southBoundLatitude',
                north: 'northBoundLatitude'
            };
            const read = function (path) {
                const shortKey = path.indexOf('.') !== -1 ? path.split('.').pop() : path;
                let raw = box[shortKey];
                if (raw === undefined || raw === null || raw === '') {
                    raw = _.get(box, path);
                }
                const num = parseFloat(raw);
                return isNaN(num) ? null : num;
            };
            const west = read(opts.west);
            const east = read(opts.east);
            const south = read(opts.south);
            const north = read(opts.north);
            if (west === null || east === null || south === null || north === null) {
                return null;
            }
            if (south >= north) {
                return null;
            }
            return { west: west, east: east, south: south, north: north };
        },
        initMap: function () {
            if (!this.corners) {
                return;
            }
            const mapContainer = document.getElementById(this.mapContainerId);
            if (!mapContainer) {
                setTimeout(this.initMap, 100);
                return;
            }
            if (typeof L === 'undefined' || typeof BoundingBoxUtil === 'undefined') {
                setTimeout(this.initMap, 200);
                return;
            }
            if (this.map) {
                this.map.invalidateSize();
                this.updateRectangle();
                return;
            }
            if (mapContainer._leaflet_id) {
                this.destroyMap();
            }
            this.map = L.map(this.mapContainerId, {
                center: [20, 0],
                zoom: 2,
                minZoom: 1,
                maxZoom: 10,
                scrollWheelZoom: false,
                worldCopyJump: true
            });
            const tiles = BoundingBoxUtil.previewMapTileLayer(10);
            L.tileLayer(tiles.url, tiles.options).addTo(this.map);
            this.map.whenReady(() => {
                this.map.invalidateSize();
                this.updateRectangle();
            });
        },
        updateRectangle: function () {
            if (!this.map || !this.corners || typeof BoundingBoxUtil === 'undefined') {
                return;
            }
            const c = this.corners;
            const bounds = BoundingBoxUtil.leafletBoundsFromIso(c.west, c.east, c.south, c.north);
            if (this.rectangle) {
                this.map.removeLayer(this.rectangle);
                this.rectangle = null;
            }
            this.rectangle = L.rectangle(bounds, {
                color: '#3388ff',
                weight: 2,
                fillOpacity: 0.15
            }).addTo(this.map);
            this.map.fitBounds(bounds, { padding: [12, 12], maxZoom: 10 });
        },
        destroyMap: function () {
            if (this.rectangle && this.map) {
                this.map.removeLayer(this.rectangle);
                this.rectangle = null;
            }
            if (this.map) {
                this.map.remove();
                this.map = null;
            }
            const mapContainer = document.getElementById(this.mapContainerId);
            if (mapContainer && mapContainer._leaflet_id) {
                delete mapContainer._leaflet_id;
            }
        }
    },
    template: `
        <div v-if="corners" class="bounding-box-preview-map-wrapper mb-2">
            <div
                :id="mapContainerId"
                class="bounding-box-preview-map"
                style="height:220px;width:100%;max-width:640px;border:1px solid #ccc;border-radius:4px;"
            ></div>
        </div>
    `
});
