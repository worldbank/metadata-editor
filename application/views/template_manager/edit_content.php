<!-- Show available section containers when root node is selected -->
<div v-if="ActiveNode && ActiveNode.type === 'template_root'" class="m-3">
    <!-- Template Description Preview Panel -->
    <div class="mb-4 p-3 elevation-2 border" style="background-color: #fff;">
        <h5 class="mb-3 d-flex align-items-center">
            <v-icon class="mr-2" color="primary">mdi-information-outline</v-icon>
            {{$t("description") || "Template Description"}}
        </h5>
        <v-row dense class="pl-3">
            <v-col cols="12" md="6" v-if="user_template_info && user_template_info.uid">
                <div class="mb-2">
                    <strong class="text-secondary" style="font-size: 0.875rem;">{{$t('ID') || 'UID'}}:</strong>
                    <div class="mt-1" style="font-family: monospace; font-size: 0.875rem;">{{user_template_info.uid}}</div>
                </div>
            </v-col>
            <v-col cols="12" md="6" v-if="user_template_info && user_template_info.name">
                <div class="mb-2">
                    <strong class="text-secondary" style="font-size: 0.875rem;">{{$t('name') || 'Name'}}:</strong>
                    <div class="mt-1">{{user_template_info.name}}</div>
                </div>
            </v-col>
            <v-col cols="12" md="6" v-if="user_template_info && user_template_info.created">
                <div class="mb-2">
                    <strong class="text-secondary" style="font-size: 0.875rem;">{{$t('created') || 'Date Created'}}:</strong>
                    <div class="mt-1">{{formatDate(user_template_info.created)}}</div>
                </div>
            </v-col>
            <v-col cols="12" md="6" v-if="user_template_info && user_template_info.changed">
                <div class="mb-2">
                    <strong class="text-secondary" style="font-size: 0.875rem;">{{$t('changed') || 'Date Changed'}}:</strong>
                    <div class="mt-1">{{formatDate(user_template_info.changed)}}</div>
                </div>
            </v-col>
            
            <v-col cols="12" md="6" v-if="user_template_info && user_template_info.data_type">
                <div class="mb-2">
                    <strong class="text-secondary" style="font-size: 0.875rem;">{{$t('type') || 'Type'}}:</strong>
                    <div class="mt-1">{{user_template_info.data_type}}</div>
                </div>
            </v-col>
            <v-col cols="12" md="6" v-if="user_template_info && user_template_info.version">
                <div class="mb-2">
                    <strong class="text-secondary" style="font-size: 0.875rem;">{{$t('version') || 'Version'}}:</strong>
                    <div class="mt-1">{{user_template_info.version}}</div>
                </div>
            </v-col>
            <v-col cols="12" md="6" v-if="user_template_info && user_template_info.lang">
                <div class="mb-2">
                    <strong class="text-secondary" style="font-size: 0.875rem;">{{$t('language') || 'Language'}}:</strong>
                    <div class="mt-1">{{user_template_info.lang}}</div>
                </div>
            </v-col>
            <v-col cols="12" md="6" v-if="user_template_info && user_template_info.organization">
                <div class="mb-2">
                    <strong class="text-secondary" style="font-size: 0.875rem;">{{$t('organisation') || 'Organization'}}:</strong>
                    <div class="mt-1">{{user_template_info.organization}}</div>
                </div>
            </v-col>
            <v-col cols="12" md="6" v-if="user_template_info && user_template_info.author">
                <div class="mb-2">
                    <strong class="text-secondary" style="font-size: 0.875rem;">{{$t('author') || 'Author'}}:</strong>
                    <div class="mt-1">{{user_template_info.author}}</div>
                </div>
            </v-col>
            <v-col cols="12" v-if="user_template_info && user_template_info.description">
                <div class="mb-2">
                    <strong class="text-secondary" style="font-size: 0.875rem;">{{$t('description') || 'Description'}}:</strong>
                    <div class="mt-1" style="max-height: 200px; overflow-y: auto;background:#dee2e6;padding:10px;white-space: pre-wrap;">{{user_template_info.description}}</div>
                </div>
            </v-col>
            <!--
            <v-col cols="12" v-if="user_template_info && user_template_info.instructions">
                <div class="mb-2">
                    <strong class="text-secondary" style="font-size: 0.875rem;">{{$t('instructions') || 'Instructions'}}:</strong>
                    <div class="mt-1" style="white-space: pre-wrap;">{{user_template_info.instructions}}</div>
                </div>
            </v-col>
            -->
        </v-row>
    </div>

    <div v-if="MissingSectionContainers && MissingSectionContainers.length > 0" class="mb-3 p-2 elevation-2 border" style="background-color: #fff;">
        <label for="name" class="mb-2 d-block">
            
            <v-icon color="warning">mdi-alert-circle</v-icon>
            <strong>{{$t("missing_section_containers")}}:</strong>
        </label>
        <div class="text-secondary font-small mb-2">{{$t("add_missing_section_containers_help")}}</div>
        <div class="border bg-light p-3">        
            <div v-for="container in MissingSectionContainers" :key="container.key" class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-2" style="padding: 8px;">
                <div class="flex-grow-1">
                    <strong>{{container.title || container.key}}</strong>
                    <div class="text-secondary font-small" style="font-size: 0.875rem;">{{container.key}}</div>
                    <div v-if="container.help_text" class="text-secondary font-small mt-1" style="font-size: 0.75rem;">{{container.help_text}}</div>
                </div>
                <div>
                    <v-icon 
                        v-if="!isItemInUse(container.key) && user_has_edit_access"
                        color="#007bff" 
                        @click="addSectionContainer(container)"
                        style="cursor: pointer;"
                        title="Add section container"
                    >
                        mdi-plus-box
                    </v-icon>
                    <v-icon 
                        v-else
                        color="grey"
                        title="Already added or no edit access"
                    >
                        mdi-checkbox-marked
                    </v-icon>
                </div>
            </div>
        </div>
    </div>

    <div v-if="InvalidTemplateKeys && InvalidTemplateKeys.length > 0" class="mb-3 p-2 elevation-2 border" style="background-color: #fff;">
        <label class="mb-2 d-block">
            <v-icon color="warning">mdi-alert-circle</v-icon>
            <strong>{{$t("invalid_template_keys")}}:</strong>
        </label>
        <div class="text-secondary font-small mb-2">{{$t("invalid_template_keys_help")}}</div>
        <div class="border bg-light p-3">
            <div
                v-for="issue in InvalidTemplateKeys"
                :key="issue.key + ':' + (issue.prop_key || '')"
                class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-2"
                style="padding: 8px; cursor: pointer;"
                @click="selectTemplateNodeByKey(issue.select_key)"
            >
                <div class="flex-grow-1">
                    <strong>{{issue.title || issue.key}}</strong>
                    <div class="text-secondary font-small" style="font-size: 0.875rem;">{{issue.key}}</div>
                    <div class="text-danger font-small mt-1" style="font-size: 0.75rem;">{{issue.message}}</div>
                </div>
                <div>
                    <v-icon color="#007bff" title="Open field">mdi-chevron-right</v-icon>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Show description editing when description node is selected -->
<div v-if="ActiveNode && ActiveNode.type === 'template_description'" class="pl-4 pt-2">
    <h5>{{$t('description')}}</h5>

    <div class="mb-3">
      <label class="mb-1 d-block">{{$t('type')}}:</label>
      <v-text-field
        v-model="user_template_info.data_type"
        disabled
        outlined
        dense
        hide-details
      ></v-text-field>
    </div>

    <div class="mb-3">
      <label class="mb-1 d-block">{{$t('language')}}:</label>
      <v-text-field
        v-model="user_template_info.lang"
        placeholder="EN"
        maxlength="30"
        outlined
        dense
        hide-details
        :disabled="!user_has_edit_access"
      ></v-text-field>
    </div>

    <div class="mb-3">
      <label class="mb-1 d-block">{{$t('name')}}:</label>
      <v-text-field
        v-model="user_template_info.name"
        maxlength="150"
        outlined
        dense
        hide-details
        :disabled="!user_has_edit_access"
      ></v-text-field>
    </div>

    <div class="mb-3">
      <label class="mb-1 d-block">{{$t('version')}}:</label>
      <v-text-field
        v-model="user_template_info.version"
        maxlength="50"
        outlined
        dense
        hide-details
        :disabled="!user_has_edit_access"
      ></v-text-field>
    </div>

    <div class="mb-3">
      <label class="mb-1 d-block">{{$t('organisation')}}:</label>
      <v-text-field
        v-model="user_template_info.organization"
        maxlength="150"
        outlined
        dense
        hide-details
        :disabled="!user_has_edit_access"
      ></v-text-field>
    </div>

    <div class="mb-3">
      <label class="mb-1 d-block">{{$t('author')}}:</label>
      <v-text-field
        v-model="user_template_info.author"
        maxlength="150"
        outlined
        dense
        hide-details
        :disabled="!user_has_edit_access"
      ></v-text-field>
    </div>

    <div class="mb-3">
      <label class="mb-1 d-block">{{$t('description')}}:</label>
      <v-textarea
        v-model="user_template_info.description"
        maxlength="1000"
        outlined
        rows="8"
        hide-details
        :disabled="!user_has_edit_access"
      ></v-textarea>
    </div>

    <div class="mb-3">
      <label class="mb-1 d-block">{{$t('instructions')}}: </label>
      <span style="font-size:12px;color:gray">Markdown<a href="https://www.markdownguide.org/cheat-sheet/" target="_blannk"><v-icon style="font-size:14px;">mdi-open-in-new</v-icon> </a></span>
      <v-textarea
        v-model="user_template_info.instructions"
        outlined
        rows="12"
        hide-details
        class="mt-2"
        :disabled="!user_has_edit_access"
      ></v-textarea>
    </div>
</div>

<!--key editing / display-->
    <div class="mb-3" v-if="ActiveNode && (ActiveNode.key || ActiveNode.prop_key) && !ActiveNodeIsProp && ActiveNode.type !== 'template_root' && ActiveNode.type !== 'template_description'">

        <vue-custom-key-field
            :field="ActiveNode" 
            :key="ActiveNode.key"
            :value="ActiveNode.key"
            @input="UpdateActiveNodeKey"
            >
        </vue-custom-key-field>
    </div>


<!--item-->
<div v-if="ActiveNode && (ActiveNode.key || ActiveNode.prop_key) && ActiveNode.type !== 'template_root' && ActiveNode.type !== 'template_description'">

<!--section container fields - only show for non-prop nodes -->
<div v-if="ActiveNode && !ActiveNodeIsProp" class="mb-3">
    <label class="mb-1 d-block">{{$t('label')}}:</label>
    <v-text-field
        v-model="ActiveNode.title"
        @input="markDirty"
        placeholder="Label"
        outlined
        dense
        hide-details
        :disabled="!user_has_edit_access"
    ></v-text-field>
</div>
<div v-if="ActiveNode && !ActiveNodeIsProp && ActiveNode.key && coreTemplateParts[ActiveNode.key]" class="text-secondary font-small mb-3" style="font-size:small">Original label: {{coreTemplateParts[ActiveNode.key].title}} <span class="pl-3">Name: {{ActiveNode.key}}</span> <span class="pl-3">Type: {{ActiveNode.type}}</span>  </div>

<div v-if="ActiveNode && !ActiveNodeIsProp && (ActiveNode.type=='section' || ActiveNode.type=='section_container' || ActiveNode.type=='nested_array')" class="mb-3">
    <label class="mb-1 d-block">{{$t('type')}}:</label>
    <v-text-field
        v-model="ActiveNode.type"
        disabled
        outlined
        dense
        hide-details
    ></v-text-field>
</div>
<div v-else-if="ActiveNode && !ActiveNodeIsProp" class="mb-3">
    <label class="mb-1 d-block">{{$t('type')}}:</label>
    <v-select
        v-model="ActiveNode.type"
        @change="markDirty"
        :items="field_types"
        outlined
        dense
        hide-details
        :disabled="!user_has_edit_access"
    ></v-select>
</div>

<v-row v-if="ActiveNode && !ActiveNodeIsProp" class="mb-3">
    <v-col cols="auto">
        <v-checkbox
            v-if="ActiveNode.type!=='section' &&  ActiveNode.type!=='section_container'"
            v-model="ActiveNode.is_required"
            @change="markDirty"
            :label="$t('required')"
            hide-details
            :disabled="!user_has_edit_access"
        ></v-checkbox>
    </v-col>

    <v-col cols="auto">
        <v-checkbox
            v-if="ActiveNode.type!=='section' &&  ActiveNode.type!=='section_container'"
            v-model="ActiveNode.is_recommended"
            @change="markDirty"
            :label="$t('recommended')"
            hide-details
            :disabled="!user_has_edit_access"
        ></v-checkbox>
    </v-col>

    <v-col cols="auto">
        <v-checkbox
            v-if="ActiveNode.type!=='section' &&  ActiveNode.type!=='section_container'"
            v-model="ActiveNode.is_private"
            @change="markDirty"
            :label="$t('private')"
            hide-details
            :disabled="!user_has_edit_access"
        ></v-checkbox>
    </v-col>

    <v-col cols="auto">
        <v-checkbox
            v-if="ActiveNode.type!=='section' &&  ActiveNode.type!=='section_container'"
            v-model="ActiveNode.is_readonly"
            @change="markDirty"
            :label="$t('readonly')"
            hide-details
            :disabled="!user_has_edit_access"
        ></v-checkbox>
    </v-col>
</v-row>

<div v-if="ActiveNode && (ActiveNode.key || ActiveNode.prop_key) && !ActiveNodeIsProp" class="mb-3">
    <label class="mb-1 d-block">{{$t('description')}}:</label>
    <v-textarea
        v-model="ActiveNode.help_text"
        @input="markDirty"
        outlined
        rows="8"
        hide-details
        :disabled="!user_has_edit_access"
    ></v-textarea>
</div>
<div v-if="ActiveNode && (ActiveNode.key || ActiveNode.prop_key) && !ActiveNodeIsProp && ActiveNode.key" class="text-secondary p-1 mb-3" style="font-size:small;">
    <div>{{$t("original_description")}}:</div>
    <div v-if="coreTemplatePartsHelpText(coreTemplateParts[ActiveNode.key])">            
        <div style="white-space: pre-wrap;">{{coreTemplatePartsHelpText(coreTemplateParts[ActiveNode.key])}}</div>
    </div>
    <div v-else>{{$t("na")}}</div>
</div>


<!-- Removed props-treeview - all properties are now accessible from main sidebar tree -->

<!-- Show prop editing interface when a prop is selected from main tree -->
<!-- Only show prop-edit if this is actually a prop (inside an array's props array), not just a field with prop_key -->
<div class="mt-2 pb-5" v-if="ActiveNode && ActiveNode.prop_key && ActiveNodeIsProp">
    <!-- Use prop-edit component for individual prop editing -->
    <prop-edit 
        :key="ActiveNode.prop_key" 
        :parent="propParentNode"
        v-model="ActiveNode"
    ></prop-edit>
</div>

<template v-if="ActiveNode && ActiveNode.type!=='section_container' && ActiveNode.type!=='section' && !ActiveNodeIsProp && !ActiveNodeIsInsideNestedArray && ActiveNode.key">
    <v-tabs background-color="transparent" class="mb-5" :key="ActiveNode.key">
        <v-tab v-if="ActiveNode.key && isControlField(ActiveNode.type) == true">{{$t("display")}}</v-tab>
        <v-tab v-if="!ActiveArrayNodeIsNested"><span v-if="ActiveNodeEnumCount>0"><v-icon style="color:green;">mdi-circle-medium</v-icon></span>{{$t("controlled_vocabulary")}}</v-tab>
        <v-tab v-if="!ActiveArrayNodeIsNested || (ActiveNode && isControlField(ActiveNode.type) == true)"><span v-if="ActiveNode && ActiveNode.default"><v-icon style="color:green;">mdi-circle-medium</v-icon></span>{{$t("default")}}</v-tab>
        <v-tab v-if="ActiveNode && isControlField(ActiveNode.type)"><span v-if="ActiveNode && ActiveNode.rules && Object.keys(ActiveNode.rules).length>0"><v-icon style="color:green;">mdi-circle-medium</v-icon></span>{{$t("validation_rules")}}</v-tab>
        <v-tab>{{$t("json")}}</v-tab>

        <v-tab-item class="p-3 tab-display" v-if="ActiveNode.key && isControlField(ActiveNode.type) == true">
            <!--display-->
            <div v-if="ActiveNode.type!='simple_array'" class="mb-3">
                <label class="mb-1 d-block">{{$t('data_type')}}:</label>
                <v-select
                    v-model="ActiveNode.type"
                    @change="markDirty"
                    :items="field_data_types"
                    outlined
                    dense
                    hide-details
                    :disabled="!user_has_edit_access"
                ></v-select>
            </div>

            <div class="mb-3">
                <label class="mb-1 d-block">{{$t('display')}}:</label>
                <v-select
                    v-model="ActiveNode.display_type"
                    @change="markDirty"
                    :items="field_display_types"
                    outlined
                    dense
                    hide-details
                    :disabled="!user_has_edit_access"
                ></v-select>
            </div>

            <div v-if="ActiveNode.display_type=='textarea'" class="mb-3">
                <label class="mb-1 d-block">{{$t("field_content_format")}}:</label>
                <div class="text-secondary font-small mb-2">{{$t("field_content_format_help")}}</div>
                <v-select
                    v-model="ActiveNode.content_format"
                    @change="markDirty"
                    :items="[{ value: '', text: 'None' }, ...Object.keys(field_content_formats).map(key => ({ value: key, text: field_content_formats[key] }))]"
                    item-text="text"
                    item-value="value"
                    outlined
                    dense
                    hide-details
                    :disabled="!user_has_edit_access"
                ></v-select>
            </div>

            <!--end display -->
        </v-tab-item>

        <v-tab-item class="p-3 tab-cv" v-if="!ActiveArrayNodeIsNested">
            <!-- controlled vocab -->
            <template >
            <div class="mb-3" >
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
                                    :disabled="!user_has_edit_access"
                                ></v-select>
                            </div>
                        </div>

                        <table-grid-component
                            v-if="ActiveNode && ActiveNode.key"
                            :key="ActiveNode.key"
                            :columns="ActiveNodeSimpleControlledVocabColumns" 
                            v-model="ActiveNodeEnum"
                            @update:value="EnumUpdate"
                            class="border m-2 pb-2"
                        ></table-grid-component>
                         
                    </template>
                    <template v-else>

                        <table-grid-component
                            v-if="ActiveNode && ActiveNode.key"
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
        <v-tab-item class="p-3 tab-default" v-if="!ActiveArrayNodeIsNested || (ActiveNode && isControlField(ActiveNode.type) == true)">
            <!-- default -->
            <template >
                <div class="mb-3" >
                    <label for="controlled_vocab">{{$t("default")}}:</label>
                    <div class="bg-white" style="max-height:300px;overflow:auto;" v-if="ActiveNode && ActiveNode.type=='array'">
                        
                        <table-grid-component
                            v-if="ActiveNode && ActiveNode.key"
                            :key="ActiveNode.key"
                            :columns="ActiveNodeControlledVocabColumns" 
                            v-model="ActiveNode.default"                            
                            class="border m-2 pb-2"
                        ></table-grid-component>

                    </div>
                    <div class="bg-white" v-else>
                        
                        <v-textarea
                            v-if="ActiveNode && ActiveNode.type=='textarea'"
                            v-model="ActiveNode.default"
                            outlined
                            rows="8"
                            hide-details
                            class="mt-2"
                            :disabled="!user_has_edit_access"
                        ></v-textarea>
                        <v-select
                            v-else-if="ActiveNode && ActiveNode.type=='boolean'"
                            v-model="ActiveNode.default"
                            :items="['true', 'false']"
                            dense 
                            outlined
                            clearable
                            hide-details
                            class="mt-2"
                            :disabled="!user_has_edit_access"
                        ></v-select>
                        <v-text-field
                            v-else
                            dense 
                            outlined
                            clearable
                            v-model="ActiveNode.default"
                            hide-details
                            class="mt-2"
                            :disabled="!user_has_edit_access"
                        ></v-text-field>
                    </div>
                </div>
            </template>
            <!-- end default -->
        </v-tab-item>
        <v-tab-item class="p-3 tab-rules" v-if="ActiveNode && isControlField(ActiveNode.type)">
            <div class="mb-3" >
                <label for="controlled_vocab">{{$t("validation_rules")}}:</label>
                <div class="bg-white border">
                    <validation-rules-component @update:value="RulesUpdate"  v-model="ActiveNode.rules"  class="m-2 pb-2" />
                </div>
            </div>
        </v-tab-item>

        <v-tab-item class="p-3 tab-json">
            <div class="mb-3" >
                <label for="controlled_vocab">{{$t("json")}}:</label>
                <div class="bg-white border" :style="ActiveNode && ActiveNode.type === 'nested_array' ? 'max-height: 300px; overflow-y: auto;' : ''">
                    <pre>{{ActiveNode}}</pre>
                </div>
            </div>
        </v-tab-item>
    </v-tabs>

</template>


<div class="mb-3 p-2 elevation-2" v-if="ActiveNode && (ActiveNode.type=='section' || ActiveNode.type=='array' || ActiveNode.type=='nested_array')">
    <label for="name">{{$t("available_items")}}:</label>
    <div class="border bg-light">        
    <nada-treeview-field v-model="CoreTreeItems"></nada-treeview-field>
    <?php /* <pre>{{CoreTreeItems}}</pre> */ ?>
    </div>
</div>

<?php /*  [<pre>{{ActiveNode}}</pre>] */ ?>

</div>
<!-- end item -->