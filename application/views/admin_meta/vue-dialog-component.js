Vue.component('vue-dialog-component', {
    props:['value'],
    data() {
        return {            
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
    },
    computed: {
        dialog: {
            get () {
                return this.value
            },
            set (val) {
                this.$emit('input', val)
            }
        }
    },
    template: `
        <div class="vue-dialog-component">
        <!-- dialog -->
        <v-app>
            <v-dialog v-model="dialog.show" scrollable max-width="500px" persistent>
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        {{dialog.title}}
                    </v-card-title>

                    <v-card-text>
                    <div>
                        <!-- card text -->
                        <div v-if="dialog.is_loading">{{dialog.loading_message}}</div>
                        
                        <v-progress-linear v-if="dialog.is_loading"
                            indeterminate
                            color="green"
                            ></v-progress-linear>
                        

                        <div class="alert alert-success" v-if="dialog.message_success" type="success">
                            {{dialog.message_success}}
                        </div>
                        
                        <v-alert color="red" dense outlined text prominent style="color:red;" v-if="dialog.message_error">
                            <div v-if="dialog.message_error.message">{{dialog.message_error.message}}</div>                            
                            <div v-if="dialog.message_error.errors">
                                <ul class="mt-2 ml-4">
                                    <li v-for="(error, key) in dialog.message_error.errors">{{error.message}}</li>
                                </ul>
                            </div>
                            <div v-if="dialog.message_error && !dialog.message_error.message && !dialog.message_error.errors">                                
                            <pre>{{dialog.message_error}}</pre>
                            </div>
                        </v-alert>

                        <!-- end card text -->
                    </div>
                    </v-card-text>

                    <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="primary" text @click="dialog.show=false" v-if="dialog.is_loading==false">
                        Close
                    </v-btn>
                    </v-card-actions>
                </v-card>
                </v-dialog>
            <!-- end dialog -->
        </v-app>
        
        </div>
    `
});

