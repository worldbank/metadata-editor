/// project generate pdf documentation
Vue.component('generate-pdf', {
    props:['value'],
    data: function () {    
        return {            
            is_processing:false,
            pdf_generated:false,
            pdf_info:{},
            available_templates:[],
            selected_template:'',
            include_private_fields:false,
            include_external_resources:false
        }
    },
    created: async function(){
        await this.getPdfInfo();
        await this.loadTemplates();
    },
    methods:{                       
        generatePDF: function()
        {
            this.is_processing=true;
            this.pdf_generated=false;
            let url=CI.base_url + '/api/editor/generate_pdf/'+this.ProjectID;
            
            // Build query parameters
            let params = new URLSearchParams();
            if (this.selected_template) {
                params.append('template_uid', this.selected_template);
            }
            if (this.include_private_fields) {
                params.append('include_private_fields', '1');
            }
            if (this.include_external_resources) {
                params.append('include_external_resources', '1');
            }
            
            if (params.toString()) {
                url += '?' + params.toString();
            }
            
            let vm=this;
            return axios
                .get(url)
                .then(function (response) {
                    console.log(response);
                    vm.is_processing=false;
                    vm.pdf_generated=true;
                    vm.getPdfInfo();
                })
                .catch(function (error) {
                    alert(JSON.stringify(error.response));
                    console.log(error);
                    vm.is_processing=false;
                });                
        },
        downloadPDF: function()
        {
            let url=CI.base_url + '/api/editor/pdf/'+this.ProjectID;
            window.open(url, '_blank');
        },
        getPdfInfo: function()
        {
            let url=CI.base_url + '/api/editor/pdf_info/'+this.ProjectID;
            let vm=this;
            return axios
                .get(url)
                .then(function (response) {
                    console.log(response);
                    vm.pdf_info=response.data.info;
                })
                .catch(function (error) {
                    console.log(error);
                    vm.pdf_info={};
                });                
        },
        momentAgo(date) {
            let utc_date = moment(date, "YYYY-MM-DD HH:mm:ss").toDate();
            return moment.utc(date).fromNow();
        },
        loadTemplates: function() {
            let url = CI.base_url + '/api/templates/list/' + this.ProjectType;
            let vm = this;
            return axios
                .get(url)
                .then(function (response) {
                    vm.available_templates = response.data.result || [];
                    // Load project's current template
                    vm.loadProjectTemplate();
                })
                .catch(function (error) {
                    console.log('Error loading templates:', error);
                    vm.available_templates = [];
                });
        },
        loadProjectTemplate: function() {
            let url = CI.base_url + '/api/editor/basic_info/' + this.ProjectID;
            let vm = this;
            return axios
                .get(url)
                .then(function (response) {
                    if (response.data.project && response.data.project.template_uid) {
                        vm.selected_template = response.data.project.template_uid;
                    }
                })
                .catch(function (error) {
                    console.log('Error loading project template:', error);
                });
        },
        
    },    
    computed: {        
        ProjectID(){
            return this.$store.state.project_id;
        },        
        ProjectType(){
            return this.$store.state.project_type;
        },
        PdfLink()
        {
            return CI.base_url + '/api/editor/pdf/'+this.ProjectID;
        },
        templateOptions() {
            const options = [
                { text: this.$t("use_default_template"), value: '' }
            ];
            
            this.available_templates.forEach(template => {
                options.push({
                    text: `${template.name} (${template.template_type}${template.lang ? ', ' + template.lang : ''})`,
                    value: template.uid
                });
            });
            
            return options;
        }
    },  
    template: `
        <div class="pdf-component">
            <div class="container-fluid pt-5 mt-5 mb-5 pb-5">
                <v-card>
                    <v-card-title>{{$t("pdf_documentation")}}</v-card-title>
                    <v-card-text>
                        <div class="mb-4">{{$t("pdf_documentation_note")}}</div>

                        <!-- PDF Generation Options -->
                        <v-card outlined class="mb-4">
                            <v-card-text>
                                <!-- Template Selection -->
                                <div class="mb-2">{{$t("select_template")}}</div>
                                <v-select
                                    v-model="selected_template"
                                    :items="templateOptions"
                                    label=""
                                    outlined
                                    dense
                                    class="mb-1"
                                ></v-select>

                                <!-- Field Options -->
                                <v-checkbox
                                    v-model="include_private_fields"
                                    :label="$t('include_private_fields')"
                                    :hint="$t('include_private_fields_desc')"
                                    persistent-hint
                                    :class="'normal-label'"
                                    class="mb-1"
                                ></v-checkbox>

                                <!-- External Resources -->
                                <v-checkbox
                                    v-model="include_external_resources"
                                    :label="$t('include_external_resources')"
                                    :hint="$t('include_external_resources_desc')"
                                    persistent-hint
                                    :class="'normal-label'"
                                    class="mb-1"
                                ></v-checkbox>
                            </v-card-text>
                        </v-card>

                        <!-- Action Buttons -->
                        <div class="mb-4">
                            <div class="d-flex align-center">
                                <v-btn 
                                    :disabled="is_processing" 
                                    color="primary" 
                                    large 
                                    @click="generatePDF()"
                                    class="mr-3"
                                >
                                    <v-icon left>mdi-file-pdf-box</v-icon>
                                    {{$t("generate_pdf")}}
                                </v-btn>
                                
                                <v-btn 
                                    v-if="pdf_info"
                                    color="success" 
                                    large 
                                    @click="downloadPDF()"
                                >
                                    <v-icon left>mdi-download</v-icon>
                                    {{$t("download_pdf")}} [{{$t("created")}} {{momentAgo(pdf_info.created)}}]
                                </v-btn>
                            </div>
                            
                            <div v-if="is_processing" class="mt-3">
                                <v-progress-circular indeterminate color="primary" class="d-inline-block"></v-progress-circular>
                                <span class="ml-2">{{$t("processing_please_wait")}}</span>
                            </div>
                        </div>

                    </v-card-text>
                </v-card>
            </div>
        </div>          
        `,
    mounted: function() {
        // Add custom CSS for normal font weight labels
        const style = document.createElement('style');
        style.textContent = `
            .normal-label .v-label {
                font-weight: normal !important;
            }
            .normal-label {
                font-weight: normal !important;
            }
        `;
        document.head.appendChild(style);
    }
});

