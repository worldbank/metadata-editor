/// project generate pdf documentation
Vue.component('generate-pdf', {
    props:['value'],
    data: function () {    
        return {            
            is_processing:false,
            pdf_generated:false,
            pdf_info:{}
        }
    },
    created: async function(){
        await this.getPdfInfo();
    },
    methods:{                       
        generatePDF: function()
        {
            this.is_processing=true;
            this.pdf_generated=false;
            let url=CI.base_url + '/api/editor/generate_pdf/'+this.ProjectID;
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
        }
    },  
    template: `
            <div class="import-options-component mt-5">
            
                <h3>PDF documentation</h3>
                                
                <div>Generate PDF documentation for the project</div>

                <button :disabled="is_processing" type="button" class="mt-3 btn btn-primary" @click="generatePDF()">Generate PDF</button>
                <span v-if="is_processing"><i class="fas fa-circle-notch fa-spin"></i> Generating PDF documentation...</span>

                <div class="mt-5" v-if="pdf_info">
                    <button type="button" class="btn btn-outline-primary" @click="downloadPDF()">Download PDF</button>
                    <span class="text-secondary text-muted">Created {{momentAgo(pdf_info.created)}}, Size: {{pdf_info.file_size}}</span>
                </div>

            </div>          
            `    
});

