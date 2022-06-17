//vue table component
Vue.component('table-component', {
    props:['value','columns','path', 'field'],
    data: function () {    
        return {
            field_data: this.value
        }
    },
    watch: { 
        field_data: function(newVal, oldVal) {
            console.log('Prop changed: ', newVal, ' | was: ', oldVal)
            //console.log(this.key_path);                    
            //this.$vueSet (this.$store.state.formData, this.key_path, newVal);
        }
    
    },
    
    mounted: function () {
        //set data to array if empty or not set
        if (!this.field_data){
            this.field_data=[];
            this.field_data.push({});
        }
    },
    computed: {
        localColumns(){
            return this.columns;
        }
    },  
    template: `
            <div class="table-component">
            <table class="table table-striped table-sm">
                <thead class="thead-light">
                <tr>
                    <th v-for="(column,idx_col) in columns" scope="col">
                        {{column.title}}
                        <span v-if="column.rules" class="required-label"> * </span>
                    </th>
                    <th scope="col">               
                    </th>
                </tr>
                </thead>

                <!--start-v-for-->
                <tbody>
                <tr  v-for="(item,index) in field_data">
                    <td v-for="(column,idx_col) in localColumns" scope="row">
                        <div>
                            <div v-if="column.type!=='table'">
                            <input type="text"
                                v-model="field_data[index][column.key]"
                                class="form-control form-control-sm"                                 
                            >
                            </div>
                            <!--<div v-if="column.type=='table'">
                                <table-component 
                                    v-model="field_data[index][column.key]" 
                                    :columns="column.props"/>
                            </div>-->

                        </div>
                    </td>
                    <td scope="row">        
                        <button type="button"  class="btn btn-sm btn-danger grid-button-delete float-right" v-on:click="remove(index)"><i class="fas fa-trash-alt"></i></button>
                    </td>
                </tr>
                <!--end-v-for -->
                </tbody>
            </table>

            <div class="d-flex justify-content-center">
                <button type="button" class="btn btn-link btn-block btn-sm" @click="addRow" ><i class="fas fa-plus-square"></i> Add row</button>    
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
        columnName: function(column,path)
        {
            if (typeof column.name ==='undefined'){
                return column.title;
            }else{
                return column.name
            }
        }
    }
})