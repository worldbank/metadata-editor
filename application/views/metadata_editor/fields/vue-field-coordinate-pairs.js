// List of [longitude, latitude] pairs (WGS 84, decimal degrees)
Vue.component('editor-coordinate-pairs-field', {
    props: ['value', 'field'],
    data: function () {
        return {
            rows: [],
        };
    },
    computed: {
        isFieldReadOnly() {
            if (!this.$store.getters.getUserHasEditAccess) {
                return true;
            }
            if (this.field && this.field.is_readonly) {
                return true;
            }
            return false;
        },
    },
    watch: {
        value: {
            handler: function () {
                this.syncRowsFromValue();
            },
            deep: true,
        },
    },
    mounted: function () {
        this.syncRowsFromValue();
    },
    methods: {
        syncRowsFromValue: function () {
            this.rows = this.parseValueToRows(this.value);
            if (this.rows.length === 0 && !this.isFieldReadOnly) {
                this.rows = [{ longitude: '', latitude: '' }];
            }
        },
        parseValueToRows: function (value) {
            if (!Array.isArray(value)) {
                return [];
            }
            const rows = [];
            for (let i = 0; i < value.length; i++) {
                const item = value[i];
                const pair = this.extractPair(item);
                if (pair) {
                    rows.push(pair);
                }
            }
            return rows;
        },
        extractPair: function (item) {
            if (!Array.isArray(item) && typeof item !== 'object') {
                return null;
            }
            if (Array.isArray(item)) {
                return {
                    longitude: item.length > 0 ? item[0] : '',
                    latitude: item.length > 1 ? item[1] : '',
                };
            }
            if (item.value && Array.isArray(item.value)) {
                return {
                    longitude: item.value.length > 0 ? item.value[0] : '',
                    latitude: item.value.length > 1 ? item.value[1] : '',
                };
            }
            if (item.longitude !== undefined || item.latitude !== undefined) {
                return {
                    longitude: item.longitude !== undefined ? item.longitude : '',
                    latitude: item.latitude !== undefined ? item.latitude : '',
                };
            }
            return null;
        },
        coerceNumber: function (raw) {
            if (raw === '' || raw === null || raw === undefined) {
                return '';
            }
            const num = Number(raw);
            return Number.isNaN(num) ? raw : num;
        },
        emitValue: function () {
            const pairs = [];
            for (let i = 0; i < this.rows.length; i++) {
                const row = this.rows[i];
                const lon = this.coerceNumber(row.longitude);
                const lat = this.coerceNumber(row.latitude);
                if (lon === '' && lat === '') {
                    continue;
                }
                pairs.push([lon, lat]);
            }
            this.$emit('input', pairs);
        },
        updateCell: function (index, key, raw) {
            if (!this.rows[index]) {
                return;
            }
            Vue.set(this.rows[index], key, raw);
            this.emitValue();
        },
        addRow: function () {
            this.rows.push({ longitude: '', latitude: '' });
        },
        removeRow: function (index) {
            this.rows.splice(index, 1);
            if (this.rows.length === 0) {
                this.rows.push({ longitude: '', latitude: '' });
            }
            this.emitValue();
        },
    },
    template: `
        <div class="coordinate-pairs-field border rounded pa-2 bg-white">
            <table class="table table-sm table-striped mb-2">
                <thead>
                    <tr>
                        <th scope="col">{{ $t('longitude') || 'Longitude' }}</th>
                        <th scope="col">{{ $t('latitude') || 'Latitude' }}</th>
                        <th scope="col" style="width: 3rem;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in rows" :key="'coord-row-' + index">
                        <td>
                            <input
                                type="number"
                                step="any"
                                class="form-control form-control-sm"
                                :disabled="isFieldReadOnly"
                                :value="row.longitude"
                                @input="updateCell(index, 'longitude', $event.target.value)"
                                :placeholder="'-180 … 180'"
                            />
                        </td>
                        <td>
                            <input
                                type="number"
                                step="any"
                                class="form-control form-control-sm"
                                :disabled="isFieldReadOnly"
                                :value="row.latitude"
                                @input="updateCell(index, 'latitude', $event.target.value)"
                                :placeholder="'-90 … 90'"
                            />
                        </td>
                        <td class="text-right">
                            <v-icon
                                v-if="!isFieldReadOnly"
                                class="v-delete-icon"
                                small
                                @click="removeRow(index)"
                            >mdi-trash-can-outline</v-icon>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="!isFieldReadOnly" class="d-flex justify-content-center">
                <v-btn @click="addRow" text small>
                    <v-icon small>mdi-plus</v-icon>
                    {{ $t('add_row') || 'Add row' }}
                </v-btn>
            </div>
        </div>
    `,
});
