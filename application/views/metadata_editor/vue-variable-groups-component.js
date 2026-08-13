Vue.component('variable-groups', {    
    data() {
        return {            
            project_id:project_sid,
            project_idno:project_idno,
            project_type:project_type,
            showDialog:false,            
            activeItem: null,
            treeActiveItem:[],
            treeItemOpen:['-1'],            
            tree:[],
            conceptColumns:{
                "key": "variable_groups.concepts",
                "title": "Concepts",
                "type": "array",
                "props": [
                    {
                    "key": "concept",
                    "title": "Concept",
                    "type": "string",
                    "prop_key": "study_desc.study_info.keywords.keyword",
                    "help_text": "A keyword (or phrase).",
                    "display_type": "text"
                    },
                    {
                    "key": "vocab",
                    "title": "Vocabulary",
                    "type": "string",
                    "prop_key": "study_desc.study_info.keywords.vocab",
                    "help_text": "The controlled vocabulary from which the keyword is extracted, if any.",
                    "display_type": "text"
                    },
                    {
                    "key": "uri",
                    "title": "URL",
                    "type": "string",
                    "prop_key": "study_desc.study_info.keywords.uri",
                    "help_text": "The URL of the controlled vocabulary from which the keyword is extracted, if any.",
                    "rules": {
                    "is_uri": true
                    },
                    "display_type": "text"
                    }
                ]                        
            },
            custom_fields:[ "variable_groups.variables", "variable_groups.variable_groups"],
            is_saving:false,
            is_dirty:false,
            savedSnapshot:null,
            variables_loading:false,
            variables_load_attempted:false
        }
    }, 
    mounted: function () {
        this.treeItemOpen.push('-1');
        this.savedSnapshot = JSON.stringify(this.VariableGroups || []);
        this.ensureAllVariablesLoaded();
        window.addEventListener('beforeunload', this.handleBeforeUnload);
    },
    beforeDestroy: function () {
        window.removeEventListener('beforeunload', this.handleBeforeUnload);
    },
    beforeRouteLeave: function (to, from, next) {
        if (!this.showUnsavedMessage()){
            next(false);
            return;
        }
        next();
    },
    beforeRouteUpdate: function (to, from, next) {
        if (!this.showUnsavedMessage()){
            next(false);
            return;
        }
        next();
    },
    watch: {       
        'VariableGroups': {
            deep: true,
            handler: function () {
                this.syncDirty();
            }
        },
        treeActiveItem: function (val) {
            if (!val || !val.length){
                return;
            }
            this.setActiveByVgid(val[0]);
        },
        ProjectDataFiles: function () {
            this.ensureAllVariablesLoaded();
        }
    }, 
    methods: {
        showUnsavedMessage: function () {
            if (this.is_dirty){
                if (!confirm(this.$t("confirm_unsaved_changes"))){
                    return false;
                }
            }
            return true;
        },
        handleBeforeUnload: function (event) {
            if (!this.is_dirty){
                return;
            }
            event.preventDefault();
            event.returnValue = this.$t("confirm_unsaved_changes");
            return event.returnValue;
        },
        syncDirty: function () {
            if (this.savedSnapshot === null){
                this.savedSnapshot = JSON.stringify(this.VariableGroups || []);
                return;
            }
            this.is_dirty = JSON.stringify(this.VariableGroups || []) !== this.savedSnapshot;
        },
        markSaved: function () {
            this.savedSnapshot = JSON.stringify(this.VariableGroups || []);
            this.is_dirty = false;
        },
        uidInList: function (list, uid) {
            if (!list){
                return false;
            }
            for (var i = 0; i < list.length; i++){
                if (list[i] == uid){
                    return true;
                }
            }
            return false;
        },
        ensureAllVariablesLoaded: async function () {
            var vm = this;
            var dataFiles = vm.$store.state.data_files || [];
            if (dataFiles.length === 0) {
                return;
            }
            var variables = vm.$store.state.variables || {};
            var needsLoad = dataFiles.some(function (file) {
                return !Array.isArray(variables[file.file_id]);
            });
            if (!needsLoad || vm.variables_load_attempted || vm.variables_loading) {
                return;
            }
            vm.variables_loading = true;
            vm.variables_load_attempted = true;
            try {
                await vm.$store.dispatch('loadAllVariables', { dataset_id: vm.$store.state.project_id });
            } catch (e) {
                console.error('Failed to load variables for variable groups', e);
                vm.variables_load_attempted = false;
            } finally {
                vm.variables_loading = false;
            }
        },
        openVariableDialog: async function () {
            var dataFiles = this.$store.state.data_files || [];
            var variables = this.$store.state.variables || {};
            var hasAny = dataFiles.some(function (file) {
                return Array.isArray(variables[file.file_id]) && variables[file.file_id].length > 0;
            });
            if (!hasAny) {
                this.variables_load_attempted = false;
            }
            await this.ensureAllVariablesLoaded();
            this.showDialog = true;
        },
        treeClick: function(item){
            this.activeItem = item;
        },
        setActiveByVgid: function (vgid) {
            if (vgid == -1){
                this.activeItem = this.treeItems[0];
                return;
            }
            var found = this.findGroupByVgid(vgid);
            if (found){
                this.activeItem = found;
            }
        },
        findGroupByVgid: function (vgid) {
            var found = null;
            var walk = function (items) {
                if (!items || found){
                    return;
                }
                for (var i = 0; i < items.length; i++){
                    if (items[i].vgid == vgid){
                        found = items[i];
                        return;
                    }
                    walk(items[i].variable_groups);
                }
            };
            walk(this.VariableGroups);
            return found;
        },
        findParentListAndIndex: function (vgid) {
            var found = null;
            var search = function (list) {
                if (!list || found){
                    return;
                }
                for (var i = 0; i < list.length; i++){
                    if (list[i].vgid == vgid){
                        found = { list: list, index: i };
                        return;
                    }
                    search(list[i].variable_groups);
                }
            };
            search(this.VariableGroups);
            return found;
        },
        newGroupPayload: function () {
            return {
                "vgid": 'VG' + (this.getMaxVgId() + 1),
                "group_type": "pragmatic",
                "label": this.$t("new_variable_group"),
                "variables": [],
                "variable_groups": []
            };
        },
        addGroup: function(){
            var newGroup = this.newGroupPayload();
            var addToRoot = !this.activeItem || this.activeItem.vgid == -1;

            if (addToRoot){
                this.VariableGroups.push(newGroup);
            } else {
                if (!this.activeItem.variable_groups){
                    this.$set(this.activeItem, 'variable_groups', []);
                }
                this.activeItem.variable_groups.push(newGroup);
                if (this.treeItemOpen.indexOf(this.activeItem.vgid) === -1){
                    this.treeItemOpen.push(this.activeItem.vgid);
                }
            }

            this.activeItem = newGroup;
            this.treeActiveItem = [newGroup.vgid];
        },
        removeGroup: function(){
            if (!this.canRemoveGroup){
                return;
            }
            if (!confirm(this.$t("delete_variable_group_confirm"))){
                return;
            }
            this.removeGroupByVGID(this.activeItem.vgid);
            this.activeItem = null;
            this.treeActiveItem = ['-1'];
        },
        removeGroupByVGID: function(vgid){
            var loc = this.findParentListAndIndex(vgid);
            if (loc){
                loc.list.splice(loc.index, 1);
            }
        },
        moveGroup: function (delta) {
            if (!this.activeItem || this.activeItem.vgid == -1){
                return;
            }
            var loc = this.findParentListAndIndex(this.activeItem.vgid);
            if (!loc){
                return;
            }
            var newIndex = loc.index + delta;
            if (newIndex < 0 || newIndex >= loc.list.length){
                return;
            }
            var item = loc.list.splice(loc.index, 1)[0];
            loc.list.splice(newIndex, 0, item);
            this.activeItem = item;
            this.treeActiveItem = [item.vgid];
        },
        removeVariable: function(uid){
            if (!this.activeItem || !this.activeItem.variables){
                return;
            }
            if (!confirm(this.$t("remove_variable_from_group_confirm"))){
                return;
            }

            var vars = this.activeItem.variables;
            for (var i = vars.length - 1; i >= 0; i--){
                if (vars[i] == uid){
                    vars.splice(i, 1);
                }
            }
        },

        getMaxVgId: function(){
            var max = 0;
            var findMax = function(items){
                if (!items){
                    return;
                }
                for (var i = 0; i < items.length; i++){
                    var item = items[i];
                    if (item && item.vgid){
                        var match = String(item.vgid).match(/^VG(\d+)$/i);
                        if (match){
                            var n = parseInt(match[1], 10);
                            if (n > max){
                                max = n;
                            }
                        }
                    }
                    if (item && item.variable_groups){
                        findMax(item.variable_groups);
                    }
                }
            };
            findMax(this.VariableGroups);
            return max;
        },
        saveVariableGroups: function()
        {
            if (this.is_saving){
                return;
            }
            var vm = this;
            vm.is_saving = true;
            var url = CI.base_url + '/api/variable_groups/' + vm.project_id;
            var form_data = {
                'variable_groups': this.VariableGroups
            };

            axios.post(url, form_data)
            .then(function () {
                vm.markSaved();
                EventBus.$emit('onSuccess', vm.$t("saved"));
            })
            .catch(function (error) {
                EventBus.$emit('onFail', vm.$t("failed_to_save_changes") || 'Failed to save changes');
                var message = '';
                if (error.response && error.response.data && error.response.data.message){
                    message = error.response.data.message;
                }else{
                    message = error.message;
                }
                alert(vm.$t("failed") + ": " + message);
            })
            .then(function () {
                vm.is_saving = false;
            });
        },
        OnVariableSelection: function(selected){
            this.showDialog = false;
            if (!this.activeItem || !selected || !selected.length){
                return;
            }
            if (!this.activeItem.variables){
                this.$set(this.activeItem, 'variables', []);
            }
            var existing = this.activeItem.variables;
            for (var i = 0; i < selected.length; i++){
                if (!this.uidInList(existing, selected[i])){
                    existing.push(selected[i]);
                }
            }
        },
        findTemplateByItemKey: function (items,key){
            let item=null;
            let found=false;
            let i=0;

            if (!items){
                return null;
            }

            while(!found && i<items.length){
                
                if (items[i].key==key){
                    item=items[i];
                    found=true;
                }else{
                    if (items[i].items){
                        item=this.findTemplateByItemKey(items[i].items,key);
                        if (item){
                            found=true;
                        }
                    }
                }
                i++;                        
            }
            return item;
        },
        update: function (key, value)
        {
            key=key.replace('variable_groups.','');
            if (key.indexOf(".") !== -1 && this.activeItem[key]){
                delete this.activeItem[key];
            }
            Vue.set(this.activeItem,key,value);
        },
        updateSection: function (obj)
        {
            this.update(obj.key,obj.value);
        },

        localValue: function(key)
        {
            key=key.replace('variable_groups.','');
            return _.get(this.activeItem,key);
        },
    },
    computed: {
        ProjectDataFiles(){
            return this.$store.state.data_files || [];
        },
        treeItems(){
            return [
                {
                    'vgid': -1,
                    'label': this.$t("variable_groups"),
                    'variable_groups': this.VariableGroups
                }
            ];
        },
        VariableGroups(){
            if (!Array.isArray(this.$store.state.variable_groups)){
                this.$store.state.variable_groups = [];
            }
            return this.$store.state.variable_groups;
        },
        canRemoveGroup(){
            return this.activeItem && this.activeItem.vgid != -1;
        },
        canMoveUp(){
            if (!this.canRemoveGroup){
                return false;
            }
            var loc = this.findParentListAndIndex(this.activeItem.vgid);
            return loc && loc.index > 0;
        },
        canMoveDown(){
            if (!this.canRemoveGroup){
                return false;
            }
            var loc = this.findParentListAndIndex(this.activeItem.vgid);
            return loc && loc.index < loc.list.length - 1;
        },
        ActiveItemVariables(){
            if (this.activeItem && this.activeItem.variables){
                return this.activeItem.variables;
            }
            return [];
        },
        Variables(){
            var $variablesByFile = this.$store.getters.getVariablesAll;
            if (!$variablesByFile || typeof $variablesByFile !== 'object'){
                return [];
            }

            var ActiveItemVariables = this.ActiveItemVariables;
            var $variables = [];

            for (var $file in $variablesByFile){
                if (!Array.isArray($variablesByFile[$file])){
                    continue;
                }
                for (var $i = 0; $i < $variablesByFile[$file].length; $i++){
                    var $variable = $variablesByFile[$file][$i];
                    if ($variable && this.uidInList(ActiveItemVariables, $variable.uid)){
                        $variables.push($variable);
                    }
                }
            }
            
            return $variables;
        },
        
        VariableGroupTemplate(){
                let key='variable_groups';
                if (!this.$store.state.formTemplate || !this.$store.state.formTemplate.template){
                    return null;
                }
                let items=this.$store.state.formTemplate.template.items;
                let item=this.findTemplateByItemKey(items,key);
                return item;        
        },

        VariableGroupTypeField(){

            let key='variable_groups.group_type';
            if (!this.$store.state.formTemplate || !this.$store.state.formTemplate.template){
                return {
                    "key": "group_type",
                    "title": "Group type",
                    "type": "string",
                    "prop_key": "variable_groups.group_type",
                    "help_text": "The type of the group.",
                    "display_type": "text"
                };
            }
            let items=this.$store.state.formTemplate.template.items;
            let group_type_field=this.findTemplateByItemKey(items,key);
            
            if (group_type_field){
                return group_type_field;
            }

            return {
                "key": "group_type",
                "title": "Group type",
                "type": "string",
                "prop_key": "variable_groups.group_type",
                "help_text": "The type of the group.",
                "display_type": "text"
            }
        },

    },
    template: `
        <div class="variable-groups-component">
            <dialog-variable-selection v-model="showDialog" :selected_items="ActiveItemVariables" :loading="variables_loading" @selected="OnVariableSelection"></dialog-variable-selection>
        
            <div class="container-fluid mt-5 pt-5">

                <div class="bg-white p-3 border">

                <div class="d-flex align-center justify-space-between mb-3">
                    <h3 class="mb-0">{{$t("variable_groups")}}</h3>
                    <v-btn color="primary" small :disabled="!is_dirty || is_saving" :loading="is_saving" @click="saveVariableGroups">
                        {{$t("save")}}<span v-if="is_dirty"> *</span>
                    </v-btn>
                </div>

                <div class="row mt-2">
                    <div class="col-md-4">
                        <div class="d-flex border bg-white" style="position:sticky; top:16px; max-height:320px; overflow:hidden;">
                        <div style="flex:1; min-width:0; min-height:0; overflow-y:auto;">
                        <v-treeview 
                            color="warning" 
                            :items="treeItems" 
                            activatable dense 
                            :active.sync="treeActiveItem"
                            :open.sync="treeItemOpen"
                            item-key="vgid" 
                            item-text="label" 
                            expand-icon="mdi-chevron-down" 
                            indeterminate-icon="mdi-bookmark-minus" 
                            on-icon="mdi-bookmark" 
                            off-icon="mdi-bookmark-outline" 
                            item-children="variable_groups">

                            <template #label="{ item }">
                                <span @click="treeClick(item)" :title="item.label" class="tree-item-label">
                                    <span>{{item.label}}</span>
                                </span>
                            </template>

                            <template v-slot:prepend="{ item, open }">
                                <v-icon v-if="item.vgid==-1">
                                    mdi-dresser
                                </v-icon>
                                <v-icon v-else>
                                    {{ open ? 'mdi-folder-open' : 'mdi-folder' }}
                                </v-icon>
                        </template>
                        </v-treeview>
                        </div>
                        <div class="border-left text-center pt-1" style="width:36px; flex-shrink:0;">
                            <div>
                                <v-icon color="primary" @click="addGroup" :title="$t('add')">mdi-plus</v-icon>
                            </div>
                            <div>
                                <v-icon :color="canRemoveGroup ? 'primary' : 'grey'" :style="canRemoveGroup ? 'cursor:pointer' : 'cursor:default;opacity:0.4'" @click="removeGroup" :title="$t('delete')">mdi-minus</v-icon>
                            </div>
                            <div>
                                <v-icon :color="canMoveUp ? 'primary' : 'grey'" :style="canMoveUp ? 'cursor:pointer' : 'cursor:default;opacity:0.4'" @click="moveGroup(-1)" :title="$t('move_up')">mdi-arrow-up-thin</v-icon>
                            </div>
                            <div>
                                <v-icon :color="canMoveDown ? 'primary' : 'grey'" :style="canMoveDown ? 'cursor:pointer' : 'cursor:default;opacity:0.4'" @click="moveGroup(1)" :title="$t('move_down')">mdi-arrow-down-thin</v-icon>
                            </div>
                        </div>
                        </div>
                    </div>
                    <div class="col-md-8"> 
                        <div v-if="VariableGroups.length==0">
                            <div class="border text-center text-primary p-3 m-3">{{$t("no_variable_groups")}}</div>
                        </div>

                        <div v-if="activeItem && activeItem.vgid!=-1">


                            <div v-if="VariableGroupTemplate && VariableGroupTemplate.items">
                            <div v-for="(column,idx_col) in VariableGroupTemplate.items" :key="column.key"  v-if="custom_fields.indexOf(column.key)<0">

                                <template v-if="column.type=='section'">
                                
                                    <form-section
                                        :parentElement="localVariable"
                                        :value="localValue(column.key)"
                                        :columns="column.items"
                                        :title="column.title"
                                        :path="column.key"
                                        :field="column"                            
                                        @sectionUpdate="updateSection($event)"
                                    ></form-section>  
                                    
                                </template>
                                <template v-else>
                                                                
                                    <form-input
                                        :value="localValue(column.key)"
                                        :field="column"
                                        @input="update(column.key, $event)"
                                    ></form-input>                              
                                    
                                </template>
                            </div>
                            </div>


                            <div class="form-group form-field">
                                <label>{{$t("variables")}}</label> <button type="button" class="btn btn-sm btn-link" @click="openVariableDialog">{{$t("select_variables")}}</button> 
                                <table class="table table-sm table-xs table-bordered bg-white" v-if="Variables.length>0">
                                    <thead>
                                        <tr class="bg-light">
                                            <th>FID</th>
                                            <th>{{$t("name")}}</th>
                                            <th>{{$t("label")}}</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="variable in Variables" :key="variable.uid">
                                            <td>{{variable.fid}}</td>
                                            <td>{{variable.name}}</td>
                                            <td>{{variable.labl}}</td>
                                            <td>
                                                <button type="button" class="btn btn-link p-0" v-on:click="removeVariable(variable.uid)" >
                                                    <v-icon color="primary">mdi-trash-can</v-icon>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div v-else>
                                    <p class="text-muted text-secondary border text-center p-2">{{$t("no_variables_selected")}}</p>
                                </div>
                            </div>


                            <div class="form-group form-field">
                                <label>{{$t("concepts")}}</label>
                                <table-grid-component 
                                    v-model="activeItem.concepts" 
                                    :columns="conceptColumns.props" 
                                    class="border"
                                    >
                                </table-grid-component>
                            </div>


                        </div>       
                        
                    </div>
                </div>
                </div>


            </div>
        </div>
    `
});
