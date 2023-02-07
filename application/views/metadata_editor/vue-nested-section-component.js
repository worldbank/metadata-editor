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
            console.log('Prop changed: ', newVal, ' | was: ', oldVal)
            console.log('key path:',this.key_path);
            this.$vueSet (this.$store.state.formData, this.key_path, newVal);
        }
    },
    mounted: function () {
        //set data to array if empty or not set
        if (!this.field_data){
            this.field_data=[{}];            
        }
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
                    <div class="label-wrapper" @click="toggleChildren(index)">
                        <div class="tree-node form-section nested-form-section" >[{{index+1}}] - {{ title }}
                            <button type="button"  class="btn btn-sm btn-link" v-on:click="remove(index)">Remove <i class="fa fa-trash-o" aria-hidden="true"></i></button>
                            <span class="float-right section-toggle-icon"><i class="fas" :class="toggleClasses(index)"></i></span>
                        </div>
                    </div>

                    <div v-show="showChildren(index)" class="nested-section-body">
                    <div v-for="(column,idx_col) in localColumns" scope="row" >
                        
                            <div v-if="column.type=='nested_array'">
                                <label :for="'field-' + normalizeClassID(column.key)">{{column.title}}</label>
                                <nested-section 
                                    :value="getData(index+'.'+column.key)"
                                    :columns="column.props"
                                    :title="column.title"
                                    :path="path + '.' + index + '.' + column.key">
                                </nested-section>  
                            </div>

                            <!-- textarea-->
                            <div v-if="column.type=='textarea'">

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
                            
                            <div  v-if="column.type=='text' || column.type=='string'">
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
                            <div v-if="column.type=='dropdown'">
                                <div class="form-group form-field">
                                    <label :for="'field-' + normalizeClassID(column.key)">{{column.title}}</label>

                                    <select 
                                        :value="getData(index+'.'+column.key)"
                                        @input="setData(index+'.'+column.key, $event.target.value)"
                                        class="form-control form-field-dropdown"
                                        :id="'field-' + normalizeClassID(column.key)" 
                                    >
                                        <option value="">Select</option>
                                        <option v-for="enum_ in column.enum" v-bind:key="enum_.key">
                                            {{ enum_ }}
                                        </option>
                                    </select>
                                    <small class="help-text form-text text-muted">{{getData(index+'.'+column.key)}}</small>
                                    <small :id="'field-toggle-' + normalizeClassID(path + '-' + column.key)" class="collapse help-text form-text text-muted">{{column.help_text}}</small>
                                </div>
                            </div>  
                            <!--end dropdown-->

                            <div v-if="column.type=='array'">
                                <div class="form-group form-field form-field-table">
                                    <label :for="'field-' + path">{{column.title}}</label>                                      
                                    <grid-component 
                                        :value="getData('['+index+']'+ column.key)"   
                                        :columns="column.props"
                                        :path="path + '['+index+']'+ column.key"
                                        >
                                    </grid-component>  
                                </div>
                            </div>


                            <!-- simple array -->
                            <div v-if="column.type=='simple_array'">
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

                <div class="d-flex justify-content-center">
                    <button type="button" class="btn btn-light btn-block btn-sm" @click="addRow" >Add row</button>    
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
            //console.log("getData",field_xpath, 'value',JSON.stringify(_.get(this.field_data, field_xpath)));
            return _.get(this.field_data, field_xpath)
        },
        setData: function (field_xpath,event){
            //console.log("setData",field_xpath,event);
            _.set(this.field_data,field_xpath,event);
            //console.log(this.field_data);
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
        } 
    }
})