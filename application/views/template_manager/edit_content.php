<div v-if="!ActiveNode.key" class="m-3 p-3">Click on an item on sidebar to start editing!</div>

<!--item-->
<div v-if="ActiveNode.key">

<!--section container fields -->
<div class="form-group">
    <label for="name">Label:</label>
    <input type="text" class="form-control" id="name" placeholder="Label" v-model="ActiveNode.title">
    <div v-if="ActiveNode.key && coreTemplateParts[ActiveNode.key]" class="text-secondary font-small" style="margin-top:4px;font-size:small">Original label: {{coreTemplateParts[ActiveNode.key].title}} <span class="pl-3">Name: {{ActiveNode.key}}</span> <span class="pl-3">Type: {{ActiveNode.type}}</span>  </div>
</div>

<div class="form-group">
    <label for="name">Type:</label>
    <input type="text" class="form-control" id="name" placeholder="Label" v-model="ActiveNode.type">    
</div>

<div class="row">
    <div class="col-auto">
        <div class="form-group form-check" v-if="ActiveNode.type!=='section' &&  ActiveNode.type!=='section_container'">
            <input type="checkbox" class="form-check-input" id="required" v-model="ActiveNode.is_required" >
            <label class="form-check-label" for="required">Required</label>
        </div>
    </div>

    <div class="col-auto">
        <div class="form-group form-check" v-if="ActiveNode.type!=='section' &&  ActiveNode.type!=='section_container'">
            <input type="checkbox" class="form-check-input" id="recommended" v-model="ActiveNode.is_recommended">
            <label class="form-check-label" for="recommended">Recommended</label>
        </div>
    </div>
</div>

<div class="form-group mb-3" v-if="ActiveNode.key && ActiveNode.type!='section' && ActiveNode.type!='section_container'">
    <label >Description:</label>
    <textarea style="height:200px;" class="form-control"  v-model="ActiveNode.help_text"></textarea>
    <div class="text-secondary p-1" style="font-size:small;">
        <div>Original description:</div>
        <div v-if="coreTemplatePartsHelpText(coreTemplateParts[ActiveNode.key])">            
            <div style="white-space: pre-wrap;">{{coreTemplatePartsHelpText(coreTemplateParts[ActiveNode.key])}}</div>
        </div>
        <div v-else>N/A</div>
    </div>
</div>


<div class="form-group mt-2 pb-5" v-if="ActiveNode.key && ActiveNode.props">
    <div><label>Field properties:</label></div>
    <props-treeview :key="ActiveNode.key" :parent_key="ActiveNode.key" v-model="ActiveNode.props" :core_props="coreTemplateParts[ActiveNode.key].props"></props-treeview>
</div>

<template v-if="ActiveNode.type!=='section_container' && ActiveNode.type!=='section'">
    <v-tabs background-color="transparent" class="mb-5">
        <v-tab v-if="ActiveNode.key && isControlField(ActiveNode.type) == true">Display</v-tab>
        <v-tab><span v-if="ActiveNodeEnumCount>0"><v-icon style="color:green;">mdi-circle-medium</v-icon></span>Controlled vocabulary</v-tab>
        <v-tab><span v-if="ActiveNode.default"><v-icon style="color:green;">mdi-circle-medium</v-icon></span>Default</v-tab>
        <v-tab v-if="ActiveNode.type!=='array'"><span v-if="ActiveNode.rules && Object.keys(ActiveNode.rules).length>0"><v-icon style="color:green;">mdi-circle-medium</v-icon></span>Validation rules</v-tab>

        <v-tab-item class="p-3" v-if="ActiveNode.key && isControlField(ActiveNode.type) == true">
            <!--display-->
            <div class="form-group">
                <label >Display:</label>
                <div class="text-secondary">UI control to use for editing field value</div>
                <select 
                    v-model="ActiveNode.type" 
                    class="form-control form-field-dropdown" >        
                    <option v-for="field_type in field_types">
                        {{field_type}}
                    </option>
                </select>

                <div v-if="ActiveNode.type=='date'">
                    <pre>{{ActiveNode}}</pre>
                </div>
            </div>
            <!--end display -->
        </v-tab-item>

        <v-tab-item class="p-3">
            <!-- controlled vocab -->
            <template v-if="ActiveNode.type!=='section_container' && ActiveNode.type!=='section' ">
            <div class="form-group" >
                <label for="controlled_vocab">Controlled vocabulary:</label>
                <div class="border bg-white" style="max-height:300px;overflow:auto;">
                    <template v-if="!ActiveNodeControlledVocabColumns">
                        <list-component :key="ActiveNode.key" @update:value="EnumListUpdate" v-model="ActiveNode.enum" :columns="ActiveNodeControlledVocabColumns" class="border m-2 pb-2"/>
                    </template>
                    <template v-else>
                        <table-component :key="ActiveNode.key"  @update:value="EnumUpdate" v-model="ActiveNode.enum" :columns="ActiveNodeControlledVocabColumns" class="border m-2 pb-2" />
                    </template>
                </div>

            </div>
            </template>
            <!-- end controlled vocab -->
        </v-tab-item>
        <v-tab-item class="p-3">
            <!-- default -->
            <template v-if="ActiveNode.type!=='section_container' && ActiveNode.type!=='section'">
                <div class="form-group" >
                    <label for="controlled_vocab">Default:</label>
                    <div class="border bg-white" style="max-height:300px;overflow:auto;" v-if="ActiveNode.type=='array'">
                        <table-component @update:value="DefaultUpdate" v-model="ActiveNode.default" :columns="ActiveNodeControlledVocabColumns" class="border m-2 pb-2" />
                    </div>
                    <div class="border bg-white" v-else>
                        <div v-if="ActiveNode.type=='string' || ActiveNode.type=='text' || ActiveNode.type=='dropdown' ">
                            <input class="form-control" type="text" v-model="ActiveNode.default"/>
                        </div>
                        <div v-else-if="ActiveNode.type=='textarea'">
                            <textarea class="form-control" style="height:200px;" v-model="ActiveNode.default"></textarea>
                        </div>
                    </div>
                </div>
            </template>
            <!-- end default -->
        </v-tab-item>
        <v-tab-item class="p-3" v-if="ActiveNode.type!=='array'">
            <div class="form-group" >
                <label for="controlled_vocab">Validation rules:</label>
                <div class="bg-white border">
                    <validation-rules-component @update:value="RulesUpdate"  v-model="ActiveNode.rules"  class="m-2 pb-2" />
                </div>
            </div>
        </v-tab-item>
    </v-tabs>

</template>





<div class="form-group" v-if="ActiveNode.type=='section'  || ActiveNode.type=='nested_array'">
    <label for="name">Available items:</label>
    <div class="border bg-light">        
    <nada-treeview-field v-model="CoreTreeItems"></nada-treeview-field>
    <?php /* <pre>{{CoreTreeItems}}</pre> */ ?>
    </div>
</div>

<?php /*  [<pre>{{ActiveNode}}</pre>] */ ?>

</div>
<!-- end item -->