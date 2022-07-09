///variable categories edit form
Vue.component('variable-categories', {
    props:['value'],
    data: function () {    
        return {   
            variable:this.value,
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
    /*methods: {
        updateValue: function () {
            console.log("emitting variable change",this.value);
          this.$emit('updateVariable', this.value);
        }
    },*/   
    template: `
        <div class="variable-categories-edit-component" style="height:inherit">
            <!--categories-->
            <div style="font-size:small;" class="mb-2">
                <div class="section-title p-1 bg-primary"><strong>Categories</strong></div>
                <div>
                    <table-component v-model="variable.var_catgry" :columns="catgry_columns" class="border m-2 pb-2"/>
                </div>
            </div>
            <!--categories-end-->            
        </div>          
        `
});


