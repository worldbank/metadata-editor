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
            activeNode:{},
            switchShowAll:false
        }
    },
    mounted: function(){
      //this.initiallyOpen.push(this.Items[0].key);
      window._items=this.Items;
    },
    
    computed: {
        Items(){
          if (this.TemplateActiveNode){
            parent=this.findNodeParent(this.UserTemplate,this.TemplateActiveNode.key);
            if (this.coreTemplateParts[parent.key]){
              return this.coreTemplateParts[parent.key].items;
            }
            return []
          }
        },
        filteredItems()
        {
          if (this.switchShowAll){
            return this.Items;
          }

          return this.filterUnused(this.Items);          
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
       filterUnused: function(node)
       {
         let vm=this;
         return node.reduce((acc,obj)=>{
           
           if (obj.items){
            result={...obj, items: this.filterUnused(obj.items)};

            if (result.items && result.items.length>0){
              return [...acc, result];
            }else{
              return acc;
            }
           }
           else if (!vm.isItemInUse(obj.key)){
               return [...acc,obj];
           }
           else{
             return acc;
           }
       
         },[]);
       },

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
      isItemContainer: function(item){
        if (item.type=='section' || item.type=='section_container' || item.type=='nested_array_'){
          return true;
        }
        return false;
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
      treeClick: function (node)
      {
        this.activeNode=node;
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

            <div class="container-fluid p-1 pt-2">
            
            <v-switch
              v-model="switchShowAll"
              :label="$t('show_all_elements')"
            ></v-switch>
            

            <div class="row">
              <div class="col-md-6" style="height: 100vh; overflow: auto;">

                <div class="p-3 border m-3 text-center" v-if="filteredItems.length==0">{{$t("no_items_available")}}</div>

                <v-treeview                   
                    color="warning"
                    open-all
                    :open.sync="initiallyOpen" 
                    :items="filteredItems" 
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
                          <span v-if="isItemInUse(item.key) && !isItemContainer(item)" style="color:gray;">{{item.title}}</span>
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
                    <span v-if="isItemContainer(item)">
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
              </div>
              <div class="col-md-6" style="height: 100vh; overflow: auto;">
                <div v-if="activeNode.key" class="p-3">
                
                  <div><strong>{{$t("description")}}</strong></div>
                  <table class="table table-sm table-bordered table-striped">
                    <tr>
                      <td>{{$t("field")}}</td>
                      <td>{{activeNode.key}}</td>
                    </tr>
                    <tr>
                      <td>{{$t("type")}}</td>
                      <td>{{activeNode.type}}</td>
                    </tr>
                    <tr>
                      <td>{{$t("title")}}</td>
                      <td>{{activeNode.title}}</td>
                    </tr>
                    <tr>
                      <td>{{$t("description")}}</td>
                      <td><div style="white-space: pre-wrap;">{{activeNode.help_text}}</div></td>
                    </tr>                    
                  </table>

                  <div v-if="activeNode.props" >
                      <strong>{{$t("array_properties")}}</strong>
                      
                          <table class="table table-sm">
                            <thead>
                            <tr>
                              <th>{{$t("key")}}</th>
                              <th>{{$t("title")}}</th>
                              <th>{{$t("type")}}</th>
                              <th>{{$t("description")}}</th>
                            </tr>
                          </thead>
                          <template v-for="prop in activeNode.props">
                            <tr>
                              <td>{{prop.key}}</td>
                              <td>{{prop.title}}</td>
                              <td>{{prop.type}}</td>
                              <td><div style="white-space: pre-wrap;">{{prop.help_text}}</div></td>
                            </tr>
                          </template>
                          </table>
                    </div>
                
                </div>
                <div v-else class="p-3 border m-3 text-center">
                  <template v-if="switchShowAll==true">{{$t("click_to_edit")}}</template>
                </div>


              </div>
              </div>

            </div>


            </div>          
            `    
});

