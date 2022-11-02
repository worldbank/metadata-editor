/// view treeview component
Vue.component('nada-treeview-field', {
    props:['value'],
    data: function () {    
        return {
            template: this.value,
            initiallyOpen:[],
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
            selected_item:{},           
        }
    },
    created: function(){
        
    },
    
    computed: {
        Items(){
          console.log("templateActiveNode",this.TemplateActiveNode);
          if (this.TemplateActiveNode){
            parent=this.findNodeParent(this.UserTemplate,this.TemplateActiveNode.key);
            console.log("value matched",parent);
            return this.coreTemplateParts[parent.key].items;
          }
          //return this.value;
        },
        coreTemplateParts(){
          return this.$store.state.core_template_parts;
        },
        TemplateActiveNode(){
          return this.$store.state.active_node;
        },
        ActiveCoreNode(){
          return this.$store.state.active_core_node;
        },
        UserTreeUsedKeys(){
          return this.$store.getters.getUserTreeKeys;
        },
        CoreTemplate(){
          return this.$store.state.core_template;
        },
        UserTemplate(){
          return this.$store.state.user_template;
        },
        CoreTreeItems(){
          return this.$store.state.core_tree_items;
        },
        UserTreeItems(){
          return this.$store.state.user_tree_items;
        },
    },
    methods:{
      findNodeParent: function(tree,node_key)
          {
            found='';
            for(var i=0;i<tree.items.length;i++){
              let item=tree.items[i];
                if (item.key && item.key==node_key){
                    found=tree;
                    return tree;
                }

                if (item.items){
                  result=this.findNodeParent(item,node_key);
                  if (result!=''){
                    return result;
                  }
                }
            }
            return found;
          },
      isItemInUse: function(item_key){
        return _.includes(this.UserTreeUsedKeys, item_key);
      },
      addItem: function (item){

        if (this.isItemInUse(item.key)){
          return false;
        }

        if (this.checkNodeKeyExists(this.TemplateActiveNode,item.key)==true){
          return false;
        }

        this.selected_item=item;
        if (!this.TemplateActiveNode.items) {
          this.$set(this.TemplateActiveNode, "items", []);
        }
      
        this.TemplateActiveNode.items.push(item);
      },
      checkNodeKeyExists: function(node,key)
       {
         let exists=false;
         node.items.forEach(item=>{
             if (item.key){
               if (item.key==key){
                 exists=true;                 
               }
             }
         });

         return exists;
       },
      treeClick: function (node){
        //expand tree node          
        this.initiallyOpen.push(node.key);

        if (this.isItemInUse(node.key)){
          store.commit('activeCoreNode',{});
        }else{
          store.commit('activeCoreNode',node);
        }        
      },
    },
    template: `
            <div class="nada-treeview-component">

            <template>
              <v-treeview                   
                  color="warning"
                  v-model="Items"                   
                  :open.sync="initiallyOpen" 
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
                    <span @click="treeClick(item)" :title="item.title" >
                        <span v-if="isItemInUse(item.key) && item.type!='section'" style="color:gray;">{{item.title}}</span>
                        <span v-else>{{item.title}}</span>
                    </span>
                </template>

                <template v-slot:prepend="{ item, open }">
                  <v-icon v-if="!item.file">
                    {{ open ? 'mdi-folder-open' : 'mdi-folder' }}
                  </v-icon>
                  <v-icon v-else>
                    {{ files[item.file] }}
                  </v-icon>
                </template>

                <template slot="prepend" slot-scope="{ item, open }" >
                  <span v-if="item.type=='section' || item.type=='section_container'">
                    <v-icon v-if="!item.file">
                      {{ open ? 'mdi-folder-open' : 'mdi-folder' }}
                    </v-icon>
                    <v-icon v-else>
                      {{ files[item.file] }}
                    </v-icon>
                  </span>
                  <span v-else>
                    <v-icon small color="#007bff" v-if="!isItemInUse(item.key)" @click="addItem(item)">mdi-plus-box</v-icon>
                    <v-icon small v-if="isItemInUse(item.key)" @click="addItem(item)">mdi-checkbox-marked</v-icon>
                  </span>
                </template>

              </v-treeview>
            </template>

            </div>          
            `    
});

