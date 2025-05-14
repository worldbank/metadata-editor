<nav class="main-header sticky-top navbar navbar-expand navbar-white navbar-light bg-light border-bottom elevation-2" style="margin-left:0px;">

    <div class="pl-2" style="overflow:hidden;min-height:35px;margin-right:40px;">
        <div style="font-size:20px;" :title="Title" class="wrap-text">
            <i style="font-size:x-large;" :class="project_types_icons[dataset_type]"></i> <strong>{{Title}}</strong>
        </div>
        <!--<div>{{ProjectMetadata.idno}} </div>-->
        <?php /*
        <div class="pl-5 ml-3" v-if="is_dirty">

            <v-btn
              x-small
              color="green"
              dark
              @click="saveProject"
            >
              {{$t('Save project')}}
            </v-btn>
            <v-btn
              x-small
              dark
                @click="cancelProject"
            >
              {{$t('Cancel')}}
            </v-btn>  
        </div>
        */?>
    </div>

    <ul class="navbar-nav ml-5 ml-auto" id="projectMenuBar" ref="projectMenuBar" v-show="!hideProjectSaveOnRoute">         
        <template v-if="UserHasEditAccess">
        <template v-if="is_dirty">            
            <v-btn                
                color="primary"
                dark
                @click="saveProject"
                style="margin-top:5px;"
            >
                <v-icon left>mdi-content-save</v-icon>
                {{$t('Save')}}
            </v-btn>

            <v-tooltip bottom>
                <template v-slot:activator="{ on, attrs }">
                        <v-btn
                        color="error"
                        dark
                        icon
                        small 
                        @click="cancelProject"
                        style="margin-left:15px;margin-top:5px"
                        v-bind="attrs"
                        v-on="on"                        
                    >
                        <v-icon left>mdi-restore-alert</v-icon>            
                    </v-btn>                    
                </template>
                <span>{{$t('Cancel changes')}}</span>
            </v-tooltip>
                            
        </template>
        <template v-else>
        <v-btn
            color="secondary"
            dark
            @click="saveProject"
            style="margin-top:5px;"
        >
            <v-icon left>mdi-content-save</v-icon>
            {{$t('Save')}}
        </v-btn>
        </template>
        </template>
        <template v-else>
            <v-btn
                color="red"
                dark
                   
                outlined             
                >
                <v-icon left>mdi-content-save-off</v-icon>
                {{$t('READ ONLY')}}
            </v-btn>
        </template>

        <!--
        <v-btn color="primary"  large icon  style="margin-left:23px;">
            <v-icon>mdi-alpha-t-box-outline</v-icon>
        </v-btn>
        -->

    <template>
    <div class="text-center" v-if="UserHasEditAccess">
        <v-menu min-width="600px">
        <template v-slot:activator="{ on, attrs }">
            
            <v-btn 
                v-bind="attrs"
                v-on="on"
                icon
                large
                color="primary"
            >
                <v-icon>mdi-dots-vertical</v-icon>
            </v-btn>

        </template>

        <v-card>
        <v-container>
            <v-row>
                <v-col cols="6">
                    <v-list dense>
                    <v-subheader>Project</v-subheader>
                        <v-list-item-group       
                            color="primary"
                        >
                            <v-list-item @click="onRouterLinkClick('/project-package')">
                            <v-list-item-icon>
                                <v-icon>mdi-package-down</v-icon>
                            </v-list-item-icon>
                            <v-list-item-title>
                                <?php echo t("export_package_zip"); ?>
                            </v-list-item-title>
                            </v-list-item>
                            
                            <v-list-item v-if="dataset_type=='survey'" @click="onLinkClick(base_url + '/api/editor/ddi/' + dataset_id)">
                                <v-list-item-icon>
                                    <v-icon>mdi-file</v-icon>
                                </v-list-item-icon>
                                <v-list-item-title>                                    
                                    <?php echo t("export_ddi"); ?>
                                </v-list-item-title>
                            </v-list-item>
                            <v-list-item v-if="dataset_type=='geospatial'" @click="onLinkClick(base_url + '/api/editor/iso19139/' + dataset_id+'?download=true')">
                                <v-list-item-icon>
                                    <v-icon>mdi-file-xml-box</v-icon>
                                </v-list-item-icon>
                                <v-list-item-title>                                    
                                    <?php echo t("export_iso19139"); ?>
                                </v-list-item-title>
                            </v-list-item>
                            <v-list-item @click="export_json_dialog=true">
                                <v-list-item-icon>
                                    <v-icon>mdi-file</v-icon>
                                </v-list-item-icon>
                                <v-list-item-title>
                                    <?php echo t("export_json"); ?>
                                </v-list-item-title>
                            </v-list-item>

                            <v-list-item v-if="dataset_type=='timeseries'" @click="onLinkClick(base_url + '/api/sdmx/msd/?template_uid=' + projectTemplateUID)">
                                <v-list-item-icon>
                                    <v-icon>mdi-file</v-icon>
                                </v-list-item-icon>
                                <v-list-item-title>                                    
                                    <?php echo t("Export MSD (SDMX/XML 3.0)"); ?>
                                </v-list-item-title>
                            </v-list-item>

                            <v-list-item v-if="dataset_type=='timeseries'" @click="onLinkClick(base_url + '/api/sdmx/metadatasetreport/' + dataset_id)">
                                <v-list-item-icon>
                                    <v-icon>mdi-file</v-icon>
                                </v-list-item-icon>
                                <v-list-item-title>                                    
                                    <?php echo t("Export MetadataSet (SDMX/JSON 3.0)"); ?>
                                </v-list-item-title>
                            </v-list-item>



                            <v-list-item @click="onRouterLinkClick('/publish')">
                            <v-list-item-icon>
                                <v-icon>mdi-arrow-top-right-thick</v-icon>
                            </v-list-item-icon>
                            <v-list-item-title>
                                <?php echo t("publish_to_nada"); ?>
                            </v-list-item-title>
                            </v-list-item>
                            <v-list-item @click="onRouterLinkClick('/generate-pdf')">
                            <v-list-item-icon>
                                <v-icon>mdi-file-pdf-box</v-icon>
                            </v-list-item-icon>
                            <v-list-item-title>
                                <?php echo t("pdf_documentation"); ?>
                            </v-list-item-title>
                            </v-list-item>                             
                            <v-list-item @click="onRouterLinkClick('/change-log')">
                                <v-list-item-icon>
                                    <v-icon>mdi-content-copy</v-icon>
                                </v-list-item-icon>
                                <v-list-item-title>
                                    {{$t('Change log')}}
                                </v-list-item-title>
                            </v-list-item>
                        </v-list-item-group>
                    </v-list>
                </v-col>
                <v-col cols="6">
                    
                    <v-list dense>
                        <v-subheader>Metadata</v-subheader>
                        <v-list-item-group color="primary">
                        
                        <v-list-item @click="templateApplyDefaults">
                            <v-list-item-icon>
                                <v-icon>mdi-checkbox-multiple-marked-circle</v-icon>
                            </v-list-item-icon>
                            <v-list-item-title>                            
                                <?php echo t("apply_template_defaults"); ?>                            
                            </v-list-item-title>
                        </v-list-item>
                        <v-list-item @click="onRouterLinkClick('/import')">
                            <v-list-item-icon>
                                <v-icon>mdi-import</v-icon>
                            </v-list-item-icon>
                            <v-list-item-title>                            
                                <?php echo t("import_project_metadata"); ?>                            
                            </v-list-item-title>
                        </v-list-item>
                        <v-list-item @click="onRouterLinkClick('/external-resources/import')">
                            <v-list-item-icon>
                                <v-icon>mdi-file-import</v-icon>
                            </v-list-item-icon>
                            <v-list-item-title>
                                <?php echo t("import_external_resources"); ?>
                            </v-list-item-title>
                        </v-list-item>                                                
                        </v-list-item-group>

                        <v-subheader><?php echo t("external_resources"); ?> </v-subheader>
                        <v-list-item-group       
                            color="primary"
                        >
                        <v-list-item @click="onLinkClick(base_url + '/api/resources/rdf/' + dataset_id)">
                            <v-list-item-icon>
                                <v-icon>mdi-file</v-icon>
                            </v-list-item-icon>
                            <v-list-item-title>
                                Export RDF/XML
                            </v-list-item-title>
                        </v-list-item>
                        <v-list-item @click="onLinkClick(base_url + '/api/resources/' + dataset_id)">
                            <v-list-item-icon>
                                <v-icon>mdi-file</v-icon>
                            </v-list-item-icon>
                            <v-list-item-title>
                                Export RDF/JSON
                            </v-list-item-title>
                        </v-list-item>
                        </v-list-item-group>

                    </v-list>
                </v-col>
            </v-row>

        </v-container>
        </v-card>

        
        </v-menu>
    </div>
    </template>
    </ul>    

</nav>