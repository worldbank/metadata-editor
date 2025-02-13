Vue.component('vue-template-uuid', {
    props: ['value','template_id'],
    data() {
        return {            
            is_loading: false,
            new_value: '',
            selected_users: [],
        }
    },
    created:function(){

    },
    watch:{
    },
    methods: {   
        erorrMessageToText: function(error){
            let error_text = '';
            if (error.response.data.errors) {
                for (let key in error.response.data.errors) {
                    error_text += error.response.data.errors[key] + '\n';
                }
            } else {
                error_text = error.response.data.message;
            }
            return error_text;
        },          
        updateUid: function(index){
            let form_data={
                'old_uid': this.template_id,
                'new_uid': this.new_value
            };
            let vm=this;
            let url = CI.base_url + '/api/templates/uid/';
            axios.post(url,
                form_data
            )
            .then(response => {
                console.log(response);
                this.$emit('update-uuid', true);
                vm.dialog=false;                
            })
            .catch(function (error) {
                console.log(error);
                alert("Failed: " + vm.erorrMessageToText(error));
            });
            
        }               
        
    },
    computed:{
        dialog: {
            get: function () {
                return this.value;
            },
            set: function (newValue) {
                this.$emit('input', newValue);               
            }
       },
    },
    template: `
        <div class="vue-project-uuid">

        <template>        
            <div class="text-center">
                <v-dialog
                v-model="dialog"
                width="800px"
                scrollable                
                >

                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                    Change template UID
                    </v-card-title>
                    <v-card-text>

                        <div>Template UID: {{template_id}}</div>

                        <v-divider></v-divider>

                        <strong>New UID:</strong>
                        <v-row>
                            <v-col cols="8">                                
                                <v-text-field
                                    v-model="new_value"
                                    :loading="is_loading"
                                    :rules="[v => !!v || 'Template UID is required']"
                                    label=""
                                    required
                                    clearable
                                    outlined
                                    dense

                                ></v-text-field>
                               
                            </v-col>
                            <v-col cols="4">
                             <v-btn
                                    class="ma-2"
                                    outlined
                                    color="indigo"                                    
                                    @click="updateUid"
                                >
                                    Update
                                </v-btn>
                            </v-col>

                        </v-row>
                        
                    </v-card-text>

                    <v-divider></v-divider>

                    <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn
                        class="ma-2"
                        outlined
                        color="indigo"
                        small
                        @click="selected=[];dialog = false"
                    >
                        Close
                    </v-btn>
                    </v-card-actions>
                    
                </v-card>
                </v-dialog>
            </div>
        </template>
        
    </div>
    `
});

