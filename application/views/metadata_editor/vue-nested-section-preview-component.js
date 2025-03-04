///// nested-section-preview
Vue.component('nested-section-preview', {
    props:['value','columns','path','title'],
    data: function () {    
        return {
            field_data: this.value,
            key_path: this.path,
            active_sections:[]
        }
    },
    mounted: function () {
        //set data to array if empty or not set
        /*if (!this.field_data){
            this.field_data=[{}];            
        }*/
    },
    computed: {
        localColumns(){
            return this.columns;
        }               
    },  
    template: `
            <div class="nested-section" >
                <template  v-for="(item,index) in field_data">

                <div :class="'nested-section-row nested-section-'+index" > 
                    <div class="label-wrapper" @click="toggleChildren(index)">
                        <div class="tree-node form-section nested-form-section" >[{{index+1}}] - {{ title }}                            
                            <span class="float-right section-toggle-icon"><i class="fas" :class="toggleClasses(index)"></i></span>
                        </div>
                    </div>

                    <div v-show="showChildren(index)" class="nested-section-body">
                    <div v-for="(column,idx_col) in localColumns" scope="row" >
                        
                            <div v-if="column.type=='nested_array'">
                                <label :for="'field-' + normalizeClassID(column.key)">{{column.title}}</label>
                                <nested-section-preview 
                                    :value="field_data[index][column.key]"                                         
                                    :columns="column.props"
                                    :title="column.title"
                                    :path="path + '.' + index + '.' + column.key">
                                </nested-section-preview>
                            </div>


                            <div v-if="column.type=='text' || column.type=='string' || column.type=='textarea' || column.type=='dropdown'">
                                <div v-if="getData(index+'.'+column.key)" class="form-group form-field" :class="['field-' + column.key, column.class] ">
                                    <label :for="'field-' + normalizeClassID(column.key)">
                                        {{column.title}} 
                                        <span class="small" v-if="column.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(column.key)" ><i class="far fa-question-circle"></i></span>
                                        <span v-if="column.required==true" class="required-label"> * </span>
                                    </label>

                                    <div class="text-block">{{getData(index+'.'+column.key)}} </div>
                            
                                </div>

                            </div>

                            

                            <div v-if="column.type=='array'">
                                <div class="form-group form-field form-field-table">
                                    <label :for="'field-' + path">{{column.title}}</label>                                      
                                    <grid-preview-component 
                                        :value="field_data[index][column.key]"   
                                        :columns="column.props"
                                        :path="path + '['+index+']'+ column.key"
                                        >
                                    </grid-preview-component>  
                                </div>
                            </div>
                        
                    </div>    
                    </div>
                    
                    </div>
                </template>

            </div>  `,
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
        getData: function(field_xpath){
            return _.get(this.field_data, field_xpath)
        },
        setData: function (field_xpath,event){
            console.log(field_xpath,event);
            _.set(this.field_data,field_xpath,event);
            console.log(this.field_data);
            Vue.set(this.field_data, 0, this.field_data[0]);
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
        } 
    }
})