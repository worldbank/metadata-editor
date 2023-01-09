//vue validation-rules component
Vue.component('validation-rules-component', {
    props:['value'],
    data: function () {    
        return {
            rule_selected:'',
            validation_rules:{
                "required":{
                    "rule":"required",
                    "description":"Must have a value",
                    "param":false,
                    "value_type":"regex"
                },
                "regex":{
                    "rule":"regex",
                    "description":"Regular expression - ",
                    "param":true,
                    "value_type":"regex"
                },
                "min":{
                    "rule":"min",
                    "description":"Minimum length of text",
                    "param":true,
                    "value_type":"integer"
                },
                "max":{
                    "rule":"max_length",
                    "description":"Maximum length of text",
                    "param":true,
                    "value_type":"integer"
                },
                "alpha":{
                    "rule":"alpha",
                    "description":"Allow only alphabets",
                    "param":false
                },
                "alpha_num":{
                    "rule":"alpha_num",
                    "description":"Allow only alphabets and numbers",
                    "param":false
                },
                "numeric":{
                    "rule":"numeric",
                    "description":"Allow only numeric values",
                    "param":false
                },
                "is_uri":{
                    "rule":"is_uri",
                    "description":"Must be a valid URL",
                    "param":false
                },



            }
        }
    },    
    computed: {
        local()
        {
            return this.value ? this.value : {};
        },
        ValidationRules()
        {
            return this.validation_rules;            
            /*let filtered_={};
            let keys_=Object.keys(this.validation_rules);
            for(i=0;i<keys_.length;i++)
            {
                if (!this.isRuleInUse(keys_[i])){
                 filtered_[keys_[i]]=this.validation_rules[keys_[i]];
                }
            }
            console.log("filtered",filtered_);
            return filtered_;
            */
        }
    },
    methods:{
        update(key, value) {
            this.$emit('input', { ...this.value, [key]: value })
        },
        validateRuleValue: function(idx)
        {
            if (!this.field_data[idx]['rule']){
                return true;
            }

            let rule_key=this.field_data[idx]['rule'];
            let rule=this.validation_rules[rule_key];
            let value=this.field_data[idx]['value']

            if (rule.value_type=='regex'){
                try {
                    new RegExp(value);
                    return true;
                } catch(e) {
                    return false;
                }                
            }
            return true;

        },
        isRuleInUse: function(rule_key){
            for(i=0;i<Object.keys(this.local).length;i++)
            {
                if (this.local["rule"]==rule_key){
                    return true;
                }
            }
            return false;
        },
        ruleHasParam: function(rule){
            if (this.validation_rules[rule] && this.validation_rules[rule].param){
                return this.validation_rules[rule].param==true;
            }

            return false;
        },
        RuleDescription: function(rule){
            console.log(this.validation_rules[rule]);
            if (this.validation_rules[rule] && this.validation_rules[rule].description){
                return this.validation_rules[rule].description;
            }

            return '';
        },
        remove: function (rule_name){
            Vue.delete(this.value, rule_name);
        },
        addRule: function ()
        {
            rule_info=this.validation_rules[this.rule_selected];
            this.local[this.rule_selected]=rule_info.param==true ? '' : true;
            this.rule_selected='';
        }        
        
    },
    template: `
            <div class="validation-rules-component">

            <div class="p-2 mb-3 row justify-content-md-right">
                <div class="col-md-6">
                <div class="form-inline">
                <select 
                    v-model="rule_selected" 
                    class="custom-select" 
                >
                    <option value="">Select rule</option>
                    <option v-for="(rule in ValidationRules">
                        {{ rule.rule }}
                    </option>
                </select>
                <button type="button" class="ml-1 btn btn-primary" @click="addRule" :disabled="rule_selected==''">Add</button>
                </div>
                </div>
            </div>

            <table class="table table-striped table-sm ">
                <thead class="thead-light">
                <tr>
                    <th>Rule</th>
                    <th>Value</th>
                    <th></th>
                </tr>
                </thead>
                <tr v-for="(value_, name, index) in local">
                    <td>
                        <div class="text-primary">{{name}}</div>
                        <div class="text-secondary" style="font-size:small;margin-top:5px;">{{RuleDescription(name)}}</div>
                    </td>
                    <td><div v-if="ruleHasParam(name)">
                        <input type="text" :value="local[name]"  @input="update(name, $event.target.value)" class="form-control form-control-sm" />
                        </div>
                        <div v-else>{{local[name]}}</div>
                    </td>
                    <td scope="row">        
                        <button type="button"  class="btn btn-sm btn-danger grid-button-delete float-right"  v-on:click="remove(name)">
                        <i aria-hidden="true" class="v-icon notranslate mdi mdi-delete" style="font-size:16px;"></i>
                        </button>
                    </td>
                </tr>
            </table>

            </div>  `    
});