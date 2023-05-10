Vue.component('dialog-weight-variable-selection', {
    props:['value',"selected_items","variables"],
    data() {
        return {
            selection:''
        }
    }, 
    mounted: function () {
    },      
    methods: {   
        closeDialog: function(){
            this.dialog = false;
            this.$emit('selected', this.selection);
            this.selection = '';
        },
        /*isItemIncluded(uid){
            if (!this.selected_items){
                return false;
            }
            
            for(var i=0; i<this.selected_items.length; i++){
                if (this.selected_items[i]==uid){
                    return true;
                }
            }
            return false;
        },*/
        selectedVariable: function(){
            if (!this.selection){
                return null;
            }
            for (var i=0; i<this.Variables.length; i++){
                if (this.Variables[i].uid==this.selection){
                    return this.Variables[i];
                }
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
        Variables(){
            //return only variables with var_wgt==1
            variables = [];
            if (Array.isArray(this.variables)){
                for (var i=0; i<this.variables.length; i++){
                    if (this.variables[i].var_wgt==1){
                        variables.push(this.variables[i]);
                    }
                }
            }
            return variables;
        },
        ProjectID(){
            return this.$store.state.project_id;
        }        
    },
    template: `
        <div class="vue-dialog-component">

            <!-- dialog -->
            <v-dialog v-model="dialog" width="600" height="350" persistent>
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        Select weight variable {{selection}}
                    </v-card-title>

                    <v-card-text>
                    <div v-if="Variables && Variables.length>0">
                        <!-- card text -->
                        
                        <v-virtual-scroll
                            :items="Variables"
                            height="400"
                            item-height="40"
                        >

                        <template v-slot:default="{ item,index }">

                            <v-list-item :key="index" v-if="item.uid">
                                <v-list-item-action>
                                    <input type="radio" name="variable-selected" v-model="selection" :value="item.uid" :id="item.uid"/>
                                </v-list-item-action>

                                <v-list-item-content>
                                <v-list-item-title>
                                    <label :for="item.uid" class="text-normal">
                                    {{item.name}} -
                                    {{item.labl}}
                                    </label>
                                </v-list-item-title>

                                </v-list-item-content>

                                <v-list-item-action>
                                    
                                </v-list-item-action>
                            </v-list-item>                    

                        </template>

                            
                        </v-virtual-scroll>
                        

                        <!-- end card text -->
                    </div>
                    <div v-else class="border p-2 m-2 text-center text-danger">
                        <p>No weight variables found</p>
                    </div>
                    </v-card-text>

                    <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="primary" text @click="closeDialog" >
                        Close
                    </v-btn>
                    </v-card-actions>
                </v-card>
                </v-dialog>
            <!-- end dialog -->
        
        </div>
    `
});

