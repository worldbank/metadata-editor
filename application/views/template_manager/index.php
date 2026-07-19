<html>

<head>
  <link rel="icon" href="<?php echo base_url();?>favicon.ico">
  <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
  <link href="<?php echo base_url();?>vue-app/assets/mdi.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css" crossorigin="anonymous" />

  <link href="<?php echo base_url();?>vue-app/assets/vuetify.min.css" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">

  <script src="<?php echo base_url();?>vue-app/assets/vue.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/vuetify.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/vuex.min.js"></script>
  
  <script src="<?php echo base_url(); ?>vue-app/assets/axios.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/vue-i18n.js"></script>

  <script src="<?php echo base_url(); ?>vue-app/assets/sortable.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/vuedraggable.umd.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/lodash.min.js"></script>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

  <style>
    .tree-item-label {
      display: block
    }

    .v-treeview-node__root:hover {
      background: gainsboro;
      cursor: pointer;
    }

    .table-sm td,
    .table-sm th {
      font-size: small;
    }

    .iscut {
      color: #e56767;
    }

    .disabled-button-color {
      color: rgb(0 0 0 / 12%) !important;
    }

    .isactive {
      color: #fb8c00;
      background: #fb8c0021
    }

    .text-crop {
      max-width: 40em;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .additional-item {
      color: #6f42c1!important;
    }

    .v-icon.additional-item {
      color: #6f42c1!important;
    }

    .font-small{
      font-size:small;
    }
    
    .search-field {
      font-size: 0.875rem;
    }
    
    .search-field .v-input__control {
      min-height: 32px !important;
    }
    
    .prop-item {
      color: #6c757d;
      font-style: italic;
    }
    
    /* Form label styling */
    label.mb-1.d-block {
      font-weight: 500;
      color: rgba(0, 0, 0, 0.87);
      margin-bottom: 4px !important;
    }
    
    .v-application .v-container--fluid {
      max-width: 100% !important;
      padding-left: 0 !important;
      padding-right: 0 !important;
      width: 100% !important;
    }
    
    .v-application {
      width: 100% !important;
      max-width: 100% !important;
    }
    
    body, html {
      width: 100% !important;
      max-width: 100% !important;
      overflow-x: hidden;
    }
    
    #app {
      width: 100% !important;
      max-width: 100% !important;
    }

    .v-text-field__details{
      display:none !important;
    }
  </style>


</head>

<body>

  <?php

  $core_template = json_encode($core_template['template']);
  $core_template_arr = json_decode($core_template, true);

  $user_template = json_encode($user_template['template']);
  $user_template_arr = json_decode($user_template, true);

  //break template into smaller templates by spliting template ['items']
  $core_template_parts = array();
  $user_template_parts = array();

  //update template_parts
  get_template_part($core_template_arr['items'], null, $core_template_parts);
  //get_template_part($user_template_arr['items'], null, $user_template_parts);

  function get_template_part($items, $parent = null, &$output)
  {
    foreach ($items as $item) {
      if (isset($item['items'])) {
        $parent_ = isset($item['key']) ? $item['key'] : null;
        get_template_part($item['items'], $parent_, $output);
      }
      if (isset($item['key'])) {
        $item["parent"] = $parent;
        $output[$item['key']] = $item;
      }
    }
  }
  ?>

  <script>
    var CI = {
      'base_url': '<?php echo site_url(); ?>'
    };
    let user_template_info = <?php echo json_encode($user_template_info); ?>;
    let core_template = <?php echo $core_template; ?>;
    let core_template_parts = <?php echo json_encode($core_template_parts, JSON_PRETTY_PRINT); ?>;

    let user_template = <?php echo $user_template; ?>;
    let template_icon_url = <?php echo json_encode(isset($template_icon_url) ? $template_icon_url : null); ?>;
    let user_has_edit_access = <?php echo json_encode(isset($user_has_edit_access) ? $user_has_edit_access : false); ?>;
  </script>

  <div id="app" data-app>
    <v-app>

      <div class="pa-0">

        <v-row no-gutters class="sticky-top border-bottom bg-white">

          <v-col cols="12" md="3">
            <div class="color-white branding-icon" style="padding:5px;padding-left:30px;font-weight:bold;">
              <v-icon large color="#007bff">mdi-alpha-t-box</v-icon>
              {{$t('template_manager')}}
            </div>
          </v-col>

          <v-col cols="12" md="9">
            <!-- header -->
            <div class="header">
              <v-row>
                <v-col cols="12" md="9">

                  <div class="ml-5 pt-2">
                    <div class="text-crop">
                      <template v-if="template_icon_url">
                        <img :src="template_icon_url" 
                             style="width:20px;height:20px;vertical-align:middle;margin-right:8px;" 
                             :alt="user_template_info.data_type" />
                      </template>
                      <template v-else>
                        <i class="fa fa-file" style="margin-right:8px;"></i>
                      </template>
                      <strong style="font-size:large;">{{user_template_info.name}}</strong>
                    </div>
                  </div>
                </v-col>
                <v-col cols="12" md="3">
                  <div class="text-right pt-1 mr-5">
                    <v-btn v-if="isEditable" small color="success" @click="saveTemplate()" class="mr-2">
                      <v-icon left style="color:white;">mdi-content-save-check</v-icon> {{$t('save')}} <span v-if="is_dirty==true">*</span>
                    </v-btn>
                    <v-btn v-else small outlined disabled class="mr-2">
                      <v-icon left>mdi-lock</v-icon> {{$t('read_only')}}
                    </v-btn>
                    <v-btn small outlined @click="cancelTemplate()">
                      <v-icon left>mdi-exit-to-app</v-icon> {{$t('close')}}
                    </v-btn>
                  </div>
                </v-col>
              </v-row>

            </div>
            <!-- end header -->
          </v-col>


        </v-row>

        <v-row no-gutters style="height:100vh;">

          <v-col cols="12" md="3" style="height:100vh;">

            <v-row no-gutters class="border-right pt-2" style="height:100vh;overflow:auto;">
              <v-col cols="11" style="height:100vh;">
                <div class="px-3 pb-2 pt-2">
                  <v-text-field
                    v-model="treeSearchQuery"
                    prepend-inner-icon="mdi-magnify"
                    placeholder="Search fields..."
                    hide-details
                    dense
                    outlined
                    clearable
                    class="search-field"
                  ></v-text-field>
                </div>
                
                <nada-treeview 
                    v-model="filteredUserTreeItems" 
                    :cut_fields="cut_fields" 
                    :initially_open="initiallyOpen" 
                    :tree_active_items="tree_active_items"
                    @initially-open="updateInitiallyOpen"
                    ></nada-treeview>
              </v-col>
              <v-col cols="1" style="position:relative;padding-left:5px;" >
                <div class="pr-1" style="position:fixed;">

                  <div>
                    <v-icon v-if="ActiveCoreNode.type && user_has_edit_access" color="#3498db" @click="addField()">mdi-chevron-left-box</v-icon>
                    <v-icon v-else class="disabled-button-color">mdi-chevron-left-box</v-icon>
                  </div>
                  <div>
                    <v-icon v-if="ActiveNodeIsField && user_has_edit_access" color="#3498db" @click="removeField()">mdi-chevron-right-box</v-icon>
                    <v-icon v-else class="disabled-button-color">mdi-chevron-right-box</v-icon>
                  </div>

                  <div>
                    <v-icon v-if="ActiveNode && (ActiveNode.type=='section_container' || ActiveNode.type=='section') && user_has_edit_access" color="#3498db" @click="addSection()">mdi-plus-box</v-icon>
                    <v-icon v-else class="disabled-button-color">mdi-plus-box</v-icon>
                  </div>
                  <div>
                    <v-icon v-if="ActiveNode && ActiveNode.type=='section' && user_has_edit_access" color="#3498db" @click="removeField()">mdi-minus-box</v-icon>
                    <v-icon v-else class="disabled-button-color">mdi-minus-box</v-icon>
                  </div>
                  <div>
                    <v-icon v-if="ActiveNode && ActiveNode.type && ActiveNode.key && !ActiveNodeIsRoot && !ActiveNodeIsDescription && user_has_edit_access" color="#3498db" @click="moveUp()">mdi-arrow-up-bold-box</v-icon>
                    <v-icon v-else class="disabled-button-color">mdi-arrow-up-bold-box</v-icon>
                  </div>
                  <div>
                    <v-icon v-if="ActiveNode && ActiveNode.type && ActiveNode.key && !ActiveNodeIsRoot && !ActiveNodeIsDescription && user_has_edit_access" color="#3498db" @click="moveDown()">mdi-arrow-down-bold-box</v-icon>
                    <v-icon v-else class="disabled-button-color">mdi-arrow-down-bold-box</v-icon>
                  </div>


                  <div class="mt-5" title="Move">
                    <v-icon v-if="ActiveNodeIsField && user_has_edit_access" color="#3498db" @click="cutField()">mdi-content-copy</v-icon>
                    <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-content-copy</v-icon>
                  </div>

                  <div class="mt-2" title="Paste">
                    <v-icon v-if="ActiveNode && ActiveNode.type=='section' && cut_fields.length>0 && user_has_edit_access" color="#3498db" @click="pasteField()">mdi-content-paste</v-icon>
                    <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-content-paste</v-icon>
                  </div>

                  <!--additional (not allowed directly under section_container) -->
                  <div class="mt-5" v-if="(!ActiveNode || !ActiveNode.is_custom) && ((ActiveNode && (ActiveNode.type=='section' || ActiveNode.type=='array' || ActiveNode.type=='nested_array')) || TemplateIsAdminMeta || TemplateIsCustom)">
                    <v-icon title="Add custom field" v-if="ActiveNode && (ActiveNode.type=='section' || ActiveNode.type=='array' || ActiveNode.type=='nested_array') && user_has_edit_access" class="additional-item" @click="addAdditionalField()">mdi-text-box-plus-outline</v-icon>
                    <v-icon title="Add custom field" v-else class="disabled-button-color">mdi-text-box-plus-outline</v-icon>
                  </div>

                  <div class="mt-1" v-if="(!ActiveNode || !ActiveNode.is_custom) && ((ActiveNode && (ActiveNode.type=='section' || ActiveNode.type=='nested_array')) || (TemplateIsCustom && ActiveNode && ActiveNode.type!='array'))">
                    <v-icon title="Add custom Array field" v-if="ActiveNode && ActiveNode.type!='array' && (ActiveNode.type=='section' || ActiveNode.type=='nested_array') && user_has_edit_access" class="additional-item"  @click="addAdditionalFieldArray()">mdi-table-large-plus</v-icon>
                    <v-icon title="Add custom Array field" v-else class="disabled-button-color">mdi-table-large-plus</v-icon>
                  </div>

                  <div class="mt-1" v-if="(!ActiveNode || !ActiveNode.is_custom) && ((ActiveNode && (ActiveNode.type=='section' || ActiveNode.type=='nested_array')) || (TemplateIsCustom && ActiveNode && ActiveNode.type!='array'))">
                    <v-icon title="Add custom NestedArray field" v-if="ActiveNode && ActiveNode.type!='array' && (ActiveNode.type=='section' || ActiveNode.type=='nested_array') && user_has_edit_access" class="additional-item"  @click="addAdditionalFieldNestedArray()">mdi-file-tree</v-icon>
                    <v-icon title="Add custom NestedArray field" v-else class="disabled-button-color">mdi-file-tree</v-icon>
                  </div>

                </div>
              </v-col>
            </v-row>
          </v-col>


          <!--content section-->
          <v-col cols="12" md="9" class="bg-light" style="height:100vh;">



            <!-- content -->
            <div class="main-content-container p-3" style="height:100vh;overflow:auto;">

              <?php echo $this->load->view('template_manager/edit_content', null, true); ?>
            </div>

          </v-col>
          <!-- end content -->

          <!--end content section-->

        </v-row>

      </div>
    </v-app>
  </div>

  <script>
    //global js functions
    function getTreeKeys(tree_items, output) {
      tree_items.forEach(item => {
        if (item.items) {
          getTreeKeys(item.items, output);
        }
        if (item.key) {
          output.push(item.key);
        }
      });

      return Array.from(new Set(output));
    }

    // Rewrite template item-form prefixes to schema array names
    // e.g. variable.name → variables.name (aliases: {variable:'variables'})
    function resolveTemplateKeyToSchema(key, aliases) {
      if (!key || !aliases || typeof aliases !== 'object') {
        return key;
      }
      const parts = String(key).split('.');
      const mapped = aliases[parts[0]];
      if (!mapped) {
        return key;
      }
      parts[0] = mapped;
      return parts.join('.');
    }

    // Reverse: schema path → preferred template key for autocomplete
    function resolveSchemaKeyToTemplate(key, aliases) {
      if (!key || !aliases || typeof aliases !== 'object') {
        return key;
      }
      const reverse = {};
      Object.keys(aliases).forEach(function(templatePrefix) {
        reverse[aliases[templatePrefix]] = templatePrefix;
      });
      const parts = String(key).split('.');
      const mapped = reverse[parts[0]];
      if (!mapped) {
        return key;
      }
      parts[0] = mapped;
      return parts.join('.');
    }

    function isAcceptedSchemaKey(key, schemaKeys, aliases) {
      if (!key || !schemaKeys || !schemaKeys.length) {
        return false;
      }
      if (schemaKeys.indexOf(key) !== -1) {
        return true;
      }
      const resolved = resolveTemplateKeyToSchema(key, aliases);
      return resolved !== key && schemaKeys.indexOf(resolved) !== -1;
    }

    // Extension / free-form keys under additional (and nested paths like additional.kv.key)
    function isAdditionalTemplateKey(key) {
      if (!key) {
        return false;
      }
      const k = String(key);
      return k === 'additional' || k.indexOf('additional.') === 0;
    }

    // Custom/extension field nodes (including nested/array custom fields outside additional.*)
    function isExtensionTemplateNode(node) {
      if (!node || typeof node !== 'object') {
        return false;
      }
      return node.is_additional === true || node.is_additional === 1 || node.is_additional === '1';
    }

    <?php echo include_once("vue-field-key-component.js"); ?>
    <?php echo include_once("vue-field-custom-key-component.js"); ?>
    <?php echo include_once("vue-prop-key-component.js"); ?>
    <?php echo include_once("vue-tree-component.js"); ?>
    <?php echo include_once("vue-tree-field-component.js"); ?>
    <?php echo include_once("vue-table-grid-component.js"); ?>
    <?php echo include_once("vue-validation-rules-component.js"); ?>
    <?php echo include_once("vue-prop-edit-component.js"); ?>


    const translation_messages = {
      default: <?php echo json_encode($translations,JSON_HEX_APOS);?>
    }

    const i18n = new VueI18n({
      locale: 'default', // set locale
      messages: translation_messages, // set locale messages
    });

    Vue.mixin({
      methods: {
                
                copyToClipBoard: function(textToCopy){
                    const tmpTextField = document.createElement("textarea")
                    tmpTextField.textContent = textToCopy
                    tmpTextField.setAttribute("style","position:absolute; right:200%;")
                    document.body.appendChild(tmpTextField)
                    tmpTextField.select()
                    tmpTextField.setSelectionRange(0, 99999) /*For mobile devices*/
                    document.execCommand("copy")
                    tmpTextField.remove();
                },

                pasteFromClipBoard: async function() 
                {
                    const text = await navigator.clipboard.readText();
                    return text;                    
                },
                CSVToArray: function ( strData, strDelimiter )
                {
                    //source: https://gist.github.com/bennadel/9753411#file-code-1-htm
                    
                    // Check to see if the delimiter is defined. If not,
                    // then default to comma.
                    strDelimiter = (strDelimiter || ",");

                    // Create a regular expression to parse the CSV values.
                    var objPattern = new RegExp(
                        (
                            // Delimiters.
                            "(\\" + strDelimiter + "|\\r?\\n|\\r|^)" +

                            // Quoted fields.
                            "(?:\"([^\"]*(?:\"\"[^\"]*)*)\"|" +

                            // Standard fields.
                            "([^\"\\" + strDelimiter + "\\r\\n]*))"
                        ),
                        "gi"
                        );


                    // Create an array to hold our data. Give the array
                    // a default empty first row.
                    var arrData = [[]];

                    // Create an array to hold our individual pattern
                    // matching groups.
                    var arrMatches = null;


                    // Keep looping over the regular expression matches
                    // until we can no longer find a match.
                    while (arrMatches = objPattern.exec( strData )){

                        // Get the delimiter that was found.
                        var strMatchedDelimiter = arrMatches[ 1 ];

                        // Check to see if the given delimiter has a length
                        // (is not the start of string) and if it matches
                        // field delimiter. If id does not, then we know
                        // that this delimiter is a row delimiter.
                        if (
                            strMatchedDelimiter.length &&
                            strMatchedDelimiter !== strDelimiter
                            ){

                            // Since we have reached a new row of data,
                            // add an empty row to our data array.
                            arrData.push( [] );

                        }

                        var strMatchedValue;

                        // Now that we have our delimiter out of the way,
                        // let's check to see which kind of value we
                        // captured (quoted or unquoted).
                        if (arrMatches[ 2 ]){

                            // We found a quoted value. When we capture
                            // this value, unescape any double quotes.
                            strMatchedValue = arrMatches[ 2 ].replace(
                                new RegExp( "\"\"", "g" ),
                                "\""
                                );

                        } else {

                            // We found a non-quoted value.
                            strMatchedValue = arrMatches[ 3 ];

                        }


                        // Now that we have our value string, let's add
                        // it to the data array.
                        arrData[ arrData.length - 1 ].push( strMatchedValue );
                    }

                    // Return the parsed data.
                    return( arrData );
                }
            }
    })

    const store = new Vuex.Store({
      state: {
        active_node: {},
        active_core_node: {},

        //templates
        core_template: core_template,
        user_template: user_template,

        //template items uses core_template and user_template
        core_tree_items: [],
        user_tree_items: [],

        //template parts by key
        core_template_parts: core_template_parts,
        //user_template_parts: user_template_parts,

        //keys only
        core_tree_keys: [], //default system template keys
        user_tree_keys: [], //custom user defined template keys

        user_template_info: user_template_info,

        // schema field paths for key validation / autocomplete (dotted keys)
        schema_field_keys: [],
        schema_fields: [],
        schema_key_aliases: {},
        schema_fields_loaded: false,
        schema_fields_error: null

      },
      mutations: {
        activeNode(state, node) {
          state.active_node = node;
        },
        activeCoreNode(state, node) {
          state.active_core_node = node;
        },
        setSchemaFields(state, payload) {
          state.schema_field_keys = payload.keys || [];
          state.schema_fields = payload.fields || [];
          state.schema_key_aliases = payload.key_aliases || {};
          state.schema_fields_loaded = true;
          state.schema_fields_error = payload.error || null;
        }
      },
      getters: {
        getActiveNode(state) {
          return state.active_node;
        },
        getUnusedFields(state) {
          return _.difference(state.core_tree_keys, state.user_tree_keys);
        },
        getCoreTreeKeys: function(state) {
          let items = [];
          items = getTreeKeys(state.core_tree_items, items);
          return items;
        },
        getUserTreeKeys: function(state) {
          let items = [];
          items = getTreeKeys(state.user_tree_items, items);
          return items;
        },
        getSchemaFieldKeys: function(state) {
          return state.schema_field_keys;
        },
        getSchemaKeyAliases: function(state) {
          return state.schema_key_aliases || {};
        },
        getUnusedSchemaFieldKeys: function(state) {
          let used = [];
          used = getTreeKeys(state.user_tree_items, used);
          // Normalize used keys to schema form so alias prefixes match
          const aliases = state.schema_key_aliases || {};
          const usedResolved = used.map(function(k) {
            return resolveTemplateKeyToSchema(k, aliases);
          });
          const unusedSchema = _.difference(state.schema_field_keys, usedResolved);
          // Prefer template-convention prefixes in autocomplete (variable.* not variables.*)
          return unusedSchema.map(function(k) {
            return resolveSchemaKeyToTemplate(k, aliases);
          });
        }

      },
      actions: {

        get_tree_keys({
          commit
        }, tree_items, output) {
          tree_items.forEach(item => {
            if (item.items) {
              this.get_tree_keys({
                commit
              }, item.items, output);
            }
            if (item.key) {
              output.push(item.key);
            }
          });

          return Array.from(new Set(output));
        }
      }
    })

    new Vue({
      el: "#app",
      i18n,
      store,
      vuetify: new Vuetify(),
      data() {
        return {
          user_template_info: user_template_info,
          initiallyOpen: ['template_root'],
          tree_active_items: [],
          is_dirty: false,
          deep_link_ready: false,
          treeSearchQuery: '',
          user_has_edit_access: user_has_edit_access,
          files: {
            html: 'mdi-language-html5',
            js: 'mdi-nodejs',
            json: 'mdi-code-json',
            md: 'mdi-language-markdown',
            pdf: 'mdi-file-pdf',
            png: 'mdi-file-image',
            txt: 'mdi-file-document-outline',
            xls: 'mdi-file-excel',
          },
          tree: [],

          tab: '',
          field_data_types: [
            "string",
            "number",
            "integer",
            "boolean"
          ],
          field_display_types: [
            "text",
            "textarea",
            "date",
            "dropdown",
            "dropdown-custom"
          ],
          field_content_formats: {
            "text": "Text",
            "html": "HTML",
            "markdown": "Markdown",
            "latex": "LaTeX",
            "json": "JSON"
          },
          
          field_types: [
            "string",
            "number",
            "date",
            "boolean",
            "array",
            "nested_array",
            "simple_array"
            /*"number",
            "date",
            "boolean"*/
          ],
          cut_fields: [],
          template_icon_url: template_icon_url,
          enum_store_options:[
            {
              "value":"both",
              "label":"Label with code"
            },
            {
              "value":"code",
              "label":"Code"
            },
            {
              "value":"label",
              "label":"Label"
            }            
          ]          
        }
      },
      created: function() {
        this.init_template();
        this.init_tree();
        this.loadSchemaFields();
        // Reset dirty state after initialization to prevent false positives
        this.$nextTick(() => {
          this.is_dirty = false;
          this.applyDeepLinkFromUrl();
          this.deep_link_ready = true;
        });
        let vm=this;
        window.addEventListener('beforeunload', function(event) {
          return vm.onWindowUnload(event);
        });
        window.addEventListener('popstate', function() {
          if (!vm.deep_link_ready) {
            return;
          }
          vm.applyDeepLinkFromUrl();
        });
      },
      methods: {

        getDeepLinkKeyFromUrl: function() {
          try {
            const params = new URLSearchParams(window.location.search || '');
            const queryKey = params.get('key');
            if (queryKey) {
              return queryKey;
            }
          } catch (e) {}

          const hash = (window.location.hash || '').replace(/^#/, '');
          if (!hash) {
            return null;
          }
          if (hash.indexOf('key=') === 0) {
            try {
              return decodeURIComponent(hash.substring(4));
            } catch (e) {
              return hash.substring(4);
            }
          }
          // Bare hash path, e.g. #metadata_information.title
          if (hash.indexOf('=') === -1 && hash !== 'template_root' && hash !== 'template_description') {
            try {
              return decodeURIComponent(hash);
            } catch (e) {
              return hash;
            }
          }
          return null;
        },
        setDeepLinkKeyInUrl: function(key) {
          try {
            const url = new URL(window.location.href);
            if (!key || key === 'template_root' || key === 'template_description') {
              url.searchParams.delete('key');
            } else {
              url.searchParams.set('key', key);
            }

            // Prefer query param; clear deep-link-style hashes
            const hash = (url.hash || '').replace(/^#/, '');
            if (hash && (hash.indexOf('key=') === 0 || (hash.indexOf('=') === -1 && hash.indexOf('.') !== -1))) {
              url.hash = '';
            }

            const next = url.pathname + url.search + url.hash;
            const current = window.location.pathname + window.location.search + window.location.hash;
            if (next !== current) {
              history.replaceState(history.state, '', next);
            }
          } catch (e) {}
        },
        getActiveNodeDeepLinkKey: function(node) {
          if (!node) {
            return null;
          }
          if (node.type === 'template_root' || node.type === 'template_description') {
            return null;
          }
          // Prefer absolute prop_key for array props (avoids relative key collisions)
          if (node.prop_key && (node.isProp || node.is_prop)) {
            return node.prop_key;
          }
          return node.key || node.prop_key || null;
        },
        applyDeepLinkFromUrl: function() {
          const key = this.getDeepLinkKeyFromUrl();
          if (!key) {
            return false;
          }
          return this.selectTemplateNodeByKey(key);
        },

        loadSchemaFields: function(){
          const dataType = this.user_template_info && this.user_template_info.data_type
            ? this.user_template_info.data_type
            : null;

          if (!dataType || dataType === 'custom') {
            store.commit('setSchemaFields', { keys: [], fields: [], key_aliases: {} });
            return;
          }

          const url = CI.base_url + '/api/schemas/fields/' + encodeURIComponent(dataType) + '?format=dotted';
          axios.get(url)
            .then(response => {
              const data = response.data || {};
              store.commit('setSchemaFields', {
                keys: data.keys || [],
                fields: data.fields || [],
                key_aliases: data.template_key_aliases || data.key_aliases || {}
              });
            })
            .catch(error => {
              const message = (error.response && error.response.data && error.response.data.message)
                ? error.response.data.message
                : (error.message || 'Failed to load schema fields');
              store.commit('setSchemaFields', {
                keys: [],
                fields: [],
                key_aliases: {},
                error: message
              });
            });
        },
        
        onWindowUnload: function(event){
          if (!this.is_dirty){
            return null;
          }

          let message=this.$t('unsaved_changes');

          event.returnValue = message;
          return message;
        },
        formatDate: function(timestamp) {
          if (!timestamp) return '';
          // Handle Unix timestamp (seconds) - convert to milliseconds if needed
          const date = new Date(timestamp * 1000);
          // Check if date is valid
          if (isNaN(date.getTime())) return '';
          // Format as: YYYY-MM-DD HH:MM:SS
          const year = date.getFullYear();
          const month = String(date.getMonth() + 1).padStart(2, '0');
          const day = String(date.getDate()).padStart(2, '0');
          const hours = String(date.getHours()).padStart(2, '0');
          const minutes = String(date.getMinutes()).padStart(2, '0');
          const seconds = String(date.getSeconds()).padStart(2, '0');
          return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        },
        init_template: function(){
          //check if user template includes additional container and add if not

          let user_template = this.$store.state.user_template;

          //search for additional_container
          let additional_container = user_template.items.find(item => item.key == 'additional_container');

          if (!additional_container) {
            user_template.items.push({
              "key": "additional_container",
              "title": "Additional Fields",
              "type": "section_container",
              "items": []
            });
          }

        },
        init_tree: function() {
          this.$store.state.core_tree_items = this.$store.state.core_template.items;
          this.$store.state.user_tree_items = this.$store.state.user_template.items;
          
          // Set root node as active by default
          this.$nextTick(() => {
            const rootNode = {
              key: 'template_root',
              title: this.TemplateDataType ? (this.TemplateDataType.charAt(0).toUpperCase() + this.TemplateDataType.slice(1).replace(/-/g, ' ')) : 'Template',
              type: 'template_root',
              isVirtual: true,
              items: []
            };
            store.commit('activeNode', rootNode);
            this.tree_active_items = ['template_root'];
            this.initiallyOpen = ['template_root'];
          });
        },
        updateInitiallyOpen: function (e){
          this.initiallyOpen=e;
        },
        delete_tree_item: function(tree, item_key) {
          tree.forEach((item, idx) => {
            if (item.items) {
              this.delete_tree_item(item.items, item_key);
            }
            // Also check props for array/nested_array nodes
            if ((item.type === 'array' || item.type === 'nested_array') && item.props) {
              item.props.forEach((prop, propIdx) => {
                const propKey = prop.prop_key || prop.key;
                if (propKey === item_key) {
                  Vue.delete(item.props, propIdx);
                }
                // Recursively check nested props
                if (prop.props) {
                  this.delete_tree_item([{ items: prop.props }], item_key);
                }
              });
            }
            if (item.key == item_key || (item.prop_key && item.prop_key == item_key)) {
              Vue.delete(tree, idx);
            }
          });
        },
        EnumUpdate: function(e) {
          if (!this.ActiveNode.enum) {
            this.$set(this.ActiveNode, "enum", [{}]);
          }
        },
        EnumListUpdate: function(e) {
          if (!this.ActiveNode.enum) {
            this.$set(this.ActiveNode, "enum", []);
          }
        },
        DefaultUpdate: function(e) {
          if (!this.ActiveNode.default) {
            this.$set(this.ActiveNode, "default", [{}]);
          }
        },
        RulesUpdate: function(e) {
          this.$set(this.ActiveNode, "rules", e);
        },
        removeField: function() {
          const nodeKey = this.ActiveNode.key || this.ActiveNode.prop_key;
          if (!nodeKey) return;
          
          // Use ActiveNodeIsProp to check if this is actually a prop (in an array's props array)
          // Just having prop_key doesn't mean it's a prop - it could be a regular field with prop_key set
          if (this.ActiveNodeIsProp) {
            const propKeyToDelete = this.ActiveNode.prop_key || nodeKey;
            const activeNodeKey = this.ActiveNode.key;
            
            // Recursive function to find and delete prop from props arrays
            const deletePropFromProps = (propsArray, vm) => {
              if (!propsArray || !Array.isArray(propsArray)) return false;
              
              // Try to find the prop by multiple methods
              let propIndex = -1;
              
              // First, try direct object reference (most reliable)
              propIndex = propsArray.findIndex(p => p === vm.ActiveNode);
              
              // If not found, try by prop_key
              if (propIndex === -1 && propKeyToDelete) {
                propIndex = propsArray.findIndex(p => {
                  const pKey = p.prop_key || p.key;
                  return pKey === propKeyToDelete;
                });
              }
              
              // If still not found, try by key
              if (propIndex === -1 && activeNodeKey) {
                propIndex = propsArray.findIndex(p => {
                  const pKey = p.key;
                  return pKey === activeNodeKey;
                });
              }
              
              // If still not found, try by matching both key and prop_key parts
              if (propIndex === -1 && propKeyToDelete && propKeyToDelete.includes('.')) {
                const keyPart = propKeyToDelete.split('.').pop();
                propIndex = propsArray.findIndex(p => {
                  return (p.key === keyPart) || (p.key === activeNodeKey);
                });
              }
              
              if (propIndex !== -1) {
                Vue.delete(propsArray, propIndex);
                vm.ActiveNode = {};
                vm.tree_active_items = [];
                return true;
              }
              
              // Recursively check nested props
              for (let prop of propsArray) {
                if (prop.props && Array.isArray(prop.props)) {
                  if (deletePropFromProps(prop.props, vm)) {
                    return true;
                  }
                }
              }
              
              return false;
            };
            
            // Recursive function to search through tree items
            const searchAndDelete = (items, vm) => {
              if (!items || !Array.isArray(items)) return false;
              
              for (let item of items) {
                // Check if this item is an array/nested_array with props
                if ((item.type === 'array' || item.type === 'nested_array') && item.props && Array.isArray(item.props)) {
                  if (deletePropFromProps(item.props, vm)) {
                    return true;
                  }
                }
                
                // Continue searching in items
                if (item.items && Array.isArray(item.items)) {
                  if (searchAndDelete(item.items, vm)) {
                    return true;
                  }
                }
              }
              
              return false;
            };
            
            // Start search with reference to this
            const deleted = searchAndDelete(this.UserTreeItems, this);
            
            // If prop deletion failed, fall back to regular deletion
            // This handles cases where a field has prop_key but isn't actually in a props array
            if (!deleted) {
              this.delete_tree_item(this.UserTreeItems, nodeKey);
              this.ActiveNode = {};
              this.tree_active_items = [];
            }
          } else {
            // Regular field deletion
            this.delete_tree_item(this.UserTreeItems, nodeKey);
            this.ActiveNode = {};
            this.tree_active_items = [];
          }
        },
        // Find parent array node that contains a prop
        findParentArrayNode: function(tree, propKey) {
          if (!tree || !Array.isArray(tree)) {
            return null;
          }
          
          for (let item of tree) {
            // Check if this item is an array/nested_array with props
            if ((item.type === 'array' || item.type === 'nested_array') && item.props && Array.isArray(item.props)) {
              // Check if prop is directly in this array's props
              const found = item.props.find(p => {
                const pKey = p.prop_key || p.key;
                return pKey === propKey;
              });
              if (found) {
                return item;
              }
              
              // Check nested props recursively
              for (let prop of item.props) {
                if (prop.props && Array.isArray(prop.props)) {
                  const nestedResult = this.findParentArrayNode(prop.props.map(p => ({ 
                    type: 'nested_array', 
                    props: [p] 
                  })), propKey);
                  if (nestedResult) {
                    // Return the parent array that contains this nested prop
                    return item;
                  }
                }
              }
            }
            
            // Recursively search in items
            if (item.items && Array.isArray(item.items)) {
              const result = this.findParentArrayNode(item.items, propKey);
              if (result) return result;
            }
          }
          return null;
        },
        cutField: function() {
          const nodeKey = this.ActiveNode.key || this.ActiveNode.prop_key;
          if (!nodeKey) return;
          
          let active_container_key = this.getNodeContainerKey(this.UserTreeItems, nodeKey);

          //unselect cut field
          for (i = 0; i < this.cut_fields.length; i++) {
            if (active_container_key == this.cut_fields[i].container) {
              const cutNodeKey = this.cut_fields[i].node.key || this.cut_fields[i].node.prop_key;
              if (cutNodeKey == nodeKey) {
                this.cut_fields.splice(i, 1);
                return;
              }
            }
          }

          this.cut_fields.push({
            "node": this.ActiveNode,
            "container": active_container_key,
            "isProp": !!this.ActiveNode.prop_key
          });
          //this.removeField();
        },
        pasteField: function() {
          if (this.cut_fields.length < 1) {
            return false;
          }

          if (this.isItemContainer(this.ActiveCoreNode)) {
            return false;
          }

          const activeNodeKey = this.ActiveNode.key || this.ActiveNode.prop_key;
          let active_container_key = this.getNodeContainerKey(this.UserTreeItems, activeNodeKey);

          for (i = 0; i < this.cut_fields.length; i++) {
            if (active_container_key == this.cut_fields[i].container) {
              const cutNodeKey = this.cut_fields[i].node.key || this.cut_fields[i].node.prop_key;
              
              //remove existing item
              this.delete_tree_item(this.UserTreeItems, cutNodeKey);
              
              // If pasting into an array/nested_array and the cut item is a prop, add to props
              if ((this.ActiveNode.type === 'array' || this.ActiveNode.type === 'nested_array') && this.cut_fields[i].isProp) {
                if (!this.ActiveNode.props) {
                  this.$set(this.ActiveNode, "props", []);
                }
                this.ActiveNode.props.push(this.cut_fields[i].node);
              } else {
                // Regular paste to items
                if (!this.ActiveNode.items) {
                  this.$set(this.ActiveNode, "items", []);
                }
                this.ActiveNode.items.push(this.cut_fields[i].node);
              }
            }
          }

          this.cut_fields = [];
          store.commit('activeCoreNode', {});
        },
        //check if an item is selected for cut/paste        
        isItemCut: function(item) {
          const itemKey = item.key || item.prop_key;
          if (!itemKey) return false;
          
          let active_container_key = this.getNodeContainerKey(this.UserTreeItems, itemKey);

          for (i = 0; i < this.cut_fields.length; i++) {
            if (active_container_key == this.cut_fields[i].container) {
              const cutNodeKey = this.cut_fields[i].node.key || this.cut_fields[i].node.prop_key;
              if (itemKey == cutNodeKey) {
                return true;
              }
            }
          }
          return false;
        },
        isItemContainer: function(item) {
          if (item.type == 'section' || item.type == 'section_container' || item.type == 'nested_array_' || item.type == 'template_root' || item.type == 'template_description') {
            return true;
          }
          return false;
        },
        isControlField: function(field_type) {
          let field_types = ["text", "string", "integer", "textarea", "dropdown", "date", "boolean", "simple_array"];
          return field_types.includes(field_type);
        },
        addField: function() {
          if (!this.ActiveCoreNode.key) {
            return false;
          }

          if (this.isItemContainer(this.ActiveCoreNode)) {
            return false;
          }

          if (this.isItemInUse(this.ActiveCoreNode.key)) {
            return false;
          }

          if (this.checkNodeKeyExists(this.ActiveNode, this.ActiveCoreNode.key) == true) {
            return false;
          }

          if (!this.ActiveNode.items) {
            this.$set(this.ActiveNode, "items", []);
          }

          this.ActiveNode.items.push(this.ActiveCoreNode);
          store.commit('activeCoreNode', {});
        },
        addSection: function() {
          if (!this.ActiveNode.key == 'section_container') {
            return false;
          }

          let parentNode = this.ActiveNode;
          let new_node_key = parentNode.key + Date.now();
          let new_node={
            "key": new_node_key,
            "title": "Untitled",
            "type": "section",
            "items": [],
            "help_text": ""
          };

          this.$set(parentNode, "items", [
            ...parentNode.items,
            new_node
          ]);
          

          this.ActiveNode = parentNode.items[parentNode.items.length - 1];
          
          // Use $nextTick to wait for Vue to finish updating the tree before activating the new item
          this.$nextTick(() => {
            this.tree_active_items = new Array();
            this.tree_active_items.push(new_node_key);
            this.initiallyOpen.push(new_node_key);
            this.initiallyOpen.push(parentNode.key);
          });
        },
        addSectionContainer: function(container) {
          if (!container || !container.key) {
            return false;
          }
          
          // Check if already in use
          if (this.isItemInUse(container.key)) {
            return false;
          }
          
          // Ensure UserTemplate.items exists
          if (!this.UserTemplate.items) {
            this.$set(this.UserTemplate, "items", []);
          }
          
          // Check if container already exists
          const exists = this.UserTemplate.items.some(item => 
            item && item.key === container.key
          );
          
          if (exists) {
            return false;
          }
          
          // Clone the container to avoid reference issues
          const containerToAdd = JSON.parse(JSON.stringify(container));
          
          // Add to root items array
          this.UserTemplate.items.push(containerToAdd);
          
          // Update tree items to reflect the change
          this.$store.state.user_tree_items = this.UserTemplate.items;
          
          // Activate the newly added container (not the root)
          this.ActiveNode = containerToAdd;
          
          // Use $nextTick to wait for Vue to finish updating the tree
          this.$nextTick(() => {
            this.tree_active_items = new Array();
            this.tree_active_items.push(container.key);
            this.initiallyOpen.push(container.key);
            // Keep root open
            if (this.initiallyOpen.indexOf('template_root') === -1) {
              this.initiallyOpen.push('template_root');
            }
          });
          
          this.markDirty();
          return true;
        },
        buildPathPrefix: function(parentNode){
          if (!parentNode) return null;
          const parentKey = parentNode.prop_key || parentNode.key;
          if (!parentKey) return null;
          const segments = this.getNodePathSegments(this.UserTreeItems, parentKey);
          if (!segments || segments.length === 0) return null;
          // Always include container key (first segment); exclude section/section_container from the rest
          const containerKey = segments[0].key;
          const restSegments = segments.slice(1).filter(function(s){ return s.type !== 'section' && s.type !== 'section_container'; });
          const pathParts = [containerKey].concat(restSegments.map(function(s){ return s.key; }));
          return pathParts.join('.');
        },
        generateNewFieldKey: function() {
          const timestamp = Date.now();

          // Keep admin_meta behavior unchanged
          if (this.TemplateDataType=='admin_meta'){
            return "options." + timestamp;
          }

          const pathPrefix = this.buildPathPrefix(this.ActiveNode);

          // If current container is the Additional section, keep the additional.* convention
          if (this.ActiveNodeContainerKey === 'additional_container'){
            const baseKey = "additional.ns:field_" + timestamp;
            return pathPrefix ? `${pathPrefix}.ns:field_${timestamp}` : baseKey;
          }

          const baseKey = `ns:field_${timestamp}`;
          return pathPrefix ? `${pathPrefix}.${baseKey}` : baseKey;
        },
        addAdditionalField: function() {
          console.log("addAdditionalField");
          let parentNode = this.ActiveNode;
          if (!parentNode) {
            return false;
          }
          // Do not allow adding fields directly under section_container
          if (parentNode.type === 'section_container') {
            return false;
          }
          const new_node_key = this.generateNewFieldKey();

          console.log("new_node_key", new_node_key);
          console.log("parentNode", parentNode);

          // If parent is array/nested_array, add as prop; otherwise add as child item
          if (parentNode.type === 'array' || parentNode.type === 'nested_array') {
            if (!parentNode.props) {
              this.$set(parentNode, "props", []);
            }
            const propKey = new_node_key;
            const propKeyShort = propKey.split('.').pop();
            parentNode.props.push({
              "key": propKeyShort,
              "prop_key": propKey,
              "title": "Untitled",
              "type": "string",
              "help_text": "",
              "display_type": "text",
              "is_additional": true
            });
            this.ActiveNode = parentNode.props[parentNode.props.length - 1];
          } else {
            if (!parentNode.items) {
              this.$set(parentNode, "items", []);
            }

            parentNode.items.push({
              "key": new_node_key,
              "title": "Untitled",
              "type": "string",            
              "help_text": "",
              "display_type": "text",
              "is_additional": true
            });
            this.ActiveNode = parentNode.items[parentNode.items.length - 1];
          }

          console.log("ActiveNode", this.ActiveNode);

          // Use $nextTick to wait for Vue to finish updating the tree before activating the new item
          this.$nextTick(() => {
            this.tree_active_items = new Array();
            this.tree_active_items.push(new_node_key);

            console.log("tree_active_items", this.tree_active_items);

            this.initiallyOpen.push(new_node_key);
            this.initiallyOpen.push(parentNode.key);

            console.log("initiallyOpen DONE", this.initiallyOpen);
          });

          this.markDirty();
        },
        addAdditionalFieldArray: function() {
          let parentNode = this.ActiveNode;
          if (!parentNode) {
            return false;
          }
          // Do not allow adding directly under section_container
          if (parentNode.type === 'section_container') {
            return false;
          }
          const new_node_key = this.generateNewFieldKey();

          // Do not allow adding array props inside a plain array node (only simple fields allowed)
          if (parentNode.type === 'array') {
            return false;
          }

          const newArrayNode = {
            "key": new_node_key,
            "title": "Untitled",
            "type": "array",
            "help_text": "",
            "is_additional": true,
            "props": [                           
            ]
          };

          if (parentNode.type === 'array' || parentNode.type === 'nested_array') {
            if (!parentNode.props) {
              this.$set(parentNode, "props", []);
            }
            newArrayNode.prop_key = new_node_key;
            newArrayNode.key = new_node_key.split('.').pop();
            parentNode.props.push(newArrayNode);
            this.ActiveNode = parentNode.props[parentNode.props.length - 1];
          } else {
            if (!parentNode.items) {
              this.$set(parentNode, "items", []);
            }
            parentNode.items.push(newArrayNode);
            this.ActiveNode = parentNode.items[parentNode.items.length - 1];
          }
          
          // Use $nextTick to wait for Vue to finish updating the tree before activating the new item
          this.$nextTick(() => {
            this.tree_active_items = new Array();
            this.tree_active_items.push(new_node_key);
            this.initiallyOpen.push(new_node_key);
            this.initiallyOpen.push(parentNode.key);
          });

          this.markDirty();

          //this.ActiveNode.items.push(this.ActiveCoreNode);
          //store.commit('activeCoreNode', {});
        },
        addAdditionalFieldNestedArray: function() {
          let parentNode = this.ActiveNode;
          if (!parentNode) {
            return false;
          }
          // Do not allow adding directly under section_container
          if (parentNode.type === 'section_container') {
            return false;
          }
          const new_node_key = this.generateNewFieldKey();

          // Do not allow adding nested_array props inside a plain array node (only simple fields allowed)
          if (parentNode.type === 'array') {
            return false;
          }

          const newNestedNode = {
            "key": new_node_key,
            "title": "Untitled",
            "type": "nested_array",            
            "help_text": "",
            "is_additional": true,
            "props": [                           
            ]
          };

          if (parentNode.type === 'array' || parentNode.type === 'nested_array') {
            if (!parentNode.props) {
              this.$set(parentNode, "props", []);
            }
            newNestedNode.prop_key = new_node_key;
            newNestedNode.key = new_node_key.split('.').pop();
            parentNode.props.push(newNestedNode);
            this.ActiveNode = parentNode.props[parentNode.props.length - 1];
          } else {
            if (!parentNode.items) {
              this.$set(parentNode, "items", []);
            }
            parentNode.items.push(newNestedNode);
            this.ActiveNode = parentNode.items[parentNode.items.length - 1];
          }
          
          // Use $nextTick to wait for Vue to finish updating the tree before activating the new item
          this.$nextTick(() => {
            this.tree_active_items = new Array();
            this.tree_active_items.push(new_node_key);
            this.initiallyOpen.push(new_node_key);
            this.initiallyOpen.push(parentNode.key);
          });

          this.markDirty();
        },
        UpdateActiveNodeKey: function(e){
          console.log("UpdateActiveNodeKey START...........");
          console.log("UpdateActiveNodeKey", e);
          const oldKey = this.ActiveNode.key || this.ActiveNode.prop_key;
          this.ActiveNode.key = e;
          // Keep tree selection in sync with key changes to avoid UI errors
          if (Array.isArray(this.tree_active_items)) {
            const idx = this.tree_active_items.indexOf(oldKey);
            if (idx !== -1) {
              this.$set(this.tree_active_items, idx, e);
            }
          }
          if (Array.isArray(this.initiallyOpen) && this.initiallyOpen.indexOf(e) === -1) {
            this.initiallyOpen.push(e);
          }
          console.log("ActiveNode", this.ActiveNode);
          console.log("markDirty");
          this.markDirty();
          console.log("markDirty DONE");
        },
        markDirty: function() {
          // Explicitly mark template as dirty when any field is modified
          this.is_dirty = true;
        },
        getNodeProps: function(node) {

          if (!node) {
            return [];
          }

          if (node.props) {
            return node.props;
          }

          return [];
        },
        // Find parent node that contains a prop
        findPropParentNode: function(propKey) {
          // Search through user template to find the array/nested_array that contains this prop
          const findInTree = (items) => {
            for (let item of items) {
              if ((item.type === 'array' || item.type === 'nested_array') && item.props) {
                // Check if prop is directly in this array's props
                const found = item.props.find(p => {
                  const pKey = p.prop_key || p.key;
                  return pKey === propKey;
                });
                if (found) {
                  return item;
                }
                // Check nested props - if a prop is itself an array/nested_array, check its props
                for (let prop of item.props) {
                  if ((prop.type === 'array' || prop.type === 'nested_array') && prop.props && Array.isArray(prop.props)) {
                    // Check if the target prop is in this prop's props array
                    const nestedFound = prop.props.find(p => {
                      const pKey = p.prop_key || p.key;
                      return pKey === propKey;
                    });
                    if (nestedFound) {
                      // Return the prop (array) that contains the target prop
                      return prop;
                    }
                    // Recursively check deeper nested props
                    const deeperResult = findInTree([{ type: prop.type, props: prop.props }]);
                    if (deeperResult) return deeperResult;
                  }
                }
              }
              if (item.items) {
                const result = findInTree(item.items);
                if (result) return result;
              }
            }
            return null;
          };
          
          return findInTree(this.UserTreeItems);
        },
        moveUp: function() {
          parentNode = this.findNodeParent(this.UserTemplate, this.ActiveNode.key);
          nodeIdx = this.findNodePosition(parentNode, this.ActiveNode.key);
          if (nodeIdx > 0) {
            this.array_move(parentNode.items, nodeIdx, nodeIdx - 1);
          }
        },
        moveDown: function() {
          parentNode = this.findNodeParent(this.UserTemplate, this.ActiveNode.key);
          nodeIdx = this.findNodePosition(parentNode, this.ActiveNode.key);

          parentNodeItemsCount = parentNode.items.length - 1;

          if (nodeIdx > -1 && nodeIdx < parentNodeItemsCount) {
            this.array_move(parentNode.items, nodeIdx, nodeIdx + 1);
          }
        },
        array_move: function(arr, old_index, new_index) {
          if (new_index >= arr.length) {
            var k = new_index - arr.length + 1;
            while (k--) {
              arr.push(undefined);
            }
          }
          arr.splice(new_index, 0, arr.splice(old_index, 1)[0]);
        },
        findNodePosition: function(node, key) {
          if (!node.items) {
            return false;
          }

          for (index = 0; index < node.items.length; index++) {
            let item = node.items[index];
            if (item.key && item.key == key) {
              return index;
            }
          }

          return -1;
        },
        isItemInUse: function(item_key) {
          // Virtual nodes (root and description) are never "in use" in the traditional sense
          if (item_key === 'template_root' || item_key === 'template_description') {
            return false;
          }
          return _.includes(this.UserTreeUsedKeys, item_key);
        },
        checkNodeKeyExists: function(node, key) {
          let exists = false;
          node.items.forEach(item => {
            if (item.key) {
              if (item.key == key) {
                exists = true;
              }
            }
          });

          return exists;
        },
        findNodeParent: function(tree, node_key) {
          found = '';
          for (var i = 0; i < tree.items.length; i++) {
            let item = tree.items[i];
            if (item.key && item.key == node_key) {
              found = tree;
              return tree;
            }

            if (item.items) {
              result = this.findNodeParent(item, node_key);
              if (result != '') {
                return result;
              }
            }
          }
          return found;
        },
        findTemplateNodeByKey: function(items, targetKey, ancestors) {
          if (!items || !Array.isArray(items) || !targetKey) {
            return null;
          }
          ancestors = ancestors || [];

          for (let i = 0; i < items.length; i++) {
            const item = items[i];
            const itemKey = item.key || item.prop_key;
            if (itemKey === targetKey) {
              return {
                node: item,
                tree_key: itemKey,
                parent_key: ancestors.length ? ancestors[ancestors.length - 1] : null,
                ancestor_keys: ancestors.slice(),
                is_prop: false
              };
            }

            if ((item.type === 'array' || item.type === 'nested_array') && item.props && Array.isArray(item.props)) {
              for (let p = 0; p < item.props.length; p++) {
                const prop = item.props[p];
                const propKey = prop.prop_key || (item.key && prop.key ? (item.key + '.' + prop.key) : prop.key);
                if (propKey === targetKey) {
                  return {
                    node: prop,
                    tree_key: prop.key || propKey,
                    parent_key: item.key,
                    ancestor_keys: ancestors.concat(item.key ? [item.key] : []),
                    is_prop: true
                  };
                }
              }
            }

            if (item.items) {
              const nextAncestors = itemKey ? ancestors.concat([itemKey]) : ancestors;
              const nested = this.findTemplateNodeByKey(item.items, targetKey, nextAncestors);
              if (nested) {
                return nested;
              }
            }
          }

          return null;
        },
        selectTemplateNodeByKey: function(selectKey) {
          if (!selectKey) {
            return false;
          }

          const found = this.findTemplateNodeByKey(this.UserTreeItems, selectKey);
          if (!found || !found.node) {
            return false;
          }

          if (found.is_prop) {
            found.node.isProp = true;
          }

          store.commit('activeNode', found.node);
          this.tree_active_items = [found.tree_key];

          if (!Array.isArray(this.initiallyOpen)) {
            this.initiallyOpen = [];
          }
          // Ensure root + all ancestors are expanded so the node is visible
          ['template_root'].concat(found.ancestor_keys || []).concat([found.parent_key, found.tree_key]).forEach((k) => {
            if (k && this.initiallyOpen.indexOf(k) === -1) {
              this.initiallyOpen.push(k);
            }
          });

          this.setDeepLinkKeyInUrl(
            found.is_prop
              ? (found.node.prop_key || selectKey)
              : (found.node.key || selectKey)
          );

          return true;
        },
        collectInvalidTemplateKeys: function(items, issues, seenKeys) {
          if (!items || !Array.isArray(items)) {
            return;
          }

          const structural = {
            section: true,
            section_container: true,
            template_root: true,
            template_description: true
          };
          const schemaKeys = this.$store.state.schema_field_keys || [];
          const keyAliases = this.$store.state.schema_key_aliases || {};
          const schemaLoaded = this.$store.state.schema_fields_loaded;
          const isCustomType = this.user_template_info && this.user_template_info.data_type === 'custom';
          const checkSchema = schemaLoaded && schemaKeys.length > 0 && !isCustomType;

          for (let i = 0; i < items.length; i++) {
            const item = items[i];
            if (!item || !item.key) {
              continue;
            }

            const key = item.key;
            const isStructural = !!(item.type && structural[item.type]);
            const errors = [];

            if (!isStructural) {
              const parts = String(key).split('.');
              if (parts.indexOf('') !== -1) {
                errors.push(this.$t('key_must_not_contain_empty_parts'));
              }
              for (let p = 0; p < parts.length; p++) {
                if (parts[p].match(/^[a-zA-Z0-9:_-]+$/) == null) {
                  errors.push(this.$t('key_can_only_contain_letters_numbers_and_underscores'));
                  break;
                }
              }
              if (seenKeys[key]) {
                errors.push(this.$t('key_already_exists'));
              }
              if (
                checkSchema &&
                !isExtensionTemplateNode(item) &&
                !isAdditionalTemplateKey(key) &&
                !isAcceptedSchemaKey(key, schemaKeys, keyAliases)
              ) {
                errors.push(this.$t('key_unknown_schema_path'));
              }
            }

            seenKeys[key] = true;

            if (errors.length > 0) {
              issues.push({
                key: key,
                select_key: key,
                title: item.title || key,
                message: errors[0],
                prop_key: null
              });
            }

            if ((item.type === 'array' || item.type === 'nested_array') && item.props && Array.isArray(item.props)) {
              for (let p = 0; p < item.props.length; p++) {
                const prop = item.props[p];
                if (!prop || !prop.key) {
                  continue;
                }
                const absolute = prop.prop_key || (key + '.' + prop.key);
                const propErrors = [];

                if (String(prop.key).indexOf('.') !== -1 || String(prop.key).match(/^[a-zA-Z0-9:_-]+$/) == null) {
                  propErrors.push(this.$t('key_can_only_contain_letters_numbers_and_underscores'));
                }
                if (
                  checkSchema &&
                  !isExtensionTemplateNode(prop) &&
                  !isExtensionTemplateNode(item) &&
                  !isAdditionalTemplateKey(absolute) &&
                  !isAdditionalTemplateKey(key) &&
                  !isAcceptedSchemaKey(absolute, schemaKeys, keyAliases)
                ) {
                  propErrors.push(this.$t('key_unknown_schema_path'));
                }

                if (propErrors.length > 0) {
                  issues.push({
                    key: absolute,
                    select_key: absolute,
                    title: prop.title || absolute,
                    message: propErrors[0],
                    prop_key: absolute
                  });
                }
              }
            }

            if (item.items) {
              this.collectInvalidTemplateKeys(item.items, issues, seenKeys);
            }
          }
        },
        getNodePath: function(arr, name) {
          if (!arr || !name) {
            return false;
          }

          for (let item of arr) {
            const itemKey = item.key || item.prop_key;
            if (!itemKey) continue;
            
            if (itemKey === name) return `/${itemKey}`;
            
            if (item.items) {
              const child = this.getNodePath(item.items, name);
              if (child) return `/${itemKey}${child}`
            }
            
            // Also check props for array/nested_array
            if ((item.type === 'array' || item.type === 'nested_array') && item.props && Array.isArray(item.props)) {
              for (let prop of item.props) {
                const propKey = prop.prop_key || prop.key;
                if (!propKey) continue;
                
                if (propKey === name) return `/${itemKey}/${propKey}`;
                
                if (prop.props && Array.isArray(prop.props)) {
                  const child = this.getNodePath(prop.props.map(p => ({ ...p, key: p.prop_key || p.key })), name);
                  if (child) return `/${itemKey}${child}`
                }
              }
            }
          }
          return false;
        },
        // Returns array of { key, type } from root to the node (for path prefix building).
        getNodePathSegments: function(arr, name, pathSoFar) {
          if (!arr || !name) return false;
          pathSoFar = pathSoFar || [];
          for (let item of arr) {
            const itemKey = item.key || item.prop_key;
            if (!itemKey) continue;
            const seg = { key: itemKey, type: item.type || 'unknown' };
            if (itemKey === name) return pathSoFar.concat([seg]);
            if (item.items) {
              const child = this.getNodePathSegments(item.items, name, pathSoFar.concat([seg]));
              if (child) return child;
            }
            if ((item.type === 'array' || item.type === 'nested_array') && item.props && Array.isArray(item.props)) {
              for (let prop of item.props) {
                const propKey = prop.prop_key || prop.key;
                if (!propKey) continue;
                const pSeg = { key: propKey, type: (prop.type || 'unknown') };
                if (propKey === name) return pathSoFar.concat([seg, pSeg]);
                if (prop.props && Array.isArray(prop.props)) {
                  const child = this.getNodePathSegments(prop.props.map(p => ({ ...p, key: p.prop_key || p.key })), name, pathSoFar.concat([seg, pSeg]));
                  if (child) return child;
                }
              }
            }
          }
          return false;
        },
        getNodeContainerKey: function(tree, node_key) {
          if (!node_key) return null;
          let el_path = this.getNodePath(tree, node_key);
          if (!el_path || typeof el_path !== 'string') {
            return null;
          }
          const parts = el_path.split("/").filter(p => p); // Filter out empty strings
          return parts.length > 0 ? parts[0] : null;
        },
        saveTemplate: function() {
          if (!this.user_has_edit_access) {
            alert(this.$t("read_only") + " - " + this.$t("no_edit_permission"));
            return;
          }
          
          vm = this;
          let url = CI.base_url + '/api/templates/update/' + this.user_template_info.uid;

          // Sync UserTreeItems back to UserTemplate.items before saving
          // This ensures all changes made to the tree are saved
          // Note: Root and description nodes are virtual and not saved
          this.$store.state.user_template.items = this.UserTreeItems;

          formData = this.user_template_info;
          formData.template = this.UserTemplate;

          axios.post(url,
              formData, {
                /*headers: {
                    'Content-Type': 'multipart/form-data'
                }*/
              }
            ).then(function(response) {
              //window.location.href = CI.base_url + '/editor/templates';
              alert(vm.$t("changes_saved"));
              vm.is_dirty = false;
            })
            .catch(function(response) {
              vm.errors = response;
              alert(vm.$t("failed_to_save"), response);
            });
        },
        cancelTemplate: function() {
          window.location.href = CI.base_url + '/editor/templates';
        },
        coreTemplatePartsHelpText: function(element) {
          if (element && element.help_text) {
            return element.help_text;
          }
          return '';
        },
        filterTreeItems: function(items, searchQuery) {
          if (!items || !Array.isArray(items)) {
            return [];
          }
          
          return items.filter(item => {
            // Check if current item matches search query
            const titleMatch = item.title && item.title.toLowerCase().includes(searchQuery);
            const keyMatch = item.key && item.key.toLowerCase().includes(searchQuery);
            const helpTextMatch = item.help_text && item.help_text.toLowerCase().includes(searchQuery);
            
            // If current item matches, include it
            if (titleMatch || keyMatch || helpTextMatch) {
              return true;
            }
            
            // If item has children, recursively check them
            if (item.items && item.items.length > 0) {
              const filteredChildren = this.filterTreeItems(item.items, searchQuery);
              if (filteredChildren.length > 0) {
                // Return item with filtered children
                return {
                  ...item,
                  items: filteredChildren
                };
              }
            }
            
            return false;
          }).map(item => {
            // If item has children and doesn't match directly, but has matching children
            if (item.items && item.items.length > 0) {
              const filteredChildren = this.filterTreeItems(item.items, searchQuery);
              if (filteredChildren.length > 0) {
                return {
                  ...item,
                  _originalItem: item._originalItem || item,
                  items: filteredChildren
                };
              }
            }
            return item;
          });
        },
        expandAllForSearch: function() {
          // Collect all keys that should be expanded
          const keysToExpand = [];
          this.collectAllKeys(this.filteredUserTreeItems, keysToExpand);
          this.initiallyOpen = keysToExpand;
        },
        collectAllKeys: function(items, keyArray) {
          if (!items || !Array.isArray(items)) {
            return;
          }
          
          items.forEach(item => {
            if (item.key) {
              keyArray.push(item.key);
            }
            if (item.items && item.items.length > 0) {
              this.collectAllKeys(item.items, keyArray);
            }
          });
        }
      },
      watch: {
        activeNodeDeepLinkKey: function(newKey, oldKey) {
          if (!this.deep_link_ready || newKey === oldKey) {
            return;
          }
          this.setDeepLinkKeyInUrl(newKey);
        },
        treeSearchQuery: function(newQuery) {
          if (newQuery && newQuery.length > 0) {
            // Auto-expand all items when searching to show results
            this.expandAllForSearch();
          } else {
            // Reset to original state when clearing search
            this.initiallyOpen = [];
          }
        },
        user_template_info: {
          deep: true,
          handler(val, oldVal) {
            // Don't mark as dirty during initialization
            if (!oldVal || JSON.stringify(oldVal) == '{}' || Object.keys(oldVal).length === 0) {
              this.is_dirty = false;
              return;
            }
            this.is_dirty = true;
          }
        },
        UserTemplate: 
         {            
            deep:true,
            immediate: false,
            handler(val, oldVal){
              // Don't mark as dirty during initialization or if oldVal is undefined/null/empty
              if (!oldVal || !oldVal.items) {
                return;
              }
              
              // Mark as dirty when UserTemplate changes (including nested property changes)
              this.is_dirty=true;   
             }
         },
        UserTemplateClone: 
         {            
            deep:true,
            immediate: false,
            handler(val, oldVal){
              // Don't mark as dirty during initialization or if oldVal is undefined/null/empty
              if (!oldVal || JSON.stringify(oldVal) == '{}' || (typeof oldVal === 'object' && Object.keys(oldVal).length === 0)) {
                this.is_dirty=false;
                return;
              }
              
              this.is_dirty=true;   
             }
         }

      },
      computed: {
        isCoreTemplate: function() {
          if (!this.user_template_info) {
            return false;
          }
          // Core templates have template_type === 'core'
          // Generated templates also cannot be edited, but we're specifically checking for core here
          return this.user_template_info.template_type === 'core';
        },
        isEditable: function() {
          // Template is editable if:
          // 1. It's not a core template
          // 2. User has edit access
          return !this.isCoreTemplate && this.user_has_edit_access;
        },
        activeNodeDeepLinkKey() {
          return this.getActiveNodeDeepLinkKey(this.ActiveNode);
        },
        TemplateIsAdminMeta(){
          return this.user_template_info.data_type=='admin_meta';
        },
        TemplateIsCustom(){
          return this.user_template_info.data_type=='custom';
        },
        TemplateDataType() {
          return this.user_template_info.data_type;
        },
        UserTemplateClone(){
          return JSON.parse(JSON.stringify(this.UserTemplate));
        },
        UserTreeUsedKeys() {
          return this.$store.getters.getUserTreeKeys;
        },
        CoreTemplate() {
          return this.$store.state.core_template;
        },
        UserTemplate() {
          return this.$store.state.user_template;
        },
        CoreTreeItems() {
          return this.$store.state.core_tree_items;
        },
        UserTreeItems() {
          return this.$store.state.user_tree_items;
        },
        UserTreeItemsWithRoot() {
          // Create a virtual root node with data_type as the key/title
          const dataType = this.TemplateDataType || 'template';
          const dataTypeTitle = dataType.charAt(0).toUpperCase() + dataType.slice(1).replace(/-/g, ' ');
          
          // Create description node (virtual, first child)
          const descriptionNode = {
            key: 'template_description',
            title: this.$t('description') || 'Description',
            type: 'template_description',
            isVirtual: true,
            items: []
          };
          
          // Create root node with description as first child, then rest of items
          const rootNode = {
            key: 'template_root',
            title: dataTypeTitle,
            type: 'template_root',
            isVirtual: true,
            items: [descriptionNode, ...this.UserTreeItems]
          };
          
          return [rootNode];
        },
        filteredUserTreeItems() {
          const itemsToFilter = this.UserTreeItemsWithRoot;
          
          if (!this.treeSearchQuery) {
            return itemsToFilter;
          }
          
          // Filter the tree, but preserve root and description nodes
          const filtered = this.filterTreeItems(itemsToFilter, this.treeSearchQuery.toLowerCase());
          
          // Always include root and description if they exist
          if (itemsToFilter && itemsToFilter.length > 0 && itemsToFilter[0].key === 'template_root') {
            const root = itemsToFilter[0];
            // If root was filtered out, add it back with description
            if (!filtered.find(item => item.key === 'template_root')) {
              return [{
                ...root,
                items: [
                  root.items[0], // description node
                  ...this.filterTreeItems(this.UserTreeItems, this.treeSearchQuery.toLowerCase())
                ]
              }];
            }
          }
          
          return filtered;
        },
        coreTreeKeys() {
          return this.$store.state.core_tree_keys;
        },
        userTreeKeys() {
          return this.$store.state.user_tree_keys;
        },
        coreTemplateParts() {
          return this.$store.state.core_template_parts;
        },
        ActiveNode: {
          get: function() {
            return this.$store.state.active_node;
          },
          set: function(newValue) {
            this.$store.state.active_node = newValue;
          }
        },
        ActiveNodekey() {
          if (!this.ActiveNode) return null;
          const nodeKey = this.ActiveNode.key || this.ActiveNode.prop_key;
          return nodeKey ? JSON.parse(JSON.stringify(nodeKey)) : null;
        },
        ActiveNodeContainerKey(){
          if (!this.ActiveNode) return null;
          const nodeKey = this.ActiveNode.key || this.ActiveNode.prop_key;
          if (!nodeKey) return null;
          return this.getNodeContainerKey(this.UserTreeItems, nodeKey);
        },
        ActiveNodeEnum: {
          get: function() {
            if (!this.ActiveNode) return [];
            if (this.ActiveNode.enum && this.ActiveNode.enum.length > 0 && typeof(this.ActiveNode.enum[0]) == 'string') {
              let enum_list = [];
              this.ActiveNode.enum.forEach(function(item) {
                enum_list.push({
                  'code': item,
                  'label': item
                });
              });
              Vue.set(this.ActiveNode, "enum", enum_list);
              return enum_list;
            }
            return this.ActiveNode.enum || [];
          },
          set: function(newValue) {
            if (!this.ActiveNode) return;
            Vue.set(this.ActiveNode, "enum", newValue);
          }
        },
        ActiveNodeEnumStoreColumn:{
          get: function(){
            if (!this.ActiveNode) return 'both';
            if (this.ActiveNode.enum_store_column){
              return this.ActiveNode.enum_store_column;
            }
            return 'both';
          },
          set: function(newValue){
            if (!this.ActiveNode) return;
            Vue.set(this.ActiveNode, "enum_store_column", newValue);
        }
          
        },
        ActiveNodeEnumCount() {
          if (!this.ActiveNode || !this.ActiveNode.enum) {
            return 0;
          }
          return this.ActiveNode.enum.length;
        },
        ActiveNodeHasAdditionalPrefix(){
            if (!this.ActiveNode) return false;
            const nodeKey = this.ActiveNode.key || this.ActiveNode.prop_key;
            if (!nodeKey) {
              return false;
            }
            // For custom type, don't require additional. prefix
            if (this.TemplateIsCustom) {
              return false;
            }
            return nodeKey.indexOf('additional.')==0;
        },
        ActiveCoreNode() {
          return this.$store.state.active_core_node;
        },
        ActiveNodeIsField() {
          if (!this.ActiveNode) return false;
          // Virtual nodes (root and description) are not fields
          if (this.ActiveNode.type === 'template_root' || this.ActiveNode.type === 'template_description') {
            return false;
          }
          // If it's a prop (has prop_key), it's a field
          if (this.ActiveNode.prop_key) {
            return true;
          }
          // Regular fields
          if (!this.ActiveNode.type || this.ActiveNode.type == 'section' || this.ActiveNode.type == 'section_container') {
            return false;
          }
          return true;
        },
        ActiveNodeIsRoot() {
          return this.ActiveNode && this.ActiveNode.type === 'template_root';
        },
        ActiveNodeIsDescription() {
          return this.ActiveNode && this.ActiveNode.type === 'template_description';
        },
        ActiveNodeControlledVocabColumns() {
          if (!this.ActiveNode || !this.ActiveNode.props) {
            return false;
          }
          return this.ActiveNode.props;
        },
        ActiveNodeSimpleControlledVocabColumns() {
          return [{
              'type': 'text',
              'key': 'code',
              'title': 'Code'
            },
            {
              'type': 'text',
              'key': 'label',
              'title': 'Label'
            }
          ]

        },
        ActiveArrayNodeIsNested() {
          if (!this.ActiveNode || !this.ActiveNode.type) return false;
          if (this.ActiveNode.type == 'array' || this.ActiveNode.type == 'nested_array') {
            let isNested = false;

            //check if array has props
            if (!this.ActiveNode.props) {
              return false;
            }

            this.ActiveNode.props.forEach((prop, index) => {
              if (prop && prop.props) {
                isNested = true;
              }
            });

            return isNested;
          }
          return false;
        },
        ActiveNodeIsInsideNestedArray() {
          // Check if the ActiveNode (section) is inside a nested_array
          // Sections should never show tabs, so this is mainly for clarity
          if (!this.ActiveNode || !this.ActiveNode.type) {
            return false;
          }
          
          // If it's a section, check if it's inside a nested_array
          if (this.ActiveNode.type === 'section') {
            const nodeKey = this.ActiveNode.key || this.ActiveNode.prop_key;
            if (!nodeKey) return false;
            
            // Find the parent of this section by searching the tree
            const findParent = (items, targetKey) => {
              if (!items || !Array.isArray(items)) return null;
              
              for (let item of items) {
                // Check if this item's items array contains the target section
                if (item.items && Array.isArray(item.items)) {
                  const found = item.items.find(child => {
                    const childKey = child.key || child.prop_key;
                    return childKey === targetKey;
                  });
                  
                  if (found) {
                    // Found it - return the parent
                    return item;
                  }
                  
                  // Recursively check nested items
                  const nestedResult = findParent(item.items, targetKey);
                  if (nestedResult) {
                    return nestedResult;
                  }
                }
              }
              
              return null;
            };
            
            const parent = findParent(this.UserTreeItems, nodeKey);
            // Return true if parent is a nested_array
            return parent && parent.type === 'nested_array';
          }
          
          return false;
        },
        ActiveNodeIsProp() {
          // Check if ActiveNode is actually a prop (inside an array's props array)
          if (!this.ActiveNode || !this.ActiveNode.prop_key) return false;
          // If it has prop_key, check if it's actually in an array's props
          const parent = this.findPropParentNode(this.ActiveNode.prop_key);
          return parent !== null && parent !== undefined;
        },
        propParentNode() {
          // Get the parent node for a prop
          if (!this.ActiveNode || !this.ActiveNode.prop_key) return null;
          return this.findPropParentNode(this.ActiveNode.prop_key);
        },
        MissingSectionContainers() {
          // Get all section_containers from core template
          if (!this.CoreTemplate || !this.CoreTemplate.items) {
            return [];
          }
          
          const coreContainers = this.CoreTemplate.items.filter(item => 
            item && item.type === 'section_container'
          );
          
          // Get keys of existing section_containers in user template
          if (!this.UserTemplate || !this.UserTemplate.items) {
            return coreContainers;
          }
          
          const userContainerKeys = this.UserTemplate.items
            .filter(item => item && item.type === 'section_container')
            .map(item => item.key);
          
          // Return missing ones (not in user template)
          return coreContainers.filter(container => 
            container.key && !userContainerKeys.includes(container.key)
          );
        },
        InvalidTemplateKeys() {
          const issues = [];
          const seenKeys = {};
          const items = this.UserTreeItems || (this.UserTemplate && this.UserTemplate.items) || [];
          this.collectInvalidTemplateKeys(items, issues, seenKeys);
          return issues;
        },
      }
    });
  </script>


</body>

</html>