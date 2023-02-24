//vue-main-form-component ///////////////////////////////////////////////////
Vue.component('form-main', {
    props: ['title', 'items', 'depth', 'css_class','path'],
    data() {
        return {
        }
    },
    created() {        
        this.field=this.activeSection;
    },
    methods:{
        activeFormFieldDisplayType()
        {
            if (this.field.display_type){
                return this.field.display_type;
            }

            if (_.includes(['text','string','integer','boolean','number'],this.field.display_type)){
                return 'text';
            }            
            
            return this.field.type;
        }
    },
    computed: {
        formData () {
            return this.$deepModel('formData')
        },
        activeSection()
        {
            return this.$store.state.treeActiveNode;
        },
        formField()
        {
            return this.field;
        },
        formTextFieldStyle(){            
            return this.$store.state.formTextFieldStyle;
        }
        
    },
    template: `
        <div class="metadata-form mt-3" >
        <!-- form-section -->
        <div v-if="activeFormFieldDisplayType()=='section_container'"  class="form-section m-3" >
            <v-form-preview                         
                    :items="formField.items" 
                    :title="formField.title"
                    :path="formField.key"
                    :field="formField"
                >
            </v-form-preview>
        </div>
        <!-- end-form-section -->

        <!-- form-section -->
        <div v-if="activeFormFieldDisplayType()=='section'"  class="form-section" >
            <h5 class="mt-3">{{formField.title}}</h5>
            <v-form                                    
                    :items="formField.items" 
                    :title="formField.title"
                    :depth="depth + 1"
                    :path="formField.key"
                    :field="formField"
                    :css_class="'lvl-' + depth"
                >
            </v-form>
        </div>
        <!-- end-form-section -->


        <div v-if="activeFormFieldDisplayType()=='nested_array'" class="mt-2 mb-3">
            <label :for="'field-' + normalizeClassID(formField.key)">{{formField.title}}</label>
            <nested-section 
                :value="formData[formField.key]"                                         
                :columns="formField.props"
                :title="formField.title"
                :path="formField.key">
            </nested-section>  
        </div>

        <div v-if="activeFormFieldDisplayType()=='textarea'">

            <div class="form-group form-field" :class="['field-' + formField.key, formField.class] ">
                <label :for="'field-' + normalizeClassID(formField.key)">{{formField.title}}</label>                
                
                <v-textarea
                    variant="outlined"
                    v-model="formData[formField.key]"
                    class="v-textarea-field"
                    auto-grow
                    clearable
                    rows="2"
                    row-height="40"
                    max-height="200"
                    max-rows="5"                            
                    density="compact"
                ></v-textarea>

                <small class="help-text form-text text-muted">{{formField.help_text}}</small>                            
            </div>

        </div> 

        <div v-if="activeFormFieldDisplayType()=='simple_array'">
            <div class="form-group form-field form-field-table">
                <label :for="'field-' + normalizeClassID(formField.key)">{{formField.title}}</label>
                <span class="small" v-if="formField.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(formField.key)" ><i class="far fa-question-circle"></i></span>
                <simple-array-component
                    :id="'field-' + normalizeClassID(formField.key)" 
                    :value="formData[formField.key]"
                    :path="formField.key"
                    :field="formField"
                    >
                </simple-array-component>  
                <small :id="'field-toggle-' + normalizeClassID(formField.key)" class="collapse help-text form-text text-muted">{{formField.help_text}}</small>
            </div>                
        </div>


        <template v-if="activeFormFieldDisplayType()=='date'">
        <!--date-field-->
            <div class="form-group form-field" :class="['field-' + formField.key, formField.class] ">

                <label :for="'field-' + normalizeClassID(formField.key)">
                    {{formField.title}}
                    <span class="small" v-if="formField.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(formField.key)" ><i class="far fa-question-circle"></i></span>
                    <span v-if="formField.required==true" class="required-label"> * </span>
                </label>
                
                <validation-provider 
                    :rules="formField.rules" 
                    :debounce=500
                    v-slot="{ errors }"                            
                    :name="formField.title"
                    >

                <editor-date-field v-model="formData[formField.key]" :field="field"></editor-date-field>
                <span v-if="errors[0]" class="error">{{errors[0]}}</span>
            </validation-provider>
                
                <small :id="'field-toggle-' + normalizeClassID(formField.key)" class="collapse help-text form-text text-muted">{{formField.help_text}}</small>                            
            </div>
        <!--end-date-field-->
        </template>


        <template v-if="activeFormFieldDisplayType()=='text'">
            <!--text-field-->
            <div class="form-group form-field" :class="['field-' + formField.key, formField.class] ">

                <label :for="'field-' + normalizeClassID(formField.key)">
                    {{formField.title}}
                    <span class="small" v-if="formField.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(formField.key)" ><i class="far fa-question-circle"></i></span>
                    <span v-if="formField.required==true" class="required-label"> * </span>
                </label>
                
                <validation-provider 
                    :rules="formField.rules" 
                    :debounce=500
                    v-slot="{ errors }"                            
                    :name="formField.title"
                    >

                <v-text-field
                    v-model="formData[formField.key]"
                    v-bind="formTextFieldStyle"
                ></v-text-field>

                <span v-if="errors[0]" class="error">{{errors[0]}}</span>
            </validation-provider>
                
                <small :id="'field-toggle-' + normalizeClassID(formField.key)" class="collapse help-text form-text text-muted">{{formField.help_text}}</small>
            </div>
        <!--end-text-field-->
        </template>



        <div v-if="activeFormFieldDisplayType()=='array'">
            <div class="form-group form-field form-field-table">
                <label :for="'field-' + normalizeClassID(formField.key)">
                    {{formField.title}}
                    <span class="small" v-if="formField.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(formField.key)" ><i class="far fa-question-circle"></i></span>
                    <span v-if="formField.required==true" class="required-label"> * </span>
                </label>

                <grid-component
                    :id="'field-' + normalizeClassID(formField.key)" 
                    :value="formData[formField.key]"                                         
                    :columns="formField.props"
                    :path="formField.key"
                    :field="formField"
                    >
                </grid-component>
                
                <small :id="'field-toggle-' + normalizeClassID(formField.key)" class="collapse help-text form-text text-muted">{{formField.help_text}}</small>
            </div>    
        </div>

        <div v-if="activeFormFieldDisplayType()=='dropdown' || activeFormFieldDisplayType()=='dropdown-custom'">

            <div class="form-group form-field" :class="['field-' + formField.key, formField.class] ">
                <label :for="'field-' + normalizeClassID(formField.key)">{{formField.title}}</label>

                <v-combobox
                    v-model="formData[formField.key]"
                    :items="formField.enum"
                    item-text="label"
                    item-value="code"
                    :return-object="false"
                    label=""                
                    outlined
                    dense
                    clearable
                    background-color="#FFFFFF"
                ></v-combobox>
                

                <?php /*
                <select 
                    v-model="formData[formField.key]" 
                    class="form-control form-field-dropdown"
                    :id="'field-' + normalizeClassID(formField.key)" 
                >
                    <option value="">Select</option>
                    <option v-for="enum_ in formField.enum" v-bind:key="enum_.key">
                        {{ enum_.value }}
                    </option>
                </select>
                <small class="help-text form-text text-muted">{{formData[formField.key]}}</small>
                <small class="help-text form-text text-muted">{{formField.help_text}}</small>
                */ ?>
            </div>

        </div>  

        </div>
    `
});



