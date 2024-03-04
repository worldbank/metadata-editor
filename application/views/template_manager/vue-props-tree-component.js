///props treeview component
Vue.component('props-treeview', {
    props:['value','initially_open','core_props','parent_key','parent_type','parent_node'],
    data: function () {    
        return {
            template: this.value,
            initiallyOpen:[],
            tree_active_items:[],
            active_prop:'',
            cut_fields:[],
            files: {
              html: 'mdi-language-html5',
              js: 'mdi-nodejs',
              json: 'mdi-code-json',
              md: 'mdi-language-markdown',
              pdf: 'mdi-file-pdf',
              png: 'mdi-file-image',
              txt: 'mdi-file-document-outline',
              xls: 'mdi-file-excel',
            }
        }
    },
    created: function(){
      this.active_prop={};
    },    
    computed: {
        Items:{           
            get(){
              return this.value;
          },
          set(val){
              this.$emit('input:value', val);
          }
        },
        /*CoreActiveNode: {
          get: function() {
            return this.$store.state.active_node;
          },
          set: function(newValue) {
            this.$store.state.active_node = newValue;
          }
        },*/
        UserTreeItems() {
          return this.$store.state.user_tree_items;
        },
        CoreProps(){
          return this.getProps(this.core_props,this.parent_key);
        },
        UserProps(){
          return this.getProps(this.Items,this.parent_key);
        },
        UnusedSiblings(){
          return this.getUnusedSiblings();
        },
        IsAdditonalField(){
          //check parent_key starts with additional.
          if (this.parent_key.startsWith('additional.')){
            return true;
          }
          return false;
        },
        ParentNode:{
          get(){
            return this.parent_node;
          },
          set(val){            
            this.$emit('input:update', val);
          }
        }
    },
    methods:{
      treeClick: function (node){
        this.initiallyOpen.push(node.key);
        this.active_prop=node;
      },
      EnumUpdate: function(e) {
        if (!this.active_prop.enum) {
          this.$set(this.active_prop, "enum", []);
        }
      },
      onTreeOpen: function (node){
      },
      getProps: function(props_arr,parent_key='')
      {
        let vm=this;        

        _.map(props_arr,function (d) {
            if (d.props){
              return vm.getProps(d.props,parent_key + "." + d.key);
            }
            if (d.prop_key){
              return d.prop_key;
            }
            return d.prop_key=parent_key + "." + d.key;
          });

        return props_arr;
      },
      getNodePath: function(arr,name)
      {
          if (!arr){
            return false;
          }

          for(let item of arr){
              if(item.prop_key===name) return `/${item.prop_key}`;
              if(item.props) {
                  const child = this.getNodePath(item.props, name);
                  if(child) return `/${item.prop_key}${child}`
              }
          }
      },
      findNodeByKey: function(props,prop_key)
      {
        for (const item of props) {
          if (item.props) {
            result= this.findNodeByKey(item.props, prop_key);
            if (result){
              return result;
            }
          }
          if (item.prop_key == prop_key) {
            return item;
          }
        }
      },
      getUnusedSiblings: function()
      {
        if (!this.active_prop && !this.UserProps){
          return this.CoreProps;
        }
        
        let userSiblings= this.getNodeSiblings(this.UserProps,this.active_prop.prop_key);
        let coreSiblings= this.getNodeSiblings(this.CoreProps,this.active_prop.prop_key);
        let user_unused_keys= _.difference(coreSiblings, userSiblings);
        let items=new Array();

        for(i=0;i<user_unused_keys.length;i++){
          let prop_key=user_unused_keys[i];
          let prop=this.findNodeByKey(this.core_props,prop_key);

          if (prop){
            items.push(prop);
          }
        }
        
        return items;
      },
      getNodeSiblings: function(props,node_key)
      {        
         let prop_keys=new Array();
         let found=false;

         props.forEach((item, idx) => {
            if (node_key == item.prop_key){
              found=true;
            }
            
            if (item.props && !found){
              result=this.getNodeSiblings(item.props, node_key);
              if(result){
                prop_keys=result;
              }
            }
         });

         if (prop_keys.length>0){
          return prop_keys;
         }
         
         if (!found){
            return false;
         }

         for(i=0;i<props.length;i++){
            let item=props[i];
            if (item.type=='section'){              
              let result=this.getPropSectionItems(item);
              if(result){
                prop_keys.push(...result);
              }
            }else{
              prop_keys.push(item.prop_key);
            }
         }
         return prop_keys;         
      },
      getPropSectionItems: function(prop){
        let items=new Array();
        let vm=this;

        if (prop.props){
          prop.props.forEach((item, idx) => {
            if (item.type=='section'){
              let result=vm.getPropSectionItems(item);
              if(result){
                items.push(...result);
              }
            }else{
              items.push(item.prop_key);
            }
          });
        }

        return items;
      },      
      deleteProp: function()
      {
        this.delete_tree_item(this.Items,this.active_prop.prop_key);
        this.active_prop={};
      },
      delete_tree_item: function(tree, item_key) {
        tree.forEach((item, idx) => {
          if (item.props) {
            this.delete_tree_item(item.props, item_key);
          }
          if (item.prop_key == item_key) {
            Vue.delete(tree, idx);
            /*if (tree.length>1){
              Vue.delete(tree, idx);
            }
            else{
              alert("To remove all items, remove the parent element");
            }*/
          }
        });
      },
      addSubItem: function(item){
        let parent=this.getNodeParent(this.UserProps,this.active_prop.prop_key);

        if (parent){
          parent.push(item);
        }
        //this.Items=this.value;
      },
      getNodeParent: function(node,node_key)
      {
        let parent='';
        node.forEach((item, idx) => {
          if (item.props) {
            result=this.getNodeParent(item.props, node_key);
            if (result){
              parent=result;
              return result;
            }
          }
          if (item.prop_key == node_key) {
            parent=node;
            return node;
          }
        });

        return parent;
      },
      addAdditionalField: function()
      {        
        //add new prop to parentNode

        new_node_key = 'prop' + Date.now();
        new_node_prop_key = this.parent_key + '.' + new_node_key;

        new_prop={
          "key": new_node_key,
          "prop_key": new_node_prop_key,
          "title": "Untitled",
          "type": "text",          
          "help_text": ""
        };

        //check if ParentNode has props
        if (!this.ParentNode.props){
          Vue.set(this.ParentNode,"props",[new_prop]);
        }else{
          Vue.set(this.ParentNode.props,this.ParentNode.props.length,new_prop);
        }

        this.active_prop=new_prop;

      },
      moveUp: function()
        {
          parentNode = this.getNodeParent(this.UserProps, this.active_prop.prop_key);
          nodeIdx=this.findNodePosition(parentNode,this.active_prop.prop_key);
          if (nodeIdx >0 ){
            this.array_move(parentNode,nodeIdx,nodeIdx-1);
          }
        },
        moveDown: function()
        {
          parentNode = this.getNodeParent(this.UserProps, this.active_prop.prop_key);
          nodeIdx=this.findNodePosition(parentNode,this.active_prop.prop_key);

          parentNodeItemsCount=parentNode.length-1;
          
          if (nodeIdx >-1 && nodeIdx<parentNodeItemsCount){
            this.array_move(parentNode,nodeIdx,nodeIdx+1);
          }
        },
        addSection: function()
        {
          /*if (!this.active_prop.type == 'section') {
            return false;
          }*/
          if (!this.active_prop.props){
            parentNode = this.getNodeParent(this.UserProps, this.active_prop.prop_key);
          }
          else{
            parentNode=this.active_prop.props;
          }

          new_node_key = 'section-' + Date.now();
          new_node_prop_key = 'prop-' + new_node_key;
          parentNode.push({
            "key": new_node_key,
            "prop_key": new_node_prop_key,
            "title": "Untitled",
            "type": "section",
            "props": [],
            "help_text": ""
          });

          this.active_prop = parentNode[parentNode.length - 1];
          this.tree_active_items = new Array();
          this.tree_active_items.push(new_node_prop_key);
          //this.initiallyOpen.push(new_node_key);
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
        findNodePosition: function(props,key)
        {
          for(index=0;index < props.length;index++)
          {
              let item=props[index];
              if (item.prop_key && item.prop_key == key) {
                return index;
              }
          }

          return -1;
        },
        cutField: function() {
          //let nestedArrayParentKey = this.getNodeNestedArrayParentKey(this.Items, this.active_prop.prop_key);
          //console.log("nestedArrayParentKey",nestedArrayParentKey);

          //unselect cut field
          for (i = 0; i < this.cut_fields.length; i++) {
            //if (nestedArrayParentKey == this.cut_fields[i].nestedParent) {
              if (this.cut_fields[i].node.prop_key == this.active_prop.prop_key) {
                this.cut_fields.splice(i, 1);
                return;
              }
            //}
          }

          this.cut_fields.push({
            "node": this.active_prop,
            //"nestedParent": nestedArrayParentKey
          });        
        },
        pasteField: function() 
        {
          if (this.cut_fields.length < 1) {
            return false;
          }

          pasteTarget=this.active_prop;

          if (this.active_prop.type != 'section' && this.active_prop.type != 'nested_array') {
            pasteTarget=this.getNodeParent(this.Items, this.active_prop.prop_key);
          }

          for (i = 0; i < this.cut_fields.length; i++) {
              //remove existing item
              this.delete_tree_item(this.Items, this.cut_fields[i].node.prop_key);
              //add copied item

              if (pasteTarget.type=='section' || pasteTarget.type=='nested_array'){
                if (!pasteTarget.props){
                  Vue.set(pasteTarget,"props",[]);
                }                
              }

              if (pasteTarget.props) {
                pasteTarget.props.push(this.cut_fields[i].node);
              }else{
                pasteTarget.push(this.cut_fields[i].node);
              }
          }
          this.cut_fields = [];
        },
        //check if an item is selected for cut/paste        
        isPropCut: function(prop) {
          //let nestedArrayParentKey = this.getNodeNestedArrayParentKey(this.Items, prop.prop_key);

          for (i = 0; i < this.cut_fields.length; i++) {
            //if (nestedArrayParentKey == this.cut_fields[i].nestedParent) {
              if (prop.prop_key == this.cut_fields[i].node.prop_key) {
                return true;
              }
            ///}
          }
          return false;
        },
      
    },
    template: `
            <div class="props-treeview-component">            
            <div class="container-fluid">
            <div class="row">
              <div class="col-md-4 border">
                <div class="row">
                  <div class="col-md-11" style="min-height: 30vh; max-height:80vh; overflow: auto;">
                    <template>
                      <v-treeview                   
                          color="warning"
                          v-model="Items"                   
                          :open.sync="initiallyOpen" 
                          :active.sync="tree_active_items"
                          @update:open="onTreeOpen" 
                          :items="Items" 
                          activatable dense 
                          item-key="prop_key" 
                          item-text="title"  
                          expand-icon="mdi-chevron-down"
                          indeterminate-icon="mdi-bookmark-minus"
                          on-icon="mdi-bookmark"
                          off-icon="mdi-bookmark-outline"
                          item-children="props"
                      >

                        <template #label="{ item }" >
                            <span @click="treeClick(item)" :title="item.title" class="tree-item-label" :class="{iscut: isPropCut(item)}">
                                <span v-if="!item.title">untitled</span>
                                <span v-else>{{item.title}}</span>
                                <span v-if="isPropCut(item)">*</span>
                            </span>
                        </template>

                        <template v-slot:prepend="{ item, open }">
                          <v-icon v-if="item.type=='section_container'">
                            {{ open ? 'mdi-dresser' : 'mdi-dresser' }}
                          </v-icon> 
                          <v-icon v-else-if="item.type=='section'">
                            {{ open ? 'mdi-folder-open' : 'mdi-folder' }}
                          </v-icon>
                          <v-icon v-else-if="item.type=='nested_array'">
                            {{ open ? 'mdi-file-tree-outline' : 'mdi-file-tree' }}
                          </v-icon> 
                          <v-icon v-else-if="item.type=='array'">
                            {{ open ? 'mdi-folder-table-outline' : 'mdi-folder-table' }}
                          </v-icon> 
                          <v-icon v-else>
                            mdi-note-text-outline
                          </v-icon>
                        </template>
                      </v-treeview>
                    </template>
                
                </div>
                <div  class="col-md-1">
                  <div style="margin:-3px;position:sticky;top:10px;" >

                    <div>
                      <v-icon v-if="active_prop.key" color="#3498db" @click="deleteProp()">mdi-minus-box</v-icon>
                      <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-minus-box</v-icon>
                    </div>
                    <div>
                      <v-icon v-if="active_prop.key" color="#3498db" @click="moveUp()">mdi-arrow-up-bold-box</v-icon>
                      <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-arrow-up-bold-box</v-icon>
                    </div>
                    <div>
                      <v-icon v-if="active_prop.key" color="#3498db" @click="moveDown()">mdi-arrow-down-bold-box</v-icon>
                      <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-arrow-down-bold-box</v-icon>
                    </div>
                    <div>
                      <v-icon v-if="active_prop.key && parent_type!='array'" color="#3498db" @click="addSection()">mdi-plus-box</v-icon>
                      <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-mdi-plus-box</v-icon>
                    </div>


                    <div class="mt-5" title="Move">
                      <v-icon v-if="active_prop.type && parent_type!='array'" color="#3498db" @click="cutField()">mdi-content-copy</v-icon>
                      <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-content-copy</v-icon>
                    </div>

                    <div class="mt-2" title="Paste">
                      <v-icon v-if="cut_fields.length>0" color="#3498db" @click="pasteField()">mdi-content-paste</v-icon>
                      <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-content-paste</v-icon>
                    </div>

                    <!--additional -->
                    <div class="mt-5" v-if="IsAdditonalField">
                      <v-icon title="Add custom field" color="#dc3545" @click="addAdditionalField()">mdi-text-box-plus-outline</v-icon>
                      
                    </div>

                  </div>

                </div>
              </div>

              <div class="mt-5 border-top pt-5" style="min-height: 40vh; max-height:80vh; overflow: auto;">
                <strong>{{$t('available_items')}}:</strong>
                <div class="border p-3 m-3" v-if="UnusedSiblings.length==0">None</div>
                <div v-for="(item,index) in UnusedSiblings" :key="item.prop_key" class="border-top mb-2">
                  <div @click="addSubItem(item)">
                    <div style="cursor:pointer">
                    <v-icon xcolor="#3498db">mdi-plus-box</v-icon>
                    {{item.title}} <span style="color:gray;font-size:small;">{{item.key}}</span></div>
                  </div>
                </div>
                

              </div>

            
            </div>
            
            <div class="col-md-8 border">

              <div v-if="active_prop.key">               
                <prop-edit :key="active_prop.prop_key" :parent="parent_node" v-model="active_prop"></prop-edit>
              </div>
              <div v-else class="border p-3">{{$t('click_to_edit')}}</div>

              </div>
            </div>

            </div>


            </div>          
            `    
});

