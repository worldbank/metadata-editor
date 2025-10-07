// Vue Audit Logs Component
Vue.component('vue-audit-logs-component', {
    data: function () {
        return {
            audit_logs: [],
            loading: false,
            pagination: {
                page: 1,
                itemsPerPage: 15,
                total: 0,
                totalPages: 0
            },
            filters: {
                user_id: null,
                obj_type: null,
                action_type: null,
                obj_ref_id: null
            },
            filter_options: {
                users: [],
                object_types: [
                    { value: 'project', text: 'Project' },
                    { value: 'collection', text: 'Collection' },
                    { value: 'template', text: 'Template' },
                    { value: 'datafile', text: 'Data File' },
                    { value: 'variable', text: 'Variable' },
                    { value: 'user', text: 'User' }
                ],
                action_types: [
                    { value: 'create', text: 'Create' },
                    { value: 'update', text: 'Update' },
                    { value: 'delete', text: 'Delete' },
                    { value: 'patch', text: 'Patch' }
                ]
            },
            expanded_rows: [],
            show_filters: false,
            error_message: '',
            success_message: '',
            loading_details: {},
            detailed_logs: {}
        }
    },
    
    mounted: function() {
        this.loadAuditLogs();
        this.loadUsers();
    },
    
    computed: {
        table_headers() {
            return [
                { text: this.$t('date_time'), value: 'created', sortable: true },
                { text: this.$t('user'), value: 'username', sortable: true },
                { text: this.$t('object_type'), value: 'obj_type', sortable: true },
                { text: this.$t('object_id'), value: 'obj_id', sortable: true },
                { text: this.$t('action'), value: 'action_type', sortable: true }
            ];
        },
        
        combinedMetadata() {
            return (logEntry) => {
                if (!logEntry) return '';
                
                const combined = {};
                
                // Add original metadata
                if (logEntry.metadata) {
                    Object.assign(combined, logEntry.metadata);
                }
                
                // Add project info if available
                if (logEntry.project_info) {
                    combined.project_info = logEntry.project_info;
                }
                
                // Add collection info if available
                if (logEntry.collection_info) {
                    combined.collection_info = logEntry.collection_info;
                }
                
                return this.formatMetadata(combined);
            };
        },
        
        current_offset() {
            return (this.pagination.page - 1) * this.pagination.itemsPerPage;
        }
    },
    
    methods: {
        loadAuditLogs() {
            this.loading = true;
            this.error_message = '';
            
            // Build query parameters
            const params = new URLSearchParams();
            params.append('limit', this.pagination.itemsPerPage);
            params.append('offset', this.current_offset);
            
            // Add filters
            if (this.filters.user_id) {
                params.append('user_id', this.filters.user_id);
            }
            if (this.filters.obj_type) {
                params.append('obj_type', this.filters.obj_type);
            }
            if (this.filters.action_type) {
                params.append('action_type', this.filters.action_type);
            }
            if (this.filters.obj_ref_id) {
                params.append('obj_ref_id', this.filters.obj_ref_id);
            }
            
            // Make API call
            const url = `${CI.site_url}/api/audit_logs/index?${params.toString()}`;
            
            fetch(url, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    this.audit_logs = data.data;
                    this.pagination.total = data.pagination.total;
                    this.pagination.totalPages = data.pagination.total_pages;
                } else {
                    this.error_message = data.message || this.$t('error_loading_logs');
                }
            })
            .catch(error => {
                console.error('Error loading audit logs:', error);
                this.error_message = this.$t('error_loading_logs');
            })
            .finally(() => {
                this.loading = false;
            });
        },
        
        loadUsers() {
            // Load users for filter dropdown
            fetch(`${CI.site_url}/api/users`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' && data.users) {
                    this.filter_options.users = data.users.map(user => ({
                        value: user.id,
                        text: user.username || user.email
                    }));
                }
            })
            .catch(error => {
                console.error('Error loading users:', error);
            });
        },
        
        onPageChange(page) {
            this.pagination.page = page;
            this.loadAuditLogs();
        },
        
        onItemsPerPageChange(itemsPerPage) {
            this.pagination.itemsPerPage = itemsPerPage;
            this.pagination.page = 1; // Reset to first page
            this.loadAuditLogs();
        },
        
        applyFilters() {
            this.pagination.page = 1; // Reset to first page
            this.loadAuditLogs();
        },
        
        clearFilters() {
            this.filters = {
                user_id: null,
                obj_type: null,
                action_type: null,
                obj_ref_id: null
            };
            this.pagination.page = 1;
            this.loadAuditLogs();
        },
        
        refreshLogs() {
            this.loadAuditLogs();
        },
        
        formatDate(dateString) {
            if (!dateString) return '';
            return moment(dateString).format('YYYY-MM-DD HH:mm:ss');
        },
        
        formatDateRelative(dateString) {
            if (!dateString) return '';
            return moment.utc(dateString).local().fromNow();
        },
        
        formatMetadata(metadata) {
            if (!metadata) return '';
            if (typeof metadata === 'string') {
                try {
                    return JSON.stringify(JSON.parse(metadata), null, 2);
                } catch (e) {
                    return metadata;
                }
            }
            return JSON.stringify(metadata, null, 2);
        },
        
        getActionColor(action) {
            const colors = {
                'create': 'success',
                'update': 'info',
                'delete': 'error',
                'patch': 'warning'
            };
            return colors[action] || 'default';
        },
        
        getActionIcon(action) {
            const icons = {
                'create': 'mdi-plus-circle',
                'update': 'mdi-pencil',
                'delete': 'mdi-delete',
                'patch': 'mdi-patch'
            };
            return icons[action] || 'mdi-help-circle';
        },
        
        toggleRowExpansion(item) {
            // Check if this row is already expanded
            const index = this.expanded_rows.indexOf(item);
            if (index > -1) {
                // If already expanded, collapse it
                this.expanded_rows.splice(index, 1);
            } else {
                // Collapse all other rows first (only allow one expanded at a time)
                this.expanded_rows = [item];
                // Load detailed information when expanding
                this.loadLogDetails(item.id);
            }
        },
        
        loadLogDetails(logId) {
            // Check if we already have the details
            if (this.detailed_logs[logId]) {
                return;
            }
            
            // Set loading state
            this.$set(this.loading_details, logId, true);
            
            // Make API call to get detailed information
            fetch(`${CI.site_url}/api/audit_logs/info/${logId}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    this.$set(this.detailed_logs, logId, data.data);
                } else {
                    console.error('Error loading log details:', data.message);
                }
            })
            .catch(error => {
                console.error('Error loading log details:', error);
            })
            .finally(() => {
                this.$set(this.loading_details, logId, false);
            });
        }
    },
    
    template: `
        <div class="audit-logs-component">
            <v-row class="fill-height">
                <!-- Left Sidebar - Filters -->
                <v-col cols="12" md="3" lg="2" class="d-flex flex-column">
                    <v-card class="flex-grow-1 sidebar-filters" flat style="max-height: calc(100vh - 160px); overflow-y: auto;">
                        <v-card-title class="pb-2">
                            <v-icon class="mr-2">mdi-filter</v-icon>
                            {{$t('filters')}}
                            <v-spacer></v-spacer>
                            <v-chip 
                                v-if="filters.user_id || filters.obj_type || filters.action_type || filters.obj_ref_id"
                                small 
                                color="primary" 
                                outlined
                            >
                                {{$t('active')}}
                            </v-chip>
                        </v-card-title>
                        
                        <v-card-text class="pt-0">
                            <v-form>
                                <div class="mb-3">
                                    <label class="text-caption font-weight-medium">{{$t('user')}}</label>
                                    <v-select
                                        v-model="filters.user_id"
                                        :items="filter_options.users"
                                        clearable
                                        outlined
                                        dense
                                        hide-details
                                    ></v-select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="text-caption font-weight-medium">{{$t('object_type')}}</label>
                                    <v-select
                                        v-model="filters.obj_type"
                                        :items="filter_options.object_types"
                                        clearable
                                        outlined
                                        dense
                                        hide-details
                                    ></v-select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="text-caption font-weight-medium">{{$t('action_type')}}</label>
                                    <v-select
                                        v-model="filters.action_type"
                                        :items="filter_options.action_types"
                                        clearable
                                        outlined
                                        dense
                                        hide-details
                                    ></v-select>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="text-caption font-weight-medium">{{$t('object_reference_id')}}</label>
                                    <v-text-field
                                        v-model="filters.obj_ref_id"
                                        outlined
                                        dense
                                        type="number"
                                        clearable
                                        hide-details
                                    ></v-text-field>
                                </div>
                                
                                <v-btn 
                                    color="#526bc7" 
                                    dark
                                    block
                                    @click="applyFilters"
                                    :loading="loading"
                                    class="mb-2"
                                    elevation="2"
                                >
                                    <v-icon class="mr-1">mdi-magnify</v-icon>
                                    {{$t('apply_filters')}}
                                </v-btn>
                                
                                <v-btn 
                                    color="secondary" 
                                    outlined 
                                    block
                                    @click="clearFilters"
                                >
                                    <v-icon class="mr-1">mdi-close</v-icon>
                                    {{$t('clear_filters')}}
                                </v-btn>
                            </v-form>
                        </v-card-text>
                    </v-card>
                </v-col>
                
                <!-- Main Content Area -->
                <v-col cols="12" md="9" lg="10" class="d-flex flex-column">
                    <v-card class="flex-grow-1 d-flex flex-column" style="max-height: calc(100vh - 160px);">
                        <v-card-title>
                            <v-icon class="mr-2">mdi-history</v-icon>
                            {{$t('audit_logs')}}
                            
                            <v-spacer></v-spacer>
                            
                            <v-btn 
                                color="primary" 
                                outlined 
                                small 
                                @click="refreshLogs"
                                :loading="loading"
                            >
                                <v-icon class="mr-1">mdi-refresh</v-icon>
                                {{$t('refresh')}}
                            </v-btn>
                        </v-card-title>
                        
                        <!-- Success/Error Messages -->
                        <v-alert
                            v-if="success_message"
                            type="success"
                            dismissible
                            v-model="success_message"
                            class="ma-2"
                        >
                            {{success_message}}
                        </v-alert>
                        
                        <v-alert
                            v-if="error_message"
                            type="error"
                            dismissible
                            v-model="error_message"
                            class="ma-2"
                        >
                            {{error_message}}
                        </v-alert>
                
                        <!-- Data Table -->
                        <div class="flex-grow-1" style="overflow-y: auto;">
                            <v-data-table
                                :headers="table_headers"
                                :items="audit_logs"
                                :loading="loading"
                                :server-items-length="pagination.total"
                                :items-per-page="pagination.itemsPerPage"
                                :page.sync="pagination.page"
                                @update:page="onPageChange"
                                @update:items-per-page="onItemsPerPageChange"
                                class="elevation-1"
                                show-expand
                                :expanded.sync="expanded_rows"
                                :footer-props="{
                                    'items-per-page-options': [10, 15, 25, 50]
                                }"
                                @click:row="toggleRowExpansion"
                            >
                    <!-- Date/Time Column -->
                    <template v-slot:item.created="{ item }">
                        <div>
                            <div class="text-caption font-weight-medium">
                                {{formatDate(item.created)}}
                            </div>
                            <div class="text-caption text--secondary">
                                {{formatDateRelative(item.created)}}
                            </div>
                        </div>
                    </template>
                    
                    <!-- User Column -->
                    <template v-slot:item.username="{ item }">
                        <div class="d-flex align-center">
                            <v-avatar size="24" class="mr-2">
                                <v-icon>mdi-account</v-icon>
                            </v-avatar>
                            <span>{{item.username || $t('system')}}</span>
                        </div>
                    </template>
                    
                    <!-- Object Type Column -->
                    <template v-slot:item.obj_type="{ item }">
                        <v-chip 
                            small 
                            outlined 
                            color="primary"
                        >
                            {{item.obj_type}}
                        </v-chip>
                    </template>
                    
                    <!-- Action Type Column -->
                    <template v-slot:item.action_type="{ item }">
                        <v-chip 
                            :color="getActionColor(item.action_type)" 
                            small 
                            outlined
                        >
                            <v-icon left small>{{getActionIcon(item.action_type)}}</v-icon>
                            {{item.action_type}}
                        </v-chip>
                    </template>
                    
                    
                    <!-- Expandable Row Content -->
                    <template v-slot:expanded-item="{ headers, item }">
                        <td :colspan="headers.length">
                            <div class="pa-4" >
                                    <!-- Loading State -->
                                    <div v-if="loading_details[item.id]" class="text-center pa-4">
                                        <v-progress-circular indeterminate color="primary" size="24"></v-progress-circular>
                                        <div class="text-caption mt-2">{{$t('loading_details')}}</div>
                                    </div>
                                    
                                    <!-- Detailed Information -->
                                    <div v-else-if="detailed_logs[item.id]">
                                        <v-row>                                            
                                            <v-col cols="6" md="6" class="pr-4">
                                                <v-simple-table dense class="details-table mt-3 mb-5">
                                                    <tbody>
                                                        <tr>
                                                            <td><strong>{{$t('object_id')}}:</strong></td>
                                                            <td>{{detailed_logs[item.id].obj_id}}</td>
                                                        </tr>
                                                        <tr v-if="detailed_logs[item.id].obj_ref_id">
                                                            <td><strong>{{$t('object_reference_id')}}:</strong></td>
                                                            <td>{{detailed_logs[item.id].obj_ref_id}}</td>
                                                        </tr>
                                                        <tr v-if="detailed_logs[item.id].email">
                                                            <td><strong>{{$t('user_email')}}:</strong></td>
                                                            <td>{{detailed_logs[item.id].email}}</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>{{$t('date_time')}}:</strong></td>
                                                            <td>{{formatDate(detailed_logs[item.id].created)}}</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>{{$t('action_type')}}:</strong></td>
                                                            <td>{{detailed_logs[item.id].action_type}}</td>
                                                        </tr>
                                                    </tbody>
                                                </v-simple-table>
                                            </v-col>
                                            <v-col cols="6" md="6" class="pl-4">
                                                <pre class="metadata-display pa-3 mt-3" style="padding:5px;height: 200px; width: 100%; overflow: auto; background-color: #e9ecef; word-wrap: break-word; white-space: pre-wrap;">{{combinedMetadata(detailed_logs[item.id])}}</pre>
                                            </v-col>
                                        </v-row>
                                    </div>
                                    
                                    <!-- Error State -->
                                    <div v-else class="text-center pa-4">
                                        <v-icon color="error" size="48">mdi-alert-circle</v-icon>
                                        <div class="text-h6 text--secondary mt-2">{{$t('error_loading_details')}}</div>
                                        <v-btn 
                                            small 
                                            color="primary" 
                                            outlined 
                                            @click="loadLogDetails(item.id)"
                                            class="mt-2"
                                        >
                                            <v-icon class="mr-1">mdi-refresh</v-icon>
                                            {{$t('retry')}}
                                        </v-btn>
                                    </div>
                            </div>
                        </td>
                    </template>
                    
                                <!-- No Data Template -->
                                <template v-slot:no-data>
                                    <div class="text-center pa-4">
                                        <v-icon size="64" color="grey">mdi-history</v-icon>
                                        <div class="text-h6 mt-2">{{$t('no_audit_logs_found')}}</div>
                                        <div class="text-caption">{{$t('try_adjusting_filters')}}</div>
                                    </div>
                                </template>
                            </v-data-table>
                        </div>
                
                        <!-- Footer Info -->
                        <v-card-text v-if="!loading && audit_logs.length > 0">
                            <div class="text-caption text-center">
                                {{$t('showing_logs_range', {
                                    start: current_offset + 1, 
                                    end: Math.min(current_offset + pagination.itemsPerPage, pagination.total), 
                                    total: pagination.total
                                })}}
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </div>
    `
});
