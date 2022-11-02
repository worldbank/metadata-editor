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
        }
    },
    template: `
        <div :class="'metadata-form'" >

        <!-- form-section -->
        <div v-if="formField.type=='section_container'"  class="form-section m-3" >
            
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
        <div v-if="formField.type=='section'"  class="form-section" >
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

        <div v-if="formField.type=='textarea'">

            <div class="form-group form-field" :class="['field-' + formField.key, formField.class] ">
                <label :for="'field-' + normalizeClassID(formField.key)">{{formField.title}}</label>
                <textarea
                    v-model="formData[formField.key]"        
                    class="form-control form-field-textarea" 
                    :id="'field-' + normalizeClassID(formField.key)"                                     
                ></textarea>
                <small class="help-text form-text text-muted">{{formField.help_text}}</small>                            
            </div>

        </div> 


        <template v-if="formField.type=='text' || formField.type=='string'">
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

                <input type="text"
                    v-model="formData[formField.key]"
                    class="form-control"                            
                    :id="'field-' + normalizeClassID(formField.key)"                                     
                >
                <span v-if="errors[0]" class="error">{{errors[0]}}</span>
            </validation-provider>
                
                <small :id="'field-toggle-' + normalizeClassID(formField.key)" class="collapse help-text form-text text-muted">{{formField.help_text}}</small>                            
            </div>
        <!--end-text-field-->
        </template>



        <div v-if="formField.type=='array'">        
            <div class="form-group form-field form-field-table">
                <label :for="'field-' + normalizeClassID(formField.key)">{{title}}</label>
                <grid-component
                    :id="'field-' + normalizeClassID(formField.key)" 
                    :value="formData[formField.key]"                                         
                    :columns="formField.props"
                    :path="formField.key"
                    :field="formField"
                    >
                </grid-component>  
            </div>    
        </div>

        <div v-if="formField.type=='dropdown'">

            <div class="form-group form-field" :class="['field-' + formField.key, formField.class] ">
                <label :for="'field-' + normalizeClassID(formField.key)">{{formField.title}}</label>
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
            </div>

        </div>  

        </div>
    `
})


