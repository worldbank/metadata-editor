<div class="prop-edit-component">
    <div v-if="prop.key">

        <div class="mb-3">
            <label for="name">{{$t('key')}}:</label>
            <vue-prop-key-field                
                :parent="parent"
                :field="prop"
                :value="prop.key"
                @input="updatePropKey"
                >
            </vue-prop-key-field>
            <div v-if="prop.prop_key" class="text-secondary font-small mb-3" >{{prop.prop_key}}</div>
        </div>

        <div class="mb-3">
            <label class="mb-1 d-block">{{$t('type')}}:</label>
            <v-text-field
                v-model="prop.type"
                disabled
                outlined
                dense
                hide-details
            ></v-text-field>
        </div>

        <div class="mb-3">
            <label class="mb-1 d-block">{{$t('label')}}:</label>
            <v-text-field
                v-model="prop.title"
                outlined
                dense
                hide-details
            ></v-text-field>
        </div>
        <div class="text-secondary font-small mb-3" style="font-size:small">
            <span class="pl-3">{{$t('name')}}: {{prop.key}}</span>
            <span class="pl-3">{{$t('type')}}: {{prop.type}}</span>
        </div>

        <v-row class="mb-3">
            <v-col cols="auto">
                <v-checkbox
                    v-if="prop.type!=='section' &&  prop.type!=='section_container'"
                    v-model="prop.is_required"
                    :label="$t('required')"
                    hide-details
                ></v-checkbox>
            </v-col>

            <v-col cols="auto">
                <v-checkbox
                    v-if="prop.type!=='section' &&  prop.type!=='section_container'"
                    v-model="prop.is_recommended"
                    :label="$t('recommended')"
                    hide-details
                ></v-checkbox>
            </v-col>
        </v-row>

        <div class="mb-3">
            <label class="mb-1 d-block">{{$t('description')}}:</label>
            <v-textarea
                v-model="prop.help_text"
                outlined
                rows="4"
                hide-details
            ></v-textarea>
        </div> 

    </div>
    <!-- Only show tabs for non-section props - sections should work like regular sections -->
    <template v-if="prop.type!=='section' && prop.type!=='section_container'">
        <v-tabs background-color="transparent" class="mb-5" :key="prop.prop_key">
            <v-tab  v-if="isField(prop.type) || prop.type=='simple_array'">{{$t("display")}}</v-tab>
            <v-tab><span v-if="prop.enum && prop.enum.length>0"><v-icon style="color:green;">mdi-circle-medium</v-icon></span>{{$t("controlled_vocabulary")}}</v-tab>
            <v-tab>{{$t("default")}}<span v-if="prop.default"><v-icon style="color:green;">mdi-circle-medium</v-icon></span></v-tab>
            <v-tab v-if="isField(prop.type)"><span v-if="prop.rules && Object.keys(prop.rules).length>0"><v-icon style="color:green;">mdi-circle-medium</v-icon></span>{{$t("validation_rules")}}</v-tab>
            <v-tab>{{$t("json")}}</v-tab>

            <v-tab-item class="p-3"  v-if="isField(prop.type)  || prop.type=='simple_array'">

                <!--display-->
                <div v-if="prop.type!='simple_array'" class="mb-3">
                    <label class="mb-1 d-block">{{$t('data_type')}}:</label>
                    <v-select
                        v-model="prop.type"
                        :items="field_data_types"
                        outlined
                        dense
                        hide-details
                    ></v-select>
                </div>

                <div class="mb-3">
                    <label class="mb-1 d-block">{{$t('display')}}:</label>
                    <v-select
                        v-model="prop.display_type"
                        :items="field_display_types"
                        outlined
                        dense
                        hide-details
                    ></v-select>
                </div>
                <!--end display -->

            </v-tab-item>

            <v-tab-item class="p-3">
                <!-- controlled vocab -->
                <template>
                <div class="mb-3" >
                    <label for="controlled_vocab">{{$t("controlled_vocabulary")}}:</label>
                    <div class="border bg-white" style="max-height:300px;overflow:auto;">

                        <template v-if="isField(prop.type) || prop.type=='simple_array'">                                                    
                            
                            <div>
                                <div class="m-3">
                                    <div>{{$t("enum_store_options_label")}}:</div>

                                    <v-select
                                        style="max-width:300px;"
                                        v-model="PropEnumStoreColumn"
                                        :items="enum_store_options"
                                        :item-text="item => item.label"
                                        :item-value="item => item.value"
                                        dense 
                                        outlined
                                        clearable
                                        label=""
                                    ></v-select>
                                </div>
                            </div>

                            <table-grid-component
                                :key="prop.key"
                                :columns="SimpleControlledVocabColumns" 
                                v-model="PropEnum"
                                @update:value="EnumListUpdate"
                                class="border m-2 pb-2"
                            ></table-grid-component>

                        </template>
                        <template v-else>
                            
                            <table-grid-component
                                :key="prop.key"
                                :columns="prop.props" 
                                v-model="PropEnum"
                                @update:value="EnumListUpdate"
                                class="border m-2 pb-2"
                            ></table-grid-component>
                            
                        </template>
                    </div>

                </div>
                </template>
                <!-- end controlled vocab -->
            </v-tab-item>
            <v-tab-item class="p-3">
                <!-- default -->
                <template v-if="prop.type!=='section_container' && prop.type!=='section'">
                    <div class="mb-3" >
                        <label for="controlled_vocab">{{$t("default")}}:</label>
                        <div class="border bg-white" style="max-height:300px;overflow:auto;" v-if="prop.type=='array'">                            

                            <table-grid-component
                                :key="prop.key"
                                :columns="prop.props" 
                                v-model="prop.default"
                                @update:value="DefaultUpdate"
                                class="border m-2 pb-2"
                            ></table-grid-component>

                        </div>
                        <div class="border bg-white" v-else>
                            <v-textarea
                                v-if="prop.display_type=='textarea'"
                                v-model="prop.default"
                                outlined
                                rows="8"
                                hide-details
                                class="mt-2"
                            ></v-textarea>
                            <v-text-field
                                v-else-if="isField(prop.type)"
                                v-model="prop.default"
                                outlined
                                dense
                                hide-details
                                class="mt-2"
                            ></v-text-field>                        
                        </div>
                    </div>
                </template>
                <!-- end default -->
            </v-tab-item>
            <v-tab-item class="p-3" v-if="isField(prop.type)">
                <div class="mb-3" >
                    <label for="controlled_vocab">{{$t("validation_rules")}}:</label>
                    <div class="bg-white border">
                        <validation-rules-component v-model="prop.rules" @update:value="RulesUpdate"  class="m-2 pb-2" />
                    </div>
                </div>
            </v-tab-item>

            <v-tab-item class="p-3">
                <div class="mb-3" >
                    <label for="controlled_vocab">{{$t("json")}}:</label>
                    <div class="bg-white border" :style="prop && prop.type === 'nested_array' ? 'max-height: 300px; overflow-y: auto;' : ''">
                        <pre>{{prop}}</pre>
                    </div>
                </div>
            </v-tab-item>
        </v-tabs>
    </template>
</div>
    