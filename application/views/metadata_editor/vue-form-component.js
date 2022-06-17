//v-form
Vue.component('v-form', {
    props: ['title', 'items', 'depth', 'css_class','path', 'field'],
    data() {
        return {
        }
    },
    mounted:function(){
    },
    methods: {
        showFieldError(field,error){
            //field_parts=field.split("-");
            //field_name=field_parts[field_parts.length-1];
            //return error.replace(field,field_name);
            return error.replace(field,'');
        },
    },
    created() {
        this.field= this.$store.state.treeActiveNode;
      },

    /*created(){
        vm=this;
        EventBus.$on('activeSection', function(data) {
            console.log("active",data,vm.active_section);
            vm.activeSection(data);
        });
    },*/
    computed: {
        toggleClasses() {
            return {
                'fa-angle-down': !this.showChildren,
                'fa-angle-up': this.showChildren
            }
        },
        hasChildrenClass() {
            return {
                'has-children': this.nodes
            }
        },
        formData () {
            return this.$deepModel('formData')
        }
    },
    /*watch: {
        '$store.state.active_section': function() {
            alert("state",this.$store.state.active_section);
            //console.log(this.$store.state.drawer)
        }
    },*/
    template: `
        <div :class="'v-form ' + css_class"   >

            <template v-for="item in items">

            <!-- form-section-container -->
                <div v-if="item.type=='section_container'"  class="form-section-container" >
                    
                    <template>
                        <div style="font-size:18px;font-weight:bold;" class="section-container-title">{{item.title}} - {{item.key}}</div>                        
                                <v-form
                                        :items="item.items" 
                                        :title="item.title"
                                        :depth="depth + 1"
                                        :path="item.key"
                                        :field="item"
                                        :css_class="'lvl-' + depth"
                                    >
                                </v-form>                        
                    </template>                    
                </div>
                <!-- end-form-section-container -->

                <!-- form-section -->
                <div v-if="item.type=='section'"  class="form-section" >
                    <!-- <div>item.key={{item.key}} - active_section={{active_section}} - {{showActiveSection(item.key,active_section)}}</div> -->
                    <div v-show="showActiveSection(item.key,active_section)">
                    <template>
                        <v-expansion-panels :value="0">
                            <v-expansion-panel>
                            <v-expansion-panel-header>
                                {{item.title}} - {{item.key}}
                            </v-expansion-panel-header>
                            <v-expansion-panel-content>
                                <v-form
                                        :items="item.items" 
                                        :title="item.title"
                                        :depth="depth + 1"
                                        :path="item.key"
                                        :field="item"
                                        :css_class="'lvl-' + depth"
                                    >
                                </v-form>
                            </v-expansion-panel-content>
                            </v-expansion-panel>
                        </v-expansion-panels>
                    </template>
                    </div>
                </div>
                <!-- end-form-section -->

                <!-- textarea-->
                <div v-if="item.type=='textarea'">

                    <div class="form-group form-field" :class="['field-' + item.key, item.class] ">
                        <label :for="'field-' + normalizeClassID(item.key)">{{item.title}}</label>
                        <textarea
                            v-model="formData[item.key]"        
                            class="form-control form-field-textarea" 
                            :id="'field-' + normalizeClassID(item.key)"                                     
                        ></textarea>
                        <small class="help-text form-text text-muted">{{item.help_text}}</small>                            
                    </div>

                </div> 

                <!--text-field-->
                <div v-if="item.type=='text' || item.type=='string' ">
                    <div class="form-group form-field" :class="['field-' + item.key, item.class] ">
                        <label :for="'field-' + normalizeClassID(item.key)">
                            {{item.title}} 
                            <span class="small" v-if="item.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(item.key)" ><i class="far fa-question-circle"></i></span>
                            <span v-if="item.required==true" class="required-label"> * </span>
                        </label>

                        <validation-provider 
                            :rules="item.rules" 
                            :debounce=500
                            ref="form" 
                            v-slot="{ errors }"                            
                            :name="item.title"
                            >

                        <input type="text"
                            v-model="formData[item.key]"
                            class="form-control"                            
                            :id="'field-' + normalizeClassID(item.key)"                                     
                        >
                        <span v-if="errors[0]" class="error">{{errors[0]}}</span>
                    </validation-provider>
                        
                        <small :id="'field-toggle-' + normalizeClassID(item.key)" class="collapse help-text form-text text-muted">{{item.help_text}}</small>                            
                    </div>

                </div>
                <!--end-text-field-->


            <div v-if="item.type=='array'">                
            <div class="form-group form-field form-field-table">
                <label :for="'field-' + normalizeClassID(item.key)">{{item.title}}</label>
                <grid-component
                    :id="'field-' + normalizeClassID(item.key)" 
                    :value="formData[item.key]"                                         
                    :columns="item.props"
                    :path="item.key"
                    :field="item"
                    >
                </grid-component>  
            </div>    
        </div>

        <div v-if="item.type=='simple_array'">
            <div class="form-group form-field form-field-table">
                <label :for="'field-' + normalizeClassID(path)">{{title}}</label>
                <simple-array-component
                    :id="'field-' + normalizeClassID(path)" 
                    :value="formData[field.key]"                            
                    :path="field.key"
                    :field="field"
                    >
                </simple-array-component>  
            </div>    
        </div>

        <div v-if="item.type=='nested_array'">
            <label :for="'field-' + normalizeClassID(item.key)">{{item.title}}</label>
            <nested-section 
                :value="formData[item.key]"                                         
                :columns="item.props"
                :title="item.title"
                :path="item.key">
            </nested-section>  
        </div>

        <div v-if="item.type=='identification_section'">
            <label :for="'field-' + normalizeClassID(field.key)">{{item.title}}</label>
            <identification-section 
                :value="formData[item.key]"                                         
                :columns="item.props"
                :path="item.key">
            </identification-section>  
        </div>


        <div v-if="item.type=='dropdown'">        
            <div class="form-group form-field" :class="['field-' + item.key, item.class] ">
                <label :for="'field-' + normalizeClassID(item.key)">{{item.title}}</label>
                <select 
                    v-model="formData[item.key]" 
                    class="form-control form-field-dropdown"
                    :id="'field-' + normalizeClassID(item.key)" 
                >
                    <option value="">Select</option>
                    <option v-for="(option_key,option_value) in item.enum" v-bind:value="option_value">
                        {{ option_key }}
                    </option>
                </select>
                <small class="help-text form-text text-muted">{{formData[item.key]}}</small>
                <small class="help-text form-text text-muted">{{item.help_text}}</small>
            </div>
        </div>  




            </template> 

            
        </div>
    `
});


