// Dialog JSON Viewer Component
Vue.component('vue-dialog-json-viewer-component', {
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
            <v-dialog v-model="dialog.show" width="700" height="300" persistent style="z-index:20001;">
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        {{dialog.title}}
                    </v-card-title>

                    <v-card-text>
                    <div>
                        <pre style="height:400px;overflow:auto;" class="bg-light">{{dialog.data}}</pre>
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
        
        </div>
    `
});