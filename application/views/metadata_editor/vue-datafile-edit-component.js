/// datafile add/edit form
const VueDatafileEdit= Vue.component('datafile-edit', {
    data: function () {    
        return {
            form_local: {},
            is_dirty: false,
            is_loading: true,
            save_error: '',
            is_saving: false
        }
    },
    mounted: function () {
        window.onbeforeunload = function () {
            if (this.is_dirty) {
                return "You have unsaved changes. Are you sure you want to leave?";
            }
        }.bind(this);
        
        this.loadFile();
    },
    beforeRouteLeave(to, from, next) {
        if (!this.showUnsavedMessage()){
            return false;
        }
        next();
    },
    beforeRouteUpdate(to, from, next) {
        if (!this.showUnsavedMessage()){
            return false;
        }
        next();
    },
    watch: {
        form_local: {
            handler: function (newVal, oldVal) {                
                if (this.is_loading){return;}
                if (!oldVal.file_id){return;}                
                this.is_dirty=true;
            },
            deep: true
        },
    },
    methods:{
        showUnsavedMessage: function(){
            if (this.is_dirty){
                if (!confirm("You have unsaved changes. Are you sure you want to leave this page?")){
                    return false;
                }
            }
            return true;
        },
        saveForm: function (){    
            this.saveFile();
        },
        saveErrorMessage: function(error){
            if (error && error.response && error.response.data) {
                if (error.response.data.message) {
                    return error.response.data.message;
                }
                if (error.response.data.status === 'failed' && typeof error.response.data === 'object') {
                    return JSON.stringify(error.response.data);
                }
            }
            if (error && error.message) {
                return error.message;
            }
            return this.$t('failed') || 'Save failed';
        },
        cancelForm: function (){
            if (this.is_dirty){
                if (!confirm("You have unsaved changes. Are you sure you want to leave this page?")){
                    return false;
                }
            }
            this.is_dirty=false;
            router.push('/datafiles/');
        },
        loadFile: function(){
            //load file data
            vm=this;
            this.is_loading=true;
            let url=CI.base_url + '/api/datafiles/'+ this.ProjectID + '/' + this.ActiveDatafileIndex;
            axios.get( url
            ).then(function(response){
                vm.form_local=response.data.datafile;
                vm.is_loading=false;
            })
            .catch(function(response){
                vm.errors=response;
            });
        },
        saveFile: function(){
            vm=this;
            vm.save_error='';
            vm.is_saving=true;
            let url=CI.base_url + '/api/datafiles/'+ this.ProjectID;
            let payload=Object.assign({}, this.form_local);
            delete payload.file_info;
            axios.post( url, payload)
            .then(function(response){
                vm.is_saving=false;
                if (response.data && response.data.status === 'failed') {
                    vm.save_error = response.data.message || vm.$t('failed');
                    return;
                }
                vm.$store.dispatch('loadDataFiles',{dataset_id:vm.ProjectID});
                vm.is_dirty=false;
                router.push('/datafiles/');
            })
            .catch(function(error){
                vm.is_saving=false;
                vm.save_error = vm.saveErrorMessage(error);
            });
        },
        displayDash: function(){
            return '—';
        },
        stataReleaseToVersion: function(release){
            const map = {
                104: 8, 105: 9, 108: 10, 114: 11, 115: 12,
                117: 13, 118: 14, 119: 15, 120: 16, 121: 17, 122: 18, 123: 19
            };
            const n = parseInt(release, 10);
            if (isNaN(n)) {
                return null;
            }
            if (n <= 30) {
                return String(n);
            }
            if (map[n]) {
                return String(map[n]);
            }
            return null;
        },
        sourceFormatDisplay: function(){
            const fmt = this.resolveSourceFormat();
            if (fmt === 'dta') {
                return 'Stata';
            }
            if (fmt === 'sav') {
                return 'SPSS';
            }
            if (fmt === 'csv') {
                return 'CSV';
            }
            return null;
        },
        resolveSourceFormat: function(){
            const fmt = (this.form_local.source_format || '').toLowerCase();
            if (fmt) {
                return fmt;
            }
            const physical = (this.form_local.file_physical_name || '').toLowerCase();
            if (physical.endsWith('.dta')) {
                return 'dta';
            }
            if (physical.endsWith('.sav')) {
                return 'sav';
            }
            const original = this.form_local.file_info && this.form_local.file_info.original;
            if (original && original.filename) {
                const name = String(original.filename).toLowerCase();
                if (name.endsWith('.dta')) {
                    return 'dta';
                }
                if (name.endsWith('.sav')) {
                    return 'sav';
                }
            }
            return '';
        },
        sourceVersionDisplay: function(){
            const fmt = this.resolveSourceFormat();
            const version = this.form_local.source_format_version;
            if (version === null || version === undefined || version === '') {
                return null;
            }
            if (fmt === 'dta') {
                const mapped = this.stataReleaseToVersion(version);
                if (mapped) {
                    return mapped;
                }
                return String(version);
            }
            if (fmt === 'sav') {
                return String(version);
            }
            return null;
        },
        sourceFileNameDisplay: function(){
            if (this.form_local.source_upload_filename) {
                return this.form_local.source_upload_filename;
            }
            if (this.form_local.file_physical_name) {
                return this.form_local.file_physical_name;
            }
            const original = this.form_local.file_info && this.form_local.file_info.original;
            if (original && original.filename) {
                return original.filename;
            }
            return this.displayDash();
        },
        sourceFileSizeDisplay: function(){
            const original = this.form_local.file_info && this.form_local.file_info.original;
            if (original && original.file_exists && original.file_size) {
                return original.file_size;
            }
            return this.displayDash();
        },
        workingDataSizeDisplay: function(){
            const csv = this.form_local.file_info && this.form_local.file_info.csv;
            if (csv && csv.file_exists && csv.file_size) {
                return csv.file_size;
            }
            return this.displayDash();
        },
        sourceStatusDisplay: function(){
            const status = (this.form_local.source_status || 'unknown').toLowerCase();
            const fmt = (this.form_local.source_format || '').toLowerCase();
            const original = this.form_local.file_info && this.form_local.file_info.original;
            const onDisk = !!(original && original.file_exists);

            if (status === 'missing') {
                return { label: this.$t('source_file_not_stored'), warning: true };
            }
            if (status === 'unknown') {
                return { label: this.$t('source_original_format_unknown'), warning: false };
            }
            if ((fmt === 'dta' || fmt === 'sav') && (status === 'present' || onDisk)) {
                return { label: this.$t('source_file_stored'), warning: false };
            }
            if (fmt === 'dta' || fmt === 'sav') {
                return { label: this.$t('source_file_not_stored'), warning: true };
            }
            return null;
        }
    },
    computed:{
        ActiveDatafileIndex(){
            return this.$route.params.file_id;
        },
        ProjectID(){
            return this.$store.state.project_id;
        },
        uploadedAsCsvNote: function(){
            const fmt = (this.form_local.source_format || '').toLowerCase();
            const status = (this.form_local.source_status || '').toLowerCase();
            return fmt === 'csv' && status === 'not_applicable';
        },
        showSourceSection: function(){
            if (this.uploadedAsCsvNote) {
                return false;
            }
            const fmt = (this.form_local.source_format || '').toLowerCase();
            const status = (this.form_local.source_status || 'unknown').toLowerCase();
            if (fmt === 'dta' || fmt === 'sav') {
                return true;
            }
            if (status === 'missing' || status === 'unknown') {
                return true;
            }
            const physical = (this.form_local.file_physical_name || '').toLowerCase();
            return physical.endsWith('.dta') || physical.endsWith('.sav');
        }
    },  
    template: `
            <div class="datafile-edit-component container-fluid" >


            <section style="display: flex; flex-flow: column;height: calc(100vh - 140px);">
             
            
                <v-card class="mt-4 mb-2">                    
                    <v-card-title class="d-flex justify-space-between">
                        <div style="font-weight:normal">{{$t("Data file")}}: {{form_local.file_name}}</div>

                        <div>
                            <v-btn color="primary" small :loading="is_saving" :disabled="is_saving" @click="saveForm">{{$t("Save")}} <span v-if="is_dirty">*</span></v-btn>
                            <v-btn  @click="cancelForm" small :disabled="is_saving">{{$t("cancel")}}</v-btn>
                        </div>
                    </v-card-title>
                </v-card>

                <v-alert v-if="save_error" type="error" dense outlined dismissible class="my-3" style="background-color: #fff;" @input="save_error=''">
                    {{save_error}}
                </v-alert>

                <v-card style="flex: 1;overflow:auto;">                    
                    <v-card-text>
            

                        <div class="row">
                            <div class="col-md-8">

                            <div class="form-group form-field">
                                <label for="filename">File name</label> 
                                <span><input type="text" id="filename" class="form-control" v-model="form_local.file_name"/></span> 
                            </div>

                            <div class="form-group form-field">
                                <label for="description">Description</label> 
                                <span><textarea id="description" class="form-control" v-model="form_local.description"/></span> 
                            </div>

                            <div class="form-group form-field">
                                <label for="description">Producer</label> 
                                <span><textarea id="description" class="form-control" v-model="form_local.producer"/></span> 
                            </div>

                            <div class="form-group form-field">
                                <label for="description">Data checks</label> 
                                <span><textarea id="description" class="form-control" v-model="form_local.data_checks"/></span> 
                            </div>

                            <div class="form-group form-field">
                                <label for="description">Missing data</label> 
                                <span><textarea id="description" class="form-control" v-model="form_local.missing_data"/></span> 
                            </div>

                            <div class="form-group form-field">
                                <label for="description">Version</label> 
                                <span><textarea id="description" class="form-control" v-model="form_local.version"/></span> 
                            </div>

                            <div class="form-group form-field">
                                <label for="description">Notes</label> 
                                <span><textarea id="description" class="form-control" v-model="form_local.notes"/></span> 
                            </div>

                            </div>
                            <div class="col-md-4">
                                <v-card outlined class="pa-4">
                                    <div class="text-subtitle-2 font-weight-medium mb-3">{{$t("file_information")}}</div>

                                    <div class="text-caption text-uppercase grey--text text--darken-1 mb-2">{{$t("working_data")}}</div>
                                    <div class="datafile-info-row d-flex justify-space-between mb-2">
                                        <span class="text--secondary">{{$t("file_size")}}</span>
                                        <span>{{workingDataSizeDisplay()}}</span>
                                    </div>
                                    <div class="datafile-info-row d-flex justify-space-between mb-2">
                                        <span class="text--secondary">{{$t("variables")}}</span>
                                        <span>{{form_local.var_count != null ? form_local.var_count : displayDash()}}</span>
                                    </div>
                                    <div class="datafile-info-row d-flex justify-space-between mb-2">
                                        <span class="text--secondary">{{$t("cases")}}</span>
                                        <span>{{form_local.case_count != null ? form_local.case_count : displayDash()}}</span>
                                    </div>

                                    <div v-if="uploadedAsCsvNote" class="text-caption text--secondary mt-3">
                                        {{$t("uploaded_as_csv")}}
                                    </div>

                                    <template v-if="showSourceSection">
                                        <v-divider class="my-3"></v-divider>
                                        <div class="text-caption text-uppercase grey--text text--darken-1 mb-2">{{$t("source_file")}}</div>
                                        <div class="datafile-info-row d-flex justify-space-between mb-2">
                                            <span class="text--secondary">{{$t("source_file_format")}}</span>
                                            <span>{{sourceFormatDisplay() || displayDash()}}</span>
                                        </div>
                                        <div class="datafile-info-row d-flex justify-space-between mb-2">
                                            <span class="text--secondary">{{$t("source_file_version")}}</span>
                                            <span>{{sourceVersionDisplay() || displayDash()}}</span>
                                        </div>
                                        <div class="datafile-info-row d-flex justify-space-between mb-2">
                                            <span class="text--secondary">{{$t("physical_name")}}</span>
                                            <span class="text-right ml-2" style="word-break:break-all;">{{sourceFileNameDisplay()}}</span>
                                        </div>
                                        <div class="datafile-info-row d-flex justify-space-between mb-2">
                                            <span class="text--secondary">{{$t("file_size")}}</span>
                                            <span>{{sourceFileSizeDisplay()}}</span>
                                        </div>
                                        <div v-if="sourceStatusDisplay()" class="datafile-info-row d-flex justify-space-between">
                                            <span class="text--secondary">{{$t("status")}}</span>
                                            <span :class="sourceStatusDisplay().warning ? 'warning--text' : ''">{{sourceStatusDisplay().label}}</span>
                                        </div>
                                    </template>
                                </v-card>
                            </div>
                            </div>

                </v-card-text>
                </v-card>


                </section>

            </div>          
            `    
})
