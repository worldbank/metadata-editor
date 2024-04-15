<nav class="main-header sticky-top navbar navbar-expand navbar-white navbar-light bg-light border-bottom" style="margin-left:0px;">

    <div class="pl-2" style="overflow:hidden;min-height:35px;margin-right:40px;">
        <div style="font-size:20px;" :title="Title" class="wrap-text">
            <i style="font-size:x-large;" :class="project_types_icons[dataset_type]"></i> <strong>{{Title}}</strong>
        </div>
        <div>{{ProjectMetadata.idno}}</div>
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

    <?php /*
    <ul class="navbar-nav ml-5 ml-auto">

        <div class="dropdown">
            <a class="btn btn-link dropdown-toggle" href="#" role="button" id="dropdownProjectMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="far fa-folder-open"></i> <?php echo t("project"); ?>
            </a>

            <div class="dropdown-menu" aria-labelledby="dropdownProjectMenu">
                <a class="dropdown-item" href="#/project-package">
                    <span style="font-size:20px;" class="mdi mdi-package-down"></span> <?php echo t("export_package_zip"); ?></a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="#/publish">
                    <span style="font-size:20px;" class="mdi mdi-arrow-top-right-thick"></span> <?php echo t("publish_to_nada"); ?></a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="#/generate-pdf"><span style="font-size:20px;" class="mdi mdi-file-pdf-box"></span> <?php echo t("pdf_documentation"); ?></a>
            </div>
        </div>

        <div class="dropdown">
            <a class="btn btn-link dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-random"></i> <?php echo t("metadata"); ?>
            </a>

            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuLink">
                <a class="dropdown-item" href="#/import"><i class="fas fa-file-invoice"></i> <?php echo t("import_project_metadata"); ?></a>
                <a class="dropdown-item" href="#/external-resources/import"><i class="fas fa-clone"></i> <?php echo t("import_external_resources"); ?></a>
                <div class="dropdown-divider"></div>
                <a v-if="dataset_type=='survey'" class="dropdown-item" :href="'<?php echo site_url('api/editor/ddi/'); ?>' + dataset_id" target="_blank"><i class="far fa-file-alt"></i> <?php echo t("export_ddi"); ?></a>
                <a class="dropdown-item" :href="'<?php echo site_url('api/editor/json/'); ?>' + dataset_id" target="_blank"><i class="far fa-file-code"></i> <?php echo t("export_json"); ?></a>
                <a class="dropdown-item" :href="'<?php echo site_url('api/resources/rdf/'); ?>' + dataset_id" target="_blank"><i class="far fa-file-alt"></i> <?php echo t("export_external_resources"); ?> (RDF/XML)</a>
                <a class="dropdown-item" :href="'<?php echo site_url('api/resources/'); ?>' + dataset_id" target="_blank"><i class="far fa-file-alt"></i> <?php echo t("export_external_resources"); ?> (JSON)</a>
            </div>
        </div>
    </ul>
    */?>

    <ul class="navbar-nav ml-5 ml-auto">
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

    <template>
    <div class="text-center">        
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
                            <v-list-item>
                            <v-list-item-icon>
                                <v-icon>mdi-package-down</v-icon>
                            </v-list-item-icon>
                            <v-list-item-title>
                                <a href="#/project-package"><?php echo t("export_package_zip"); ?></a>
                            </v-list-item-title>
                            </v-list-item>
                            
                            <v-list-item v-if="dataset_type=='survey'" >
                                <v-list-item-icon>
                                    <v-icon>mdi-file</v-icon>
                                </v-list-item-icon>
                                <v-list-item-title>                                
                                    <a :href="'<?php echo site_url('api/editor/ddi/'); ?>' + dataset_id" target="_blank"><?php echo t("export_ddi"); ?></a>
                                </v-list-item-title>
                            </v-list-item>
                            <v-list-item>
                                <v-list-item-icon>
                                    <v-icon>mdi-file</v-icon>
                                </v-list-item-icon>
                                <v-list-item-title>
                                    <a  :href="'<?php echo site_url('api/editor/json/'); ?>' + dataset_id" target="_blank"><?php echo t("export_json"); ?></a>                                
                                </v-list-item-title>
                            </v-list-item>
                            <v-list-item>
                            <v-list-item-icon>
                                <v-icon>mdi-arrow-top-right-thick</v-icon>
                            </v-list-item-icon>
                            <v-list-item-title>
                                <a href="#/publish"><?php echo t("publish_to_nada"); ?></a>
                            </v-list-item-title>
                            </v-list-item>
                            <v-list-item>
                            <v-list-item-icon>
                                <v-icon>mdi-file-pdf-box</v-icon>
                            </v-list-item-icon>
                            <v-list-item-title>
                                <a  href="#/generate-pdf"><?php echo t("pdf_documentation"); ?></a>
                            </v-list-item-title>
                            </v-list-item>                                                        
                        </v-list-item-group>
                    </v-list>
                </v-col>
                <v-col cols="6">
                    
                    <v-list dense>
                        <v-subheader>Metadata</v-subheader>
                        <v-list-item-group       
                            color="primary"
                        >
                        
                        <v-list-item>
                        <v-list-item-icon>
                            <v-icon>mdi-import</v-icon>
                        </v-list-item-icon>
                            <v-list-item-title>
                            <router-link
                                to="/import">
                                <?php echo t("import_project_metadata"); ?>
                            </router-link>
                            </v-list-item-title>
                        </v-list-item>
                        <v-list-item>
                            <v-list-item-icon>
                                <v-icon>mdi-file-import</v-icon>
                            </v-list-item-icon>
                            <v-list-item-title>
                                <a href="#/external-resources/import"><?php echo t("import_external_resources"); ?></a>
                            </v-list-item-title>
                        </v-list-item>                                                
                        </v-list-item-group>

                        <v-subheader><?php echo t("external_resources"); ?> </v-subheader>
                        <v-list-item-group       
                            color="primary"
                        >
                        <v-list-item>
                            <v-list-item-icon>
                                <v-icon>mdi-file</v-icon>
                            </v-list-item-icon>
                            <v-list-item-title>
                                <a :href="'<?php echo site_url('api/resources/rdf/'); ?>' + dataset_id" target="_blank"> Export RDF/XML</a>
                            </v-list-item-title>
                        </v-list-item>
                        <v-list-item>
                            <v-list-item-icon>
                                <v-icon>mdi-file</v-icon>
                            </v-list-item-icon>
                            <v-list-item-title>
                            <a :href="'<?php echo site_url('api/resources/'); ?>' + dataset_id" target="_blank"> Export JSON</a>
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