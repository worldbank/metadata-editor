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
    .catalogs-table table thead th {
      vertical-align: middle !important;
      white-space: nowrap;
      height: 48px;
    }
    .catalogs-table table tbody td {
      vertical-align: middle !important;
      padding-top: 14px !important;
      padding-bottom: 14px !important;
    }
    .catalogs-table tbody tr {
      cursor: pointer;
    }
    .catalogs-title-cell {
      min-width: 0;
    }
    .catalogs-row-icon {
      width: 64px;
      height: 64px;
      border-radius: 8px;
      background: #fff;
      border: 1px solid #d7dbe3;
      box-shadow: 0 1px 4px rgba(15, 23, 42, 0.12);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      margin-right: 16px;
    }
    .catalogs-title {
      line-height: 1.3;
    }
    .catalogs-title-meta {
      margin-top: 4px;
      gap: 8px;
    }
    .catalogs-uid {
      font-size: 12px;
      color: #9e9e9e;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }
    .catalogs-url-sub {
      font-size: 13px;
      color: #6b7280;
      word-break: break-all;
    }
    .catalogs-id {
      font-variant-numeric: tabular-nums;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      color: #4b5563;
      cursor: pointer;
      font-size: 12px;
    }
    .catalogs-type-chip {
      height: 20px !important;
      font-size: 11px !important;
    }
    .catalog-availability-hint {
      font-weight: 400;
    }
    .catalog-readonly-summary {
      border: 1px solid #d7dbe3;
      border-radius: 8px;
      background: #f8fafc;
      padding: 16px;
    }
    .catalog-readonly-title {
      font-weight: 500;
      line-height: 1.3;
    }
    .v-dialog.catalog-connection-dialog {
      height: auto !important;
    }
    .v-dialog.catalog-connection-dialog .v-card {
      height: auto !important;
      flex: none !important;
    }
    .v-dialog.catalog-connection-dialog .v-card__text {
      flex: none !important;
    }
    .catalog-key-dialog-text {
      padding-top: 0 !important;
    }
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
    'can_access_admin_dashboard' => $this->ion_auth->can_access_admin_dashboard(),
    'can_manage_official' => $this->editor_acl->user_can_manage_official_catalogs(),
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
                  <vue-user-settings></vue-user-settings>
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
    echo $this->load->view("metadata_editor/vue-configure-catalog-component.js", null, true);
    echo $this->load->view("settings/vue-user-settings-component.js", null, true);
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

      new Vue({
        el: '#app',
        i18n,
        vuetify
      });
    })();
  </script>
</body>
</html>
