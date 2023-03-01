//form input component
Vue.component('form-input', {
    props:['value','field','title'],
    data: function () {    
        return {
        }
    },
    mounted: function () {
    },
    computed: {
        local: {
            get: function () {
                return this.value;
            },
            set: function (newValue) {
                this.$emit('input', newValue);               
            }
       },
        formTextFieldStyle(){            
            return this.$store.state.formTextFieldStyle;
        }
    },  
    template: `
            <div class="form-input-field mt-3" :class="'form-input-' + field.type"  >

                <div v-if="field.type=='nested_array'">
                    <div class="form-field form-field-table">
                        <label :for="'field-' + field.key">{{field.title}}</label>
                        <span class="small" v-if="field.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(field.key)" ><i class="far fa-question-circle"></i></span>
                        <small :id="'field-toggle-' + normalizeClassID(field.key)" class="collapse help-text form-text text-muted mb-2">{{field.help_text}}</small>
                        
                        <nested-array
                            :key="field.key" 
                            v-model="local"
                            :columns="field.props"
                            :title="field.title"
                            :path="field.key">
                        </nested-array>

                    </div>
                </div>
                <div v-else-if="field.type=='simple_array'" >
                    <label :for="'field-' + normalizeClassID(field.key)">{{field.title}}</label>

                    <div v-if="fieldDisplayType(field)=='text' ||fieldDisplayType(field)=='textarea' " >
                        <repeated-field
                                v-model=" local"
                                :field="field"                            
                            >
                        </repeated-field>
                    </div>
                    <div v-else-if="fieldDisplayType(field)=='dropdown' || fieldDisplayType(field)=='dropdown-custom'">
                        <v-combobox
                            v-model="local"
                            :items="field.enum"
                            item-text="label"
                            item-value="code"
                            :return-object="false"
                            label=""
                            :multiple="field.type=='simple_array'"
                            small-chips
                            v-bind="formTextFieldStyle"
                            background-color="#FFFFFF"                    
                        ></v-combobox>
                    </div>
                                    
                </div>

                <div  v-else-if="fieldDisplayType(field)=='text'">                            
                    <div class="form-field" :class="['field-' + field.key] ">
                        <label :for="'field-' + normalizeClassID(field.key)">{{field.title}}
                            <span class="small" v-if="field.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(field.key)" ><i class="far fa-question-circle"></i></span>
                            <span v-if="field.required==true" class="required-label"> * </span>
                        </label> 

                        <validation-provider 
                            :rules="field.rules" 
                            :debounce=500
                            v-slot="{ errors }"                            
                            :name="field.title"
                            >            
                        
                            <v-text-field
                                v-model="local"
                                v-bind="formTextFieldStyle"
                            ></v-text-field>                                                                                        
                            <small :id="'field-toggle-' + normalizeClassID(field.key)" class="collapse help-text form-text text-muted">{{field.help_text}}</small>

                            <span v-if="errors[0]" class="field-error">{{errors[0]}}</span>
                        </validation-provider>

                    </div>                                
                </div>

                <div v-else-if="fieldDisplayType(field)=='textarea'">
                    <div class="form-field-textarea"">
                        <label :for="'field-' + normalizeClassID(field.key)">{{field.title}}</label>                
                        <span class="small" v-if="field.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(field.key)" ><i class="far fa-question-circle"></i></span>

                        <validation-provider 
                            :rules="field.rules" 
                            :debounce=500
                            v-slot="{ errors }"                            
                            :name="field.title"
                            >   
                            <v-textarea
                                variant="outlined"
                                v-model="local"
                                v-bind="formTextFieldStyle"
                                class="v-textarea-field"
                                auto-grow
                                clearable
                                rows="2"
                                row-height="40"
                                max-height="200"
                                max-rows="5"                            
                                density="compact"
                            ></v-textarea>
                            <small :id="'field-toggle-' + normalizeClassID(field.key)" class="collapse help-text form-text text-muted mb-2">{{field.help_text}}</small>

                        <span v-if="errors[0]" class="field-error">{{errors[0]}}</span>
                        </validation-provider>
                    </div>
                </div> 

                <div v-else-if="fieldDisplayType(field)=='dropdown-custom'">
                    <div class="form-field-dropdown-custom">
                        <label :for="'field-' + normalizeClassID(field.key)">{{field.title}}</label>                
                        <span class="small" v-if="field.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(field.key)" ><i class="far fa-question-circle"></i></span>
                        <v-combobox
                            v-model="local"
                            :items="field.enum"
                            item-text="label"
                            item-value="code"
                            :return-object="false"
                            label=""
                            :multiple="field.type=='simple_array'"
                            v-bind="formTextFieldStyle"
                            background-color="#FFFFFF"                    
                        ></v-combobox>

                        <small :id="'field-toggle-' + normalizeClassID(field.key)" class="collapse help-text form-text text-muted mb-2">{{field.help_text}}</small>
                    </div>
                </div>
                
                <div v-else-if="fieldDisplayType(field)=='dropdown'">
                    <div class="form-field-dropdown">
                        <label :for="'field-' + normalizeClassID(field.key)">{{field.title}}</label>
                        <span class="small" v-if="field.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(field.key)" ><i class="far fa-question-circle"></i></span>
                        <v-select
                            v-model="local"
                            :items="field.enum"  
                            item-text="label"
                            item-value="code"                            
                            label=""
                            outlined
                            dense
                            clearable
                            background-color="#FFFFFF"                    
                        ></v-select>
                        <small :id="'field-toggle-' + normalizeClassID(field.key)" class="collapse help-text form-text text-muted mb-2">{{field.help_text}}</small>                        
                    </div>
                </div>

                <div v-else-if="fieldDisplayType(field)=='date'">
                    <div class="form-field-date">
                        <label :for="'field-' + normalizeClassID(field.key)">{{field.title}}</label>                            
                        <editor-date-field v-model="local" :field="field"></editor-date-field>
                        <small class="help-text form-text text-muted">{{field.help_text}}</small>                            
                    </div>
                </div>               
                
                <div v-else-if="field.type=='array'">                
                    <div class="form-field form-field-table">
                        <label :for="'field-' + field.key">{{field.title}}</label>
                        <span class="small" v-if="field.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(field.key)" ><i class="far fa-question-circle"></i></span>
                        <small :id="'field-toggle-' + normalizeClassID(field.key)" class="collapse help-text form-text text-muted mb-2">{{field.help_text}}</small>
                        <table-grid-component 
                            v-model="local" 
                            :columns="field.props" 
                            class="border elevation-1"
                            >
                        </table-grid-component>
                    </div>
                </div>

            </div>  `,
    methods:{        
        update: function (value)
        {
            this.$emit('input', value);
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