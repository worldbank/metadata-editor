///// nested-section
Vue.component('nested-section', {
    props:['value','columns','path','title'],
    data: function () {    
        return {
            field_data: this.value,
            key_path: this.path,
            active_sections:[]
        }
    },
    watch: { 
        field_data: function(newVal, oldVal) {
            console.log("watch field_data",this.key_path,JSON.stringify(newVal), JSON.stringify(oldVal));
            this.$vueSet (this.formData, this.key_path, newVal);
        }
    },
    mounted: function () {
        console.log("mounted nested array",this.path,this.field_data);
        if (!this.field_data || typeof(this.field_data)!=='array'){
            console.log("mounted nested array - no data");
            this.field_data=[{}];
        }
    },
    computed: {
        localColumns(){
            return this.columns;
        },
        formData () {
            return this.$deepModel('formData')
        }
        
    },  
    template: `
            <div class="nested-section" >

                <template  v-for="(item,index) in field_data">

                <div :class="'nested-section-row nested-section-'+index" > 
                    <div class="label-wrapper" @click="toggleChildren(index)">
                        <div class="tree-node form-section nested-form-section" >
                            <v-icon style="padding:4px;font-weight:bold;">mdi-file-tree-outline</v-icon> {{index+1}} - {{ title }}
                            <button type="button"  class="btn btn-sm btn-link" v-on:click="remove(index)">{{$t("remove")}} <i class="fa fa-trash-o" aria-hidden="true"></i></button>
                            <span class="float-right section-toggle-icon"><i class="fas" :class="toggleClasses(index)"></i></span>
                        </div>
                    </div>

                    <div v-show="showChildren(index)" class="nested-section-body">
                    <div v-for="(column,idx_col) in localColumns" scope="row" >
                        
                            <div v-if="fieldDisplayType(column)=='nested_array'">
                                <label :for="'field-' + normalizeClassID(column.key)">{{column.title}}</label>
                                <nested-section 
                                    :value="getData(index+'.'+column.key)"
                                    :columns="column.props"
                                    :title="column.title"
                                    :path="path + '.' + index + '.' + column.key">
                                </nested-section>  
                            </div>

                            

                            <div v-if="fieldDisplayType(column)=='section'"  class="form-section" >                    
                                <template>
                                    <v-expansion-panels :value="0">
                                        <v-expansion-panel>
                                        <v-expansion-panel-header>
                                            {{column.title}}
                                        </v-expansion-panel-header>
                                        <v-expansion-panel-content>
                                            
                                            <nested-section-subsection 
                                                :value="getData(index+'.'+column.key)"
                                                :columns="column.props"
                                                :title="column.title"
                                                :path="path + '.' + index">
                                            </nested-section-subsection> 

                                        </v-expansion-panel-content>
                                        </v-expansion-panel>
                                    </v-expansion-panels>
                                </template>
                            </div>

                            <!-- textarea-->
                            <div v-if="fieldDisplayType(column)=='textarea'">

                                <div class="form-group form-field" :class="['field-' + column.key, column.class] ">
                                    <label :for="'field-' + normalizeClassID(column.key)">{{column.title}}</label>
                                    <textarea
                                        :value="getData(index+'.'+column.key)"
                                        @input="setData(index+'.'+column.key, $event.target.value)"
                                        class="form-control form-field-textarea" 
                                        :id="'field-' + normalizeClassID(column.key)"
                                    ></textarea>
                                    <small class="help-text form-text text-muted">{{column.help_text}}</small>                            
                                </div>

                            </div> 
                            
                            <div  v-if="fieldDisplayType(column)=='text'">
                            
                                <div class="form-group form-field" :class="['field-' + column.key] ">
                                    <label :for="'field-' + normalizeClassID(path + '-' + column.key)">{{column.title}}                                        
                                        <span class="small" v-if="column.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(path + ' ' + column.key)" ><i class="far fa-question-circle"></i></span>
                                        <span v-if="column.required==true" class="required-label"> * </span>
                                    </label> 
                                    <input type="text"
                                        :value="getData(index+'.'+column.key)"
                                        @input="setData(index+'.'+column.key, $event.target.value)"
                                        class="form-control" 
                                        :id="'field-' + normalizeClassID(path + '-' + column.key)"                                     
                                    >        
                                                                                                   
                                    <small :id="'field-toggle-' + normalizeClassID(path + '-' + column.key)" class="collapse help-text form-text text-muted">{{column.help_text}}</small>
                                </div>                                
                            </div>

                            <!--drop down-->
                            <div v-if="fieldDisplayType(column)=='dropdown' || fieldDisplayType(column)=='dropdown-custom' ">
                                <div class="form-group form-field">
                                    <label :for="'field-' + normalizeClassID(column.key)">{{column.title}}</label>

                                    <select 
                                        :value="getData(index+'.'+column.key)"
                                        @input="setData(index+'.'+column.key, $event.target.value)"
                                        class="form-control form-field-dropdown"
                                        :id="'field-' + normalizeClassID(column.key)" 
                                    >
                                        <option value="">Select</option>
                                        <option v-for="enum_ in column.enum" v-bind:key="enum_.code" :value="enum_.code">
                                            {{ enum_.label }}
                                        </option>
                                    </select>
                                    
                                    <small class="help-text form-text text-muted">{{getData(index+'.'+column.key)}}</small>
                                    <small :id="'field-toggle-' + normalizeClassID(path + '-' + column.key)" class="collapse help-text form-text text-muted">{{column.help_text}}</small>
                                </div>
                            </div>  
                            <!--end dropdown-->

                            <div v-if="fieldDisplayType(column)=='array'">
                                <div class="form-group form-field form-field-table">
                                    <label :for="'field-' + path">{{column.title}}</label>
                                    <span class="small" v-if="column.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(column.key + index)" ><i class="far fa-question-circle"></i></span>
                                    <small :id="'field-toggle-' + normalizeClassID(column.key + index)" class="collapse help-text form-text text-muted">{{column.help_text}}</small>
                                    <grid-component 
                                        :value="getData('['+index+']'+ column.key)"   
                                        :columns="column.props"
                                        :path="path + '['+index+']'+ column.key"
                                        >
                                    </grid-component>  
                                </div>
                            </div>


                            <!-- simple array -->
                            <div v-if="fieldDisplayType(column)=='simple_array'">
                                <div class="form-group form-field form-field-table">
                                    <label :for="'field-' + path">{{column.title}}</label>
                                    <span class="small" v-if="column.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(column.key + index)" ><i class="far fa-question-circle"></i></span>
                                    <simple-array-component                                    
                                        :value="getData('['+index+']'+ column.key)"
                                        :path="path + '['+index+']'+ column.key"
                                        :field="column"
                                        :key="path + '['+index+']'+ column.key"
                                        >
                                    </simple-array-component>
                                    <small :id="'field-toggle-' + normalizeClassID(column.key + index)" class="collapse help-text form-text text-muted">{{column.help_text}}</small>
                                </div>    
                            </div>
                            <!-- end simple array -->

                            
                    </div>    
                    </div>
                    
                    </div>
                </template>

                <div class="d-flex justify-content-center m-3">
                    <button type="button" class="btn btn-light btn-sm btn-outline-primary" @click="addRow" >{{$t("add_section")}} - {{title}}</button>
                </div>

            </div>  `,
    methods:{
        countRows: function(){
            return this.field_data.length;
        },
        addRow: function (){    
            this.field_data.push({});
            this.$emit('adding-row', this.field_data);
        },
        remove: function (index){
            this.field_data.splice(index,1);
        },
        getData: function(field_xpath){
            return _.get(this.field_data, field_xpath)
        },
        setData: function (field_xpath,event){
            _.set(this.field_data,field_xpath,event);
            Vue.set(this.field_data, 0, this.field_data[0]);
        },
        toggleChildren(index) {
            if (!this.active_sections.includes(index)) {
                this.active_sections.push(index);
            }else{
                this.active_sections = this.active_sections.filter(function(e) { return e !== index })
            }
        },
        showChildren(index)
        {
            if (this.active_sections.includes(index)) {
                return true;
            }
            return false;
        },
        toggleClasses(index) {
            return {
                'fa-angle-down': !this.showChildren(index),
                'fa-angle-up': this.showChildren(index)
            }
        },
        fieldDisplayType(field)
        {
            if (field.display_type){
                return field.display_type;
            }

            if (_.includes(['text','string','integer','boolean','number'],field.display_type)){
                return 'text';
            }            
            
            return field.type;
        }
    }
})