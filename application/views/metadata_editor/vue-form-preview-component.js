//v-form
Vue.component('v-form-preview', {
    props: ['title', 'items', 'depth', 'css_class','path', 'value'],
    data() {
        return {
        }
    },
    created() {
        this.field= this.$store.state.treeActiveNode;
    },    
    methods:{
        isEmpty: function(data){
            let tmp=JSON.parse(JSON.stringify(data));
            this.removeEmpty(tmp);

            return tmp;
        },
        removeEmpty: function (obj) {
            vm=this;
            try {
            $.each(obj, function(key, value){
                if (value === "" || value === null || ($.isArray(value) && value.length === 0) ){
                    delete obj[key];
                } else if (JSON.stringify(value) == '[{}]' || JSON.stringify(value) == '[[]]'){
                    delete obj[key];
                } else if (Object.prototype.toString.call(value) === '[object Object]') {
                    vm.removeEmpty(value);
                } else if ($.isArray(value)) {
                    $.each(value, function (k,v) { vm.removeEmpty(v); });
                }
            });
            }catch (error) {
                console.error(error);
            }
        }
    },
    computed: {       
        formData () {
            return this.$deepModel('formData')
        },
        
        ProjectType(){
            return this.$store.state.project_type;
        }
        
    },
    template: `
        <div :class="'v-form ' + css_class"   style="background:white;padding:5px;">

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
                        <div class="card-x">
                            
                            <div class="card-header-x border-bottom ml-3">
                                <h5>{{item.title}}</h5>
                            </div>
                            <div class="card-body">
                                <v-form-preview
                                        :items="item.items" 
                                        :title="item.title"
                                        :depth="depth + 1"
                                        :path="item.key"
                                        :field="item"
                                        :css_class="'lvl-' + depth"
                                    >
                                </v-form-preview>
                            </div>
                            
                        </div>
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

                        <div class="text-block">{{formData[item.key]}}</div>
                   
                    </div>

                </div>
                <!--end-text-field-->


            <div v-if="item.type=='array'">                
                <div class="form-group form-field form-field-table" v-if="formData[item.key]">                    
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
                <label :for="'field-' + normalizeClassID(item.key)">{{item.title}}</label>
                {{formData[item.key]}}
                {{item.props}}
            </div>    
        </div>

        <div v-if="item.type=='nested_array'">
            <label :for="'field-' + normalizeClassID(item.key)">{{item.title}}</label>
            <nested-section-preview 
                :value="formData[item.key]"                                         
                :columns="item.props"
                :title="item.title"
                :path="item.key">
            </nested-section-preview>  
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


