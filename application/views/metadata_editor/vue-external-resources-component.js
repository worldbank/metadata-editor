//external resources
const VueExternalResources = Vue.component('external-resources', {
    props: ['index', 'id'],
    data() {
        return {
            selectedResources: [],
            showBulkActions: false,
            isDeleting: false,
            projectFiles: [],
            filesLoaded: false,
            microdata_status: null
        }
    }, 
    created () {
        this.loadProjectFiles();
        this.loadMicrodataStatus();
    },
    watch: {
        ExternalResources: {
            handler: function() {
                // Reload files when resources change (e.g., after adding/editing)
                if (this.filesLoaded) {
                    this.loadProjectFiles();
                }
                if (this.isMicrodataProject) {
                    this.loadMicrodataStatus();
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
        normalizeResourceId: function(resourceId) {
            const id = parseInt(resourceId, 10);
            return isNaN(id) ? resourceId : id;
        },
        isResourceSelected: function(resourceId) {
            const id = this.normalizeResourceId(resourceId);
            return this.selectedResources.some(function(selectedId) {
                return String(selectedId) === String(id);
            });
        },
        toggleResourceSelection: function(resourceId) {
            const id = this.normalizeResourceId(resourceId);
            const index = this.selectedResources.findIndex(function(selectedId) {
                return String(selectedId) === String(id);
            });
            if (index > -1) {
                this.selectedResources.splice(index, 1);
            } else {
                this.selectedResources.push(id);
            }
            this.showBulkActions = this.selectedResources.length > 0;
        },
        selectAllResources: function() {
            if (this.isAllSelected) {
                this.selectedResources = [];
            } else {
                this.selectedResources = this.ExternalResources.map(function(resource) {
                    return this.normalizeResourceId(resource.id);
                }, this);
            }
            this.showBulkActions = this.selectedResources.length > 0;
        },
        selectAllMicrodataResources: function() {
            const vm = this;
            const microdataIds = this.microdataResourceRows.map(function(row) {
                return vm.normalizeResourceId(row.resource.id);
            });
            const allMicrodataSelected = microdataIds.length > 0 && microdataIds.every(function(id) {
                return vm.isResourceSelected(id);
            });
            if (allMicrodataSelected) {
                this.selectedResources = this.selectedResources.filter(function(selectedId) {
                    return !microdataIds.some(function(id) {
                        return String(id) === String(selectedId);
                    });
                });
            } else {
                microdataIds.forEach(function(id) {
                    if (!vm.isResourceSelected(id)) {
                        vm.selectedResources.push(id);
                    }
                });
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
            const idsToDelete = this.selectedResources.slice();
            const deletePromises = idsToDelete.map(function(resourceId) {
                const url = CI.base_url + '/api/resources/delete/' + vm.ProjectID + '/' + resourceId;
                return axios.post(url).then(function(response) {
                    return { resourceId: resourceId, response: response };
                });
            });

            Promise.allSettled(deletePromises)
                .then(function(results) {
                    const failedIds = [];
                    let firstError = null;

                    results.forEach(function(result, index) {
                        if (result.status === 'rejected') {
                            failedIds.push(vm.normalizeResourceId(idsToDelete[index]));
                            if (!firstError) {
                                firstError = result.reason;
                            }
                        }
                    });

                    vm.$store.dispatch('loadExternalResources', {dataset_id: vm.ProjectID});
                    vm.loadProjectFiles();

                    vm.selectedResources = failedIds;
                    vm.showBulkActions = vm.selectedResources.length > 0;
                    vm.isDeleting = false;

                    if (failedIds.length > 0) {
                        let message = vm.$t("failed_to_delete_resources") + " (" + failedIds.length + "/" + idsToDelete.length + ")";
                        if (firstError) {
                            message += ": " + vm.errorMessageToText(firstError);
                        }
                        alert(message);
                    }
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
        isMicrodataDctype: function(dctype) {
            return dctype && String(dctype).indexOf('dat/micro') !== -1;
        },
        loadMicrodataStatus: function() {
            if (!this.isMicrodataProject) {
                this.microdata_status = null;
                return;
            }
            const vm = this;
            const url = CI.base_url + '/api/resources/microdata_status/' + this.ProjectID;
            axios.get(url)
                .then(function(response) {
                    if (response.data && response.data.status === 'success') {
                        vm.microdata_status = response.data;
                    }
                })
                .catch(function() {
                    vm.microdata_status = null;
                });
        },
        goGenerateMicrodata: function() {
            router.push('/external-resources/generate-microdata');
        },
        goRegenerateMicrodata: function(resource) {
            router.push('/external-resources/regenerate/' + resource.id);
        },
        stalenessEntryForResource: function(resourceId) {
            if (!this.microdata_status || !this.microdata_status.microdata_resources) {
                return null;
            }
            return this.microdata_status.microdata_resources.find(function(entry) {
                return entry.resource && String(entry.resource.id) === String(resourceId);
            }) || null;
        },
        stalenessLabel: function(resourceId) {
            const entry = this.stalenessEntryForResource(resourceId);
            if (!entry || !entry.staleness) {
                return null;
            }
            const status = entry.staleness.status;
            if (status === 'current') {
                return { text: this.$t('microdata_status_current') || 'Current', color: 'success' };
            }
            if (status === 'stale') {
                return { text: this.$t('microdata_status_stale') || 'Stale', color: 'warning' };
            }
            if (status === 'missing_file') {
                return { text: this.$t('microdata_status_missing_file') || 'Missing file', color: 'error' };
            }
            return { text: status, color: 'grey' };
        },
        isGeneratedResource: function(resource) {
            return resource && resource.source_type === 'generated';
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
            const vm = this;
            return this.ExternalResources.length > 0 && this.ExternalResources.every(function(resource) {
                return vm.isResourceSelected(resource.id);
            });
        },
        isIndeterminate() {
            const vm = this;
            const selectedCount = this.ExternalResources.filter(function(resource) {
                return vm.isResourceSelected(resource.id);
            }).length;
            return selectedCount > 0 && selectedCount < this.ExternalResources.length;
        },
        isAllMicrodataSelected() {
            const vm = this;
            return this.microdataResourceRows.length > 0 && this.microdataResourceRows.every(function(row) {
                return vm.isResourceSelected(row.resource.id);
            });
        },
        isMicrodataIndeterminate() {
            const vm = this;
            const selectedCount = this.microdataResourceRows.filter(function(row) {
                return vm.isResourceSelected(row.resource.id);
            }).length;
            return selectedCount > 0 && selectedCount < this.microdataResourceRows.length;
        },
        isMicrodataProject() {
            const t = this.$store.state.project_type;
            return t === 'survey' || t === 'microdata';
        },
        DataFiles() {
            return this.$store.state.data_files || [];
        },
        resourceRows() {
            return this.ExternalResources.map((resource, index) => ({ resource, index }));
        },
        microdataResourceRows() {
            const vm = this;
            return this.resourceRows.filter(function(row) {
                return vm.isMicrodataDctype(row.resource.dctype);
            });
        },
        otherResourceRows() {
            const vm = this;
            return this.resourceRows.filter(function(row) {
                return !vm.isMicrodataDctype(row.resource.dctype);
            });
        },
        canGenerateMicrodata() {
            return this.isMicrodataProject && this.isProjectEditable && this.DataFiles.length > 0;
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
                        <v-col md="4">
                            <v-btn v-if="showBulkActions" color="error" small outlined @click="bulkDeleteResources" :disabled="isDeleting">
                                <i class="fas fa-trash-alt"></i> 
                                {{isDeleting ? $t("deleting") : $t("delete_selected") + ' (' + selectedResources.length + ')'}}
                            </v-btn>
                        </v-col>
                        <v-col md="8" class="mb-2">
                            <div class="float-right d-flex align-center">
                                <v-btn color="primary" outlined small @click="addResource" class="mr-1">
                                    <v-icon small left>mdi-plus</v-icon>{{$t("create_resource")}}
                                </v-btn>
                                <v-menu offset-y left>
                                    <template v-slot:activator="{ on, attrs }">
                                        <v-btn color="primary" outlined small icon v-bind="attrs" v-on="on">
                                            <v-icon small>mdi-dots-vertical</v-icon>
                                        </v-btn>
                                    </template>
                                    <v-list dense>
                                        <v-list-item v-if="canGenerateMicrodata" @click="goGenerateMicrodata()">
                                            <v-list-item-icon><v-icon small>mdi-database-export</v-icon></v-list-item-icon>
                                            <v-list-item-title>{{$t("generate_microdata_resource")}}</v-list-item-title>
                                        </v-list-item>
                                        <v-list-item @click="importResource">
                                            <v-list-item-icon><v-icon small>mdi-file-upload</v-icon></v-list-item-icon>
                                            <v-list-item-title>{{$t("import_resources")}}</v-list-item-title>
                                        </v-list-item>
                                        <v-list-item :to="'/files'">
                                            <v-list-item-icon><v-icon small>mdi-folder-open</v-icon></v-list-item-icon>
                                            <v-list-item-title>{{$t("file_manager")}}</v-list-item-title>
                                        </v-list-item>
                                    </v-list>
                                </v-menu>
                            </div>
                        </v-col>
                    </v-row>
                </v-card-subtitle>
            <v-card-text>
                <external-resources-edit v-if="ActiveResourceIndex" :index="ActiveResourceIndex"/>
                <div v-else>
                    <div v-if="isMicrodataProject && (microdataResourceRows.length > 0 || DataFiles.length > 0)" class="mb-4">
                        <div class="d-flex align-center mb-2">
                            <strong>{{$t("microdata_resources")}}</strong>
                            <v-chip x-small class="ml-2">{{ microdataResourceRows.length }}</v-chip>
                        </div>
                        <div
                            v-if="microdataResourceRows.length === 0 && DataFiles.length > 0"
                            class="grey lighten-4 border rounded pa-4 mb-6 d-flex align-start"
                        >
                            <v-icon color="primary" class="mr-3 mt-1">mdi-database-export</v-icon>
                            <div>
                                <div class="text-body-1">{{ $t('microdata_resources_empty_hint') }}</div>
                                <v-btn
                                    v-if="canGenerateMicrodata"
                                    color="primary"
                                    outlined
                                    small
                                    class="mt-3"
                                    @click="goGenerateMicrodata()"
                                >
                                    {{ $t('generate_microdata_resource') }}
                                </v-btn>
                            </div>
                        </div>
                        <table v-if="microdataResourceRows.length > 0" class="table table-striped mb-4">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <v-checkbox
                                            :input-value="isAllMicrodataSelected"
                                            :indeterminate="isMicrodataIndeterminate"
                                            @change="selectAllMicrodataResources"
                                            hide-details dense
                                        ></v-checkbox>
                                    </th>
                                    <th>{{$t("resource")}}</th>
                                    <th>{{$t("resource_type")}}</th>
                                    <th>{{$t("modified")}}</th>
                                    <th>{{$t("actions")}}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in microdataResourceRows" class="resource-row" :key="'micro-' + row.resource.id">
                                    <td>
                                        <v-checkbox
                                            :input-value="isResourceSelected(row.resource.id)"
                                            @change="toggleResourceSelection(row.resource.id)"
                                            hide-details dense
                                        ></v-checkbox>
                                    </td>
                                    <td>
                                        <i class="fas fa-file-alt"></i>
                                        <router-link class="nav-item" :to="'/external-resources/' + row.resource.id">{{row.resource.title}}</router-link>
                                        <v-chip v-if="isGeneratedResource(row.resource)" x-small class="ml-1">{{ $t('generated') || 'Generated' }}</v-chip>
                                        <v-chip
                                            v-if="stalenessLabel(row.resource.id)"
                                            x-small
                                            :color="stalenessLabel(row.resource.id).color"
                                            text-color="white"
                                            class="ml-1"
                                        >{{ stalenessLabel(row.resource.id).text }}</v-chip>
                                        <div class="text-small text-secondary">
                                            <v-icon
                                                v-if="!isValidUrl(row.resource.filename) && row.resource.filename && fileExists(row.resource.filename) === true"
                                                x-small color="success" style="margin-right:4px;">mdi-check-circle</v-icon>
                                            <v-icon
                                                v-if="!isValidUrl(row.resource.filename) && row.resource.filename && fileExists(row.resource.filename) === false"
                                                x-small color="error" style="margin-right:4px;">mdi-alert-circle</v-icon>
                                            <span>{{row.resource.filename}}</span>
                                        </div>
                                    </td>
                                    <td>{{row.resource.dctype}}</td>
                                    <td>{{momentDateUnix(row.resource.changed)}}</td>
                                    <td>
                                        <v-menu offset-y>
                                            <template v-slot:activator="{ on, attrs }">
                                                <v-btn small icon v-on="on" v-bind="attrs" :title="$t('more_options')" color="primary">
                                                    <v-icon>mdi-dots-vertical</v-icon>
                                                </v-btn>
                                            </template>
                                            <v-list dense>
                                                <v-list-item @click="editResource(row.resource.id)">
                                                    <v-list-item-icon><v-icon>mdi-pencil</v-icon></v-list-item-icon>
                                                    <v-list-item-title>{{$t("edit")}}</v-list-item-title>
                                                </v-list-item>
                                                <v-list-item v-if="isGeneratedResource(row.resource) && isProjectEditable" @click="goRegenerateMicrodata(row.resource)">
                                                    <v-list-item-icon><v-icon>mdi-refresh</v-icon></v-list-item-icon>
                                                    <v-list-item-title>{{$t("regenerate")}}</v-list-item-title>
                                                </v-list-item>
                                                <v-list-item v-if="!isGeneratedResource(row.resource)" @click="duplicateResource(row.resource.id)">
                                                    <v-list-item-icon><v-icon>mdi-content-duplicate</v-icon></v-list-item-icon>
                                                    <v-list-item-title>{{$t("duplicate")}}</v-list-item-title>
                                                </v-list-item>
                                                <v-divider></v-divider>
                                                <v-list-item @click="deleteResource(row.resource.id)" class="red--text">
                                                    <v-list-item-icon><v-icon color="red">mdi-delete-outline</v-icon></v-list-item-icon>
                                                    <v-list-item-title class="red--text">{{$t("delete")}}</v-list-item-title>
                                                </v-list-item>
                                            </v-list>
                                        </v-menu>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div>
                        <div class="d-flex align-center mb-2">
                            <strong>{{ $t('external_resources') }}</strong>
                            <v-chip x-small class="ml-2">{{ otherResourceRows.length }}</v-chip>
                        </div>
                        <div
                            v-if="otherResourceRows.length === 0"
                            class="grey lighten-4 border rounded pa-4 mb-6 d-flex align-start"
                        >
                            <v-icon color="grey" class="mr-3 mt-1">mdi-file-document-outline</v-icon>
                            <div class="text-body-1">{{ $t('no_external_resources_found') }}</div>
                        </div>
                        <table v-if="otherResourceRows.length > 0" class="table table-striped">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <v-checkbox
                                            :input-value="isAllSelected"
                                            :indeterminate="isIndeterminate"
                                            @change="selectAllResources"
                                            hide-details dense
                                        ></v-checkbox>
                                    </th>
                                    <th>{{$t("resource")}}</th>
                                    <th>{{$t("resource_type")}}</th>
                                    <th>{{$t("modified")}}</th>
                                    <th>{{$t("actions")}}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in otherResourceRows" class="resource-row" :key="'other-' + row.resource.id">
                                    <td>
                                        <v-checkbox
                                            :input-value="isResourceSelected(row.resource.id)"
                                            @change="toggleResourceSelection(row.resource.id)"
                                            hide-details dense
                                        ></v-checkbox>
                                    </td>
                                    <td>
                                        <i class="fas fa-file-alt"></i>
                                        <router-link class="nav-item" :to="'/external-resources/' + row.resource.id">{{row.resource.title}}</router-link>
                                        <div class="text-small text-secondary">
                                            <v-icon
                                                v-if="!isValidUrl(row.resource.filename) && row.resource.filename && fileExists(row.resource.filename) === true"
                                                x-small color="success" style="margin-right:4px;">mdi-check-circle</v-icon>
                                            <v-icon
                                                v-if="!isValidUrl(row.resource.filename) && row.resource.filename && fileExists(row.resource.filename) === false"
                                                x-small color="error" style="margin-right:4px;">mdi-alert-circle</v-icon>
                                            <span>{{row.resource.filename}}</span>
                                        </div>
                                    </td>
                                    <td>{{row.resource.dctype}}</td>
                                    <td>{{momentDateUnix(row.resource.changed)}}</td>
                                    <td>
                                        <v-menu offset-y>
                                            <template v-slot:activator="{ on, attrs }">
                                                <v-btn small icon v-on="on" v-bind="attrs" :title="$t('more_options')" color="primary">
                                                    <v-icon>mdi-dots-vertical</v-icon>
                                                </v-btn>
                                            </template>
                                            <v-list dense>
                                                <v-list-item @click="editResource(row.resource.id)">
                                                    <v-list-item-icon><v-icon>mdi-pencil</v-icon></v-list-item-icon>
                                                    <v-list-item-title>{{$t("edit")}}</v-list-item-title>
                                                </v-list-item>
                                                <v-list-item @click="duplicateResource(row.resource.id)">
                                                    <v-list-item-icon><v-icon>mdi-content-duplicate</v-icon></v-list-item-icon>
                                                    <v-list-item-title>{{$t("duplicate")}}</v-list-item-title>
                                                </v-list-item>
                                                <v-divider></v-divider>
                                                <v-list-item @click="deleteResource(row.resource.id)" class="red--text">
                                                    <v-list-item-icon><v-icon color="red">mdi-delete-outline</v-icon></v-list-item-icon>
                                                    <v-list-item-title class="red--text">{{$t("delete")}}</v-list-item-title>
                                                </v-list-item>
                                            </v-list>
                                        </v-menu>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </v-card-text>
            </v-card>

        </div>
    `
})


