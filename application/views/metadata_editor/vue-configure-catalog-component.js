/// configure new catalog for publishing
Vue.component('configure-catalog', {
    props:['value'],
    data: function () {    
        return {          
            catalog_connections:[],
            catalog:{
                url:'https://'
            }
        }
    },
    created: function(){
        this.loadCatalogConnections();
    },
    methods:{
        loadCatalogConnections: function() {
            vm=this;
            let url=CI.base_url + '/api/editor/catalog_connections';
            axios.get(url)
            .then(function (response) {
                if(response.data){
                    vm.catalog_connections=response.data.connections;
                }
            })
            .catch(function (error) {
                console.log(error);
            })
            .then(function () {
                console.log("request completed");
            });
        },
        CreateCatalogConnection: function()
        {
            let formData = this.catalog;//new FormData();
            //formData.append('user_id', this.LoggedInUserID);
            //formData.user_id=this.LoggedInUserID;

            console.log("form data",formData);

            vm=this;
            let url=CI.base_url + '/api/editor/catalog_connections';

            axios.post( url,
                formData,
                {
                    /*headers: {
                        'Content-Type': 'multipart/form-data'
                    }*/
                }
            ).then(function(response){
                alert("Created");
                vm.catalog={};
                vm.loadCatalogConnections();
            })
            .catch(function(response){
                alert("failed");
                console.log("failed to create catalog connection",response);
            }); 
        },
    },
    
    computed: {

        LoggedInUserID(){
            return 1;
        },
        
    },  
    template: `
            <div class="configure-catalog-component">
                
                <div class="container">

                    <div class="row">                        
                        <div class="col-6">
                            <h5>Catalogs</h5>
                            <table class="table table-striped">
                                <tr>
                                    <th>Title</th>
                                    <th>Catalog URL</th>
                                </tr>
                                <tr v-for="connection in catalog_connections">
                                    <td>{{connection.title}}</td>
                                    <td>{{connection.url}}</td>
                                </tr>
                            </table>
                        </div>

                        <div class="col-6">
                            <h5 class="mb-4">Configure new catalog</h5>

                            <div class="form-group form-field">
                                <label for="titlte">Catalog title</label> 
                                <span><input type="text" id="title" class="form-control" v-model="catalog.title"/></span> 
                            </div>

                            <div class="form-group form-field">
                                <label for="url">Catalog URL</label> 
                                <span><input type="text" id="url" class="form-control" v-model="catalog.url"/></span> 
                            </div>

                            <div class="form-group form-field">
                                <label for="api_key">API Key</label> 
                                <span><input type="password" id="api_key" class="form-control" v-model="catalog.api_key"/></span>
                            </div>

                            <button type="button" class="btn btn-primary" @click="CreateCatalogConnection">Submit</button>
                        </div>
                    </div>


                </div>


            </div>          
            `    
});

