///// nested-section-preview
Vue.component('nested-section-preview', {
    props:['value','columns','path','title'],
    data: function () {    
        return {
            field_data: this.value,
            key_path: this.path
        }
    },
    mounted: function () {
    },
    computed: {
        localColumns(){
            return this.columns;
        }               
    },  
    template: `
            <div class="nested-section" >
                <template  v-for="(item,index) in field_data">

                <div :class="'nested-section-row nested-section-'+index" > 
                    <h5 v-if="field_data.length > 1" class="field-section mt-3 mb-2">[{{index+1}}] - {{ title }}</h5>

                    <div class="nested-section-body">
                    <div v-for="(column,idx_col) in localColumns" :key="column.key || idx_col" scope="row" >
                        
                            <div v-if="column.type=='nested_array' && hasColumnValue(index, column)">
                                <label :for="'field-' + normalizeClassID(column.key)">{{column.title}}</label>
                                <nested-section-preview 
                                    :value="field_data[index][column.key]"                                         
                                    :columns="column.props"
                                    :title="column.title"
                                    :path="path + '.' + index + '.' + column.key">
                                </nested-section-preview>
                            </div>

                            <div v-if="column.type=='section' && hasSectionColumnValue(index, column)">
                                <div class="form-group form-field preview-subsection">
                                    <h5 class="field-subsection-title">{{column.title}}</h5>
                                    <bounding-box-preview-map
                                        v-if="column.display_type=='bounding_box'"
                                        :value="resolveSectionData(index, column)"
                                        :field="column"
                                    ></bounding-box-preview-map>
                                    <dl v-if="column.display_type=='bounding_box' && formatBoundingBoxRows(index, column).length" class="preview-bbox-coords">
                                        <template v-for="(row, rowIdx) in formatBoundingBoxRows(index, column)">
                                            <dt :key="'bbox-' + rowIdx + '-dt'">{{ row.label }}</dt>
                                            <dd :key="'bbox-' + rowIdx + '-dd'">{{ row.value }}°</dd>
                                        </template>
                                    </dl>
                                    <template v-else>
                                        <div v-for="(prop, propIdx) in column.props" :key="prop.key || propIdx">
                                            <div v-if="hasSectionPropValue(index, column, prop)" class="mb-2 preview-field">
                                                <div class="field-label">{{prop.title}}</div>
                                                <div v-if="prop.type=='nested_array'" class="nested-section-prop">
                                                    <nested-section-preview 
                                                        :value="getSectionPropValue(index, column, prop)"                                         
                                                        :columns="prop.props"
                                                        :title="prop.title"
                                                        :path="path + '.' + index + '.' + prop.key">
                                                    </nested-section-preview>
                                                </div>
                                                <div v-else-if="prop.type=='coordinate_pairs'" class="text-block" style="white-space: pre-wrap;">{{formatCoordinatePairs(getSectionPropValue(index, column, prop))}}</div>
                                                <div v-else class="text-block" style="white-space: pre-wrap;">{{formatScalar(getSectionPropValue(index, column, prop), prop)}}</div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div v-if="isScalarColumn(column) && hasColumnValue(index, column)">
                                <div class="form-group form-field" :class="['field-' + column.key, column.class] ">
                                    <div class="field-label">{{column.title}}</div>
                                    <div class="text-block" style="white-space: pre-wrap;">{{formatScalar(getColumnValue(index, column), column)}}</div>
                                </div>
                            </div>

                            <div v-if="column.type=='array' && hasColumnValue(index, column)">
                                <div class="form-group form-field form-field-table">
                                    <label :for="'field-' + path">{{column.title}}</label>                                      
                                    <grid-preview-component 
                                        v-if="isListArray(getColumnValue(index, column))"
                                        :value="getColumnValue(index, column)"   
                                        :columns="column.props"
                                        :path="path + '['+index+']'+ column.key"
                                        :field="column"
                                        >
                                    </grid-preview-component>
                                    <div v-else class="text-block">
                                        <div v-for="(prop, propIdx) in column.props" :key="prop.key || propIdx" v-if="hasObjectPropValue(getColumnValue(index, column), prop)">
                                            <label class="field-label">{{prop.title}}</label>
                                            <div style="white-space: pre-wrap;">{{formatScalar(getObjectPropValue(getColumnValue(index, column), prop), prop)}}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        
                    </div>    
                    </div>
                    
                    </div>
                </template>

            </div>  `,
    methods:{
        isScalarColumn: function(column) {
            const types = ['text', 'string', 'textarea', 'dropdown', 'dropdown-custom', 'number', 'integer', 'boolean', 'date'];
            return types.includes(column.type);
        },
        isListArray: function(value) {
            return Array.isArray(value);
        },
        isEmptyValue: function(val) {
            if (val === '' || val === null || val === undefined) {
                return true;
            }
            if (Array.isArray(val)) {
                if (val.length === 0) {
                    return true;
                }
                return !val.some((item) => !this.isEmptyValue(item));
            }
            if (typeof val === 'object') {
                return Object.keys(val).length === 0;
            }
            return false;
        },
        getColumnValue: function(index, column) {
            return _.get(this.field_data[index], column.key);
        },
        hasColumnValue: function(index, column) {
            return !this.isEmptyValue(this.getColumnValue(index, column));
        },
        resolveSectionData: function(index, column) {
            const row = this.field_data[index];
            let data = _.get(row, column.key);
            if (!this.isEmptyValue(data)) {
                return data;
            }
            if (column.key && column.key.endsWith('-section')) {
                const baseKey = column.key.slice(0, -8);
                data = _.get(row, baseKey);
                if (!this.isEmptyValue(data)) {
                    return data;
                }
            }
            if (column.props && column.props.length) {
                const firstKey = column.props[0].key || '';
                if (firstKey.indexOf('.') !== -1) {
                    const prefix = firstKey.split('.')[0];
                    data = _.get(row, prefix);
                    if (!this.isEmptyValue(data)) {
                        return data;
                    }
                }
            }
            return null;
        },
        hasSectionColumnValue: function(index, column) {
            if (column.display_type === 'bounding_box') {
                return !this.isEmptyValue(this.resolveSectionData(index, column));
            }
            if (!column.props || !column.props.length) {
                return false;
            }
            return column.props.some((prop) => this.hasSectionPropValue(index, column, prop));
        },
        getSectionPropValue: function(index, column, prop) {
            const sectionData = this.resolveSectionData(index, column);
            const row = this.field_data[index];
            if (!prop.key) {
                return null;
            }
            if (prop.key.indexOf('.') === -1) {
                if (sectionData && !this.isEmptyValue(_.get(sectionData, prop.key))) {
                    return _.get(sectionData, prop.key);
                }
                return _.get(row, prop.key);
            }
            const root = prop.key.split('.')[0];
            let sectionBase = column.key;
            if (sectionBase && sectionBase.endsWith('-section')) {
                sectionBase = sectionBase.slice(0, -8);
            }
            if (sectionBase === root || column.key === root) {
                const relative = prop.key.substring(root.length + 1);
                if (sectionData) {
                    return _.get(sectionData, relative);
                }
            }
            if (sectionData && !this.isEmptyValue(_.get(sectionData, prop.key))) {
                return _.get(sectionData, prop.key);
            }
            return _.get(row, prop.key);
        },
        hasSectionPropValue: function(index, column, prop) {
            return !this.isEmptyValue(this.getSectionPropValue(index, column, prop));
        },
        getObjectPropValue: function(objectValue, prop) {
            if (!objectValue || !prop.key) {
                return null;
            }
            if (prop.key.indexOf('.') !== -1) {
                return _.get(objectValue, prop.key.split('.').pop());
            }
            return _.get(objectValue, prop.key);
        },
        hasObjectPropValue: function(objectValue, prop) {
            return !this.isEmptyValue(this.getObjectPropValue(objectValue, prop));
        },
        formatScalar: function(value, column) {
            if (value === null || value === undefined) {
                return '';
            }
            if (column && column.enum && Array.isArray(column.enum)) {
                const store = column.enum_store_column || 'code';
                const match = column.enum.find((opt) => opt && opt[store] == value);
                if (match && match.label) {
                    return match.label;
                }
            }
            if (typeof value === 'boolean') {
                return value ? 'true' : 'false';
            }
            return value;
        },
        formatCoordinatePairs: function(coordinates) {
            if (!Array.isArray(coordinates) || coordinates.length === 0) {
                return '';
            }
            return coordinates
                .filter((pair) => Array.isArray(pair) && pair.length >= 2)
                .map((pair) => pair[0] + ', ' + pair[1])
                .join('\n');
        },
        formatBoundingBoxRows: function(index, column) {
            const box = this.resolveSectionData(index, column);
            if (!box || typeof box !== 'object' || !column.props) {
                return [];
            }
            const rows = [];
            column.props.forEach((prop) => {
                const val = this.getSectionPropValue(index, column, prop);
                if (this.isEmptyValue(val)) {
                    return;
                }
                let display = val;
                if (typeof val === 'number' || (typeof val === 'string' && val !== '' && !isNaN(parseFloat(val)))) {
                    display = parseFloat(val);
                    display = display.toFixed(6).replace(/\.?0+$/, '');
                }
                rows.push({ label: prop.title, value: display });
            });
            return rows;
        }
    }
})
