/// Project summary page
Vue.component('summary-component', {
    data () {
        return {
          project_edit_stats:{},
          project_disk_usage:{},
        }
      },
    created: function(){
        this.getProjectEditStats();
        this.getProjectDiskUsage();
    },
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        },
        ProjectIDNo(){
            return this.$store.state.idno;
        },
        projectTemplateUID(){
            return this.$store.state.formTemplate.uid;
        },
        ProjectVersionInfo(){
            return this.$store.state.project_version_info;
        },
        ProjectType(state){
            return this.$store.state.project_type;
        },
        summaryTemplatesComponentKey(){
            return String(this.ProjectID) + '-' + String(this.projectTemplateUID || '');
        }
    },
    methods:{
        momentDate(date) {
            return moment.utc(date).local().format("YYYY-MM-DD HH:mm:ss");
          },
        getProjectEditStats: function() {
            let vm=this;
            let url=CI.base_url + '/api/editor/edit_stats/'+this.ProjectID;

            axios.get(url)
            .then(function (response) {
                if (response.data && response.data.info){
                    vm.project_edit_stats=response.data.info;
                }
            })
            .catch(function (error) {
                console.log("edit_stats_failed",error);
            });
        },
        getProjectDiskUsage: function() {
            let vm=this;
            let url=CI.base_url + '/api/files/size/'+this.ProjectID;

            axios.get(url)
            .then(function (response) {
                if (response.data && response.data.result){
                    vm.project_disk_usage=response.data.result;
                }
            })
            .catch(function (error) {
                console.log("disk_usage_stats_failed",error);
            });
        },
    },
    template: `
            <div class="summary-component mt-3 container-fluid">

                <div class="row">
                    <div class="col-12">
                        <v-card>
                            <v-card-text>
                            <div class="row">
                            <div class="col-3" >
                                <div class="thumbnail-container">
                                    <project-thumbnail/>
                                </div>
                            </div>
                            <div class="col-9" >

                            <!-- project info -->
                            <div class="project-info-container row">
                                <div class="col-6">

                                    <div class="mb-3">
                                        <strong>{{$t("Project owner")}}:</strong>
                                        <div class="text-capitalize">{{project_edit_stats.username_cr}}</div>
                                    </div>

                                    <div class="mb-3">
                                        <strong>{{$t("Last changed by")}}:</strong>
                                        <div class="text-capitalize">{{project_edit_stats.username}}</div>
                                    </div>

                                    <div class="mb-3">
                                        <strong>{{$t("Project IDNO")}}:</strong>
                                        <div class="text-capitalize">{{ProjectIDNo}}</div>
                                    </div>


                                </div>
                                <div class="col-6">

                                    <div class="mb-3">
                                        <strong>{{$t("Created on")}}:</strong>
                                        <div>{{momentDate(project_edit_stats.created)}}</div>
                                    </div>

                                    <div class="mb-3">
                                        <strong>{{$t("Changed on")}}:</strong>
                                        <div>{{momentDate(project_edit_stats.changed)}}</div>
                                    </div>

                                    <div class="mb-3">
                                        <strong>{{$t("version")}}:</strong>
                                        <div v-if="ProjectVersionInfo && ProjectVersionInfo.version_number">{{ProjectVersionInfo.version_number}}</div>
                                        <div v-else>{{$t('latest')}}</div>
                                    </div>

                                </div>

                            </div>

                            <!-- end -->


                            </div>
                            </div>

                            </v-card-text>
                        </v-card>
                    </div>

                    <div class="col-6">
                        <div>
                            <summary-templates-component :key="summaryTemplatesComponentKey"></summary-templates-component>
                        </div>

                        <div class="project-validation-container">
                            <template-validation-component></template-validation-component>
                        </div>
                    </div>

                    <div class="col-6" >

                        <div class="mb-5">
                            <!-- project sharing -->
                            <vue-summary-sharing-stats></vue-summary-sharing-stats>
                        </div>

                        <div class="mb-5">
                            <!-- project collections -->
                            <vue-summary-collections></vue-summary-collections>
                        </div>

                        <div class="mb-5">
                            <!-- project tags -->
                            <vue-project-tags></vue-project-tags>
                        </div>

                        <v-card>
                            <v-card-title class="d-flex justify-space-between">
                                <h6>{{$t("Data and Documentation")}}</h6>

                                <div v-if="project_disk_usage.size_formatted">
                                    <v-chip color="light" small style="font-size:small">{{$t("Disk usage")}}: {{project_disk_usage.size_formatted}}</v-chip>
                                </div>

                            </v-card-title>
                            <v-card-text>
                                <div class="files-container" v-if="ProjectType!=='timeseries-db'" style="max-height:400px;overflow:auto;">
                                <summary-files v-on:file-deleted="getProjectDiskUsage" ></summary-files>
                                </div>
                            </v-card-text>
                        </v-card>

                    </div>

                </div>

            </div>
            `
});
