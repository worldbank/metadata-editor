///variable info edit form
Vue.component('variable-info', {
    props:['value'],
    data: function () {    
        return {   
            //variable:this.value,            
            variable_formats:{
                "numeric": this.$t("numeric"),
                "character": this.$t("string"),
                "fixed": this.$t("fixed_string")
            },
            variable_intervals:{
                "contin": this.$t("contin"),
                "discrete": this.$t("discrete")
            },
            missing_template:[
                {
                    "key": "value",
                    "title": this.$t("value"),
                    "type": "text"
                }
            ],
        }
    },
    methods: {
        OnValueUpdate: function () {
          this.variable.update_required = true;
          this.$emit('input', this.variable);
        },
        GetFieldTitle: function (code, default_title='') {
            let template_field=this.FindTemplateByItemKey(this.VariableTemplate.items,code);
            if (template_field){
                return template_field.title;
            }
            return default_title;
        },
        FindTemplateByItemKey: function (items,key){            
            let item=null;
            let found=false;
            let i=0;

            while(!found && i<items.length){
                if (items[i].key==key){
                    item=items[i];
                    found=true;
                }else{
                    if (items[i].items){
                        item=this.FindTemplateByItemKey(items[i].items,key);
                        if (item){
                            found=true;
                        }
                    }
                }
                i++;                        
            }
            return item;        
        }

    },
    created: function(){
        
    },
    computed: {       
        variable:{
            get(){
                if (this.value){
                    if (!this.value.var_format){
                        this.value.var_format={
                            "type": ""
                        }                
                    }
                    if (!this.value.var_format.type){
                        this.value.var_format.type="";
                    }
                }
                return this.value;
            },
            set(newValue){
                this.$emit('input', newValue);                
            }
        },
        VariableTemplate: function()
        {
            let items=this.$store.state.formTemplate.template.items;
            let item=this.FindTemplateByItemKey(items,'variable');
            return item;        
        }
    },
    template: `
        <div class="variable-categories-edit-component" style="height:100vh" v-if="variable">
            <!--var-format-->
            <div style="font-size:small;" class="mb-2" >
                <div class="section-title p-1 bg-variable"><strong>{{$t("variable_information")}}</strong></div>

                <div class="p-2" v-if="variable">

                <div class="form-group form-field switch-field">
                    <v-switch class="ma-0 pa-0"
                    hide-details="true"
                    v-model="variable.is_key"
                    :label="GetFieldTitle('variable.is_key',$t('is_key_variable'))"
                    true-value="1"
                    false-value="0"
                    ></v-switch>
                </div>

                <div class="form-group form-field switch-field">
                    <v-switch
                    class="ma-0 pa-0"
                    v-model="variable.var_wgt"
                    :label="GetFieldTitle('variable.var_wgt',$t('is_weight_variable'))"
                    true-value="1"
                    false-value="0"
                    ></v-switch>
                </div>


                <div class="form-group form-field">
                    <label>{{GetFieldTitle('variable.var_intrvl',$t("interval_type"))}}</label>                     
                    <select 
                        v-model="variable.var_intrvl" 
                        class="form-control  form-control-sm form-field-dropdown"
                        id="variable_intervals" 
                    >
                        <option value="">-</option>
                        <option v-for="(option_key,option_value) in variable_intervals" v-bind:value="option_value">
                            {{ $t(option_value) }}
                        </option>
                    </select>
                </div>
                
                <div class="form-group form-field">
                    <label>{{GetFieldTitle('variable.var_format.type',$t("format"))}}</label>
                    <select 
                        v-model="variable.var_format.type" 
                        class="form-control  form-control-sm form-field-dropdown"
                        id="var_format_type" 
                    >
                        <option value="">-</option>
                        <option v-for="(option_key,option_value) in variable_formats" v-bind:value="option_value">
                        {{$t(option_value)}}
                        </option>
                    </select>
                </div>

                <div class="form-group form-field">                                        
                    <div class="row no-gutters">
                        <div class="col">
                            <label>{{GetFieldTitle('variable.var_valrng.range.min',$t("min"))}}</label>
                            <input type="number" class="form-control form-control-sm form-control-xs" v-model="variable.var_valrng.range.min" />
                        </div>
                        <div class="col mr-1 ml-1">
                            <label>{{GetFieldTitle('variable.var_valrng.range.max',$t("max"))}}</label>
                            <input type="number" class="form-control form-control-sm form-control-xs" v-model="variable.var_valrng.range.max" />
                        </div>
                        <div class="col">
                            <label>{{GetFieldTitle('variable.var_dcml',$t("decimals"))}}</label>
                            <input type="number" class="form-control form-control-sm form-control-xs" v-model="variable.var_dcml" />
                        </div>
                    </div>    
                </div>

                <div class="form-group form-field" v-if="variable.var_invalrng">
                    <label>{{GetFieldTitle('variable.var_invalrng.values',$t("missing"))}}</label>
                    
                        <repeated-field
                                @input="OnValueUpdate"  
                                v-model="variable.var_invalrng.values"
                                :field="missing_template"
                            >
                        </repeated-field>
                </div>

                </div>

            </div>
            <!--var-format-end-->
            
        </div>          
        `
});


