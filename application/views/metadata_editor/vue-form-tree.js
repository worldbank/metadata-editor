//treeview ///////////////////////////////////////////////////
Vue.component('form-tree', {
    props: ['title', 'depth', 'css_class', 'node'],
    data() {
        return {
            showChildren: true
        }
    },
    methods: {
        nodeClick: function (node_id){
            this.$store.state.active_node.id=node_id;
            //this.$vuexSet (this.$store.state.active_node.id, node_id);
            this.$emit('tree-node-click', node_id);
            bus.$emit('tree-node-click', node_id);
        },
        toggleChildren() {
            this.showChildren = !this.showChildren;
        },
        toggleNode() {
            console.log("endText triggered");
            /*if (target){
                this.$emit('show-parent', target.innerHTML);
            }*/
        },
        searchChild: function(node_key, node){
                //console.log(node_key,node);

            if (node.key){
                if (this.normalizeNodeID(node.key)==this.normalizeNodeID(node_key)){
                    return true;
                }
            }

            if (node.id){
                if (this.normalizeNodeID(node.id)==this.normalizeNodeID(node_key)){
                    return true;
                }
            }

            let vm=this;

            if (node.items){
                for (let i = 0; i < node.items.length; i++) {
                    if (this.searchChild(node_key,node.items[i])==true){
                        return true;
                    }
                };
            }
            return false;
            
        },
        normalizeNodeID: function(node_id){
            return node_id.replace(/\./g, "-");
        },
        getMyNodeID: function() {
            if (this.node.id){
                return this.normalizeNodeID(this.node.id);
            }

            if (this.node.key){
                return this.normalizeNodeID(this.node.key);
            }
        },
        getMySelectedNodeID: function(){
            return this.$store.state.active_node.id;
        }
    },
    computed: {
        toggleClasses() {
            return {
                'fa-folder': !this.showChildren,
                'fa-folder-open': this.showChildren
            }
        },
        isActiveNode(){
            return this.nodeID==this.activeNode;
        },
        selectedNodeID(){
            return this.$store.state.active_node.id;
        },
        nodeClass() {
            return {
                'has-children': this.items,
                'active': this.nodeID==this.activeNode
            }
        },
        nodeID() {
            if (this.node.id){
                return this.normalizeNodeID(this.node.id);
            }

            if (this.node.key){
                return this.normalizeNodeID(this.node.key);
            }
        },
        containsActiveNode()
        {
            if (!this.selectedNodeID){
                return false;
            }

            found= this.searchChild(this.selectedNodeID, this.node);

            if (found==true){
                this.showChildren=true;
                return true;
            }
        }
    },
    template: `
        <div :class="'form-tree ' + css_class " > 
            <div class="node-wrapper" @click="toggleChildren">
                <div 
                    class="tree-node" 
                    :class="(getMySelectedNodeID() == getMyNodeID()) ? 'active' : '' " 
                    :id="'node-' + nodeID"
                    @click="nodeClick(nodeID)"                            
                >
                    <i v-if="node.items" class="fas" :class="toggleClasses"></i>
                    <i v-if="!node.items" class="fas fa-file-alt" ></i>
                    {{ title }}
                    <span v-if="isActiveNode == true"></span>
                    <!--<span style="background:red;" v-if="containsActiveNode ==true">ACTIVE</span>--> 
                </div>
            </div>


            <form-tree                        
                v-show="showChildren" 
                v-for="item in node.items" 
                    :node="item" 
                    :title="item.title"
                    :depth="depth + 1"
                    :css_class="'lvl-' + depth"
                    
            >
            </form-tree>
        </div>
    `
})