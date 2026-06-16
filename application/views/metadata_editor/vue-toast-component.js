Vue.component('v-toast', {
    props: [],
    data() {
        return {
            snackbar: false,
            text: '',
            isSuccess: true,
            timeout: 4000
        };
    },
    mounted: function () {
        var vm = this;
        EventBus.$on('onSuccess', function (data) {
            vm.showToast(data, true, 4000);
        });
        EventBus.$on('onFail', function (data) {
            vm.showToast(data, false, 8000);
        });
    },
    methods: {
        showToast: function (data, isSuccess, defaultTimeout) {
            var text = '';
            var timeout = defaultTimeout;
            if (data && typeof data === 'object' && data.message != null) {
                text = String(data.message);
                if (data.timeout != null && !isNaN(parseInt(data.timeout, 10))) {
                    timeout = parseInt(data.timeout, 10);
                }
            } else if (data != null) {
                text = String(data);
            }
            this.text = text;
            this.isSuccess = isSuccess;
            this.timeout = timeout;
            this.snackbar = false;
            var vm = this;
            this.$nextTick(function () {
                vm.snackbar = true;
            });
        }
    },
    template: `
        <div>
            <template>
                <div class="text-center ma-2">
                    <v-snackbar
                        right
                        :timeout="timeout"
                        :color="isSuccess ? 'success' : 'error'"
                        v-model="snackbar"
                    >
                        {{ text }}
                        <template v-slot:action="{ attrs }">
                            <v-btn text v-bind="attrs" @click="snackbar = false">
                                Close
                            </v-btn>
                        </template>
                    </v-snackbar>
                </div>
            </template>
        </div>
    `
});
