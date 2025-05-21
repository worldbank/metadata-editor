//Metadata Form ///////////////////////////////////////////////////
Vue.component('metadata-form', {
    props: ['title', 'items', 'depth', 'css_class','path', 'field','active_section'],
    data() {
        return {
            showChildren: true
        }
    },
    mounted:function(){
        //collapse all sections by default
        /*if (this.depth>0){
            this.toggleChildren();
        }*/
        //this.active_section="not yet";
    },
    methods: {
        toggleChildren() {
            this.showChildren = !this.showChildren;
        },
        toggleNode(event){
            alert("event toggleNode");
        },
        showFieldError(field,error){
            //field_parts=field.split("-");
            //field_name=field_parts[field_parts.length-1];
            //return error.replace(field,field_name);
            return error.replace(field,'');
        },
        onSectionToggle(event){
            alert("section changed");
        },
        activeSection(section_name){
            //this.active_section=section_name;
        },
        showActiveSection(field_key,active_section)
        {
            //console.log("field_key:",field_key);
            //if (typeof active_section!=="undefined"){return true;}

            console.log("compare- ",active_section,field_key);
            if (field_key==active_section || typeof active_section=="undefined" ){
                return true;
            }

            //field_key study_description.version
            //active_section study_description.version, study_description, version
            sections=active_section.split(".");
            valid_sections=[];
            if(sections.length>1){
                valid_sections.push(sections[0]);
            }
            valid_sections.push(active_section);
            console.log("Valid sections",valid_sections);

            //is_valid=false;

            if (typeof active_section!=="undefined" && typeof field_key!=="undefined" ){
                for (let i = 0; i < valid_sections.length; i++) {
                    if (field_key ==valid_sections[i]){
                        console.log("matched: ", field_key, "with - ", valid_sections[i]);
                        return true;
                        //is_valid=true;
                        break;
                    }else{
                        console.log("NOT matched: ", field_key, "with - ", valid_sections[i]);
                    }
                }

                //check if section is part of the key
                if(field_key.indexOf(active_section)!==-1){
                    return true;
                }
            }            
            return false;
        }
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
        <div :class="'metadata-form ' + css_class + ' ' + 'field-type-' + field.type + ' ' + field.key"   >
            <!-- [x{{active_section}}x] {{depth}}-->

            <template v-for="item in items">

            <!-- form-section-container -->
                <div v-if="item.type=='section_container'"  class="form-section-container" >
                    
                    <div v-show="showActiveSection(item.key,active_section)">
                    <template>
                        <div style="font-size:18px;font-weight:bold;" class="section-container-title">{{item.title}} - {{item.key}}</div>

                        
                                <metadata-form
                                    v-show="showChildren"                                     
                                        :items="item.items" 
                                        :title="item.title"
                                        :depth="depth + 1"
                                        :path="item.key"
                                        :field="item"
                                        :css_class="'lvl-' + depth"
                                        :active_section="active_section"
                                    >
                                </metadata-form>
                            
                            
                        
                    </template>
                    </div>
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
                                <metadata-form
                                    v-show="showChildren"                                     
                                        :items="item.items" 
                                        :title="item.title"
                                        :depth="depth + 1"
                                        :path="item.key"
                                        :field="item"
                                        :css_class="'lvl-' + depth"
                                        :active_section="active_section"
                                    >
                                </metadata-form>
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
                            :rules="field.rules" 
                            :debounce=500
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


                <div v-if="field.type=='array'">                
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

        <div v-if="field.type=='simple_array'">
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

        <div v-if="field.type=='nested_array'">
            <label :for="'field-' + normalizeClassID(field.key)">{{title}}</label>
            <nested-section 
                :value="formData[field.key]"                                         
                :columns="field.props"
                :title="title"
                :path="field.key">
            </nested-section>  
        </div>

        <div v-if="field.type=='identification_section'">
            <label :for="'field-' + normalizeClassID(field.key)">{{title}}</label>
            <identification-section 
                :value="formData[field.key]"                                         
                :columns="field.props"
                :path="field.key">
            </identification-section>  
        </div>



            </template> 

            
        </div>
    `
})


<?php return;?>
<div v-if="depth>0" class="label-wrapper" @click="toggleChildren" zv-show="showActiveSection(field,active_section)">

<div v-if="field.type=='section'"  class="tree-node form-section" :class="hasChildrenClass" >                            
    {{ title }} key:{{field.key}} - {{active_section}}
    <div v-show="field.key==active_section" style="color:red">matched</div>
    <span class="float-right section-toggle-icon"><i class="fas" :class="toggleClasses"></i></span>
</div>

<div v-if="field.type=='array'">
    <div class="form-group form-field form-field-table">
        <label :for="'field-' + normalizeClassID(path)">{{title}}</label>
        <grid-component
            :id="'field-' + normalizeClassID(path)" 
            :value="formData[field.key]"                                         
            :columns="field.props"
            :path="field.key"
            :field="field"
            >
        </grid-component>  
    </div>    
</div>

<div v-if="field.type=='simple_array'">
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

<div v-if="field.type=='nested_array'">
    <label :for="'field-' + normalizeClassID(field.key)">{{title}}</label>
    <nested-section 
        :value="formData[field.key]"                                         
        :columns="field.props"
        :title="title"
        :path="field.key">
    </nested-section>  
</div>

<div v-if="field.type=='identification_section'">
    <label :for="'field-' + normalizeClassID(field.key)">{{title}}</label>
    <identification-section 
        :value="formData[field.key]"                                         
        :columns="field.props"
        :path="field.key">
    </identification-section>  
</div>

<div v-if="field.type=='textarea'">

    <div class="form-group form-field" :class="['field-' + field.key, field.class] ">
        <label :for="'field-' + normalizeClassID(field.key)">{{title}}</label>
        <textarea
            v-model="formData[field.key]"        
            class="form-control form-field-textarea" 
            :id="'field-' + normalizeClassID(field.key)"                                     
        ></textarea>
        <small class="help-text form-text text-muted">{{field.help_text}}</small>                            
    </div>

</div> 


<div v-if="field.type=='dropdown'">

    <div class="form-group form-field" :class="['field-' + field.key, field.class] ">
        <label :for="'field-' + normalizeClassID(field.key)">{{title}}</label>
        <select 
            v-model="formData[field.key]" 
            class="form-control form-field-dropdown"
            :id="'field-' + normalizeClassID(field.key)" 
        >
            <option value="">Select</option>
            <option v-for="(option_key,option_value) in field.enum" v-bind:value="option_value">
                {{ option_key }}
            </option>
        </select>
        <small class="help-text form-text text-muted">{{formData[field.key]}}</small>
        <small class="help-text form-text text-muted">{{field.help_text}}</small>
    </div>

</div>  



<metadata-form
v-show="showChildren" 
v-for="item in items" 
    :items="item.items" 
    :title="item.title"
    :depth="depth + 1"
    :path="item.key"
    :field="item"
    :css_class="'lvl-' + depth"
    :active_section="active_section" 
>
</metadata-form>