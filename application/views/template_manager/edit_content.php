<div v-if="!ActiveNode.key" class="m-3 p-3">Click on an item on sidebar to start editing!</div>

<!--item-->
<div v-if="ActiveNode.key">

<!--section container fields -->
<div class="form-group" v-if="ActiveNode.key && coreTemplateParts[ActiveNode.key]">
    <label for="name">Original label:</label>
    <div class="text-secondary border p-1 font-small">{{coreTemplateParts[ActiveNode.key].title}}</div>
</div>

<div class="form-group">
    <label for="name">Custom label:</label>
    <input type="text" class="form-control" id="name" placeholder="Label" v-model="ActiveNode.title">
</div>

<div class="form-group form-check" v-if="ActiveNode.type!=='section' &&  ActiveNode.type!=='section_container'">
    <input type="checkbox" class="form-check-input" id="required">
    <label class="form-check-label" for="required">Mandatory</label>
</div>

<div class="form-group" v-if="ActiveNode.key && coreTemplateParts[ActiveNode.key]">
    <label for="original_description">Original description:</label>
    <div class="text-secondary border p-1 font-small" style="max-height:150px;">{{coreTemplateParts[ActiveNode.key].help_text}}</div>
</div>

<div class="form-group" v-if="ActiveNode.key">
    <label >Custom description:</label>
    <textarea style="height:200px;" class="form-control"  v-model="ActiveNode.help_text"></textarea>
</div>


<div class="form-group" v-if="ActiveNode.type!=='section_container' && ActiveNode.type!=='section'">
    <label for="controlled_vocab">Controlled vocabulary:</label>
    <div class="border" style="max-height:300px;overflow:auto;">
    <table-component @update:value="EnumUpdate" v-model="ActiveNode.enum" :columns="enum_columns" class="border m-2 pb-2" />
    </div>
</div>

{{ActiveNode}}
<div class="form-group" v-if="ActiveNode.type=='section'  || ActiveNode.type=='nested_array'">
    <label for="name">Available items:</label>
    <div class="border bg-light">        
    <nada-treeview-field v-model="CoreTreeItems"></nada-treeview-field>
    <pre>{{CoreTreeItems}}</pre>
    </div>
</div>

</div>
<!-- end item -->