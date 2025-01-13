Vue.component('vue-dialog-edit-schema-component', {
    props:['value'],
    data() {
        return {                  
            schema_error:'',
            errors: null,
            /*{
                show:false,
                title:'',
                loading_message:'',
                message_success:'',
                message_error:'',
                is_loading:false
            }*/
        }
    }, 
    mounted: function () {
    },      
    methods: {
        saveSchema: function(){
            //is new or update
            if (this.dialog.data.id){
                this.updateSchema();
            }else{
                this.createSchema();
            }
        },
        createSchema: function()
        {
            vm=this;
            vm.errors=null;
            let url=CI.base_url + '/api/admin-metadata/schema';
            axios.post( url, vm.dialog.data
            ).then(function(response){
                console.log("Schema created",response.data);
                vm.dialog.show=false;
                vm.$emit('schema-created', response.data);
            })
            .catch(function(err){
                vm.errors=err.response.data;
                console.log("failed", err.response.data);
            });
            
        },
        updateSchema: function(){
            vm=this;
            let url=CI.base_url + '/api/admin-metadata/schema_update/'+vm.dialog.data.id;
            axios.post( url, vm.dialog.data
            ).then(function(response){
                console.log("Schema updated",response.data);
                vm.dialog.show=false;
                vm.$emit('schema-updated', response.data);
            })
            .catch(function(err){
                vm.errors=err.response.data;
                console.log("failed", err.response.data);
            });
        },
        closeDialog: function(){
            this.dialog.show=false;
            this.errors=null;
        }
    },
    computed: {
        dialog: {
            get () {
                return this.value
            },
            set (val) {
                this.$emit('input', val)
            }
        },
        SchemaToString: {
            get () {
                if (!this.dialog.data.schema){
                    return "";
                }
                return JSON.stringify(this.dialog.data.schema, null, 2);
            },
            set (val) {
                try{
                    this.schema_error="";
                    this.dialog.data.schema=JSON.parse(val);
                }catch(e){
                    this.schema_error="Error parsing schema: " + e;
                    console.log("Error parsing schema", e);
                }
            }
            
        },
        FormFields(){
            return [
                {
                    key: 'agency',
                    label: 'Agency',
                    hint: 'Organization name e.g. WBG, IMF, etc.',
                    type: 'text',
                    required: true,
                    rules: [
                        v => !!v || 'Agency is required',
                        v => /^[a-zA-Z0-9_-]+$/.test(v) || 'No spaces or special characters allowed',
                        v => v.length <= 30 || 'Max length 30'
                    ]
                },
                {
                    key: 'name',
                    label: 'Schema name',
                    hint: 'Schema name',
                    type: 'text',
                    required: true,
                    rules: [
                        v => !!v || 'Name is required',
                        v => /^[a-zA-Z0-9_-]+$/.test(v) || 'No spaces or special characters allowed',
                        v => v.length <= 50 || 'Max length 50'
                    ]
                },
                {
                    key: 'version',
                    label: 'Version',
                    hint: 'Version number in format 1.0.0',
                    type: 'text',
                    required: true,
                    rules: [
                        v => !!v || 'Version is required',
                        v => /^[0-9]+\.[0-9]+\.[0-9]+$/.test(v) || 'Version should be in format 1.0.0',
                        v => v.length <= 30 || 'Max length 30'
                    ]
                },
                {
                    key: 'title',
                    label: 'Title',
                    hint: 'Title',
                    type: 'text',
                    required: true,
                },
                {
                    key: 'description',
                    label: 'Description',
                    hint: 'Short description',
                    type: 'text',
                    required: false,
                }                
            ];  
        }
    },
    template: `
        <div class="vue-dialog-component">
            <v-app>
            
            <v-dialog v-model="dialog.show" width="700" height="300" persistent style='z-index:20001;'>
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        {{dialog.title}}
                    </v-card-title>

                    <v-card-text>
                    <div>
                    
                        <v-app>
                        <v-container>
                            <v-row>
                                <v-col cols="12" v-for="(field, field_key) in FormFields" :key="field_key">

                                    <div class="font-weight-bold">{{field.label}}</div>                                    
                                    <v-text-field v-if="field.type=='text'" dense outlined
                                        v-model="dialog.data[field.key]"
                                        :hint="field.hint"
                                        label=""
                                        :rules="field.rules"
                                        required
                                    ></v-text-field>
                                    <v-textarea v-else-if="field.type=='textarea'" dense outlined
                                        v-model="dialog.data[field.key]"                                        
                                        label=""
                                        :hint="hint"
                                        :rules="field.required ? [v => !!v || 'Field is required'] : []"
                                        required
                                    ></v-textarea>                                                                        
                                </v-col>
                                <v-col cols="12">
                                    <div class="font-weight-bold">JSON Schema</div>
                                    <v-textarea dense outlined
                                        v-model="SchemaToString"
                                        label=""
                                    ></v-textarea>
                                    <v-alert color="red" outlined type="error" dense v-if="schema_error">{{schema_error}}</v-alert>
                                </v-col>
                            </v-row>
                        </v-container>
                        
                    
                        <v-alert color="red" dense outlined v-if="errors" style="color:red;">                            
                            <div>Errors: <span v-if="errors.message">{{errors.message}}</span></div>
                            <ul v-if="errors.errors">
                                <li v-for="(error, key) in errors.errors">{{error.message}}</li>
                            </ul>                        
                        </v-alert>
                    
                        </v-app>

                    </div>
                    </v-card-text>

                    <v-card-actions>
                    <v-spacer></v-spacer>

                    <v-btn color="primary" text @click="saveSchema" v-if="dialog.is_loading==false">
                        Save
                    </v-btn>

                    <v-btn color="primary" text @click="closeDialog" v-if="dialog.is_loading==false">
                        Cancel
                    </v-btn>

                    
                    </v-card-actions>
                </v-card>

                

                </v-dialog>
            </v-app>
            
        
        </div>
    `
});