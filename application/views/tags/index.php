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
    .xtags-table .v-data-footer__select { display: none !important; }
  </style>
</head>

<body class="layout-top-nav">

<?php
  $user = $this->session->userdata('username');
  $this->load->library('Editor_acl');
  $user_info = array_merge(array(
    'username' => $user,
    'is_logged_in' => !empty($user),
    'is_admin' => $this->ion_auth->is_admin(),
    'can_access_site_admin' => $this->ion_auth->can_access_site_admin(),
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
                    <main-navigation-tabs active-tab="tags" v-model="navTabsModel"></main-navigation-tabs>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-12 mt-4">
                  <v-card>
                    <v-card-title class="d-flex align-center justify-space-between flex-wrap">
                      <span class="text-h6">{{ $t('Tags') }}</span>
                      <div class="d-flex align-center gap-2">
                        <v-btn
                          color="primary"
                          small
                          outlined
                          :loading="removingUnused"
                          :disabled="loading"
                          @click="confirmRemoveUnused"
                        >
                          <v-icon left small>mdi-tag-remove-outline</v-icon>
                          {{ $t('remove_unused_tags') }}
                        </v-btn>
                      </div>
                    </v-card-title>

                    <v-card-text>
                      <v-text-field
                        v-model="searchQuery"
                        :placeholder="$t('search_tags_placeholder') || 'Search tags...'"
                        dense
                        outlined
                        hide-details
                        clearable
                        class="mb-3"
                        style="max-width: 320px;"
                        prepend-inner-icon="mdi-magnify"
                      ></v-text-field>

                      <v-data-table
                        :headers="headers"
                        :items="tags"
                        :server-items-length="totalTags"
                        :items-per-page.sync="itemsPerPage"
                        :page.sync="page"
                        :loading="loading"
                        class="tags-table"
                        dense
                        item-key="id"
                      >
                        <template v-slot:item.tag="{ item }">
                          <span class="font-weight-medium"><a :href="CI.site_url+ '/editor?tag=' + item.id">{{ item.tag }}</a></span>
                          <v-chip v-if="item.is_core == 1" x-small class="ml-2" color="primary" outlined>{{ $t('core') }}</v-chip>
                        </template>

                        <template v-slot:item.project_count="{ item }">
                          <span>{{ item.project_count }}</span>
                        </template>

                        <template v-slot:item.actions="{ item }">
                          <div class="d-flex justify-end">
                            <v-menu bottom min-width="160" offset-y>
                              <template v-slot:activator="{ on, attrs }">
                                <v-btn icon small v-bind="attrs" v-on="on">
                                  <v-icon small>mdi-dots-vertical</v-icon>
                                </v-btn>
                              </template>
                              <v-list dense>
                                <v-list-item @click="confirmDelete(item)">
                                  <v-list-item-icon>
                                    <v-icon small color="error">mdi-delete</v-icon>
                                  </v-list-item-icon>
                                  <v-list-item-title>{{ $t('delete') }}</v-list-item-title>
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
            </div>
          </section>
        </div>
      </div>
    </v-app>
  </div>

  <script src="<?php echo base_url();?>vue-app/assets/vue-i18n.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/vue.min.js"></script>
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

      const apiBase = (CI && CI.site_url ? CI.site_url : '').replace(/\/?$/, '/') + 'api/tags';

      new Vue({
        el: '#app',
        i18n,
        vuetify,
        data() {
          return {
            navTabsModel: 4,
            tags: [],
            totalTags: 0,
            page: 1,
            itemsPerPage: 25,
            searchQuery: '',
            searchDebounce: null,
            loading: false,
            removingUnused: false
          };
        },
        watch: {
          page() { this.loadTags(); },
          itemsPerPage() { this.page = 1; this.loadTags(); },
          searchQuery() {
            if (this.searchDebounce) clearTimeout(this.searchDebounce);
            this.searchDebounce = setTimeout(() => {
              this.page = 1;
              this.loadTags();
            }, 350);
          }
        },
        computed: {
          headers() {
            return [
              { text: this.$t('id') || 'ID', value: 'id', sortable: false, align: 'left', width: '100px' },
              { text: this.$t('tag_name') || 'Tag', value: 'tag', sortable: false },
              { text: this.$t('projects') || 'Projects', value: 'project_count', sortable: false, align: 'center', width: '100px' },
              { text: '', value: 'actions', sortable: false, align: 'end', width: '60px' }
            ];
          }
        },
        created() {
          this.loadTags();
        },
        methods: {
          loadTags() {
            this.loading = true;
            const offset = (this.page - 1) * this.itemsPerPage;
            const params = { with_counts: 1, limit: this.itemsPerPage, offset: offset };
            const q = (this.searchQuery || '').trim();
            if (q) params.search = q;
            axios.get(apiBase, { params: params })
              .then(res => {
                if (res.data && res.data.status === 'success') {
                  this.tags = res.data.tags || [];
                  this.totalTags = res.data.total != null ? res.data.total : 0;
                } else {
                  this.tags = [];
                  this.totalTags = 0;
                }
              })
              .catch(err => {
                this.tags = [];
                this.totalTags = 0;
                const msg = (err.response && err.response.data && err.response.data.message) ? err.response.data.message : (err.message || 'Failed to load tags');
                EventBus.$emit('alert', { message: msg });
              })
              .finally(() => { this.loading = false; });
          },
          confirmDelete(item) {
            EventBus.$emit('confirm', {
              message: (this.$t('confirm_delete_tag')),
              resolve: (ok) => {
                if (ok) this.deleteTag(item.id);
              },
              reject: () => {}
            });
          },
          deleteTag(id) {
            this.loading = true;
            axios.post(apiBase + '/delete/' + id)
              .then(res => {
                if (res.data && res.data.status === 'success') {
                  this.loadTags();
                  EventBus.$emit('alert', { message: this.$t('tag_deleted') || 'Tag deleted.' });
                } else {
                  EventBus.$emit('alert', { message: (res.data && res.data.message) || 'Delete failed.' });
                }
              })
              .catch(err => {
                const msg = (err.response && err.response.data && err.response.data.message) || err.message || 'Delete failed.';
                EventBus.$emit('alert', { message: msg });
              })
              .finally(() => { this.loading = false; });
          },
          confirmRemoveUnused() {
            EventBus.$emit('confirm', {
              message: this.$t('confirm_remove_unused_tags') || 'Remove all tags that are not used by any project?',
              resolve: (ok) => {
                if (ok) this.removeUnused();
              },
              reject: () => {}
            });
          },
          removeUnused() {
            this.removingUnused = true;
            axios.post(apiBase + '/remove_unused')
              .then(res => {
                if (res.data && res.data.status === 'success') {
                  this.loadTags();
                  const n = (res.data.deleted != null) ? res.data.deleted : 0;
                  EventBus.$emit('alert', { message: (this.$t('unused_tags_removed') || '{n} unused tag(s) removed.').replace('{n}', n) });
                } else {
                  EventBus.$emit('alert', { message: (res.data && res.data.message) || 'Request failed.' });
                }
              })
              .catch(err => {
                const msg = (err.response && err.response.data && err.response.data.message) || err.message || 'Request failed.';
                EventBus.$emit('alert', { message: msg });
              })
              .finally(() => { this.removingUnused = false; });
          }
        }
      });
    })();
  </script>
</body>
</html>
