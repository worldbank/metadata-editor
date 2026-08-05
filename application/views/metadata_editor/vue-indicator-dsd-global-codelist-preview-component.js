// Read-only preview of registry (global) codelist codes — wraps shared codes grid.
Vue.component('indicator-dsd-global-codelist-preview', {
    props: {
        registryCodelistId: {
            default: null,
            validator: function (v) {
                return v === null || v === undefined || v === '' || typeof v === 'number' || typeof v === 'string';
            }
        },
        codelistName: {
            type: String,
            default: ''
        }
    },
    template: `
        <global-codelist-codes-grid
            class="indicator-dsd-global-codelist-preview mt-2"
            :registry-codelist-id="registryCodelistId"
            :title="codelistName"
            max-table-height="280px"
            :page-size-default="50"
        ></global-codelist-codes-grid>
    `
});
