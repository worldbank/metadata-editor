///variable categories edit form
Vue.component('variable-categories', {
    props:['value'],
    data: function () {    
        return {   
            //variable:this.value,
            catgry_columns:[
                {
                    "key": "value",
                    "title": "Value",
                    "type": "text"
                },
                {
                    "key": "labl",
                    "title": "Label",
                    "type": "text"
                }
            ],
            variable_formats:{
                "numeric": "Numeric",
                "fixed": "Fixed string"
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
            if (confirm("Are you sure you want to clear all categories?")){
                this.variable.var_catgry_labels=[];
            }
        },
        refreshCategories: function(){
            if (!confirm("This will remove existing categories, are you sure?")){
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
                        <strong>Categories</strong> <span v-if="variable.var_catgry && variable.var_catgry.length>0"><span class="badge badge-light">{{variable.var_catgry.length}}</span></span>

                        <div class="float-right">                           
                            <span title="Create categories" @click="refreshCategories"><v-icon aria-hidden="false" class="var-icon">mdi-update</v-icon></span>
                            <span title="Clear all" @click="clearCategories"><v-icon aria-hidden="false" class="var-icon">mdi-table-remove</v-icon></span>                                                                                
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
                    <div v-else class="m-3 border text-align-center p-3 text-secondary">Only available for Discrete variables</div>
                </div>
            </div>
            
            <!--categories-end-->            
        </div>          
        `
});


