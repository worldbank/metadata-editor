<div v-if="!ActiveNode.key" class="m-3 p-3">{{$t("click_on_sidebar_to_edit")}}</div>

<!--additional fields-->
    <div class="mb-3">

        <template v-if="ActiveNodeHasAdditionalPrefix">

            <vue-key-field 
                :key="ActiveNode.key"
                :value="ActiveNode.key"
                @input="UpdateActiveNodeKey"
                >
            </vue-key-field>
        </template>
        <template v-else>
            <div><label>{{$t("key")}}:</label></div>
            <div class="border p-1 text-secondary">{{ActiveNode.key}}</div>
        </template>
    </div>


<!--item-->
<div v-if="ActiveNode.key ">

<!--section container fields -->
<div class="form-group">
    <label for="name">{{$t("label")}}:</label>
    <input type="text" class="form-control" id="name" placeholder="Label" v-model="ActiveNode.title">
    <div v-if="ActiveNode.key && coreTemplateParts[ActiveNode.key]" class="text-secondary font-small" style="margin-top:4px;font-size:small">Original label: {{coreTemplateParts[ActiveNode.key].title}} <span class="pl-3">Name: {{ActiveNode.key}}</span> <span class="pl-3">Type: {{ActiveNode.type}}</span>  </div>
</div>

<div class="form-group">
    <label for="name">{{$t("type")}}:</label>
    <template v-if="ActiveNode.type=='section' || ActiveNode.type=='section_container' || ActiveNode.type=='nested_array'">        
        <input type="text" class="form-control" id="name" placeholder="Label" v-model="ActiveNode.type" disabled="disabled">
    </template>
    <template v-else>        
        <select v-model="ActiveNode.type" class="form-control form-field-dropdown" >
            <option v-for="field_type in field_types">
                {{field_type}}
            </option>
        </select>
    </template>
</div>

<div class="row">
    <div class="col-auto">
        <div class="form-group form-check" v-if="ActiveNode.type!=='section' &&  ActiveNode.type!=='section_container'">
            <input type="checkbox" class="form-check-input" id="required" v-model="ActiveNode.is_required" >
            <label class="form-check-label" for="required">{{$t("required")}}</label>
        </div>
    </div>

    <div class="col-auto">
        <div class="form-group form-check" v-if="ActiveNode.type!=='section' &&  ActiveNode.type!=='section_container'">
            <input type="checkbox" class="form-check-input" id="recommended" v-model="ActiveNode.is_recommended">
            <label class="form-check-label" for="recommended">{{$t("recommended")}}</label>
        </div>
    </div>

    <div class="col-auto">
        <div class="form-group form-check" v-if="ActiveNode.type!=='section' &&  ActiveNode.type!=='section_container'">
            <input type="checkbox" class="form-check-input" id="private" v-model="ActiveNode.is_private">
            <label class="form-check-label" for="private">{{$t("private")}}</label>
        </div>
    </div>

    <div class="col-auto">
        <div class="form-group form-check" v-if="ActiveNode.type!=='section' &&  ActiveNode.type!=='section_container'">
            <input type="checkbox" class="form-check-input" id="readonly" v-model="ActiveNode.is_readonly">
            <label class="form-check-label" for="private">{{$t("readonly")}}</label>
        </div>
    </div>
</div>

<div class="form-group mb-3" v-if="ActiveNode.key">
    <label >{{$t("description")}}:</label>
    <textarea style="height:200px;" class="form-control"  v-model="ActiveNode.help_text"></textarea>
    <div class="text-secondary p-1" style="font-size:small;">
        <div>{{$t("original_description")}}:</div>
        <div v-if="coreTemplatePartsHelpText(coreTemplateParts[ActiveNode.key])">            
            <div style="white-space: pre-wrap;">{{coreTemplatePartsHelpText(coreTemplateParts[ActiveNode.key])}}</div>
        </div>
        <div v-else>{{$t("na")}}</div>
    </div>
</div>


<div class="form-group mt-2 pb-5" v-if="ActiveNode.key && (ActiveNode.type=='array' || ActiveNode.type=='nested_array')">
    <div><label>{{$t("field_properties")}}:</label></div>
    <props-treeview 
        :key="ActiveNode.key" 
        :parent_node="ActiveNode"
        :parent_type="ActiveNode.type" 
        :parent_key="ActiveNode.key" 
        v-model="getNodeProps(ActiveNode)" 
        :core_props="getNodeProps(coreTemplateParts[ActiveNode.key])"
    >
    </props-treeview>
</div>

<template v-if="ActiveNode.type!=='section_container' && ActiveNode.type!=='section' ">
    <v-tabs background-color="transparent" class="mb-5" :key="ActiveNode.key">
        <v-tab v-if="ActiveNode.key && isControlField(ActiveNode.type) == true">{{$t("display")}}</v-tab>
        <v-tab v-if="!ActiveArrayNodeIsNested"><span v-if="ActiveNodeEnumCount>0"><v-icon style="color:green;">mdi-circle-medium</v-icon></span>{{$t("controlled_vocabulary")}}</v-tab>
        <v-tab v-if="!ActiveArrayNodeIsNested || isControlField(ActiveNode.type) == true"><span v-if="ActiveNode.default"><v-icon style="color:green;">mdi-circle-medium</v-icon></span>{{$t("default")}}</v-tab>
        <v-tab v-if="isControlField(ActiveNode.type)"><span v-if="ActiveNode.rules && Object.keys(ActiveNode.rules).length>0"><v-icon style="color:green;">mdi-circle-medium</v-icon></span>{{$t("validation_rules")}}</v-tab>
        <v-tab>{{$t("json")}}</v-tab>

        <v-tab-item class="p-3 tab-display" v-if="ActiveNode.key && isControlField(ActiveNode.type) == true">
            <!--display-->
            <div class="form-group" v-if="ActiveNode.type!='simple_array'">
                <label >{{$t("data_type")}}:</label>
                <select 
                    v-model="ActiveNode.type" 
                    class="form-control form-field-dropdown" >        
                    <option v-for="field_type in field_data_types">
                        {{field_type}}
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label>{{$t("display")}}:</label>
                <select 
                    v-model="ActiveNode.display_type" 
                    class="form-control form-field-dropdown" >        
                    <option v-for="display_type in field_display_types">
                        {{display_type}}
                    </option>
                </select>
            </div>


            <div class="form-group" v-if="ActiveNode.display_type=='textarea'">
                <label>{{$t("field_content_format")}}:</label>
                <div class="text-secondary font-small">{{$t("field_content_format_help")}}</div>
                <select 
                    v-model="ActiveNode.content_format" 
                    class="form-control form-field-dropdown" >        
                    <option value="">None</option>
                    <option v-for="content_format,format_key in field_content_formats" :value="format_key">
                        {{content_format}}
                    </option>
                </select>
            </div>

            <!--end display -->
        </v-tab-item>

        <v-tab-item class="p-3 tab-cv" v-if="!ActiveArrayNodeIsNested">
            <!-- controlled vocab -->
            <template >
            <div class="form-group" >
                <label for="controlled_vocab">{{$t("controlled_vocabulary")}}:</label>
                <div class="bg-white border " style="max-height:300px;overflow:auto;">


                    <template v-if="!ActiveNodeControlledVocabColumns"> 

                        <div>

                            <div class="m-3">
                                <div>{{$t("enum_store_options_label")}}:</div>

                                <v-select
                                    style="max-width:300px;"
                                    v-model="ActiveNodeEnumStoreColumn"
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
                            :key="ActiveNode.key"
                            :columns="ActiveNodeSimpleControlledVocabColumns" 
                            v-model="ActiveNodeEnum"
                            @update:value="EnumUpdate"
                            class="border m-2 pb-2"
                        ></table-grid-component>
                         
                    </template>
                    <template v-else>

                        <table-grid-component
                            :key="ActiveNode.key"
                            :columns="ActiveNodeControlledVocabColumns" 
                            v-model="ActiveNodeEnum"
                            @update:value="EnumUpdate"
                            class="border m-2 pb-2"
                        ></table-grid-component>
                        
                    </template>
                </div>

            </div>
            </template>
            <!-- end controlled vocab -->
        </v-tab-item>
        <v-tab-item class="p-3 tab-default" v-if="!ActiveArrayNodeIsNested || isControlField(ActiveNode.type) == true">
            <!-- default -->
            <template >
                <div class="form-group" >
                    <label for="controlled_vocab">{{$t("default")}}:</label>
                    <div class="bg-white" style="max-height:300px;overflow:auto;" v-if="ActiveNode.type=='array'">
                        
                        <table-grid-component
                            :key="ActiveNode.key"
                            :columns="ActiveNodeControlledVocabColumns" 
                            v-model="ActiveNode.default"                            
                            class="border m-2 pb-2"
                        ></table-grid-component>

                    </div>
                    <div class="bg-white" v-else>
                        
                        <div v-if="ActiveNode.type=='textarea'">
                            <textarea class="form-control" style="height:200px;" v-model="ActiveNode.default"></textarea>                            
                        </div>
                        <div v-else-if="ActiveNode.type=='boolean'">                           
                            <v-select
                                v-model="ActiveNode.default"
                                :items="['true', 'false']"
                                dense 
                                outlined
                                clearable
                                label="Select"
                            ></v-select>
                        </div>
                        <div v-else>
                            <v-text-field
                                label=""
                                dense 
                                outlined
                                clearable
                                v-model="ActiveNode.default"
                            ></v-text-field>
                        </div>
                    </div>
                </div>
            </template>
            <!-- end default -->
        </v-tab-item>
        <v-tab-item class="p-3 tab-rules" v-if="isControlField(ActiveNode.type) ">
            <div class="form-group" >
                <label for="controlled_vocab">{{$t("validation_rules")}}:</label>
                <div class="bg-white border">
                    <validation-rules-component @update:value="RulesUpdate"  v-model="ActiveNode.rules"  class="m-2 pb-2" />
                </div>
            </div>
        </v-tab-item>

        <v-tab-item class="p-3 tab-json">
            <div class="form-group" >
                <label for="controlled_vocab">{{$t("json")}}:</label>
                <div class="bg-white border">
                    <pre>{{ActiveNode}}</pre>
                </div>
            </div>
        </v-tab-item>
    </v-tabs>

</template>


<div class="form-group" v-if="ActiveNode.type=='section'  || ActiveNode.type=='nested_array'">
    <label for="name">{{$t("available_items")}}:</label>
    <div class="border bg-light">        
    <nada-treeview-field v-model="CoreTreeItems"></nada-treeview-field>
    <?php /* <pre>{{CoreTreeItems}}</pre> */ ?>
    </div>
</div>

<?php /*  [<pre>{{ActiveNode}}</pre>] */ ?>

</div>
<!-- end item -->