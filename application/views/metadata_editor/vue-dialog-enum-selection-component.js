Vue.component('vue-dialog-enum-selection-component', {
    props:['value','enums','columns','selected_enum'],
    data() {
        return {            
            selected: [],
            singleSelect: false,
            selected: [],
            headers: [],
            item_key: 'index',
            search: '',
            table_options: {
                itemsPerPage: -1
              }
        }
    }, 
    mounted: function () {
        this.initColumnHeaders();
    },      
    methods: {
        initColumnHeaders() {
            this.headers = [];
            for (var i = 0; i < this.columns.length; i++) {
                var col = this.columns[i];
                this.headers.push({ text: col.title, value: col.key});
            }
        },
        addSelection: function(){
            this.$emit('selection', JSON.parse(JSON.stringify(this.selectedItems)));
            this.dialog=false;
            this.selected=[];
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
        items() {
            //add a numeric index to the enums
            //this is needed for the v-data-table
            let items=[];
            
            if (this.enums==null){
                return items;
            }

            for (let i=0;i<this.enums.length;i++){
                let item=this.enums[i];
                item.index__=i;
                items.push(item);
            }
            return items;
        },
        selectedItems(){
            //exclude column index__
            let items=[];
            for (let i=0;i<this.selected.length;i++){
                let item=JSON.parse(JSON.stringify(this.selected[i]));
                delete item.index__;
                items.push(item);
            }
            return items;
        }
    },
    template: `
        <div class="vue-dialog-enum-selection-component">

            <!-- dialog -->
            <v-dialog v-model="dialog" max-width="700" height="300" persistent scrollable>
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        
                    </v-card-title>
                    <v-card-text style="height: 300px;">
                    <div>
                    
                        <!-- card text -->
                        <template>

                        <v-text-field
                            v-model="search"
                            append-icon="mdi-magnify"
                            label="Search"
                            single-line
                            hide-details
                        ></v-text-field>

                            <v-data-table
                                v-model="selected"
                                :headers="headers"
                                :items="items"
                                :single-select="singleSelect"
                                item-key="index__"
                                show-select
                                class="elevation-1"
                                hide-default-header
                                hide-default-footer
                                :search="search"
                                :options="table_options"
                            >                                
                            </v-data-table>
                        </template>

                        <!-- end card text -->
                    </div>
                    </v-card-text>

                    <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="primary" text @click="addSelection" >
                        Apply
                    </v-btn>
                    <v-btn color="primary" text @click="dialog=false" >
                        Close
                    </v-btn>
                    </v-card-actions>
                </v-card>
                </v-dialog>
            <!-- end dialog -->
        
        </div>
    `
});

