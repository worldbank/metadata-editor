Vue.component('page-preview', {
    props: [],
    data() {
        return {
            html: '',
            is_loading: false
        }
    },
    mounted:function(){      
        //this.loadHtml();  
    },
    methods: {
        downloadHtml: async function()
        {
            url=CI.base_url + '/api/editor/html/'+this.ProjectID + '?download=1';
            //open a new window
            window.open(url);
        },
        loadHtml: async function()
        {
            vm=this;            
            vm.is_loading=true;
            let url=CI.base_url + '/api/editor/html/'+this.ProjectID;
            
            let resp = await axios.get(url);
            vm.html=resp.data;
            vm.is_loading=false;                         
        },
                
    },
    computed: {    
        ProjectID(){
            return this.$store.state.project_id;
        },
        ProjectTemplate(){
            return this.$store.state.formTemplate;
        },
        TemplateItems()
        {
            return this.ProjectTemplate.template.items;
        }
        
    },
    template: `
        <div class="vue-page-preview-component m-3 mt-5 ">

            <div class="float-right mt-1">
                <v-btn text @click="downloadHtml" color="primary">
                    <v-icon>mdi-download</v-icon> HTML
                </v-btn>
            </div>

            <v-form-preview
                    :items="TemplateItems" 
                    title="Preview"
                >
            </v-form-preview>

            
            <div v-if="is_loading" class="text-center">
                <v-progress-circular
                    indeterminate
                    color="primary"
                ></v-progress-circular>
            </div>

            <div v-else>
                <div class="bg-light p-3" v-html="html"></div>
            </div>


        </div>
    `
});

