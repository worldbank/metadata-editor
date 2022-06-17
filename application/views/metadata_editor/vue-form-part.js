//Metadata Form ///////////////////////////////////////////////////
Vue.component('form-part', {
    props: ['title', 'items', 'depth', 'css_class','path', 'field','active_section'],
    data() {
        return {            
        }
    },
    created() {
        console.log(this.field);
        this.field=this.activeSection;
      },
      beforeUpdate () {
        console.log('beforeUpdate:', this.field)
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
        <div :class="'form-part'"   >

        {{field.key}}
            
        <!-- form-section -->
        <div v-if="formField.type=='section'"  class="form-section" >
            <metadata-form                                    
                    :items="formField.items" 
                    :title="formField.title"
                    :depth="depth + 1"
                    :path="formField.key"
                    :field="field"
                    :css_class="'lvl-' + depth"
                >
            </metadata-form>
        </div>
        <!-- end-form-section -->

        <div v-if="formField.type=='textarea'">

            <div class="form-group form-field" :class="['field-' + formField.key, formField.class] ">
                <label :for="'field-' + normalizeClassID(formField.key)">{{title}}</label>
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



        <div v-if="field.type=='array'">
        ddfdfdfdfdfdfdfdfd
            <div class="form-group form-field form-field-table">
                <label :for="'field-' + normalizeClassID(field.key)">{{title}}</label>
                <grid-component
                    :id="'field-' + normalizeClassID(field.key)" 
                    :value="formData[field.key]"                                         
                    :columns="field.props"
                    :path="field.key"
                    :field="field"
                    >
                </grid-component>  
            </div>    
        </div>

        


        </div>
    `
})


