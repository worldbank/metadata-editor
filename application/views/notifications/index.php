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
    table th {
      white-space: nowrap;
    }
    .jobs-page .jobs-table {
      width: 100%;
    }
    .jobs-page .jobs-table .v-data-table__wrapper {
      width: 100%;
    }
    .jobs-page .jobs-table .v-data-table__wrapper > table {
      width: 100%;
      table-layout: auto;
    }
    .jobs-page .jobs-table tbody tr,
    .table-jobs tbody tr {
      cursor: pointer;
    }
    .jobs-page .jobs-table tbody tr:hover,
    .table-jobs tbody tr:hover {
      background: rgba(82, 107, 199, .1) !important;
    }
    .jobs-page .jobs-filter-select {
      width: 100%;
    }
    .jobs-page .jobs-filter-select .v-input__control {
      width: 100%;
    }
    .jobs-page .jobs-clear-filters-btn {
      text-transform: none;
      letter-spacing: normal;
    }
    .jobs-page .jobs-stat-card {
      cursor: pointer;
      transition: box-shadow 0.15s ease, border-color 0.15s ease;
    }
    .jobs-page .jobs-stat-card:hover {
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
    }
    .jobs-page .jobs-stat-card--active {
      border-color: #526bc7 !important;
      border-width: 2px;
      box-shadow: 0 2px 8px rgba(82, 107, 199, 0.25);
    }
    .notifications-page .notifications-table tbody td {
      padding-top: 12px !important;
      padding-bottom: 12px !important;
    }
    .notifications-page .notification-row--unread .notification-title {
      font-weight: 600;
    }
    .notifications-page .notification-title {
      line-height: 1.35;
    }
    .notifications-page .notification-body {
      color: rgba(0, 0, 0, 0.6);
      font-size: 13px;
      margin-top: 2px;
    }
    .notifications-page .notification-unread-dot {
      display: inline-block;
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: transparent;
      border: 1px solid #c5c9d3;
    }
    .notifications-page .notification-unread-dot--on {
      background: #526bc7;
      border-color: #526bc7;
    }
    .notifications-page .notification-type-chip,
    .notifications-page .notification-action-chip {
      height: 20px !important;
      font-size: 11px !important;
    }
    .notifications-page .notification-detail-table td {
      padding-top: 10px !important;
      padding-bottom: 10px !important;
      vertical-align: top;
    }
    .notifications-page .notification-detail-label {
      width: 160px;
      color: rgba(0, 0, 0, 0.54);
      white-space: nowrap;
    }
    .notifications-page .notification-detail-value {
      word-break: break-word;
      white-space: pre-wrap;
    }
    .notifications-page .notification-detail-extra {
      color: rgba(0, 0, 0, 0.54);
      font-size: 12px;
      margin-top: 2px;
      word-break: break-word;
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
                  <vue-notifications-component></vue-notifications-component>
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
  <script src="<?php echo base_url();?>vue-app/assets/moment-with-locales.min.js"></script>

  <script>
    <?php
    echo $this->load->view("vue/vue-global-eventbus.js", null, true);
    echo $this->load->view("vue/vue-alert-dialog-component.js", null, true);
    echo $this->load->view("vue/vue-confirm-dialog-component.js", null, true);
    echo $this->load->view("editor_common/global-site-header-component.js", null, true);
    echo $this->load->view("notifications/vue-notifications-component.js", null, true);
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
