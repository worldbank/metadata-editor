<html>

<head>

  <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@mdi/font@6.x/css/materialdesignicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css" crossorigin="anonymous" />
  
  <link href="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.min.css" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">

  <script src="https://cdn.jsdelivr.net/npm/vue@2.x/dist/vue.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.js"></script>
  <script src="https://unpkg.com/vuex@2.0.0"></script>
  <script src="<?php echo base_url(); ?>javascript/axios.min.js"></script>
  <script src="https://unpkg.com/vue-i18n@8"></script>

  <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.21/lodash.min.js"></script>
  <script src="https://unpkg.com/moment@2.26.0/moment.js"></script>


  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <script src="https://adminlte.io/themes/v3/plugins/jquery/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-fQybjgWLrvvRgtW6bFlB7jaZrFsaBXjsOMm/tB9LTS58ONXgqbR9W8oWht/amnpF" crossorigin="anonymous"></script>
  

  <style>
    <?php echo $this->load->view('metadata_editor/styles.css', null, true); ?>
  </style>

</head>

<body class="layout-top-nav">

  <script>
    var CI = {
      'base_url': '<?php echo site_url(); ?>'
    };
  </script>

  <div id="app" data-app>
    <v-app>
    
    <?php echo $this->load->view('editor_common/global-header', null, true); ?>
    <div class="container-fluid mt-5">
        <div class="row">
          <!-- <div class="" style="width:300px">
            left side bar
          </div> -->
          <div class="col" style="overflow:auto;">

                <div class="mt-5 mb-5 ml-3">
                  <h3>{{$t('administrative_metadata')}}</h3>

                  <div class="d-flex">

                    <v-tabs background-color="transparent" v-model="nav_tabs_model">
                        <v-tab @click="pageLink('projects')"><v-icon>mdi-text-box</v-icon> <a :href="site_base_url + '/editor'">{{$t("projects")}}</a></v-tab>
                        <v-tab @click="pageLink('collections')" ><v-icon>mdi-folder-text</v-icon> <a :href="site_base_url + '/collections'">{{$t("collections")}}</a> </v-tab>
                        <!--<v-tab>Archives</v-tab>-->
                        <v-tab @click="pageLink('templates')"><v-icon>mdi-alpha-t-box</v-icon> <a :href="site_base_url + '/templates'">{{$t("templates")}}</a></v-tab>
                        <v-tab @click="pageLink('templates')" active><v-icon>mdi-table-column</v-icon> <a :href="site_base_url + '/templates'">{{$t("administrative_metadata")}}</a></v-tab>
                    </v-tabs>

                  </div>
                  
                </div>


            <confirm-dialog></confirm-dialog>
            <vue-main-app></vue-main-app>
          </div>
      </div>
    </div>

    </v-app>
  </div>

  <script>
    
    <?php include_once("vue-dialog-component.js"); ?>
    <?php include_once("vue-meta-type-share-component.js"); ?>
    <?php include_once("vue-dialog-json-viewer-component.js"); ?>
    <?php include_once("vue-main.js"); ?>
    <?php include_once("vue-dialog-edit-schema-component.js"); ?>
    <?php include_once("vue-dialog-edit-meta-component.js"); ?>

    const translation_messages = {
      default: <?php echo json_encode($translations,JSON_HEX_APOS);?>
    }

    const i18n = new VueI18n({
      locale: 'default', // set locale
      messages: translation_messages, // set locale messages
    });

    Vue.mixin({
      methods: {          
            momentDateUnix(date) {
                if (!date) {
                    return "";
                }

                return moment.unix(date).format("YYYY-MM-DD");
            }            
        }
    })


    <?php echo $this->load->view("vue/vue-global-eventbus.js",null,true); ?>
    <?php echo $this->load->view("vue/vue-confirm-dialog-component.js",null,true); ?>


    const vuetify = new Vuetify({
            theme: {
            themes: {
                light: {
                    primary: '#526bc7',
                    secondary: '#b0bec5',
                    accent: '#8c9eff',
                    error: '#b71c1c',
                },
            },
            },
        });

    new Vue({
      el: "#app",
      i18n,
      vuetify: vuetify,
      data() {
        return {      
          nav_tabs_model: 3,
          site_base_url: CI.base_url      
        }
      },
      methods: {
        pageLink(page) {
          window.location.href = this.site_base_url + page;
        }
      }
    });
  </script>


</body>

</html>