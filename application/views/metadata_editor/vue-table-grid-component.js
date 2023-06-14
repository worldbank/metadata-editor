//vue table-grid component
Vue.component('table-grid-component', {
    props:['value','columns', 'field'],
    data: function () {    
        return {
            sort_field:'',
            sort_asc:true,
            undo_paste:'',
            snackbar:false,
            snackbar_message:''
        }
    },
    watch: {
    },
    
    mounted: function () {   
        
    },
    computed: {
        local(){
            let value= this.value ? this.value : [{}];

            if (value.length<1){
                value= [{}];
            }

            if (!Array.isArray(value)){
                value=[{}];
            }
        
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
        showToast: function(message){
            this.snackbar=true;
            this.snackbar_message=message;
        },
        update: function (index, key, value)
        {
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
            this.showToast("Copied to clipboard");
        },
        pasteTsv: function(pasteMode='replace')
        {
            let vm=this;
            let tsv='';
            this.pasteFromClipBoard().then((result) => {
                tsv=result;
                let json=this.tsvToArray(tsv);

                if (json===false){
                    return;
                }

                //save undo
                vm.undo_paste=JSON.parse(JSON.stringify(vm.local));
                
                if (pasteMode=='append'){
                    vm.$emit('input', JSON.parse(JSON.stringify(vm.local.concat(json))));
                }else{
                    vm.$emit('input', JSON.parse(JSON.stringify(json)));
                }
            });
        },
        undoPaste: function(){
            if (this.undo_paste){
                this.$emit('input', JSON.parse(JSON.stringify(this.undo_paste)));
            }  
            this.undo_paste='';          
        },

        jsonToTsv: function(json){
            let csv='';
            //let keys=Object.keys(json[0]);
            let keys=this.columnKeys;
            
            //include header
            //csv+=keys.join('\t') + "\n";
            
            for (let i=0;i<json.length;i++){
              let row=[];
              console.log("csv row",i);
              for (let j=0;j<keys.length;j++){
                let cell=json[i][keys[j]];
                if (cell){
                    row.push('\"'+cell+'\"');
                }else{
                    row.push('');
                }
              }
              csv+=row.join('\t') + "\n";
            }
            return csv;
          },          
        tsvToArray: function(tsv){

            let rows=this.CSVToArray( tsv, strDelimiter= "\t" );

            if (rows.length<1){
                alert("Invalid data format. No rows found");
                return false;
            }

            let keys=this.columnKeys;
            let colsCount=rows[0].length;

            if (colsCount>keys.length){
                alert("Invalid data format. Too many columns");
                return false;
            }
            
            let json=[];
            for (let i=0;i<rows.length;i++){
              let row=rows[i];
              let obj={};
              for (let j=0;j<colsCount;j++){
                    let cell=row[j];
                    if (cell){
                        cell=cell.trim();
                    }
                    obj[keys[j]]=cell;
              }
                if (!this.isRowEmpty(obj)){
                    json.push(obj);
                }
            }
            return json;
        },
        isRowEmpty: function(row){
            let keys=Object.keys(row);
            for (let i=0;i<keys.length;i++){
                if (row[keys[i]]){
                    return false;
                }
            }
            return true;
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
                                <v-list-item-title>{{$t("copy")}}</v-list-item-title>
                            </v-list-item-content>
                        </v-list-item>
                        <v-list-item @click="pasteTsv('replace')">
                            <v-list-item-icon>
                                <v-icon>mdi-content-paste</v-icon>
                            </v-list-item-icon>
                            <v-list-item-content>
                                <v-list-item-title>{{$t("paste_replace")}}</v-list-item-title>
                            </v-list-item-content>                            
                        </v-list-item>
                        <v-list-item @click="pasteTsv('append')">
                            <v-list-item-icon>
                                <v-icon>mdi-file-replace</v-icon>
                            </v-list-item-icon>
                            <v-list-item-content>
                                <v-list-item-title>{{$t("paste_append")}}</v-list-item-title>
                            </v-list-item-content> 
                        </v-list-item>

                        <v-list-item @click="undoPaste()" :disabled="!undo_paste">
                            <v-list-item-icon>
                                <v-icon>mdi-arrow-u-left-top</v-icon>
                            </v-list-item-icon>
                            <v-list-item-content>
                                <v-list-item-title>{{$t("undo_paste")}}</v-list-item-title>
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
                <tbody is="draggable" :list="local" tag="tbody" handle=".handle">
                <tr  v-for="(item,index) in local">
                    <td><span class="move-row handle" ><i class="fas fa-grip-vertical"></i></span></td>
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
                <button type="button" class="btn btn-default btn-block btn-sm border m-2" @click="addRow" ><i class="fas fa-plus-square"></i> {{$t("add_row")}}</button>
            </div>

            <v-snackbar
            
            right
            bottom
                v-model="snackbar"
                >
                {{ snackbar_message }}

                <div class="float-right">
                    <v-icon @click="snackbar = false">mdi-close-circle-outline</v-icon>                    
                </div>
            </v-snackbar>

            </div>  `    
})