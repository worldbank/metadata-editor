<nav class="main-header sticky-top navbar navbar-expand navbar-white navbar-light bg-light border-bottom" style="margin-left:0px;">

<?php /*
    <ul class="navbar-nav">
        <li class="nav-item">
            <a href="<?php echo site_url(); ?>" title="<?php echo t("home"); ?>" role="button" class="nav-link"><i class="mdi mdi-home-outline"></i>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo site_url('editor'); ?>" class="nav-link"><i class="mdi mdi-folder-multiple-outline"></i> <?php echo t("my_projects"); ?>
            </a>
        </li>

        <li class="nav-item">
            <a href="<?php echo site_url('editor/templates'); ?>" role="button" class="nav-link btn btn-link"><i class="mdi mdi-alpha-t-box-outline"></i> <?php echo t("templates"); ?></a>
        </li>
    </ul>

    */?>

    <div class="pl-2" style="overflow:hidden;height:35px;">
        <div style="font-size:20px;" :title="Title"><i style="font-size:x-large;" :class="project_types_icons[dataset_type]"></i> <strong>{{Title}}</strong></div>
    </div>

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

        <li class="nav-item">
            <?php echo $this->load->view('user_menu/user-menu', null, true); ?>
        </li>

    </ul>



</nav>