/**
 * Issue Status Badge Component
 * 
 * Displays a colored badge for issue status
 * 
 * Props:
 *   - status: String - Issue status (open, accepted, rejected, fixed, dismissed, false_positive)
 *   - small: Boolean - Small size badge (default: false)
 */
Vue.component('issue-status-badge', {
    props: {
        status: {
            type: String,
            default: 'open',
            validator: function(value) {
                return ['open', 'accepted', 'rejected', 'fixed', 'dismissed', 'false_positive'].includes(value);
            }
        },
        small: {
            type: Boolean,
            default: false
        }
    },
    computed: {
        badgeColor() {
            const colors = {
                'open': 'primary',
                'accepted': 'success',
                'rejected': 'error',
                'fixed': 'success',
                'dismissed': 'grey',
                'false_positive': 'warning'
            };
            return colors[this.status] || 'grey';
        },
        badgeText() {
            const labels = {
                'open': 'Open',
                'accepted': 'Accepted',
                'rejected': 'Rejected',
                'fixed': 'Fixed',
                'dismissed': 'Dismissed',
                'false_positive': 'False Positive'
            };
            return labels[this.status] || this.status;
        },
        badgeIcon() {
            const icons = {
                'open': 'mdi-alert-circle-outline',
                'accepted': 'mdi-check-circle',
                'rejected': 'mdi-close-circle',
                'fixed': 'mdi-check-all',
                'dismissed': 'mdi-minus-circle',
                'false_positive': 'mdi-alert'
            };
            return icons[this.status];
        }
    },
    template: `
        <v-chip 
            :color="badgeColor" 
            :small="small"
            outlined
            :class="{'text-capitalize': true}"
        >
            <v-icon v-if="badgeIcon" left :small="small">{{ badgeIcon }}</v-icon>
            {{ badgeText }}
        </v-chip>
    `
});
