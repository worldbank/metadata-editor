///variable documentation tab
Vue.component('variable-edit-documentation', {
    props:['variable'],
    data: function () {    
        return {
            drawer: true,       
            drawer_mini: true,
            //variable: this.value,            
            //variable:{},
            form_local:{}
            //template_items_enabled: []
        }        
    },
    watch: {          
        
    },    
    created: function () {
        this.$store.variable_template_items_enabled= JSON.parse(JSON.stringify(this.variableDocumentationElements));
    },
    computed: {
        templateItemsEnabled:{
            get(){
                return this.$store.state.variable_template_items_enabled;
            },
            set(newValue){
                this.$store.state.variable_template_items_enabled=newValue;
            }
        },
        localVariable: {
            get(){
                let variable_= { 
                    'variable':this.variable
                }
    
                return variable_;
            }
        },
        Variable:{
            get(){                
                return this.variable;
            },
            set(newValue){
                this.variable=newValue;                
            }
        },

        variable_template_enabled: function()
        {
            let template_=JSON.parse(JSON.stringify(this.VariableTemplate));

            //remove keys that are not in the template_items_enabled
            let template_keys=this.templateItemsEnabled;

            //search template_ recursively for keys and remove items that are not in the template_keys            
            this.removeTemplateItemsByKeys(template_.items,template_keys);
            
            this.removeTemplateEmptySections(template_.items);
            return template_;
        },
        
        VariableTemplate: function()
        {
            let template_= JSON.parse(JSON.stringify(this.GetVariableTemplate));

            //remove keys that are not in the template
            let template_keys=this.variableDocumentationElements;

            //keep only documentation tab elements            
            this.removeTemplateItemsByKeys(template_.items,template_keys);

            //remove empty sections
            this.removeTemplateEmptySections(template_.items);
            return template_;
        },
        GetVariableTemplate: function(){

                let key='variable';
                    
                let findTemplateByItemKey= function (items,key){
                    let item=null;
                    let found=false;
                    let i=0;

                    while(!found && i<items.length){
                        if (items[i].key==key){
                            item=items[i];
                            found=true;
                        }else{
                            if (items[i].items){
                                item=findTemplateByItemKey(items[i].items,key);
                                if (item){
                                    found=true;
                                }
                            }
                        }
                        i++;                        
                    }
                    return item;
                }

                //search nested formTemplate
                let items=this.$store.state.formTemplate.template.items;
                let item=findTemplateByItemKey(items,key);

                return item;        
        },
        variableDocumentationElements()
        {
            return this.$store.state.variable_documentation_fields;
        },
        VariableFileID()
        {
            return this.Variable.fid;
        }        
    },
    methods: {

        //search template_ recursively for keys and remove items that are not in the template_keys
        removeTemplateItemsByKeys: function (items,template_keys){            
            let i=0;
            while(i<items.length){
                //if item.type=='section' then keep it
                if (items[i].type=='section'){
                    this.removeTemplateItemsByKeys(items[i].items,template_keys);
                    i++;
                    continue;
                }

                if (items[i].key && !template_keys.includes(items[i].key)){
                    items.splice(i,1);
                }else{
                    if (items[i].items){
                        this.removeTemplateItemsByKeys(items[i].items,template_keys);
                    }
                    i++;
                }
            }

            return items;            
        },
        removeTemplateEmptySections: function (items){            
            let i=0;
            while(i<items.length){
                //if item.type=='section' then keep it
                if (items[i].type=='section'){
                    if (items[i].items.length==0){
                        items.splice(i,1);
                    }else{
                        this.removeTemplateEmptySections(items[i].items);
                        i++;
                    }
                    continue;
                }
                i++;
            }
            
            return items;
        },

        toggleItem: function(key){
            let idx= this.templateItemsEnabled.indexOf(key);
            if (idx==-1){
                this.templateItemsEnabled.push(key);
            }else{
                this.templateItemsEnabled.splice(idx,1);
            }
        },
        isItemEnabled: function(key){
            return this.templateItemsEnabled.includes(key);
        },

        update: function (key, value)
        {
            key=key.replace('variable.','');
            if (key.indexOf(".") !== -1 && this.variable[key]){
                delete this.variable[key];
            }
            Vue.set(this.variable,key,value);
        },
        updateSection: function (obj)
        {
            this.update(obj.key,obj.value);
        },

        localValue: function(key)
        {
            //remove 'variable.' from key
            key=key.replace('variable.','');
            return _.get(this.variable,key);
        },
        
        sectionEnabled: function(section){
            
            if (section.items==undefined){
                return false;
            }

            for(i=0;i<section.items.length;i++){
                if (section.items[i].enabled){
                    return true;
                }
            }
            return false;
        },
        
    },
    template: `
        <div class="variable-edit-documentation-component pb-5">
            
            <div class="row mt-1">
                <div class="col-auto">
                <template>
                    <v-navigation-drawer
                        v-model="drawer"
                        :mini-variant.sync="drawer_mini" permanent bottom>
                        <v-list-item class="px-2">
                            <v-app-bar-nav-icon></v-app-bar-nav-icon>
                                <v-list-item-title>Settings</v-list-item-title>                            
                                <v-btn icon @click.stop="drawer_mini = !drawer_mini">
                                    <v-icon>mdi-chevron-left</v-icon>
                                </v-btn>
                        </v-list-item>                            
                        <v-divider></v-divider>                            
                        <v-list dense v-if="!drawer_mini">
                        <v-list-item
                            v-for="section in VariableTemplate.items" :key="section.key" link>
                            <v-list-item-content>
                             <template v-if="section.type=='section'">
                                <v-list-item-title>{{ section.title }}</v-list-item-title>
                            
                                <div v-for="subitem in section.items" :key="subitem.key">
                                    <input type="checkbox" :checked="isItemEnabled(subitem.key)" @change="toggleItem(subitem.key)"/> {{subitem.title}}
                                </div>
                            </template>
                            <template v-else>
                                <div>
                                <input type="checkbox" :checked="isItemEnabled(section.key)" @change="toggleItem(section.key)"/> {{section.title}}
                                </div>
                            </template>
                            </v-list-item-content>
                        </v-list-item>
                        </v-list>                        
                    </v-navigation-drawer>                        
                </template>
                </div>

                <div class="col">

                    <div v-for="(column,idx_col) in variable_template_enabled.items" scope="row" :key="column.key" >
                        <template v-if="column.type=='section'">
                        
                            <form-section
                                :parentElement="localVariable"
                                :value="localValue(column.key)"
                                :columns="column.items"
                                :title="column.title"
                                :path="column.key"
                                :field="column"                            
                                @sectionUpdate="updateSection($event)"
                            ></form-section>  
                            
                        </template>
                        <template v-else>
                                                        
                            <form-input
                                :value="localValue(column.key)"
                                :field="column"
                                @input="update(column.key, $event)"
                            ></form-input>                              
                            
                        </template>
                    </div>

                </div>
                    
            </div>                        
            </div>

        </div>          
        `
});

