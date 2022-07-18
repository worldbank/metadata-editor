//spread metadata for variables
Vue.component('spread-metadata', {
    props:['variables','value'],
    data: function () {    
        return {
            //field_data: this.value,
            //dialog:this.value,
            dialogm1: ''
        }
    },
    created: async function(){
        //this.fid=this.$route.params.file_id;
        //alert(1);
    },
    
    computed: {
        /*dataFiles(){
            return this.$store.getters.getDataFiles;
        }*/
    },  
    template: `
            <div class="spread-metadata-component">

            <template>
                <v-layout row justify-center>
                    <v-dialog
                    v-model="value" persistent max-width="500">
                    scrollable
                    
                    max-width="650px"
                    >
                    
                    <v-card>
                        <v-card-title style="m-0 p-1">
                            <div>Spread metadata</div>
                            <v-spacer></v-spacer>
                            <v-btn right text color="red" @click.native="$emit('input', false)">Close</v-btn>
                        </v-card-title>
                        <v-divider class="m-0 p-1"></v-divider>
                        <v-card-text>

                        <div style="height:200px;overflow:auto;">
                        <table class="table table-sm table-bordered">
                            <tr>
                                <td>Dataset</td>
                                <td>Matches</td>
                                <td>Match %</td>
                                <td>Type mismatches</td>
                            </tr>
                            <tr>
                                <td>Dataset</td>
                                <td>Matches</td>
                                <td>Match %</td>
                                <td>Type mismatches</td>
                            </tr>
                        </table>
                        </div>

                        <div>
                            <div class="border-bottom"><strong>Spread metadata</strong></div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="" id="defaultCheck1">
                                <label class="form-check-label" for="defaultCheck1">
                                    Variable information
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="" id="defaultCheck1">
                                <label class="form-check-label" for="defaultCheck1">
                                    Variable documentation
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="" id="defaultCheck1">
                                <label class="form-check-label" for="defaultCheck1">
                                    Categories
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="" id="defaultCheck1">
                                <label class="form-check-label" for="defaultCheck1">
                                    Question texts
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="" id="defaultCheck1">
                                <label class="form-check-label" for="defaultCheck1">
                                    Weights
                                </label>
                            </div>
                        </div>

                        
                        </v-card-text>
                        <v-divider></v-divider>
                        <v-card-actions>
                        <v-btn text color="red" @click.native="$emit('input', false)">Close</v-btn>
                        <v-btn
                            color="blue darken-1"
                            text
                            @click="value = false"
                        >
                            Save
                        </v-btn>
                        </v-card-actions>
                    </v-card>
                    </v-dialog>
                
                    </v-layout>
                    </template>
            </div>          
            `    
});

