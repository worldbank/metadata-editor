///prop edit componennt
Vue.component('prop-edit', {
    props:['value'],
    data: function () {    
        return {
          field_ui_types: [
            "text",
            "textarea",
            "dropdown",
            "date"
          ]
        }
    },
    mounted: function(){
      if (!this.value.enum){
        //console.log("creating ENUM", this.value);
        //this.$set(this.prop, "enum", []);
      }
    },
    
    computed: {
        prop:{           
            get(){
              return this.value;
          },
          set(val){
              this.$emit('input:value', val);
          }
        }
    },
    methods:{    
      isField: function(field_type){
        let field_types= [
          "text",
          "string",
          "number",
          "textarea",
          "dropdown",
          "date"
        ];
        return field_types.includes(field_type);
      },
      isArrayField: function(prop){
        let array_types=['array', 'nested_array', 'simple_array'];

        if (array_types.includes(prop.type) && !prop.prop){
          return true;
        }

        return false;
      },
      EnumListUpdate: function(e) {
        if (Array.isArray(e)){
          this.$set(this.prop, "enum", e);
        }
        if (!this.prop.enum) {
          this.$set(this.prop, "enum", []);
        }
      },
      DefaultUpdate: function (e){
        if (Array.isArray(e)){
          this.$set(this.prop, "default", e);
        }
        if (!this.prop.default) {
          this.$set(this.prop, "default", []);
        }
      },
      RulesUpdate: function (e)
      {
        this.$set(this.prop, "rules", e);
      }
      
    },
    template: `
            <div class="prop-edit-component">
                                    
              <div v-if="prop.key">
                <div class="form-group">
                    <label for="name">Label:</label>
                    <input type="text" class="form-control" v-model="prop.title">
                    <div class="text-secondary font-small" style="margin-top:4px;font-size:small">
                        <span class="pl-3">Name: {{prop.key}}</span>
                        <span class="pl-3">Type: {{prop.type}}</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="name">Description:</label>
                    <textarea class="form-control" v-model="prop.help_text"/>
                </div>

                <div class="form-group">
                    <label for="name">Type:</label>
                    <textarea class="form-control" v-model="prop.type"/>
                </div>
              </div>


              <?php require_once 'vue-prop-edit-component-template.php';?>

              <pre>{{prop}}</pre>
              
            </div>          
            `    
});

