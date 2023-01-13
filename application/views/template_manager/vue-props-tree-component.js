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
        CoreActiveNode: {
          get: function() {
            return this.$store.state.active_node;
          },
          set: function(newValue) {
            this.$store.state.active_node = newValue;
          }
        },
        UserTreeItems() {
          return this.$store.state.user_tree_items;
        },
        CoreProps(){
          return this.getProps(this.Items,this.parent_key);
        }
    },
    methods:{
      treeClick: function (node){
        //store.commit('tree_active_node',node.key);
        console.log("treeClick",node);

        //expand tree node          
        this.initiallyOpen.push(node.key);
        this.active_prop=node;
      },
      onTreeOpen: function (node){
        console.log("tree node open");
        
      },
      getProps: function(props_arr,parent_key='')
      {
        let vm=this;

        _.map(props_arr,function (d) {
            if (d.props){
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
      getNodeContainerKey: function(tree,node_key)
      {
        let el_path=this.getNodePath(tree,node_key);
        return el_path.split("/")[1];
      }
    },
    template: `
            <div class="props-treeview-component">
            
            <div class="container-fluid">
            <div class="row">
              <div class="col-md-4 border">
                <div class="row">
                  <div class="col-md-11">

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
                  <div style="margin:-3px;">

                    <div>
                      <v-icon v-if="active_prop" color="#3498db" @click="addSection()">mdi-plus-box</v-icon>
                      <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-plus-box</v-icon>
                    </div>
                    <div>
                      <v-icon v-if="active_prop" color="#3498db" @click="removeField()">mdi-minus-box</v-icon>
                      <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-minus-box</v-icon>
                    </div>
                    <div>
                      <v-icon v-if="active_prop" color="#3498db" @click="moveUp()">mdi-arrow-up-bold-box</v-icon>
                      <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-arrow-up-bold-box</v-icon>
                    </div>
                    <div>
                      <v-icon v-if="active_prop" color="#3498db" @click="moveDown()">mdi-arrow-down-bold-box</v-icon>
                      <v-icon v-else color="rgb(0 0 0 / 12%)">mdi-arrow-down-bold-box</v-icon>
                    </div>

                  </div>
                </div>

              </div>


            
            </div>
            
            <div class="col-md-8 border">
              <div v-if="active_prop">

                <div class="form-group">
                    <label for="name">Label:</label>
                    <input type="text" class="form-control" v-model="active_prop.title">
                    <div class="text-secondary font-small" style="margin-top:4px;font-size:small"><span class="pl-3">Name: {{active_prop.key}}</span></div>
                </div>

                <div class="form-group">
                    <label for="name">Description:</label>
                    <textarea class="form-control" v-model="active_prop.help_text"/>
                </div>

              </div>
              <div v-else class="border p-3">Click on an item to edit
              </div>

              coreProps
              <pre>{{CoreProps}}</pre>

              </div>
            </div>

            </div>


            </div>          
            `    
});

