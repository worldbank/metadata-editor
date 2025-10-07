<!DOCTYPE html>
<html>

<head>
  <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
  <link href="<?php echo base_url();?>vue-app/assets/mdi.min.css" rel="stylesheet">
  <link href="<?php echo base_url();?>vue-app/assets/vuetify.min.css" rel="stylesheet">

  <script src="<?php echo base_url();?>vue-app/assets/moment-with-locales.min.js"></script>

  <link href="<?php echo base_url();?>vue-app/assets/styles.css" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">

<?php
  $user=$this->session->userdata('username');

  $user_info=[
    'username'=> $user,
    'is_logged_in'=> !empty($user),
    'is_admin'=> $this->ion_auth->is_admin(),
  ];
  
?>

</head>

<style>
  .text-xs {
    font-size: small;
    color: gray;
  }

  .cursor-pointer {
    cursor: pointer;
  }

  .v-text-field--filled.v-input--dense.v-text-field--single-line .v-label, .v-text-field--full-width.v-input--dense.v-text-field--single-line .v-label
  {
    font-weight:normal;
  }

  table th {
    white-space: nowrap;
  }

  /*v-tree spacing */
  .v-treeview-node__root {
    height: auto;
    min-height: 30px;
  }

  .v-treeview-node.v-treeview-node--leaf {
    margin-left: 14px;
  }


  .json-diff-container {
    max-height: 600px;
    overflow: auto;
  }

  /* Diff Legend Colors */
  .legend-color {
    width: 16px;
    height: 16px;
    border-radius: 2px;
    border: 1px solid rgba(0,0,0,0.2);
  }
  
  .legend-color.yellow {
    background-color: #ffe082;
    border-color: #ffeaa7;
  }
  
  .legend-color.red {
    background-color: #f8d7da;
    border-color: #f5c6cb;
  }
  
  .legend-color.green {
    background-color: #a5d6a7;
    border-color: #c3e6cb;
  }

  /* Custom checkbox label styling */
  .v-dialog .v-label {
    font-size: 13px !important;
    font-weight: normal !important;
    color: #555 !important;
  }

  .v-dialog .v-input--selection-controls__input {
    margin-right: 8px !important;
  }

  .v-dialog .v-input--selection-controls .v-input__slot {
    margin-bottom: 0 !important;
  }

  /* Custom text field and select label styling */
  .v-dialog .v-text-field .v-label,
  .v-dialog .v-select .v-label {
    font-size: 13px !important;
    font-weight: normal !important;
    color: #666 !important;
  }

  /* Custom input field styling */
  .v-dialog .v-text-field--outlined .v-input__control .v-input__slot {
    min-height: 36px !important;
  }

  .v-dialog .v-select--outlined .v-input__control .v-input__slot {
    min-height: 36px !important;
  }

  /* Equal height columns */
  .v-dialog .v-row {
    align-items: stretch;
  }

  .v-dialog .v-col .v-card {
    height: 100%;
    display: flex;
    flex-direction: column;
  }

  .v-dialog .v-col .v-card .v-card-text {
    flex: 1;
  }

</style>

<body class="layout-top-nav">

  <script>
    var CI = {
      'site_url': '<?php echo site_url(); ?>',
      'base_url': '<?php echo base_url(); ?>',
      'user_info': <?php echo json_encode($user_info); ?>
    };
  </script>

  <div id="app" data-app >    
    <v-app >

    <div class="wrapper">


      <!-- Options Dialog -->
      <v-dialog v-model="showOptionsDialog" max-width="800px" persistent>
        <v-card>
          <v-card-title style="font-size: 16px; ">
            <v-icon left size="small">mdi-cog</v-icon>
            {{$t('diff_options')}}
            <v-spacer></v-spacer>
            <v-btn icon @click="showOptionsDialog = false">
              <v-icon>mdi-close</v-icon>
            </v-btn>
          </v-card-title>
          
          <v-card-text>
            <v-row>
              <!-- Differ Options -->
              <v-col cols="12" md="6" style="border-right: 1px solid #e0e0e0; padding-right: 20px;">
                <div>
                  <div class="subtitle-2" style="font-size: 14px; font-weight: bold; margin-bottom: 12px; display: flex; align-items: center;">
                    <v-icon left color="primary" size="small">mdi-compare-horizontal</v-icon>
                    {{$t('differ_options')}}
                  </div>
                  <div style="padding: 0;">
                    <v-checkbox
                      v-model="diffOptions.showModifications"
                      :label="$t('show_modifications')"
                      hide-details
                      dense
                      class="mb-1"
                    ></v-checkbox>
                    
                    <v-checkbox
                      v-model="diffOptions.ignoreCase"
                      :label="$t('ignore_case')"
                      hide-details
                      dense
                      class="mb-1"
                    ></v-checkbox>
                    
                    <v-checkbox
                      v-model="diffOptions.ignoreCaseForKey"
                      :label="$t('ignore_case_for_keys')"
                      hide-details
                      dense
                      class="mb-1"
                    ></v-checkbox>
                    
                    <div class="mb-2">
                      <div class="text-caption mb-0 mt-3" >{{$t('array_diff_method')}}:</div>
                      <v-radio-group class="mt-0 pt-0" v-model="diffOptions.arrayDiffMethod" hide-details dense>
                        <v-radio
                          value="lcs"
                          :label="$t('lcs_longest_common_subsequence')"
                          dense
                          class="mb-1"
                        ></v-radio>
                        <v-radio
                          value="unorder-normal"
                          :label="$t('unorder_normal')"
                          dense
                          class="mb-1"
                        ></v-radio>
                        <v-radio
                          value="unorder-array"
                          :label="$t('unorder_array')"
                          dense
                          class="mb-1"
                        ></v-radio>
                      </v-radio-group>
                    </div>
                  </div>
                </div>
              </v-col>
              
              <!-- Viewer Options -->
              <v-col cols="12" md="6" style="padding-left: 20px;">
                <div>
                  <div class="subtitle-2" style="font-size: 14px; font-weight: bold; margin-bottom: 12px; display: flex; align-items: center;">
                    <v-icon left color="success" size="small">mdi-eye</v-icon>
                    {{$t('viewer_options')}}
                  </div>
                  <div style="padding: 0;">
                    <v-checkbox
                      v-model="diffOptions.lineNumbers"
                      :label="$t('show_line_numbers')"
                      hide-details
                      dense
                      class="mb-1"
                    ></v-checkbox>
                    
                    <v-checkbox
                      v-model="diffOptions.highlightInlineDiff"
                      :label="$t('highlight_inline_diff')"
                      hide-details
                      dense
                      class="mb-1"
                    ></v-checkbox>
                    
                    <v-checkbox
                      v-model="diffOptions.hideUnchangedLines"
                      :label="$t('hide_unchanged_lines')"
                      hide-details
                      dense
                      class="mb-1"
                    ></v-checkbox>
                    
                    <v-checkbox
                      v-model="diffOptions.syntaxHighlight"
                      :label="$t('syntax_highlighting')"
                      hide-details
                      dense
                      class="mb-1"
                    ></v-checkbox>
                    
                    <v-checkbox
                      v-model="diffOptions.virtual"
                      :label="$t('virtual_scrolling')"
                      hide-details
                      dense
                      class="mb-1"
                    ></v-checkbox>
                  </div>
                </div>
              </v-col>
            </v-row>
          </v-card-text>
          
          <v-card-actions>
            <v-btn text small @click="resetToDefaults">
              <v-icon left size="small">mdi-undo</v-icon>
              {{$t('reset_to_defaults')}}
            </v-btn>
            <v-btn text small @click="showOptionsDialog = false">
              {{$t('cancel')}}
            </v-btn>
            <v-spacer></v-spacer>
            <v-btn color="primary" small @click="applyOptionsAndClose">
              <v-icon left size="small">mdi-check</v-icon>
              {{$t('apply_and_compare')}}
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Project Selection Dialog -->
      <v-dialog v-model="showProjectSelectionDialog" max-width="600px" persistent>
        <v-card>
          <v-card-title style="font-size: 16px;">
            <v-icon left size="small">mdi-magnify</v-icon>
            {{$t('select_projects_to_compare')}}
            <v-spacer></v-spacer>
            <v-btn icon @click="showProjectSelectionDialog = false">
              <v-icon>mdi-close</v-icon>
            </v-btn>
          </v-card-title>
          
          <v-card-text>
            <!-- Project 1 Input -->
            <v-row class="mb-3">
              <v-col cols="12">
                <!-- label -->
                <v-text-field
                  v-model="project1Id"
                  label=""
                  prepend-inner-icon="mdi-1"
                  dense
                  outlined
                  clearable
                  :placeholder="$t('enter_project_id_placeholder')"
                ></v-text-field>
              </v-col>
            </v-row>
            
            <!-- Project 2 Input -->
            <v-row class="mb-3">
              <v-col cols="12">
                <v-text-field
                  v-model="project2Id"
                  label=""
                  prepend-inner-icon="mdi-2"
                  dense
                  outlined
                  clearable
                  :placeholder="$t('enter_project_id_placeholder')"
                ></v-text-field>
              </v-col>
            </v-row>
            
            <!-- Validation Errors -->
            <div v-if="validationErrors.sameId || validationErrors.differentTypes" class="mt-3">
              <v-alert type="error" dense style="background-color:red;color:white;">
                <div v-if="validationErrors.sameId">{{ validationErrors.sameId }}</div>
                <div v-if="validationErrors.differentTypes">{{ validationErrors.differentTypes }}</div>
              </v-alert>
            </div>
            
            <!-- Loading indicator -->
            <div v-if="isValidating" class="text-center mt-3">
              <v-progress-circular indeterminate size="20"></v-progress-circular>
              <div class="text-caption mt-1">{{$t('validating_projects')}}</div>
            </div>
          </v-card-text>
          
          <v-card-actions style="padding-bottom: 16px; padding-right: 16px;margin-right:10px;">
            <v-btn text small @click="showProjectSelectionDialog = false">
              {{$t('cancel')}}
            </v-btn>
            <v-spacer></v-spacer>
            <v-btn 
              color="primary" 
              small 
              @click="compareSelectedProjects"
              :disabled="!project1Id || !project2Id || validationErrors.sameId || validationErrors.differentTypes || isValidating"
            >
              <v-icon left size="small">mdi-compare</v-icon>
              {{$t('compare_projects')}}
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <div class="content-wrapper" v-cloak>
        <section class="content">

          <div class="container-fluid px-4 pt-3" >

            <div class="row">

              <!-- Main Content -->
              <div class="col-md-12">
                
                <!-- Page Header -->
                <v-card>
                  <v-card-title>
                    <v-icon left>mdi-compare</v-icon>
                    {{$t('project_comparison')}}
                    <v-spacer></v-spacer>
                    <v-btn color="primary" outlined @click="openProjectSelectionDialog" class="mr-2">
                      <v-icon left>mdi-magnify</v-icon>
                      {{$t('select_projects')}}
                    </v-btn>
                    <v-btn icon @click="openOptionsDialog" :title="$t('diff_options')">
                      <v-icon>mdi-cog</v-icon>
                    </v-btn>
                  </v-card-title>
                </v-card>

                <!-- Loading State -->
                <div v-if="is_loading" class="text-center pa-8">
                  <v-progress-circular indeterminate color="primary" size="64"></v-progress-circular>
                  <div class="text-h6 mt-4">{{$t('loading_projects')}}</div>
                </div>
                
                <!-- Error State -->
                <div v-else-if="error_message" class="pa-4">
                  <v-alert type="error">
                    {{ error_message }}
                  </v-alert>
                </div>
                
                <!-- Missing Parameters -->
                <div v-else-if="!project1_id || !project2_id" class="pa-4">
                  <v-alert type="warning">
                    <div class="text-h6 mb-2">{{$t('missing_project_parameters')}}</div>
                    <div>{{$t('provide_both_project_parameters')}}</div>
                    <div class="mt-2">
                      <strong>{{$t('example')}}:</strong> /editor/compare?project1=123&project2=456
                    </div>
                  </v-alert>
                </div>
                
                <!-- Validation Errors on Page Load -->
                <div v-else-if="validationErrors.sameId || validationErrors.differentTypes" class="pa-4">
                  <v-alert type="error">
                    <div class="text-h6 mb-2">{{$t('project_comparison_error')}}</div>
                    <div v-if="validationErrors.sameId">{{ validationErrors.sameId }}</div>
                    <div v-if="validationErrors.differentTypes">{{ validationErrors.differentTypes }}</div>
                    <div class="mt-3">
                      <v-btn color="primary" @click="openProjectSelectionDialog">
                        <v-icon left>mdi-magnify</v-icon>
                        {{$t('select_different_projects')}}
                      </v-btn>
                    </div>
                  </v-alert>
                </div>
                
                <!-- Projects Not Found -->
                <div v-else-if="!project1 || !project2" class="pa-4">
                  <v-alert type="warning">
                    <div class="text-h6 mb-2">{{$t('projects_not_found')}}</div>
                    <div v-if="!project1">{{$t('project_1_not_found', {id: project1_id})}}</div>
                    <div v-if="!project2">{{$t('project_2_not_found', {id: project2_id})}}</div>
                  </v-alert>
                </div>
                
                <!-- Auto-comparison in progress -->
                <div v-else-if="is_comparing" class="text-center pa-8">
                  <v-progress-circular indeterminate color="primary" size="64"></v-progress-circular>
                  <div class="text-h6 mt-4">{{$t('comparing_projects')}}</div>
                  <div class="text-body-2 mt-2">{{ project1.title }} vs {{ project2.title }}</div>
                </div>
                    
                    <!-- Comparison Results -->
                    <div v-if="diff_html" class="mt-4">
                      <v-divider class="mb-4"></v-divider>
                      
                      <!-- Project Info -->
                      <v-row class="mb-4">
                        <v-col cols="12" md="6">
                          <v-card >
                            <v-card-title>                              
                              {{ project1.title }}
                            </v-card-title>
                            <v-card-text>
                              <div><strong>IDNO:</strong> {{ project1.idno }}</div>
                              <div><strong>Type:</strong> {{ project1.type }}</div>
                            </v-card-text>
                          </v-card>
                        </v-col>
                        
                        <v-col cols="12" md="6">
                          <v-card >
                            <v-card-title>
                              {{ project2.title }}
                            </v-card-title>
                            <v-card-text>
                              <div><strong>ID:</strong> {{ project2.idno }}</div>
                              <div><strong>Type:</strong> {{ project2.type }}</div>
                            </v-card-text>
                          </v-card>
                        </v-col>
                      </v-row>
                      
                      <!-- Diff Legend -->
                      <v-card class="mb-4">
                        <v-card-text class="pt-3">
                          <div class="d-flex align-center justify-space-between">
                            <div class="d-flex align-center text-caption">
                              <div class="d-flex align-center mr-4">
                                <div class="legend-color yellow mr-2"></div>
                                <span> {{$t('modifications')}}</span>
                              </div>
                              <div class="d-flex align-center mr-4">
                                <div class="legend-color red mr-2"></div>
                                <span>{{$t('deletions')}}</span>
                              </div>
                              <div class="d-flex align-center">
                                <div class="legend-color green mr-2"></div>
                                <span>{{$t('additions')}}</span>
                              </div>
                            </div>
                            <div class="d-flex align-center">
                              <v-switch
                                v-model="diffOptions.hideUnchangedLines"
                                :label="$t('hide_unchanged_lines')"
                                hide-details
                                dense
                                @change="renderDiff"
                                class="mt-0"
                              ></v-switch>
                            </div>
                          </div>
                        </v-card-text>
                      </v-card>

                      <!-- Comparison Results -->
                      <v-card >
                        <v-card-text>
                          <div class="json-diff-container" v-if="!diff_html"></div>
                          <div v-html="diff_html" v-if="diff_html"></div>
                        </v-card-text>
                      </v-card>
                      
                    </div>
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

  <!-- Vue.js and dependencies -->
  <script src="<?php echo base_url();?>vue-app/assets/vue.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/vuetify.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/vue-router.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/vuex.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/axios.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/vue-i18n.min.js"></script>
  
  <!-- JSON Diff Library -->
  <script src="<?php echo base_url();?>vue-app/assets/json-diff-kit/json-diff-kit.umd.min.js"></script>
  <link href="<?php echo base_url();?>vue-app/assets/json-diff-kit/viewer.css" rel="stylesheet">

  <script>
    // Vue i18n setup
    const translation_messages = {
      default: <?php echo json_encode($translations, JSON_HEX_APOS); ?>
    };
    
    const i18n = new VueI18n({
      locale: 'default',
      fallbackLocale: 'default',
      messages: translation_messages
    });


    // Vuetify setup
    const vuetify = new Vuetify({
      theme: {
        themes: {
          light: {
            primary: '#1976D2',
            secondary: '#424242',
            accent: '#82B1FF',
            error: '#FF5252',
            info: '#2196F3',
            success: '#4CAF50',
            warning: '#FFC107'
          }
        }
      }
    });

    // Vue app
    const vue_app = new Vue({
      el: '#app',
      i18n,
      vuetify,
      data: {
        // Get project IDs from URL query string
        project1_id: new URLSearchParams(window.location.search).get('project1'),
        project2_id: new URLSearchParams(window.location.search).get('project2'),
        is_loading: false,
        is_comparing: false,
        error_message: null,
        project1: null,
        project2: null,
        project1_json: null,
        project2_json: null,
        diff_html: null,
        showOptionsDialog: false,
        showProjectSelectionDialog: false,
        
        // Project Selection
        project1Id: '',
        project2Id: '',
        project1Info: null,
        project2Info: null,
        validationErrors: {
          sameId: '',
          differentTypes: ''
        },
        isValidating: false,
        
        // Diff Options
        diffOptions: {
          // Differ Options
          detectCircular: true,
          maxDepth: null,
          showModifications: true,
          arrayDiffMethod: 'lcs',
          ignoreCase: false,
          ignoreCaseForKey: false,
          recursiveEqual: true,
          
          // Viewer Options
          indent: 2,
          lineNumbers: true,
          highlightInlineDiff: true,
          hideUnchangedLines: false,
          syntaxHighlight: false,
          virtual: false,
          inlineDiffOptions: {
            mode: 'word',
            wordSeparator: ' '
          }
        }
      },
      mounted() {
        if (this.project1_id && this.project2_id) {
          this.loadProjects();
        }
      },
      watch: {
        project1Id() {
          if (this.project1Id && this.project2Id) {
            this.validateProjects();
          } else {
            this.validationErrors.sameId = '';
            this.validationErrors.differentTypes = '';
          }
        },
        project2Id() {
          if (this.project1Id && this.project2Id) {
            this.validateProjects();
          } else {
            this.validationErrors.sameId = '';
            this.validationErrors.differentTypes = '';
          }
        }
      },
      methods: {
        loadProjects() {
          this.is_loading = true;
          this.error_message = null;
          const vm = this;
          
          // First, validate projects before loading metadata
          this.validateProjectsBeforeLoading()
            .then(() => {
              // If validation passes, proceed with loading metadata
              return vm.loadProjectMetadata();
            })
            .catch(error => {
              console.error('Validation failed:', error);
              vm.error_message = error.message || vm.$t('project_validation_failed');
              vm.is_loading = false;
            });
        },

        validateProjectsBeforeLoading() {
          return new Promise((resolve, reject) => {            
            // Fetch basic info for both projects to validate types
            Promise.all([
              axios.get(CI.site_url + '/api/editor/basic_info/' + this.project1_id),
              axios.get(CI.site_url + '/api/editor/basic_info/' + this.project2_id)
            ])
            .then(([project1Response, project2Response]) => {
              const project1Info = project1Response.data?.project;
              const project2Info = project2Response.data?.project;

              // Check if projects exist
              if (!project1Info) {
                reject(new Error(`Project 1 (ID: ${this.project1_id}) not found or you don't have access to it.`));
                return;
              }
              
              if (!project2Info) {
                reject(new Error(`Project 2 (ID: ${this.project2_id}) not found or you don't have access to it.`));
                return;
              }

              // Check if project IDs are the same
              if (project1Info.id === project2Info.id) {
                 reject(new Error('Cannot compare a project with itself. Please select different projects.'));
                return;
              }

              // Check if project types match
              if (project1Info.type !== project2Info.type) {
                reject(new Error(`Cannot compare projects of different types. Project 1 is "${project1Info.type}" and Project 2 is "${project2Info.type}".`));
                return;
              }

              // Store basic project info for later use
              this.project1 = project1Info;
              this.project2 = project2Info;

              resolve();
            })
            .catch(error => {
              console.error('Error validating projects:', error);
              if (error.response && error.response.status === 404) {
                reject(new Error('One or both projects not found or you don\'t have access to them.'));
              } else if (error.response && error.response.status === 403) {
                reject(new Error('You don\'t have permission to access one or both projects.'));
              } else {
                reject(new Error('Error validating projects: ' + (error.message || 'Unknown error')));
              }
            });
          });
        },

        loadProjectMetadata() {
          const vm = this;
          
          // Now load the full metadata for both projects
          const fetchProjectMetadata = (projectId) => {
            return axios.get(CI.site_url + '/api/editor/json/' + projectId)
              .then(response => {
                return response.data;
              });
          };
          
          return Promise.all([
            fetchProjectMetadata(this.project1_id),
            fetchProjectMetadata(this.project2_id)
          ])
          .then(([project1Json, project2Json]) => {
            vm.project1_json = project1Json;
            vm.project2_json = project2Json;
            vm.is_loading = false;
            
            // Auto-compare after metadata is loaded
            vm.$nextTick(() => {
              vm.renderDiff();
            });
          })
          .catch(error => {
            console.error('Error loading project metadata:', error);
            vm.error_message = vm.getErrorMessage(error);
            vm.is_loading = false;
          });
        },        
        renderDiff() {
          if (!this.project1_json || !this.project2_json) return;
          
          try {
            if (typeof JsonDiffKit === 'undefined') {
              console.error('JsonDiffKit library not loaded');
              this.diff_html = '<div class="error">JSON diff library not loaded. Please refresh the page.</div>';
              return;
            }
            
            // temporary container for React rendering
            const tempContainer = document.createElement('div');
            
            // Create differ instance with options
            const differOptions = {
              detectCircular: this.diffOptions.detectCircular,
              maxDepth: this.diffOptions.maxDepth || undefined,
              showModifications: this.diffOptions.showModifications,
              arrayDiffMethod: this.diffOptions.arrayDiffMethod,
              ignoreCase: this.diffOptions.ignoreCase,
              ignoreCaseForKey: this.diffOptions.ignoreCaseForKey,
              recursiveEqual: this.diffOptions.recursiveEqual
            };
            const differ = new JsonDiffKit.Differ(differOptions);
            const diff = differ.diff(this.project1_json, this.project2_json);

            /*
            all options available:
            const d = new Differ({
                    detectCircular: true,
                    maxDepth: null,
                    showModifications: true,
                    arrayDiffMethod: 'lcs',
                    ignoreCase: true,
                    ignoreCaseForKey: true,
                    recursiveEqual: true
                    });
                    const diff = d.diff(before, after);

                    const viewerProps = {
                    indent: 4,
                    lineNumbers: true,
                    highlightInlineDiff: true,
                    inlineDiffOptions: {
                        mode: 'word',
                        wordSeparator: ' '
                    },
                    hideUnchangedLines: true,
                    syntaxHighlight: false,
                    virtual: false
                    };
            */
            
            // Create viewer instance with options
            const viewerOptions = {
              diff: diff,
              indent: this.diffOptions.indent || 2,
              lineNumbers: this.diffOptions.lineNumbers,
              highlightInlineDiff: this.diffOptions.highlightInlineDiff,
              hideUnchangedLines: this.diffOptions.hideUnchangedLines,
              inlineDiffOptions: {
                mode: this.diffOptions.inlineDiffOptions.mode || 'word',
                wordSeparator: this.diffOptions.inlineDiffOptions.wordSeparator || ' '
              },
              syntaxHighlight: this.diffOptions.syntaxHighlight,
              virtual: this.diffOptions.virtual
            };
            const viewer = new JsonDiffKit.Viewer(viewerOptions);
            
            //render directly into the Vue template
            const diffContainer = document.querySelector('.json-diff-container');
            if (diffContainer) {
              const root = JsonDiffKit.ReactDOM.createRoot(diffContainer);
              root.render(JsonDiffKit.React.createElement(viewer.render));
            } else {
              // Fallback: render to temp container and extract HTML
              const root = JsonDiffKit.ReactDOM.createRoot(tempContainer);
              root.render(JsonDiffKit.React.createElement(viewer.render));
              
              setTimeout(() => {
                this.diff_html = tempContainer.innerHTML;
              }, 100);
            }
            
          } catch (error) {
            console.error('Error creating visual diff:', error);
            this.diff_html = '<div class="error">Error creating diff: ' + error.message + '</div>';
          }
        },
        
        
        canCompare() {
          return this.project1 && this.project2 && this.project1.id !== this.project2.id;
        },
        
        getErrorMessage(error) {
          if (error.response && error.response.data) {
            if (error.response.data.message) {
              return error.response.data.message;
            }
            if (error.response.data.error) {
              return error.response.data.error;
            }
            return JSON.stringify(error.response.data);
          }
          return error.message || 'An error occurred';
        },
        
        openOptionsDialog() {
          this.showOptionsDialog = true;
        },
        
        applyOptionsAndCompare() {
          this.renderDiff();
        },
        
        applyOptionsAndClose() {
          this.renderDiff();
          this.showOptionsDialog = false;
        },
        
        resetToDefaults() {
          this.diffOptions = {
            // Differ Options
            showModifications: true,
            arrayDiffMethod: 'lcs',
            ignoreCase: false,
            ignoreCaseForKey: false,
            maxDepth: null,
            
          // Viewer Options
          lineNumbers: true,
          highlightInlineDiff: true,
          hideUnchangedLines: false,
          syntaxHighlight: false,
          virtual: true
          };
        },
        
        // Project Selection Methods
        openProjectSelectionDialog() {
          this.showProjectSelectionDialog = true;
          // Pre-populate with current project IDs if they exist
          if (this.project1_id) {
            this.project1Id = this.project1_id;
          }
          if (this.project2_id) {
            this.project2Id = this.project2_id;
          }
          // Validate if both IDs are already filled
          if (this.project1Id && this.project2Id) {
            this.validateProjects();
          }
        },
        
        validateProjects() {
          // Clear previous errors
          this.validationErrors.sameId = '';
          this.validationErrors.differentTypes = '';
          
          // Check if both IDs are provided
          if (!this.project1Id || !this.project2Id) {
            return;
          }
          
          // Check if IDs are the same
          if (this.project1Id === this.project2Id) {
            this.validationErrors.sameId = 'Cannot compare a project with itself. Please enter different project IDs.';
            return;
          }
          
          // Fetch project information for type validation
          this.isValidating = true;
          const vm = this;
          
          Promise.all([
            this.fetchProjectInfo(this.project1Id),
            this.fetchProjectInfo(this.project2Id)
          ])
          .then(([project1Info, project2Info]) => {
            vm.project1Info = project1Info;
            vm.project2Info = project2Info;
            
            // Check if both projects exist
            if (!project1Info) {
              vm.validationErrors.differentTypes = 'Project 1 not found.';
            } else if (!project2Info) {
              vm.validationErrors.differentTypes = 'Project 2 not found.';
            } else if (project1Info.type !== project2Info.type) {
              vm.validationErrors.differentTypes = `Cannot compare projects of different types. Project 1 is "${project1Info.type}" and Project 2 is "${project2Info.type}".`;
            }
            
            vm.isValidating = false;
          })
          .catch(error => {
            console.error('Error validating projects:', error);
            vm.validationErrors.differentTypes = 'Error validating projects. Please check the project IDs.';
            vm.isValidating = false;
          });
        },
        
        fetchProjectInfo(projectId) {
          const url = CI.site_url + '/api/editor/basic_info/' + projectId;
          
          return axios.get(url)
            .then(response => {
              if (response.data && response.data.project) {
                return response.data.project;
              }
              return null;
            })
            .catch(error => {
              console.error('Error fetching project info:', error);
              return null;
            });
        },
        
        compareSelectedProjects() {
          if (!this.project1Id || !this.project2Id || this.validationErrors.sameId || this.validationErrors.differentTypes || this.isValidating) {
            return;
          }
          
          // Update URL with new project IDs
          const newUrl = new URL(window.location);
          newUrl.searchParams.set('project1', this.project1Id);
          newUrl.searchParams.set('project2', this.project2Id);
          
          // Navigate to new URL
          window.location.href = newUrl.toString();
        }
      }
    });
  </script>

</body>
</html>
