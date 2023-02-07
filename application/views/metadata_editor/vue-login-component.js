//v-login
Vue.component('v-login', {
    props: ['value'],
    data() {
        return {
            email:'',
            password:'',
            login_error:''
        }
    },
    mounted:function(){
    },
    methods: {
        login: function() {
            vm = this;
            let url = CI.base_url + '/auth/login?isajax=1';
            vm.login_error='';

            formData =new FormData();
            formData.append('email',this.email);
            formData.append('password',this.password);

            axios.post(url,
                formData, {
                    /*headers: {
                        'Content-Type': 'multipart/form-data'
                    }*/
                }
                ).then(function(response) {
                    vm.$emit('input', false);
                })
                .catch(function(response) {
                    vm.login_error = response.response;
                    console.log("error",response);
                });
        }
    },
    created() {
      },

    computed: {        
    },
    /*watch: {
        '$store.state.active_section': function() {
        }
    },*/
    template: `
        <div class="v-login"   >

        <template>
            <v-row justify="center">
            <v-dialog
                v-model="value"
                persistent
                max-width="500"
            >
                <v-card>
                    <button type="button"  @click="$emit('input', false)" class="float-right btn btn-default">
                        <v-icon aria-hidden="false">mdi-close</v-icon>
                    </button>

                    <v-card-title class="text-h5">Login</v-card-title>
                    <v-card-text>
                        <div class="alert alert-warning mb-3">Your session has expired. Do not refresh the page, you will lose all unsaved changes!</div>

                        <div v-if="login_error.data" class="alert alert-danger mt-2">{{login_error.data.message}}</div>

                        <div class="form-group form-field">
                            <label for="email">Email</label>
                            <input type="text" v-model="email" class="form-control">
                        </div>

                        <div class="form-group form-field">
                            <label for="password">Password</label>
                            <input type="password" v-model="password"  class="form-control">
                        </div>

                        <div class="form-grou form-field">
                            <button type="button" class="btn btn-primary btn-block" @click="login">Login</button>
                        </div>
                    </v-card-text>
                </v-card>

            </v-dialog>
            </v-row>
        </template>
            
        </div>
    `
});


