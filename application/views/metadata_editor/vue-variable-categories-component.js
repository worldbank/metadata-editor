///variable categories edit form
Vue.component('variable-categories', {
    props:['value'],
    data: function () {    
        return {   
            //variable:this.value,
            catgry_columns:[
                {
                    "key": "value",
                    "title": this.$t("value"),
                    "type": "text",
                    "is_unique": true
                },
                {
                    "key": "labl",
                    "title": this.$t("label"),
                    "type": "text"
                }
            ],
            variable_formats:{
                "numeric": this.$t("numeric"),
                "fixed": this.$t("fixed_string")
            }
        }
    },
    created: function(){        
        //this.variable=this.value;
    },
    mounted: function () {
        //this.variable=this.value;
        /*if (!this.variable.var_catgry){
            this.variable.var_catgry=[{}];
            //this.field_data.push({});
        }*/
    },
    methods:{
        clearCategories: function(){
            if (confirm(this.$t("confirm_clear_categories"))){
                this.variable.var_catgry_labels=[];
            }
        },
        refreshCategories: function(){
            if (!confirm(this.$t("confirm_reload_categories"))){
                return;
            }

            labels=[];

            for (let i=0;i<this.variable.var_catgry.length;i++){
                labels.push(
                    {
                        "value":this.variable.var_catgry[i].value,
                        "labl":this.variable.var_catgry[i].labl
                    }
                )
            }

            Vue.set(this.variable, 'var_catgry_labels', labels);
        },
        GetFieldTitle: function (code, default_title='') {
            let template_field=this.FindTemplateByItemKey(this.VariableTemplate.items,code);
            if (template_field){
                return template_field.title;
            }
            return default_title;
        },
        FindTemplateByItemKey: function (items,key){            
            let item=null;
            let found=false;
            let i=0;

            while(!found && i<items.length){
                if (items[i].key==key){
                    item=items[i];
                    found=true;
                }else{
                    if (items[i].items){
                        item=this.FindTemplateByItemKey(items[i].items,key);
                        if (item){
                            found=true;
                        }
                    }
                }
                i++;                        
            }
            return item;        
        }
    },
    computed: {        
        variable:
        {
            get(){
                /*if (!this.value.var_catgry){
                    this.value.var_catgry=[{}];
                }*/
                return this.value;
            },
            set(val){
                this.$emit('update:value', val);
            }
        },
        variableCategories: function()
        {
            if (!this.variable.var_catgry){
                this.variable.var_catgry=[];
            }
            return this.variable
        },
        VariableTemplate: function()
        {
            let items=this.$store.state.formTemplate.template.items;
            let item=this.FindTemplateByItemKey(items,'variable');
            return item;        
        }
    },
    /*methods: {
        updateValue: function () {
            console.log("emitting variable change",this.value);
          this.$emit('updateVariable', this.value);
        }
    },*/   
    template: `
        <div class="variable-categories-edit-component section-list-container bg-white" >
            <!--categories--> 
            <div style="font-size:small;height:100%;" class=" pb-5" v-if="variable">
                <div class="section-title section-list-header p-1 bg-variable">
                    <div class="row">
                        <div class="col">
                        <strong>{{GetFieldTitle('variable.var_catgry',$t("categories"))}}</strong> <span v-if="variable.var_catgry && variable.var_catgry.length>0"><span class="badge badge-light">{{variable.var_catgry.length}}</span></span>

                        <div class="float-right">                           
                            <span :title="$t('create_categories')" @click="refreshCategories"><v-icon aria-hidden="false" class="var-icon">mdi-update</v-icon></span>
                            <span :title="$t('clear_all')" @click="clearCategories"><v-icon aria-hidden="false" class="var-icon">mdi-table-remove</v-icon></span>                                                                                
                        </div>
                        </div>
                    </div>
                </div>
                <div class="section-list-body">
                    <div v-if="variable.var_intrvl=='discrete'" class="section-rows">                        

                        <table-grid-component 
                            v-model="variable.var_catgry_labels" 
                            :columns="catgry_columns" 
                            class="border elevation-1 m-2 pb-2"
                            >
                        </table-grid-component>
                    </div>
                    <v-alert outlined v-else class="m-3 border text-center p-3 text-secondary">{{$t("only_for_discrete_variables")}}</v-alert>
                </div>
            </div>
            
            <!--categories-end-->            
        </div>          
        `
});


