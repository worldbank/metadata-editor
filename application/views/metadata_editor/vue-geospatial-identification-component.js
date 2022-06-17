//
Vue.component('identification-section', {
    props:['value','columns','path'],
    data: function () {    
        return {
            field_data: this.value,
            key_path: this.path
        }
    },
    watch: { 
        field_data: function(newVal, oldVal) {
            console.log('=========Prop changed: ', newVal, ' | was: ', oldVal)
            console.log('=========key path:',this.key_path);
            this.$vueSet (this.$store.state.formData, this.key_path, newVal);
        }
    },
    mounted: function () {
        //set data to array if empty or not set
        if (!this.field_data){
            this.field_data=[{}];
            
        }
    },

    computed: {
        localColumns(){
            return this.columns;
        }
    },  
    template: `
            <div class="nested-section-identification">                                            
                <template  v-for="(item,index) in field_data">
                    <div v-for="(column,idx_col) in localColumns" scope="row">
                        
                            <div  v-if="column.type!=='array'">

                                <div class="form-group form-field" :class="['field-' + column.key] ">
                                    <label :for="'field-' + normalizeClassID(path + '-' + column.key)">                                        
                                        <span class="small" v-if="column.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(path + ' ' + column.key)" ><i class="far fa-question-circle"></i></span>
                                        <span v-if="column.required==true" class="required-label"> * </span>
                                    </label>
                                    <input type="text"
                                        :value="getData(index+'.'+column.key)"
                                        @input="setData(index+'.'+column.key, $event.target.value)"
                                        class="form-control" 
                                        :id="'field-' + normalizeClassID(path + '-' + column.key)"                                     
                                    >                                      
                                    <small :id="'field-toggle-' + normalizeClassID(path + '-' + column.key)" class="collapse help-text form-text text-muted">{{column.help_text}}</small>                            
                                </div>
                                
                            </div>

                            <div v-if="column.type=='array'">
                                <div class="form-group form-field form-field-table">
                                    <label :for="'field-' + path">{{column.title}}</label>                                      
                                    <grid-component 
                                        :value="field_data[index][column.key]"   
                                        :columns="column.props"
                                        :path="path + '['+index+']'+ column.key"
                                        >
                                    </grid-component>  
                                </div>
                            </div>
                        
                    </div>    
                    <div>        
                        <button type="button"  class="btn btn-sm btn-danger float-right" v-on:click="remove(index)">remove <i class="fa fa-trash-o" aria-hidden="true"></i></button>
                    </div>
                </template>

            <div class="d-flex justify-content-center">
                <button type="button" class="btn btn-light btn-block btn-sm" @click="addRow" >Add row</button>    
            </div>

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
            //alert(data,field_xpath);
            console.log(field_xpath,event);
            _.set(this.field_data,field_xpath,event);
            console.log(this.field_data);
            Vue.set(this.field_data, 0, this.field_data[0]);

        }    
    }
})