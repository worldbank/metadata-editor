/// view treeview component
Vue.component('nada-treeview', {
    props:['value','initially_open','tree_active_items','cut_fields'],
    data: function () {    
        return {
            template: this.value,
            initiallyOpen:[],
            //tree_active_items:[],
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
      this.initiallyOpen=this.initially_open;
    },
    watch:{
      initiallyOpen: function(val) {
        this.$emit('initially-open',this.initiallyOpen);
      }
    },
    computed: {
        TreeActiveItems: {
          get: function() {
            return this.tree_active_items;
          },
          set: function(newValue) {
            //todo
          }          
        },
        Items(){
            return this.value;
        },
        ActiveNode: {
          get: function() {
            return this.$store.state.active_node;
          },
          set: function(newValue) {
            this.$store.state.active_node = newValue;
          }
        },
        UserTreeItems() {
          return this.$store.state.user_tree_items;
        }
    },
    methods:{
      treeClick: function (node){
        this.initiallyOpen.push(node.key);
        store.commit('activeNode',node);        
      },
      onTreeOpen: function (node){
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
      isItemAdditional: function(item){
        //check if item key has additional. prefix
        return item.key.startsWith('additional.');
      },
      getItemClasses: function(item){
        let classes=[];
        if (this.isItemCut(item)){
          classes.push('iscut');
        }
        if (this.isItemAdditional(item)){
          classes.push('additional-item');
        }        
        return classes;
      }
    },
    template: `
            <div class="nada-treeview-component">
            <template>            
              <v-treeview                   
                  color="warning"
                  :open.sync="initiallyOpen" 
                  :active.sync="TreeActiveItems"
                  @update:open="onTreeOpen" 
                  :items="Items" 
                  activatable dense 
                  item-key="key" 
                  item-text="title"  
                  expand-icon="mdi-chevron-down"
                  indeterminate-icon="mdi-bookmark-minus"
                  on-icon="mdi-bookmark"
                  off-icon="mdi-bookmark-outline"
                  item-children="items"                  
              >

                <template #label="{ item }" >
                    <span @click="treeClick(item)" :title="item.title" class="tree-item-label" :class="getItemClasses(item)" >
                        <span v-if="item.type=='resource'" >{{item.title | truncate(23, '...') }}</span>
                        <span v-else>{{item.title}} <template v-if="item.title==''">Untitled</template></span>
                        <span v-if="isItemCut(item)">*</span>                        
                    </span>
                </template>

                <template v-slot:prepend="{ item, open }" >
                  <v-icon v-if="item.type=='section_container'">
                    {{ open ? 'mdi-dresser' : 'mdi-dresser' }}
                  </v-icon> 
                  <v-icon v-else-if="item.type=='section'">
                    {{ open ? 'mdi-folder-open' : 'mdi-folder' }}
                  </v-icon>
                  
                  <v-icon v-else-if="item.type=='nested_array'" >
                    {{ open ? 'mdi-file-tree-outline' : 'mdi-file-tree' }}
                  </v-icon> 
                  <v-icon v-else-if="item.type=='array'" >
                    {{ open ? 'mdi-folder-table-outline' : 'mdi-folder-table' }}
                  </v-icon> 

                  <v-icon v-else>
                    mdi-note-text-outline
                  </v-icon>
                </template>
              </v-treeview>
            </template>

            </div>          
            `    
});

