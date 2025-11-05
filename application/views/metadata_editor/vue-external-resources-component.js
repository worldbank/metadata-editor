//external resources
const VueExternalResources = Vue.component('external-resources', {
    props: ['index', 'id'],
    data() {
        return {
            selectedResources: [],
            showBulkActions: false,
            isDeleting: false,
            projectFiles: [],
            filesLoaded: false
        }
    }, 
    created () {
        this.loadProjectFiles();
    },
    watch: {
        ExternalResources: {
            handler: function() {
                // Reload files when resources change (e.g., after adding/editing)
                if (this.filesLoaded) {
                    this.loadProjectFiles();
                }
            },
            deep: true
        }
    },
    methods: {
        editResource: function(id) {
            this.page_action = "edit";
            router.push('/external-resources/' + id);
        },
        addResource: function() {
            router.push('/external-resources/create');
        },
        importResource: function() {
            router.push('/external-resources/import');
        },
        deleteResource: function(id) {
            if (!confirm(this.$t("confirm_delete"))) {
                return;
            }

            const vm = this;
            const url = CI.base_url + '/api/resources/delete/' + this.ProjectID + '/' + id;

            axios.post(url)
                .then(function(response) {
                    vm.$store.dispatch('loadExternalResources', {dataset_id: vm.ProjectID});
                    vm.loadProjectFiles(); // Reload files after deletion
                })
                .catch(function(response) {
                    vm.errors = response;
                    alert(vm.$t("failed_operation") + ": " + vm.errorMessageToText(response));
                });
        },
        errorMessageToText: function(error) {
            let error_text = '';
            if (error.response.data.errors) {
                for (let key in error.response.data.errors) {
                    error_text += error.response.data.errors[key] + '\n';
                }
            } else {
                error_text = error.response.data.message;
            }
            return error_text;
        },
        toggleResourceSelection: function(resourceId) {
            const index = this.selectedResources.indexOf(resourceId);
            if (index > -1) {
                this.selectedResources.splice(index, 1);
            } else {
                this.selectedResources.push(resourceId);
            }
            this.showBulkActions = this.selectedResources.length > 0;
        },
        selectAllResources: function() {
            if (this.selectedResources.length === this.ExternalResources.length) {
                this.selectedResources = [];
            } else {
                this.selectedResources = this.ExternalResources.map(resource => resource.id);
            }
            this.showBulkActions = this.selectedResources.length > 0;
        },
        bulkDeleteResources: function() {
            if (this.selectedResources.length === 0) {
                return;
            }

            const resourceCount = this.selectedResources.length;
            const confirmMessage = this.$t("confirm_bulk_delete", { count: resourceCount });
            
            if (!confirm(confirmMessage)) {
                return;
            }

            this.isDeleting = true;
            const vm = this;
            const deletePromises = this.selectedResources.map(resourceId => {
                const url = CI.base_url + '/api/resources/delete/' + this.ProjectID + '/' + resourceId;
                return axios.post(url);
            });

            Promise.all(deletePromises)
                .then(function(responses) {
                    vm.$store.dispatch('loadExternalResources', {dataset_id: vm.ProjectID});
                    vm.loadProjectFiles(); // Reload files after deletion
                    vm.selectedResources = [];
                    vm.showBulkActions = false;
                    vm.isDeleting = false;
                })
                .catch(function(error) {
                    vm.isDeleting = false;
                    alert(vm.$t("failed_to_delete_resources") + ": " + vm.errorMessageToText(error));
                });
        },
        clearSelection: function() {
            this.selectedResources = [];
            this.showBulkActions = false;
        },
        duplicateResource: function(id) {
            const vm = this;
            
            // Find the resource to duplicate
            const resourceToDuplicate = this.ExternalResources.find(resource => resource.id == id);
            if (!resourceToDuplicate) {
                alert(vm.$t("failed_operation") + ": Resource not found");
                return;
            }

            // Prepare the resource data for duplication
            const duplicateData = {
                ...resourceToDuplicate,
                title: resourceToDuplicate.title + ' (Copy)',
                filename: resourceToDuplicate.filename ? resourceToDuplicate.filename + '_copy' : null
            };

            // Remove the ID so it creates a new resource
            delete duplicateData.id;

            const url = CI.base_url + '/api/resources/' + this.ProjectID;

            axios.post(url, duplicateData)
                .then(function(response) {
                    vm.$store.dispatch('loadExternalResources', {dataset_id: vm.ProjectID});
                    // Show success message
                    alert(vm.$t("resource_duplicated_successfully"));
                })
                .catch(function(response) {
                    vm.errors = response;
                    alert(vm.$t("failed_operation") + ": " + vm.errorMessageToText(response));
                });
        },
        momentDateUnix: function(timestamp) {
            if (!timestamp) {
                return this.$t("na");
            }
            
            return moment.unix(timestamp).format("YYYY-MM-DD HH:mm");
        },
        loadProjectFiles: function() {
            const vm = this;
            const url = CI.base_url + '/api/files/' + this.ProjectID;

            axios.get(url)
                .then(function(response) {
                    if (response.data && response.data.files) {
                        vm.projectFiles = response.data.files;
                        vm.filesLoaded = true;
                    }
                })
                .catch(function(error) {
                    console.log("Failed to load project files", error);
                    vm.filesLoaded = true; // Mark as loaded even on error
                });
        },
        isValidUrl: function(string) {
            if (!string) return false;
            
            let url;
            try {
                url = new URL(string);
            } catch (_) {
                return false;  
            }
            
            return url.protocol === "http:" || url.protocol === "https:";
        },
        fileExists: function(filename) {
            if (!filename || this.isValidUrl(filename)) {
                return null; // null means not applicable (URL or empty)
            }

            // Check if file exists in the documentation folder
            const found = this.projectFiles.find(file => 
                file.dir_path === 'documentation' && 
                file.name === filename && 
                file.is_dir === false
            );

            return found !== undefined;
        },
    },
    computed: {
        ExternalResources() {
            return this.$store.state.external_resources;
        },
        ActiveResourceIndex() {
            return this.$route.params.index;
        },
        ProjectID() {
            return this.$store.state.project_id;
        },
        isProjectEditable() {
            return this.$store.getters.getUserHasEditAccess;
        },
        isAllSelected() {
            return this.ExternalResources.length > 0 && this.selectedResources.length === this.ExternalResources.length;
        },
        isIndeterminate() {
            return this.selectedResources.length > 0 && this.selectedResources.length < this.ExternalResources.length;
        }
    },
    template: `
        <div class="external-resources container-fluid pt-5 mt-5">

            <v-card>
                <v-card-title>
                    {{$t("external_resources")}} 
                    <v-chip small color="primary" class="ml-2">{{ExternalResources.length}}</v-chip>
                </v-card-title>
                <v-card-subtitle>
                    <v-row>
                        <v-col md="8">
                            <v-btn v-if="showBulkActions" color="error" small outlined @click="bulkDeleteResources" :disabled="isDeleting">
                                <i class="fas fa-trash-alt"></i> 
                                {{isDeleting ? $t("deleting") : $t("delete_selected") + ' (' + selectedResources.length + ')'}}
                            </v-btn>
                        </v-col>
                        <v-col md="4" class="mb-2">
                            <div class="float-right">
                                <v-btn color="primary" outlined small @click="addResource">
                                    <i class="fas fa-plus-square"></i> {{$t("create_resource")}}
                                </v-btn>
                                <v-btn color="primary" outlined small @click="importResource">
                                    <i class="fas fa-file-upload"></i> {{$t("import_resources")}}
                                </v-btn> 
                            </div>
                        </v-col>
                    </v-row>
                </v-card-subtitle>
            <v-card-text>
                <external-resources-edit v-if="ActiveResourceIndex" :index="ActiveResourceIndex"/>
                <div v-else>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th width="50">
                                    <v-checkbox 
                                        :value="isAllSelected"
                                        :indeterminate="isIndeterminate"
                                        @change="selectAllResources"
                                        hide-details
                                        dense
                                    ></v-checkbox>
                                </th>
                                <th>{{$t("resource")}}</th>
                                <th>{{$t("resource_type")}}</th>
                                <th>{{$t("modified")}}</th>
                                <th>{{$t("actions")}}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(resource, index) in ExternalResources" class="resource-row" :key="resource.id">                        
                                <td>
                                    <v-checkbox 
                                        :value="selectedResources.includes(resource.id)"
                                        @change="toggleResourceSelection(resource.id)"
                                        hide-details
                                        dense
                                    ></v-checkbox>
                                </td>
                                <td>
                                    <i class="fas fa-file-alt"></i> 
                                    <router-link :key="resource.id" class="nav-item" :to="'/external-resources/' + resource.id">{{resource.title}}</router-link>
                                    <div class="text-small text-secondary">
                                        <!-- File status icon -->
                                        <v-icon 
                                            v-if="!isValidUrl(resource.filename) && resource.filename && fileExists(resource.filename) === true" 
                                            x-small 
                                            color="success" 
                                            title="File exists"
                                            style="margin-right:4px;">
                                            mdi-check-circle
                                        </v-icon>
                                        <v-icon 
                                            v-if="!isValidUrl(resource.filename) && resource.filename && fileExists(resource.filename) === false" 
                                            x-small 
                                            color="error" 
                                            title="File not found"
                                            style="margin-right:4px;">
                                            mdi-alert-circle
                                        </v-icon>
                                        <span :style="!isValidUrl(resource.filename) && resource.filename && fileExists(resource.filename) === true ? 'color: green;' : (!isValidUrl(resource.filename) && resource.filename && fileExists(resource.filename) === false ? 'color: red;' : '')">
                                            {{resource.filename}}
                                        </span>
                                    </div>
                                </td>
                                <td>{{resource.dctype}}</td>
                                <td>{{momentDateUnix(resource.changed)}}</td>
                                
                                <td>
                                    <v-menu offset-y>
                                        <template v-slot:activator="{ on, attrs }">
                                            <v-btn small icon v-on="on" v-bind="attrs" 
                                                   :title="$t('more_options')" 
                                                   color="primary">
                                                <v-icon>mdi-dots-vertical</v-icon>
                                            </v-btn>
                                        </template>
                                        
                                        <v-list dense>
                                            <v-list-item @click="editResource(resource.id)">
                                                <v-list-item-icon>
                                                    <v-icon>mdi-pencil</v-icon>
                                                </v-list-item-icon>
                                                <v-list-item-title>{{$t("edit")}}</v-list-item-title>
                                            </v-list-item>
                                            
                                            <v-list-item @click="duplicateResource(resource.id)">
                                                <v-list-item-icon>
                                                    <v-icon>mdi-content-duplicate</v-icon>
                                                </v-list-item-icon>
                                                <v-list-item-title>{{$t("duplicate")}}</v-list-item-title>
                                            </v-list-item>
                                            
                                            <v-divider></v-divider>
                                            
                                            <v-list-item @click="deleteResource(resource.id)" class="red--text">
                                                <v-list-item-icon>
                                                    <v-icon color="red">mdi-delete-outline</v-icon>
                                                </v-list-item-icon>
                                                <v-list-item-title class="red--text">{{$t("delete")}}</v-list-item-title>
                                            </v-list-item>
                                        </v-list>
                                    </v-menu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </v-card-text>
            </v-card>
        </div>
    `
})


