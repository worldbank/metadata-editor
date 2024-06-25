/// Project export json component
Vue.component('project-export-json-component', {
    props:['value'],
    data () {
        return {
            options: 'all',
            base_url: CI.base_url
        }
      },
    mounted: function(){
    },
    watch:{
    },
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        },
        ProjectIDNo(){
            return this.$store.state.idno;
        },
        dialog: {
            get () {
                return this.value
            },
            set (val) {
                this.$emit('input', val)
            }
        }
    },
    methods:{        
        exportJson: async function(){
            if (this.options=='all'){
                window.open(this.base_url + '/api/editor/json/'+this.ProjectID, '_blank');
            }else{
                window.open(this.base_url + '/api/editor/json/'+this.ProjectID+'/1', '_blank');
            }

            this.dialog=false;
        }        
    },     
    template: `
            <div class="project-export-json-component">

            <!-- dialog -->
            <v-dialog v-model="dialog" max-width="400" scrollable persistent style="z-index:5000">
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        {{$t('Export project metadata as JSON')}}
                    </v-card-title>
                    <v-card-subtitle>
                        
                    </v-card-subtitle>
                    <v-card-text style="min-height: 100px;">
                        <v-radio-group
                            v-model="options"
                            mandatory
                            >
                            <v-radio
                                label="Export all fields"
                                value="all"
                                class="font-weigh-normal"
                            ></v-radio>
                            <v-radio
                                label="Exclude fields marked as 'private'"
                                value="public"
                                class="font-weigh-normal"
                            ></v-radio>
                        </v-radio-group>
                    </v-card-text>

                    <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="primary" text @click="exportJson" >
                        Export
                    </v-btn>
                    <v-btn color="primary" text @click="dialog=false;" >
                        Close
                    </v-btn>
                    </v-card-actions>
                </v-card>
                </v-dialog>
            <!-- end dialog -->
                    
            </div>          
            `    
});

