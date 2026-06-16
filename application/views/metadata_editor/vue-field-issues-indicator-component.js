/**
 * Field Issues Indicator
 *
 * Shows warning icon + open-issues count next to the field label when the field has open issues.
 * Click opens the same issues menu as the dots trigger (via Vuex openFieldIssuesMenu).
 * Uses store openIssuesSummary (from GET .../project/{id}/summary).
 *
 * Props:
 *   - fieldPath: String - Dot path for the field
 */
Vue.component('field-issues-indicator', {
    props: {
        fieldPath: {
            type: String,
            required: true
        }
    },
    computed: {
        openCount() {
            return this.$store.getters.getOpenIssueCountByFieldPath(this.fieldPath) || 0;
        },
        hasIssues() {
            return this.openCount > 0;
        },
        chipText() {
            var n = this.openCount;
            if (n <= 0) return '';
            if (n === 1) return '1 issue';
            return n + ' issues';
        }
    },
    methods: {
        openMenu() {
            if (!this.hasIssues) return;
            this.$store.dispatch('openFieldIssuesMenu', this.fieldPath);
        }
    },
    template: `
        <v-chip
            v-if="hasIssues"
            x-small
            outlined
            color="warning"
            class="field-issues-indicator ml-1 mb-2 cursor-pointer"
            @click.stop="openMenu"
            title="Open issues for this field"            
        >
            <v-icon left small>mdi-alert</v-icon>
            {{ chipText }}
        </v-chip>
    `
});
