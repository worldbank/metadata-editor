/// Project summary page
Vue.component('summary-component', {
    data () {
        return {
          validation_errors: ""
        }
      },
    created: function(){      
        this.validateProject();
    },
    
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        },
    },
    methods:{
        validateProject: function() {
            let vm=this;
            let url=CI.base_url + '/api/editor/validate/'+this.ProjectID;

            axios.get(url)
            .then(function (response) {
                if(response.data){                    
                    console.log("validation response",response);
                }
            })
            .catch(function (error) {
                console.log("validation errors",error);                
                vm.validation_errors=error;
            })
            .then(function () {
                console.log("request completed");
            });
        },
    },     
    template: `
            <div class="summary-component mt-3">

                <div class="row">
                    <div class="col-6">
                        <div class="thumbnail-container border bg-white">
                            <project-thumbnail/>
                        </div>

                        <div class="template-selection-container border mt-3 p-3 bg-white">
                            <h5>Template</h5>
                            <div>Default template</div>
                        </div>


                        <div class="project-validation-container border mt-3 p-3 bg-white">
                            <h5>Project validation</h5>
                            <div>Project metadata</div>
                            <div class="progress mb-2">                                
                                <div class="progress-bar bg-success" role="progressbar" style="width: 25%" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>

                            <div>Data files</div>
                            <div class="progress mb-2">                            
                            <div class="progress-bar bg-info" role="progressbar" style="width: 50%" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>

                            <div>Variables</div>
                            <div class="progress mb-2">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: 75%" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>

                            <div>External resources</div>
                            <div class="progress">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>

                            <div class="validation-errors mt-2 border" v-if="validation_errors!=''" style="color:red;" >
                                <strong>Validation errors</strong>
                                <pre>{{validation_errors}}</pre>
                            </div>
                            <div class="mt-3 border" v-else>No issues found</div>
                        </div>


                    </div>
                    <div class="col-6">
                        <div class="files-container border bg-white p-3">
                            <summary-files></summary-files>
                        </div>
                    </div>
                </div>

            </div>          
            `    
});

