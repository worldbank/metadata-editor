//vue table component
Vue.component('table-component', {
    props:['value','columns','path', 'field'],
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
            <div class="table-component">
            <table class="table table-striped table-sm border-bottom">
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
                <tbody is="draggable" :list="field_data" tag="tbody" handle=".handle">
                <tr  v-for="(item,index) in field_data">
                    <td><span class="move-row handle" ><i class="fas fa-grip-vertical"></i></span></td>
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
                    <td scope="row">        
                        <div class="mr-1">
                        <v-icon class="v-delete-icon"  v-on:click="remove(index)">mdi-close-circle-outline</v-icon>
                        </div>
                    </td>
                </tr>
                <!--end-v-for -->
                </tbody>
            </table>

            <div class="d-flex justify-content-center">
                <button type="button" class="btn btn-default btn-block btn-sm border m-2" @click="addRow" ><i class="fas fa-plus-square"></i> Add row</button>    
            </div>

            </div>  `    
})