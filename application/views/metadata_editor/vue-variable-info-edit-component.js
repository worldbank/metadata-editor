///variable info edit form
Vue.component('variable-info', {
    props:['value'],
    data: function () {    
        return {   
            //variable:this.value,            
            variable_formats:{
                "numeric": "Numeric",
                "fixed": "Fixed string"
            },
            variable_intervals:{
                "contin": "Continuous",
                "discrete": "Discrete"
            },
            missing_template:[
                {
                    "key": "value",
                    "title": "Value",
                    "type": "text"
                }
            ],
        }
    },
    /*methods: {
        updateValue: function () {
            console.log("emitting variable change",this.value);
          this.$emit('updateVariable', this.value);
        }
    },*/
    created: function(){
        
    },
    computed: {
        variable: function()
        {
            return this.value;
        }
    },
    template: `
        <div class="variable-categories-edit-component" style="height:100vh">
            <!--var-format-->
            <div style="font-size:small;" class="mb-2" >
                <div class="section-title p-1 bg-variable"><strong>Variable information</strong></div>

                <div class="p-2" v-if="variable">

                <div class="form-group form-field switch-field">                    
                    <v-switch
                    v-model="variable.var_wgt"
                    label="is weight variable?"
                    true-value="1"
                    false-value="0"
                    ></v-switch>                    
                </div>


                <div class="form-group form-field">
                    <label>Interval type</label>                     
                    <select 
                        v-model="variable.var_intrvl" 
                        class="form-control  form-control-sm form-field-dropdown"
                        id="variable_intervals" 
                    >
                        <option value="">Select</option>
                        <option v-for="(option_key,option_value) in variable_intervals" v-bind:value="option_value">
                            {{ option_key }}
                        </option>
                    </select>
                    <small class="help-text form-text text-muted">{{variable.var_intrvl}}</small> 
                </div>
                
                <div class="form-group form-field">
                    <label>Format</label>
                    <select 
                        v-model="variable.var_format.type" 
                        class="form-control  form-control-sm form-field-dropdown"
                        id="var_format_type" 
                    >
                        <option value="">Select</option>
                        <option v-for="(option_key,option_value) in variable_formats" v-bind:value="option_value">
                            {{ option_key }}
                        </option>
                    </select>
                    <small class="help-text form-text text-muted">{{variable.var_format.type}}</small>                    
                </div>

                <div class="form-group form-field">                                        
                    <div class="row no-gutters">
                        <div class="col">
                            <label>Min</label>
                            <input type="number" class="form-control form-control-sm form-control-xs" v-model="variable.var_valrng.range.min" />
                        </div>
                        <div class="col mr-1 ml-1">
                            <label>Max</label>
                            <input type="number" class="form-control form-control-sm form-control-xs" v-model="variable.var_valrng.range.max" />
                        </div>
                        <div class="col">
                            <label>Decimals</label>
                            <input type="number" class="form-control form-control-sm form-control-xs" v-model="variable.var_dcml" />
                        </div>
                    </div>    
                </div>

                <div class="form-group form-field" v-if="variable.var_invalrng">
                    <label>Missing</label>
                    <div v-for="i in 5">
                        <input type="text" class="form-control form-control-sm form-control-xs" v-model="variable.var_invalrng.values[i-1]" />
                    </div>
                </div>

                </div>

            </div>
            <!--var-format-end-->
            
        </div>          
        `
});


