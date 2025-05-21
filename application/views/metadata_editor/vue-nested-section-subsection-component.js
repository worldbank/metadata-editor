///// nested-section-subsection
Vue.component('nested-section-subsection', {
    props:['value','columns','path','title','parentElement'],
    data: function () {    
        return {
            local_data:{}
        }
    },
    watch: { 
    },
    mounted: function () {     
        console.log("local value beforex",JSON.stringify(this.parentElement));
        let value= this.parentElement ? this.parentElement : {};

        this.local_data= value;
    },
    computed: {
        /*local(){
            //return this.value;
            console.log("local value before",JSON.stringify(this.value));
            let value= this.value ? this.value : {};

            return value;

            if (value.length<1){
                value= [{}];
            }
            console.log("local value after",JSON.stringify(value));
            //console.log("local value",JSON.stringify(value));
            return value;
        },*/
        localColumns(){
            return this.columns;
        },
        formData () {
            return this.$deepModel('formData')
        }
    },
    methods:{
        countRows: function(){
            return this.field_data.length;
        },
        addRow: function (){    
            this.field_data.push({});
            this.$emit('adding-row', this.field_data);
        },
        remove: function (index){
            this.field_data.splice(index,1);
        },
        localValue: function(key)
        {
            console.log("searching for local value path",key,this.local_data);
            return _.get(this.local_data,key);
        },
        parentValue: function(key){
            console.log("searching for parent value path",key,this.parentElement);
            return _.get(this.parentElement,key);
        },
        update: function (key, value)
        {
            console.log("updating sub-section value",key,value);
            
            //this.local[key] = value;
            _.set(this.local_data,key,value);
            this.$emit('input', JSON.parse(JSON.stringify(this.local_data)));
            console.log("emitting from nested-section",JSON.stringify(this.local_data));
        },
        toggleChildren(index) {
            if (!this.active_sections.includes(index)) {
                this.active_sections.push(index);
            }else{
                this.active_sections = this.active_sections.filter(function(e) { return e !== index })
            }
        },
        showChildren(index)
        {
            if (this.active_sections.includes(index)) {
                return true;
            }
            return false;
        },
        toggleClasses(index) {
            return {
                'fa-angle-down': !this.showChildren(index),
                'fa-angle-up': this.showChildren(index)
            }
        },
        fieldDisplayType(field)
        {
            /*if (field.type=='simple_array'){
                return 'simple_array';
            }*/

            if (field.display_type){
                return field.display_type;
            }

            if (_.includes(['text','string','integer','boolean','number'],field.display_type)){
                return 'text';
            }            
            
            return field.type;
        }
    },
    template: `
            <div class="nested-section-subsection mt-3" >

                    <template>
                        <v-expansion-panels :value="0">
                            <v-expansion-panel>
                            <v-expansion-panel-header>
                                <span><v-icon>mdi-folder-text-outline</v-icon> {{title}}</span>
                            </v-expansion-panel-header>
                            <v-expansion-panel-content>
                                <div v-for="(column,idx_col) in localColumns" scope="row" >
                                    <div v-if="column.type=='section'">
                                        <nested-section-subsection
                                            :value="localValue(column.key)"
                                            :columns="column.props"
                                            :path="column.key"
                                            :title="column.title"
                                            :parentElement="parentElement"
                                        >
                                        </nested-section-subsection>
                                    </div>


                                    <div v-if="fieldDisplayType(column)=='text' || 
                                            fieldDisplayType(column)=='textarea' || 
                                            fieldDisplayType(column)=='dropdown' ||
                                            fieldDisplayType(column)=='dropdown-custom' ||
                                            fieldDisplayType(column)=='simple_array'
                                            "
                                    >
                                            <form-input
                                                :value=" localValue(column.key)"
                                                :field="column"
                                                @input="update(column.key, $event)"
                                            ></form-input>
                                            
                                    </div>

                                    <div v-if="fieldDisplayType(column)=='array'">                                           
                                            
                                                <div class="form-group form-field form-field-table">
                                                    <label :for="'field-' + path">{{column.title}}</label>
                                                    <span class="small" v-if="column.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(column.key)" ><i class="far fa-question-circle"></i></span>
                                                    <small :id="'field-toggle-' + normalizeClassID(column.key)" class="collapse help-text form-text text-muted">{{column.help_text}}</small>
                                                    

                                                    <table-grid-component 
                                                        :value=" localValue(column.key)"
                                                        @input="update(column.key, $event)"
                                                        :columns="column.props"
                                                        :enums="column.enum" 
                                                        class="border elevation-1"
                                                        >
                                                    </table-grid-component>
                                                </div>
                                            
                                    </div>

                                    <div v-if="fieldDisplayType(column)=='nested_array'">                                           
                                            
                                                <div class="form-group form-field form-field-table">
                                                    <label :for="'field-' + path">{{column.title}}</label>
                                                    <span class="small" v-if="column.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(column.key)" ><i class="far fa-question-circle"></i></span>
                                                    <small :id="'field-toggle-' + normalizeClassID(column.key)" class="collapse help-text form-text text-muted">{{column.help_text}}</small>
                                                    

                                                    <nested-array
                                                        :key="column.key" 
                                                        :value="localValue(column.key)"
                                                        @input="update(column.key, $event)"
                                                        :columns="column.props"                                                        
                                                        :path="column.key">
                                                    </nested-array> 
                                                </div>
                                            
                                    </div>

                                    
                                        
                                </div>  
                            </v-expansion-panel-content>
                            </v-expansion-panel>
                        </v-expansion-panels>
                    </template>

            </div>  `
})