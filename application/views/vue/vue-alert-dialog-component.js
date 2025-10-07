// Global alert dialog component
Vue.component('alert-dialog', {
  template: `
    <v-dialog v-model="visible" max-width="500" persistent>
      <v-card>
        <v-card-title class="headline">
          <v-icon left :style="{ color: iconColor }">{{ icon }}</v-icon>
          <span :class="titleClass">{{ title }}</span>
        </v-card-title>
        <v-card-text class="pt-4 pb-4" style="overflow-y: auto; max-height: 300px;">
          <div style="font-size: 16px;" class="px-4">{{ message }}</div>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn :color="buttonColor" text @click="close">OK</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  `,
  data() {
    return {
      visible: false,
      message: '',
      color: 'info',
      customTitle: '',
      resolve: null,
    };
  },
  computed: {
    title() {
      // Use custom title if provided, otherwise use default based on color
      if (this.customTitle) {
        return this.customTitle;
      }
      const titles = {
        'error': 'Error',
        'success': 'Success',
        'warning': 'Warning',
        'info': 'Information',
      };
      return titles[this.color] || 'Alert';
    },
    icon() {
      const icons = {
        'error': 'mdi-alert-circle',
        'success': 'mdi-check-circle',
        'warning': 'mdi-alert',
        'info': 'mdi-information',
      };
      return icons[this.color] || 'mdi-information';
    },
    iconColor() {
      const colors = {
        'error': '#f44336',    // Material red
        'success': '#4caf50',  // Material green
        'warning': '#ff9800',  // Material orange
        'info': '#2196f3',     // Material blue
      };
      return colors[this.color] || '#2196f3';
    },
    titleClass() {
      return this.color === 'error' ? 'error--text' : '';
    },
    buttonColor() {
      return this.color === 'error' ? 'error' : 'primary';
    }
  },
  methods: {
    show({ message, color = 'info', title = '', resolve }) {
      this.message = message;
      this.color = color;
      this.customTitle = title;
      this.resolve = resolve;
      this.visible = true;
    },
    close() {
      this.visible = false;
      if (this.resolve) {
        this.resolve();
      }
      this.message = '';
      this.customTitle = '';
      this.resolve = null;
    }
  },
  mounted() {
    EventBus.$on('alert', this.show);
  },
});

