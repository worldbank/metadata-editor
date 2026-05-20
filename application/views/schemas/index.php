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
    .schemas-table .schema-title-link {
      color: #526bc7;
      cursor: pointer;
      font-weight: 500;
      transition: color 0.2s;
    }

    .schemas-table .schema-title-link:hover {
      text-decoration: underline;
    }

    .schemas-table .schema-title-link--disabled {
      color: rgba(0, 0, 0, 0.38);
      cursor: default;
      pointer-events: none;
      text-decoration: none;
    }
    .schemas-table .schema-icon-cell {
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .schemas-table .schema-icon-avatar {
      width: 28px;
      height: 28px;
      border-radius: 6px;
      background-color: transparent;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .schemas-table .schema-icon-placeholder {
      font-weight: 600;
      font-size: 12px;
      color: rgba(0, 0, 0, 0.54);
    }

    .schemas-table .schema-icon-image {
      width: 28px;
      height: 28px;
      object-fit: contain;
      display: block;
    }

    .schemas-table .v-data-table__wrapper table tbody tr {
      height: 56px;
    }

    .schemas-table .v-data-table__wrapper table tbody tr td {
      padding-top: 14px;
      padding-bottom: 14px;
    }

    .debug-json {
      background-color: #f5f5f5;
      border-radius: 6px;
      padding: 12px;
      font-family: Menlo, Monaco, Consolas, "Courier New", monospace;
      font-size: 12px;
      line-height: 1.5;
      white-space: pre-wrap;
      word-break: break-word;
      max-height: 300px;
      overflow: auto;
    }

    .mapping-table td{
      padding-top: 14px !important;
      padding-bottom: 14px !important;
    }

  </style>

<?php
  $user=$this->session->userdata('username');
  $this->load->library('Editor_acl');
  
  $has_schema_permission = false;
  try {
      $has_schema_permission = $this->editor_acl->has_access('schema', 'view');
  } catch (Exception $e) {
      $has_schema_permission = false;
  }

  $user_info=[
    'username'=> $user,
    'is_logged_in'=> !empty($user),
    'is_admin'=> $this->ion_auth->is_admin(),
    'can_access_site_admin'=> $this->ion_auth->can_access_site_admin(),
    'has_schema_permission'=> $has_schema_permission,
  ];
?>

</head>

<body class="layout-top-nav">

  <script>
    var CI = {
      'site_url': '<?php echo site_url(); ?>',
      'base_url': '<?php echo base_url(); ?>',
      'user_info': <?php echo json_encode($user_info); ?>
    };
  </script>

  <script type="text/x-template" id="schema-mapping-template">
    <div class="row">
      <div class="col-12 mt-4">
        <v-card>
          <v-card-title class="d-flex align-center justify-space-between">
            <span class="text-h6">{{$t('core_field_mappings')}} - {{ schemaTitle }}</span>
            <v-btn text small color="primary" @click="$router.back()">
              <v-icon left small>mdi-arrow-left</v-icon>
              {{$t('back_to_schemas')}}
            </v-btn>            
          </v-card-title>
          <v-card-subtitle>
            <span class="text-subtitle-1">{{$t('core_field_mappings_hint')}}</span>
          </v-card-subtitle>

          <v-card-text>
            <v-progress-linear indeterminate color="primary" v-if="loading"></v-progress-linear>

            <v-form v-if="!loading" ref="form" v-model="valid" lazy-validation>
              <v-simple-table dense class="mapping-table">
                <thead>
                  <tr>
                    <th class="text-left" style="width: 200px;">{{$t('core_field')}}</th>
                    <th class="text-left">{{$t('mapped_field')}}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>
                      <label class="font-weight-medium">IDNO <span class="red--text">*</span></label>
                    </td>
                    <td>
                      <v-combobox
                        v-model="form.core_fields.idno"
                        :items="fieldOptions"
                        multiple
                        chips
                        dense
                        outlined
                        hide-details="auto"
                        clearable
                        :loading="fieldsLoading"
                        :disabled="fieldsLoading && !fieldOptions.length"
                      ></v-combobox>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <label class="font-weight-medium">Title <span class="red--text">*</span></label>
                    </td>
                    <td>
                      <v-combobox
                        v-model="form.core_fields.title"
                        :items="fieldOptions"
                        multiple
                        chips
                        dense
                        outlined
                        hide-details="auto"
                        clearable
                        :loading="fieldsLoading"
                        :disabled="fieldsLoading && !fieldOptions.length"
                      ></v-combobox>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <label class="font-weight-medium">Country</label>
                    </td>
                    <td>
                      <v-combobox
                        v-model="form.core_fields.country"
                        :items="fieldOptions"
                        multiple
                        chips
                        dense
                        outlined
                        hide-details="auto"
                        clearable
                        :loading="fieldsLoading"
                        :disabled="fieldsLoading && !fieldOptions.length"
                      ></v-combobox>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <label class="font-weight-medium">Year Start</label>
                    </td>
                    <td>
                      <v-combobox
                        v-model="form.core_fields.year_start"
                        :items="fieldOptions"
                        multiple
                        chips
                        dense
                        outlined
                        hide-details="auto"
                        clearable
                        :loading="fieldsLoading"
                        :disabled="fieldsLoading && !fieldOptions.length"
                      ></v-combobox>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <label class="font-weight-medium">Year End</label>
                    </td>
                    <td>
                      <v-combobox
                        v-model="form.core_fields.year_end"
                        :items="fieldOptions"
                        multiple
                        chips
                        dense
                        outlined
                        hide-details="auto"
                        clearable
                        :loading="fieldsLoading"
                        :disabled="fieldsLoading && !fieldOptions.length"
                      ></v-combobox>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <label class="font-weight-medium">Attributes</label>
                    </td>
                    <td>
                      <div>
                        <v-simple-table dense v-if="Object.keys(form.core_fields.attributes || {}).length > 0" class="mb-2">
                          <thead>
                            <tr>
                              <th class="text-left" style="width: 200px;">{{$t('attribute_key')}}</th>
                              <th class="text-left">{{$t('mapped_field')}}</th>
                              <th class="text-right" style="width: 60px;">{{$t('actions')}}</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="(value, key) in form.core_fields.attributes" :key="key">
                              <td>
                                <v-text-field
                                  :value="key"
                                  @input="updateAttributeKey(key, $event)"
                                  dense
                                  outlined
                                  hide-details="auto"
                                ></v-text-field>
                              </td>
                              <td>
                                <v-combobox
                                  :value="value"
                                  @input="updateAttributeValue(key, $event)"
                                  :items="fieldOptions"
                                  dense
                                  outlined
                                  hide-details="auto"
                                  clearable
                                  :loading="fieldsLoading"
                                  :disabled="fieldsLoading && !fieldOptions.length"
                                ></v-combobox>
                              </td>
                              <td class="text-right">
                                <v-btn
                                  icon
                                  small
                                  color="error"
                                  @click="removeAttribute(key)"
                                >
                                  <v-icon small>mdi-delete</v-icon>
                                </v-btn>
                              </td>
                            </tr>
                          </tbody>
                        </v-simple-table>
                        <v-btn
                          small
                          outlined
                          color="primary"
                          @click="addAttribute"
                          class="mt-2"
                        >
                          <v-icon left small>mdi-plus</v-icon>
                          {{$t('add_attribute')}}
                        </v-btn>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </v-simple-table>

              <v-alert
                v-if="formErrorMessage"
                type="error"
                dense
                outlined
                class="mb-4 mt-4"
              >
                {{ formErrorMessage }}
              </v-alert>
            </v-form>
          </v-card-text>

          <v-card-actions>
            <v-spacer></v-spacer>
            <v-btn text color="primary" @click="$router.back()">{{$t('cancel')}}</v-btn>
            <v-btn color="primary" :loading="saving" :disabled="!valid" @click="submit">
              {{$t('save_mappings')}}
            </v-btn>
          </v-card-actions>
        </v-card>
      </div>
    </div>
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
                <div class="col-12">
                  <div class="mt-5 mb-4">
                    <main-navigation-tabs active-tab="schemas" v-model="navTabsModel"></main-navigation-tabs>
                  </div>
                </div>
              </div>

              <router-view></router-view>

            </div>
          </section>
        </div>
      </div>

    </v-app>
  </div>

  <script type="text/x-template" id="schema-list-template">
    <div class="row">
      <div class="col-12 mt-4">
        <v-card>
          <v-card-title class="d-flex align-center justify-space-between">
            <span class="text-h6">{{$t('schemas')}}</span>
            <v-btn color="primary" small outlined @click="$router.push({ name: 'create-schema' })">
              <v-icon left small>mdi-plus</v-icon>
              {{$t('create_schema')}}
            </v-btn>
          </v-card-title>

          <v-card-text>
            <v-data-table
              :headers="headers"
              :items="schemas"
              :items-per-page="-1"
              :loading="loading"
              class="schemas-table"
              dense
              item-key="uid"
              hide-default-footer>

              <template v-slot:item.icon_url="{ item }">
                <div class="schema-icon-cell">
                  <div class="schema-icon-avatar">
                    <img
                      v-if="iconSrc(item)"
                      :src="iconSrc(item)"
                      :alt="item.display_name || item.title || item.uid"
                      class="schema-icon-image"
                    >
                    <span v-else class="schema-icon-placeholder">
                      {{ (item.display_name || item.title || item.uid || '?').charAt(0).toUpperCase() }}
                    </span>
                  </div>
                </div>
              </template>

              <template v-slot:item.title="{ item }">
                <span
                  class="schema-title-link"
                  :class="{ 'schema-title-link--disabled': item.is_core }"
                  role="button"
                  tabindex="0"
                  @click="handleTitleClick(item)"
                  @keyup.enter="handleTitleClick(item)"
                  @keyup.space.prevent="handleTitleClick(item)"
                >
                  {{ item.title || item.uid }}
                </span>
              </template>

              <template v-slot:item.uid="{ item }">
                <div>
                  <span>{{ item.uid }}</span>
                  <div class="text-caption grey--text" v-if="item.storage_path">
                    {{ item.storage_path }}
                  </div>
                </div>
              </template>

              <template v-slot:item.alias="{ item }">
                <span v-if="item.alias">{{ item.alias }}</span>
                <span v-else class="text--disabled">—</span>
              </template>

              <template v-slot:item.is_core="{ item }">
                <span>{{ item.is_core ? $t('core') : $t('custom') }}</span>
              </template>

              <template v-slot:item.updated="{ item }">
                <span>{{ formatDate(item.updated) }}</span>
              </template>

              <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end">
                  <v-menu bottom min-width="200" offset-y>
                    <template v-slot:activator="{ attrs, on }">
                      <v-btn icon small v-bind="attrs" v-on="on">
                        <v-icon small>mdi-dots-vertical</v-icon>
                      </v-btn>
                    </template>
                    <v-list dense>
                      <v-list-item @click="previewSchema(item)">
                        <v-list-item-icon>
                          <v-icon small>mdi-eye</v-icon>
                        </v-list-item-icon>
                        <v-list-item-title>{{$t('preview_schema')}}</v-list-item-title>
                      </v-list-item>
                      <v-list-item @click="editSchemaMappings(item)" :disabled="item.is_core">
                        <v-list-item-icon>
                          <v-icon small>mdi-source-fork</v-icon>
                        </v-list-item-icon>
                        <v-list-item-title>{{$t('edit_core_mappings')}}</v-list-item-title>
                      </v-list-item>
                      <v-list-item @click="regenerateTemplate(item)" :disabled="item.is_core">
                        <v-list-item-icon>
                          <v-icon small>mdi-refresh</v-icon>
                        </v-list-item-icon>
                        <v-list-item-title>{{$t('regenerate_template')}}</v-list-item-title>
                      </v-list-item>
                      <v-divider class="my-1" v-if="!item.is_core"></v-divider>
                      <v-list-item @click="editSchema(item)" :disabled="item.is_core">
                        <v-list-item-icon>
                          <v-icon small>mdi-pencil</v-icon>
                        </v-list-item-icon>
                        <v-list-item-title>{{$t('edit')}}</v-list-item-title>
                      </v-list-item>
                      <v-list-item @click="deleteSchema(item)" :disabled="item.is_core">
                        <v-list-item-icon>
                          <v-icon small>mdi-delete</v-icon>
                        </v-list-item-icon>
                        <v-list-item-title>{{$t('delete')}}</v-list-item-title>
                      </v-list-item>
                    </v-list>
                  </v-menu>
                </div>
              </template>

            </v-data-table>
          </v-card-text>
        </v-card>
      </div>
    </div>
  </script>

  <script type="text/x-template" id="schema-create-template">
    <div class="row">
      <div class="col-12 mt-4">
        <v-card>
          <v-card-title class="d-flex align-center justify-space-between">
            <span class="text-h6">{{ formTitle }}</span>
          </v-card-title>

          <v-card-text>
            <v-progress-linear indeterminate color="primary" v-if="initializing"></v-progress-linear>

            <v-form v-if="!initializing" ref="form" v-model="valid" lazy-validation>
              <v-row>
                <v-col cols="12" md="4">
                  <label class="font-weight-medium d-block mb-1">{{$t('schema_uid')}}</label>
                  <v-text-field
                    v-model="form.uid"
                    :rules="uidFieldRules"
                    :hint="$t('uid_hint')"
                    persistent-hint
                    :disabled="!isCreate"
                    :readonly="!isCreate"
                    dense
                    outlined
                    hide-details="auto"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="8">
                  <label class="font-weight-medium d-block mb-1">{{$t('title')}}</label>
                  <v-text-field
                    v-model="form.title"
                    :rules="[rules.required]"
                    dense
                    outlined
                    hide-details="auto"
                  ></v-text-field>
                </v-col>
              </v-row>

              <v-row>
                <v-col cols="12">
                  <label class="font-weight-medium d-block mb-1">{{$t('description')}}</label>
                  <v-textarea
                    v-model="form.description"
                    outlined
                    rows="3"
                    dense
                    hide-details="auto"
                  ></v-textarea>
                </v-col>
              </v-row>

              <v-divider class="my-6"></v-divider>

              <v-card outlined class="mt-4">
                <v-card-title class="py-3">
                  <span class="text-subtitle-1 font-weight-medium">{{$t('main_schema_file')}}</span>
                </v-card-title>
                <v-divider></v-divider>
                <v-card-text>
                  <input
                    type="file"
                    accept="application/json"
                    @change="handleMainFileSelection"
                    class="d-block w-100"
                  >
                  <div class="text-caption grey--text mt-2">
                    {{$t(isCreate ? 'main_schema_hint' : 'replace_main_schema')}}
                  </div>

                  <v-simple-table v-if="mainFileRows.length" class="mt-4">
                    <thead>
                      <tr>
                        <th>{{$t('file_name')}}</th>
                        <th class="text-right">{{$t('size')}}</th>
                        <th class="text-right">{{$t('actions')}}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="row in mainFileRows" :key="row.key">
                        <td>
                          <div class="font-weight-medium">{{ row.name }}</div>
                          <div class="text-caption grey--text">
                            {{ row.type === 'staged' ? $t('pending_main_indicator') : $t('current_main_indicator') }}
                          </div>
                        </td>
                        <td class="text-right">
                          {{ formatFileSize(row.size) }}
                        </td>
                        <td class="text-right">
                          <v-btn
                            v-if="row.type === 'staged'"
                            icon
                            small
                            color="error"
                            @click="removeMainFile"
                          >
                            <v-icon small>mdi-close</v-icon>
                          </v-btn>
                          <v-btn
                            v-else-if="row.download_url"
                            small
                            text
                            color="primary"
                            :href="row.download_url"
                            target="_blank"
                          >
                            {{$t('download')}}
                          </v-btn>
                        </td>
                      </tr>
                    </tbody>
                  </v-simple-table>
                  <div v-else-if="!fileManifestLoading" class="text-caption grey--text mt-3">
                    {{$t('no_schema_files_found')}}
                  </div>
                  <v-alert
                    v-else
                    type="info"
                    dense
                    outlined
                    class="mt-4"
                  >
                    {{$t('schema_files_loading')}}
                  </v-alert>
                </v-card-text>
              </v-card>

              <v-card outlined class="mt-6">
                <v-card-title class="py-3">
                  <span class="text-subtitle-1 font-weight-medium">{{$t('related_schema_files')}}</span>
                </v-card-title>
                <v-divider></v-divider>
                <v-card-text>
                  <input
                    type="file"
                    accept="application/json"
                    multiple
                    @change="handleAssociatedFilesSelection"
                    class="d-block w-100"
                  >
                  <div class="text-caption grey--text mt-2">
                    {{$t('related_schema_hint')}}
                  </div>

                  <div v-if="associatedFileRows.length" class="mt-4">
                    <v-simple-table>
                      <thead>
                        <tr>
                          <th>{{$t('file_name')}}</th>
                          <th class="text-right">{{$t('size')}}</th>
                          <th class="text-right">{{$t('actions')}}</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="row in associatedFileRows" :key="row.key">
                          <td>
                            <div class="font-weight-medium">{{ row.name }}</div>
                            <div class="text-caption grey--text">
                              {{ row.type === 'staged' ? $t('pending_related_indicator') : $t('current_related_indicator') }}
                            </div>
                          </td>
                          <td class="text-right">
                            {{ formatFileSize(row.size) }}
                          </td>
                          <td class="text-right">
                            <v-btn
                              v-if="row.type === 'staged'"
                              icon
                              small
                              color="error"
                              @click="removeAssociatedFile(row.staged)"
                            >
                              <v-icon small>mdi-close</v-icon>
                            </v-btn>
                            <template v-else>
                              <v-btn
                                v-if="row.download_url"
                                small
                                text
                                color="primary"
                                :href="row.download_url"
                                target="_blank"
                              >
                                {{$t('download')}}
                              </v-btn>
                              <v-btn
                                v-if="row.manifest"
                                small
                                text
                                color="error"
                                :loading="isDeletingFile(row.manifest && row.manifest.filename)"
                                @click.prevent="deleteSchemaFile(row.manifest)"
                              >
                                {{$t('delete')}}
                              </v-btn>
                            </template>
                          </td>
                        </tr>
                      </tbody>
                    </v-simple-table>
                  </div>
                  <div v-else-if="!fileManifestLoading" class="text-caption grey--text mt-3">
                    {{$t('no_schema_files_found')}}
                  </div>
                  <v-alert
                    v-else
                    type="info"
                    dense
                    outlined
                    class="mt-4"
                  >
                    {{$t('schema_files_loading')}}
                  </v-alert>
                </v-card-text>
              </v-card>

              <div v-if="!isCreate" class="mt-6">
                <label class="font-weight-medium d-block mb-2">Schema debug</label>
                <pre class="debug-json">{{ debugState }}</pre>
              </div>


              <v-divider class="my-6"></v-divider>

              <v-alert
                v-if="formErrorMessage"
                type="error"
                dense
                outlined
                class="mb-4"
              >
                {{ formErrorMessage }}
              </v-alert>

              <v-alert
                v-if="uploadErrorMessage"
                type="error"
                dense
                outlined
                class="mb-4"
              >
                {{ uploadErrorMessage }}
              </v-alert>
            </v-form>
          </v-card-text>

          <v-card-actions>
            <v-spacer></v-spacer>
            <v-btn text color="primary" @click="cancel">{{$t('cancel')}}</v-btn>
            <v-btn color="primary" :loading="uploading" :disabled="isSaveDisabled" @click="submit">
              {{ submitButtonText }}
            </v-btn>
          </v-card-actions>
        </v-card>
      </div>
    </div>
  </script>

  <script src="<?php echo base_url();?>vue-app/assets/moment-with-locales.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/vue-i18n.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/vue.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/vuetify.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/axios.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/vue-router.min.js"></script>

  <script>
    <?php
      echo $this->load->view("vue/vue-global-eventbus.js", null, true);
      echo $this->load->view("vue/vue-alert-dialog-component.js", null, true);
      echo $this->load->view("vue/vue-confirm-dialog-component.js", null, true);
      echo $this->load->view("editor_common/global-site-header-component.js", null, true);
      echo $this->load->view("editor_common/main-navigation-tabs-component.js", null, true);
      echo $this->load->view("schemas/vue-schemas-app.js", array('translations'=>$translations), true);
    ?>
  </script>

</body>
</html>

