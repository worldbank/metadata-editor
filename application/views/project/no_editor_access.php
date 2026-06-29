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
    .editor-access-notice-card {
      border: 1px solid #e0e4ec;
      border-left: 4px solid #0c1a4d;
      border-radius: 4px;
      background: #fff;
      box-shadow: 0 4px 14px rgba(12, 26, 77, 0.12), 0 2px 6px rgba(12, 26, 77, 0.08) !important;
    }
    .editor-access-notice-card .notice-icon {
      color: #526bc7;
      flex-shrink: 0;
    }
    .editor-access-notice-card .notice-title {
      color: #0c1a4d;
      font-size: 1.125rem;
      font-weight: 500;
      letter-spacing: 0.01em;
      line-height: 1.4;
    }
    .editor-access-notice-card .notice-body {
      color: #5f6368;
      font-size: 0.9375rem;
      line-height: 1.6;
    }
    .editor-access-notice-card .notice-admin {
      color: #5f6368;
      font-size: 0.875rem;
      line-height: 1.55;
      padding-top: 12px;
      margin-top: 12px;
      border-top: 1px solid #eef0f4;
    }
  </style>
</head>

<body class="layout-top-nav">

<?php
  $user_info = build_editor_user_info(null, true);
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
        <vue-global-site-header></vue-global-site-header>
        <div class="content-wrapperx" v-cloak>
          <section class="content">
            <div class="container-fluid">
              <div class="row justify-content-center">
                <div class="col-md-8 col-lg-7 mt-5">
                  <v-card class="editor-access-notice-card" elevation="4" tile>
                    <v-card-text class="pa-5">
                      <div class="d-flex">
                        <v-icon class="notice-icon mr-4" size="32">mdi-shield-lock-outline</v-icon>
                        <div class="flex-grow-1">
                          <div class="notice-title mb-2">{{ $t('editor_access_required_title') }}</div>
                          <div class="notice-body">{{ $t('editor_access_required_message') }}</div>
                          <div v-if="CI.user_info.can_access_site_admin" class="notice-admin">
                            {{ $t('editor_access_required_admin_hint') }}
                            <div class="mt-3">
                              <v-btn color="primary" outlined @click="goToUsers">{{ $t('title_user_management') }}</v-btn>
                            </div>
                          </div>
                        </div>
                      </div>
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

  <script src="<?php echo base_url();?>vue-app/assets/vue.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/vuetify.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/vue-i18n.min.js"></script>
  <script>
    <?php echo $this->load->view('editor_common/global-site-header-component.js', null, true); ?>

    const translation_messages = {
      default: <?php echo json_encode($translations, JSON_HEX_APOS); ?>
    };

    const i18n = new VueI18n({
      locale: 'default',
      messages: translation_messages,
    });

    const vuetify = new Vuetify({
      theme: {
        themes: {
          light: {
            primary: '#526bc7',
            'primary-dark': '#0c1a4d',
          },
        },
      },
    });

    new Vue({
      el: '#app',
      vuetify,
      i18n,
      methods: {
        goToUsers() {
          window.location.href = CI.site_url + '/admin/users';
        }
      }
    });
  </script>
</body>
</html>
