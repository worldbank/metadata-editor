Vue.component('page-preview', {
    props: [],
    data() {
        return {};
    },
    methods: {
        downloadHtml: async function()
        {
            url=CI.base_url + '/api/editor/html/'+this.ProjectID + '?download=1';
            window.open(url);
        }
    },
    computed: {    
        ProjectID(){
            return this.$store.state.project_id;
        },
        ProjectTemplate(){
            return this.$store.state.formTemplate;
        },
        templateReady() {
            const ft = this.ProjectTemplate;
            return !!(ft && ft.template && Array.isArray(ft.template.items));
        },
        TemplateItems()
        {
            if (!this.templateReady) {
                return [];
            }
            return this.ProjectTemplate.template.items;
        },
        projectIsLoading() {
            return this.$store.state.project_isloading || this.$store.state.template_isloading;
        }
    },
    template: `
        <div class="vue-page-preview-component project-preview-metadata m-3 mt-5 ">

            <div class="float-right mt-1">
                <v-btn text @click="downloadHtml" color="primary" :disabled="!ProjectID">
                    <v-icon>mdi-download</v-icon> HTML
                </v-btn>
            </div>

            <div v-if="!templateReady && projectIsLoading" class="text-center py-5">
                <v-progress-circular indeterminate color="primary"></v-progress-circular>
            </div>

            <v-form-preview
                    v-else-if="templateReady"
                    :items="TemplateItems" 
                    title="Preview"
                >
            </v-form-preview>

            <div v-else-if="!templateReady" class="text-muted py-3">
                Preview is not available until the project template has loaded.
            </div>

        </div>
    `
});
