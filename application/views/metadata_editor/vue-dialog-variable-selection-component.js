Vue.component('dialog-variable-selection', {
    props:['value',"selected_items","loading"],
    data() {
        return {
            selection:[],
            search:'',
            file_filter:'__all__',
            lastClickedIndex:null
        }
    },
    watch: {
        value: function (val) {
            if (val){
                this.selection = [];
                this.search = '';
                this.file_filter = '__all__';
                this.lastClickedIndex = null;
            }
        },
        search: function () {
            this.lastClickedIndex = null;
        },
        file_filter: function () {
            this.lastClickedIndex = null;
        }
    },
    methods: {   
        applySelection: function(){
            this.dialog = false;
            this.$emit('selected', this.selection.slice());
            this.selection = [];
        },
        cancelDialog: function(){
            this.dialog = false;
            this.selection = [];
        },
        isItemIncluded(uid){
            if (!this.selected_items){
                return false;
            }
            
            for(var i=0; i<this.selected_items.length; i++){
                if (this.selected_items[i]==uid){
                    return true;
                }
            }
            return false;
        },
        isSelected: function(uid){
            for (var i = 0; i < this.selection.length; i++){
                if (this.selection[i] == uid){
                    return true;
                }
            }
            return false;
        },
        addUid: function(uid){
            if (this.isItemIncluded(uid) || this.isSelected(uid)){
                return;
            }
            this.selection.push(uid);
        },
        removeUid: function(uid){
            for (var i = this.selection.length - 1; i >= 0; i--){
                if (this.selection[i] == uid){
                    this.selection.splice(i, 1);
                }
            }
        },
        toggleUid: function(uid){
            if (this.isItemIncluded(uid)){
                return;
            }
            if (this.isSelected(uid)){
                this.removeUid(uid);
            } else {
                this.addUid(uid);
            }
        },
        toggleRow: function(item, index, event){
            if (!item || this.isItemIncluded(item.uid)){
                return;
            }
            if (event && event.shiftKey && this.lastClickedIndex !== null){
                var start = Math.min(this.lastClickedIndex, index);
                var end = Math.max(this.lastClickedIndex, index);
                for (var i = start; i <= end; i++){
                    var row = this.FilteredVariables[i];
                    if (row){
                        this.addUid(row.uid);
                    }
                }
            } else {
                this.toggleUid(item.uid);
            }
            this.lastClickedIndex = index;
        },
        selectAllVisible: function(){
            for (var i = 0; i < this.FilteredVariables.length; i++){
                this.addUid(this.FilteredVariables[i].uid);
            }
        },
        variableUid: function(item){
            if (!item){
                return null;
            }
            if (item.uid !== undefined && item.uid !== null && item.uid !== ''){
                return item.uid;
            }
            if (item.metadata && item.metadata.uid !== undefined){
                return item.metadata.uid;
            }
            return null;
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
        DataFiles(){
            return this.$store.state.data_files || [];
        },
        FileFilterItems(){
            return [{ file_id: '__all__', file_name: this.$t('all_data_files') }].concat(this.DataFiles);
        },
        Variables(){
            var variablesByFile = this.$store.getters.getVariablesAll;
            if (!variablesByFile || typeof variablesByFile !== 'object'){
                return [];
            }

            var variables = [];
            for (var file in variablesByFile){
                var fileVars = variablesByFile[file];
                if (Array.isArray(fileVars)){
                    variables = variables.concat(fileVars);
                }
            }
            return variables;
        },
        FilteredVariables(){
            var search = (this.search || '').toLowerCase().trim();
            var fileFilter = this.file_filter;
            var out = [];

            for (var i = 0; i < this.Variables.length; i++){
                var item = this.Variables[i];
                var uid = this.variableUid(item);
                if (!item || uid === null){
                    continue;
                }
                if (fileFilter && fileFilter !== '__all__' && item.fid != fileFilter){
                    continue;
                }
                if (search){
                    var haystack = [
                        item.name,
                        item.labl,
                        item.fid,
                        item.vid
                    ].join(' ').toLowerCase();
                    if (haystack.indexOf(search) === -1){
                        continue;
                    }
                }
                out.push(item);
            }
            return out;
        },
        ProjectID(){
            return this.$store.state.project_id;
        }        
    },
    template: `
        <div class="vue-dialog-component">

            <v-dialog v-model="dialog" width="700" persistent>
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2 py-2">
                        {{$t("variable_selection")}}
                    </v-card-title>

                    <v-card-text class="pt-3 pb-0">
                    <div>
                        <div class="d-flex align-center mb-2" style="gap:12px;">
                            <v-text-field
                                v-model="search"
                                :label="$t('search')"
                                dense
                                hide-details
                                clearable
                                prepend-inner-icon="mdi-magnify"
                            ></v-text-field>
                            <v-select
                                v-model="file_filter"
                                :items="FileFilterItems"
                                item-text="file_name"
                                item-value="file_id"
                                :label="$t('data_files')"
                                dense
                                hide-details
                                style="max-width:240px;"
                            ></v-select>
                            <v-btn text small class="flex-shrink-0" @click="selectAllVisible">
                                {{$t("select_all")}}
                            </v-btn>
                        </div>
                        <div class="text-caption text-muted mb-1">{{$t("variable_selection_hint")}}</div>
                        
                        <div class="border" style="height:400px; overflow-y:auto;">
                            <div v-if="loading" class="d-flex align-center justify-center pa-4">
                                <v-progress-circular indeterminate color="primary" size="24" class="mr-2"></v-progress-circular>
                                <span>{{$t("loading")}} {{$t("variables")}}...</span>
                            </div>
                            <div v-else-if="FilteredVariables.length==0" class="text-center text-muted pa-4">
                                {{$t("no_variables_selected")}}
                            </div>
                            <div
                                v-for="(item,index) in FilteredVariables"
                                :key="variableUid(item)"
                                class="d-flex align-center px-2"
                                style="border-bottom:1px solid #e0e0e0; height:28px;"
                                :style="{
                                    cursor: isItemIncluded(variableUid(item)) ? 'default' : 'pointer',
                                    background: (isSelected(variableUid(item)) || isItemIncluded(variableUid(item))) ? '#f5f5f5' : 'transparent',
                                    opacity: isItemIncluded(variableUid(item)) ? 0.6 : 1
                                }"
                                @click="toggleRow(item, index, $event)"
                            >
                                <input
                                    type="checkbox"
                                    class="mr-2"
                                    :checked="isSelected(variableUid(item)) || isItemIncluded(variableUid(item))"
                                    :disabled="isItemIncluded(variableUid(item))"
                                    :id="'vg-var-'+variableUid(item)"
                                    @click.stop="toggleRow(item, index, $event)"
                                />
                                <label :for="'vg-var-'+variableUid(item)" class="text-normal mb-0 flex-grow-1 text-truncate" style="cursor:inherit;" @click.prevent>
                                    {{item.name}} — {{item.labl}}
                                </label>
                                <span class="text-muted ml-2">{{item.fid}}</span>
                            </div>
                        </div>
                        
                    </div>
                    </v-card-text>

                    <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn text @click="cancelDialog">
                        {{$t("cancel")}}
                    </v-btn>
                    <v-btn color="primary" text @click="applySelection">
                        {{$t("select")}}
                    </v-btn>
                    </v-card-actions>
                </v-card>
                </v-dialog>
        
        </div>
    `
});
