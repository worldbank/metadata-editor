//vue grid component
Vue.component('grid-preview-component', {
    props:['value','columns','path', 'field'],
    data: function () {    
        return {
            field_data: this.value,
            key_path: this.path,
            store: this.$store
        }
    },
    methods:{
        loadData: function() {
            return "x";
        },
        isEmpty: function()
        {
            is_empty=true;

            this.field_data.forEach(row => {
                Object.values(row).forEach(val=>{
                    if (val.length>0){
                        is_empty=false;
                    }
                });
            });

            return is_empty;
        }
    },     
    mounted: function () {
        
    },
    computed: {
        localColumns(){
            return this.columns;
        }
    },  
    template: `
            <!--vuejs template for grid -->
            <div class="grid-component-preview" v-if="!isEmpty()">
            <label class="table-title">{{field.title}}</label>
            <table class="table table-striped table-sm" >
                <thead class="thead-light">
                <tr>
                    <th v-for="(column,idx_col) in columns" scope="col">
                        {{column.title}}
                        <span v-if="column.rules" class="required-label"> * </span>
                    </th>
                </tr>
                </thead>

                <!--start-v-for-->
                <tbody>
                <tr  v-for="(item,index) in field_data">
                    <td v-for="(column,idx_col) in localColumns" scope="row">
                        <div>{{field_data[index][column.key]}}</div>
                    </td>                    
                </tr>
                <!--end-v-for -->
                </tbody>
            </table>

            </div>  `,
})