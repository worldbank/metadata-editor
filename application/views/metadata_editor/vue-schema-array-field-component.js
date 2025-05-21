//vue schema array field component
Vue.component('schema-array-field', {
    props:['value', 'field','is_readonly'],
    data: function () {    
        return {
            //field_data: this.value,
            sort_field:'',
            sort_asc:true
        }
    },
    watch: {
    },
    
    mounted: function () {
        //set data to array if empty or not set
        if (!this.field_data){            
            this.field_data=[{}];
            //this.field_data.push({});
        }
    },
    computed: {
        localColumns(){
            //iterate properties to get columns
            let columns=[];
            for (let key in this.field.items.properties){
                columns.push({
                    key: key,
                    title: this.field.items.properties[key].title,
                    type: this.field.items.properties[key].type
                });
            }
            return columns;
        },
        field_data:
        {
            get(){
                console.log("get field_data", this.value);
                return this.value;
            },
            set(val){
                console.log("set field_data", val);
                this.$emit('input', val);
            }
        },
        isReadOnly(){
            if (!this.is_readonly){
                return false;
            }

            return this.is_readonly;
        }
    },
    methods:{
        countRows: function(){
            return this.field_data.length;
        },
        addRow: function (){

            //if not array
            if (!Array.isArray(this.field_data)){
                this.field_data=[{}];
                //this.field_data.push({});
            }
            else{
                this.field_data.push({});
            }
            //this.field_data.push({});
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
            <div class="table-component schema-array-field">
            <table class="table table-striped table-sm border-bottom">
                <thead class="thead-light">
                <tr>
                    <th></th>
                    <th v-for="(column,idx_col) in localColumns" scope="col">
                        <span @click="sortColumn(column.key)" role="button" title="Click to sort">
                            <span v-if="column.title">{{column.title}}</span>
                            <span v-else>{{column.key}}</span>
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
                <tbody is="draggable" :list="field_data" tag="tbody" handle=".handle">
                <tr  v-for="(item,index) in field_data">
                    <td><span class="move-row handle" ><i class="fas fa-grip-vertical"></i></span></td>
                    <td v-for="(column,idx_col) in localColumns" scope="row">
                        <div>
                            <div v-if="column.type!=='table'">
                            <input type="text"
                                :disabled="isReadOnly"
                                v-model="field_data[index][column.key]"
                                class="form-control form-control-sm"                                 
                            >
                            </div>
                        </div>
                    </td>
                    <td scope="row">        
                        <div class="mr-1">
                        <v-icon :disabled="isReadOnly" class="v-delete-icon"  v-on:click="remove(index)">mdi-close-circle-outline</v-icon>
                        </div>
                    </td>
                </tr>
                <!--end-v-for -->
                </tbody>
            </table>

            <div class="d-flex justify-content-center"></div>
                <button type="button" :disabled="isReadOnly" class="btn btn-default btn-block btn-sm border m-2" @click="addRow" ><i class="fas fa-plus-square"></i> Add row</button>    
            </div>

            </div>  `    
});

