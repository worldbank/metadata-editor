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
  $can_edit_issues = false;
  try {
      $has_schema_permission = $this->editor_acl->has_access('schema', 'view');
      $can_edit_issues = $this->editor_acl->has_access('editor', 'edit');
  } catch (Exception $e) {
      $has_schema_permission = false;
      $can_edit_issues = false;
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
            <div class="container">
              <div class="row">
                <div class="col-12">
                  <div class="mt-2 mb-4">
                    <issue-detail-view :issue-id="issueId" :can-edit="canEdit"></issue-detail-view>
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
  <script src="<?php echo base_url();?>vue-app/assets/json-diff-kit/json-diff-kit.umd.min.js"></script>
  <link href="<?php echo base_url();?>vue-app/assets/json-diff-kit/viewer.css" rel="stylesheet">

  <script>
    <?php
    echo $this->load->view("vue/vue-global-eventbus.js", null, true);
    echo $this->load->view("vue/vue-alert-dialog-component.js", null, true);
    echo $this->load->view("vue/vue-confirm-dialog-component.js", null, true);
    echo $this->load->view("editor_common/global-site-header-component.js", null, true);
    echo $this->load->view("editor_common/main-navigation-tabs-component.js", null, true);
    echo $this->load->view("issues/vue-issue-detail-view-component.js", null, true);
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
        vuetify,
        data() {
          return {
            issueId: <?php echo (int) isset($issue_id) ? $issue_id : 0; ?>,
            canEdit: <?php echo !empty($can_edit_issues) ? 'true' : 'false'; ?>
          };
        }
      });
    })();
  </script>
</body>
</html>
