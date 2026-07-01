<!DOCTYPE html>
<html>

<head>
  <link rel="icon" href="<?php echo base_url();?>favicon.ico">
  <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
  <link href="<?php echo base_url();?>vue-app/assets/mdi.min.css" rel="stylesheet">
  <link href="<?php echo base_url();?>vue-app/assets/vuetify.min.css" rel="stylesheet">
  <link href="<?php echo base_url();?>vue-app/assets/bootstrap.min.css" rel="stylesheet">
  <script src="<?php echo base_url();?>vue-app/assets/jquery.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/bootstrap.bundle.min.js"></script>
  <link href="<?php echo base_url();?>vue-app/assets/styles.css" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">
  <style>
    [v-cloak] { display: none; }
  </style>
</head>

<body class="layout-top-nav">

<?php
  $user = $this->session->userdata('username');
  $this->load->library('Editor_acl');
  $has_schema_permission = false;
  try {
      $has_schema_permission = $this->editor_acl->has_access('schema', 'view');
  } catch (Exception $e) {
      $has_schema_permission = false;
  }
  $user_info = array(
    'username' => $user,
    'is_logged_in' => !empty($user),
    'is_admin' => $this->ion_auth->is_admin(),
    'has_schema_permission' => $has_schema_permission,
  );
?>

  <script>
    var CI = {
      'site_url': '<?php echo site_url(); ?>',
      'base_url': '<?php echo base_url(); ?>',
      'user_info': <?php echo json_encode($user_info); ?>
    };
  </script>

  <div id="app" data-app>
    <v-app>
      <alert-dialog></alert-dialog>
      <confirm-dialog></confirm-dialog>

      <div class="wrapper">
        <vue-global-site-header></vue-global-site-header>
        <div class="content-wrapperx" v-cloak>
          <section class="content">
            <div class="container-fluid">
              <div class="row">
                <div class="sidebar col-md-3 col-sm-4">
                  <div class="mr-4 mt-5">
                    <v-expansion-panels v-model="filterPanel" multiple>

                      <v-expansion-panel>
                        <v-expansion-panel-header class="capitalize">Status</v-expansion-panel-header>
                        <v-expansion-panel-content>
                          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:4px;" v-for="opt in scopedStatusOptions" :key="opt.value">
                            <v-checkbox
                              v-model="filters.status"
                              :value="opt.value"
                              hide-details dense
                              class="mt-0 pt-0 facet-checkbox"
                              style="flex:1;min-width:0;"
                            >
                              <template v-slot:label>
                                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ opt.text }}</span>
                              </template>
                            </v-checkbox>
                          </div>
                        </v-expansion-panel-content>
                      </v-expansion-panel>

                      <v-expansion-panel>
                        <v-expansion-panel-header class="capitalize">Severity</v-expansion-panel-header>
                        <v-expansion-panel-content>
                          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:4px;" v-for="opt in severityOptions.filter(o => o.value)" :key="opt.value">
                            <v-checkbox
                              v-model="filters.severity"
                              :value="opt.value"
                              hide-details dense
                              class="mt-0 pt-0 facet-checkbox"
                              style="flex:1;min-width:0;"
                            >
                              <template v-slot:label>
                                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ opt.text }}</span>
                              </template>
                            </v-checkbox>
                          </div>
                        </v-expansion-panel-content>
                      </v-expansion-panel>

                      <v-expansion-panel>
                        <v-expansion-panel-header class="capitalize">Category</v-expansion-panel-header>
                        <v-expansion-panel-content>
                          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:4px;" v-for="opt in categoryOptions.filter(o => o.value)" :key="opt.value">
                            <v-checkbox
                              v-model="filters.category"
                              :value="opt.value"
                              hide-details dense
                              class="mt-0 pt-0 facet-checkbox"
                              style="flex:1;min-width:0;"
                            >
                              <template v-slot:label>
                                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ opt.text }}</span>
                              </template>
                            </v-checkbox>
                          </div>
                        </v-expansion-panel-content>
                      </v-expansion-panel>

                      <v-expansion-panel>
                        <v-expansion-panel-header class="capitalize">Applied</v-expansion-panel-header>
                        <v-expansion-panel-content>
                          <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;" v-for="opt in appliedOptions.filter(o => o.value !== '')" :key="opt.value">
                            <v-checkbox
                              v-model="filters.applied"
                              :value="opt.value"
                              hide-details dense
                              class="mt-0 pt-0 facet-checkbox"
                              style="flex:1;min-width:0;"
                            >
                              <template v-slot:label>
                                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ opt.text }}</span>
                              </template>
                            </v-checkbox>
                          </div>
                        </v-expansion-panel-content>
                      </v-expansion-panel>

                    </v-expansion-panels>

                    <div class="mt-3">
                      <v-btn @click="clearFilters" outlined small block>
                        <v-icon left small>mdi-filter-off</v-icon>
                        Clear Filters
                      </v-btn>
                    </div>
                  </div>
                </div>

                <div class="col-md-9 col-sm-8">
                  <div class="mt-5 mb-5">
                    <div class="mb-5">
                      <main-navigation-tabs active-tab="issues" v-model="navTabsModel"></main-navigation-tabs>
                    </div>

                    <div class="d-flex">
                      <div class="flex-grow-1 flex-shrink-0 mr-auto">
                        <h3 class="mt-3">Issues</h3>
                      </div>
                    </div>
                  </div>

                  <div class="d-flex search-box mb-3">
                    <div style="min-width: 100px; max-width: 100%;" class="flex-grow-1 flex-shrink-0">
                      <v-text-field
                        background-color="white"
                        v-model="searchQuery"
                        :prepend-inner-icon="loading ? 'mdi-loading mdi-spin' : 'mdi-magnify'"
                        label="Search issues..."
                        single-line
                        dense
                        outlined
                        clearable
                        :loading="loading"
                        @click:clear="searchQuery = ''"
                      ></v-text-field>
                    </div>
                    <div class="flex-grow-0 flex-shrink-0">
                      <div class="ml-3" style="width: 160px;">
                        <v-select
                          :items="sortByOptions"
                          v-model="sortBy"
                          item-text="text"
                          item-value="value"
                          label=""
                          background-color="white"
                          dense
                          outlined
                        ></v-select>
                      </div>
                    </div>
                  </div>

                  <!-- Active filter chips -->
                  <div v-if="hasActiveFilters" class="mb-3">
                    <v-chip v-if="(searchQuery || '').trim()" small close class="mr-1 mb-1" color="grey darken-1" dark @click:close="searchQuery = ''">
                      <v-icon left x-small>mdi-magnify</v-icon>
                      {{ searchQuery }}
                    </v-chip>
                    <template v-for="(values, type) in filters">
                      <v-chip
                        v-for="val in values"
                        :key="type + '_' + val"
                        small close
                        class="mr-1 mb-1"
                        :color="getFilterChipColor(type)"
                        dark
                        @click:close="removeFilter(type, val)"
                      >
                        {{ getFilterLabel(type, val) }}
                      </v-chip>
                    </template>
                    <v-btn x-small text @click="clearFilters(); searchQuery = '';" class="ml-1">Clear all</v-btn>
                  </div>

                  <div class="bg-white shadow rounded p-3 pt-1 mt-2">

                    <!-- Open / Closed toggle -->
                    <div class="d-flex align-center py-3 px-1" style="border-bottom: 1px solid #e0e0e0;">
                      <a href="javascript:void(0)" @click="setScope('open')"
                         class="mr-4 text-decoration-none d-flex align-center"
                         :style="statusScope === 'open' ? 'font-weight:600;color:inherit;' : 'color:#6c757d;'"
                      >
                        <v-icon small :color="statusScope === 'open' ? 'success' : 'grey lighten-1'" class="mr-1">mdi-alert-circle-outline</v-icon>
                        {{ openCount }} Open
                      </a>
                      <a href="javascript:void(0)" @click="setScope('closed')"
                         class="text-decoration-none d-flex align-center"
                         :style="statusScope === 'closed' ? 'font-weight:600;color:inherit;' : 'color:#6c757d;'"
                      >
                        <v-icon small :color="statusScope === 'closed' ? 'grey darken-1' : 'grey lighten-1'" class="mr-1">mdi-check-circle-outline</v-icon>
                        {{ closedCount }} Closed
                      </a>
                    </div>

                    <div v-if="loading" class="mt-5 mb-3 p-3 text-center">
                      <v-progress-circular indeterminate color="primary" class="mr-2"></v-progress-circular>
                      <span>Loading issues...</span>
                    </div>

                    <div v-if="!loading && issues.length === 0" class="mt-5 mb-3 p-3 border text-center text--secondary">
                      No issues found
                    </div>

                    <table class="table table-hover border-bottom" v-if="issues.length > 0">
                      <thead>
                        <tr>
                          <th style="width:30px;">
                            <input type="checkbox" v-model="selectAll" @change="toggleAll" />
                          </th>
                          <th style="width:60px;">
                            <v-menu offset-y :disabled="selected.length === 0">
                              <template v-slot:activator="{ on, attrs }">
                                <v-btn icon v-bind="attrs" v-on="on" :disabled="selected.length === 0">
                                  <v-icon>mdi-dots-vertical</v-icon>
                                </v-btn>
                              </template>
                              <v-list dense>
                                <v-list-item @click="bulkUpdateStatus('accepted')">
                                  <v-icon left small>mdi-check</v-icon>
                                  <v-list-item-title>Accept</v-list-item-title>
                                </v-list-item>
                                <v-list-item @click="bulkUpdateStatus('dismissed')">
                                  <v-icon left small>mdi-minus-circle</v-icon>
                                  <v-list-item-title>Dismiss</v-list-item-title>
                                </v-list-item>
                                <v-list-item @click="bulkUpdateStatus('false_positive')">
                                  <v-icon left small>mdi-alert-remove</v-icon>
                                  <v-list-item-title>Mark as False Positive</v-list-item-title>
                                </v-list-item>
                                <v-list-item @click="bulkUpdateStatus('rejected')">
                                  <v-icon left small>mdi-close</v-icon>
                                  <v-list-item-title>Reject</v-list-item-title>
                                </v-list-item>
                                <v-divider></v-divider>
                                <v-list-item @click="bulkDelete">
                                  <v-icon left small color="error">mdi-delete</v-icon>
                                  <v-list-item-title class="error--text">Delete</v-list-item-title>
                                </v-list-item>
                              </v-list>
                            </v-menu>
                          </th>
                          <th style="width:4px;"></th>
                          <th>Title</th>
                          <th style="width:180px;">Labels</th>
                          <th style="width:120px;">Status</th>
                          <th style="width:110px;">Created</th>
                          <th style="width:40px;"></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="item in issues" :key="item.id">
                          <td>
                            <input type="checkbox" v-model="selected" :value="item.id" />
                          </td>
                          <td style="vertical-align: top; text-align: center; width: 48px; padding-top: 14px;">
                            <v-icon
                              size="36"
                              :color="isOpenStatus(item.status) ? 'success' : 'grey lighten-1'"
                              :title="item.status"
                            >{{ isOpenStatus(item.status) ? 'mdi-alert-circle-outline' : 'mdi-check-circle-outline' }}</v-icon>
                          </td>
                          <td style="width:4px;"></td>
                          <td>
                            <div>
                              <a :href="issueUrl(item)" class="font-weight-bold text-dark">{{ item.title || '(no title)' }}</a>
                            </div>
                            <div class="text-muted mt-1" style="font-size: 0.9em;">
                              <v-icon x-small class="mr-1">mdi-open-in-new</v-icon>
                              <a :href="projectUrl(item)" target="_blank" class="text-muted">{{ item.project_title || ('Project ' + item.project_id) }}</a>
                              <span v-if="item.field_path"> &middot; <code style="font-size: 11px;">{{ item.field_path }}</code></span>
                            </div>
                            <div class="text-muted" style="font-size: 0.85em;">
                              #{{ item.id }} &middot; opened {{ formatDate(item.created) }}<span v-if="item.created_by_username"> by {{ item.created_by_username }}</span>
                            </div>
                          </td>
                          <td style="vertical-align: top; padding-top: 12px;">
                            <v-chip v-if="item.severity" x-small :color="getSeverityColor(item.severity)" outlined class="text-capitalize mr-1 mb-1">{{ item.severity }}</v-chip>
                            <v-chip v-if="item.category" x-small outlined class="mb-1">{{ getFilterLabel('category', item.category) }}</v-chip>
                          </td>
                          <td style="vertical-align: top; padding-top: 12px;">
                            <v-chip small :color="getStatusColor(item.status)" dark class="text-capitalize">{{ formatStatus(item.status) }}</v-chip>
                          </td>
                          <td class="text-nowrap text-muted" style="vertical-align: top; padding-top: 14px;">{{ formatDate(item.created) }}</td>
                          <td style="vertical-align: top; padding-top: 10px;">
                            <a :href="issueUrl(item)" title="View">
                              <v-icon small>mdi-chevron-right</v-icon>
                            </a>
                          </td>
                        </tr>
                      </tbody>
                    </table>

                    <div class="mb-3 mt-2" v-if="pageCount > 1">
                      <v-pagination v-model="options.page" :length="pageCount" :total-visible="6"></v-pagination>
                    </div>

                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>
      </div>
    </v-app>
  </div>

  <script src="<?php echo base_url();?>vue-app/assets/vue-i18n.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/vue.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/moment-with-locales.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/vuetify.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/axios.min.js"></script>

  <script>
    <?php
    echo $this->load->view("vue/vue-global-eventbus.js", null, true);
    echo $this->load->view("vue/vue-alert-dialog-component.js", null, true);
    echo $this->load->view("vue/vue-confirm-dialog-component.js", null, true);
    echo $this->load->view("editor_common/global-site-header-component.js", null, true);
    echo $this->load->view("editor_common/main-navigation-tabs-component.js", null, true);
    ?>
  </script>

  <script>
    (function() {
      const translations = <?php echo json_encode(isset($translations) ? $translations : array(), JSON_UNESCAPED_UNICODE); ?>;
      const i18n = new VueI18n({ locale: 'default', messages: { default: translations } });
      const vuetify = new Vuetify({
        theme: {
          themes: {
            light: {
              primary: '#526bc7',
              'primary-dark': '#0c1a4d',
              secondary: '#b0bec5',
              accent: '#8c9eff',
              error: '#b71c1c'
            }
          }
        }
      });

      const apiBase = (CI && CI.site_url ? CI.site_url : '').replace(/\/?$/, '/') + 'api/issues';

      new Vue({
        el: '#app',
        i18n,
        vuetify,
        data() {
          return {
            navTabsModel: 7,
            issues: [],
            totalIssues: 0,
            selected: [],
            selectAll: false,
            statusScope: 'open',
            openCount: 0,
            closedCount: 0,
            searchQuery: '',
            sortBy: 'created_desc',
            sortByOptions: [
              { value: 'created_desc', text: 'Newest first' },
              { value: 'created_asc',  text: 'Oldest first' },
              { value: 'title_asc',    text: 'Title A–Z' },
              { value: 'title_desc',   text: 'Title Z–A' },
              { value: 'severity_desc',text: 'Severity (high first)' }
            ],
            searchDebounce: null,
            loading: false,
            filters: {
              status: [],
              category: [],
              severity: [],
              applied: []
            },
            filterPanel: [0, 1, 2, 3],
            statusOptions: [
              { text: 'All', value: '' },
              { text: 'Open', value: 'open' },
              { text: 'Accepted', value: 'accepted' },
              { text: 'Fixed', value: 'fixed' },
              { text: 'Rejected', value: 'rejected' },
              { text: 'Dismissed', value: 'dismissed' },
              { text: 'False Positive', value: 'false_positive' }
            ],
            categoryOptions: [
              { text: 'All',             value: '' },
              { text: 'Typo / Wording', value: 'typo_wording' },
              { text: 'Inconsistency',   value: 'inconsistency' },
              { text: 'Missing Data',    value: 'missing_data' },
              { text: 'Format Issue',    value: 'format_issue' },
              { text: 'Completeness',    value: 'completeness' },
              { text: 'Other',           value: 'other' }
            ],
            severityOptions: [
              { text: 'All', value: '' },
              { text: 'Low', value: 'low' },
              { text: 'Medium', value: 'medium' },
              { text: 'High', value: 'high' },
              { text: 'Critical', value: 'critical' }
            ],
            appliedOptions: [
              { text: 'All', value: '' },
              { text: 'Applied', value: '1' },
              { text: 'Not Applied', value: '0' }
            ],
            options: {
              page: 1,
              itemsPerPage: 25,
              sortBy: ['created'],
              sortDesc: [true]
            }
          };
        },
        watch: {
          filters: {
            handler() {
              if (this._initializing) return;
              this.options.page = 1;
              this.loadIssues();
            },
            deep: true
          },
          'options.page'() {
            if (this._initializing) return;
            this.loadIssues();
          },
          'options.itemsPerPage'() {
            if (this._initializing) return;
            this.options.page = 1;
            this.loadIssues();
          },
          searchQuery() {
            if (this._initializing) return;
            if (this.searchDebounce) clearTimeout(this.searchDebounce);
            this.searchDebounce = setTimeout(() => {
              this.options.page = 1;
              this.loadIssues();
            }, 350);
          },
          sortBy() {
            if (this._initializing) return;
            this.options.page = 1;
            this.loadIssues();
          }
        },
        computed: {
          pageCount() {
            return Math.ceil(this.totalIssues / this.options.itemsPerPage) || 1;
          },
          scopedStatusOptions() {
            if (this.statusScope === 'open') {
              return this.statusOptions.filter(o => ['open', 'accepted'].includes(o.value));
            }
            if (this.statusScope === 'closed') {
              return this.statusOptions.filter(o => ['fixed', 'rejected', 'dismissed', 'false_positive'].includes(o.value));
            }
            return this.statusOptions.filter(o => o.value);
          },
          hasActiveFilters() {
            return (this.searchQuery || '').trim() ||
              this.filters.status.length ||
              this.filters.category.length ||
              this.filters.severity.length ||
              this.filters.applied.length;
          }
        },
        created() {
          this._initializing = true;
          this.readFromUrl();
          this.$nextTick(() => { this._initializing = false; });
          this.loadIssues();
          this.loadCounts();
        },
        methods: {
          loadIssues() {
            this.loading = true;
            const offset = (this.options.page - 1) * this.options.itemsPerPage;
            const params = { 
              limit: this.options.itemsPerPage, 
              offset: offset 
            };
            
            const q = (this.searchQuery || '').trim();
            if (q) params.search = q;

            // Add scope
            if (this.statusScope) params.scope = this.statusScope;

            // Add sort
            const sortMap = {
              'created_desc':  { sort_by: 'created',  sort_order: 'DESC' },
              'created_asc':   { sort_by: 'created',  sort_order: 'ASC'  },
              'title_asc':     { sort_by: 'title',    sort_order: 'ASC'  },
              'title_desc':    { sort_by: 'title',    sort_order: 'DESC' },
              'severity_desc': { sort_by: 'severity', sort_order: 'DESC' }
            };
            const sort = sortMap[this.sortBy] || sortMap['created_desc'];
            params.sort_by    = sort.sort_by;
            params.sort_order = sort.sort_order;

            // Add filters
            if (this.filters.status.length)   params.status   = this.filters.status.join(',');
            if (this.filters.category.length)  params.category = this.filters.category.join(',');
            if (this.filters.severity.length)  params.severity = this.filters.severity.join(',');
            if (this.filters.applied.length)   params.applied  = this.filters.applied.join(',');

            axios.get(apiBase, { params: params })
              .then(res => {
                if (res.data && res.data.status === 'success') {
                  this.updateUrl();
                  this.selected = [];
                  this.selectAll = false;
                  this.issues = (res.data.issues || []).map(issue => {
                    return {
                      ...issue,
                      project_title: issue.project_title || 'Project ' + issue.project_id
                    };
                  });
                  this.totalIssues = res.data.total != null ? res.data.total : 0;
                } else {
                  this.issues = [];
                  this.totalIssues = 0;
                }
              })
              .catch(err => {
                this.issues = [];
                this.totalIssues = 0;
                const msg = (err.response && err.response.data && err.response.data.message) ? err.response.data.message : (err.message || 'Failed to load issues');
                EventBus.$emit('alert', { message: msg });
              })
              .finally(() => { this.loading = false; });
          },
          truncateText(text, length) {
            if (!text) return '';
            return text.length > length ? text.substring(0, length) + '...' : text;
          },
          formatDate(timestamp) {
            if (!timestamp) return '-';
            return moment.unix(timestamp).format('YYYY-MM-DD');
          },
          getSeverityColor(severity) {
            const colors = {
              low: 'blue',
              medium: 'orange',
              high: 'deep-orange',
              critical: 'red'
            };
            return colors[severity] || 'grey';
          },
          getStatusColor(status) {
            const colors = {
              open: 'primary',
              accepted: 'blue',
              fixed: 'success',
              rejected: 'error',
              dismissed: 'grey',
              false_positive: 'warning'
            };
            return colors[status] || 'grey';
          },
          formatStatus(status) {
            return (status || '').replace(/_/g, ' ');
          },
          setScope(scope) {
            this.statusScope = scope;
            this.filters.status = [];
            this.options.page = 1;
            this.loadIssues();
          },
          async loadCounts() {
            try {
              const [openRes, closedRes] = await Promise.all([
                axios.get(apiBase, { params: { limit: 1, offset: 0, scope: 'open' } }),
                axios.get(apiBase, { params: { limit: 1, offset: 0, scope: 'closed' } })
              ]);
              if (openRes.data && openRes.data.status === 'success') this.openCount = openRes.data.total || 0;
              if (closedRes.data && closedRes.data.status === 'success') this.closedCount = closedRes.data.total || 0;
            } catch (e) {}
          },
          toggleAll() {
            if (this.selectAll) {
              this.selected = this.issues.map(i => i.id);
            } else {
              this.selected = [];
            }
          },
          async bulkUpdateStatus(status) {
            if (!this.selected.length) return;
            try {
              const url = apiBase + '/bulk_status';
              const res = await axios.post(url, { ids: this.selected, status: status });
              if (res.data && res.data.status === 'success') {
                this.selected = [];
                this.selectAll = false;
                this.loadIssues();
                this.loadCounts();
              }
            } catch (err) {
              console.error('Bulk status error', err);
            }
          },
          async bulkDelete() {
            if (!this.selected.length) return;
            if (!confirm('Delete ' + this.selected.length + ' selected issue(s)? This cannot be undone.')) return;
            try {
              const url = apiBase + '/bulk_delete';
              const res = await axios.post(url, { ids: this.selected });
              if (res.data && res.data.status === 'success') {
                this.selected = [];
                this.selectAll = false;
                this.loadIssues();
                this.loadCounts();
              }
            } catch (err) {
              console.error('Bulk delete error', err);
            }
          },
          isOpenStatus(status) {
            return status === 'open' || status === 'accepted';
          },
          issueUrl(item) {
            return (CI.site_url || '').replace(/\/?$/, '') + '/issues/edit/' + item.id;
          },
          projectUrl(item) {
            return (CI.site_url || '').replace(/\/?$/, '') + '/editor/edit/' + item.project_id;
          },
          clearFilters() {
            this.filters = { status: [], category: [], severity: [], applied: [] };
          },
          getFilterLabel(type, value) {
            const maps = {
              status: this.statusOptions,
              category: this.categoryOptions,
              severity: this.severityOptions,
              applied: this.appliedOptions
            };
            const found = (maps[type] || []).find(o => o.value === value);
            return found ? found.text : value;
          },
          getFilterChipColor(type) {
            const colors = { status: 'primary', severity: 'deep-orange', category: 'blue', applied: 'teal' };
            return colors[type] || 'grey';
          },
          removeFilter(type, value) {
            this.filters[type] = this.filters[type].filter(v => v !== value);
          },
          updateUrl() {
            const params = new URLSearchParams();
            if ((this.searchQuery || '').trim()) params.set('q', this.searchQuery.trim());
            if (this.statusScope !== 'open') params.set('scope', this.statusScope);
            if (this.sortBy !== 'created_desc') params.set('sort', this.sortBy);
            if (this.options.page > 1) params.set('page', this.options.page);
            ['status', 'category', 'severity', 'applied'].forEach(key => {
              if (this.filters[key] && this.filters[key].length) params.set(key, this.filters[key].join(','));
            });
            const qs = params.toString();
            history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : ''));
          },
          readFromUrl() {
            const params = new URLSearchParams(window.location.search);
            if (params.get('q')) this.searchQuery = params.get('q');
            if (params.get('scope')) this.statusScope = params.get('scope');
            if (params.get('sort')) this.sortBy = params.get('sort');
            if (params.get('page')) this.options.page = parseInt(params.get('page')) || 1;
            ['status', 'category', 'severity', 'applied'].forEach(key => {
              const val = params.get(key);
              if (val) this.filters[key] = val.split(',').filter(Boolean);
            });
          }
        }
      });
    })();
  </script>
</body>
</html>
