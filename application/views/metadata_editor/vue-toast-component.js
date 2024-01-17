Vue.component('v-toast', {
    props: [],
    data() {
        return {
            snackbar: false,
            text: ``,
            isSuccess:true
        }
    },
    mounted:function(){        
        let vm=this;
        EventBus.$on('onSuccess', function(data) {
            vm.text=data;
            vm.snackbar=true;
            isSuccess=true;
          });

          EventBus.$on('onFail', function(data) {
            vm.text=data;
            vm.snackbar=true;
            isSuccess=false;
          });
    },
    methods: {       
                
    },
    computed: {        
    },
    template: `
        <div>
            <template>
                <div class="text-center ma-2">
                    
                    <v-snackbar
                    v-model="snackbar"
                    >
                    {{ text }}

                    <template v-slot:action="{ attrs }">
                        <v-btn
                        color="pink"
                        text
                        v-bind="attrs"
                        @click="snackbar = false"
                        >
                        Close
                        </v-btn>
                    </template>
                    </v-snackbar>
                </div>
            </template>

        </div>
    `
});

