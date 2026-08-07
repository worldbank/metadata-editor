<!DOCTYPE html>
<html>

<head>
  <link rel="icon" href="<?php echo base_url();?>favicon.ico">
  <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
  <link href="<?php echo base_url();?>vue-app/assets/mdi.min.css" rel="stylesheet">
  <link href="<?php echo base_url();?>vue-app/assets/vuetify.min.css" rel="stylesheet">
  <link href="<?php echo base_url()?>themes/nada52/fontawesome/css/all.css" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo base_url(); ?>themes/nada52/css/bootstrap.min.css">
  <link href="<?php echo base_url();?>vue-app/assets/styles.css" rel="stylesheet">

  <script src="<?php echo base_url();?>vue-app/assets/jquery.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/bootstrap.bundle.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/moment-with-locales.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/vue-i18n.min.js"></script>

  <script src="<?php echo base_url();?>vue-app/assets/vue.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/vue-router.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/vuex.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/axios.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/vuetify.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/session_channel.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/global-session-handler.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/global-login-plugin.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/lodash.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/vue-deepset.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/ajv.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/deepdash.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/vue-json-pretty.min.js"></script>
  <link href="<?php echo base_url();?>vue-app/assets/vue-json-pretty.min.css" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">
</head>

<style>
  <?php //echo $this->load->view('metadata_editor/styles.css', null, true); ?>

  .navigation-tabs .v-tabs-bar{
    background-color: transparent!important;    
  }

.schema-icon-avatar {
  width: 20px;
  height: 20px;
  border-radius: 8px;
  background-color: transparent;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.schema-icon-avatar img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.schema-icon-placeholder {
  font-weight: 600;
  color: #5c6bc0;
  font-size: 14px;
  text-transform: uppercase;
}

.import-template-dialog .import-template-field-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: rgba(0, 0, 0, 0.87);
  margin-bottom: 4px;
}

.import-template-dialog .v-card__text {
  padding-top: 16px !important;
}

.import-template-dialog .import-template-summary {
  background-color: #f5f7fa;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 4px;
  padding: 12px 14px;
  font-size: 0.8125rem;
  line-height: 1.5;
  color: rgba(0, 0, 0, 0.87);
}

.import-template-dialog .import-template-summary strong {
  font-weight: 600;
  color: rgba(0, 0, 0, 0.6);
}

.import-template-dialog .import-template-uid-options {
  margin-top: 8px;
  padding: 0;
}

.import-template-dialog .import-template-uid-warning {
  display: flex;
  align-items: flex-start;
  margin-bottom: 8px;
}

.import-template-dialog .import-template-uid-warning .v-icon {
  color: #ef6c00 !important;
  flex-shrink: 0;
  margin-right: 8px;
  margin-top: 2px;
}

.import-template-dialog .import-template-uid-warning-text {
  color: #e65100;
  font-size: 0.875rem;
  line-height: 1.45;
  font-weight: 500;
}

.import-template-dialog .import-template-uid-checkbox {
  margin-top: 0;
  padding-top: 0;
}

.import-template-dialog .import-template-uid-checkbox .v-label {
  font-size: 0.875rem;
  color: rgba(0, 0, 0, 0.87) !important;
}

.import-template-dialog .import-template-error-alert .v-alert__content {
  font-size: 0.875rem;
}
</style>

<body class="layout-top-nav">
    <?php
      $user=$this->session->userdata('username');
      $this->load->library('Editor_acl');
      $can_template_admin=false;
      $can_import_template=false;
      $can_duplicate_template=false;
      try{
        $can_template_admin=$this->editor_acl->has_access('template_manager','admin');
      }catch(Exception $e){
        $can_template_admin=false;
      }
      try{
        $can_import_template=$this->editor_acl->has_access('template_manager','edit');
      }catch(Exception $e){
        $can_import_template=false;
      }
      try{
        $can_duplicate_template=$this->editor_acl->has_access('template_manager','duplicate');
      }catch(Exception $e){
        $can_duplicate_template=false;
      }
      
      $user_info=array_merge(array(
        'username'=> $user,
        'user_id'=> (int)$this->session->userdata('user_id'),
        'is_logged_in'=> !empty($user),
        'is_admin'=> $this->ion_auth->is_admin(),
        'can_access_site_admin'=> $this->ion_auth->can_access_site_admin(),
        'can_access_admin_dashboard'=> $this->ion_auth->can_access_admin_dashboard(),
        'can_template_admin'=> $can_template_admin,
        'can_import_template'=> $can_import_template,
        'can_duplicate_template'=> $can_duplicate_template,
      ), registry_acl_user_info_flags());
      
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

    <div class="wrapper">

      <?php //echo $this->load->view('editor_common/global-header', null, true); ?>
      <vue-global-site-header></vue-global-site-header>
      <v-login v-model="login_dialog"></v-login>

      <div class="content-wrapper">
        <section class="content">
          
          <div class="container-fluid" >

            <div class="row">

              <div class="sidebar mt-5 mr-5" style="width: 300px; ">
                <!-- sidebar -->

                  <v-card rounded class="v-card--app-filter pa-3 mt-5 ml-2 elevation-3" style="position:sticky; top:50px;">
                    <!-- header -->
                    <section class="d-flex justify-space-between align-center py-3 px-5 v-card--app-filter__title">
                        {{$t('Types')}}
                    </section>

                    <!-- content -->
                    <section>
                        <v-list flat>
                            <v-list-item-group color="primary">
                                <v-list-item @click="sidebar_selected=''">
                                    <v-list-item-icon>
                                        <v-icon>mdi-filter</v-icon>
                                    </v-list-item-icon>
                                    <v-list-item-content>
                                        <v-list-item-title>{{$t('All')}}</v-list-item-title>
                                    </v-list-item-content>
                                </v-list-item>
                                <v-list-item
                                    v-for="schema in sidebar_data_types"
                                    :key="schema.uid"
                                    :class="{'v-list-item-active' : sidebar_selected === schema.uid}"
                                    @click="sidebar_selected = schema.uid"
                                >
                                    <div class="schema-icon-avatar mr-3">
                                        <img v-if="getSchemaIconSrc(schema)" :src="getSchemaIconSrc(schema)" :alt="schema.label">
                                        <span v-else class="schema-icon-placeholder">{{ getSchemaInitial(schema) }}</span>
                                    </div>
                                    <v-list-item-content>
                                        <v-list-item-title>{{ getSchemaLabel(schema) }}</v-list-item-title>
                                    </v-list-item-content>
                                </v-list-item>
                            </v-list-item-group>
                        </v-list>
                    </section>
                  </v-card>

                 <!-- end sidebar -->
              </div>

              <div class="projects col" style="overflow:auto;" >

                <div class="mt-5 mb-5">

                    <main-navigation-tabs active-tab="templates" v-model="nav_tabs_model"></main-navigation-tabs>

                  <div class="d-flex">
                    <div class="flex-grow-1 flex-shrink-0 mr-auto">
                      <h3 class="mt-5">{{$t('template_manager')}}</h3>
                    </div>

                    <div class="justify-content-end">
                      <v-btn class="primary mr-2" @click="showImportTemplateDialog" v-if="list_view === 'active' && canImportTemplate">{{$t('import_template')}}</v-btn>
                    </div>

                  </div>

                  <v-tabs v-model="list_view" class="mt-3 mb-4">
                    <v-tab href="#active">{{$t('active')}}</v-tab>
                    <v-tab href="#deleted">{{$t('deleted')}}</v-tab>
                  </v-tabs>
                  
                </div>
               
                <div v-show="list_view === 'active'">
                  <div v-if="!templates"> {{$t('no_templates_found')}}</div>

                  <div
                    v-for="schema in schemaGroups"
                    :key="schema.uid"
                    class="mb-5"
                    v-if="sidebar_selected === '' || sidebar_selected === schema.uid"
                  >
                    <v-data-table                      
                      :headers="[
                        { text: $t('type'), value: 'template_type' },
                        { text: $t('default'), value: 'default'},
                        { text: $t('title'), value: 'name' },
                        { text: $t('language'), value: 'lang' },
                        { text: $t('version'), value: 'version' },
                         { text: $t('owner'), value: 'owner_username' },
                        { text: $t('changed_by'), value: 'changed_by_username' },
                        { text: $t('changed_at'), value: 'changed' },
                        { text: '', value: 'actions', sortable: false }
                      ]"
                      :items="getTemplatesForSchema(schema)"
                      class="elevation-7 mb-5 pt-3"
                      :disable-pagination="true"
                      :items-per-page="100"
                      :hide-default-footer="true"
                    >
                      <template v-slot:item.template_type="{ item }">
                        <span>{{ templateTypeLabel(item) }}</span>
                      </template>
                      <template v-slot:top>
                            <div class="d-flex pl-6 pb-4 align-center">                                
                          <div class="schema-icon-avatar mr-3">
                            <img v-if="getSchemaIconSrc(schema)" :src="getSchemaIconSrc(schema)" :alt="schema.label">
                            <span class="schema-icon-placeholder" v-else>{{ getSchemaInitial(schema) }}</span>
                          </div>
                          <div class="v-data-table--title font-weight-bold">
                            {{ getSchemaLabel(schema) }}
                          </div>
                            </div>
                        </template>                                                   
                      
                      <template v-slot:item.template_type="{ item }">
                        <span>{{ templateTypeLabel(item) }}</span>
                      </template>
                      <template v-slot:item.default="{ item }">
                        <span class="btn btn-sm btn-link" @click="setDefaultTemplate(item.data_type,item.uid)">
                          <v-icon v-if="item.default">mdi-radiobox-marked</v-icon>
                          <v-icon v-else>mdi-radiobox-blank</v-icon>
                        </span>                        
                      </template>
                      <template v-slot:item.actions="{ item }">
                        <v-icon @click="showMenu($event, item.uid, isReadOnlyTemplate(item), item.data_type)">mdi-dots-vertical</v-icon>
                      </template>
                      <template v-slot:item.changed="{ item }">
                        <span  v-if="item.changed">{{momentDate(item.changed)}}</span>
                      </template>
                      <template v-slot:item.owner_username="{ item }">
                        <span :title="item.owner_email" v-if="item.owner_username">{{item.owner_username}}</span>
                      </template>
                      <template v-slot:item.changed_by_username="{ item }">
                        <span :title="item.changed_by_email" v-if="item.changed_by_username">{{item.changed_by_username}}</span>
                      </template>
                      <template v-slot:item.name="{ item }">
                        <a :title="'UID: ' + item.uid"  target="_blank" :href="getTemplateEditLink(item)" @xclick="editTemplate(item.uid)">{{item.name}}</a>
                      </template>
                    </v-data-table>

                  </div>

                </div>

                <div v-show="list_view === 'deleted'">
                  <div v-if="!deleted_templates || !deleted_templates.custom || !deleted_templates.custom.length">{{$t('no_deleted_templates_found')}}</div>

                  <div
                    v-for="schema in schemaGroups"
                    :key="'deleted-' + schema.uid"
                    class="mb-5"
                    v-if="sidebar_selected === '' || sidebar_selected === schema.uid"
                  >
                    <v-data-table
                      :headers="deletedTableHeaders"
                      :items="getDeletedTemplatesForSchema(schema)"
                      class="elevation-7 mb-5 pt-3"
                      :disable-pagination="true"
                      :items-per-page="100"
                      :hide-default-footer="true"
                      v-if="getDeletedTemplatesForSchema(schema).length"
                    >
                      <template v-slot:top>
                        <div class="d-flex pl-6 pb-4 align-center">
                          <div class="schema-icon-avatar mr-3">
                            <img v-if="getSchemaIconSrc(schema)" :src="getSchemaIconSrc(schema)" :alt="schema.label">
                            <span v-else class="schema-icon-placeholder">{{ getSchemaInitial(schema) }}</span>
                          </div>
                          <div class="text-h6">{{ getSchemaLabel(schema) }}</div>
                        </div>
                      </template>
                      <template v-slot:item.deleted_at="{ item }">
                        <span v-if="item.deleted_at">{{ momentDate(item.deleted_at) }}</span>
                      </template>
                      <template v-slot:item.actions="{ item }">
                        <v-icon @click="showDeletedMenu($event, item)">mdi-dots-vertical</v-icon>
                      </template>
                    </v-data-table>
                  </div>
                </div>

              </div>

            </div>

          </div>
        </section>
      </div>    
    </div>

    <template class="import-template">
      <div class="text-center">
        <v-dialog
          v-model="dialog_import_template"
          width="520"
          :key="dialog_import_template_key"
          :persistent="import_template_loading"
          @click:outside="onImportTemplateDialogOutsideClick"
        >
          <v-card class="import-template-dialog">
            <v-card-title class="text-h6 grey lighten-3 py-3">
              {{$t('import_template')}}
            </v-card-title>

            <v-card-text class="pb-2">
              <div class="mb-3">
                <div class="import-template-field-label">{{$t('select_file')}} (JSON)</div>
                <v-file-input
                  accept=".json,application/json"
                  label=""
                  truncate-length="50"
                  dense
                  outlined
                  hide-details
                  v-model="import_template_file"
                  prepend-icon=""
                  prepend-inner-icon="mdi-file-upload"
                  :disabled="import_template_loading"
                ></v-file-input>
              </div>

              <v-alert
                v-if="import_parse_error"
                type="error"
                dense
                outlined
                text
                color="error"
                icon="mdi-alert-circle-outline"
                class="mb-3 import-template-error-alert"
              >
                {{import_parse_error}}
              </v-alert>

              <div
                v-if="importJSON && !import_parse_error"
                class="import-template-summary mb-3"
              >
                <div v-if="importJSON.name"><strong>{{$t('name')}}:</strong> {{importJSON.name}}</div>
                <div v-if="importJSON.data_type"><strong>{{$t('data_type')}}:</strong> {{importJSON.data_type}}</div>
                <div v-if="importJSON.uid"><strong>{{$t('template_uid')}}:</strong> {{importJSON.uid}}</div>
              </div>

              <div
                v-if="importJSON && importJSON.uid && import_uid_in_use && !import_parse_error"
                class="import-template-uid-options mb-2"
              >
                <div class="import-template-uid-warning">
                  <v-icon small>mdi-alert-circle-outline</v-icon>
                  <span class="import-template-uid-warning-text">{{$t('import_template_uid_in_use_warning')}}</span>
                </div>

                <v-checkbox
                  v-model="import_assign_new_uid"
                  :disabled="import_template_loading"
                  hide-details
                  color="primary"
                  class="import-template-uid-checkbox ml-1"
                  :label="$t('import_template_assign_new_uid_checkbox')"
                ></v-checkbox>
              </div>

              <v-alert
                v-if="import_api_error"
                type="error"
                dense
                outlined
                text
                color="error"
                icon="mdi-alert-circle-outline"
                class="mt-2 import-template-error-alert"
              >
                {{import_api_error}}
              </v-alert>
            </v-card-text>

            <v-divider></v-divider>

            <v-card-actions class="px-4 py-3">
              <v-spacer></v-spacer>
              <v-btn color="grey darken-1" text @click="closeImportTemplateDialog" :disabled="import_template_loading">
                {{$t('cancel')}}
              </v-btn>
              <v-btn color="primary" depressed @click="importTemplate" :loading="import_template_loading" :disabled="!importJSON || import_template_loading || !!import_parse_error">
                {{$t('import')}}
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>
      </div>
    </template>

  </v-app>

    <template>
      <v-menu
        v-model="showTemplateMenu"
        :position-x="menu_x"
        :position-y="menu_y"
        absolute
        offset-y
      >

        <v-list>
          <template v-if="menu_is_deleted">
            <v-list-item v-if="canManageTemplate(menu_active_template_item)">
              <v-list-item-icon>
                <v-icon>mdi-restore</v-icon>
              </v-list-item-icon>
              <v-list-item-title @click="restoreTemplate(menu_active_template_id)"><v-btn text> {{$t('restore')}}</v-btn></v-list-item-title>
            </v-list-item>
            <v-list-item v-if="canManageTemplate(menu_active_template_item)">
              <v-list-item-icon>
                <v-icon>mdi-delete-forever</v-icon>
              </v-list-item-icon>
              <v-list-item-title @click="purgeTemplate(menu_active_template_id)"><v-btn text> {{$t('delete_permanently')}}</v-btn></v-list-item-title>
            </v-list-item>
            <v-list-item>
              <v-list-item-icon>
                <v-icon>mdi-code-json</v-icon>
              </v-list-item-icon>
              <v-list-item-title @click="exportTemplate(menu_active_template_id)"><v-btn text> {{$t('export')}}</v-btn></v-list-item-title>
            </v-list-item>
          </template>
          <template v-else>
          <v-list-item v-if="!isCoreTemplate(menu_active_template_id)">
            <v-list-item-icon>
              <v-icon>mdi-share</v-icon>
            </v-list-item-icon>
            <v-list-item-title @click="shareTemplate(menu_active_template_id)"><v-btn text> {{$t('share')}}</v-btn></v-list-item-title>
          </v-list-item>
          <v-list-item v-if="canDuplicateTemplate">
            <v-list-item-icon>
              <v-icon>mdi-content-duplicate</v-icon>
            </v-list-item-icon>
            <v-list-item-title @click="duplicateTemplate(menu_active_template_id)"><v-btn text> {{$t('duplicate')}}</v-btn></v-list-item-title>
          </v-list-item>
          <v-list-item>
            <v-list-item-icon>
              <v-icon>mdi-code-json</v-icon>
            </v-list-item-icon>
            <v-list-item-title @click="exportTemplate(menu_active_template_id)"><v-btn text> {{$t('export')}}</v-btn></v-list-item-title>
          </v-list-item>
          <template v-if="!menu_active_template_core && canManageTemplate(getTemplateRecord(menu_active_template_id))">
            <v-list-item>
              <v-list-item-icon>
                <v-icon>mdi-delete-outline</v-icon>
              </v-list-item-icon>
              <v-list-item-title @click="deleteTemplate(menu_active_template_id)"><v-btn text> {{$t('delete')}}</v-btn></v-list-item-title>
            </v-list-item>            
          </template>
          <v-list-item>
            <v-list-item-icon>
              <v-icon>mdi-eye-outline</v-icon>
            </v-list-item-icon>
            <v-list-item-title @click="previewTemplate(menu_active_template_id)"><v-btn text> {{$t('preview')}}</v-btn></v-list-item-title>
          </v-list-item>
          <v-list-item>
            <v-list-item-icon>
              <v-icon>mdi-database-eye-outline</v-icon>
            </v-list-item-icon>
            <v-list-item-title @click="previewTableTemplate(menu_active_template_id)"><v-btn text> {{$t('table')}}</v-btn></v-list-item-title>
          </v-list-item>
          <v-list-item>
            <v-list-item-icon>
              <v-icon>mdi-file-pdf-box</v-icon>
            </v-list-item-icon>
            <v-list-item-title @click="pdfTemplate(menu_active_template_id)"><v-btn text> {{$t('pdf')}}</v-btn></v-list-item-title>
          </v-list-item>  
          
          <v-list-item>
            <v-list-item-icon>
                <v-icon>mdi-content-copy</v-icon>
            </v-list-item-icon>
            <v-list-item-title @click="viewTemplateRevisions(menu_active_template_id)"><v-btn text> {{$t('revisions')}}</v-btn></v-list-item-title>        
          </v-list-item>

          <v-list-item>
            <v-list-item-icon>
                <v-icon>mdi-key</v-icon>
            </v-list-item-icon>
            <v-list-item-title @click="updateTemplateUUID(menu_active_template_id)"><v-btn text> {{$t('UUID')}}</v-btn></v-list-item-title>        
          </v-list-item>
          </template>

        </v-list>
      </v-menu>
    </template>

    <vue-template-share :key="menu_active_template_id" 
        v-if="menu_active_template_id && !isCoreTemplate(menu_active_template_id)" 
        v-model="dialog_share_template" 
        :template_id="menu_active_template_id">
      </vue-template-share>
    <vue-template-acl :key="menu_active_template_id"
        v-if="menu_active_template_id && !isCoreTemplate(menu_active_template_id)" 
        v-model="dialog_acl_template" 
        :template_id="menu_active_template_id">
    </vue-template-acl>
    <vue-template-revision-history 
        :key="'r' +menu_active_template_id" 
        v-if="menu_active_template_id && !isCoreTemplate(menu_active_template_id)" 
        v-model="dialog_template_revision" 
        :template_id="menu_active_template_id"
      ></vue-template-revision-history>

    <vue-template-uuid :key="'uuid' + menu_active_template_id" 
        v-if="menu_active_template_id && !isCoreTemplate(menu_active_template_id)" 
        v-model="dialog_uuid_template" 
        :template_id="menu_active_template_id"
        v-on:update-uuid="loadTemplates"
      ></vue-template-uuid>

  </div>

  <script>
  
    
    <?php echo $this->load->view("metadata_editor/vue-login-component.js", null, true); ?>
    <?php include_once("vue-template-revision-history.js"); ?>

    <?php include_once("vue-template-share-component.js"); ?>
    <?php include_once("vue-template-share-common-component.js"); ?>
    <?php include_once("vue-template-acl-common-component.js"); ?>
    <?php include_once("vue-template-acl-component.js"); ?>
    <?php include_once("vue-template-uuid-component.js"); ?>
    <?php echo $this->load->view("editor_common/global-site-header-component.js", null, true);?>
    <?php echo $this->load->view("editor_common/main-navigation-tabs-component.js", null, true);?>
  

    const translation_messages = {
      default: <?php echo json_encode($translations,JSON_HEX_APOS);?>
    }

    const i18n = new VueI18n({
      locale: 'default', // set locale
      messages: translation_messages, // set locale messages
    })

    const Home = {
      template: '<div>Home -todo </div>'
    }

    const routes = [{
      path: '/',
      component: Home,
      name: 'home'
    }]

    const router = new VueRouter({
      routes
    })

    const vuetify = new Vuetify({
            theme: {
            themes: {
                light: {
                    primary: '#526bc7',
                    "primary-dark": '#0c1a4d',
                    secondary: '#b0bec5',
                    accent: '#8c9eff',
                    error: '#b71c1c',
                },
            },
            },
        })

    // Use GlobalLoginPlugin for session handling
    if (typeof GlobalLoginPlugin !== 'undefined') {
        Vue.use(GlobalLoginPlugin);
    }

    vue_app = new Vue({
      i18n,
      el: '#app',
      vuetify: vuetify,
      router: router,
      data: {
        site_base_url: CI.site_url,
        templates: { core: [], custom: [] },
        deleted_templates: { core: [], custom: [] },
        list_view: 'active',
        is_loading: false,
        loading_status: null,
        form_errors: [],
        facet_panel: [],
        pagination_page: 1,
        dialog_create_project: false,
        dialog_share_template:false,
        dialog_acl_template:false,
        dialog_template_revision:false,
        dialog_uuid_template:false,
        search_keywords: '',
        dialog_import_template: false,
        dialog_import_template_key: 0,
        import_template_file: null,
        import_parse_error: null,
        import_api_error: null,
        import_template_loading: false,
        import_uid_in_use: false,
        import_assign_new_uid: false,
        template_import_errors:[],
        dialog_import: {},
        importJSON: null,
        showTemplateMenu: false,        
        menu_x: 0,
        menu_y: 0,
        menu_active_template_id: null,
        menu_active_template_item: null,
        menu_is_deleted: false,
        menu_active_template_core: false,
        menu_active_template_data_type:'',
        nav_tabs_active:2,
        nav_tabs_model:2,
        sidebar_data_types: [],
        sidebar_selected: '',
        schemas: [],
        schemaFilters: [],
        schemaGroups: [],
        schemasByUid: {},
        schemasByAlias: {},
        schemasLoading: false
      },
      created: async function() {
        //await this.$store.dispatch('initData',{dataset_idno:this.dataset_idno});
        //this.init_tree_data();
      },
      mounted: async function() {
        var vm = this;
        this.initListViewFromUrl();
        await this.loadSchemaMeta();
        await this.loadTemplates();
        if (this.list_view === 'deleted') {
          await this.loadDeletedTemplates();
        }
        this.visiblility_change_handler();
      },
      computed: {
        Title() {
          return 'title';
        },
        Projects() {
          return this.projects.projects;
        },
        deletedTableHeaders() {
          return [
            { text: this.$t('title'), value: 'name' },
            { text: this.$t('language'), value: 'lang' },
            { text: this.$t('owner'), value: 'owner_username' },
            { text: this.$t('deleted_at'), value: 'deleted_at' },
            { text: this.$t('deleted_by'), value: 'deleted_by_username' },
            { text: '', value: 'actions', sortable: false }
          ];
        },
        canImportTemplate() {
          const info = CI.user_info || {};
          return info.can_import_template === true || info.is_admin === true;
        },
        canDuplicateTemplate() {
          const info = CI.user_info || {};
          return info.can_duplicate_template === true || info.is_admin === true;
        }
      },
      watch: {
        import_template_file: function() {
          this.onImportTemplateFileChange();
        },
        import_assign_new_uid: function() {
          if (this.import_assign_new_uid) {
            this.import_api_error = null;
          }
        },
        list_view: function(newValue) {
          this.syncListViewToUrl(newValue);
          if (newValue === 'deleted') {
            this.loadDeletedTemplates();
          }
        }
      },
      methods: {
        initListViewFromUrl() {
          const params = new URLSearchParams(window.location.search);
          const tab = params.get('tab');
          if (tab === 'deleted' || tab === 'active') {
            this.list_view = tab;
          }
        },
        syncListViewToUrl(tab) {
          const url = new URL(window.location.href);
          if (tab === 'active') {
            url.searchParams.delete('tab');
          } else {
            url.searchParams.set('tab', tab);
          }
          window.history.replaceState({}, '', url);
        },
        async loadSchemaMeta(){
          this.schemasLoading = true;
          try{
            const response = await axios.get(CI.site_url + '/api/schemas', {
              params: { include_core: true }
            });
            const schemas = response.data && Array.isArray(response.data.schemas)
              ? response.data.schemas
              : [];
            this.schemas = schemas;
            this.buildSchemaFilters();
          }catch(error){
            console.error('Failed to load schemas', error);
            this.schemas = [];
            this.schemaFilters = [];
          }finally{
            this.schemasLoading = false;
            this.updateSchemaGroups();
          }
        },
        visiblility_change_handler: function() {
          var vm = this;
          
          // Reload templates when page/tab becomes visible/active
          document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
              if (!vm.loading_status) {
                vm.loadTemplates();
              } else {
                console.log("Skipping reload - templates already loading");
              }
            }
          });
          
          var focusTimeout;
          window.addEventListener('focus', function() {
            console.log("window focus event fired");
            clearTimeout(focusTimeout);
            focusTimeout = setTimeout(function() {
              if (!vm.loading_status && !document.hidden) {
                console.log("Reloading templates due to window focus");
                vm.loadTemplates();
              }
            }, 500);
          });
          
        },
        buildSchemaFilters(){
          const filters = [];
          const byUid = {};
          const byAlias = {};

          (this.schemas || []).forEach(schema => {
            if (!schema){
              return;
            }
            const label = schema.display_name || schema.title || schema.uid || this.$t('unknown');
            const alias = schema.alias || '';
            const matchKeys = [];
            if (schema.uid){
              matchKeys.push(schema.uid);
            }
            if (alias){
              matchKeys.push(alias);
            }
            const filter = {
              uid: schema.uid || alias || label,
              label,
              alias,
              icon: schema.icon_full_url || schema.icon_url || null,
              matchKeys,
              schema
            };
            filters.push(filter);
            if (schema.uid){
              byUid[schema.uid] = filter;
            }
            if (alias){
              byAlias[alias] = filter;
            }
          });

          this.schemaFilters = filters;
          this.schemasByUid = byUid;
          this.schemasByAlias = byAlias;
        },
        updateSchemaGroups(){
          const groups = [];
          const seen = new Set();

          (this.schemaFilters || []).forEach(filter => {
            groups.push(filter);
            (filter.matchKeys || []).forEach(key => {
              seen.add(key);
            });
          });

          const extras = {};
          this.getAllTemplates().forEach(template => {
            if (template && template.data_type && !seen.has(template.data_type)){
              extras[template.data_type] = true;
            }
          });

          Object.keys(extras).sort().forEach(type => {
            groups.push({
              uid: type,
              label: this.getFallbackLabel(type),
              alias: '',
              icon: null,
              matchKeys: [type],
              schema: null,
              isFallback: true
            });
          });

          this.sidebar_data_types = groups;
          this.schemaGroups = groups;
        },
        getAllTemplates(){
          const core = Array.isArray(this.templates.core) ? this.templates.core : [];
          const custom = Array.isArray(this.templates.custom) ? this.templates.custom : [];
          return core.concat(custom);
        },
        getTemplatesForSchema(schema){
          if (!schema){
            return [];
          }
          const keys = (schema.matchKeys && schema.matchKeys.length)
            ? schema.matchKeys
            : (schema.uid ? [schema.uid] : []);
          if (!keys.length){
            return [];
          }
          return this.getAllTemplates().filter(template => template && keys.includes(template.data_type));
        },
        getSchemaIconSrc(schema){
          if (!schema){
            return null;
          }
          if (schema.icon){
            return schema.icon;
          }
          if (schema.schema){
            if (schema.schema.icon_full_url){
              return schema.schema.icon_full_url;
            }
            if (schema.schema.icon_url){
              return schema.schema.icon_url;
            }
          }
          return null;
        },
        getSchemaInitial(schema){
          if (!schema || !schema.label){
            return '?';
          }
          return schema.label.trim().charAt(0).toUpperCase() || '?';
        },
        getSchemaLabel(schema){

          if (!schema){
            return this.$te && this.$te('unknown') ? this.$t('unknown') : 'Unknown';
          }
          // Try to translate using the schema UID (like projects page does)
          if (schema.uid && this.$te && this.$te(schema.uid)){
            const translated = this.$t(schema.uid);
            if (translated && translated !== schema.uid){
              return translated;
            }
          }
          // Fallback to the label (display_name || title || uid)
          return schema.label || schema.uid || (this.$te && this.$te('unknown') ? this.$t('unknown') : 'Unknown');
        },
        getFallbackLabel(type){
          if (!type){
            return this.$te && this.$te('unknown') ? this.$t('unknown') : 'Unknown';
          }
          if (this.$te && this.$te(type)){
            const value = this.$t(type);
            if (value){
              return value;
            }
          }
          return type;
        },
        updateTemplateUUID(uid){
          this.dialog_uuid_template=true;
        },
        viewTemplateRevisions(uid){
          this.dialog_template_revision=true;
        },
        shareTemplate(uid){
          if (this.menu_active_template_data_type=='admin_meta'){
            this.dialog_acl_template=true;
            return;
          }

          this.dialog_share_template=true;
        },        
        getTemplateEditLink: function(template) {
          return CI.site_url + '/templates/edit/' + template.uid;
        },
        getTemplateRecord: function(uid) {
          if (!uid || !this.templates){
            return null;
          }

          const coreList = Array.isArray(this.templates.core) ? this.templates.core : [];
          const customList = Array.isArray(this.templates.custom) ? this.templates.custom : [];

          const coreMatch = coreList.find(template => template.uid == uid);
          if (coreMatch){
            return coreMatch;
          }

          const customMatch = customList.find(template => template.uid == uid);
          return customMatch || null;
        },
        isReadOnlyTemplate: function(item) {
          if (!item){
            return false;
          }
          if (item.template_type && item.template_type === 'core'){
            return true;
          }
          return !!item.is_generated;
        },
        templateTypeLabel: function(item) {
          if (item && item.template_type === 'core'){
            return this.$t('core');
          }
          if (item && item.is_generated){
            return this.$t('generated');
          }
          return this.$t('custom');
        },
        pageLink: function(page){
          window.location.href = CI.site_url + '/'+page;
        },
        showMenu (e, templateId, isCore=false, templateDataType='') {
          e.preventDefault()
          this.showTemplateMenu = false
          this.menu_x = e.clientX
          this.menu_y = e.clientY
          this.menu_active_template_id = templateId
          this.menu_active_template_item = this.getTemplateRecord(templateId)
          this.menu_is_deleted = false
          this.menu_active_template_core = isCore
          this.menu_active_template_data_type=templateDataType
          this.$nextTick(() => {
            this.showTemplateMenu = true
          })
        },
        showDeletedMenu (e, item) {
          e.preventDefault()
          this.showTemplateMenu = false
          this.menu_x = e.clientX
          this.menu_y = e.clientY
          this.menu_active_template_id = item.uid
          this.menu_active_template_item = item
          this.menu_is_deleted = true
          this.menu_active_template_core = false
          this.menu_active_template_data_type = item.data_type || ''
          this.$nextTick(() => {
            this.showTemplateMenu = true
          })
        },
        canManageTemplate(item) {
          if (!item) {
            return false;
          }
          const info = CI.user_info || {};
          if (info.is_admin || info.can_template_admin) {
            return true;
          }
          if (!info.user_id || !item.owner_id) {
            return false;
          }
          return parseInt(info.user_id, 10) === parseInt(item.owner_id, 10);
        },
        momentDate(date) {
          return moment.unix(date).format("MM/DD/YYYY")
        },
        async loadTemplates() {
          const url = CI.site_url + '/api/templates/';
          this.loading_status = this.$t("loading_templates");
          try{
            const response = await axios.get(url);
            const templates = response.data && response.data.templates ? response.data.templates : {};
            this.templates = {
              core: Array.isArray(templates.core) ? templates.core : [],
              custom: Array.isArray(templates.custom) ? templates.custom : []
            };
          }catch(error){
              console.log("error", error);
          }finally{
              this.loading_status = "";
            this.updateSchemaGroups();
          }
        },
        async loadDeletedTemplates() {
          const url = CI.site_url + '/api/templates/deleted';
          try{
            const response = await axios.get(url);
            const templates = response.data && response.data.templates ? response.data.templates : {};
            this.deleted_templates = {
              core: Array.isArray(templates.core) ? templates.core : [],
              custom: Array.isArray(templates.custom) ? templates.custom : []
            };
          }catch(error){
            console.log("error", error);
          }
        },
        getDeletedTemplatesForSchema(schema){
          if (!schema){
            return [];
          }
          const keys = (schema.matchKeys && schema.matchKeys.length)
            ? schema.matchKeys
            : (schema.uid ? [schema.uid] : []);
          if (!keys.length){
            return [];
          }
          const custom = Array.isArray(this.deleted_templates.custom) ? this.deleted_templates.custom : [];
          return custom.filter(template => template && keys.includes(template.data_type));
        },
        setDefaultTemplate: function(template_type, uid) {
          vm = this;
          let form_data = {};
          let url = CI.site_url + '/api/templates/default/' + template_type + '/' + uid;

          axios.post(url,
              form_data
              /*headers: {
                  "xname" : "value"
              }*/
            )
            .then(function(response) {
              console.log(response);
              vm.loadTemplates();
            })
            .catch(function(error) {
              console.log("error", error);
              alert(vm.$t("failed"), error);
            })
            .then(function() {
              console.log("request completed");
            });
        },
        isCoreTemplate: function(uid) {
          if (!uid){
            return false;
          }
          var record=this.getTemplateRecord(uid);
          if (!record){
            return false;
          }
          return record.template_type==='core' || !!record.is_generated;
        },
        deleteTemplate: function(uid) {
          const record=this.getTemplateRecord(uid);
          if (record && this.isReadOnlyTemplate(record)){
            this.$alert(this.$t('generated_template_locked'), { color: 'info' });
            return false;
          }
          if (!confirm(this.$t("confirm_delete"))) {
            return false;
          }

          const vm = this;
          let form_data = {};
          let url = CI.site_url + '/api/templates/delete/' + uid;

          axios.post(url,
              form_data
            )
            .then(function(response) {
              console.log(response);
              vm.loadTemplates();
            })
            .catch(function(error) {
              console.log("error", error);
              if (error.response.data.message){
                alert (vm.$t("failed") + ": " + error.response.data.message);
              }else{
                alert(vm.$t("failed") + ": "+ JSON.stringify(error.response.data));
              }
            })
            .then(function() {
              console.log("request completed");
            });
        },
        restoreTemplate: function(uid) {
          if (!confirm(this.$t('confirm_restore_template'))) {
            return false;
          }
          const vm = this;
          axios.post(CI.site_url + '/api/templates/restore/' + uid, {})
            .then(function() {
              vm.loadDeletedTemplates();
              vm.loadTemplates();
              vm.list_view = 'active';
            })
            .catch(function(error) {
              let message = vm.$t('failed');
              if (error.response && error.response.data && error.response.data.message) {
                message += ': ' + error.response.data.message;
              }
              alert(message);
            });
        },
        purgeTemplate: function(uid) {
          if (!confirm(this.$t('confirm_delete_permanently'))) {
            return false;
          }
          const vm = this;
          axios.post(CI.site_url + '/api/templates/purge/' + uid, {})
            .then(function() {
              vm.loadDeletedTemplates();
            })
            .catch(function(error) {
              let message = vm.$t('failed');
              if (error.response && error.response.data && error.response.data.message) {
                message += ': ' + error.response.data.message;
              }
              alert(message);
            });
        },
        exportTemplate: function(uid) {
          window.open(CI.site_url + '/api/templates/' + uid);
        },
        previewTemplate: function(uid) {
          window.open(CI.site_url + '/templates/preview/' + uid);
        },
        previewTableTemplate: function(uid) {
          window.open(CI.site_url + '/templates/table/' + uid);
        },
        pdfTemplate: function(uid) {
          window.open(CI.site_url + '/templates/pdf/' + uid);
        },
        duplicateTemplate: function(uid) {
          vm = this;
          let form_data = {};
          let url = CI.site_url + '/api/templates/duplicate/' + uid;
          this.loading_status = vm.$t("creating_template");

          axios.post(url,
              form_data
              /*headers: {
                  "xname" : "value"
              }*/
            )
            .then(function(response) {
              console.log(response);
              vm.loadTemplates();
              if (response.data.template.uid) {
                window.open(CI.site_url + '/templates/edit/' + response.data.template.uid);
              }
            })
            .catch(function(error) {
              console.log("error", error);
              if (error.response.data.error){
                alert (vm.$t("failed") + ": " + error.response.data.error);
              }else{
                alert(vm.$t("failed"), error);
              }
            })
            .then(function() {
              // always executed
              console.log("request completed");
            });
        },
        editTemplate: function(uid) {
          window.open(CI.site_url + '/templates/edit/' + uid);
        },
        getApiErrorMessage: function(error, fallback) {
          fallback = fallback || this.$t('failed');
          if (!error || !error.response || !error.response.data) {
            return fallback;
          }
          const data = error.response.data;
          if (typeof data === 'string') {
            return data.trim() ? data : fallback;
          }
          if (data.code === 'TEMPLATE_UID_DELETED' && data.message) {
            return data.message;
          }
          if (data.code === 'TEMPLATE_UID_ACTIVE' && data.message) {
            return data.message;
          }
          if (data.message) {
            return data.message;
          }
          if (data.error) {
            return data.error;
          }
          return fallback;
        },
        showImportTemplateDialog: function(){
          this.resetImportTemplateDialogState();
          this.dialog_import_template=true;
        },
        resetImportTemplateDialogState: function() {
          this.import_template_file = null;
          this.importJSON = null;
          this.import_parse_error = null;
          this.import_api_error = null;
          this.import_template_loading = false;
          this.import_uid_in_use = false;
          this.import_assign_new_uid = false;
          this.template_import_errors = [];
        },
        closeImportTemplateDialog: function() {
          if (this.import_template_loading) {
            return;
          }
          this.dialog_import_template = false;
          this.dialog_import_template_key++;
          this.resetImportTemplateDialogState();
        },
        onImportTemplateDialogOutsideClick: function() {
          if (this.import_template_loading) {
            return;
          }
          this.closeImportTemplateDialog();
        },
        onImportTemplateFileChange: function() {
          this.import_parse_error = null;
          this.import_api_error = null;
          this.importJSON = null;
          this.import_uid_in_use = false;
          this.import_assign_new_uid = false;

          if (!this.import_template_file) {
            return;
          }

          const vm = this;
          const reader = new FileReader();
          reader.onload = function(e) {
            try {
              const parsed = JSON.parse(e.target.result);
              if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
                vm.import_parse_error = vm.$t('invalid_file_failed_to_read');
                return;
              }
              vm.importJSON = parsed;
              vm.preflightImportTemplateUid();
            } catch (err) {
              vm.import_parse_error = vm.$t('invalid_file_failed_to_read');
            }
          };
          reader.onerror = function() {
            vm.import_parse_error = vm.$t('invalid_file_failed_to_read');
          };
          reader.readAsText(this.import_template_file);
        },
        preflightImportTemplateUid: function() {
          const uid = this.importJSON && this.importJSON.uid;
          if (!uid) {
            this.import_uid_in_use = false;
            this.import_assign_new_uid = false;
            return;
          }
          const vm = this;
          axios.get(CI.site_url + '/api/templates/uid/' + encodeURIComponent(uid))
            .then(function(response) {
              vm.import_uid_in_use = !!(response.data && response.data.found);
              if (!vm.import_uid_in_use) {
                vm.import_assign_new_uid = false;
              }
            })
            .catch(function() {
              vm.import_uid_in_use = false;
              vm.import_assign_new_uid = false;
            });
        },
        isImportTemplateUidConflictError: function(error) {
          if (!error || !error.response) {
            return false;
          }
          const data = error.response.data;
          if (error.response.status === 409) {
            return true;
          }
          if (data && (data.code === 'TEMPLATE_UID_DELETED' || data.code === 'TEMPLATE_UID_ACTIVE')) {
            return true;
          }
          return false;
        },
        resolveImportOnUidConflict: function() {
          if (this.import_uid_in_use && this.import_assign_new_uid) {
            return 'assign_new_uid';
          }
          return 'fail';
        },
        runImportTemplateCreate: function(onUidConflict) {
          const vm = this;
          if (!vm.importJSON) {
            return Promise.reject(new Error('missing payload'));
          }
          const payload = Object.assign({}, vm.importJSON, {
            on_uid_conflict: onUidConflict || 'fail',
          });
          const url = CI.site_url + '/api/templates/create';
          vm.import_template_loading = true;
          vm.import_api_error = null;
          vm.template_import_errors = [];

          return axios.post(url, payload).then(function(response) {
            return response;
          }).catch(function(error) {
            throw error;
          }).finally(function() {
            vm.import_template_loading = false;
          });
        },
        handleImportTemplateSuccess: function(response) {
          const vm = this;
          vm.loadTemplates();
          const data = response && response.data ? response.data : {};
          const template = data.template || {};
          alert(vm.$t('imported_successfully'));
          vm.closeImportTemplateDialog();
          if (template.uid) {
            window.open(CI.site_url + '/templates/edit/' + template.uid);
          }
        },
        importTemplate: function() {
          const vm = this;
          vm.runImportTemplateCreate(vm.resolveImportOnUidConflict())
            .then(function(response) {
              vm.handleImportTemplateSuccess(response);
            })
            .catch(function(error) {
              if (vm.isImportTemplateUidConflictError(error)) {
                vm.import_uid_in_use = true;
                vm.import_api_error = vm.$t('import_template_uid_conflict_enable_checkbox');
              } else {
                vm.import_api_error = vm.getApiErrorMessage(error);
              }
              vm.template_import_errors = error;
            });
        },
      }
    })

    //register components
    //vue_app.component('vue-template-share', VueTemplateShareComponent);
    Vue.component('VueJsonPretty', VueJsonPretty.default)

  </script>
</body>

</html>