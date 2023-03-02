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
            //console.log("bofore local value",JSON.stringify(this.value));
            let value= this.value ? this.value : [{}];

            if (value.length<1){
                value= [{}];
            }

            if (!Array.isArray(value)){
                value=[{}];
            }
        
            //console.log("local value",JSON.stringify(value));
            return value;
        },
        localColumns(){
            return this.columns;
        },
        columnKeys(){
            let keys=[];
            for (let i=0;i<this.columns.length;i++){
                keys.push(this.columns[i]['key']);
            }
            return keys;
        }
    },
    methods:{
        update: function (index, key, value)
        {
            //console.log("updating value",index,key,value);
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
        copyTsv: function()
        {
            this.copyToClipBoard(this.jsonToTsv(this.local));
            alert("Copied to clipboard");
        },
        pasteTsv: function(pasteMode='replace')
        {
            let vm=this;
            let tsv='';
            this.pasteFromClipBoard().then((result) => {
                tsv=result;
                let json=this.tsvToArray(tsv);
                
                if (pasteMode=='append'){
                    vm.$emit('input', JSON.parse(JSON.stringify(vm.local.concat(json))));
                }else{
                    vm.$emit('input', JSON.parse(JSON.stringify(json)));
                }
            });
        },

        jsonToTsv: function(json){
            let csv='';
            let keys=Object.keys(json[0]);
            
            //include header
            //csv+=keys.join('\t') + "\n";
            
            for (let i=0;i<json.length;i++){
              let row=[];
              console.log("csv row",i);
              for (let j=0;j<keys.length;j++){
                row.push(json[i][keys[j]]);
              }
              csv+=row.join('\t') + "\n";
            }
            console.log("csv output",csv);
            return csv;
          },          
        tsvToArray: function(tsv){
            let lines=tsv.split("\n");  
            //let keys=lines[0].split("\t");    
            //let keys=lines[0].split("\t").map((x,i)=>{return "col"+i;});
            let keys=this.columnKeys;
            let colsCount=lines[0].split("\t").length;

            if (colsCount>keys.length){
                alert("Invalid data format. Too many columns");
                return false;
            }
            
            let json=[];
            for (let i=0;i<lines.length;i++){
              let row=lines[i].split("\t");
              let obj={};
              for (let j=0;j<colsCount;j++){
                    obj[keys[j]]=row[j];//.trim();
              }
              json.push(obj);
            }
            return json;
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
        },
        fieldDisplayType(field)
        {
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
            <div class="table-grid-component">

            <table class="table table-striped table-sm border-bottom">
                <thead class="thead-light">
                <tr>
                    <th>
                    <!--options -->
                    <v-menu bottom left>
                        <template v-slot:activator="{ on, attrs }">
                        <v-btn
                            light
                            icon
                            x-small
                            v-bind="attrs"
                            v-on="on"
                        >
                            <v-icon>mdi-dots-vertical</v-icon>
                        </v-btn>
                        </template>

                        <v-card dense>
                        <v-list dense>
                        <v-list-item @click="copyTsv" dense>
                            <v-list-item-icon>
                                <v-icon>mdi-content-copy</v-icon>
                            </v-list-item-icon>
                            <v-list-item-content>
                                <v-list-item-title>Copy</v-list-item-title>
                            </v-list-item-content>
                        </v-list-item>
                        <v-list-item @click="pasteTsv('replace')">
                            <v-list-item-icon>
                                <v-icon>mdi-content-paste</v-icon>
                            </v-list-item-icon>
                            <v-list-item-content>
                                <v-list-item-title>Paste (Replace)</v-list-item-title>
                            </v-list-item-content>                            
                        </v-list-item>
                        <v-list-item @click="pasteTsv('append')">
                            <v-list-item-icon>
                                <v-icon>mdi-file-replace</v-icon>
                            </v-list-item-icon>
                            <v-list-item-content>
                                <v-list-item-title>Paste (Append)</v-list-item-title>
                            </v-list-item-content> 
                        </v-list-item>

                        </v-list>
                        </v-card>
                    </v-menu>
                    <!-- end points -->
                    </th>
                    <th v-for="(column,idx_col) in columns" scope="col">
                        <span @click="sortColumn(column.key)" role="button" title="Click to sort">
                            {{column.title}} 
                            <i v-if="sort_field==column.key && !sort_asc" class="fas fa-caret-down"></i>
                            <i v-if="sort_field==column.key && sort_asc==true" class="fas fa-caret-up"></i>
                        </span>
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
                        <div v-if="fieldDisplayType(column)=='textarea'" >
                            <textarea class="form-control form-control-sm"
                                :value="local[index][column.key]"
                                @input="update(index,column.key, $event.target.value)"
                            >
                            </textarea>
                        </div>
                        <div v-else-if="fieldDisplayType(column)=='dropdown-custom' || fieldDisplayType(column)=='dropdown'">
                                <v-combobox
                                    :value="local[index][column.key]"
                                    @input="update(index,column.key, $event)"
                                    :items="column.enum"
                                    label=""                
                                    outlined
                                    dense
                                    clearable
                                    background-color="#FFFFFF"
                                    item-text="label"
                                    item-value="code"
                                    :return-object="false"
                                    class="form-field-dropdown-custom"
                                ></v-combobox>
                        </div>
                        <div v-else>
                            <input type="text"
                                :value="local[index][column.key]"
                                @input="update(index,column.key, $event.target.value)"
                                class="form-control form-control-sm"
                            >
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