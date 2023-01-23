///props treeview component
Vue.component('props-treeview', {
    props:['value','initially_open','cut_fields','core_props','parent_key'],
    data: function () {    
        return {
            template: this.value,
            initiallyOpen:[],
            tree_active_items:[],
            active_prop:'',
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
        console.log("tree node open");
      },
      getProps: function(props_arr,parent_key='')
      {
        let vm=this;

        _.map(props_arr,function (d) {
            if (d.props){
              d.prop_key=parent_key + "." + d.key;              
              return vm.getProps(d.props,parent_key + "." + d.key);
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
              if(item.key===name) return `/${item.key}`;
              if(item.items) {
                  const child = this.getNodePath(item.items, name);
                  if(child) return `/${item.key}${child}`
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
      getNodeSiblings: function(props,node_key){
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
            prop_keys.push(item.prop_key);
         }
         return prop_keys;         
      },
      getNodeContainerKey: function(tree,node_key)
      {
        let el_path=this.getNodePath(tree,node_key);
        return el_path.split("/")[1];
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
            if (tree.length>1){
              Vue.delete(tree, idx);
            }
            else{
              alert("To remove all items, remove the parent element");
            }
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
        }
      
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
                            <span @click="treeClick(item)" :title="item.title" class="tree-item-label" >
                                <span>{{item.title}}</span>                        
                            </span>
                        </template>

                        <template v-slot:prepend="{ item, open }">
                          <v-icon v-if="item.type=='section_container'">
                            {{ open ? 'mdi-dresser' : 'mdi-dresser' }}
                          </v-icon> 
                          <v-icon v-else-if="item.type=='section'">
                            {{ open ? 'mdi-folder-open' : 'mdi-folder' }}
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
                  </div>

                </div>
              </div>

              <div class="mt-5 border-top pt-5" style="min-height: 40vh; max-height:80vh; overflow: auto;">
                <strong>Available items:</strong>
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
                <prop-edit :key="active_prop.prop_key" v-model="active_prop"></prop-edit>
              </div>
              <div v-else class="border p-3">Click on an item to edit</div>

              </div>
            </div>

            </div>


            </div>          
            `    
});

