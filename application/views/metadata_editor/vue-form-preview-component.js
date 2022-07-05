//v-form
Vue.component('v-form-preview', {
    props: ['title', 'items', 'depth', 'css_class','path', 'value'],
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
                        <div  class="section-container-title">{{item.title}}</div>                        
                                <v-form-preview
                                        :items="item.items" 
                                        :title="item.title"
                                        :depth="depth + 1"
                                        :path="item.key"
                                        :field="item"
                                        :css_class="'lvl-' + depth"
                                    >
                                </v-form-preview>
                    </template>                    
                </div>
                <!-- end-form-section-container -->

                <!-- form-section -->
                <div v-if="item.type=='section'"  class="form-section mb-3" >
                    <div>
                    <template>
                        <v-expansion-panels :value="0">
                            <v-expansion-panel>
                            <v-expansion-panel-header>
                                {{item.title}}
                            </v-expansion-panel-header>
                            <v-expansion-panel-content>
                                <v-form-preview
                                        :items="item.items" 
                                        :title="item.title"
                                        :depth="depth + 1"
                                        :path="item.key"
                                        :field="item"
                                        :css_class="'lvl-' + depth"
                                    >
                                </v-form-preview>
                            </v-expansion-panel-content>
                            </v-expansion-panel>
                        </v-expansion-panels>
                    </template>
                    </div>
                </div>
                <!-- end-form-section -->

                <!--text-field-->
                <div v-if="item.type=='text' || item.type=='string' || item.type=='textarea' || item.type=='dropdown'">
                    <div v-if="formData[item.key]" class="form-group form-field" :class="['field-' + item.key, item.class] ">
                        <label :for="'field-' + normalizeClassID(item.key)">
                            {{item.title}} 
                            <span class="small" v-if="item.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(item.key)" ><i class="far fa-question-circle"></i></span>
                            <span v-if="item.required==true" class="required-label"> * </span>
                        </label>

                        <div>{{formData[item.key]}}</div>
                   
                    </div>

                </div>
                <!--end-text-field-->


            <div v-if="item.type=='array'">                
            <div class="form-group form-field form-field-table" v-if="formData[item.key].length>0">
                <label :for="'field-' + normalizeClassID(item.key)">{{item.title}}</label>
                <grid-preview-component                    
                    :id="'field-' + normalizeClassID(item.key)" 
                    :value="formData[item.key]"
                    :columns="item.props"
                    :path="item.key"
                    :field="item"
                    >
                </grid-preview-component>  
            </div>    
        </div>

        <div v-if="item.type=='simple_array'">
            <div class="form-group form-field form-field-table">
                <label :for="'field-' + normalizeClassID(path)">{{title}}</label>
                {{formData[item.key]}}
                {{item.props}}
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
 




            </template> 

            
        </div>
    `
});


