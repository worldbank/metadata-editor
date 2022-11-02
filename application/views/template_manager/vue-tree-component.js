/// view treeview component
Vue.component('nada-treeview', {
    props:['value','initially_open'],
    data: function () {    
        return {
            template: this.value,
            initiallyOpen:[],
            tree_active_items:[],
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
      
    },
    
    computed: {
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
        TreeActiveItems:{
          get: function(){
            let items=[];
            items.push(this.ActiveNode.key);
            return items;
          },
          set: function(newValue){
                
          }
        }
    },
    methods:{
      treeClick: function (node){
        //store.commit('tree_active_node',node.key);
        //console.log("treeClick",node);

        //expand tree node          
        this.initiallyOpen.push(node.key);
        store.commit('activeNode',node);        
      },
    },
    template: `
            <div class="nada-treeview-component">
            
            <template>
            {{TreeActiveItems}}
              <v-treeview                   
                  color="warning"
                  v-model="value"                   
                  :open.sync="initiallyOpen" 
                  :active.sync="TreeActiveItems" 
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
                    <span @click="treeClick(item)" :title="item.title">
                        <span v-if="item.type=='resource'" >{{item.title | truncate(23, '...') }}</span>
                        <span v-else>{{item.title}}</span>
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
            `    
});

