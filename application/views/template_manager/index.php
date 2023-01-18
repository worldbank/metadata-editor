<html>

<head>

  <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@mdi/font@6.x/css/materialdesignicons.min.css" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.min.css" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">

  <script src="https://cdn.jsdelivr.net/npm/vue@2.x/dist/vue.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.js"></script>
  <script src="https://unpkg.com/vuex@2.0.0"></script>
  <script src="<?php echo base_url(); ?>javascript/axios.min.js"></script>

  <script src="//cdn.jsdelivr.net/npm/sortablejs@1.8.4/Sortable.min.js"></script>
  <script src="//cdnjs.cloudflare.com/ajax/libs/Vue.Draggable/2.20.0/vuedraggable.umd.min.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.20/lodash.min.js"></script>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

  <style>
    .tree-item-label{display:block}
    .v-treeview-node__root:hover {
      background: #ececec;
      cursor:pointer;
    }

  .table-sm td,
  .table-sm th{
      font-size:small;
  }
  .iscut{
    color:#e56767;
  }
  </style>


</head>

<body>

  <?php

  $core_template = json_encode($core_template);
  $core_template_arr = json_decode($core_template, true);

  $user_template = json_encode($user_template['template']);
  $user_template_arr = json_decode($user_template, true);

  //break template into smaller templates by spliting template ['items']
  $core_template_parts = array();
  $user_template_parts = array();

  //update template_parts
  get_template_part($core_template_arr['items'], null, $core_template_parts);
  get_template_part($user_template_arr['items'], null, $user_template_parts);

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
    let user_template_parts = <?php echo json_encode($user_template_parts, JSON_PRETTY_PRINT); ?>;
  </script>

  <div id="app" data-app>
    <v-app>
    <div class="header bg-dark p-1 pt-2" style="margin-bottom:12px;">
      <div class="row">
        <div class="col-md-10">
          <h5 class="color-white"> 
            <v-icon large color="rgb(0 0 0 / 12%)">mdi-alpha-t-box</v-icon>
            Template manager - {{user_template_info.name}}
            {{user_template_info.lang}}
          </h5>
        </div>
        <div class="col-md-2">
          <div class="float-right">
            <button type="button" class="btn btn-sm btn-primary" @click="saveTemplate()">Save</button>
            <button type="button" class="btn btn-sm btn-danger" @click="cancelTemplate()">Cancel</button>
          </div>
        </div>
      </div>

    </div>

    <div class="container-fluid" style="height: 100vh">

      <div class="row">

        <div class="col col-md-3" style="height:100vh;">

          <div class="row">
            <div class="col bg-light" style="height:100vh; overflow:auto;">
              <div @click="isEditingDescription=true" style="padding-left:33px;cursor:pointer;" class="border-bottom pb-2"><v-icon>mdi-ballot-outline</v-icon>Template Description</div>
              <div @click="isEditingDescription=false">
                <nada-treeview  v-model="UserTreeItems" :cut_fields="cut_fields" :initially_open="initiallyOpen"></nada-treeview>
              </div>
            </div>
            <div class="col-1" style="background:#dee2e6;">
              <div style="margin:-3px;">

                <div>
                  <v-icon v-if="ActiveCoreNode.type" color="#3498db" @click="addField()">mdi-chevron-left-box</v-icon>
                  <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-chevron-left-box</v-icon>
                </div>
                <div>
                  <v-icon v-if="ActiveNodeIsField" color="#3498db" @click="removeField()">mdi-chevron-right-box</v-icon>
                  <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-chevron-right-box</v-icon>
                </div>
                
                <div>
                  <v-icon v-if="ActiveNode.type=='section_container'" color="#3498db" @click="addSection()">mdi-plus-box</v-icon>
                  <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-plus-box</v-icon>
                </div>
                <div>
                  <v-icon v-if="ActiveNode.type=='section'" color="#3498db" @click="removeField()">mdi-minus-box</v-icon>
                  <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-minus-box</v-icon>
                </div>
                <div>
                  <v-icon v-if="ActiveNode.type!='section_container'  && ActiveNode.type" color="#3498db" @click="moveUp()">mdi-arrow-up-bold-box</v-icon>
                  <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-arrow-up-bold-box</v-icon>
                </div>
                <div>
                  <v-icon v-if="ActiveNode.type!='section_container' && ActiveNode.type" color="#3498db" @click="moveDown()">mdi-arrow-down-bold-box</v-icon>
                  <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-arrow-down-bold-box</v-icon>
                </div>


                <div class="mt-5" title="Move">
                  <v-icon v-if="ActiveNodeIsField" color="#3498db" @click="cutField()">mdi-content-copy</v-icon>
                  <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-content-copy</v-icon>
                </div>

                <div class="mt-2" title="Paste">
                  <v-icon v-if="ActiveNode.type=='section' && cut_fields.length>0" color="#3498db" @click="pasteField()">mdi-content-paste</v-icon>
                  <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-content-paste</v-icon>
                </div>

              </div>
            </div>
          </div>
        </div>


        <!--detail-->
        <div class="col bg-light" style="height:100vh; overflow:auto;">

            <div v-if="isEditingDescription==false">
              <?php echo $this->load->view('template_manager/edit_content',null,true);?>
            </div>
            <div v-if="isEditingDescription==true" class="pl-4 pt-2">
            
              <h5>Template description</h5>

              <div class="form-group">
                  <label>Language:</label>
                  <input type="text" class="form-control" placeholder="EN" v-model="user_template_info.lang">
              </div>

              <div class="form-group">
                  <label>Name:</label>
                  <input type="text" class="form-control" v-model="user_template_info.name">
              </div>

              <div class="form-group">
                  <label>Version:</label>
                  <input type="text" class="form-control" v-model="user_template_info.version">
              </div>

              <div class="form-group">
                  <label>Organization:</label>
                  <input type="text" class="form-control" v-model="user_template_info.organization">
              </div>

              <div class="form-group">
                  <label>Author:</label>
                  <input type="text" class="form-control" v-model="user_template_info.author">
              </div>

              <div class="form-group">
                  <label>Description:</label>
                  <textarea style="height:200px;" class="form-control" v-model="user_template_info.description"></textarea>
              </div>

            
              <pre>{{user_template_info}}</pre>
            </div>
        </div>
        <!--end detail-->

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

    <?php echo include_once("vue-tree-component.js"); ?>
    <?php echo include_once("vue-tree-field-component.js"); ?>
    <?php echo include_once("vue-table-component.js"); ?>
    <?php echo include_once("vue-list-component.js"); ?>
    <?php echo include_once("vue-validation-rules-component.js"); ?>
    <?php echo include_once("vue-props-tree-component.js"); ?>
    <?php echo include_once("vue-prop-edit-component.js"); ?>


    Vue.mixin({
      methods: {}
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
        user_template_parts: user_template_parts,

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
          console.log("core tree keys", items);
          return items;
        },
        getUserTreeKeys: function(state) {
          let items = [];
          items = getTreeKeys(state.user_tree_items, items);
          console.log("user tree keys", items);
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
      store,
      vuetify: new Vuetify(),
      data() {
        return {
          isEditingDescription: true,
          user_template_info: user_template_info,
          initiallyOpen: [],
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
          field_types: [
            "text",
            "textarea",
            "dropdown",
            //"date"
          ],
          cut_fields:[]          
        }
      },
      created: function() {
        this.init_tree();
        //this.init_core_template_keys();
        //result=this.$store.getters.getUserTreeKeys;
        //console.log("adklfjlaksdjfkajsdkfljasdlkjflkdsajfladsjf", result);
        //return this.$store.getters.getDataFileById(this.fid);
      },
      methods: {
        init_tree: function() {
          this.$store.state.core_tree_items = this.$store.state.core_template.items;
          this.$store.state.user_tree_items = this.$store.state.user_template.items;

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
        removeField: function() {
          this.delete_tree_item(this.UserTreeItems, this.ActiveNode.key);
          this.ActiveNode = {};
        },
        cutField: function() 
        {
          let active_container_key=this.getNodeContainerKey(this.UserTreeItems,this.ActiveNode.key);

          //unselect cut field
          for(i=0;i<this.cut_fields.length;i++){
            if (active_container_key==this.cut_fields[i].container){
              if (this.cut_fields[i].node.key == this.ActiveNode.key){
                this.cut_fields.splice(i,1);
                return;
              }
            }
          }

          this.cut_fields.push(
            {
              "node":this.ActiveNode,
              "container": this.getNodeContainerKey(this.UserTreeItems,this.ActiveNode.key)
            }
          );
          //this.removeField();
        },
        pasteField: function() {
          if (this.cut_fields.length<1) {
            return false;
          }

          if (this.isItemContainer(this.ActiveCoreNode)){
            return false;
          }

          if (!this.ActiveNode.items) {
              this.$set(this.ActiveNode, "items", []);
          }

          let active_container_key=this.getNodeContainerKey(this.UserTreeItems,this.ActiveNode.key);

          for(i=0;i<this.cut_fields.length;i++){
            if (active_container_key==this.cut_fields[i].container){
              //remove existing item
              this.delete_tree_item(this.UserTreeItems, this.cut_fields[i].node.key);
              //add copied item
              this.ActiveNode.items.push(this.cut_fields[i].node);
            }
          }
          
          this.cut_fields=[];
          store.commit('activeCoreNode', {});
        },
        //check if an item is selected for cut/paste        
        isItemCut: function(item)
        {
          let active_container_key=this.getNodeContainerKey(this.UserTreeItems, item.key);

          for(i=0;i<this.cut_fields.length;i++){
            if (active_container_key==this.cut_fields[i].container){
               if (item.key==this.cut_fields[i].node.key){
                return true;
               }
            }
          }
          return false;
        },
        isItemContainer: function(item){
          if (item.type=='section' || item.type=='section_container' || item.type=='nested_array_'){
            return true;
          }
          return false;
        },
        isControlField: function(field_type){          
          return this.field_types.includes(field_type);
        },
        addField: function() {
          if (!this.ActiveCoreNode.key) {
            return false;
          }

          if (this.isItemContainer(this.ActiveCoreNode)){
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
        addSection: function() 
        {          
          if (!this.ActiveNode.key=='section_container'){
            return false;
          }

          parentNode = this.ActiveNode;
          new_node_key=parentNode.key + Date.now();
          parentNode.items.push ({
            "key": new_node_key,
            "title": "Untitled",
            "type": "section",
            "items": [],
            "help_text":""
          });

          this.ActiveNode= parentNode.items[parentNode.items.length-1];
        },
        moveUp: function()
        {
          parentNode = this.findNodeParent(this.UserTemplate, this.ActiveNode.key);          
          nodeIdx=this.findNodePosition(parentNode,this.ActiveNode.key);
          if (nodeIdx >0 ){
            this.array_move(parentNode.items,nodeIdx,nodeIdx-1);
          }
        },
        moveDown: function()
        {
          parentNode = this.findNodeParent(this.UserTemplate, this.ActiveNode.key);
          nodeIdx=this.findNodePosition(parentNode,this.ActiveNode.key);

          parentNodeItemsCount=parentNode.items.length-1;
          
          if (nodeIdx >-1 && nodeIdx<parentNodeItemsCount){
            this.array_move(parentNode.items,nodeIdx,nodeIdx+1);
          }
        },
        array_move: function (arr, old_index, new_index) {
            if (new_index >= arr.length) {
                var k = new_index - arr.length + 1;
                while (k--) {
                    arr.push(undefined);
                }
            }
            arr.splice(new_index, 0, arr.splice(old_index, 1)[0]);
        },
        findNodePosition: function(node,key)
        {
          if (!node.items){
            return false;
          }

          for(index=0;index < node.items.length;index++)
          {
              let item=node.items[index];
              if (item.key && item.key == key) {
                return index;
              }
          }

          return -1;
          
          /*node.items.forEach(item => {
            index++;
            console.log("searching", index, item.key,key);
            if (item.key) {
              if (item.key == key) {
                console.log("matched", index, item.key,key);
                return index;
              }
            }
          });
          return index;*/
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
        getNodePath: function(arr,name)
        {
            if (!arr){
              return false;
            }

            for(let item of arr){
                if(item.key===name) return `/${item.key}`;
                if(item.items) {
                    const child = this.getNodePath(item.items, name);
                    if(child) return `/${item.key}${child}`
                }
            }
        },
        getNodeContainerKey: function(tree,node_key)
        {
          let el_path=this.getNodePath(tree,node_key);
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
              window.location.href = CI.base_url + '/admin/metadata_editor/templates';
            })
            .catch(function(response) {
              vm.errors = response;
              alert("Failed to save", response);
            });
        },
        cancelTemplate: function() {
          window.location.href = CI.base_url + '/admin/metadata_editor/templates';
        },
        coreTemplatePartsHelpText: function(element)
        {
          if (element && element.help_text){
            return element.help_text;
          }
          return '';
        }
      },
      watch:{
      },
      computed: {
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
        userTemplateParts() {
          return this.$store.state.user_template_parts;
        },
        ActiveNode: {
          get: function() {
            return this.$store.state.active_node;
          },
          set: function(newValue) {
            this.$store.state.active_node = newValue;
          }
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
        ActiveNodeControlledVocabColumns(){
          if (this.ActiveNode.props){
            return this.ActiveNode.props;
          }
          return false;
        }
      }
    });
  </script>


</body>

</html>