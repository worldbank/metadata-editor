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

    .font-small{
      font-size:small;
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
  </script>

  <div id="app" data-app>
    <v-app>

      <div class="container-fluid">

        <div class="row no-gutters sticky-top border-bottom bg-white">

          <div class="col-md-3">
            <div class="color-white branding-icon" style="padding:5px;padding-left:30px;font-weight:bold;">
              <v-icon large color="#007bff">mdi-alpha-t-box</v-icon>
              {{$t('template_manager')}}
            </div>
          </div>

          <div class="col-md-9">
            <!-- header -->
            <div class="header">
              <div class="row">
                <div class="col-md-9">

                  <div class="ml-5 pt-2">
                    <div class="text-crop">
                      <i :class="project_types_icons[user_template_info.data_type]"></i>
                      <strong style="font-size:large;">{{user_template_info.name}}</strong>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="float-right pt-1 mr-5">
                    <button type="button" class="btn btn-sm btn-success" @click="saveTemplate()"><v-icon style="color:white;">mdi-content-save-check</v-icon> {{$t('save')}} <span v-if="is_dirty==true">*</span></button>
                    <button type="button" class="btn btn-sm btn-default" @click="cancelTemplate()"><v-icon>mdi-exit-to-app</v-icon> {{$t('close')}}</button>
                  </div>
                </div>
              </div>

            </div>
            <!-- end header -->
          </div>


        </div>

        <div class="row no-gutters" style="height:100vh;">

          <div class="col-md-3" style="height:100vh;">

            <div class="row no-gutters border-right pt-2" style="height:100vh;overflow:auto;">
              <div class="col-md-11" style="height:100vh;">
                <div @click="isEditingDescription=true" style="padding:5px;padding-left:38px;cursor:pointer;" class="pb-2" :class="{isactive: isEditingDescription}"><v-icon>mdi-ballot-outline</v-icon>{{$t('description')}}</div>
                <div @click="isEditingDescription=false">
                  <nada-treeview 
                      v-model="UserTreeItems" 
                      :cut_fields="cut_fields" 
                      :initially_open="initiallyOpen" 
                      :tree_active_items="tree_active_items"
                      @initially-open="updateInitiallyOpen"
                      ></nada-treeview>
                </div>
              </div>
              <div class="col-md-1 col-xs-2" style="position:relative;">
                <div class="pr-1" v-if="!isEditingDescription" style="position:fixed;">

                  <div>
                    <v-icon v-if="ActiveCoreNode.type" color="#3498db" @click="addField()">mdi-chevron-left-box</v-icon>
                    <v-icon v-else class="disabled-button-color">mdi-chevron-left-box</v-icon>
                  </div>
                  <div>
                    <v-icon v-if="ActiveNodeIsField" color="#3498db" @click="removeField()">mdi-chevron-right-box</v-icon>
                    <v-icon v-else class="disabled-button-color">mdi-chevron-right-box</v-icon>
                  </div>

                  <div>
                    <v-icon v-if="ActiveNode.type=='section_container' || ActiveNode.type=='section'" color="#3498db" @click="addSection()">mdi-plus-box</v-icon>
                    <v-icon v-else class="disabled-button-color">mdi-plus-box</v-icon>
                  </div>
                  <div>
                    <v-icon v-if="ActiveNode.type=='section'" color="#3498db" @click="removeField()">mdi-minus-box</v-icon>
                    <v-icon v-else class="disabled-button-color">mdi-minus-box</v-icon>
                  </div>
                  <div>
                    <v-icon v-if="ActiveNode.type!='section_container'  && ActiveNode.type" color="#3498db" @click="moveUp()">mdi-arrow-up-bold-box</v-icon>
                    <v-icon v-else class="disabled-button-color">mdi-arrow-up-bold-box</v-icon>
                  </div>
                  <div>
                    <v-icon v-if="ActiveNode.type!='section_container' && ActiveNode.type" color="#3498db" @click="moveDown()">mdi-arrow-down-bold-box</v-icon>
                    <v-icon v-else class="disabled-button-color">mdi-arrow-down-bold-box</v-icon>
                  </div>


                  <div class="mt-5" title="Move">
                    <v-icon v-if="ActiveNodeIsField" color="#3498db" @click="cutField()">mdi-content-copy</v-icon>
                    <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-content-copy</v-icon>
                  </div>

                  <div class="mt-2" title="Paste">
                    <v-icon v-if="ActiveNode.type=='section' && cut_fields.length>0" color="#3498db" @click="pasteField()">mdi-content-paste</v-icon>
                    <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-content-paste</v-icon>
                  </div>

                  <!--additional -->
                  <div class="mt-5" v-if="ActiveNode.type=='section'">
                    <v-icon title="Add custom field" v-if="ActiveNode.type=='section_container' || ActiveNode.type=='section'" class="additional-item" @click="addAdditionalField()">mdi-text-box-plus-outline</v-icon>
                    <v-icon title="Add custom field" v-else class="disabled-button-color">mdi-text-box-plus-outline</v-icon>
                  </div>

                  <div class="mt-1" v-if="ActiveNode.type=='section'">
                    <v-icon title="Add custom Array field" v-if="ActiveNode.type=='section_container' || ActiveNode.type=='section'" class="additional-item"  @click="addAdditionalFieldArray()">mdi-table-large-plus</v-icon>
                    <v-icon title="Add custom Array field" v-else class="disabled-button-color">mdi-table-large-plus</v-icon>
                  </div>

                  <div class="mt-1" v-if="ActiveNode.type=='section'">
                    <v-icon title="Add custom NestedArray field" v-if="ActiveNode.type=='section_container' || ActiveNode.type=='section'" class="additional-item"  @click="addAdditionalFieldNestedArray()">mdi-file-tree</v-icon>
                    <v-icon title="Add custom NestedArray field" v-else class="disabled-button-color">mdi-file-tree</v-icon>
                  </div>

                </div>
              </div>
            </div>
          </div>


          <!--content section-->
          <div class="col-md-9 bg-light" style="height:100vh;">



            <!-- content -->
            <div class="main-content-container p-3" style="height:100vh;overflow:auto;">

              <div v-if="isEditingDescription==false">
                <?php echo $this->load->view('template_manager/edit_content', null, true); ?>
              </div>
              <div v-if="isEditingDescription==true" class="pl-4 pt-2">

                <h5>{{$t('description')}}</h5>

                <div class="form-group">
                  <label>{{$t('type')}}:</label>
                  <input type="text" class="form-control" disabled="disabled" v-model="user_template_info.data_type">
                </div>


                <div class="form-group">
                  <label>{{$t('language')}}:</label>
                  <input type="text" class="form-control" placeholder="EN" v-model="user_template_info.lang" maxlength="30">
                </div>

                <div class="form-group">
                  <label>{{$t('name')}}:</label>
                  <input type="text" class="form-control" v-model="user_template_info.name" maxlength="150">
                </div>

                <div class="form-group">
                  <label>{{$t('version')}}:</label>
                  <input type="text" class="form-control" v-model="user_template_info.version" maxlength="50">
                </div>

                <div class="form-group">
                  <label>{{$t('organisation')}}:</label>
                  <input type="text" class="form-control" v-model="user_template_info.organization" maxlength="150">
                </div>

                <div class="form-group">
                  <label>{{$t('author')}}:</label>
                  <input type="text" class="form-control" v-model="user_template_info.author" maxlength="150">
                </div>

                <div class="form-group">
                  <label>{{$t('description')}}:</label>
                  <textarea style="height:200px;" maxlength="1000" class="form-control" v-model="user_template_info.description"></textarea>
                </div>

                <div class="form-group">
                  <label>{{$t('instructions')}}: </label>
                  <span style="font-size:12px;color:gray">Markdown<a href="https://www.markdownguide.org/cheat-sheet/" target="_blannk"><v-icon style="font-size:14px;">mdi-open-in-new</v-icon> </a></span>
                  <textarea style="min-height:300px;"  class="form-control" v-model="user_template_info.instructions"></textarea>
                </div>

              </div>
            </div>

          </div>
          <!-- end content -->

          <!--end content section-->

        </div>

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

    <?php echo include_once("vue-field-key-component.js"); ?>
    <?php echo include_once("vue-prop-key-component.js"); ?>
    <?php echo include_once("vue-tree-component.js"); ?>
    <?php echo include_once("vue-tree-field-component.js"); ?>
    <?php echo include_once("vue-table-grid-component.js"); ?>
    <?php echo include_once("vue-validation-rules-component.js"); ?>
    <?php echo include_once("vue-props-tree-component.js"); ?>
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

      },
      mutations: {
        activeNode(state, node) {
          state.active_node = node;
        },
        activeCoreNode(state, node) {
          state.active_core_node = node;
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
          isEditingDescription: true,
          user_template_info: user_template_info,
          initiallyOpen: [],
          tree_active_items: [],
          is_dirty: false,
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
            "array",
            "nested_array",
            "simple_array"
            /*"number",
            "date",
            "boolean"*/
          ],
          cut_fields: [],
          data_types: {
            "survey": "Microdata",
            "document": "Document",
            "table": "Table",
            "geospatial": "Geospatial",
            "image": "Image",
            "script": "Script",
            "video": "Video"
          },
          project_types_icons: {
            "document": "fa fa-file-code",
            "survey": "fa fa-database",
            "geospatial": "fa fa-globe-americas",
            "table": "fa fa-database",
            "timeseries": "fa fa-chart-line",
            "image": "fa fa-image",
            "video": "fa fa-video",
            "script": "fa fa-file-code"
          }
        }
      },
      created: function() {
        this.init_template();
        this.init_tree();
        let vm=this;
        window.addEventListener('beforeunload', function(event) {
          return vm.onWindowUnload(event);
        });
      },
      methods: {
        onWindowUnload: function(event){
          if (!this.is_dirty){
            return null;
          }

          let message=this.$t('unsaved_changes');

          event.returnValue = message;
          return message;
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
        },
        updateInitiallyOpen: function (e){
          this.initiallyOpen=e;
        },
        delete_tree_item: function(tree, item_key) {
          tree.forEach((item, idx) => {
            if (item.items) {
              this.delete_tree_item(item.items, item_key);
            }
            if (item.key == item_key) {
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
          this.delete_tree_item(this.UserTreeItems, this.ActiveNode.key);
          this.ActiveNode = {};
        },
        cutField: function() {
          let active_container_key = this.getNodeContainerKey(this.UserTreeItems, this.ActiveNode.key);

          //unselect cut field
          for (i = 0; i < this.cut_fields.length; i++) {
            if (active_container_key == this.cut_fields[i].container) {
              if (this.cut_fields[i].node.key == this.ActiveNode.key) {
                this.cut_fields.splice(i, 1);
                return;
              }
            }
          }

          this.cut_fields.push({
            "node": this.ActiveNode,
            "container": this.getNodeContainerKey(this.UserTreeItems, this.ActiveNode.key)
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

          if (!this.ActiveNode.items) {
            this.$set(this.ActiveNode, "items", []);
          }

          let active_container_key = this.getNodeContainerKey(this.UserTreeItems, this.ActiveNode.key);

          for (i = 0; i < this.cut_fields.length; i++) {
            if (active_container_key == this.cut_fields[i].container) {
              //remove existing item
              this.delete_tree_item(this.UserTreeItems, this.cut_fields[i].node.key);
              //add copied item
              this.ActiveNode.items.push(this.cut_fields[i].node);
            }
          }

          this.cut_fields = [];
          store.commit('activeCoreNode', {});
        },
        //check if an item is selected for cut/paste        
        isItemCut: function(item) {
          let active_container_key = this.getNodeContainerKey(this.UserTreeItems, item.key);

          for (i = 0; i < this.cut_fields.length; i++) {
            if (active_container_key == this.cut_fields[i].container) {
              if (item.key == this.cut_fields[i].node.key) {
                return true;
              }
            }
          }
          return false;
        },
        isItemContainer: function(item) {
          if (item.type == 'section' || item.type == 'section_container' || item.type == 'nested_array_') {
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
          this.tree_active_items = new Array();
          this.tree_active_items.push(new_node_key);
          this.initiallyOpen.push(new_node_key);
          this.initiallyOpen.push(parentNode.key);          
        },
        addAdditionalField: function() {
          /*if (this.ActiveNodeContainerKey != 'additional_container') {
            return false;
          }*/

          parentNode = this.ActiveNode;
          new_node_key = "additional." + Date.now();
          parentNode.items.push({
            "key": new_node_key,
            "title": "Untitled",
            "type": "string",            
            "help_text": "",
            "display_type": "text"
          });

          this.ActiveNode = parentNode.items[parentNode.items.length - 1];
          this.tree_active_items = new Array();
          this.tree_active_items.push(new_node_key);
          this.initiallyOpen.push(new_node_key);
          this.initiallyOpen.push(parentNode.key);
        },
        addAdditionalFieldArray: function() {
          /*if (this.ActiveNodeContainerKey != 'additional_container') {
            return false;
          }*/

          parentNode = this.ActiveNode;
          new_node_key = "additional." + Date.now();
          parentNode.items.push({
            "key": new_node_key,
            "title": "Untitled",
            "type": "array",            
            "help_text": "",
            "props": [                           
            ]
          });

          this.ActiveNode = parentNode.items[parentNode.items.length - 1];
          this.tree_active_items = new Array();
          this.tree_active_items.push(new_node_key);
          this.initiallyOpen.push(new_node_key);
          this.initiallyOpen.push(parentNode.key);

          //this.ActiveNode.items.push(this.ActiveCoreNode);
          //store.commit('activeCoreNode', {});
        },
        addAdditionalFieldNestedArray: function() {
          parentNode = this.ActiveNode;
          new_node_key = "additional." + Date.now();
          parentNode.items.push({
            "key": new_node_key,
            "title": "Untitled",
            "type": "nested_array",            
            "help_text": "",
            "props": [                           
            ]
          });
          
          this.ActiveNode = parentNode.items[parentNode.items.length - 1];
          this.tree_active_items = new Array();
          this.tree_active_items.push(new_node_key);
          this.initiallyOpen.push(new_node_key);
          this.initiallyOpen.push(parentNode.key);
        },
        UpdateActiveNodeKey: function(e){
          this.ActiveNode.key = e;
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
        getNodePath: function(arr, name) {
          if (!arr) {
            return false;
          }

          for (let item of arr) {
            if (item.key === name) return `/${item.key}`;
            if (item.items) {
              const child = this.getNodePath(item.items, name);
              if (child) return `/${item.key}${child}`
            }
          }
        },
        getNodeContainerKey: function(tree, node_key) {
          let el_path = this.getNodePath(tree, node_key);
          return el_path.split("/")[1];
        },
        saveTemplate: function() {
          vm = this;
          let url = CI.base_url + '/api/templates/update/' + this.user_template_info.uid;

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
        }
      },
      watch: {
        isEditingDescription: function(val) {
          if (val == true) {
            this.tree_active_items = new Array();
          }
        },
        user_template_info: {
          deep: true,
          handler(val, oldVal) {
            if (JSON.stringify(oldVal) == '{}') {
              this.is_dirty = false;
              return;
            }
            this.is_dirty = true;
          }
        },
        UserTemplateClone: 
         {            
            deep:true,
            handler(val, oldVal){
              if (JSON.stringify(oldVal) == '{}') {
                this.is_dirty=false;
                return;
              }
              
              this.is_dirty=true;   
             }
         }

      },
      computed: {
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
          return JSON.parse(JSON.stringify(this.ActiveNode.key));
        },
        ActiveNodeContainerKey(){
          return this.getNodeContainerKey(this.UserTreeItems, this.ActiveNode.key);
        },
        ActiveNodeEnum: {
          get: function() {
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
            return this.ActiveNode.enum;
          },
          set: function(newValue) {
            Vue.set(this.ActiveNode, "enum", newValue);
          }
        },
        ActiveNodeEnumCount() {
          if (this.ActiveNode.enum) {
            return this.ActiveNode.enum.length;
          }
          return 0;
        },
        ActiveNodeHasAdditionalPrefix(){
            if (!this.ActiveNode.key) {
              return false;
            }

            return this.ActiveNode.key.indexOf('additional.')==0;
        },
        ActiveCoreNode() {
          return this.$store.state.active_core_node;
        },
        ActiveNodeIsField() {
          if (!this.ActiveNode.type || this.ActiveNode.type == 'section' || this.ActiveNode.type == 'section_container') {
            return false;
          }
          return true;
        },
        ActiveNodeControlledVocabColumns() {
          if (this.ActiveNode.props) {
            return this.ActiveNode.props;
          }
          return false;
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
          if (this.ActiveNode.type == 'array' || this.ActiveNode.type == 'nested_array') {
            let isNested = false;

            //check if array has props
            if (!this.ActiveNode.props) {
              return false;
            }

            this.ActiveNode.props.forEach((prop, index) => {
              if (prop.props) {
                isNested = true;
              }
            });

            return isNested;
          }
          return false;
        },
      }
    });
  </script>


</body>

</html>