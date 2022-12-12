//vue validation-rules component
Vue.component('validation-rules-component', {
    props:['value','path', 'field'],
    data: function () {    
        return {
            //field_data: this.value,
            sort_field:'',
            sort_asc:true,
            columns:{
                "rule":{
                    "key":"rule",
                    "title":"Rule"
                },
                "value":{
                    "key":"value",
                    "title":"Value"
                }
            }
            ,
            validation_rules:{
                "regex_match":{
                    "rule":"regex_match",
                    "description":"Regular expression - ",
                    "param":true,
                    "value_type":"regex"
                },
                "min_length":{
                    "rule":"min_length",
                    "description":"Minimum length of text",
                    "param":true,
                    "value_type":"integer"
                },
                "max_length":{
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
                "alpha_numeric":{
                    "rule":"alpha_numeric",
                    "description":"Allow only alphabets and numbers",
                    "param":false
                }
            }
        }
    },
    
    mounted: function () {
        //set data to array if empty or not set
        /*if (!this.field_data){
            this.field_data=[{}];
            //this.field_data.push({});
        }*/
    },
    computed: {
        localColumns(){
            return this.columns;
        },
        field_data:
        {
            get(){
                return this.value;
            },
            set(val){
                this.$emit('update:value', val);
            }
        },
        ValidationRules()
        {
            return this.validation_rules;
            /*const filtered = Object.keys(this.validation_rules)
                .filter(key => !this.isRuleInUse(key))
                .reduce((obj, key) => {
                    obj[key] = this.validation_rules[key];
                    return obj;
                }, {});

            

            console.log("valdiationRUles",filtered,this.validation_rules);

            return filtered;
            return this.validation_rules;
            */

            let filtered_={};
            let keys_=Object.keys(this.validation_rules);
            for(i=0;i<keys_.length;i++)
            {
                if (!this.isRuleInUse(keys_[i])){
                 filtered_[keys_[i]]=this.validation_rules[keys_[i]];
                }
            }
            console.log("filtered",filtered_);
            return filtered_;

        }          
    },
    methods:{
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
            for(i=0;i<this.field_data.length;i++)
            {
                if (this.field_data[i]["rule"]==rule_key){
                    return true;
                }
            }
            return false;
        },
        ruleHasParam: function(rule){
            console.log("rule name=",rule);

            if (this.validation_rules[rule] && this.validation_rules[rule].param){
                return this.validation_rules[rule].param==true;
            }

            return false;
            
        },
        countRows: function(){
            return this.field_data.length;
        },
        addRow: function (){
            window._data=this.field_data;
            console.log("adding row");
            if (!this.field_data){
                console.log("addRow !field_data")
                this.field_data=[{}];
            }else{                
                this.field_data.push({});
                console.log("addRow Elsefield_data")
                this.$emit('adding-row', this.field_data);
            }
            console.log("adding row", this.field_data);
        },
        remove: function (index){
            this.field_data.splice(index,1);
        },
        columnName: function(column,path)
        {
            if (typeof column.name ==='undefined'){
                return column.title;
            }else{
                return column.name
            }
        },
        sortColumn: function(column_key)
        {
            if (this.sort_field==column_key){
                this.sort_asc=!this.sort_asc;
            }

            this.sort_field = column_key;

            if (this.sort_asc==true){
                this.field_data.sort(function (a, b) {
                    return ('' + a[column_key]).localeCompare(b[column_key], undefined, {
                        numeric: true,
                        sensitivity: 'base'
                      });
                });                
            }
            else{
                this.field_data.sort(function(a, b){
                    return ('' + b[column_key]).localeCompare(a[column_key], undefined, {
                        numeric: true,
                        sensitivity: 'base'
                      });
                });                
            }
        }
    },
    template: `
            <div class="validation-rules-component">

            <table class="table table-striped table-sm">
                <thead class="thead-light">
                <tr>
                    <th></th>
                    <th v-for="(column,idx_col) in columns" scope="col">
                        <span @click="sortColumn(column.key)" role="button" title="Click to sort">
                            {{column.title}} 
                            <i v-if="sort_field==column.key && !sort_asc" class="fas fa-caret-down"></i>
                            <i v-if="sort_field==column.key && sort_asc==true" class="fas fa-caret-up"></i>
                        </span>
                        <span v-if="column.rules" class="required-label"> * </span>
                    </th>
                    <th scope="col">               
                    </th>
                </tr>
                </thead>

                <!--start-v-for-->
                <tbody is="draggable" :list="field_data" tag="tbody">
                <tr  v-for="(item,index) in field_data">
                    <td><span class="move-row" title="Drag to move">
                        <i aria-hidden="true" class="v-icon notranslate mdi mdi-drag"></i>
                    </span></td>
                    <!-- 
                    <td v-for="(column,idx_col) in localColumns" scope="row">
                        <div>
                            <div v-if="column.type!=='table'">
                            <input type="text"
                                v-model="field_data[index][column.key]"
                                class="form-control form-control-sm"                                 
                            >
                            </div>
                        </div>
                    </td>
                    -->
                    <td>
                        <select 
                            v-model="field_data[index]['rule']" 
                            class="form-control form-field-dropdown" 
                        >
                            <option v-for="(rule in ValidationRules">
                                {{ rule.rule }}
                            </option>
                        </select>
                        <small class="help-text form-text text-muted">{{field_data[index]['rule']}}</small>
                    </td>
                    <td>
                        <div v-if="ruleHasParam(field_data[index]['rule'])">
                        <input type="text"
                            v-model="field_data[index]['value']"
                            class="form-control form-control-sm"                            
                        >
                        </div>
                        <!--<div>{{validateRuleValue(index)}}</div>-->
                    </td>
                    <td scope="row">        
                        <button type="button"  class="btn btn-sm btn-danger grid-button-delete float-right"  v-on:click="remove(index)">
                        <i aria-hidden="true" class="v-icon notranslate mdi mdi-delete" style="font-size:18px;"></i>
                        </button>
                    </td>
                </tr>
                <!--end-v-for -->
                </tbody>
            </table>

            <div class="d-flex justify-content-center">
                <button type="button" class="btn btn-default btn-block btn-sm border m-2" @click="addRow" ><i class="fas fa-plus-square"></i> Add row</button>    
            </div>

            </div>  `    
});