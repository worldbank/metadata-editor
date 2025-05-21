Vue.component('vue-dialog-edit-meta-component', {
    props:['value'],
    data() {
        return {                              
            errors: null,
            schema_list:[]
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
        this.initSchemaList();
    },      
    methods: {
        saveMetaType: function(){
            //is new or update
            if (this.dialog.data.id){
                this.updateMetaType();
            }else{
                this.createMetaType();
            }
        },
        createMetaType: function()
        {
            vm=this;
            vm.errors=null;
            let url=CI.base_url + '/api/admin-metadata/type';
            axios.post( url, vm.dialog.data
            ).then(function(response){
                console.log("Meta type created",response.data);
                vm.dialog.show=false;
                vm.$emit('meta-created', response.data);
            })
            .catch(function(err){
                vm.errors=err.response.data;
                console.log("failed", err.response.data);
            });
            
        },
        updateMetaType: function(){
            vm=this;
            let url=CI.base_url + '/api/admin-metadata/type_update/'+vm.dialog.data.id;
            axios.post( url, vm.dialog.data
            ).then(function(response){
                console.log("Meta type updated",response.data);
                vm.dialog.show=false;
                vm.$emit('meta-updated', response.data);
            })
            .catch(function(err){
                vm.errors=err.response.data;
                console.log("failed", err.response.data);
            });
        },
        closeDialog: function(){
            this.dialog.show=false;
            this.dialog.data={};            
            this.errors=null;
        },
        initSchemaList: function(){            
            let vm=this;            
            for (let key in vm.dialog.schema_list){
                vm.schema_list.push({
                    text: vm.dialog.schema_list[key].name,
                    value: vm.dialog.schema_list[key].id
                });
            }            
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
        
        FormFields(){
            return [
                {
                    key: 'name',
                    label: 'Administrative metadata name',
                    hint: 'Unique name for the metadata type',
                    type: 'text',
                    required: true,
                    rules: [
                        v => !!v || 'Name is required',
                        v => /^[a-zA-Z0-9_-]+$/.test(v) || 'No spaces or special characters allowed',
                        //v => v.length <= 50 || 'Max length 50'
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
                },
                {
                    key: 'schema_id',
                    label: 'Select schema',
                    hint: 'Select schema',
                    type: 'dropdown',
                    required: true,
                    rules: [
                        v => !!v || 'Schema is required'
                    ],
                    dropdown_items: this.schema_list
                }
            ];  
        }
    },
    template: `
        <div class="vue-dialog-component vue-dialog-edit-meta-component">
            <v-app>
            
            <v-dialog v-model="dialog.show" width="700" height="300" persistent style='z-index:20001;'>
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        {{dialog.title}}
                    </v-card-title>

                    <v-card-text>
                    <div>
                    
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
                                    <v-select v-else-if="field.type=='dropdown'" dense outlined
                                        v-model="dialog.data[field.key]"
                                        :items="dialog.schema_list"
                                        :item-text="(row) => {return row.title + ' - ' + row.version;}""
                                        item-value="id"
                                        label=""
                                        :rules="field.rules"
                                        required
                                        >                                        
                                        </v-select>                                        
                                </v-col>
                            </v-row>
                        </v-container>
                        
                        <v-alert color="red" dense outlined v-if="errors" style="color:red;">                            
                            <div>Errors: <span v-if="errors.message">{{errors.message}}</span></div>
                            <ul v-if="errors.errors" class="mt-2 ml-4">
                                <li v-for="(error, key) in errors.errors">{{error.message}}</li>
                            </ul>                        
                        </v-alert>
                    
                    </div>
                    </v-card-text>

                    <v-card-actions>
                    <v-spacer></v-spacer>

                    <v-btn color="primary" text @click="saveMetaType" v-if="dialog.is_loading==false">
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