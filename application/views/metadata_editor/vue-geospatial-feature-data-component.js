/// geospatial feature data explorer
Vue.component('geospatial-feature-data', {
    props:['feature_id'],
    data: function () {    
        return {
            feature: null,
            feature_data:[],            
            characteristics: [],
            errors:[],            
            rows_limit:50,
            page: 1,
            data_loading_dialog:false,
            dialog:{
                show:false,
                title:'',
                loading_message:'',
                message_success:'',
                message_error:'',
                is_loading:false
            },
        }
    },
    mounted: function(){
        this.loadFeature();
    },
    
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        },
        PageOffset(){
            return this.feature_data.offset || 0;
        },
        PaginationPageSize(){
            return this.rows_limit;
        },
        TotalPages()
        {
            // If total is -1 (row counting skipped), return a large number to allow pagination
            if (this.feature_data.total === -1) {
                return 999; // Large number to allow pagination without knowing total
            }
            return Math.ceil((this.feature_data.total) / this.rows_limit);            
        },
        tableHeaders() {
            if (!this.feature_data || !this.feature_data.headers || !Array.isArray(this.feature_data.headers)) {
                return [];
            }
            console.log('Building table headers. Headers:', this.feature_data.headers);
            console.log('Available characteristics:', this.characteristics);
            
            // Add row number header as first column
            const headers = [{
                text: '#',
                value: 'row_number',
                sortable: false,
                dataType: 'number',
                label: 'Row Number',
                metadata: null,
                width: '60px'
            }];
            
            // Add data headers
            const dataHeaders = this.feature_data.headers.map(header => {
                if (!header) return null;
                const characteristic = this.characteristics.find(char => char && char.name === header);
                console.log(`Header: ${header}, Found characteristic:`, characteristic);
                if (characteristic) {
                    console.log('Characteristic fields:', Object.keys(characteristic));
                    console.log('data_type:', characteristic.data_type);
                    console.log('label:', characteristic.label);
                    console.log('metadata:', characteristic.metadata);
                }
                
                return {
                    text: header,
                    value: header,
                    sortable: true,
                    dataType: characteristic ? characteristic.data_type : 'unknown',
                    label: characteristic ? characteristic.label : null,
                    metadata: characteristic ? characteristic.metadata : null
                };
            }).filter(header => header !== null);
            
            return headers.concat(dataHeaders);
        },
        
        tableItems() {
            if (!this.feature_data || !this.feature_data.records || !Array.isArray(this.feature_data.records)) {
                return [];
            }
            
            // Add row numbers to each record
            const offset = this.feature_data.offset || 0;
            return this.feature_data.records.map((record, index) => ({
                ...record,
                row_number: offset + index + 1
            }));
        },
    },
    methods:{        
        loadFeature: function() {
            // First try to find the feature in the store
            if (this.$store && this.$store.state.geospatial_features && this.$store.state.geospatial_features.length > 0) {
                const features = this.$store.state.geospatial_features;
                this.feature = features.find(f => f.id == this.feature_id);
                
                if (this.feature && this.feature.data_file) {
                    this.loadCharacteristics().then(() => {
                        this.loadData();
                    });
                } else if (this.feature) {
                    this.errors = ['No data file available for this feature'];
                } else {
                    // Feature not found in store, load from API
                    this.loadFeatureFromAPI();
                }
            } else {
                // Store is empty, load from API
                this.loadFeatureFromAPI();
            }
        },
        
        loadFeatureFromAPI: function() {
            const projectId = this.$store.state.project_id;
            const url = CI.base_url + '/api/geospatial-features/' + projectId + '/' + this.feature_id;
            axios.get(url)
            .then(response => {
                if (response.data && response.data.status === 'success' && response.data.feature) {
                    this.feature = response.data.feature;
                    if (this.feature.data_file) {
                        this.loadCharacteristics().then(() => {
                            this.loadData();
                        });
                    } else {
                        this.errors = ['No data file available for this feature'];
                    }
                } else {
                    this.errors = ['Feature not found'];
                }
            })
            .catch(error => {
                console.error('Error loading feature:', error);
                this.errors = ['Error loading feature: ' + (error.response?.data?.message || error.message)];
            });
        },
        
        loadCharacteristics: function() {
            const url = CI.base_url + '/api/geospatial_features/chars/' + this.feature_id;
            console.log('Loading characteristics from:', url);
            return axios.get(url)
            .then(response => {
                console.log('Characteristics response:', response.data);
                if (response.data && response.data.status === 'success') {
                    this.characteristics = response.data.characteristics || [];
                    console.log('Loaded characteristics:', this.characteristics);
                } else {
                    console.log('No characteristics found or error in response');
                    this.characteristics = [];
                }
                return this.characteristics;
            })
            .catch(error => {
                console.error('Error loading characteristics:', error);
                this.characteristics = [];
                return [];
            });
        },
        
        loadData: function(offset=0,limit=50) {
            if (!this.feature || !this.feature.data_file) {
                this.errors = ['No data file available for this feature'];
                return;
            }
            
            this.data_loading_dialog=true;
            vm=this;
            let url=CI.base_url + '/api/geospatial_features/read_csv/'+this.ProjectID+'/'+this.feature.id+'?offset='+offset+'&limit='+limit;            
            axios.get(url)
            .then(function (response) {
                if(response.data){
                    vm.feature_data=response.data;
                    vm.data_loading_dialog=false;
                }
            })
            .catch(function (error) {
                console.log(error);
                vm.data_loading_dialog=false;
                vm.errors=error;
            })
            .then(function () {
                console.log("request completed");
                vm.data_loading_dialog=false;
            });
        },
        navigatePage: function(page)
        {
            page_offset=(page - 1) * this.PaginationPageSize;
            this.loadData(page_offset, this.PaginationPageSize);
        },
        sleep: function(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },
        
        getDataTypeColor: function(dataType) {
            const colors = {
                'int32': 'blue',
                'int64': 'blue',
                'float64': 'green',
                'object': 'orange',
                'geometry': 'purple',
                'unknown': 'grey'
            };
            return colors[dataType] || 'grey';
        },
        
        showCharacteristicInfo: function(characteristic) {
            // Show characteristic metadata in a dialog or tooltip
            console.log('Characteristic info:', characteristic);
        },
        
        getHeaderMetadata: function(headerValue) {
            const characteristic = this.characteristics.find(char => char.name === headerValue);
            return characteristic ? characteristic.metadata : null;
        },
        
        getHeaderDataType: function(headerValue) {
            const characteristic = this.characteristics.find(char => char.name === headerValue);
            return characteristic ? characteristic.data_type : 'unknown';
        },
        
        getHeaderLabel: function(headerValue) {
            const characteristic = this.characteristics.find(char => char.name === headerValue);
            return characteristic ? characteristic.label : null;
        },
    },  
    template: `
        <div class="geospatial-feature-data-component h-100 m-3" v-if="feature">
            <v-row>
                <v-col cols="12">
                    <v-card>
                        <v-card-title>
                            {{$t('Data')}} - {{feature.name || feature.file_name || 'Unnamed Feature'}}
                        </v-card-title>
                        
                        <v-card-text>
                            <v-row v-if="data_loading_dialog">
                                <v-col cols="12" class="text-center">
                                    <v-progress-circular
                                        indeterminate
                                        color="primary"
                                    ></v-progress-circular>
                                    <p class="mt-2">{{$t('Loading data...')}}</p>
                                </v-col>
                            </v-row>

                            <v-row v-else-if="errors.length > 0">
                                <v-col cols="12" class="text-center">
                                    <v-alert type="error">
                                        {{$t('Error loading data:')}} {{errors}}
                                    </v-alert>
                                </v-col>
                            </v-row>

                            <v-row v-else-if="!feature_data.records || feature_data.records.length === 0">
                                <v-col cols="12" class="text-center">
                                    <v-alert type="info">
                                        {{$t('No data available')}}
                                    </v-alert>
                                </v-col>
                            </v-row>

                            <v-row v-else>
                                <v-col cols="12">
                                    <v-data-table
                                        :headers="tableHeaders"
                                        :items="tableItems"
                                        :items-per-page="rows_limit"
                                        class="elevation-2 truncate-table-td-lines-3"
                                        dense
                                        fixed-header
                                        hide-default-footer
                                        :hide-default-header="true"
                                        height="600"
                                    >
                                        <template v-slot:header="{ props: { headers } }">
                                            <thead>
                                                <tr>
                                                    <th 
                                                        v-for="header in headers" 
                                                        :key="header.value"
                                                        class="text-left"
                                                        :style="{ 
                                                            'background-color': '#f5f5f5', 
                                                            'border-bottom': '1px solid #e0e0e0',
                                                            'width': header.width || 'auto'
                                                        }"
                                                    >
                                                        <!-- Row number column -->
                                                        <div v-if="header.value === 'row_number'" class="text-center" style="background-color: #f0f0f0;">
                                                            <span class="font-weight-bold">{{ header.text }}</span>
                                                        </div>
                                                        <!-- Data columns -->
                                                        <div v-else class="d-flex flex-column">
                                                            <div class="d-flex align-center justify-start">
                                                                <span class="font-weight-bold">{{ header.text }}</span>
                                                                <v-tooltip bottom>
                                                                    <template v-slot:activator="{ on, attrs }">
                                                                        <v-icon 
                                                                            v-if="getHeaderMetadata(header.value)" 
                                                                            small 
                                                                            class="ml-1" 
                                                                            @click="showCharacteristicInfo(getHeaderMetadata(header.value))"
                                                                            style="cursor: pointer;"
                                                                            v-bind="attrs"
                                                                            v-on="on"
                                                                        >
                                                                            mdi-information-outline
                                                                        </v-icon>
                                                                    </template>
                                                                    <span>{{ getHeaderLabel(header.value) || header.text }}</span>
                                                                </v-tooltip>
                                                            </div>
                                                            <div class="d-flex align-center justify-start mt-1">
                                                                <span 
                                                                    class="text-caption font-weight-medium"
                                                                    :style="{ color: getDataTypeColor(getHeaderDataType(header.value)) }"
                                                                >
                                                                    {{ getHeaderDataType(header.value) }}
                                                                </span>                                                                 
                                                            </div>
                                                        </div>
                                                    </th>
                                                </tr>
                                            </thead>
                                        </template>
                                        
                                        <template v-slot:item.row_number="{ item }">
                                            <td class="text-center" style="background-color: #f0f0f0; font-size: 0.85em; font-weight: normal;">
                                                {{ item.row_number }}
                                            </td>
                                        </template>
                                    </v-data-table>                                                                        
                                </v-col>
                            </v-row>
                        </v-card-text>
                        
                        <v-card-actions v-if="feature_data.records" class="justify-space-between">
                            <div class="d-flex flex-column align-start">
                                <span class="text-body-2 ml-2 font-weight-bold">
                                    <span v-if="feature_data.total !== -1">
                                        Total: {{ feature_data.total || 0 }}
                                    </span>
                                </span>
                                <span v-if="feature_data.file_size_mb" class="text-caption ml-2 text-grey">
                                    <span>File size: {{ feature_data.file_size_mb }} MB</span>

                                    <span v-if="feature_data.total === -1">
                                        - Row count unavailable
                                    </span>
                                </span>
                                <span v-if="feature_data.total === -1" class="text-caption ml-2 text-grey">
                                    Showing page {{ page }} ({{ rows_limit }} records per page)
                                </span>
                            </div>
                            <v-pagination
                                v-if="TotalPages > 1"
                                v-model="page"
                                :length="feature_data.total === -1 ? 999 : TotalPages"
                                :total-visible="7"
                                @input="navigatePage"
                            ></v-pagination>
                        </v-card-actions>
                    </v-card>
                </v-col>
            </v-row>

        </div>
    `
});
