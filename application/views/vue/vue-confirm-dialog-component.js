// Global confirm dialog component
Vue.component('confirm-dialog', {
  template: `
    <v-dialog v-model="dialogVisible" persistent max-width="600">
      <v-card>
        <v-card-title class="text-h5 border-bottom">Confirmation</v-card-title>
        <v-card-text class="text-center pt-5 pb-5"><span class="h5">{{ message }}</span></v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="green darken-1" text @click="confirm">Confirm</v-btn>
          <v-btn color="red darken-1" text @click="cancel">Cancel</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  `,
  data() {
    return {
      dialogVisible: false,
      message: '',
      resolve: null,
      reject: null,
    };
  },
  methods: {
    showConfirmDialog({ message, resolve, reject }) {
      this.message = message;
      this.dialogVisible = true;
      this.resolve = resolve;
      this.reject = reject;
    },
    confirm() {
      if (this.resolve) {
        this.resolve(true);
      }
      this.closeDialog();
    },
    cancel() {
      if (this.resolve) {
        this.resolve(false);
      }
      this.closeDialog();
    },
    closeDialog() {
      this.dialogVisible = false;
      this.message = '';
      this.resolve = null;
      this.reject = null;
    },
  },
  mounted() {
    EventBus.$on('confirm', this.showConfirmDialog);
  },
  beforeDestroy() {
    EventBus.$off('confirm', this.showConfirmDialog);
  },
});