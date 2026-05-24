/// publish project options
Vue.component('publish-options', {
    props:['value'],
    data: function () {    
        return {
            field_data: this.value,
            project_info: {},//study_metadata_idno
            resources_selected:[],
            toggle_resources_selected:false,
            resources_overwrite:"yes",
            delete_nada_resources_before_publish:false,
            publish_selection_snapshot:null,
            publish_metadata:true,
            dialog_process:false,
            publish_thumbnail:true,
            publish_resources:true,
            publish_dsd:true,
            dsd_overwrite:false,
            indicator_publish_defaults_key:null,
            publish_indicator_data:true,
            indicator_publish:null,
            nada_version:null,
            nada_resumable_uploads:false,
            catalog_connections:[],
            panels: [1, 2],
            catalog:false,
            publish_options:{
                "overwrite": {
                    "title":this.$t("overwrite_if_already_exists"),
                    "value":"no",
                    "type":"text",
                    "enum": {
                        "yes":this.$t("yes"),
                        "no":this.$t("no")
                    }                    
                },
                "published":
                {
                    "title":this.$t("publish"),
                    "value":0,
                    "type":"text",
                    "enum":{
                        "0": this.$t("draft"),
                        "1": this.$t("publish")
                    }
                },
                "access_policy":{
                    "title":this.$t("data_access"),
                    "value":"data_na",
                    "type":"text",
                    "custom":true,
                    "enum":{
                        "direct": this.$t("direct_access"),
                        "public": this.$t("public_use_files"),
                        "licensed": this.$t("licensed_data_files"),
                        "remote": this.$t("data_accessible_only_in_data_enclave"),
                        "enclave": this.$t("data_available_from_external_repository"),
                        "": this.$t("data_not_available"),
                        "open": this.$t("open_access")
                    }
                },
                "data_remote_url":{
                    "custom":true,
                    "title":this.$t("data_access_link"),
                    "value":'',
                    "type":"text"
                },
                "repositoryid":{
                    "custom":true,
                    "title":this.$t("collection"),
                    "value":'',
                    "type":"text"
                },
            },            
            
            file:'',
            update_status:'',
            publish_processing_message:'',
            publish_progress_percent:null,
            publish_upload_detail:null,
            publish_file_upload_active:false,
            publish_cancel_requested:false,
            publish_was_cancelled:false,
            activeUploadCancel:null,
            is_publishing:false,
            is_publishing_completed:false,
            project_export_status:'',
            collections_codes:[],
            collections_linked:[],
            data_access_list:[],
            study_info: null,
            publish_responses:{}//all publish responses                        
        }
    },
    mounted: async function(){
        this.loadCatalogConnections();
        this.getProjectBasicInfo();
        var vm = this;
        this.$nextTick(function () {
            vm.panels = vm.getDefaultExpandedPanels();
            vm.applyPublishOptionDefaults();
        });
    },
    methods:{
        getExternalResourcesPanelIndex: function() {
            return this.isIndicatorProject ? 3 : 2;
        },
        getDefaultExpandedPanels: function() {
            var externalPanel = this.getExternalResourcesPanelIndex();
            if (this.isIndicatorProject) {
                return [1, 2, externalPanel];
            }
            return [1, externalPanel];
        },
        applyPublishOptionDefaults: function() {
            this.publish_metadata = true;
            this.publish_thumbnail = this.hasProjectThumbnail;
            this.resources_overwrite = 'yes';

            var resources = this.ExternalResources;
            if (!resources || resources.length === 0) {
                this.publish_resources = false;
                this.resources_selected = [];
                this.toggle_resources_selected = false;
            } else {
                this.publish_resources = true;
                var selected = [];
                for (var i = 0; i < resources.length; i++) {
                    selected.push(i);
                }
                this.resources_selected = selected;
                this.toggle_resources_selected = true;
            }

            if (!this.isIndicatorProject) {
                this.publish_dsd = false;
                this.publish_indicator_data = false;
                this.dsd_overwrite = false;
                return;
            }

            if (!this.indicator_publish || !this.indicator_publish.local) {
                this.publish_dsd = false;
                this.publish_indicator_data = false;
                this.dsd_overwrite = false;
                return;
            }

            var local = this.indicator_publish.local;
            var nadaDsd = this.indicator_publish.nada_dsd;
            var refIdno = local.data_structure_reference && local.data_structure_reference.idno
                ? String(local.data_structure_reference.idno)
                : '';
            var defaultsKey = String(this.catalog) + ':' + refIdno;
            var isNewContext = defaultsKey !== this.indicator_publish_defaults_key;

            this.publish_dsd = !!local.bound;
            this.publish_indicator_data = !!local.has_published_data
                && (this.studyExistsOnNada || this.publish_metadata);

            if (isNewContext) {
                this.dsd_overwrite = !!(nadaDsd && nadaDsd.exists && nadaDsd.matches_local === false);
                this.indicator_publish_defaults_key = defaultsKey;
            }
        },
        getProjectBasicInfo: function(){
            let url=CI.site_url + '/api/editor/basic_info/'+this.ProjectID;
            let vm=this;

            axios.get(url)
            .then(function (response) {
                if (response.data.project){                    
                    vm.project_info=response.data.project;
                    vm.applyPublishOptionDefaults();
                }
                else{
                    alert(vm.$t("project_metadata_not_found"));
                    console.log("Project metadata not found", response);
                }
            })
            .catch(function (error) {
                alert(vm.$t("failed_to_load_project_metadata"));
                console.log(error);
            })
        },

        capturePublishSelectionSnapshot: function() {
            this.publish_selection_snapshot = {
                publish_metadata: this.publish_metadata === true,
                publish_thumbnail: this.publish_thumbnail === true,
                publish_resources: this.publish_resources === true,
                publish_dsd: !!this.publish_dsd,
                publish_indicator_data: !!this.publish_indicator_data,
                delete_nada_resources: !!this.delete_nada_resources_before_publish,
                is_indicator_project: !!this.isIndicatorProject,
                resources_selected_count: Array.isArray(this.resources_selected)
                    ? this.resources_selected.length
                    : 0,
            };
        },
        isPublishCancelled: function() {
            return this.publish_cancel_requested === true;
        },
        isUploadCancelledError: function(error) {
            if (!error) {
                return false;
            }
            if (error.name === 'UploadCancelledError') {
                return true;
            }
            if (typeof NadaPublishUploader !== 'undefined'
                && NadaPublishUploader.UploadCancelledError
                && error instanceof NadaPublishUploader.UploadCancelledError) {
                return true;
            }
            return false;
        },
        requestPublishCancel: async function() {
            if (!this.is_publishing || this.publish_cancel_requested) {
                return;
            }
            this.publish_cancel_requested = true;
            this.publish_processing_message = this.$t('cancelling_publish');
            if (typeof this.activeUploadCancel === 'function') {
                try {
                    await this.activeUploadCancel();
                } catch (error) {
                    console.log('upload cancel failed', error);
                }
            }
        },
        initPublishResponses: function(){
            this.publish_responses={
                "export":[],
                "metadata":{
                    "messages":[],
                    "errors":[],
                },
                "thumbnail":{
                    "messages":[],
                    "errors":[],
                },
                "external_resources":{
                    "messages":[],
                    "errors":[],
                    //"resource.id, resource_title",
                    //"error_response"
                },
                "dsd":{
                    "messages":[],
                    "errors":[],
                },
                "indicator_data":{
                    "messages":[],
                    "errors":[],
                }
            };
        },
        /**
         * Classify a string body from a failed HTTP response (JSON vs HTML vs plain text).
         */
        detectErrorBodyFormat: function (s) {
            if (typeof s !== 'string') {
                return 'text';
            }
            var t = s.trim();
            if (/^<!DOCTYPE/i.test(t) || /^<html/i.test(t)) {
                return 'html';
            }
            if (t.charAt(0) === '<' && t.indexOf('>') > 1) {
                return 'html';
            }
            if (t.charAt(0) === '{' || t.charAt(0) === '[') {
                try {
                    JSON.parse(t);
                    return 'json';
                } catch (e) {
                    return 'text';
                }
            }
            return 'text';
        },
        formatJsonForDisplay: function (obj) {
            try {
                return JSON.stringify(obj, null, 2);
            } catch (e) {
                return String(obj);
            }
        },
        catalogApiFailed: function (data) {
            if (!data || typeof data !== 'object') {
                return true;
            }
            if (data.status === 'failed' || data.status === 'error') {
                return true;
            }
            return false;
        },
        metadataPublishSucceeded: function (data) {
            if (!data || typeof data !== 'object') {
                return true;
            }
            if (data.status === 'failed' || data.status === 'error') {
                return false;
            }
            return true;
        },
        saveUnsavedProjectIfNeeded: async function () {
            var root = this.$root;
            if (!root || !root.is_dirty) {
                return true;
            }
            if (!confirm(this.$t('publish_save_before_confirm'))) {
                return false;
            }
            var form_data = JSON.parse(JSON.stringify(this.$store.state.formData));
            if (typeof root.removeEmpty === 'function') {
                root.removeEmpty(form_data);
            }
            var url = CI.base_url + '/api/editor/update/' + this.ProjectType + '/' + this.ProjectID;
            try {
                await axios.post(url, form_data);
                root.is_dirty = false;
                await this.getProjectBasicInfoAsync();
                return true;
            } catch (error) {
                alert(this.$t('publish_save_before_failed'));
                return false;
            }
        },
        getProjectBasicInfoAsync: function () {
            var vm = this;
            var url = CI.site_url + '/api/editor/basic_info/' + this.ProjectID;
            return axios.get(url).then(function (response) {
                if (response.data.project) {
                    vm.project_info = response.data.project;
                    vm.applyPublishOptionDefaults();
                }
            });
        },
        exportGet: async function (url, errorLabel) {
            try {
                await axios.get(url);
                return true;
            } catch (error) {
                var msg = errorLabel;
                if (error.response && error.response.data) {
                    var d = error.response.data;
                    if (typeof d.message === 'string' && d.message !== '') {
                        msg += ': ' + d.message;
                    } else if (typeof d === 'string') {
                        msg += ': ' + d;
                    }
                } else if (error.message) {
                    msg += ': ' + error.message;
                }
                this.publish_responses.export.push(msg);
                return false;
            }
        },
        /**
         * Normalize axios errors from /api/publish/* for safe UI (escape via {{ }}, no v-html).
         * Handles JSON and HTML bodies from NADA or the editor API.
         */
        normalizePublishError: function (error) {
            var out = {
                summary: 'Request failed',
                httpStatus: null,
                appStatus: null,
                bodyFormat: null,
                message: '',
                nada: null,
                rawBody: '',
                jsonDetail: null,
                ddiFallback: null
            };
            if (!error || !error.response) {
                out.summary = (error && error.message) ? error.message : 'Network error — no response from server.';
                return out;
            }
            out.httpStatus = error.response.status;
            var d = error.response.data;
            if (d == null) {
                out.summary = 'Empty error response (HTTP ' + out.httpStatus + ')';
                return out;
            }
            if (typeof d === 'string') {
                out.bodyFormat = this.detectErrorBodyFormat(d);
                out.rawBody = d.length > 20000 ? d.substring(0, 20000) + '\n…' : d;
                if (out.bodyFormat === 'json') {
                    try {
                        out.jsonDetail = JSON.parse(d);
                    } catch (e) {}
                }
                out.summary = out.bodyFormat === 'html'
                    ? 'Server returned HTML (HTTP ' + out.httpStatus + ')'
                    : (out.bodyFormat === 'json' ? 'Server returned JSON (HTTP ' + out.httpStatus + ')' : 'Server returned text (HTTP ' + out.httpStatus + ')');
                return out;
            }
            out.appStatus = d.status;
            out.message = typeof d.message === 'string' ? d.message : '';
            out.nada = d.response && typeof d.response === 'object' ? d.response : null;
            if (out.nada && out.nada.ddi_fallback_error) {
                out.ddiFallback = out.nada.ddi_fallback_error;
            }
            if (out.nada && out.nada.body_format) {
                out.bodyFormat = out.nada.body_format;
            } else if (out.message) {
                out.bodyFormat = this.detectErrorBodyFormat(out.message);
            }
            if (out.nada && out.nada.raw_body) {
                out.rawBody = out.nada.raw_body;
            } else if (out.message && (out.bodyFormat === 'html' || out.bodyFormat === 'text')) {
                out.rawBody = out.message;
            }
            if (out.nada && out.nada.response_ != null) {
                out.jsonDetail = out.nada.response_;
            } else if (out.message && out.bodyFormat === 'json') {
                try {
                    out.jsonDetail = JSON.parse(out.message);
                } catch (e) {}
            }
            if (out.nada && out.nada.status) {
                out.summary = 'Catalog HTTP ' + out.nada.status;
                if (out.message && out.message.length < 400) {
                    out.summary += ': ' + out.message;
                }
            } else if (out.message) {
                out.summary = out.message.length > 400 ? out.message.substring(0, 400) + '…' : out.message;
            } else {
                out.summary = 'Failed (HTTP ' + out.httpStatus + ')';
            }
            return out;
        },
        toggleSelectedResources: function()
        {
            this.resources_selected = [];
            if (this.toggle_resources_selected == true) {                
                for (let i = 0; i < this.ExternalResources.length; i++) {
                this.resources_selected.push(i);
                }
            }
        },
        publishToCatalog: async function()
        {            
            if (this.catalog===false){
                alert(this.$t("select_catalog_for_publishing"));
                return false;
            }

            if(!this.hasAnyPublishSelection){
                alert(this.$t("please_select_at_least_one_option_to_publish"));
                return;
            }

            if (this.isIndicatorProject && !this.hasStudyIdno) {
                alert(this.$t('publish_study_idno_missing'));
                return false;
            }

            if (this.isIndicatorProject && this.publish_indicator_data && !this.indicatorDataPublishAllowed) {
                alert(this.$t('indicator_data_requires_metadata'));
                return false;
            }

            this.dialog_process=true;
            let formData=this.PublishOptions;
            vm=this;

            this.initPublishResponses();
            this.capturePublishSelectionSnapshot();
            this.is_publishing=true;
            this.is_publishing_completed=false;
            this.publish_progress_percent=null;
            this.publish_upload_detail=null;
            this.publish_file_upload_active=false;
            this.publish_cancel_requested=false;
            this.publish_was_cancelled=false;
            this.activeUploadCancel=null;

            if (!await this.saveUnsavedProjectIfNeeded()) {
                this.is_publishing=false;
                this.dialog_process=false;
                return false;
            }

            this.publish_processing_message=this.$t("preparing_project_export");
            var exportOk = await this.prepareProjectExport();
            if (!exportOk) {
                this.publish_processing_message=this.$t("publishing_completed");
                this.is_publishing=false;
                this.is_publishing_completed=true;
                return false;
            }
            if (this.isPublishCancelled()) {
                this.completePublishRun(true);
                return false;
            }

            if (this.publish_metadata==true){
                this.publish_processing_message=this.$t("publishing_project_metadata");
                var metadataOk = await this.publishProjectMetadata();
                if (metadataOk !== true) {
                    this.completePublishRun();
                    return false;
                }
            }
            if (this.isPublishCancelled()) {
                this.completePublishRun(true);
                return false;
            }

            if (this.isIndicatorProject && this.publish_dsd) {
                this.publish_processing_message=this.$t("publishing_dsd_to_nada");
                if (this.nadaDsdExists && this.dsd_overwrite !== true) {
                    this.publish_responses.dsd.messages.push(this.$t('dsd_skipped_already_exists'));
                } else {
                    await this.publishIndicatorExtras({
                        publish_dsd: true,
                        dsd_overwrite: this.dsd_overwrite === true,
                        publish_indicator_data: false
                    });
                }
            }
            if (this.isPublishCancelled()) {
                this.completePublishRun(true);
                return false;
            }

            if (this.isIndicatorProject && this.publish_indicator_data) {
                try {
                    await this.publishIndicatorDataStepByStep();
                } catch (error) {
                    if (this.isUploadCancelledError(error)) {
                        this.completePublishRun(true);
                        return false;
                    }
                }
            }
            if (this.isPublishCancelled()) {
                this.completePublishRun(true);
                return false;
            }

            if (this.publish_thumbnail==true){
                this.publish_processing_message=this.$t("publishing_project_thumbnail");
                try{
                    await this.publishProjectThumbnail();
                    this.publish_responses.thumbnail.messages.push(this.$t("thumbnail_published_successfully"));
                }catch(error){
                    console.log("publishing thumbnail failed", error);
                    this.publish_responses.thumbnail.errors.push(this.normalizePublishError(error));
                }
            }
            if (this.isPublishCancelled()) {
                this.completePublishRun(true);
                return false;
            }

            if (this.delete_nada_resources_before_publish && this.studyExistsOnNada) {
                await this.deleteNadaResourcesIfRequested();
            }
            if (this.isPublishCancelled()) {
                this.completePublishRun(true);
                return false;
            }

            if (this.publish_resources==true){
                this.publish_processing_message=this.$t("publishing_external_resources");
                try {
                    await this.publishExternalResoures();
                } catch (error) {
                    if (this.isUploadCancelledError(error)) {
                        this.completePublishRun(true);
                        return false;
                    }
                }
            }

            this.completePublishRun();
        },
        completePublishRun: function(wasCancelled) {
            var cancelled = wasCancelled === true || this.publish_cancel_requested === true;
            this.publish_file_upload_active = false;
            this.activeUploadCancel = null;
            this.publish_progress_percent = null;
            this.publish_upload_detail = null;
            this.publish_cancel_requested = false;
            if (cancelled) {
                this.publish_was_cancelled = true;
                this.publish_processing_message = this.$t('publish_cancelled');
            } else {
                this.publish_processing_message=this.$t("publish_completed_refreshing");
                this.loadCatalogInfo();
                this.publish_processing_message=this.$t("publishing_completed");
            }
            this.is_publishing=false;
            this.is_publishing_completed=true;
        },
        publishProjectMetadata: async function()
        {
            let formData=this.PublishOptions;
            vm=this;

            let nada_catalog=this.getConnectionInfo(this.catalog);
            
            if(!nada_catalog){
                alert(this.$t("catalog_was_not_found"));
                return false;
            }

            let url=CI.site_url + '/api/publish/' +this.ProjectID +'/' + nada_catalog.id;
        
            try {
                const response = await axios.post(url, formData, {});
                var data = response.data;
                if (!vm.metadataPublishSucceeded(data)) {
                    var failErr = {
                        response: {
                            status: response.status,
                            data: data && typeof data === 'object' ? data : { status: 'failed', message: String(data) }
                        }
                    };
                    vm.publish_responses.metadata.errors.push(vm.normalizePublishError(failErr));
                    return false;
                }
                var successMsg = vm.$t("metadata_publishing_updated_successfully");
                if (data && data.dataset && data.dataset.idno) {
                    successMsg = vm.$t("metadata_published_with_idno") + data.dataset.idno;
                }
                vm.publish_responses.metadata.messages.push(successMsg);
                return true;
            } catch (error) {
                console.log("publishing project failed", error);
                vm.publish_responses.metadata.errors.push(vm.normalizePublishError(error));
                return false;
            }
        },
        deleteNadaResourcesIfRequested: async function()
        {
            if (!this.delete_nada_resources_before_publish || !this.studyExistsOnNada) {
                return;
            }

            if (!window.confirm(this.$t('confirm_delete_all_nada_resources'))) {
                return;
            }

            try {
                this.publish_processing_message = this.$t('deleting_all_nada_resources');
                await this.deleteAllNadaResourcesBeforePublish();
                this.publish_responses.external_resources.messages.push(
                    this.$t('nada_resources_deleted_successfully')
                );
            } catch (error) {
                console.log('delete all NADA resources failed', error);
                this.publish_responses.external_resources.errors.push(this.normalizePublishError(error));
            }
        },
        deleteAllNadaResourcesBeforePublish: async function()
        {
            let nada_catalog = this.getConnectionInfo(this.catalog);
            if (!nada_catalog) {
                throw new Error(this.$t('catalog_was_not_found'));
            }

            let url = CI.site_url + '/api/publish/nada_resources_delete_all/'
                + this.ProjectID + '/' + nada_catalog.id;

            const response = await axios.post(url, {});
            if (response.data && response.data.status === 'failed') {
                throw new Error(response.data.message || 'Delete failed');
            }
            return response.data;
        },
        publishExternalResoures:  async function() 
        {
            if (this.resources_selected.length==0){
                return;
            }

            let vm=this;

            for (const idx of this.resources_selected) {
                if (vm.isPublishCancelled()) {
                    break;
                }
                vm.publish_processing_message=vm.$t("publishing_external_resource") + ": " + vm.ExternalResources[idx].title;
                try {
                    await vm.publishExternalResourceStepByStep(vm.ExternalResources[idx]);
                    vm.publish_responses.external_resources.messages.push( 
                        vm.ExternalResources[idx].title + ' ' + vm.$t("published_successfully")
                    );
                } catch (error) {
                    if (vm.isUploadCancelledError(error)) {
                        throw error;
                    }
                    console.error('Request ' + (idx + 1) + ' failed:', error.response || error);
                    var base = vm.normalizePublishError(error);
                    vm.publish_responses.external_resources.errors.push(Object.assign({}, base, {
                        resource_id: vm.ExternalResources[idx].id,
                        resource_title: vm.ExternalResources[idx].title
                    }));
                }
            }
        },
        resourceHasLocalFile: function(resource)
        {
            if (!resource || resource.filename === undefined || resource.filename === null) {
                return false;
            }
            var filename = String(resource.filename).trim();
            if (filename === '') {
                return false;
            }
            return !/^https?:\/\//i.test(filename);
        },
        updatePublishUploadProgress: function(progress, uploadLabel) {
            var percent = Number(progress && progress.progress);
            if (isNaN(percent) && progress && progress.total_chunks > 0) {
                percent = Math.round((Number(progress.uploaded_chunks) / Number(progress.total_chunks)) * 100);
            }
            if (isNaN(percent)) {
                percent = 0;
            }
            this.publish_progress_percent = percent;
            if (progress && progress.total_chunks != null) {
                this.publish_upload_detail = {
                    uploaded_chunks: progress.uploaded_chunks,
                    total_chunks: progress.total_chunks
                };
            }
            if (uploadLabel) {
                this.publish_processing_message = uploadLabel;
            }
        },
        uploadServerFileToNada: async function(options)
        {
            if (typeof NadaPublishUploader === 'undefined') {
                throw new Error('NADA publish uploader is not loaded');
            }

            let vm = this;
            let nada_catalog = options.catalogConnectionId
                ? { id: options.catalogConnectionId }
                : this.getConnectionInfo(this.catalog);
            if (!nada_catalog) {
                throw new Error(this.$t('catalog_was_not_found'));
            }

            vm.publish_file_upload_active = true;
            vm.publish_progress_percent = 0;
            vm.publish_upload_detail = null;
            if (options.uploadLabel) {
                vm.publish_processing_message = options.uploadLabel;
            }
            const uploadHandle = NadaPublishUploader.uploadServerSourceToNada({
                catalogConnectionId: nada_catalog.id,
                projectId: vm.ProjectID,
                source: options.source,
                resourceId: options.resourceId,
                onProgress: function (progress) {
                    vm.updatePublishUploadProgress(progress, options.uploadLabel);
                }
            });
            vm.activeUploadCancel = uploadHandle.cancel;

            try {
                const uploadResult = await uploadHandle.promise;
                vm.publish_progress_percent = 100;
                if (vm.publish_upload_detail) {
                    vm.publish_upload_detail.uploaded_chunks = vm.publish_upload_detail.total_chunks;
                }
                return uploadResult;
            } finally {
                vm.publish_file_upload_active = false;
                vm.activeUploadCancel = null;
                if (!vm.isPublishCancelled()) {
                    vm.publish_progress_percent = null;
                    vm.publish_upload_detail = null;
                }
            }
        },
        publishExternalResourceStepByStep: async function(resource)
        {
            let nada_catalog=this.getConnectionInfo(this.catalog);

            if(!nada_catalog){
                alert(this.$t("catalog_was_not_found"));
                return false;
            }

            let vm=this;
            let formData={
                "overwrite": this.resources_overwrite,
                "resource_id": resource.id,
                "sid": this.ProjectID,
                "catalog_id": nada_catalog.id
            };
            let url=CI.site_url + '/api/publish/external_resource/'+this.ProjectID +'/' + nada_catalog.id;

            if (this.catalogSupportsResumableUploads && this.resourceHasLocalFile(resource)) {
                const uploadLabel = vm.$t('uploading_external_resource_to_nada') + ': ' + resource.title;

                const uploadResult = await vm.uploadServerFileToNada({
                    source: 'external_resource',
                    resourceId: resource.id,
                    uploadLabel: uploadLabel
                });

                vm.publish_processing_message = vm.$t('publishing_external_resource') + ': ' + resource.title;
                formData.nada_upload_id = uploadResult.upload_id;
            }

            return axios.post(url, formData, {});
        },
        publishSingleResource: async function(resource)
        {
            return this.publishExternalResourceStepByStep(resource);
        },
        publishProjectThumbnail: async function()
        {
            let formData={
            }

            //let nada_catalog=this.catalog_connections[this.catalog];          
            let nada_catalog=this.getConnectionInfo(this.catalog);  

            if(!nada_catalog){
                alert(this.$t("catalog_was_not_found"));
                return false;
            }

            vm=this;            
            let url=CI.site_url + '/api/publish/thumbnail/'+this.ProjectID +'/' + nada_catalog.id;

            return axios.post(url,
                formData,
                {}            
            );
        },
        publishIndicatorExtras: async function(options)
        {
            let nada_catalog=this.getConnectionInfo(this.catalog);
            if(!nada_catalog){
                alert(this.$t("catalog_was_not_found"));
                return false;
            }

            let vm=this;
            let url=CI.site_url + '/api/publish/indicator/'+this.ProjectID +'/' + nada_catalog.id;

            try {
                const { data } = await axios.post(url, options || {});
                if (options && options.publish_dsd && data && data.dsd) {
                    if (data.dsd.status === 'skipped') {
                        vm.publish_responses.dsd.messages.push(vm.$t('dsd_skipped_already_exists'));
                    } else {
                        vm.publish_responses.dsd.messages.push(vm.$t('dsd_published_successfully'));
                    }
                }
                if (options && options.publish_indicator_data && data && data.data) {
                    vm.publish_responses.indicator_data.messages.push(vm.$t('indicator_data_published_successfully'));
                }
            } catch (error) {
                console.log('indicator publish failed', error);
                var normalized = vm.normalizePublishError(error);
                if (options && options.publish_dsd) {
                    vm.publish_responses.dsd.errors.push(normalized);
                }
                if (options && (options.publish_indicator_data || options.nada_upload_id)) {
                    vm.publish_responses.indicator_data.errors.push(normalized);
                }
            }
        },
        publishIndicatorDataStepByStep: async function()
        {
            let nada_catalog=this.getConnectionInfo(this.catalog);
            if(!nada_catalog){
                alert(this.$t("catalog_was_not_found"));
                return false;
            }

            let vm=this;

            try {
                const uploadLabel = vm.$t('uploading_indicator_csv_to_nada');
                const uploadResult = await vm.uploadServerFileToNada({
                    source: 'indicator_data',
                    uploadLabel: uploadLabel
                });

                vm.publish_processing_message = vm.$t('publishing_indicator_data_to_nada');
                await vm.publishIndicatorExtras({
                    publish_indicator_data: true,
                    nada_upload_id: uploadResult.upload_id
                });
            } catch (error) {
                if (vm.isUploadCancelledError(error)) {
                    throw error;
                }
                console.log('indicator data step-by-step publish failed', error);
                if (vm.publish_responses.indicator_data.errors.length === 0) {
                    var normalized = vm.normalizePublishError(error);
                    if (!normalized.summary && error && error.message) {
                        normalized.summary = error.message;
                    }
                    vm.publish_responses.indicator_data.errors.push(normalized);
                }
            }
        },
        applyIndicatorPublishDefaults: function()
        {
            this.applyPublishOptionDefaults();
        },
        async prepareProjectExport()
        {
            var ok = true;
            this.project_export_status=this.$t("exporting_metadata_to_json");
            ok = await this.exportGet(
                CI.site_url + '/api/editor/generate_json/' + this.ProjectID,
                this.$t('exporting_metadata_to_json')
            ) && ok;

            if (this.ProjectType=='survey' || this.ProjectType=='microdata'){
                this.project_export_status=this.$t("exporting_metadata_to_ddi");
                ok = await this.exportGet(
                    CI.site_url + '/api/editor/generate_ddi/' + this.ProjectID,
                    this.$t('exporting_metadata_to_ddi')
                ) && ok;
            }
            
            this.project_export_status=this.$t("exporting_external_resources_metadata_as_json");
            ok = await this.exportGet(
                CI.site_url + '/api/resources/write_json/' + this.ProjectID,
                this.$t('exporting_external_resources_metadata_as_json')
            ) && ok;
            this.project_export_status=this.$t("exporting_external_resources_as_rdf_xml");
            ok = await this.exportGet(
                CI.site_url + '/api/resources/write_rdf/' + this.ProjectID,
                this.$t('exporting_external_resources_as_rdf_xml')
            ) && ok;
            this.project_export_status = ok ? 'done' : 'failed';
            return ok;
        },
        loadCatalogConnections: function() {
            vm=this;
            let url=CI.site_url + '/api/publish/catalog_connections';
            axios.get(url)
            .then(function (response) {
                if(response.data){
                    vm.catalog_connections=response.data.connections;
                }
            })
            .catch(function (error) {
                console.log(error);
            })
            .then(function () {
                console.log("request completed");
            });
        },
        getConnectionInfo: function(id)
        {
            for (let i=0;i<this.catalog_connections.length;i++){
                if (this.catalog_connections[i].id==id){
                    return this.catalog_connections[i];
                }
            }

            return false
        },
        onCatalogSelection: function(){
            this.getProjectBasicInfo();
            this.loadCatalogInfo();
        },
        enumToItems: function (enumObj) {
            if (!enumObj || typeof enumObj !== 'object') return [];
            return Object.keys(enumObj).map(function (k) {
                return { text: enumObj[k], value: k };
            });
        },
        normalizeDataAccessList: function(codes) {
            if (Array.isArray(codes)) {
                var normalized = codes.filter(function (code) {
                    return code && code.type !== undefined && code.type !== null && String(code.type) !== '';
                }).map(function (code) {
                    return {
                        id: code.id,
                        type: code.type,
                        title: code.title || code.type
                    };
                });
                if (normalized.length > 0) {
                    return normalized;
                }
            }
            return this.enumToItems(this.publish_options.access_policy.enum).map(function (item) {
                return { title: item.text, type: item.value };
            });
        },
        applyStudyInfoToPublishOptions: function(studyInfo) {
            if (!studyInfo || studyInfo.status === 'failed' || studyInfo.status === 'error') {
                this.publish_options.overwrite.value = 'no';
                return;
            }

            if (studyInfo.published !== undefined && studyInfo.published !== null) {
                this.publish_options.published.value = String(studyInfo.published);
            }

            var accessPolicy = studyInfo.access_policy || studyInfo.data_access_type;
            if (accessPolicy !== undefined && accessPolicy !== null && accessPolicy !== '') {
                this.publish_options.access_policy.value = accessPolicy;
            }

            if (studyInfo.repositoryid !== undefined && studyInfo.repositoryid !== null) {
                this.publish_options.repositoryid.value = studyInfo.repositoryid;
            }

            if (studyInfo.remote_data_url !== undefined && studyInfo.remote_data_url !== null) {
                this.publish_options.data_remote_url.value = studyInfo.remote_data_url;
            }

            this.publish_options.overwrite.value = 'yes';
        },
        /**
         * Load collections and data_access_codes from NADA via backend (single endpoint).
         */
        loadCatalogInfo: function() {
            var vm = this;
            vm.collections_codes = [];
            vm.collections_linked = [];
            vm.data_access_list = [];
            vm.indicator_publish = null;
            vm.nada_version = null;
            vm.nada_resumable_uploads = false;

            if (vm.catalog === false || vm.catalog < 0){
                return;
            }

            var connection = vm.getConnectionInfo(vm.catalog);
            if (!connection){
                return;
            }

            var url = CI.site_url + '/api/publish/catalog_info/' + vm.ProjectID + '/' + connection.id;
            axios.get(url)
                .then(function (response) {
                    if (response.data.collections_codes){
                        vm.collections_codes = response.data.collections_codes;
                    }
                    vm.data_access_list = vm.normalizeDataAccessList(response.data.data_access_codes);
                    if (response.data.collections_linked) {
                        var raw = (response.data.collections_linked.collections && Array.isArray(response.data.collections_linked.collections))
                            ? response.data.collections_linked.collections
                            : (Array.isArray(response.data.collections_linked) ? response.data.collections_linked : []);
                        var arr = Array.isArray(raw) ? raw : (raw != null ? [raw] : []);
                        vm.collections_linked = arr.map(function (r) { return r != null ? String(r) : ''; }).filter(Boolean);
                    } else {
                        vm.collections_linked = [];
                    }
                    vm.study_info = response.data.study_info || null;
                    vm.nada_version = response.data.nada_version || null;
                    vm.nada_resumable_uploads = !!response.data.resumable_uploads;
                    if (response.data.indicator_publish) {
                        vm.indicator_publish = response.data.indicator_publish;
                        vm.applyIndicatorPublishDefaults();
                    } else {
                        vm.indicator_publish = null;
                    }
                    if (vm.catalog !== false && vm.catalog !== null) {
                        vm.$nextTick(function () {
                            vm.panels = vm.getDefaultExpandedPanels();
                            vm.applyPublishOptionDefaults();
                        });
                    } else {
                        vm.applyPublishOptionDefaults();
                    }
                    vm.$nextTick(function () {
                        vm.applyStudyInfoToPublishOptions(response.data.study_info || null);
                    });
                })
                .catch(function (error) {
                    console.log("failed loading catalog info (collections, data access codes)", error);
                    var data = error.response && error.response.data;
                    vm.study_info = {
                        status: 'failed',
                        error: (data && (data.message != null)) ? data.message : (data ? JSON.stringify(data) : (error.message || 'Request failed'))
                    };
                });
        }
    },
    watch: {
        publish_metadata: function (val) {
            if (!val && !this.studyExistsOnNada && this.publish_indicator_data) {
                this.publish_indicator_data = false;
            }
        },
        resources_selected: {
            handler: function (val) {
                if (!val || val.length === 0) {
                    this.publish_resources = false;
                }
            },
            deep: true
        }
    },
    computed: {
        showPublishUploadProgress(){
            return this.publish_file_upload_active || this.publish_progress_percent != null;
        },
        ProjectID(){
            return this.$store.state.project_id;
        },
        StudyIDNO(){
            return this.project_info.study_idno;
        },
        hasStudyIdno(){
            var id = this.StudyIDNO;
            return id != null && String(id).trim() !== '';
        },
        studyExistsOnNada(){
            return !!(this.study_info
                && !this.catalogApiFailed(this.study_info)
                && (this.study_info.idno || this.study_info.title));
        },
        ProjectMetadata(){
            return this.$store.state.formData;
        },
        Datafiles(){
            return this.$store.state.data_files;
        },
        Variables(){
            return this.$store.state.variables;
        },
        ProjectType(){
            return this.$store.state.project_type;
        },
        ExternalResources()
        {
          return JSON.parse(JSON.stringify(this.$store.state.external_resources));
        },
        studyInfoJson(){
            if (!this.study_info) return '';
            try {
                return JSON.stringify(this.study_info, null, 2);
            } catch (e) {
                return String(this.study_info);
            }
        },
        studyCatalogErrorMessage(){
            if (!this.study_info || !this.study_info.error) return this.$t('study_not_found_in_catalog');
            var err = String(this.study_info.error);
            if (err.indexOf('IDNO-NOT-FOUND') !== -1) return this.$t('study_not_found_in_catalog');
            return err;
        },
        PublishOptions(){
            let items={};
            vm=this;
            Object.keys(this.publish_options).forEach(function eachKey(key) {                 
                items[key]=vm.publish_options[key]["value"];
            });

            return items;
        },
        CatalogConnections()
        {
            //add a new field connection_title [title + url]
            let connections=[];
            for (let i=0;i<this.catalog_connections.length;i++){
                let connection=this.catalog_connections[i];
                connection.connection_title=connection.title + ' - ' + connection.url;
                connections.push(connection);
            }

            return connections;
        },
        TargetCatalogPublishedUrl(){
            let nada_catalog=this.getConnectionInfo(this.catalog);
            if(!nada_catalog){
                return '';
            }

            return nada_catalog.url + '/index.php/catalog/study/'+this.StudyIDNO;
        },
        catalogSelected(){
            return this.catalog !== false && this.catalog !== null;
        },
        /** True when a thumbnail file exists on the project (from basic_info). */
        hasProjectThumbnail(){
            var p = this.project_info;
            if (!p || typeof p.has_thumbnail === 'undefined') {
                return false;
            }
            return p.has_thumbnail === true || p.has_thumbnail === 1 || p.has_thumbnail === '1';
        },
        /** At least one external resource row is selected for publishing. */
        hasExternalResourcesPublishSelection(){
            return Array.isArray(this.resources_selected) && this.resources_selected.length > 0;
        },
        isIndicatorProject(){
            var t = this.ProjectType;
            return t === 'indicator' || t === 'timeseries';
        },
        canPublishDsd(){
            return this.isIndicatorProject
                && this.indicator_publish
                && this.indicator_publish.local
                && this.indicator_publish.local.bound;
        },
        canPublishIndicatorData(){
            return this.isIndicatorProject
                && this.indicator_publish
                && this.indicator_publish.local
                && this.indicator_publish.local.has_published_data;
        },
        indicatorDataPublishAllowed(){
            if (!this.canPublishIndicatorData || !this.catalogSelected || !this.hasStudyIdno) {
                return false;
            }
            if (this.publish_metadata) {
                return true;
            }
            return this.studyExistsOnNada;
        },
        catalogSupportsResumableUploads(){
            return !!this.nada_resumable_uploads;
        },
        nadaDsdExists(){
            return !!(this.indicator_publish
                && this.indicator_publish.nada_dsd
                && this.indicator_publish.nada_dsd.exists);
        },
        hasAnyPublishSelection(){
            return this.publish_metadata
                || this.publish_thumbnail
                || this.publish_resources
                || (this.delete_nada_resources_before_publish && this.studyExistsOnNada)
                || (this.isIndicatorProject && (this.publish_dsd || this.publish_indicator_data));
        },
        indicatorPublishInfoJson(){
            if (!this.indicator_publish) return '';
            try {
                return JSON.stringify(this.indicator_publish, null, 2);
            } catch (e) {
                return String(this.indicator_publish);
            }
        }
    },  
    template: `
            <div class="import-options-component mt-5 p-3">

                <v-card>
                    <v-card-title>{{$t("publish_to_nada")}}</v-card-title>
                    <v-card-subtitle>{{$t("publish_to_nada_note")}}</v-card-subtitle>
                
                    <v-card-text>
                    
                    <v-card elevation="2" class="p-3 mb-3">
                            <div class="form-group-x" elevation="10">
                                <label for="catalog_id">{{$t("catalog")}} <router-link class="btn btn-sm btn-link" to="/configure-catalog">{{$t("configure_catalog")}}</router-link></label>

                                <v-select
                                    v-model="catalog"
                                    :items="CatalogConnections"
                                    item-text="connection_title"
                                    :return-object="false"
                                    item-value="id"
                                    label=""
                                    @change="onCatalogSelection"
                                    outlined
                                    dense
                                ></v-select>                                

                            </div>
                    </v-card>

                    <v-alert v-if="!catalogSelected" type="info" dense outlined class="mb-0 mt-3">
                        {{ $t('select_catalog_for_publishing') }}
                    </v-alert>

                    <template v-if="catalogSelected">
                    <v-alert v-if="hasStudyIdno" type="info" dense outlined class="mb-3 mt-3">
                        <strong>{{ $t('study_idno') }}:</strong> {{ StudyIDNO }}
                    </v-alert>
                    <v-alert v-else-if="isIndicatorProject" type="warning" dense outlined class="mb-3 mt-3">
                        {{ $t('publish_study_idno_missing') }}
                    </v-alert>

                    <v-expansion-panels multiple v-model="panels" class="mt-3">
                        <v-expansion-panel>
                            <v-expansion-panel-header>
                            <div>
                                <v-icon v-if="studyExistsOnNada" color="success" class="mr-2">mdi-check-circle</v-icon>
                                {{$t("study_in_catalog")}} (NADA)
                                </div>
                            </v-expansion-panel-header>
                            <v-expansion-panel-content>
                                <div v-if="studyExistsOnNada" class="mb-3">
                                    <pre class="pa-3 bg-light border rounded text-left" style="max-height:400px;overflow:auto;font-size:0.85em;"><code>{{ studyInfoJson }}</code></pre>
                                </div>
                                <div v-else class="text-muted pa-3">
                                    <span v-if="study_info && (study_info.status === 'failed' || study_info.status === 'error')">
                                    <span class="mdi mdi-alert text-danger"></span> {{ studyCatalogErrorMessage }}
                                     </span>
                                    <span v-else-if="study_info === null">{{ $t("loading") }}...</span>
                                </div>
                            </v-expansion-panel-content>
                        </v-expansion-panel>
                        <v-expansion-panel>
                            <v-expansion-panel-header>
                                {{$t("project_options")}}
                            </v-expansion-panel-header>
                            <v-expansion-panel-content>

                            <div class="mb-4">
                                
                                <table class="table table-sm table-bordered table-hover table-striped mb-0 pb-0" style="font-size:small;">
                                    <tr>
                                        <th>{{$t("option")}}</th>
                                        <th>{{$t("value")}}</th>
                                    </tr>
                                    <template v-for="(kv,kv_key) in publish_options">                                            
                                    <tr v-if="!kv.custom">
                                        <td>
                                            {{kv.title}}                                        
                                        </td>
                                        <td>
                                            <input v-if="!kv.enum" type="text" class="form-control" v-model="kv.value" :disabled="!catalogSelected"/>
                                            <v-select
                                                v-else
                                                v-model="kv.value"
                                                :items="enumToItems(kv.enum)"
                                                item-text="text"
                                                item-value="value"
                                                :disabled="!catalogSelected"
                                                outlined
                                                dense
                                                hide-details
                                                :placeholder="kv.title"
                                            ></v-select>
                                        </td>
                                    </tr>                                            
                                    </template>
                                    <tr>
                                        <td>{{$t("data_access")}}</td>
                                        <td>
                                            <v-select
                                                v-model="publish_options.access_policy.value"
                                                :items="data_access_list"
                                                item-text="title"
                                                item-value="type"
                                                :disabled="!catalogSelected"
                                                clearable
                                                outlined
                                                dense
                                                hide-details
                                                :placeholder="$t('data_access')"
                                            ></v-select>

                                            <div v-if="publish_options.access_policy.value=='remote'" class="mt-2">
                                                <label>{{$t("Link to remote repository")}}</label>
                                                <v-text-field
                                                    v-model="publish_options.data_remote_url.value"
                                                    :disabled="!catalogSelected"
                                                    outlined
                                                    dense
                                                    hide-details
                                                ></v-text-field>
                                            </div>

                                        </td>

                                    </tr>
                                    <tr>
                                        <td>{{$t("collection")}}</td>
                                        <td>
                                            <v-select
                                                v-model="publish_options.repositoryid.value"
                                                :items="collections_codes"
                                                item-text="title"
                                                item-value="repositoryid"
                                                :disabled="!catalogSelected"
                                                clearable
                                                outlined
                                                dense
                                                hide-details
                                                :placeholder="$t('collection')"
                                            ></v-select>
                                        </td>

                                    </tr>
                                    <tr v-show="false">
                                        <td>{{$t("collections_linked")}}</td>
                                        <td>
                                            <v-select
                                                v-model="collections_linked"
                                                :items="collections_codes"
                                                item-text="title"
                                                item-value="repositoryid"
                                                multiple
                                                chips
                                                small-chips
                                                readonly
                                                outlined
                                                dense
                                                hide-details
                                                :placeholder="$t('select_collections_linked')"
                                            ></v-select>
                                        </td>
                                    </tr>
                                    
                                </table>                            
                            </div>
                            </v-expansion-panel-content>
                        </v-expansion-panel>

                        <v-expansion-panel v-if="isIndicatorProject">
                            <v-expansion-panel-header>
                                <div>
                                    {{$t("indicator_publish_status")}}
                                </div>
                            </v-expansion-panel-header>
                            <v-expansion-panel-content>
                                <div v-if="indicator_publish === null" class="text-muted pa-3">{{ $t("loading") }}...</div>
                                <div v-else>
                                    <div v-if="!indicator_publish.local || !indicator_publish.local.bound" class="alert alert-warning">
                                        {{ $t("no_dsd_bound_to_project") }}
                                    </div>
                                    <div v-else class="mb-3">
                                        <div><strong>{{ $t('study_idno') }}:</strong> {{ indicator_publish.local.study_idno || StudyIDNO || '—' }}</div>
                                        <div><strong>{{ $t("data_structure") }}:</strong> {{ indicator_publish.local.data_structure_reference && indicator_publish.local.data_structure_reference.idno ? indicator_publish.local.data_structure_reference.idno : '—' }}</div>
                                    </div>
                                    <div class="switch-control mt-3 pt-3 border-top">
                                        <v-switch
                                            v-model="publish_dsd"
                                            :value="true"
                                            :disabled="!canPublishDsd"
                                            :label="$t('publish_dsd_to_nada')"
                                            dense
                                            hide-details
                                            class="ma-0 pa-0"
                                        ></v-switch>
                                        <v-switch
                                            v-if="publish_dsd && nadaDsdExists"
                                            v-model="dsd_overwrite"
                                            :true-value="true"
                                            :false-value="false"
                                            :label="$t('dsd_overwrite_on_nada')"
                                            dense
                                            hide-details
                                            class="ma-0 pa-0"
                                        ></v-switch>
                                        <v-switch
                                            v-model="publish_indicator_data"
                                            :value="true"
                                            :disabled="!indicatorDataPublishAllowed"
                                            :label="$t('publish_indicator_data_to_nada')"
                                            dense
                                            hide-details
                                            class="ma-0 pa-0"
                                        ></v-switch>
                                        <div v-if="canPublishIndicatorData && !indicatorDataPublishAllowed" class="switch-control-hint text-warning">
                                            {{ $t('indicator_data_requires_metadata') }}
                                        </div>
                                    </div>
                                </div>
                            </v-expansion-panel-content>
                        </v-expansion-panel>

                        <v-expansion-panel>
                            <v-expansion-panel-header>
                                <div>{{$t("external_resources")}}
                                    <div class="text-secondary text-muted text-xs text-small text-normal">{{$t("select_external_resources_to_be_published")}}</div>
                                </div>
                                
                            </v-expansion-panel-header>
                            <v-expansion-panel-content>
                                <div class="mt-3 switch-control">
                                        <v-switch
                                        v-model="resources_overwrite"
                                        value="yes"
                                        :label="$t('overwrite_resources')"
                                    ></v-switch>
                                </div>
                                
                                <div v-if="ExternalResources.length>0" >
                                    <div>
                                        <strong>{{ExternalResources.length}}</strong> {{$t("n_resources_found")}}
                                        <span class="ml-2"><strong>{{resources_selected.length}}</strong> {{$t("n_selected")}}</span>
                                    </div>
                                    <div class="border" style="max-height:300px;overflow:auto;">                    
                                        <table class="table table-sm table-striped">
                                            <thead>
                                            <tr class="bg-light">
                                                <th><input type="checkbox" v-model="toggle_resources_selected" @change="toggleSelectedResources"></th>
                                                <th>{{$t("title")}}</th>
                                                <th>{{$t("type")}}</th>
                                            </tr>
                                            </thead>
                                            <tr v-for="(resource,resource_index) in ExternalResources" :key="resource.id">
                                                <td><input type="checkbox" :value="resource_index" v-model="resources_selected"></td>
                                                <td>
                                                    <div>{{resource.title}}</div>
                                                    <div class="text-secondary text-small">{{resource.filename}}</div>
                                                </td>
                                                <td>{{resource.dctype}}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <div v-else class="alert alert-warning">
                                {{$t("no_external_resources_found")}}
                                </div>
                            </v-expansion-panel-content>
                        </v-expansion-panel>

                    </v-expansion-panels>

                                            
                    <div class=" mb-3 mt-5 switch-control elevation-2 p-4">
                        <div><strong>{{$t("options")}}</strong></div>
                        
                            <v-switch
                                v-model="publish_metadata"
                                :value="true"
                                :label="$t('publish_project')"
                                dense
                                hide-details
                                class="ma-0 pa-0"
                            ></v-switch>
                            
                            <v-switch
                                v-model="publish_thumbnail"
                                :value="true"
                                :disabled="!hasProjectThumbnail"
                                :label="$t('publish_thumbnail')"
                                dense
                                hide-details
                                class="ma-0 pa-0"
                            ></v-switch>
                            
                            <v-switch
                                v-model="publish_resources"
                                :value="true"
                                :disabled="ExternalResources.length === 0"
                                :label="$t('external_resources') + (resources_selected.length>0?' ('+resources_selected.length+')':'')"
                                dense
                                hide-details
                                class="ma-0 pa-0"
                            ></v-switch>

                            <template v-if="studyExistsOnNada">
                                <v-switch
                                    v-model="delete_nada_resources_before_publish"
                                    :true-value="true"
                                    :false-value="false"
                                    :label="$t('delete_all_nada_resources_before_publish')"
                                    dense
                                    hide-details
                                    class="ma-0 pa-0"
                                ></v-switch>
                                <v-alert
                                    v-if="delete_nada_resources_before_publish"
                                    type="warning"
                                    dense
                                    outlined
                                    class="mt-2 mb-0"
                                >
                                    {{ $t('delete_all_nada_resources_warning') }}
                                </v-alert>
                            </template>

                    </div>

                    
                    <div v-if="hasStudyIdno" class="mt-5 p-4 elevation-2 mb-5 bg-light" >
                        <div><strong>{{ studyExistsOnNada ? $t('published_project_link') : $t('catalog_preview_link_note') }}:</strong></div>
                        <div><a :href="TargetCatalogPublishedUrl" target="_blank">{{TargetCatalogPublishedUrl}} <v-icon color="primary">mdi-open-in-new</v-icon></a></div>
                    </div>


                    <v-btn :disabled="is_publishing==true" color="primary" @click="publishToCatalog()">{{$t("publish")}}</v-btn>

                    </template>
                    
                

                </v-card-text>
                </v-card>

                


                <!-- dialog -->
                <v-dialog v-model="dialog_process" width="700" persistent scrollable>
                    <v-card>
                        <v-card-title class="text-h5 grey lighten-2">
                            <div class="text-h5">{{$t('publish_project')}}</div>
                        </v-card-title>

                        <v-card-text>
                        <div>
                            <!-- card text -->
                            <!-- show-status -->
                            <div v-if="is_publishing">
                                <div class="border p-3 mt-5 mb-5">
                                    <div><strong>{{ $t('publish_update_status') }}</strong></div>
                                    <div class="mt-2">{{publish_processing_message}}<span v-if="!showPublishUploadProgress">...</span></div>
                                    <template v-if="showPublishUploadProgress">
                                        <v-progress-linear
                                            class="mt-3 rounded"
                                            color="primary"
                                            height="12"
                                            :indeterminate="publish_progress_percent == null"
                                            :value="publish_progress_percent != null ? publish_progress_percent : 0"
                                        >
                                            <template v-if="publish_progress_percent != null" v-slot:default="{ value }">
                                                <strong class="text-caption">{{ Math.ceil(value) }}%</strong>
                                            </template>
                                        </v-progress-linear>
                                        <div
                                            v-if="publish_upload_detail && publish_upload_detail.total_chunks"
                                            class="text-caption text-center grey--text text--darken-1 mt-1"
                                        >
                                            {{ publish_upload_detail.uploaded_chunks }} / {{ publish_upload_detail.total_chunks }}
                                        </div>
                                    </template>
                                </div>                        
                            </div>
                            <!-- end show-status --> 
                            
                            <div v-if="is_publishing_completed" class="p-2">
                                <div v-if="publish_was_cancelled" class="alert alert-warning mb-3">
                                    {{ $t('publish_cancelled') }}
                                </div>
                                <div v-if="publish_responses.export.length>0" class="mb-3">
                                    <strong>{{ $t('publish_export_step') }}</strong>
                                    <div class="border rounded p-2 mb-2 mt-2 text-left text-danger" v-for="(msg, export_index) in publish_responses.export" :key="'pub-export-err-' + export_index">
                                        {{ msg }}
                                    </div>
                                </div>

                                <div v-if="publish_selection_snapshot && publish_selection_snapshot.publish_metadata">
                                    <strong>{{$t('metadata')}}</strong>
                                    <div v-if="publish_responses.metadata.errors.length>0">
                                        <span class="mdi mdi-alert text-danger"></span>
                                        <span>{{ $t('metadata_publishing_failed') }}</span>
                                        <div class="border rounded p-2 mb-2 mt-2 text-left text-body" v-for="(err, response_index) in publish_responses.metadata.errors" :key="'pub-meta-err-' + response_index">
                                            <div class="text-danger font-weight-bold">{{ err.summary }}</div>
                                            <div v-if="err.httpStatus != null" class="text-muted small">Editor API: HTTP {{ err.httpStatus }}</div>
                                            <div v-if="err.nada && err.nada.api_url" class="text-muted small text-break">URL: {{ err.nada.api_url }}</div>
                                            <div v-if="err.bodyFormat" class="text-muted small">Catalog response: {{ err.bodyFormat }}</div>
                                            <div v-if="err.ddiFallback" class="text-warning small mt-1">DDI fallback: {{ err.ddiFallback }}</div>
                                            <pre v-if="err.jsonDetail != null" class="bg-light border rounded p-2 mt-1 small text-dark" style="max-height:240px;overflow:auto;white-space:pre-wrap;">{{ formatJsonForDisplay(err.jsonDetail) }}</pre>
                                            <pre v-if="err.nada && err.nada.ddi_fallback_details" class="bg-light border rounded p-2 mt-1 small text-dark" style="max-height:200px;overflow:auto;white-space:pre-wrap;">DDI import details: {{ formatJsonForDisplay(err.nada.ddi_fallback_details) }}</pre>
                                            <pre v-if="err.rawBody && err.jsonDetail == null" class="bg-light border rounded p-2 mt-1 small text-dark" style="max-height:240px;overflow:auto;white-space:pre-wrap;">{{ err.rawBody }}</pre>
                                        </div>    
                                    </div>
                                    <div v-else-if="publish_responses.metadata.messages.length>0">
                                        <div class="border m-1 text-success" v-for="(message, msg_index) in publish_responses.metadata.messages" :key="'pub-meta-msg-' + msg_index">
                                            <span class="mdi mdi-check-circle text-success"></span> {{ message }}
                                        </div>
                                    </div>
                                </div>

                                <div v-if="publish_selection_snapshot && publish_selection_snapshot.is_indicator_project && publish_selection_snapshot.publish_dsd" class="mt-3">
                                    <strong>{{$t('data_structure')}} (DSD)</strong>
                                    <div v-if="publish_responses.dsd.errors.length>0">
                                        <div class="border rounded p-2 mb-2 mt-2 text-left text-body" v-for="(err, response_index) in publish_responses.dsd.errors" :key="'pub-dsd-err-' + response_index">
                                            <div class="text-danger font-weight-bold">{{ err.summary }}</div>
                                            <div v-if="err.httpStatus != null" class="text-muted small">Editor API: HTTP {{ err.httpStatus }}</div>
                                            <div v-if="err.nada && err.nada.api_url" class="text-muted small text-break">URL: {{ err.nada.api_url }}</div>
                                            <pre v-if="err.jsonDetail != null" class="bg-light border rounded p-2 mt-1 small text-dark" style="max-height:200px;overflow:auto;white-space:pre-wrap;">{{ formatJsonForDisplay(err.jsonDetail) }}</pre>
                                            <pre v-if="err.rawBody && err.jsonDetail == null" class="bg-light border rounded p-2 mt-1 small text-dark" style="max-height:200px;overflow:auto;white-space:pre-wrap;">{{ err.rawBody }}</pre>
                                        </div>
                                    </div>
                                    <div v-if="publish_responses.dsd.messages.length>0">
                                        <div class="border-bottom m-1 text-success" v-for="(message, msg_index) in publish_responses.dsd.messages" :key="'pub-dsd-msg-' + msg_index">
                                            <span class="mdi mdi-check-circle text-success"></span> {{ message }}
                                        </div>
                                    </div>
                                </div>

                                <div v-if="publish_selection_snapshot && publish_selection_snapshot.is_indicator_project && publish_selection_snapshot.publish_indicator_data" class="mt-3">
                                    <strong>{{$t('indicator_data')}}</strong>
                                    <div v-if="publish_responses.indicator_data.errors.length>0">
                                        <div class="border rounded p-2 mb-2 mt-2 text-left text-body" v-for="(err, response_index) in publish_responses.indicator_data.errors" :key="'pub-data-err-' + response_index">
                                            <div class="text-danger font-weight-bold">{{ err.summary }}</div>
                                            <div v-if="err.httpStatus != null" class="text-muted small">Editor API: HTTP {{ err.httpStatus }}</div>
                                            <div v-if="err.nada && err.nada.api_url" class="text-muted small text-break">URL: {{ err.nada.api_url }}</div>
                                            <div v-if="err.nada && err.nada.payload_idno" class="text-muted small">Study IDNO: {{ err.nada.payload_idno }}</div>
                                            <div v-if="err.bodyFormat" class="text-muted small">Catalog response: {{ err.bodyFormat }}</div>
                                            <pre v-if="err.jsonDetail != null" class="bg-light border rounded p-2 mt-1 small text-dark" style="max-height:240px;overflow:auto;white-space:pre-wrap;">{{ formatJsonForDisplay(err.jsonDetail) }}</pre>
                                            <pre v-if="err.rawBody && err.jsonDetail == null" class="bg-light border rounded p-2 mt-1 small text-dark" style="max-height:240px;overflow:auto;white-space:pre-wrap;">{{ err.rawBody }}</pre>
                                        </div>
                                    </div>
                                    <div v-if="publish_responses.indicator_data.messages.length>0">
                                        <div class="border-bottom m-1 text-success" v-for="(message, msg_index) in publish_responses.indicator_data.messages" :key="'pub-data-msg-' + msg_index">
                                            <span class="mdi mdi-check-circle text-success"></span> {{ message }}
                                        </div>
                                    </div>
                                </div>

                                <div v-if="publish_selection_snapshot && publish_selection_snapshot.publish_thumbnail" class="mt-5">
                                    <strong>{{$t('thumbnail')}}</strong>
                                    <div v-if="publish_responses.thumbnail.errors.length>0">
                                        <span class="mdi mdi-alert text-danger"></span>
                                        <span>{{ $t('thumbnail_publishing_failed') }}</span>
                                        <div class="border rounded p-2 mb-2 mt-2 text-left text-body" v-for="(err, response_index) in publish_responses.thumbnail.errors" :key="'pub-thumb-err-' + response_index">
                                            <div class="text-danger font-weight-bold">{{ err.summary }}</div>
                                            <div v-if="err.httpStatus != null" class="text-muted small">Editor API: HTTP {{ err.httpStatus }}</div>
                                            <div v-if="err.nada && err.nada.api_url" class="text-muted small text-break">URL: {{ err.nada.api_url }}</div>
                                            <div v-if="err.bodyFormat" class="text-muted small">Catalog response: {{ err.bodyFormat }}</div>
                                            <pre v-if="err.jsonDetail != null" class="bg-light border rounded p-2 mt-1 small text-dark" style="max-height:240px;overflow:auto;white-space:pre-wrap;">{{ formatJsonForDisplay(err.jsonDetail) }}</pre>
                                            <pre v-if="err.rawBody && err.jsonDetail == null" class="bg-light border rounded p-2 mt-1 small text-dark" style="max-height:240px;overflow:auto;white-space:pre-wrap;">{{ err.rawBody }}</pre>
                                        </div>    
                                    </div>
                                    <div v-if="publish_responses.thumbnail.messages.length>0" >                            
                                        <div class="border-bottom m-1" v-for="message in publish_responses.thumbnail.messages">
                                            <div class="text-success">
                                            <span class="mdi mdi-check-circle text-success"></span> {{message}}
                                            </div>                                
                                        </div>    
                                    </div>                       
                                </div>

                                <div v-if="publish_selection_snapshot && (publish_selection_snapshot.delete_nada_resources || (publish_selection_snapshot.publish_resources && publish_selection_snapshot.resources_selected_count > 0))" class="mt-5">
                                    <strong>{{ $t('publish_external_resources_heading') }}</strong>
                                    <div v-if="publish_responses.external_resources.messages.length>0" >                            
                                        <div class="border-bottom m-1" v-for="message in publish_responses.external_resources.messages">
                                            <div>
                                            <span class="mdi mdi-check-circle text-success"></span> {{message}}
                                            </div>                                
                                        </div>    
                                    </div>
                                    <div v-if="publish_responses.external_resources.errors.length>0" >
                                        <div class="border rounded p-2 mb-2 text-left text-body" v-for="(err, response_index) in publish_responses.external_resources.errors" :key="'pub-res-err-' + response_index">
                                            <div><span class="mdi mdi-alert text-danger"></span> {{ err.resource_title }}</div>
                                            <div class="text-danger font-weight-bold small mt-1">{{ err.summary }}</div>
                                            <div v-if="err.httpStatus != null" class="text-muted small">Editor API: HTTP {{ err.httpStatus }}</div>
                                            <div v-if="err.nada && err.nada.api_url" class="text-muted small text-break">URL: {{ err.nada.api_url }}</div>
                                            <div v-if="err.bodyFormat" class="text-muted small">Catalog response: {{ err.bodyFormat }}</div>
                                            <pre v-if="err.jsonDetail != null" class="bg-light border rounded p-2 mt-1 small text-dark" style="max-height:200px;overflow:auto;white-space:pre-wrap;">{{ formatJsonForDisplay(err.jsonDetail) }}</pre>
                                            <pre v-if="err.rawBody && err.jsonDetail == null" class="bg-light border rounded p-2 mt-1 small text-dark" style="max-height:200px;overflow:auto;white-space:pre-wrap;">{{ err.rawBody }}</pre>
                                        </div>    
                                    </div>
                                </div>                    

                            </div>
                            
                            <!-- end card text -->
                        </div>
                        </v-card-text>

                        <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn color="error" text @click="requestPublishCancel()" v-if="is_publishing">
                        {{$t('cancel')}}
                        </v-btn>
                        <v-btn color="primary" text @click="dialog_process=false" v-if="is_publishing==false">
                        {{$t('close')}}
                        </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>
                <!-- end dialog -->



                
            </div>          
            `    
});

