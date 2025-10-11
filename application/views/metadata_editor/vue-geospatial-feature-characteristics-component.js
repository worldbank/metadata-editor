/// Geospatial feature characteristics component
Vue.component('geospatial-feature-characteristics', {
    props: ['feature_id'],
    data: function () {    
        return {
            loading: true,
            characteristics: [],
            selectedCharacteristic: null,
            selectedRowIndex: -1,
            errors: '',
            success_message: '',
            headers: [
                { text: 'Name', value: 'name', sortable: true },
                { text: 'Label', value: 'label', sortable: true },
                { text: 'Data Type', value: 'data_type', sortable: true }
            ],
            search: '',
            sortBy: 'name',
            sortDesc: false
        }
    },
    mounted: function() {
        this.loadCharacteristics();
    },
    computed: {
        filteredCharacteristics: function() {
            let filtered = this.characteristics;
            
            // Apply search filter
            if (this.search) {
                const searchLower = this.search.toLowerCase();
                filtered = filtered.filter(char => 
                    (char.name && char.name.toLowerCase().includes(searchLower)) ||
                    (char.label && char.label.toLowerCase().includes(searchLower)) ||
                    (char.data_type && char.data_type.toLowerCase().includes(searchLower))
                );
            }
            
            // Apply sorting
            filtered.sort((a, b) => {
                let aVal = a[this.sortBy] || '';
                let bVal = b[this.sortBy] || '';
                
                if (typeof aVal === 'string') {
                    aVal = aVal.toLowerCase();
                    bVal = bVal.toLowerCase();
                }
                
                if (this.sortDesc) {
                    return bVal > aVal ? 1 : -1;
                } else {
                    return aVal > bVal ? 1 : -1;
                }
            });
            
            return filtered;
        }
    },
    methods: {
        loadCharacteristics: function() {
            this.loading = true;
            this.errors = '';
            
            if (!this.feature_id) {
                this.errors = 'Feature ID not provided';
                this.loading = false;
                return;
            }
            
            const url = CI.base_url + '/api/geospatial-features/chars/' + this.feature_id;
            
            axios.get(url)
            .then(response => {
                if (response.data.status === 'success') {
                    this.characteristics = response.data.characteristics || [];
                } else {
                    this.errors = response.data.message || 'Failed to load characteristics';
                }
                this.loading = false;
            })
            .catch(error => {
                this.errors = error.response?.data?.message || 'Failed to load characteristics';
                this.loading = false;
            });
        },
        
        selectCharacteristic: function(characteristic) {
            this.selectedCharacteristic = characteristic;
            // Find the index of the selected characteristic
            this.selectedRowIndex = this.characteristics.findIndex(c => c.id === characteristic.id);
        },
        
        updateLabel: function(characteristic, newLabel) {
            const url = CI.base_url + '/api/geospatial-features/chars/' + characteristic.id;
            const data = { label: newLabel };
            
            axios.put(url, data)
            .then(response => {
                if (response.data.status === 'success') {
                    // Update the characteristic in the local array
                    const index = this.characteristics.findIndex(c => c.id === characteristic.id);
                    if (index !== -1) {
                        this.characteristics[index].label = newLabel;
                    }
                    this.success_message = 'Label updated successfully';
                    setTimeout(() => { this.success_message = ''; }, 3000);
                } else {
                    this.errors = response.data.message || 'Failed to update label';
                }
            })
            .catch(error => {
                this.errors = error.response?.data?.message || 'Failed to update label';
            });
        },
        
        hasFrequencies: function(characteristic) {
            if (!characteristic || !characteristic.metadata) return false;
            
            try {
                let metadata = characteristic.metadata;
                if (typeof metadata === 'string') {
                    metadata = JSON.parse(metadata);
                }
                return metadata && metadata.frequencies && Object.keys(metadata.frequencies).length > 0;
            } catch (e) {
                return false;
            }
        },
        
        getFrequencies: function(characteristic) {
            if (!characteristic || !characteristic.metadata) return null;
            
            try {
                let metadata = characteristic.metadata;
                if (typeof metadata === 'string') {
                    metadata = JSON.parse(metadata);
                }
                return metadata.frequencies || null;
            } catch (e) {
                return null;
            }
        },
        
        getValidCount: function(characteristic) {
            if (!characteristic || !characteristic.metadata) return 0;
            
            try {
                let metadata = characteristic.metadata;
                if (typeof metadata === 'string') {
                    metadata = JSON.parse(metadata);
                }
                return metadata.valid || 0;
            } catch (e) {
                return 0;
            }
        },
        
        hasSummaryStatistics: function(characteristic) {
            if (!characteristic || !characteristic.metadata) return false;
            
            try {
                let metadata = characteristic.metadata;
                if (typeof metadata === 'string') {
                    metadata = JSON.parse(metadata);
                }
                return metadata && metadata.summary_statistics && Object.keys(metadata.summary_statistics).length > 0;
            } catch (e) {
                return false;
            }
        },
        
        getSummaryStatistics: function(characteristic) {
            if (!characteristic || !characteristic.metadata) return null;
            
            try {
                let metadata = characteristic.metadata;
                if (typeof metadata === 'string') {
                    metadata = JSON.parse(metadata);
                }
                return metadata.summary_statistics || null;
            } catch (e) {
                return null;
            }
        },
        
        formatStatValue: function(value) {
            if (value === null || value === undefined) return 'N/A';
            if (typeof value === 'number') {
                return value.toFixed(2);
            }
            return value;
        },
        
        formatMetadata: function(metadata) {
            if (!metadata) return 'No metadata available';
            
            try {
                if (typeof metadata === 'string') {
                    const parsed = JSON.parse(metadata);
                    return JSON.stringify(parsed, null, 2);
                }
                return JSON.stringify(metadata, null, 2);
            } catch (e) {
                return metadata;
            }
        },
        
        getDataTypeColor: function(dataType) {
            const colorMap = {
                'int32': 'blue',
                'int64': 'blue',
                'float64': 'green',
                'object': 'orange',
                'string': 'purple',
                'bool': 'red'
            };
            return colorMap[dataType] || 'grey';
        },
        
        getDataTypeIcon: function(dataType) {
            const iconMap = {
                'int32': 'mdi-numeric',
                'int64': 'mdi-numeric',
                'float64': 'mdi-decimal',
                'object': 'mdi-text',
                'string': 'mdi-format-text',
                'bool': 'mdi-checkbox-marked'
            };
            return iconMap[dataType] || 'mdi-help-circle';
        },
        
        getRowClass: function(item) {
            const index = this.characteristics.findIndex(c => c.id === item.id);
            let classes = 'char_row';
            if (index === this.selectedRowIndex) {
                classes += ' selected-row';
            }
            return classes;
        }
        
    },
    template: `
        <div class="geospatial-feature-characteristics">
            <v-row no-gutters class="fill-height mt-5">
                    <!-- Left Column - Data Grid -->
                    <v-col cols="12" lg="5" xl="4">
                        <v-card height="100%">
                            <v-card-title class="d-flex justify-space-between align-center">
                                <div>
                                    <v-icon class="mr-2">mdi-table</v-icon>
                                    {{$t('Feature Characteristics')}}
                                </div>
                                <v-btn @click="loadCharacteristics" :loading="loading" small outlined>
                                    <v-icon left>mdi-refresh</v-icon>
                                    {{$t('Refresh')}}
                                </v-btn>
                            </v-card-title>
                            
                            <v-card-text class="flex-grow-1 overflow-auto pa-4" style="height: calc(100vh - 200px);overflow:auto;">
                                <v-alert v-if="errors" type="error" class="mb-4">
                                    {{errors}}
                                </v-alert>
                                
                                <v-alert v-if="success_message" type="success" class="mb-4">
                                    {{success_message}}
                                </v-alert>
                                
                                <v-progress-linear v-if="loading" indeterminate></v-progress-linear>
                                
                                <!-- Search -->
                                <v-text-field
                                    v-model="search"
                                    :label="$t('Search characteristics')"
                                    prepend-inner-icon="mdi-magnify"
                                    clearable
                                    outlined
                                    dense
                                    class="mb-4"
                                ></v-text-field>
                                
                                <!-- Data Grid -->
                                <v-data-table
                                    :headers="headers"
                                    :items="filteredCharacteristics"
                                    :loading="loading"
                                    item-key="id"
                                    class="elevation-1 text-caption"
                                    @click:row="selectCharacteristic"
                                    :sort-by="sortBy"
                                    :sort-desc="sortDesc"
                                    @update:sort-by="sortBy = $event"
                                    @update:sort-desc="sortDesc = $event"
                                    :items-per-page="-1"
                                    hide-default-footer
                                    dense
                                    :item-class="getRowClass"
                                >
                                    <template v-slot:item.name="{ item }">
                                        <div class="d-flex align-center">
                                            <v-icon :color="getDataTypeColor(item.data_type)" class="mr-2">
                                                {{getDataTypeIcon(item.data_type)}}
                                            </v-icon>
                                            <span class="font-weight-normal">{{item.name}}</span>
                                        </div>
                                    </template>
                                    
                                    <template v-slot:item.label="{ item }">
                                        <input
                                            v-model="item.label"
                                            :placeholder="$t('label')"
                                            class="text-caption"
                                            style="font-size: 11px; height: 24px; padding: 4px 8px; border: 1px solid #ccc; border-radius: 4px; width: 100%;"
                                            @blur="updateLabel(item, item.label)"
                                            @keyup.enter="updateLabel(item, item.label)"
                                        />
                                    </template>
                                    
                                    <template v-slot:item.data_type="{ item }">                                        
                                            {{item.data_type}}                                        
                                    </template>
                                </v-data-table>
                                
                                <div v-if="!loading && characteristics.length === 0" class="text-center py-8">
                                    <v-icon size="64" color="grey">mdi-table-off</v-icon>
                                    <div class="text-h6 mt-4">{{$t('No characteristics found')}}</div>
                                    <div class="text-body-2 text--secondary">{{$t('This feature has no characteristics data.')}}</div>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                    
                    <!-- Right Column - Selected Characteristic Details -->
                    <v-col cols="12" lg="7" xl="8">
                        <v-card height="100%">
                            <v-card-title>
                                <v-icon class="mr-2">mdi-information</v-icon>
                                {{$t('Characteristic Details')}}
                            </v-card-title>
                            
                            <v-card-text>
                                <div v-if="!selectedCharacteristic" class="text-center py-8">
                                    <v-icon size="64" color="grey">mdi-cursor-pointer</v-icon>
                                    <div class="text-h6 mt-4">{{$t('Select a characteristic')}}</div>
                                    <div class="text-body-2 text--secondary">{{$t('Click on a row in the table to view details.')}}</div>
                                </div>
                                
                                <div v-else>
                                    <!-- Basic Information -->
                                    <v-row>
                                        <v-col cols="12" md="6">
                                            <v-text-field
                                                :value="selectedCharacteristic.name"
                                                :label="$t('Name')"
                                                readonly
                                                outlined
                                                dense
                                            ></v-text-field>
                                        </v-col>
                                        <v-col cols="12" md="6">
                                            <v-text-field
                                                :value="selectedCharacteristic.data_type"
                                                :label="$t('Data Type')"
                                                readonly
                                                outlined
                                                dense
                                            ></v-text-field>
                                        </v-col>
                                    </v-row>
                                    
                                    <v-row>
                                        <v-col cols="12">
                                            <v-text-field
                                                v-model="selectedCharacteristic.label"
                                                :label="$t('Label')"
                                                outlined
                                                dense
                                                @blur="updateLabel(selectedCharacteristic, selectedCharacteristic.label)"
                                                @keyup.enter="updateLabel(selectedCharacteristic, selectedCharacteristic.label)"
                                            ></v-text-field>
                                        </v-col>
                                    </v-row>
                                    
                                    <!-- Frequencies -->
                                    <v-card outlined class="mt-4" v-if="hasFrequencies(selectedCharacteristic)">
                                        <v-card-title class="text-subtitle-1">
                                            <v-icon class="mr-2">mdi-chart-bar</v-icon>
                                            {{$t('Frequencies')}}
                                            <v-chip small class="ml-2">{{$t('Valid')}}: {{getValidCount(selectedCharacteristic)}}</v-chip>
                                        </v-card-title>
                                        <v-card-text>
                                            <table class="table table-sm variable-frequencies" style="width: 100%; font-size: 12px;">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 40%;">{{$t('Value')}}</th>
                                                        <th style="width: 15%; text-align: right;">{{$t('Count')}}</th>
                                                        <th style="width: 45%;">{{$t('Percentage')}}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="(freq, value) in getFrequencies(selectedCharacteristic)" :key="value">
                                                        <td>{{value}}</td>
                                                        <td style="text-align: right;">{{freq.count}}</td>
                                                        <td>
                                                            <div class="progress" style="height: 20px; position: relative;">
                                                                <div class="progress-bar bg-warning" 
                                                                     role="progressbar" 
                                                                     :style="'width: ' + freq.percentage + '%'" 
                                                                     :aria-valuenow="freq.percentage" 
                                                                     aria-valuemin="0" 
                                                                     aria-valuemax="100">
                                                                </div>
                                                                <span class="progress-text" style="position: absolute; left: 50%; top: 10%; transform: translate(-50%, -50%); font-size: 11px; font-weight: 500;">
                                                                    {{freq.percentage.toFixed(2)}}%
                                                                </span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </v-card-text>
                                    </v-card>
                                    
                                    <!-- Summary Statistics -->
                                    <v-card outlined class="mt-4" v-if="hasSummaryStatistics(selectedCharacteristic)">
                                        <v-card-title class="text-subtitle-1">
                                            <v-icon class="mr-2">mdi-chart-line</v-icon>
                                            {{$t('Summary Statistics')}}
                                        </v-card-title>
                                        <v-card-text>
                                            <table style="width: 100%; font-size: 12px;">
                                                <tbody>
                                                    <tr v-for="(value, key) in getSummaryStatistics(selectedCharacteristic)" :key="key">
                                                        <td style="width: 150px; padding: 4px 8px;">
                                                            <strong>{{key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ')}}</strong>
                                                        </td>
                                                        <td style="padding: 4px 8px;">
                                                            {{formatStatValue(value)}}
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </v-card-text>
                                    </v-card>
                                    
                                    <!-- Metadata -->
                                    <v-card outlined class="mt-4">
                                        <v-card-title class="text-subtitle-1">
                                            <v-icon class="mr-2">mdi-code-json</v-icon>
                                            {{$t('Metadata')}}
                                        </v-card-title>
                                        <v-card-text>
                                            <pre class="metadata-display">{{formatMetadata(selectedCharacteristic.metadata)}}</pre>
                                        </v-card-text>
                                    </v-card>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>
            </v-row>
        </div>
    `
});
