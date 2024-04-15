Vue.component('vue-collection', {
    props: ['value'],
    data() {
        return {     
            collections: [],
            edit_collection: {},
            dialog_edit: false,
            active_tab:1,
            site_base_url: CI.base_url
        }
    },
    
    created: function() {
        this.loadCollections();
    },
    computed: {
        Collections() {
            return this.collections;
        }
    },
    methods: {
        momentDate(date) {
            return moment.utc(date).format("MMM d, YYYY")
        },
        loadCollections: function() {
            vm = this;
            let url = CI.base_url + '/api/collections';
            console.log("loading collections");
            return axios
                .get(url)
                .then(function(response) {
                    vm.collections = response.data.collections;
                    console.log("loading collections",vm.collections);
                })
                .catch(function(error) {
                    console.log("error", error);
                });
        },
        editCollection: function(index) {
            console.log("edit", index, this.collections,this.collections[index]);
            this.edit_collection = JSON.parse(JSON.stringify(this.collections[index]));
            this.dialog_edit = true;
        },
        createCollection: function() {
            this.edit_collection = {};
            this.dialog_edit = true;
        },
        updateCollection: function(collection) {
            this.dialog_edit = false;
            let url = CI.base_url + '/api/collections';

            if (collection.id) {
                url = CI.base_url + '/api/collections/update/' + collection.id;
            }

            let form_data = collection;

            axios.post(url,
                    form_data
                )
                .then(function(response) {
                    console.log(response);
                    vm.loadCollections();
                })
                .catch(function(error) {
                    console.log("error", error);
                    alert("Failed", error);
                });
        },
        DeleteCollection: function(id) {
            if (!confirm("Are you sure you want to delete the collection?")) {
                return false;
            }

            vm = this;
            let url = CI.base_url + '/api/collections/delete/' + id;

            axios.post(url)
                .then(function(response) {
                    vm.loadCollections();
                })
                .catch(function(error) {
                    console.log("error", error);
                    alert("Failed", error);
                });
        }
    },
    template: `
    <div class="vue-collection-component">
        

            <vue-edit-collection v-model="dialog_edit" :collection="edit_collection" v-on:update-collection="updateCollection" vonremove-access="UnshareProjectWithUser"></vue-edit-collection>
            
            <section class="container">

                    <div class="row">

                        <div class="projects col">

                            <h3 class="mt-3 mb-5">Collections</h3>

                            <v-tabs background-color="transparent" v-model="active_tab">
                                <v-tab><v-icon>mdi-text-box</v-icon> <a :href="site_base_url + '/editor'">{{$t("projects")}}</a></v-tab>
                                <v-tab active><v-icon>mdi-folder-text</v-icon> <a :href="site_base_url + '/collections'">{{$t("collections")}}</a> </v-tab>
                                <!--<v-tab>Archives</v-tab>-->
                                <v-tab><v-icon>mdi-alpha-t-box</v-icon> <a :href="site_base_url + '/templates'">{{$t("templates")}}</a></v-tab>
                            </v-tabs>


                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-sm btn-primary" @click="createCollection">Create new collection</button>
                            </div>

                            <div>
                                <div class="mt-5 p-3 border text-center text-danger" v-if="!Collections || Collections.found<1"> No projects found!</div>

                                <div v-if="!Collections || Collections.found>0" class="row mb-2 border-bottom  mt-3">
                                    <div class="col-md-6">
                                        <div class="p-2" v-if="Collections">
                                            <strong>{{parseInt(collections.found)}}</strong> collections
                                        </div>
                                    </div>
                                </div>

                                <div v-for="(collection,index) in Collections" class="row">
                                    <div class="col-md-1">
                                        <span class="mdi mdi-folder-table" style="font-size:45px;color:green"></span>
                                    </div>
                                    <div class="col  border-bottom">
                                        <h5 class="wb-card-title title">
                                            <a href="#" :title="collection.title" class="d-flex" @click="editCollection(index)">
                                                <span>{{collection.title}}</span>
                                            </a>
                                        </h5>
                                        <div class="text-secondary">
                                            {{collection.description}}
                                        </div>

                                        <div class="survey-stats mt-3 text-small text-muted">
                                            <span class="mr-3"><span class="wb-label">Last modified:</span> <span class="wb-value">{{momentDate(collection.changed)}}</span></span>
                                            <span><span class="wb-label">Created by:</span> <span class="wb-value capitalize">{{collection.username}}</span></span>
                                            <span class="ml-4 float-right">
                                                <a class="btn btn-xs btn-outline-primary" @click="editCollection(index)" href="#">Edit</a>
                                                <a class="btn btn-xs btn-outline-danger" @click="DeleteCollection(collection.id)" href="#">Delete</a>
                                                <a class="btn btn-xs btn-outline-primary" :href="'#/manage-users/' + collection.id">Manage access</a>
                                            </span>
                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>
            </section>
        </div>
                
    </div>
    `
});

