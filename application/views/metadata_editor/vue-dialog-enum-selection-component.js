Vue.component('vue-dialog-enum-selection-component', {
    props:['value','enums','columns','selected_enum'],
    data() {
        return {            
            selected: [],
            singleSelect: false,
            selected: [],
            headers: [],
            item_key: 'name',
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
            this.$emit('selection', this.selected);
            this.dialog=false;
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
            return this.enums;
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
                                item-key="name"
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

