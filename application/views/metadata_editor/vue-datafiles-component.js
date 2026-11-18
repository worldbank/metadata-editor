Vue.component('datafiles', {
    data() {
        return {
            showChildren: true,
            dataset_id:project_sid,
            dataset_idno:project_idno,
            dataset_type:project_type,
            dialog_datafile_import:false,
            dialog_datafile_import_fid:null,
            form_errors:[],
            schema_errors:[],
            page_action:'list',
            edit_item:null,
            selected_files:[],
            select_all_files:false,
            sort_column:'file_id',
            sort_direction:'asc',
            dialog:{
                show:false,
                title:'',
                loading_message:'',
                message_success:'',
                message_success_html:'',
                download_links:[],
                message_error:'',
                is_loading:false
            },
            export_dialog:{
                show:false,
                file_id:null,
                file_name:'',
                file_physical_name:''
            },
            batch_export_dialog_show: false,
            // Variables-vs-CSV status per file_id; kept here (not in the store) so
            // other data file reloads can't overwrite it
            columns_sync: {},
            columns_sync_request: 0,
            dialog_unmatched: {
                show: false,
                loading: false,
                applying: false,
                done: false,
                results: [],
                removed_count: 0,
                error: '',
                filter: '',
                only_kept: false,
                expanded: {},   // file_id => true
                show_all: {},   // file_id => true (uncapped name list)
                show_skipped: false
            },
            dialog_sum_stats_options: {
                show: false,
                files: []  // [{file_id, file_name}] - 1 or more
            },
            dialog_import_metadata: {
                show: false,
                file_id: null,
                file_name: '',
                selected_file: null,
                uploading: false,
                message_success: '',
                message_error: ''
            },
            dialog_import_variable_documentation: {
                show: false,
                file_id: null,
                file_name: '',
                selected_file: null,
                uploading: false,
                message_success: '',
                message_error: ''
            },
            attrs: {}
            
        }
    }, 
    mounted: function () {
        // Load data files when component is mounted
        this.reloadDataFiles();
        
        // Listen for page visibility changes (tab activation)
        document.addEventListener('visibilitychange', this.handleVisibilityChange);
    },
    beforeDestroy: function() {
        // Clean up event listener
        document.removeEventListener('visibilitychange', this.handleVisibilityChange);
    },
    watch: {
        'data_files': {
            handler: function(newVal, oldVal) {
                if (oldVal.length < 1) {
                    return;
                }
                
                // Only update weights if the order actually changed
                // Compare the sequence of IDs
                if (newVal.length !== oldVal.length) {
                    // Length changed (files added/removed), update weights
                    this.updateDataFilesWeight();
                    return;
                }
                
                // Check if order changed by comparing IDs at each position
                let orderChanged = false;
                for (let i = 0; i < newVal.length; i++) {
                    // Compare by 'id' property (used in getRowSequence)
                    if (!newVal[i] || !oldVal[i] || newVal[i].id !== oldVal[i].id) {
                        orderChanged = true;
                        break;
                    }
                }
                
                if (orderChanged) {
                    this.updateDataFilesWeight();
                }
            },
            deep: false // Don't watch deep changes, only array reference and order
        },
        // Watch for route changes to refresh when navigating to datafiles page
        '$route': {
            handler: function(to, from) {
                // Refresh when navigating to /datafiles route
                if (to.path === '/datafiles') {
                    this.reloadDataFiles();
                }
            },
            immediate: false
        }
    }, 
    methods: {
        momentDate(date) {
            return moment.unix(date).format("YYYY-MM-DD")
        },
        removeData: async function(data_file){
            if (!confirm(this.$t("confirm_remove_data"))){
                data_file.store_data=1;
                return false;
            }

            //save file, set store_data to 0
            data_file.store_data=0;
            let result=await this.saveFile(data_file);

            vm=this;
            let url=CI.base_url + '/api/datafiles/cleanup/'+ vm.dataset_id;
            let formData=new FormData();            

            await axios.post( url, formData,
            ).then(function(response){                
            })
            .catch(function(response){
                console.log(response);
                alert(vm.$t("failed")+": "+ response.message);
            });
        },

        editFile:function(file_id){
            //this.page_action="edit";
            //this.edit_item=file_id;
            let file_=this.data_files[file_id];

            if (!file_) return;

            router.push('/datafile/'+file_.file_id);
        },
        addFile:function(){
            this.page_action="edit";
            this.$store.commit('data_files_add',{file_name:'untitled'});
            newIdx=this.data_files.length -1;
            this.edit_item=newIdx;
        },
        saveFile: async function(data)
        {            
            vm=this;
            let url=CI.base_url + '/api/datafiles/'+vm.dataset_id;
            form_data=data;

            axios.post(url, 
                form_data
            )
            .then(function (response) {
                vm.$store.dispatch('loadDataFiles',{dataset_id:vm.dataset_id});
            })
            .catch(function (error) {
                console.log(error);
                let message='';
                if (error.response.data.message){
                    message=error.response.data.message;
                }else{
                    message=error.message;
                }
                alert(vm.$t("failed")+": "+ message);
            })
            .then(function () {
                console.log("request completed");
            });
        },
        updateDataFilesWeight: function()
        {
            vm=this;
            let url=CI.base_url + '/api/datafiles/sequence/'+vm.dataset_id;
            let form_data={};
            form_data.options=this.getRowSequence(this.data_files);

            axios.post(url, 
                form_data
            )
            .then(function (response) {
                console.log("updating",response);
            })
            .catch(function (error) {
                console.log("failed to update datafiles sequence",error);
                alert(vm.$t("failed")+": "+ error.message);
            })            
        },
        getRowSequence: function(rows){
            let seq=[];
            for (let i=0;i<rows.length;i++){
                seq.push(
                    {
                       'id': rows[i]['id'],
                        'wght': i
                    });
            }
            return seq;
        },        
        replaceFile:function(file_idx){
            let data_file=this.data_files[file_idx];
            this.dialog_datafile_import_fid=data_file.file_id;
            this.dialog_datafile_import=true;
        },
        dataFileSizeDisplay:function(file){
            if (!file || !file.file_info || !file.file_info.csv) {
                return '—';
            }
            if (file.file_info.csv.file_exists && file.file_info.csv.file_size) {
                return file.file_info.csv.file_size;
            }
            return '—';
        },
        exportMetadataFile: function(file_idx){
            let data_file = this.data_files[file_idx];
            if (!data_file) return;
            let url = CI.base_url + '/api/datafiles/export_metadata/' + this.dataset_id + '/' + encodeURIComponent(data_file.file_id);
            window.location.href = url;
        },
        exportDictionaryCsv: function(file_idx) {
            let data_file = this.data_files[file_idx];
            if (!data_file) return;
            let url = CI.base_url + '/api/variables/export_csv/' + this.dataset_id + '/' + encodeURIComponent(data_file.file_id) + '?download=1';
            window.location.href = url;
        },
        exportVariableDocumentationCsv: function(file_idx) {
            let data_file = this.data_files[file_idx];
            if (!data_file) return;
            let url = CI.base_url + '/api/variables/export_documentation_csv/' + this.dataset_id + '/' + encodeURIComponent(data_file.file_id) + '?download=1&profile=full';
            window.location.href = url;
        },
        openImportVariableDocumentationDialog: function(file_idx) {
            let data_file = this.data_files[file_idx];
            if (!data_file) return;
            this.dialog_import_variable_documentation.show = true;
            this.dialog_import_variable_documentation.file_id = data_file.file_id;
            this.dialog_import_variable_documentation.file_name = data_file.file_name || data_file.file_id;
            this.dialog_import_variable_documentation.selected_file = null;
            this.dialog_import_variable_documentation.uploading = false;
            this.dialog_import_variable_documentation.message_success = '';
            this.dialog_import_variable_documentation.message_error = '';
        },
        submitImportVariableDocumentation: async function() {
            var raw = this.dialog_import_variable_documentation.selected_file;
            var file = Array.isArray(raw) ? (raw.length ? raw[0] : null) : raw;
            if (!file || !(file instanceof File)) {
                this.dialog_import_variable_documentation.message_error = this.$t("please_select_file");
                return;
            }
            let vm = this;
            let url = CI.base_url + '/api/variables/import_documentation_csv/' + vm.dataset_id + '/' + encodeURIComponent(this.dialog_import_variable_documentation.file_id) + '?profile=full';
            let formData = new FormData();
            formData.append('file', file);
            this.dialog_import_variable_documentation.uploading = true;
            this.dialog_import_variable_documentation.message_error = '';
            this.dialog_import_variable_documentation.message_success = '';
            try {
                let response = await axios.post(url, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });
                if (response.data && response.data.status === 'success') {
                    this.dialog_import_variable_documentation.message_success = this.$t("import_variable_documentation_success") + ' (' + (response.data.updated || 0) + ' ' + this.$t("variables") + ')';
                    await vm.reloadDataFileVariables(vm.dialog_import_variable_documentation.file_id);
                    await vm.reloadDataFiles();
                } else {
                    this.dialog_import_variable_documentation.message_error = (response.data && response.data.message) ? response.data.message : vm.$t("failed");
                }
            } catch (error) {
                let msg = (error.response && error.response.data && error.response.data.message) ? error.response.data.message : error.message;
                if (error.response && error.response.data && error.response.data.errors && error.response.data.errors.length) {
                    let first = error.response.data.errors[0];
                    if (first && first.message) {
                        msg = first.message;
                    }
                }
                this.dialog_import_variable_documentation.message_error = vm.$t("failed") + ": " + msg;
            } finally {
                this.dialog_import_variable_documentation.uploading = false;
            }
        },
        openImportMetadataDialog: function(file_idx){
            let data_file = this.data_files[file_idx];
            if (!data_file) return;
            this.dialog_import_metadata.show = true;
            this.dialog_import_metadata.file_id = data_file.file_id;
            this.dialog_import_metadata.file_name = data_file.file_name || data_file.file_id;
            this.dialog_import_metadata.selected_file = null;
            this.dialog_import_metadata.uploading = false;
            this.dialog_import_metadata.message_success = '';
            this.dialog_import_metadata.message_error = '';
        },
        submitImportMetadata: async function(){
            var raw = this.dialog_import_metadata.selected_file;
            var file = Array.isArray(raw) ? (raw.length ? raw[0] : null) : raw;
            if (!file || !(file instanceof File)) {
                this.dialog_import_metadata.message_error = this.$t("please_select_file");
                return;
            }
            let vm = this;
            let url = CI.base_url + '/api/datafiles/replace_metadata/' + vm.dataset_id + '/' + encodeURIComponent(this.dialog_import_metadata.file_id);
            let formData = new FormData();
            formData.append('file', file);
            this.dialog_import_metadata.uploading = true;
            this.dialog_import_metadata.message_error = '';
            this.dialog_import_metadata.message_success = '';
            try {
                let response = await axios.post(url, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });
                if (response.data && response.data.status === 'success') {
                    this.dialog_import_metadata.message_success = this.$t("import_metadata_success") + ' (' + (response.data.variables_count || 0) + ' ' + this.$t("variables") + ')';
                    await vm.reloadDataFileVariables(vm.dialog_import_metadata.file_id);
                    await vm.reloadDataFiles();
                } else {
                    this.dialog_import_metadata.message_error = (response.data && response.data.message) ? response.data.message : vm.$t("failed");
                }
            } catch (error) {
                let msg = (error.response && error.response.data && error.response.data.message) ? error.response.data.message : error.message;
                this.dialog_import_metadata.message_error = vm.$t("failed") + ": " + msg;
            } finally {
                this.dialog_import_metadata.uploading = false;
            }
        },
        duplicateFile: async function(file_idx){
            let data_file = this.data_files[file_idx];
            if (!data_file) return;
            if (!confirm(this.$t("confirm_duplicate_data_file"))) return;
            let vm = this;
            let url = CI.base_url + '/api/datafiles/duplicate/' + vm.dataset_id + '/' + encodeURIComponent(data_file.file_id);
            try {
                let response = await axios.post(url, {});
                if (response.data && response.data.status === 'success') {
                    await vm.reloadDataFiles();
                    vm.dialog.show = true;
                    vm.dialog.title = vm.$t("duplicate_data_file");
                    vm.dialog.is_loading = false;
                    vm.dialog.message_success = vm.$t("data_file_duplicated_successfully");
                } else {
                    alert(vm.$t("failed") + ": " + (response.data && response.data.message ? response.data.message : "Unknown error"));
                }
            } catch (error) {
                let message = (error.response && error.response.data && error.response.data.message) ? error.response.data.message : error.message;
                alert(vm.$t("failed") + ": " + message);
            }
        },
        exportFile: function(file_idx, format){
            let data_file = this.data_files[file_idx];
            this.export_dialog.file_id = data_file.file_id;
            this.export_dialog.file_name = data_file.file_name;
            this.export_dialog.file_physical_name = data_file.file_physical_name || '';
            this.export_dialog.show = true;
        },        
        batchDelete: async function() {
            if (!confirm(this.$t("confirm_delete_selected"))) {
                return;
            }
        
            let deletionPromises = this.selected_files.map(async (file_id) => {                
                try {
                    await this.deleteFileByFileId(file_id);
                } catch (error) {
                    console.error(`Error deleting file with ID ${file_id}:`, error);
                    // Optionally, handle the error, e.g., by notifying the user
                }
            });
        
            await Promise.all(deletionPromises);
            this.reloadDataFiles();
            this.selected_files = [];
        },
        deleteFileByFileId: async function(file_id)
        {
            vm=this;
            let url=CI.base_url + '/api/datafiles/delete/'+vm.dataset_id + '/'+ file_id;
            form_data={};

            try {
                await axios.post(url, form_data);
                return true;
            } catch (error) {
                console.log(error);
                return error; // Return the error object in case of failure
            }
        },
        deleteFile:function(file_idx,confirm_=false)
        {
            let data_file=this.data_files[file_idx];

            if (confirm_==false && !confirm(this.$t("confirm_delete") + " " + data_file.file_id + "?")){
                return;
            }

            vm=this;
            let url=CI.base_url + '/api/datafiles/delete/'+vm.dataset_id + '/'+ data_file.file_id;
            form_data={};

            axios.post(url, 
                form_data
            )
            .then(function (response) {
                vm.data_files.splice(file_idx, 1);
            })
            .catch(function (error) {
                console.log(error);
                alert(vm.$t("failed")+": "+ error.message);
            });                        
        },
        exitEditMode: function()
        {
            this.page_action="list";
            this.edit_item=null;
        },
        hasCsvFile: function (file_id){            
            for (let i=0;i<this.data_files.length;i++){
                if (this.data_files[i].file_id==file_id){
                    let file_=this.data_files[i];

                    if (file_.file_info && file_.file_info.csv && file_.file_info.csv.file_exists && file_.file_info.csv.file_exists==true){
                        return true;
                    }
                }
            }
            return false;
        },
        importSummaryStatistics: async function(file_id)
        {                        
            if (!confirm(this.$t("confirm_import_summary_statistics"))){
                return;
            }

            if (!this.hasCsvFile(file_id)){
                await this.generateCSV(file_id);
            }

            this.dialog={
                show:true,
                title:'',
                loading_message:'',
                message_success:'',
                message_error:'',
                is_loading:false
            }

            this.dialog.is_loading=true;
            this.dialog.title=this.$t("summary_stats");
            this.dialog.loading_message=this.$t("processing_please_wait");
            try{
                let result=await this.$store.dispatch('importDataFileSummaryStatisticsQueue',{file_id:file_id});
                console.log("updated",result);
                this.dialog.loading_message="Queued for import..." + result.data.message;
                this.importSummaryStatisticsQueueStatusCheck(file_id,result.data.job_id);
            }catch(e){
                console.log("failed",e);
                this.dialog.is_loading=false;
                this.dialog.message_error=this.$t("failed") +  ": " + e.response.data.message;
            }
        },
        importSummaryStatisticsQueueStatusCheck: async function(file_id,job_id){

            this.dialog={
                show:true,
                title:'',
                loading_message:'',
                message_success:'',
                message_error:'',
                is_loading:false
            }

            this.dialog.is_loading=true;
            this.dialog.title=this.$t("summary_stats");
            this.dialog.loading_message=this.$t("processing_please_wait");
            try{
                await this.sleep(5000);
                let result=await this.$store.dispatch('importDataFileSummaryStatisticsQueueStatusCheck',{file_id:file_id, job_id:job_id});
                console.log("job updated",result);
                this.dialog.is_loading=true;
                this.dialog.loading_message="Job status: " + result.data.job_status;
                if (result.data.job_status === 'failed' || result.data.job_status === 'error') {
                    const msg = result.data.message || (typeof result.data.detail === 'string' ? result.data.detail : '') || 'Job failed';
                    this.dialog.is_loading = false;
                    this.dialog.message_error = msg;
                    return;
                }
                if (result.data.job_status!=='done'){
                    this.importSummaryStatisticsQueueStatusCheck(file_id,job_id);
                }else if (result.data.job_status==='done'){
                    await this.reloadDataFileVariables(file_id);
                    await this.reloadDataFiles();
                    this.dialog.is_loading=false;
                    this.dialog.message_success=this.$t("sum_stats_imported_success");
                }
                
            }catch(e){
                console.log("failed",e);
                this.dialog.is_loading=false;
                this.dialog.message_error="Failed to import summary statistics: "+(e.response && e.response.data && e.response.data.message ? e.response.data.message : e.message);
            }
        },
        reloadDataFileVariables: async function(file_id){
            return await this.$store.dispatch('loadVariables',{dataset_id:this.dataset_id, fid:file_id});
        },
        reloadDataFiles: async function(){
            await store.dispatch('loadDataFiles',{dataset_id:this.dataset_id});
            await this.loadColumnsSync();
        },
        loadColumnsSync: async function(){
            // Ignore responses from older requests if reloads overlap
            let request_id = ++this.columns_sync_request;
            try {
                let response = await axios.get(CI.base_url + '/api/datafiles/columns_sync/' + this.dataset_id);
                if (request_id !== this.columns_sync_request) return;
                this.columns_sync = (response.data && response.data.columns_sync) || {};
            } catch (e) {
                if (request_id !== this.columns_sync_request) return;
                console.error('Failed to load data file columns status', e);
                this.columns_sync = {};
            }
        },
        sleep: function(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },
        generateCSV: async function(file_id)
        {
            this.dialog={
                show:true,
                title:this.$t('generate_csv_file'),
                loading_message:this.$t('processing_please_wait'),
                message_success:'',
                message_error:'',
                is_loading:true
            }

            try{
                let result=await this.$store.dispatch('generateCsvQueue',{file_id:file_id});
                console.log("updated",result);
                await this.generateCsvQueueStatusCheck(file_id,result.data.job_id);
            }catch(e){
                console.log("failed",e);
                this.dialog.is_loading=false;
                this.dialog.message_error="Failed to generate CSV file: "+e.response.data.message;                
            }
        },
        generateCsvQueueStatusCheck: async function(file_id,job_id)
        {
            this.dialog={
                show:true,
                title:'',
                loading_message:'',
                message_success:'',
                message_error:'',
                is_loading:false
            }

            this.dialog.is_loading=true;
            this.dialog.title=this.$t('generate_csv_file');
            this.dialog.loading_message=this.$t('processing_please_wait');
            try{
                await this.sleep(5000);
                let result=await this.$store.dispatch('generateCsvQueueStatusCheck',{file_id:file_id, job_id:job_id});
                console.log("csv updated",result);
                this.dialog.is_loading=true;
                this.dialog.loading_message="Job status: " + result.data.job_status;
                if (result.data.job_status === 'failed' || result.data.job_status === 'error') {
                    const msg = result.data.message || (typeof result.data.detail === 'string' ? result.data.detail : '') || 'Job failed';
                    this.dialog.is_loading = false;
                    this.dialog.message_error = msg;
                    return;
                }
                if (result.data.job_status!=='done'){
                    this.generateCsvQueueStatusCheck(file_id,job_id);
                }else if (result.data.job_status==='done'){
                    this.dialog.is_loading=false;
                    this.dialog.message_success=this.$t('csv_generated_success');                
                }
                
            }catch(e){
                console.log("failed",e);
                this.dialog.is_loading=false;
                this.dialog.message_error=this.$t("failed")+": "+e.response.data.message;
            }
        },
        toggleFilesSelection: function()
        {
            this.selected_files = [];
          if (this.select_all_files == true) {
            for (i = 0; i < this.data_files.length; i++) {
              this.selected_files.push(this.data_files[i].file_id);
            }
          }
        },
        onDatafileReplaced: function(event){
            this.dialog_datafile_import=false;            
            this.importSummaryStatistics(this.dialog_datafile_import_fid);
        },
        openExportDialog: function(file_index){
            let data_file = this.data_files[file_index];
            this.export_dialog.file_id = data_file.file_id;
            this.export_dialog.file_name = data_file.file_name;
            this.export_dialog.file_physical_name = data_file.file_physical_name || '';
            this.export_dialog.show = true;
        },
        // Warning text for a file whose variables don't all exist in the data ('' if none)
        columnsSyncWarning: function(data_file){
            let sync = data_file ? this.columns_sync[data_file.file_id] : null;
            if (!sync || !sync.csv_exists) return '';
            if (sync.csv_readable === false) return this.$t('data_file_csv_unreadable');
            if (sync.missing_count > 0) return this.$t('variables_not_in_data_count', {count: sync.missing_count});
            return '';
        },
        hasUnmatchedVariables: function(data_file){
            let sync = data_file ? this.columns_sync[data_file.file_id] : null;
            return !!(sync && sync.csv_exists && sync.csv_readable !== false && sync.missing_count > 0);
        },
        removeUnmatchedRequest: async function(dry_run){
            let url = CI.base_url + '/api/datafiles/remove_unmatched_variables/' + this.dataset_id;
            let response = await axios.post(url, {file_ids: this.unmatchedSelectedFiles, dry_run: dry_run});
            return (response.data && response.data.results) || [];
        },
        unmatchedErrorMessage: function(e){
            return (e.response && e.response.data && e.response.data.message) ? e.response.data.message : this.$t('failed');
        },
        openRemoveUnmatchedDialog: async function(){
            let d = this.dialog_unmatched;
            d.show = true;
            d.loading = true;
            d.applying = false;
            d.done = false;
            d.results = [];
            d.removed_count = 0;
            d.error = '';
            d.filter = '';
            d.only_kept = false;
            d.expanded = {};
            d.show_all = {};
            d.show_skipped = false;
            try {
                d.results = await this.removeUnmatchedRequest(true);
                this.expandUnmatchedDefaults();
            } catch (e) {
                d.error = this.unmatchedErrorMessage(e);
            }
            d.loading = false;
        },
        confirmRemoveUnmatched: async function(){
            let d = this.dialog_unmatched;
            d.applying = true;
            d.error = '';
            try {
                d.results = await this.removeUnmatchedRequest(false);
                this.expandUnmatchedDefaults();
                d.removed_count = d.results.reduce((n, r) => n + (r.status === 'ok' ? r.removed.length : 0), 0);
                d.done = true;
                for (let r of d.results) {
                    if (r.status === 'ok' && r.removed.length > 0) {
                        await this.reloadDataFileVariables(r.file_id);
                    }
                }
                await this.reloadDataFiles();
                this.selected_files = [];
                this.select_all_files = false;
                if (typeof EventBus !== 'undefined') {
                    EventBus.$emit('onSuccess', this.$t('remove_unmatched_variables_done', {count: d.removed_count}));
                }
            } catch (e) {
                d.error = this.unmatchedErrorMessage(e);
            }
            d.applying = false;
        },
        // Expand files that need attention (kept variables); everything else stays collapsed
        expandUnmatchedDefaults: function(){
            let d = this.dialog_unmatched;
            let expanded = {};
            d.results.forEach(r => {
                if (r.status === 'ok' && this.unmatchedKeptCount(r) > 0) expanded[r.file_id] = true;
            });
            d.expanded = expanded;
        },
        unmatchedKeptCount: function(r){
            return (r.skipped_case ? r.skipped_case.length : 0) + (r.skipped_weight ? r.skipped_weight.length : 0);
        },
        toggleUnmatchedRow: function(file_id){
            this.$set(this.dialog_unmatched.expanded, file_id, !this.dialog_unmatched.expanded[file_id]);
        },
        toggleUnmatchedShowAll: function(file_id){
            this.$set(this.dialog_unmatched.show_all, file_id, !this.dialog_unmatched.show_all[file_id]);
        },
        unmatchedVisibleNames: function(r){
            let names = r.removed.map(v => v.name);
            if (this.dialog_unmatched.show_all[r.file_id]) return names;
            return names.slice(0, this.unmatchedNameCap);
        },
        unmatchedFileName: function(file_id){
            let f = this.data_files.find(x => x.file_id === file_id);
            return f ? f.file_name : file_id;
        },
        openBatchExportDialog: function(){
            this.batch_export_dialog_show = true;
        },
        openSumStatsOptionsDialog: function(file_idx){
            let data_file = this.data_files[file_idx];
            if (!data_file) return;
            this.dialog_sum_stats_options.files = [{file_id: data_file.file_id, file_name: data_file.file_name}];
            this.dialog_sum_stats_options.show = true;
        },
        openBatchSumStatsOptionsDialog: function(){
            this.dialog_sum_stats_options.files = this.sumStatsBatchSelectedFiles;
            this.dialog_sum_stats_options.show = true;
        },
        confirmExport: function(){
            // This function is no longer needed as the export dialog handles everything
        },
        handleVisibilityChange: function() {
            // Refresh data files when page/tab becomes visible
            if (!document.hidden) {
                this.reloadDataFiles();
            }
        },
        // Client-side only column sorting (not persisted). Clicking a sortable
        // column header cycles asc -> desc -> unsorted (manual/drag order).
        toggleSort: function(column){
            if (this.sort_column !== column){
                this.sort_column = column;
                this.sort_direction = 'asc';
            } else if (this.sort_direction === 'asc'){
                this.sort_direction = 'desc';
            } else {
                this.sort_column = null;
                this.sort_direction = 'asc';
            }
        },
        sortIconClass: function(column){
            if (this.sort_column !== column) return 'mdi-unfold-more-horizontal';
            return this.sort_direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down';
        },
        parseFileSizeToBytes: function(size_display){
            if (!size_display || size_display === '—') return -1;
            let parts = size_display.trim().split(' ');
            let value = parseFloat(parts[0]);
            if (isNaN(value)) return -1;
            let units = {B:1, KB:1024, MB:1024*1024, GB:1024*1024*1024, TB:1024*1024*1024*1024};
            let unit = (parts[1] || '').toUpperCase();
            return value * (units[unit] || 1);
        },
        getSortValue: function(data_file, column){
            switch(column){
                case 'file_id':
                    return (data_file.file_id || '').toString().toLowerCase();
                case 'file_name':
                    return (data_file.file_name || '').toString().toLowerCase();
                case 'data':
                    return this.parseFileSizeToBytes(this.dataFileSizeDisplay(data_file));
                case 'var_count':
                    return Number(data_file.var_count) || 0;
                case 'case_count':
                    return Number(data_file.case_count) || 0;
                case 'changed':
                    return Number(data_file.changed) || 0;
                default:
                    return '';
            }
        },
    },
    computed: {
        data_files(){
            return this.$store.state.data_files;
        },
        // Wraps data_files with their original (store) index so row actions
        // that expect an index into data_files keep working when a sort is
        // applied. When no sort column is set, order matches data_files
        // exactly, so drag-to-reorder keeps working unaffected.
        sortedDataFiles(){
            let wrapped = this.data_files.map((file, originalIndex) => ({file, originalIndex}));
            if (!this.sort_column) return wrapped;
            let column = this.sort_column;
            let dir = this.sort_direction === 'desc' ? -1 : 1;
            wrapped.sort((a, b) => {
                let va = this.getSortValue(a.file, column);
                let vb = this.getSortValue(b.file, column);
                if (typeof va === 'string' && typeof vb === 'string'){
                    return va.localeCompare(vb, undefined, {numeric: true, sensitivity: 'base'}) * dir;
                }
                if (va < vb) return -1 * dir;
                if (va > vb) return 1 * dir;
                return 0;
            });
            return wrapped;
        },
        batchExportSelectedFiles(){
            if (!this.data_files.length || !this.selected_files.length) return [];
            return this.data_files
                .filter(f => this.selected_files.indexOf(f.file_id) !== -1 && (this.hasCsvFile(f.file_id) || f.store_data === 1))
                .map(f => ({ file_id: f.file_id, file_name: f.file_name, file_physical_name: f.file_physical_name || '' }));
        },        
        unmatchedSelectedFiles(){
            if (!this.data_files.length || !this.selected_files.length) return [];
            return this.data_files
                .filter(f => this.selected_files.indexOf(f.file_id) !== -1 && this.hasUnmatchedVariables(f))
                .map(f => f.file_id);
        },
        unmatchedRemoveCount(){
            return this.dialog_unmatched.results.reduce((n, r) => n + (r.status === 'ok' ? r.removed.length : 0), 0);
        },
        unmatchedNameCap(){
            return 30;
        },
        // Files with something to remove or keep; kept-first, then by file order
        unmatchedActiveRows(){
            let order = {};
            this.data_files.forEach((f, i) => { order[f.file_id] = i; });
            return this.dialog_unmatched.results
                .filter(r => r.status === 'ok' && (r.removed.length > 0 || this.unmatchedKeptCount(r) > 0))
                .slice()
                .sort((a, b) => {
                    let ka = this.unmatchedKeptCount(a) > 0 ? 0 : 1;
                    let kb = this.unmatchedKeptCount(b) > 0 ? 0 : 1;
                    if (ka !== kb) return ka - kb;
                    return (order[a.file_id] || 0) - (order[b.file_id] || 0);
                });
        },
        unmatchedFilteredRows(){
            let d = this.dialog_unmatched;
            let q = (d.filter || '').trim().toLowerCase();
            return this.unmatchedActiveRows.filter(r => {
                if (d.only_kept && this.unmatchedKeptCount(r) === 0) return false;
                if (!q) return true;
                return (r.file_id + ' ' + this.unmatchedFileName(r.file_id)).toLowerCase().indexOf(q) !== -1;
            });
        },
        unmatchedSkippedRows(){
            return this.dialog_unmatched.results.filter(r => r.status === 'skipped' || (r.status === 'ok' && r.removed.length === 0 && this.unmatchedKeptCount(r) === 0));
        },
        unmatchedFailedRows(){
            return this.dialog_unmatched.results.filter(r => r.status === 'failed');
        },
        unmatchedSummary(){
            let s = {files: 0, removed: 0, renames: 0, weights: 0};
            this.dialog_unmatched.results.forEach(r => {
                if (r.status !== 'ok') return;
                if (r.removed.length > 0) s.files++;
                s.removed += r.removed.length;
                s.renames += r.skipped_case.length;
                s.weights += r.skipped_weight.length;
            });
            s.kept = s.renames + s.weights;
            return s;
        },
        unmatchedShowTools(){
            return this.unmatchedActiveRows.length > 10;
        },
        sumStatsBatchSelectedFiles(){
            if (!this.data_files.length || !this.selected_files.length) return [];
            return this.data_files
                .filter(f => this.selected_files.indexOf(f.file_id) !== -1 && f.var_count > 0)
                .map(f => ({ file_id: f.file_id, file_name: f.file_name }));
        },
        importMetadataFileSelected(){
            var s = this.dialog_import_metadata.selected_file;
            if (!s) return false;
            return Array.isArray(s) ? s.length > 0 : true;
        },
    },
    template: `
        <div class="datfiles-component">
        
            <div class="container-fluid pt-5 mt-5 mb-5 pb-5">

            <v-card>
                <v-card-title>{{$t("data-files")}}</v-card-title>
                <v-card-text>
            <div v-show="page_action=='list'">

            <strong>{{data_files.length}}</strong> {{$t("files")}}

                <v-row>
                    <v-col md="8">
                    <button v-if="selected_files.length>0" type="button" class="btn btn-sm btn-outline-danger mr-2" @click="batchDelete">{{$t("Delete")}} {{selected_files.length}} {{$t("selected")}}</button>
                    <button v-if="selected_files.length>0" type="button" class="btn btn-sm btn-outline-primary mr-2" @click="openBatchExportDialog">{{$t("batch_export")}} ({{selected_files.length}})</button>
                    <button v-if="selected_files.length>0" type="button" class="btn btn-sm btn-outline-primary mr-2" :disabled="sumStatsBatchSelectedFiles.length===0" @click="openBatchSumStatsOptionsDialog">{{$t("summary_stats_options")}} ({{sumStatsBatchSelectedFiles.length}})</button>
                    <button v-if="unmatchedSelectedFiles.length>0" type="button" class="btn btn-sm btn-outline-warning" @click="openRemoveUnmatchedDialog">{{$t("remove_unmatched_variables")}} ({{unmatchedSelectedFiles.length}})</button>

                    </v-col>
                    <v-col md="4" align="right" class="mb-2">
                        <v-btn color="primary" :to="'datafiles/import'" outlined small>{{$t("import_files")}}</v-btn>
                    </v-col>
                </v-row>

                
                <table class="table table-striped" v-if="data_files.length>0">
                    <thead>
                    <tr>
                        <th><input type="checkbox" v-model="select_all_files" @change="toggleFilesSelection" /></th>
                        <th><span class="mdi mdi-swap-vertical"></span></th>
                        <th style="width:80px;cursor:pointer;user-select:none;" @click="toggleSort('file_id')">{{$t("file")}}# <v-icon small :color="sort_column=='file_id' ? 'primary' : 'grey lighten-1'">{{sortIconClass('file_id')}}</v-icon></th>
                        <th style="cursor:pointer;user-select:none;" @click="toggleSort('file_name')">{{$t("file_name")}} <v-icon small :color="sort_column=='file_name' ? 'primary' : 'grey lighten-1'">{{sortIconClass('file_name')}}</v-icon></th>
                        <th style="cursor:pointer;user-select:none;" @click="toggleSort('data')">{{$t("data")}} <v-icon small :color="sort_column=='data' ? 'primary' : 'grey lighten-1'">{{sortIconClass('data')}}</v-icon></th>
                        <th style="cursor:pointer;user-select:none;" @click="toggleSort('var_count')">{{$t("variables")}} <v-icon small :color="sort_column=='var_count' ? 'primary' : 'grey lighten-1'">{{sortIconClass('var_count')}}</v-icon></th>
                        <th style="cursor:pointer;user-select:none;" @click="toggleSort('case_count')">{{$t("cases")}} <v-icon small :color="sort_column=='case_count' ? 'primary' : 'grey lighten-1'">{{sortIconClass('case_count')}}</v-icon></th>
                        <th style="cursor:pointer;user-select:none;" @click="toggleSort('changed')">{{$t("Modified")}} <v-icon small :color="sort_column=='changed' ? 'primary' : 'grey lighten-1'">{{sortIconClass('changed')}}</v-icon></th>
                        <th style="width: 50px;">{{$t("Actions")}}</th>
                    </tr>
                    </thead>
                    <tbody is="draggable" :list="data_files" :disabled="sort_column !== null" tag="tbody" handle=".handle" >
                    <tr v-for="(row, row_index) in sortedDataFiles" :key="row.file.file_id">
                        <td><input type="checkbox" v-model="selected_files" :value="row.file.file_id" /></td>
                        <td><v-icon class="handle" :disabled="sort_column !== null" :color="sort_column ? 'grey lighten-2' : ''">mdi-drag</v-icon></td>
                        <td><v-icon color="primary" >mdi-file-document</v-icon> {{row.file.file_id}}</td>
                        <td>
                            <div class="d-flex align-center">
                                <div style="cursor:pointer;color:#0D47A1;font-weight:500" @click="editFile(row.originalIndex)">
                                    {{row.file.file_name}}
                                </div>
                                <v-tooltip bottom v-if="columnsSyncWarning(row.file)">
                                    <template v-slot:activator="{ on, attrs }">
                                        <v-icon small color="warning" class="ml-2" v-bind="attrs" v-on="on"
                                            style="cursor:pointer;"
                                            @click="$router.push('/variables-validation/' + row.file.file_id)">mdi-alert</v-icon>
                                    </template>
                                    <span>{{columnsSyncWarning(row.file)}}</span>
                                </v-tooltip>
                            </div>
                        </td>
                        <td class="text-secondary">{{dataFileSizeDisplay(row.file)}}</td>
                        <td>{{row.file.var_count}}</td>
                        <td>{{row.file.case_count}}</td>
                        <td>{{momentDate(row.file.changed)}}</td>
                        <td>
                            <div class="zxaction-buttons-hover">
                                <v-menu offset-y>
                                    <template v-slot:activator="{ on, attrs }">
                                            <v-btn small icon v-on="on" v-bind="attrs"
                                                   :title="$t('More options')"
                                                   color="primary">
                                                <v-icon>mdi-dots-vertical</v-icon>
                                            </v-btn>
                                    </template>

                                    <v-list dense>
                                        <!-- View/Edit Options -->
                                        <v-list-item @click="editFile(row.originalIndex)">
                                            <v-list-item-icon>
                                                <v-icon>mdi-file-edit</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("edit")}}</v-list-item-title>
                                        </v-list-item>

                                        <v-list-item
                                            :to="'/variables/' + row.file.file_id"
                                            :title="$t('Variables')">
                                            <v-list-item-icon>
                                                <v-icon>mdi-table</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("Variables")}}</v-list-item-title>
                                        </v-list-item>

                                        <v-list-item v-if="hasCsvFile(row.file.file_id) || row.file.store_data==1"
                                            :to="'/data-explorer/' + row.file.file_id"
                                            :title="$t('data')">
                                            <v-list-item-icon>
                                                <v-icon>mdi-table-eye</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("data")}}</v-list-item-title>
                                        </v-list-item>

                                        <v-list-item v-else disabled
                                            :title="$t('data')">
                                            <v-list-item-icon>
                                                <v-icon>mdi-table-eye</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("data")}} ({{$t("No data")}})</v-list-item-title>
                                        </v-list-item>

                                        <v-divider></v-divider>

                                        <!-- Data Management -->
                                        <v-list-item v-if="hasCsvFile(row.file.file_id) || row.file.store_data==1"
                                            @click="removeData(row.file)">
                                            <v-list-item-icon>
                                                <v-icon>mdi-database-remove</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("clear_data")}}</v-list-item-title>
                                        </v-list-item>

                                        <v-list-item v-else disabled>
                                            <v-list-item-icon>
                                                <v-icon>mdi-database-remove</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("clear_data")}} ({{$t("No data")}})</v-list-item-title>
                                        </v-list-item>

                                        <v-list-item @click="importSummaryStatistics(row.file.file_id)">
                                            <v-list-item-icon>
                                                <v-icon>mdi-update</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("Refresh summary statistics")}}</v-list-item-title>
                                        </v-list-item>

                                        <v-list-item v-if="row.file.var_count > 0" @click="openSumStatsOptionsDialog(row.originalIndex)">
                                            <v-list-item-icon>
                                                <v-icon>mdi-tune-variant</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("summary_stats_options")}}</v-list-item-title>
                                        </v-list-item>

                                        <v-list-item v-else disabled>
                                            <v-list-item-icon>
                                                <v-icon>mdi-tune-variant</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("summary_stats_options")}} ({{$t("no_variables")}})</v-list-item-title>
                                        </v-list-item>

                                        <v-list-item @click="replaceFile(row.originalIndex)">
                                            <v-list-item-icon>
                                                <v-icon>mdi-file-upload-outline</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("Replace file")}}</v-list-item-title>
                                        </v-list-item>

                                        <v-list-item @click="duplicateFile(row.originalIndex)">
                                            <v-list-item-icon>
                                                <v-icon>mdi-content-copy</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("duplicate_data_file")}}</v-list-item-title>
                                        </v-list-item>

                                        <v-list-item @click="exportMetadataFile(row.originalIndex)">
                                            <v-list-item-icon>
                                                <v-icon>mdi-code-json</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("export_metadata")}}</v-list-item-title>
                                        </v-list-item>

                                        <v-list-item @click="exportDictionaryCsv(row.originalIndex)">
                                            <v-list-item-icon>
                                                <v-icon>mdi-book-open-variant</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("export_data_dictionary")}}</v-list-item-title>
                                        </v-list-item>

                                        <v-list-item @click="exportVariableDocumentationCsv(row.originalIndex)">
                                            <v-list-item-icon>
                                                <v-icon>mdi-file-document-outline</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("export_variable_documentation")}}</v-list-item-title>
                                        </v-list-item>

                                        <v-list-item @click="openImportVariableDocumentationDialog(row.originalIndex)">
                                            <v-list-item-icon>
                                                <v-icon>mdi-file-upload-outline</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("import_variable_documentation")}}</v-list-item-title>
                                        </v-list-item>

                                        <v-list-item @click="openImportMetadataDialog(row.originalIndex)">
                                            <v-list-item-icon>
                                                <v-icon>mdi-file-import</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("import_metadata")}}</v-list-item-title>
                                        </v-list-item>

                                        <v-divider></v-divider>

                                        <!-- Export Options -->
                                        <v-list-item v-if="hasCsvFile(row.file.file_id) || row.file.store_data==1"
                                            @click="openExportDialog(row.originalIndex)">
                                            <v-list-item-icon>
                                                <v-icon>mdi-file-export</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("export")}}</v-list-item-title>
                                        </v-list-item>

                                        <v-list-item v-else disabled>
                                            <v-list-item-icon>
                                                <v-icon>mdi-file-export</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("export")}} ({{$t("No data")}})</v-list-item-title>
                                        </v-list-item>

                                        <v-divider></v-divider>

                                        <!-- Delete Option -->
                                        <v-list-item @click="deleteFile(row.originalIndex)" class="red--text">
                                            <v-list-item-icon>
                                                <v-icon color="red">mdi-delete-outline</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title class="red--text">{{$t("Delete")}}</v-list-item-title>
                                        </v-list-item>
                                    </v-list>
                                </v-menu>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
                
                
            </div>

            <div v-show="page_action=='edit'" >
                <div v-if="data_files[edit_item]">
                    <datafile-edit :value="data_files[edit_item]" @input="saveFile" @exit-edit="exitEditMode"></datafile-edit>                
                </div>
            </div>

            </v-card-text>
            </v-card>

            </div>

            <!-- dialog -->
            <v-dialog v-model="dialog.show" width="500" height="300" persistent>
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        {{dialog.title}}
                    </v-card-title>

                    <v-card-text>
                    <div>
                        <!-- card text -->
                        <div v-if="dialog.is_loading">{{dialog.loading_message}}</div>
                        <v-app>
                        <v-progress-linear v-if="dialog.is_loading"
                            indeterminate
                            color="green"
                            ></v-progress-linear>
                        </v-app>

                        <div class="alert alert-success" v-if="dialog.message_success" type="success">
                            {{dialog.message_success}}
                        </div>

                        <div v-html="dialog.message_success_html" v-if="dialog.message_success_html"></div>

                        <div v-if="dialog.download_links">
                            <div v-for="link in dialog.download_links">
                                <v-btn color="primary" outlined block text><a :href="link.url">{{link.title}}</a></v-btn>                                
                            </div>
                        </div>

                        <div class="alert alert-danger" v-if="dialog.message_error" type="error">
                            {{dialog.message_error}}
                        </div>

                        <!-- end card text -->
                    </div>
                    </v-card-text>

                    <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="primary" text @click="dialog.show=false" v-if="dialog.is_loading==false">
                        {{$t("close")}}
                    </v-btn>
                    </v-card-actions>
                </v-card>
                </v-dialog>
            <!-- end dialog -->           

            <dialog-datafile-replace 
                v-model="dialog_datafile_import" 
                :file_id="dialog_datafile_import_fid"
                v-on:file-replaced="onDatafileReplaced"
            ></dialog-datafile-replace>

            <!-- Export Dialog -->
            <dialog-datafile-export 
                v-model="export_dialog.show" 
                :file_id="export_dialog.file_id"
                :file_name="export_dialog.file_name"
                :file_physical_name="export_dialog.file_physical_name || ''">
            </dialog-datafile-export>

            <!-- Batch Export Dialog -->
            <dialog-batch-export
                v-model="batch_export_dialog_show"
                :selected-files="batchExportSelectedFiles">
            </dialog-batch-export>

            <!-- Remove variables not in data (multi-file batch) -->
            <v-dialog v-model="dialog_unmatched.show" max-width="760" persistent scrollable>
                <v-card>
                    <v-card-title class="text-h6 grey lighten-2">{{$t("remove_unmatched_variables")}}</v-card-title>
                    <v-card-text class="pt-4" style="max-height:70vh;">
                        <div v-if="dialog_unmatched.loading" class="d-flex align-center py-4">
                            <v-progress-circular indeterminate color="primary" size="28"></v-progress-circular>
                            <span class="ml-3">{{$t("loading")}}...</span>
                        </div>
                        <template v-else>
                            <div v-if="dialog_unmatched.error" class="alert alert-danger">{{dialog_unmatched.error}}</div>
                            <p v-if="!dialog_unmatched.done" class="text-body-2">{{$t("remove_unmatched_variables_help")}}</p>

                            <!-- Summary -->
                            <v-alert v-if="dialog_unmatched.results.length" dense outlined
                                :type="dialog_unmatched.done ? 'success' : (unmatchedSummary.removed > 0 ? 'info' : 'warning')" class="mb-3">
                                <div class="font-weight-medium">
                                    <template v-if="dialog_unmatched.done">{{$t("unmatched_summary_removed", {count: unmatchedSummary.removed, files: unmatchedSummary.files})}}</template>
                                    <template v-else-if="unmatchedSummary.removed > 0">{{$t("unmatched_summary_remove", {count: unmatchedSummary.removed, files: unmatchedSummary.files})}}</template>
                                    <template v-else>{{$t("remove_unmatched_variables_none")}}</template>
                                </div>
                                <div v-if="unmatchedSummary.kept > 0" class="warning--text text--darken-2 mt-1">
                                    <v-icon small color="warning">mdi-alert</v-icon>
                                    {{$t("unmatched_summary_kept", {count: unmatchedSummary.kept, renames: unmatchedSummary.renames, weights: unmatchedSummary.weights})}}
                                </div>
                            </v-alert>

                            <!-- Failed files -->
                            <div v-if="unmatchedFailedRows.length" class="alert alert-danger py-2">
                                <div class="font-weight-medium mb-1">{{$t("unmatched_failed_files", {count: unmatchedFailedRows.length})}}</div>
                                <div v-for="r in unmatchedFailedRows" :key="'unf-' + r.file_id" class="text-body-2">
                                    {{r.file_id}} — {{unmatchedFileName(r.file_id)}}: {{r.message}}
                                </div>
                            </div>

                            <!-- Filter (many files only) -->
                            <div v-if="unmatchedShowTools" class="d-flex align-center flex-wrap mb-2">
                                <input type="text" v-model="dialog_unmatched.filter" class="form-control form-control-sm mr-3 mb-1" style="max-width:260px;" :placeholder="$t('unmatched_filter_files')">
                                <label class="mb-1 text-body-2" style="cursor:pointer;" v-if="unmatchedSummary.kept > 0">
                                    <input type="checkbox" v-model="dialog_unmatched.only_kept" class="mr-1"> {{$t("unmatched_only_kept")}}
                                </label>
                            </div>

                            <!-- Per-file table -->
                            <table v-if="unmatchedActiveRows.length" class="table table-sm mb-2">
                                <thead>
                                    <tr>
                                        <th style="width:28px;"></th>
                                        <th>{{$t("file")}}</th>
                                        <th class="text-right" style="width:90px;">{{ dialog_unmatched.done ? $t("unmatched_col_removed") : $t("unmatched_col_remove") }}</th>
                                        <th class="text-right" style="width:70px;">{{$t("unmatched_col_kept")}}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="r in unmatchedFilteredRows">
                                        <tr :key="'unr-' + r.file_id" style="cursor:pointer;" @click="toggleUnmatchedRow(r.file_id)">
                                            <td><v-icon small>{{ dialog_unmatched.expanded[r.file_id] ? 'mdi-chevron-down' : 'mdi-chevron-right' }}</v-icon></td>
                                            <td>
                                                <span class="grey--text text--darken-1 mr-1">{{r.file_id}}</span>
                                                <span style="word-break:break-all;">{{unmatchedFileName(r.file_id)}}</span>
                                            </td>
                                            <td class="text-right">{{r.removed.length}}</td>
                                            <td class="text-right">
                                                <span v-if="unmatchedKeptCount(r) > 0" class="warning--text text--darken-2">
                                                    <v-icon x-small color="warning">mdi-alert</v-icon> {{unmatchedKeptCount(r)}}
                                                </span>
                                                <span v-else class="grey--text">—</span>
                                            </td>
                                        </tr>
                                        <tr v-if="dialog_unmatched.expanded[r.file_id]" :key="'und-' + r.file_id">
                                            <td></td>
                                            <td colspan="3" class="pt-0 pb-3" style="border-top:0;">
                                                <div v-if="r.skipped_case.length" class="text-body-2 warning--text text--darken-2 mb-1">
                                                    {{$t("unmatched_possible_rename")}}:
                                                    <span v-for="v in r.skipped_case" :key="'sc-' + v.name" class="mr-2"><code>{{v.name}}</code> ≈ <code>{{v.csv_column}}</code></span>
                                                </div>
                                                <div v-if="r.skipped_weight.length" class="text-body-2 warning--text text--darken-2 mb-1">
                                                    {{$t("unmatched_weight_variable")}}:
                                                    <code v-for="v in r.skipped_weight" :key="'sw-' + v.uid" class="mr-1">{{v.name}}</code>
                                                </div>
                                                <div v-if="r.removed.length" class="text-body-2">
                                                    <span class="grey--text text--darken-1">{{ dialog_unmatched.done ? $t("unmatched_col_removed") : $t("unmatched_to_remove") }}:</span>
                                                    <code v-for="name in unmatchedVisibleNames(r)" :key="'rm-' + r.file_id + '-' + name" class="mr-1">{{name}}</code>
                                                    <a v-if="r.removed.length > unmatchedNameCap" href="#" class="text-caption" @click.prevent.stop="toggleUnmatchedShowAll(r.file_id)">
                                                        {{ dialog_unmatched.show_all[r.file_id] ? $t("show_less") : $t("show_all_n", {count: r.removed.length}) }}
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr v-if="unmatchedFilteredRows.length === 0">
                                        <td colspan="4" class="text-body-2 grey--text">{{$t("unmatched_no_files_match_filter")}}</td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- Skipped files (no CSV, unreadable, nothing to do) -->
                            <div v-if="unmatchedSkippedRows.length" class="text-body-2">
                                <a href="#" class="grey--text text--darken-1" @click.prevent="dialog_unmatched.show_skipped = !dialog_unmatched.show_skipped">
                                    <v-icon small>{{ dialog_unmatched.show_skipped ? 'mdi-chevron-down' : 'mdi-chevron-right' }}</v-icon>
                                    {{$t("unmatched_skipped_files", {count: unmatchedSkippedRows.length})}}
                                </a>
                                <div v-if="dialog_unmatched.show_skipped" class="pl-5 pt-1">
                                    <div v-for="r in unmatchedSkippedRows" :key="'uns-' + r.file_id" class="grey--text text--darken-1">
                                        {{r.file_id}} — {{unmatchedFileName(r.file_id)}}:
                                        {{ r.message==='no_csv' ? $t('unmatched_no_csv') : (r.message==='csv_unreadable' ? $t('unmatched_csv_unreadable') : $t('remove_unmatched_variables_none')) }}
                                    </div>
                                </div>
                            </div>
                        </template>
                    </v-card-text>
                    <v-divider></v-divider>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn text @click="dialog_unmatched.show=false" :disabled="dialog_unmatched.applying">{{ (dialog_unmatched.done || unmatchedRemoveCount===0) ? $t("close") : $t("cancel") }}</v-btn>
                        <v-btn v-if="!dialog_unmatched.done && !dialog_unmatched.loading && unmatchedRemoveCount > 0"
                            color="error" dark depressed
                            :loading="dialog_unmatched.applying"
                            @click="confirmRemoveUnmatched">{{$t("remove_n_variables", {count: unmatchedRemoveCount})}}</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <!-- Summary Stats Options Dialog (row action or multi-file batch) -->
            <dialog-datafiles-sum-stats-options
                v-model="dialog_sum_stats_options.show"
                :selected-files="dialog_sum_stats_options.files">
            </dialog-datafiles-sum-stats-options>

            <!-- Import variable documentation CSV dialog -->
            <v-dialog v-model="dialog_import_variable_documentation.show" max-width="500" persistent>
                <v-card>
                    <v-card-title class="text-h6 grey lighten-2">
                        {{$t("import_variable_documentation")}}
                    </v-card-title>
                    <v-card-text>
                        <p class="mb-3">{{$t("import_variable_documentation_help")}}</p>
                        <v-file-input
                            v-model="dialog_import_variable_documentation.selected_file"
                            accept=".csv,text/csv"
                            label=""
                            outlined
                            truncate-length="50"
                            dense
                            clearable
                            prepend-icon=""
                            prepend-inner-icon="mdi-paperclip"
                            show-size
                        ></v-file-input>
                        <div class="alert alert-success mt-3" v-if="dialog_import_variable_documentation.message_success">
                            {{dialog_import_variable_documentation.message_success}}
                        </div>
                        <div class="alert alert-danger mt-3" v-if="dialog_import_variable_documentation.message_error">
                            {{dialog_import_variable_documentation.message_error}}
                        </div>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn small text @click="dialog_import_variable_documentation.show = false" :disabled="dialog_import_variable_documentation.uploading">
                            {{$t("close")}}
                        </v-btn>
                        <v-btn small color="primary" @click="submitImportVariableDocumentation" :loading="dialog_import_variable_documentation.uploading">
                            {{$t("import")}}
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <!-- Import metadata (replace) dialog -->
            <v-dialog v-model="dialog_import_metadata.show" max-width="500" persistent>
                <v-card>
                    <v-card-title class="text-h6 grey lighten-2">
                        {{$t("import_metadata")}}
                    </v-card-title>
                    <v-card-text>
                        <p class="mb-3">{{$t("import_metadata_replace_help")}}</p>
                        <v-file-input
                            v-model="dialog_import_metadata.selected_file"
                            accept=".json,application/json"
                            label=""
                            outlined
                            truncate-length="50"
                            dense
                            clearable
                            prepend-icon=""
                            prepend-inner-icon="mdi-paperclip"
                            show-size
                        ></v-file-input>
                        <div class="alert alert-success mt-3" v-if="dialog_import_metadata.message_success">
                            {{dialog_import_metadata.message_success}}
                        </div>
                        <div class="alert alert-danger mt-3" v-if="dialog_import_metadata.message_error">
                            {{dialog_import_metadata.message_error}}
                        </div>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn small text @click="dialog_import_metadata.show = false" :disabled="dialog_import_metadata.uploading">
                            {{$t("close")}}
                        </v-btn>
                        <v-btn small class="mr-2" color="primary" @click="submitImportMetadata"
                               :disabled="!importMetadataFileSelected || dialog_import_metadata.uploading"
                               :loading="dialog_import_metadata.uploading">
                            {{$t("import_metadata")}}
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        
        </div>
    `
})