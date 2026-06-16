/**
 * Chooses CSV file explorer vs DuckDB timeseries explorer by project type and file_id.
 */
Vue.component('data-explorer-host', {
    props: ['file_id'],
    computed: {
        useDuckdbExplorer: function() {
            var t = this.$store.getters.getProjectType;
            var fid = this.$route.params.file_id || this.file_id;
            return (t === 'indicator' || t === 'timeseries') && fid === 'INDICATOR_DATA';
        }
    },
    template: `
        <div>
            <indicator-data-page v-if="useDuckdbExplorer" :file_id="file_id" />
            <datafile-data-explorer v-else :file_id="file_id" />
        </div>
    `
});
