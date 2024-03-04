<div class="prop-edit-component">
    <div v-if="prop.key">
        <div class="form-group">
            <label for="name">{{$t('label')}}:</label>
            <input type="text" class="form-control" v-model="prop.title">
            <div class="text-secondary font-small" style="margin-top:4px;font-size:small">
                <span class="pl-3">{{$t('name')}}: {{prop.key}}</span>
                <span class="pl-3">{{$t('type')}}: {{prop.type}}</span>
            </div>
        </div>

        <div class="form-group">
            <label for="name">{{$t('description')}}:</label>
            <textarea class="form-control" v-model="prop.help_text"/>
        </div>

        <div class="form-group">
            <label for="name">{{$t('type')}}:</label>
            <input type="text" class="form-control" v-model="prop.type">
        </div>

        <div class="form-group" v-if="HasAdditionalPrefix(parent.key)">
            <label for="name">{{$t('key')}}:</label>
            <vue-prop-key-field                
                :parent="parent"
                :value="prop.key"
                @input="updatePropKey"
                >
            </vue-prop-key-field>            
        </div>
    </div>
    <template>
        <v-tabs background-color="transparent" class="mb-5" :key="prop.prop_key">
            <v-tab  v-if="isField(prop.type) || prop.type=='simple_array'">{{$t("display")}}</v-tab>
            <v-tab><span v-if="prop.enum && prop.enum.length>0"><v-icon style="color:green;">mdi-circle-medium</v-icon></span>{{$t("controlled_vocabulary")}}</v-tab>
            <v-tab>{{$t("default")}}<span v-if="prop.default"><v-icon style="color:green;">mdi-circle-medium</v-icon></span></v-tab>
            <v-tab v-if="isField(prop.type)"><span v-if="prop.rules && Object.keys(prop.rules).length>0"><v-icon style="color:green;">mdi-circle-medium</v-icon></span>{{$t("validation_rules")}}</v-tab>
            <v-tab>{{$t("json")}}</v-tab>

            <v-tab-item class="p-3"  v-if="isField(prop.type)  || prop.type=='simple_array'">

                <!--display-->
                <div class="form-group" v-if="prop.type!='simple_array'">
                    <label >{{$t("data_type")}}:</label>
                    <select 
                        v-model="prop.type" 
                        class="form-control form-field-dropdown" >        
                        <option v-for="field_type in field_data_types">
                            {{field_type}}
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label>{{$t("display")}}:</label>
                    <select 
                        v-model="prop.display_type" 
                        class="form-control form-field-dropdown" >        
                        <option v-for="display_type in field_display_types">
                            {{display_type}}
                        </option>
                    </select>

                </div>
                <!--end display -->

            </v-tab-item>

            <v-tab-item class="p-3">
                <!-- controlled vocab -->
                <template>
                <div class="form-group" >
                    <label for="controlled_vocab">{{$t("controlled_vocabulary")}}:</label>
                    <div class="border bg-white" style="max-height:300px;overflow:auto;">

                        <template v-if="isField(prop.type) || prop.type=='simple_array'">                                                    

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
                    <div class="form-group" >
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
                            <div v-if="prop.display_type=='textarea'">
                                <textarea class="form-control" style="height:200px;" v-model="prop.default"></textarea>
                            </div>
                            <div v-else-if="isField(prop.type)">
                                <input class="form-control" type="text" v-model="prop.default"/>
                            </div>                        
                        </div>
                    </div>
                </template>
                <!-- end default -->
            </v-tab-item>
            <v-tab-item class="p-3" v-if="isField(prop.type)">
                <div class="form-group" >
                    <label for="controlled_vocab">{{$t("validation_rules")}}:</label>
                    <div class="bg-white border">
                        <validation-rules-component v-model="prop.rules" @update:value="RulesUpdate"  class="m-2 pb-2" />
                    </div>
                </div>
            </v-tab-item>

            <v-tab-item class="p-3">
                <div class="form-group" >
                    <label for="controlled_vocab">{{$t("json")}}:</label>
                    <pre>{{prop}}</pre>
                </div>
            </v-tab-item>
        </v-tabs>

    </template>
</div>
    