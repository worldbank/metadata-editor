//vue table-grid component
Vue.component('table-grid-component', {
    props:['value','columns', 'field'],
    data: function () {    
        return {
            sort_field:'',
            sort_asc:true
        }
    },
    watch: {
    },
    
    mounted: function () {        
    },
    computed: {
        local(){
            console.log("bofore local value",JSON.stringify(this.value));
            let value= this.value ? this.value : [{}];

            if (value.length<1){
                value= [{}];
            }
        
            console.log("local value",JSON.stringify(value));
            return value;
        },
        localColumns(){
            return this.columns;
        }
    },
    methods:{
        update: function (index, key, value)
        {
            console.log("updating value",index,key,value);
            if (Array.isArray(this.local[index])){
                this.local[index] = {};
            }

            this.local[index][key] = value;
            this.$emit('input', JSON.parse(JSON.stringify(this.local)));
        },
        countRows: function(){
            return this.local.length;
        },
        addRow: function (){    
            this.local.push({});
            this.$emit('input', JSON.parse(JSON.stringify(this.local)));
        },
        remove: function (index){
            this.local.splice(index,1);
            this.$emit('input', JSON.parse(JSON.stringify(this.local)));
            console.log("after removed", this.local);
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
                this.local.sort(function (a, b) {
                    return ('' + a[column_key]).localeCompare(b[column_key], undefined, {
                        numeric: true,
                        sensitivity: 'base'
                      });
                });                
            }
            else{
                this.local.sort(function(a, b){
                    return ('' + b[column_key]).localeCompare(a[column_key], undefined, {
                        numeric: true,
                        sensitivity: 'base'
                      });
                });                
            }
        }
    },  
    template: `
            <div class="table-grid-component">
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
                <tbody is="draggable" :list="local" tag="tbody">
                <tr  v-for="(item,index) in local">
                    <td><span class="move-row" ><i class="fas fa-grip-vertical"></i></span></td>
                    <td v-for="(column,idx_col) in localColumns" scope="row">
                        <div>
                            <div v-if="column.type!=='table'">
                            <input type="text"
                                :value="local[index][column.key]"
                                @input="update(index,column.key, $event.target.value)"
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