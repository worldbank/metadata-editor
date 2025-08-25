Vue.component('vue-tree-list', {
    props: ['value','parent_path','path_level','permissions','getPermissionsFunction'],
    data() {
        return {
            toggle_items: false,
        }
    },
    created:function(){
    },
    methods: {        
        toggleItems: function() {            
            this.toggle_items = !this.toggle_items;            
        },
        saveCollection: function() {
            this.$emit('update-collection', JSON.parse(JSON.stringify(this.collection)));
        },
        removeAccess: function(index) {
            this.$emit('remove-access', 
                {
                    'project_id':this.project_id,
                    'user_id':this.shared_users[index]['user_id']
                }
            );
            this.shared_users.splice(index, 1);
        },
        showMenu: function(e,id) {
            console.log("showMenu",id);
            this.$emit('show-menu',{e,id});
        },
        onShowMenu: function(data) {
            console.log("onShowMenu",data);
            this.$emit('show-menu',data);
        }
    },
    computed:{
        ParentPath(){
            if (!this.parent_path){
                return this.value.title;
            }

            return this.parent_path + ' / ' + this.value.title;
        },
        PathLevel(){
            if (!this.path_level){
                return 1;
            }
            return this.path_level + 1;
        },
        PermissionBadge() {
            if (!this.permissions) return '';
            
            if (this.permissions.permissions === 'admin') {
                return '<span class="badge badge-success">Admin</span>';
            } else if (this.permissions.permissions === 'edit') {
                return '<span class="badge badge-warning">Edit</span>';
            } else {
                return '';
            }
        },
        PermissionColor() {
            if (!this.permissions) return 'text-muted';
            
            if (this.permissions.permissions === 'admin') {
                return 'text-success';
            } else if (this.permissions.permissions === 'edit') {
                return 'text-warning';
            } else {
                return 'text-muted';
            }
        }
    },
    template: `
        <div class="vue-tree-list">
 
        <template v-if="value" >
            <div class="border-bottom ">
                <div class="row collection-row">
                    <div class="col-1">
                        {{value.id}}
                    </div>                    
                    <div class="col">
                        <div v-if="value.items" @click="toggleItems" :class="'collection-item item-level-'+PathLevel">                            
                            <v-icon v-if="toggle_items" >mdi-chevron-down</v-icon>
                            <v-icon v-else>mdi-chevron-right</v-icon>                            
                            <v-icon>mdi-folder</v-icon>
                            <span class="collection-title">{{value.title}}</span>
                            <div v-if="permissions && permissions.permissions !== 'view'" class="permission-indicator">
                                <span v-if="permissions.permissions === 'admin'" class="badge badge-success">Admin</span>
                                <span v-else-if="permissions.permissions === 'edit'" class="badge badge-warning">Edit</span>
                            </div>
                            <div style="display:none;" class="text-secondary">{{value.description}}</div>
                        </div>
                        <div v-else :class="' collection-item item-level-'+PathLevel">                            
                            <v-icon class="collection-leaf">mdi-folder-outline</v-icon> 
                            <span class="collection-title">{{value.title}}</span>
                            <div v-if="permissions && permissions.permissions !== 'view'" class="permission-indicator">
                                <span v-if="permissions.permissions === 'admin'" class="badge badge-success">Admin</span>
                                <span v-else-if="permissions.permissions === 'edit'" class="badge badge-warning">Edit</span>
                            </div>
                            <div style="display:none;" class="text-secondary">{{value.description}}</div>
                        </div>    
                    </div>
                    <div class="col-1">
                        {{value.users}}
                    </div>
                    <div class="col-1">
                        {{value.projects}}
                    </div>
                    <div class="col-1">
                        <v-btn icon>
                            <v-icon @click="showMenu($event,value.id)">mdi-dots-vertical</v-icon>
                        </v-btn>
                    </div>                    
                    
                </div>                
            </div>
            
        </template>

        <div v-if="value.items && toggle_items" class="tree-item">
            <vue-tree-list 
                v-for="item in value.items" 
                :value="item" 
                :parent_path="ParentPath" 
                :path_level="PathLevel"
                :permissions="getPermissionsFunction ? getPermissionsFunction(item.id) : null"
                :getPermissionsFunction="getPermissionsFunction"
                v-on:show-menu="onShowMenu"
            >
            </vue-tree-list>
        </div>

        
    </div>
    `
});

