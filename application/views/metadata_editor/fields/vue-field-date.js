//text field control
Vue.component('editor-date-field', {
    props: ['value','field'],
    data: function () {    
      return {
        menu1: false,
        date:''
      }
  },
    methods:{
      isFieldReadOnly() {
        if (!this.$store.getters.getUserHasEditAccess) {
          return true;
        }
  
        return this.field.is_readonly;
      },
      momentDateISO(date) {        
        return moment(date).toISOString();        
      },
      momentDate(date) {        
        return moment(date).format("YYYY-MM-DD");
      },

      parseDate (date) {
        if (!date) return null

        return this.momentDate(date);
      },
      
    },
    computed:{
      Value(){
        if (!this.value){
          return null;
        }

        return this.momentDate(this.value);
      }
    },
    watch: {
      date (val) {
        if (!val){
          this.$emit('input', val);
        }else{
          this.$emit('input', this.momentDateISO(val));
        }
      },
    },
    template: `
    <div class="date-field">    
        <v-menu
          v-model="menu1"
          :close-on-content-click="false"
          max-width="290"
        >
          <template v-slot:activator="{ on, attrs }">
            <v-text-field
              :value="Value"
              clearable
              readonly
              v-bind="attrs"
              v-on="on"
              dense
              solo
              @click:clear="date = null"
              prepend-inner-icon="mdi-calendar"
              :hint="'Date format: YYYY-MM-DD - ' + value"
              persistent-hint
              :disabled="isFieldReadOnly"
            ></v-text-field>            
          </template>
          <v-date-picker
            v-model="date"
            @change="menu1 = false"
            :disabled="isFieldReadOnly"
          ></v-date-picker>
        </v-menu>
    </div>
    `
  });