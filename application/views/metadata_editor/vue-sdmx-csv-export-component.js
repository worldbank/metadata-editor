/// SDMX CSV Export Options Component
Vue.component('sdmx-csv-export-options', {
    props:['value'],
    data: function () {    
        return {
            field_data: this.value,
            project_info: {},
            export_options: {
                "structure_type": {
                    "title": this.$t("structure_type"),
                    "value": "DATAFLOW",
                    "type": "text",
                    "required": true,
                    "description": this.$t("sdmx_structure_type_description")
                },
                "structure_id": {
                    "title": this.$t("structure_id"),
                    "value": "",
                    "type": "text",
                    "required": true,
                    "description": this.$t("sdmx_structure_id_description")
                },
                "action": {
                    "title": this.$t("action"),
                    "value": "I",
                    "type": "dropdown",
                    "required": true,
                    "description": this.$t("sdmx_action_description"),
                    "enum": {
                        "I": this.$t("insert"),
                        "U": this.$t("update"),
                        "D": this.$t("delete"),
                        "R": this.$t("replace")
                    }
                }
            },
            dimensions: [],
            panels: [0],
            inline_export: false
        }
    },
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        },
        StudyIDNO(){
            return this.project_info.study_idno;
        },
        ProjectType(){
            return this.$store.state.project_type;
        },        
        base_url() {
            return CI.site_url;
        },        
        series_idno() {
            return _.get(this.$store.state.formData, 'series_description.idno') || '';
        },
        dimensionColumns() {
            return [
                {
                    key: 'key',
                    title: this.$t('key'),
                    type: 'text'
                },
                {
                    key: 'value',
                    title: this.$t('value'),
                    type: 'text'
                }
            ];
        }
    },
    watch: {
        series_idno: {
            handler: function(newVal, oldVal) {
                if (this.dimensions.length > 0 && this.dimensions[0].key === "INDICATOR") {
                    this.dimensions[0].value = newVal || "";
                }
            },
            immediate: true
        },
        '$store.state.formData': {
            handler: function(newVal, oldVal) {
                if (newVal && newVal !== oldVal) {
                    this.$nextTick(() => {
                        this.initializeDimensions();
                    });
                }
            },
            deep: true
        }
    },
    mounted() {
        this.initializeDimensions();
    },
    methods: {
        initializeDimensions() {
            // Initialize with default INDICATOR dimension
            this.dimensions = [
                {
                    "key": "INDICATOR",
                    "value": this.series_idno || ""
                }
            ];
        },
        async onExportOptions() {
            const requestBody = {
                inline: this.inline_export,
                structure_type: this.export_options.structure_type.value,
                structure_id: this.export_options.structure_id.value,
                action: this.export_options.action.value
            };
            
            if (this.dimensions && this.dimensions.length > 0) {
                const dimensionsObj = {};
                this.dimensions.forEach(dim => {
                    if (dim.key && dim.value) {
                        dimensionsObj[dim.key] = dim.value;
                    }
                });
                if (Object.keys(dimensionsObj).length > 0) {
                    requestBody.dimensions = dimensionsObj;
                }
            }
            
            try {
                const response = await axios.post(`${this.base_url}/api/sdmx/csv/${this.ProjectID}`, requestBody, {
                    headers: {
                        'Content-Type': 'application/json'
                    }//,
                    //responseType: 'blob' // Important for handling CSV files
                });
                
                // Handle the response based on content type
                const contentType = response.headers['content-type'];
                if (contentType && contentType.includes('text/csv')) {
                    // Download CSV file
                    const blob = new Blob([response.data], { type: 'text/csv' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `sdmx_export_${this.ProjectID}_${new Date().toISOString().slice(0,10)}.csv`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                } else {
                    // Handle other response types (e.g., inline display)
                    const text = response.data;
                    const newWindow = window.open('', '_blank');
                    newWindow.document.write(text);
                    newWindow.document.close();
                }
            } catch (error) {
                console.error('Export failed:', error.response.data);
                if (error.response && error.response.data) {
                    try {                        
                        const errorData = error.response.data;
                        if (errorData.message) {
                            alert(`Export failed: ${errorData.message}`);
                        } else {
                            alert(`Export failed: ${error.response.status} ${error.response.statusText}`);
                        }
                    } catch (parseError) {
                        alert(`Export failed: ${error.response.status} ${error.response.statusText}`);
                    }
                } else {
                    alert(`Export failed: ${error.message}`);
                }
            }
        },
        
        resetExportOptions() {
            this.export_options.structure_type.value = "DATAFLOW";
            this.export_options.structure_id.value = "";
            this.export_options.action.value = "I";
            this.inline_export = false;
            this.initializeDimensions();
        }
    },
    template: `
        <div class="import-options-component mt-5 p-3">
            <v-card>
                <v-card-title>
                    <v-icon left>mdi-file-document-arrow-right-outline</v-icon>
                    {{$t("Export SDMX CSV")}}
                </v-card-title>
                <v-card-subtitle>{{$t("sdmx_csv_export_note")}}</v-card-subtitle>
            
                <v-card-text>
                
                <v-expansion-panels multiple v-model="panels">
                    <v-expansion-panel>
                        <v-expansion-panel-header>
                            {{$t("SDMX Parameters")}}
                        </v-expansion-panel-header>
                        <v-expansion-panel-content>
                            <div class="mb-4">
                                <table class="table table-sm table-bordered table-hover table-striped mb-0 pb-0" style="font-size:small;">
                                    <tr>
                                        <th>{{$t("option")}}</th>
                                        <th>{{$t("value")}}</th>
                                    </tr>
                                    <template v-for="(kv,kv_key) in export_options">                                            
                                    <tr>
                                        <td>
                                            {{kv.title}}
                                            <span v-if="kv.required" class="required-label"> * </span>
                                        </td>
                                        <td>
                                            <input v-if="kv.type === 'text'" 
                                                   type="text" 
                                                   class="form-control" 
                                                   v-model="kv.value"
                                                   :placeholder="kv.title"
                                                   :required="kv.required"/>
                                            <select v-if="kv.type === 'dropdown'" 
                                                    class="form-control" 
                                                    v-model="kv.value"
                                                    :required="kv.required">
                                                <option v-for="(enum_val,enum_key) in kv.enum" v-bind:value="enum_key">
                                                    {{ enum_val }}
                                                </option>
                                            </select>
                                        </td>
                                    </tr>                                            
                                    </template>
                                </table>
                                
                                <div class="mt-4">
                                    <label class="form-label">{{$t("dimensions")}}</label>
                                    <div class="text-muted small mb-2">{{$t("sdmx_dimensions_description")}}</div>
                                    <table-grid-component 
                                        v-model="dimensions" 
                                        :columns="dimensionColumns"
                                        class="border elevation-1"
                                    >
                                    </table-grid-component>
                                </div>
                            </div>
                        </v-expansion-panel-content>
                    </v-expansion-panel>
                </v-expansion-panels>

                <div class="mb-3 mt-4">
                    <v-checkbox
                        v-model="inline_export"
                        :label="$t('Export as inline display')"
                        color="primary"
                    ></v-checkbox>
                </div>

                <v-btn color="primary" @click="onExportOptions()">
                    <v-icon left>mdi-download</v-icon>
                    {{$t("export_csv")}}
                </v-btn>
                
                <v-btn color="secondary" @click="resetExportOptions()" class="ml-2">
                    <v-icon left>mdi-refresh</v-icon>
                    {{$t("reset_options")}}
                </v-btn>

                </v-card-text>
            </v-card>
        </div>          
    `    
});
