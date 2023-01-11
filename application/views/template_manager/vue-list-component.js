//vue list component
Vue.component('list-component', {
    props:['value','columns','path', 'field'],
    data: function () {    
        return {
            //field_data: this.value,
            key_path: this.path,
            store: this.$store,
            sort_asc:true
        }
    },
    watch: { 
        /*field_data:
        {
            get(){
                return this.value;
            },
            set(val){
                this.$emit('update:value', val);
            }
        } */   
    },
    
    created: function () {
        if (!this.field_data){
            this.field_data=new Array(1);
        }
        
        if (!Array.isArray(this.field_data)){
            this.field_data=new Array(1);
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
    template: `
            <!--vuejs template for simple-array -->
            <div class="simple-array-component bg-white p-2 border" >

            <table class="table table-striped table-sm">
                <thead>
                    <th></th>
                    <th>
                        <span @click="sortColumn()" role="button" title="Click to sort">
                            Value
                            <i v-if="!sort_asc" style="font-size:18px;" class="v-icon notranslate mdi mdi-sort-alphabetical-ascending"></i>
                            <i v-if="sort_asc==true" style="font-size:18px;" class="v-icon notranslate mdi mdi-sort-alphabetical-descending"></i>
                        </span>
                </th>                    
                    <th></th>
                </thead>
                <!--start-v-for-->
                <tbody is="draggable" :list="field_data" tag="tbody">
                <tr  v-for="(item,index) in field_data">
                    <td><span class="move-row" title="Drag to move">
                        <i aria-hidden="true" class="v-icon notranslate mdi mdi-drag"></i>
                    </span></td>
                    <td scope="row">
                        <div>
                            <input type="text"
                                v-model="field_data[index]"
                                class="form-control form-control-sm"                                 
                            >                            
                        </div>
                    </td>
                    <td scope="row">        
                        <button type="button"  class="btn btn-sm btn-danger grid-button-delete float-right" v-on:click="remove(index)">
                        <i aria-hidden="true" class="v-icon notranslate mdi mdi-delete" style="font-size:18px;"></i>
                        </button>
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
            this.field_data.push(undefined);
            this.$emit('update:value', this.field_data);
        },
        remove: function (index){
            this.field_data.splice(index,1);
        },
        columnName: function(column,path)
        {
            if (typeof column.name ==='undefined'){
                return path + '.' + column.title;
            }else{
                return column.name
            }
        },
        sortColumn: function()
        {            
            this.sort_asc=!this.sort_asc;
         
            if (this.sort_asc==true){
                this.field_data.sort();
                /*this.field_data.sort(function (a, b) {
                    return ('' + a).localeCompare(b, undefined, {
                        numeric: true,
                        sensitivity: 'base'
                      });
                });*/                
            }
            else{
                this.field_data.reverse();
                /*this.field_data.sort(function(a, b){
                    return ('' + b).localeCompare(a, undefined, {
                        numeric: true,
                        sensitivity: 'base'
                      });
                });*/                
            }
        }
    }
});

