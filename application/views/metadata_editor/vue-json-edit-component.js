/// JSON Edit Component
Vue.component('json-edit', {
    props: ['value'],
    data: function () {
        return {
            errorMessage: '',
            localValue: JSON.stringify(this.value),
            activeTab:0
        }
    },
    watch: {
        value(newVal) {
          this.localValue = JSON.stringify(newVal, null, 2);
        },
      },
    computed: {
        jsonValue() {
            if (!this.value){
                return "";
            }
            return JSON.stringify(this.value, null, 2);
        }
    },
    methods: {
        validateJSON: function (value) {
            try {
                JSON.parse(value);
                this.errorMessage = '';
                return true;
            } catch (e) {
                this.errorMessage = 'Invalid JSON';
                return false;
            }
        },
        update: function () {
            let value = this.localValue;
            if (this.validateJSON(value)) {
                this.$emit('input', JSON.parse(value));
            }
        },
        resetValue: function () {
            this.localValue = JSON.stringify(this.value, null, 2);
            this.validateJSON(this.localValue);
        },
        removeEmptyValues: function (obj) {
            if (typeof obj !== "object" || obj === null) {
              return obj;
            }

            let vm=this;
          
            const newObj = Array.isArray(obj) ? [] : {};
          
            for (const key in obj) {
              if (obj.hasOwnProperty(key)) {
                const value = vm.removeEmptyValues(obj[key]);
                if (value !== null && value !== undefined && !vm.isEmptyObject(value) && value !== "") {
                  newObj[key] = value;
                }
              }
            }
          
            return newObj;
          },
          
          isEmptyObject: function(obj) {
            return typeof obj === "object" && Object.keys(obj).length === 0;
          },
    },
    template: `
        <div class="json-edit-component">
            <v-tabs v-model="activeTab">
                <v-tab>Preview</v-tab>
                <v-tab>Edit JSON</v-tab>
            </v-tabs>
            <v-tabs-items v-model="activeTab">
                <v-tab-item>
                    <div class="bg-light p-2">
                    <pre>{{ removeEmptyValues(localValue) }}</pre>
                    </div>
                </v-tab-item>
                <v-tab-item>
                    <div class="bg-light p-2">
                    <v-textarea
                        v-model="localValue"
                        style="font-size: small;max-height:400px;overflow:auto;"
                        label=""
                        filled
                        auto-grow
                        @input="validateJSON(localValue)"
                    ></v-textarea>
                    </div>
                    <v-btn @click="update" small outlined color="primary">{{$t("update")}}</v-btn>
                    <v-btn @click="resetValue" small outlined color="default">Reset</v-btn>
                    <div v-if="errorMessage" class="error text-white p-1">{{ errorMessage }}</div>
                </v-tab-item>
            </v-tabs-items>
        </div>
    `
});