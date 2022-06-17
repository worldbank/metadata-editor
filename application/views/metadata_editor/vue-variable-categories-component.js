///variable categories edit form
Vue.component('variable-categories', {
    props:['value'],
    data: function () {    
        return {   
            variable:this.value,
            catgry_columns:[
                {
                    "key": "value",
                    "title": "Value",
                    "type": "text"
                },
                {
                    "key": "labl",
                    "title": "Label",
                    "type": "text"
                }
            ],
            variable_formats:{
                "numeric": "Numeric",
                "fixed": "Fixed string"
            }
        }
    },
    /*methods: {
        updateValue: function () {
            console.log("emitting variable change",this.value);
          this.$emit('updateVariable', this.value);
        }
    },*/   
    template: `
        <div class="variable-categories-edit-component">            
            <div style="font-size:small;" class="border mb-2">                    
                <div class="section-title p-2 bg-primary"><strong>Categories</strong></div>
                <div style="height: 250px;overflow-y: scroll;">
                    <table-component v-model="variable.var_catgry" :columns="catgry_columns"/>
                </div>
            </div>

            <div style="font-size:small;" class="border mb-2">
                <div class="section-title p-2 bg-primary"><strong>Variable information</strong></div>

                <div class="p-2">

                <div class="form-group form-field">
                    <label>var_intrvl</label> 
                    <span><input type="text" class="form-control" v-model="variable.var_intrvl"/></span> 
                </div>

                <div class="form-group form-field">
                    <label>var_dcml</label> 
                    <span><input type="text" class="form-control" v-model="variable.var_dcml"/></span> 
                </div>
                
                <div class="form-group form-field">
                    <label>Format</label>
                    <select 
                        v-model="variable.var_format.type" 
                        class="form-control form-field-dropdown"
                        :id="var_format_type" 
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
            
        </div>          
        `
});


