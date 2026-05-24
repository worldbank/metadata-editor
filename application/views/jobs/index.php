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
    .jobs-page .jobs-cell-uuid {
      font-family: 'Courier New', monospace;
      font-size: 12px;
      word-break: break-all;
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
    .jobs-page .jobs-json-block {
      background: #f5f5f5;
      border: 1px solid #e0e0e0;
      border-radius: 4px;
      padding: 12px;
      font-family: 'Courier New', monospace;
      font-size: 12px;
      line-height: 1.45;
      overflow-x: auto;
      white-space: pre-wrap;
      word-break: break-word;
      max-height: 320px;
      overflow-y: auto;
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
                  <vue-jobs-component></vue-jobs-component>
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
    echo $this->load->view("jobs/vue-jobs-component.js", null, true);
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
