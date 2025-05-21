//vue table-grid component
Vue.component('table-grid-component', {
    props:['value','columns', 'field','enums'],
    data: function () {    
        return {
            sort_field:'',
            sort_asc:true,
            undo_paste:'',
            snackbar:false,
            snackbar_message:'',
            dialog_enum_selection:false,
            validation_errors:[]
        }
    },
    watch: {
        local: {
            handler: function (newVal, oldVal) {
                this.validation_errors=[];
                this.validateUniqueValues();    
            },
            deep: true
        },
        
    },
    
    mounted: function () {   
        //this.validation_errors=[];
        //this.validateUniqueValues();
    },
    computed: {
        local(){
            let value= this.value ? this.value : [{}];

            if (value.length<1){
                value= [{}];
            }

            //if dictionary object, convert to array
            if (typeof value=='object'){
                //do nothing
                return value;                
            }


            if (!Array.isArray(value)){
                value=[{}];
            }
        
            return value;
        },
        //get columns that require unique values
        columnsRequireUnique(){
            let keys=[];
            for (let i=0;i<this.columns.length;i++){
                if (this.columns[i]['is_unique']){
                    keys.push(this.columns[i]['key']);
                }
            }
            return keys;
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
        },
        isFieldReadOnly() {
            if (!this.$store.getters.getUserHasEditAccess) {
                return true;
            }
        
            if (this.field && this.field.is_readonly){
                return this.field.is_readonly;
            }

            return false;
        }
    },
    methods:{
        columnHasUniqueValues(data, key){
            let values=[];
            for (let i=0;i<data.length;i++){
                let value=data[i][key];

                if (value){
                    value=value.toString().trim();
                }

                if (values.includes(value)){
                    return false;
                }
                values.push(value);
            }
            return true;
        },
        validateUniqueValues: function(){
            let keys=this.columnsRequireUnique;

            if (keys.length<1){
                return;
            }

            let has_errors=false;

            for (let i=0;i<keys.length;i++){                
                if (!this.columnHasUniqueValues(this.local,keys[i])){
                    this.validation_errors.push("Duplicate values found in column: ["+keys[i] +"]");
                    has_errors=true;
                }
            }

            return !has_errors;
        },
        showToast: function(message){
            this.snackbar=true;
            this.snackbar_message=message;
        },
        addEnum: function(){
            this.dialog_enum_selection=true;
        },
        onEnumSelection: function(selection){            
            for(i=0;i<selection.length;i++){
                this.local.push(JSON.parse(JSON.stringify(selection[i])));
            }
            this.$emit('input', JSON.parse(JSON.stringify(this.local))); 
        },
        update: function (index, key, value, column_data_type='text')
        {
            if (Array.isArray(this.local[index])){
                this.local[index] = {};
            }

            if (column_data_type=='number' || column_data_type=='integer'){
                let value_=Number(value);
                if (String(value_)==value){
                    value=value_;
                }
            }
            else if (column_data_type=='boolean'){
                let value_=String(value).toLowerCase();
                if (value_=='true'){
                    value=true;
                }
                else if (value_=='false'){
                    value=false;
                }
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
            if (!confirm("Are you sure you want to delete this row?")){
                return;
            }
            
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
        columnDataType: function(column)
        {
            if (!column.type){
                return 'text';
            }

            if (column.type=='integer' || column.type=='number'){
                return 'number';
            }

            return 'text';
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
            
            <div v-if="validation_errors.length>0" class="sticky-top">
                <v-alert dense type="error" v-for="error in validation_errors">{{error}}</v-alert>
            </div>

            <table class="table table-striped table-sm border-bottom">
                <thead class="thead-light">
                <tr>
                    <th>
                    <!--options -->
                    <v-menu bottom left v-if="!isFieldReadOnly">
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
                    <span class="float-right" v-show="enums" v-if="!isFieldReadOnly">
                    <v-btn
                            light
                            icon
                            x-small                            
                            @click="addEnum"
                            
                        >
                            <v-icon>mdi-form-dropdown</v-icon>
                        </v-btn>
                        </span>
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
                                :disabled="isFieldReadOnly"
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
                                    :disabled="isFieldReadOnly"
                                ></v-combobox>
                        </div>
                        <div v-else>
                            <input type="text"
                                :value="local[index][column.key]"
                                @input="update(index,column.key, $event.target.value, column.type)"
                                class="form-control form-control-sm"
                                :disabled="isFieldReadOnly"
                            >
                        </div>
                        
                    </td>
                    <td scope="row">        
                        <div class="mr-1">
                            <v-icon  v-if="!isFieldReadOnly" class="v-delete-icon" v-on:click="remove(index)">mdi-trash-can-outline</v-icon>
                        </div>
                    </td>
                </tr>
                <!--end-v-for -->
                </tbody>
            </table>

            <div class="d-flex justify-content-center" v-if="!isFieldReadOnly">                
                <v-btn @click="addRow" class="m-2" text small ><v-icon>mdi-plus</v-icon>{{ $t("add_row") }}</v-btn>
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

            <vue-dialog-enum-selection-component
                v-model="dialog_enum_selection"
                :columns="columns"
                @selection="onEnumSelection($event)"
                :enums="enums"
            >
            </vue-dialog-enum-selection-component>

            </div>  `    
})