Vue.component('dialog-variable-selection', {
    props:['value',"selected_items"],
    data() {
        return {
            selection:[]
        }
    }, 
    mounted: function () {

        
    },      
    methods: {   
        closeDialog: function(){
            this.dialog = false;
            this.$emit('selected', this.selection);
            this.selection = [];
        },
        isItemIncluded(uid){
            if (!this.selected_items){
                return false;
            }
            
            for(var i=0; i<this.selected_items.length; i++){
                if (this.selected_items[i]==uid){
                    return true;
                }
            }
            return false;
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
        items () {
            return Array.from({ length: 1000}, (k, v) => v + 1)
          },
        Variables(){
            $variablesByFile= this.$store.getters.getVariablesAll;
            if (!$variablesByFile){
                return [];
            }

            $variables = [];
            for (var $file in $variablesByFile){
                $variables = $variables.concat($variablesByFile[$file]);
            }
            return $variables;
        },
        ProjectID(){
            return this.$store.state.project_id;
        }        
    },
    template: `
        <div class="vue-dialog-component">

            <!-- dialog -->
            <v-dialog v-model="dialog" width="700" height="400" persistent>
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        Variable selection
                    </v-card-title>

                    <v-card-text>
                    <div v-if="Variables">
                        <!-- card text -->
                        
                        <v-virtual-scroll
                            :items="Variables"
                            height="400"
                            item-height="40"
                        >

                        <template v-slot:default="{ item,index }">

                            <v-list-item :key="index" v-if="item.uid">
                                <v-list-item-action>
                                    <input type="checkbox" v-model="selection" :value="item.uid" :disabled="isItemIncluded(item.uid)" :id="item.uid"/>                                
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
                                    {{item.fid}}
                                </v-list-item-action>
                            </v-list-item>                    

                        </template>

                            
                        </v-virtual-scroll>
                        

                        <!-- end card text -->
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

