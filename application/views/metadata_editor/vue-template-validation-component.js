/// Template validation component
Vue.component('template-validation-component', {
    data () {
        return {
          validation_errors: "",
          template_idx:-1,
          template_validation:[],
          validation_report:[]
        }
      },
    watch:{
        ProjectMetadata: {
            handler: function (val, oldVal) {
                this.validateProject();
                this.projectValidationReport();
            }            
        }
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
        projectTemplateSelectedIndex: {
            get: function () {
                if (this.template_idx>-1){
                    return this.template_idx;
                }

                let templates=this.ProjectTemplates;
                let idx=-1;
                for(let i=0;i<templates.length;i++){
                    if(templates[i].uid==this.projectTemplateUID){
                        idx=i;
                        break;
                    }
                }                
                return idx;
            },
            set: function (newValue) {
                this.template_idx = newValue;
            }
        },
        ProjectType(state){
            return this.$store.state.project_type;
        },
        ProjectMetadata(){
            return this.$store.state.formData;
        },
        TemplateValidationErrors(){
            let errors=[];
            //check validation_report for errors
            for(let i=0;i<this.validation_report.length;i++){
                if (!this.validation_report[i].result.valid){
                    errors.push(this.validation_report[i]);
                }
            }

            return errors;
        }
    },
    methods:{
        RefreshValidation: function() {
            this.validateProject();
            this.projectValidationReport();
        },
        navigateToError: function(key){
            let vm=this;
            let key_parts=key.split("[");

            //variables
            if (key.startsWith("variables")){
                store.commit('tree_active_node_path',key);
                this.$router.push(key);
                return;
            }

            store.commit('tree_active_node_path',key_parts[0]);
            this.$router.push('/study/' + key_parts[0]);
        },
        projectValidationReport: async function() 
        {
            let vm=this;
            let validation_report=[];
            this.validation_report=[];

            //recursively walk through template items and validate
            async function walkTemplate(item, metadata){
                
                if (item.hasOwnProperty("is_custom")){
                    return;
                }

                if(item.hasOwnProperty("rules")){
                    let value=_.get(metadata, item.key, null);

                    VeeValidate.validate(value, item.rules, {name:item.title}).then(result => {
                        if (item.prop_key){
                            validation_report[item.prop_key]=result;
                            vm.validation_report.push({key:item.prop_key, item:item, result:result, value:value});
                        }else{
                            validation_report[item.key]=result;
                            vm.validation_report.push({key:item.key, item:item, result:result, value:value});
                        }
                        //console.log("validation-report",item.key,validation_report);
                      });
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

            function walkTemplateProp(item, metadata, item_path=null){
                if(item.hasOwnProperty("rules")){
                    //for props metadata is single prop value
                    let value=metadata;                    
                    
                    VeeValidate.validate(value, item.rules,{name:item.title}).then(result => {
                        if (item.prop_key){
                            if (item_path!=null){

                                vm.validation_report.push({
                                    key:item_path + "." + item.key,
                                    item:item,
                                    result:result,
                                    value:JSON.stringify(value)
                                });

                                validation_report[item_path]={
                                    result:result,
                                    item:item,
                                    value:JSON.stringify(value)};
                            }else{
                                validation_report[item.prop_key]=result;
                                vm.validation_report.push({
                                    key:item.prop_key, 
                                    item:item,
                                    result:result,
                                    value:JSON.stringify(value)
                                });
                            }
                        }else{
                            validation_report[item.key]=result;
                            vm.validation_report.push({
                                key:item.key, 
                                item:item,
                                result:result,
                                value:JSON.stringify(value)
                            });
                        }
                        //console.log("validation-report-prop",item.key,validation_report);
                      });
                }

                if(item.hasOwnProperty("items")){
                    for(let i=0;i<item.items.length;i++){
                        walkTemplate(item.items[i], metadata);                        
                    }
                }

                if (item.hasOwnProperty("props")){
                    let itemMetadata=metadata;                    

                    if (itemMetadata==null){                        
                        return;
                    }

                    for (let k=0;k<itemMetadata.length;k++){
                        for(let i=0;i<item.props.length;i++){
                            let propMetadata=_.get(itemMetadata[k], item.props[i].key, null);
                            walkTemplateProp(item.props[i], propMetadata, item_path + "." + item.props[i].key + "["+k+"]");
                        }
                    }

                }
            }
            
            //validate
            walkTemplate(this.ProjectTemplate.template, this.ProjectMetadata);
        },        
        validateProject: function() {
            let vm=this;
            this.validation_errors="";
            let url=CI.base_url + '/api/editor/validate/'+this.ProjectID;

            axios.get(url)
            .then(function (response) {
                if(response.data){                    
                    console.log("validation response",response);
                }
            })
            .catch(function (error) {
                console.log("validation errors",error);                
                vm.validation_errors=error.response.data;
            })
            .then(function () {
                console.log("request completed");
            });
        }
    },     
    template: `
            <div class="summary-template-validation-component">

                <v-card>
                    <v-card-title class="d-flex justify-space-between">
                        <h6>{{$t("project_validation")}}</h6>
                        <v-btn title="Re-run validation" icon @click="RefreshValidation">
                            <v-icon small>mdi-refresh</v-icon>
                        </v-btn>
                    </v-card-title>
                    
                    <v-card-text>
                    <div style="overflow:auto;max-height:400px;">

                    <div>{{$t("Schema validation")}} <v-icon small :title="$t('Requires project to be saved')" >mdi-information-outline</v-icon></div>
                    <div class="validation-errors mt-2" v-if="validation_errors!=''" style="color:red;font-size:small;" >
                        
                        <v-list dense>
                            <template v-for="error in validation_errors.errors" >
                                <v-list-item @click="navigateToError(error.property)">
                                    <v-list-item-icon>
                                        <v-icon color="red">mdi-alert-circle</v-icon>
                                    </v-list-item-icon>
                                    <v-list-item-content>
                                        <v-list-item-title color="red">
                                            <div style="font-weight:bold;color:red">{{error.message}}</div>                                            
                                        </v-list-item-title>
                                        <v-list-item-subtitle >
                                            <div style="font-weight:normal">
                                                {{error.property}}
                                            </div>
                                        </v-list-item-subtitle>
                                    </v-list-item-content>                                                                                            
                                </v-list-item>
                            </template>
                        </v-list>

                    </div>
                    <div class="mt-3 p-2 border" style="color:green" v-else>{{$t("no_validation_errors")}}</div>

                    <div class="mt-3">{{$t("Template validation")}}</div>
                                        
                    <div>
                        <v-list dense>                            
                            <template v-for="(item, i) in TemplateValidationErrors" >
                                <v-list-item v-if="!item.result.valid" :key="i" @click="navigateToError(item.key)">
                                    <v-list-item-icon>
                                        <v-icon color="red">mdi-alert-circle</v-icon>
                                    </v-list-item-icon>
                                    <v-list-item-content>
                                        <v-list-item-title color="red">
                                            <div v-for="error in item.result.errors" style="font-weight:bold;color:red">{{error}}</div>                                            
                                        </v-list-item-title>
                                        <v-list-item-subtitle >
                                            <div style="font-weight:normal">
                                                {{item.item.title}} - {{item.key}}
                                            </div>
                                        </v-list-item-subtitle>
                                    </v-list-item-content>                                                                                            
                                </v-list-item>
                            </template>
                        </v-list>
                    </div>

                    <div v-if="TemplateValidationErrors.length==0" class="p-2 border" style="color:green">{{$t("no_validation_errors")}}</div>

                    </div>
                    </v-card-text>
                </v-card>
            </div>          
            `    
});

