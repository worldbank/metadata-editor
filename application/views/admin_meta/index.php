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

  <script src="//cdn.jsdelivr.net/npm/sortablejs@1.8.4/Sortable.min.js"></script>
  <script src="//cdnjs.cloudflare.com/ajax/libs/Vue.Draggable/2.20.0/vuedraggable.umd.min.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.20/lodash.min.js"></script>
  <script src="https://unpkg.com/moment@2.26.0/moment.js"></script>


  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

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
        <vue-main-app></vue-main-app>
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


    new Vue({
      el: "#app",
      i18n,
      vuetify: new Vuetify(),
      data() {
        return {
            message: 'Hello Vue!'
        }
      },
      created: function() {
      },
      methods: {
        

      }
     
    });
  </script>


</body>

</html>