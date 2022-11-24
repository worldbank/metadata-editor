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
            }
        }
    },
    /*methods: {
        updateValue: function () {
            console.log("emitting variable change",this.value);
          this.$emit('updateVariable', this.value);
        }
    },*/
    computed: {
        variable: function()
        {
            return this.value;
        }
    },
    template: `
        <div class="variable-categories-edit-component" style="height:100vh">
            <!--var-format-->
            <div style="font-size:small;" class="mb-2">
                <div class="section-title p-1 bg-primary"><strong>Variable information</strong></div>

                <div class="p-2">

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
                    <label>Decimal points</label> 
                    <span><input type="text" class="form-control form-control-sm" v-model="variable.var_dcml"/></span> 
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

                </div>

            </div>
            <!--var-format-end-->
            
        </div>          
        `
});


