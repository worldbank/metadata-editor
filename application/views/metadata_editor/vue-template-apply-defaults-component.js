/// Template apply defaults component
Vue.component('template-apply-defaults-component', {
    props:['value'],
    data () {
        return {
            options: 'empty',
            validation_report: [],
            is_processed: false
        }
      },
    mounted: function(){
        this.validation_report=[];
        this.is_processed=false;
    },
    watch:{
    },
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        },
        ProjectIDNo(){
            return this.$store.state.idno;
        },
        ProjectTemplates()
        {
            return this.$store.state.templates;
        },
        ProjectTemplate()
        {
            return this.$store.state.formTemplate;
        },
        projectTemplateUID(){
            return this.$store.state.formTemplate.uid;
        },
        ProjectType(state){
            return this.$store.state.project_type;
        },
        ProjectMetadata(){
            return this.$store.state.formData;
        },
        dialog: {
            get () {
                return this.value
            },
            set (val) {
                this.$emit('input', val)
            }
        }
    },
    methods:{        
        templateApplyDefaults: async function() 
        {
            let vm=this;
            this.validation_report=[];
            let project_metadata=this.ProjectMetadata;
            this.is_processed=false;

            //recursively walk through template items and apply defaults
            async function walkTemplate(item, metadata){

                if (item.hasOwnProperty("is_custom")){
                    return;
                }

                if(item.hasOwnProperty("default")){
                    let value=_.get(metadata, item.key, null);
                    
                    let item_key=item.key;

                    if (item.hasOwnProperty("prop_key")){
                        item_key=item.prop_key;
                    }

                    //set default value
                    if (vm.options=="empty" && !value){
                        _.set(metadata, item.key, item.default);
                        vm.validation_report.push({key:item_key, item:item, value:value, default:item.default});
                    }
                    else if (vm.options=="all"){
                        _.set(metadata, item.key, item.default);
                        vm.validation_report.push({key:item_key, item:item, value:value, default:item.default});
                    }                    
                }

                if(item.hasOwnProperty("items")){
                    for(let i=0;i<item.items.length;i++){
                        walkTemplate(item.items[i], metadata);
                    }
                }

                if (item.hasOwnProperty("props")){
                    let itemMetadata=_.get(metadata, item.key, null);

                    if (itemMetadata==null){
                        return;
                    }

                    for (let k=0;k<itemMetadata.length;k++){
                        for(let i=0;i<item.props.length;i++){
                            let propMetadata=_.get(itemMetadata[k], item.props[i].key, null);
                            walkTemplateProp(item.props[i], propMetadata, item.key+"["+k+"]");
                        }
                    }
                }
            }

            function walkTemplateProp(item, metadata, item_path=null)
            {
                if(item.hasOwnProperty("default")){                    
                    let value=metadata;
                    let item_key="";

                    if (!item_path){
                        item_key=item.prop_key;
                    }else{
                        item_key=item_path + "." + item.key;
                    }

                    //set default value only if has props
                    if (item.hasOwnProperty("props")){

                        if (vm.options=="empty" && !value){
                            _.set(project_metadata,item_key, JSON.parse(JSON.stringify(item.default)));
                            vm.validation_report.push({key:item_key, item:item, value:value, default:item.default});
                        }
                        else if (vm.options=="all"){
                            _.set(project_metadata,item_key, JSON.parse(JSON.stringify(item.default)));
                            vm.validation_report.push({key:item_key, item:item, value:value, default:item.default});
                        }                        
                    }                
                }

                /*if(item.hasOwnProperty("items")){                    
                    for(let i=0;i<item.items.length;i++){
                        walkTemplate(item.items[i], metadata);
                    }
                }*/

                if (item.hasOwnProperty("props")){
                    let itemMetadata=metadata;

                    for (let k=0;k<itemMetadata.length;k++){
                        for(let i=0;i<item.props.length;i++){
                            let propMetadata=_.get(itemMetadata[k], item.props[i].key, null);
                            walkTemplateProp(item.props[i], propMetadata, item_path + "." + item.props[i].key + "["+k+"]");
                        }
                    }
                }
            }
            
            //apply defaults            
            walkTemplate(this.ProjectTemplate.template, this.ProjectMetadata);
            console.log("projectMetadata",this.ProjectMetadata);
            this.is_processed=true;
        }        
    },     
    template: `
            <div class="template-apply-defaults-component">

            <!-- dialog -->
            <v-dialog v-model="dialog" max-width="500" scrollable persistent xstyle="z-index:5000">
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        {{$t('apply_template_defaults')}}
                    </v-card-title>
                    <v-card-subtitle>
                        <div class="pt-2">{{$t('apply_template_defaults_description')}}</div>
                    </v-card-subtitle>
                    <v-card-text style="min-height: 100px;">
                    <div>                    
                    
                    <v-radio-group
                        v-model="options"
                        mandatory
                        >
                        <v-radio
                            :label="$t('update_empty_fields')"
                            value="empty"
                            class="font-weigh-normal"
                        ></v-radio>
                        <v-radio
                            :label="$t('update_all_fields')"
                            value="all"
                            class="font-weigh-normal"
                        ></v-radio>
                    </v-radio-group>
                    
                    </div>

                    <div v-if="validation_report.length>0">
                        <v-divider></v-divider>
                        <div>
                            {{$t('items_updated')}}:
                        </div>
                        <ul style="margin-left:20px;">
                            <template v-for="item in validation_report">
                            <li><strong>{{item.item.title}}</strong>: {{item.key}}</li>
                            </template>
                        </ul>
                    </div>
                    <div v-if="is_processed && validation_report.length==0">
                        <v-divider></v-divider>
                        <div>
                            {{$t('no_items_updated')}}
                        </div>
                    </div>


                    </v-card-text>

                    <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="primary" text @click="templateApplyDefaults" >
                        {{$t('apply')}}
                    </v-btn>
                    <v-btn color="primary" text @click="dialog=false;is_processed=false;" >
                        {{$t('close')}}
                    </v-btn>
                    </v-card-actions>
                </v-card>
                </v-dialog>
            <!-- end dialog -->
                    
            </div>          
            `    
});

