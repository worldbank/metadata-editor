//vue validation-rules component
Vue.component('validation-rules-component', {
    props:['value'],
    data: function () {    
        return {
            rule_selected:'',
            validation_rules:{
                "required":{
                    "rule":"required",
                    "label":"Required",
                    "description":"Must have a value. Use the Required checkbox on the field instead of adding this rule.",
                    "param":false,
                    "addable":false
                },
                "regex":{
                    "rule":"regex",
                    "label":"Regular expression",
                    "description":"Value must match the pattern. Enter the pattern only, without slashes or flags. Use ^ and $ to match the whole value.",
                    "param":true,
                    "value_type":"regex",
                    "placeholder":"^[A-Z0-9_-]+$",
                    "hint":"Example: ^[A-Z0-9_-]+$  — do not wrap in /slashes/."
                },
                "min":{
                    "rule":"min",
                    "label":"Minimum length",
                    "description":"Minimum number of characters",
                    "param":true,
                    "value_type":"integer",
                    "placeholder":"5"
                },
                "max":{
                    "rule":"max",
                    "label":"Maximum length",
                    "description":"Maximum number of characters",
                    "param":true,
                    "value_type":"integer",
                    "placeholder":"80"
                },
                "alpha":{
                    "rule":"alpha",
                    "label":"Letters only",
                    "description":"Allow only alphabetic characters",
                    "param":false
                },
                "alpha_num":{
                    "rule":"alpha_num",
                    "label":"Letters and numbers",
                    "description":"Allow only alphabetic characters and numbers",
                    "param":false
                },
                "numeric":{
                    "rule":"numeric",
                    "label":"Numeric",
                    "description":"Allow only numeric values",
                    "param":false
                },
                "is_uri":{
                    "rule":"is_uri",
                    "label":"URL",
                    "description":"Must be a valid URL",
                    "param":false
                },
                "iso_date":{
                    "rule":"iso_date",
                    "label":"Date (YYYY-MM-DD)",
                    "description":"Must be a complete calendar date, for example 2024-03-15",
                    "param":false
                },
                "iso_date_partial":{
                    "rule":"iso_date_partial",
                    "label":"Date (YYYY, YYYY-MM, or YYYY-MM-DD)",
                    "description":"Must be a year, year-month, or full date, for example 2024, 2024-03, or 2024-03-15",
                    "param":false
                }
            }
        }
    },
    created: function () {           
    },
    computed: {
        local:{
            get(){                
                if (this.isValidFormat(this.value)){
                    return this.value;
                }
                return {};
            },
            set(val){
                this.$emit('update:value', val);
            }
        },
        ValidationRules()
        {
            return this.validation_rules;
        },
        availableRuleItems()
        {
            let catalog = this.validation_rules;
            let used = Object.keys(this.local);
            return Object.keys(catalog).filter(function (key) {
                if (catalog[key].addable === false) {
                    return false;
                }
                if (used.indexOf(key) !== -1) {
                    return false;
                }
                return true;
            }).map(function (key) {
                return {
                    value: key,
                    text: catalog[key].label || catalog[key].rule || key
                };
            });
        }
    },
    methods:{
        isValidFormat: function(value)
        {            
            if (typeof value=='string' || Array.isArray(value) || !value)
            {
                return false;
            }
            return true;
        },
        update(key, value) {
            this.$emit('input', { ...this.value, [key]: value })
        },
        looksLikeWrappedRegex: function(value)
        {
            return /^\/.+\/[a-z]*$/i.test(String(value).trim());
        },
        isRuleParamValid: function(name, value)
        {
            let rule = this.validation_rules[name];
            if (!rule || !rule.param) {
                return true;
            }
            if (value === '' || value === null || value === undefined) {
                return true;
            }
            if (rule.value_type === 'regex') {
                if (this.looksLikeWrappedRegex(value)) {
                    return false;
                }
                try {
                    new RegExp(value);
                    return true;
                } catch (e) {
                    return false;
                }
            }
            if (rule.value_type === 'integer') {
                return /^\d+$/.test(String(value));
            }
            return true;
        },
        ruleParamError: function(name, value)
        {
            if (this.isRuleParamValid(name, value)) {
                return [];
            }
            let rule = this.validation_rules[name];
            if (rule && rule.value_type === 'regex') {
                if (this.looksLikeWrappedRegex(value)) {
                    return ['Enter the pattern without wrapping slashes or flags'];
                }
                return ['This is not a valid regular expression'];
            }
            if (rule && rule.value_type === 'integer') {
                return ['Enter a whole number of 0 or more'];
            }
            return ['Invalid value'];
        },
        ruleHasParam: function(rule){
            if (this.validation_rules[rule] && this.validation_rules[rule].param){
                return this.validation_rules[rule].param==true;
            }

            return false;
        },
        RuleLabel: function(rule){
            if (this.validation_rules[rule] && this.validation_rules[rule].label){
                return this.validation_rules[rule].label;
            }
            return rule;
        },
        RuleDescription: function(rule){
            if (this.validation_rules[rule] && this.validation_rules[rule].description){
                return this.validation_rules[rule].description;
            }

            return '';
        },
        RulePlaceholder: function(rule){
            if (this.validation_rules[rule] && this.validation_rules[rule].placeholder){
                return this.validation_rules[rule].placeholder;
            }
            return '';
        },
        RuleHint: function(rule){
            if (this.validation_rules[rule] && this.validation_rules[rule].hint){
                return this.validation_rules[rule].hint;
            }
            return '';
        },
        remove: function (rule_name){
            Vue.delete(this.local, rule_name);
        },
        addRule: function ()
        {
            if (!this.rule_selected || !this.validation_rules[this.rule_selected]) {
                return;
            }
            if (this.validation_rules[this.rule_selected].addable === false) {
                this.rule_selected='';
                return;
            }
            rule_info=this.validation_rules[this.rule_selected];
            Vue.set(this.local, this.rule_selected, rule_info.param==true ? '' : true);
            this.rule_selected='';
            this.$emit('update:value', this.local);
        }
        
    },
    template: `
            <div class="validation-rules-component">

            <v-row class="p-2 mb-3" justify="end">
                <v-col cols="12" md="6">
                    <v-row align="center">
                        <v-col cols="auto" class="flex-grow-1">
                            <v-select
                                v-model="rule_selected"
                                :items="availableRuleItems"
                                item-text="text"
                                item-value="value"
                                placeholder="Select rule"
                                outlined
                                dense
                                hide-details
                            ></v-select>
                        </v-col>
                        <v-col cols="auto">
                            <v-btn color="primary" @click="addRule" :disabled="rule_selected==''" small>Add</v-btn>
                        </v-col>
                    </v-row>
                </v-col>
            </v-row>

            <v-simple-table>
                <thead>
                <tr>
                    <th>Rule</th>
                    <th>Value</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(value_, name, index) in local" :key="name">
                    <td>
                        <div class="text-primary">{{RuleLabel(name)}}</div>
                        <div class="text-secondary" style="font-size:small;margin-top:5px;">{{RuleDescription(name)}}</div>
                    </td>
                    <td>
                        <div v-if="ruleHasParam(name)">
                            <v-text-field
                                :value="local[name]"
                                @input="update(name, $event)"
                                :placeholder="RulePlaceholder(name)"
                                :hint="RuleHint(name)"
                                :persistent-hint="!!RuleHint(name)"
                                :error="!isRuleParamValid(name, local[name])"
                                :error-messages="ruleParamError(name, local[name])"
                                outlined
                                dense
                                class="mt-2"
                            ></v-text-field>
                        </div>
                        <div v-else>{{local[name]}}</div>
                    </td>
                    <td>        
                        <v-btn icon small color="error" @click="remove(name)" class="float-right">
                            <v-icon>mdi-delete</v-icon>
                        </v-btn>
                    </td>
                </tr>
                </tbody>
            </v-simple-table>

            </div>  `    
});
