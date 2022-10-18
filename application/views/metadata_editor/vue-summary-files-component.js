///Project files summary
Vue.component('summary-files', {
    props:[],
    data: function () {    
        return {
            files:[]            
        }
    },
    created: function(){    
        this.loadData();    
    },
    
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        },
    },
    methods:{
        loadData: function() {
            vm=this;
            let url=CI.base_url + '/api/editor/files/'+this.ProjectID;
            axios.get(url)
            .then(function (response) {
                if(response.data){                    
                    vm.files=response.data.files;
                }
            })
            .catch(function (error) {
                console.log(error);
            })
            .then(function () {
                console.log("request completed");
            });
        },
    },    
    template: `
    <div class="project-summary-files-component">
        <h5>Project files</h5>

        <div class="component-container">

            <div v-for="doc in files.documentation">
                <div class="border-bottom">{{doc.file}}</div>
            </div>

            <div class="mt-3">
                <h5>Data files</h5>
                <div v-for="data in files.data">
                    <div class="border-bottom small">{{data}}</div>
                </div>
            </div>

            <div class="mt-3">
                <h5>External resources ({{files.external_resources.length}})</h5>
                <div v-for="resource in files.external_resources">
                    <div class="border-bottom p-1 small">{{resource.title}}</div>
                </div>
            </div>

        </div>

    </div>          
    `    
});

