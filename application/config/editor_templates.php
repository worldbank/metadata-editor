<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Metadata editor core templates
|--------------------------------------------------------------------------
|
| This file should have the template configurations for each data type
| such as survey, geospatial, time series, dublin core, etc.
|
|
| @template - path to the view file 
| 
| @language_translations - language file containing the translations for fields names/labels
|
*/

$config['survey'][]=array(
        'template' => 'metadata_editor/metadata_editor_templates/survey_form_template.json',
        'lang'=>'en',        
        'uid'=>'microdata-system-en',
        'name'=>'Microdata DDI 2.5 EN'
);

$config['survey'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/survey_template_ihsn_2.5_v1_fr.json',
    'lang'=>'fr',
    'uid'=>'232ea3aaece0cdf1db157f797f6b92e5fr',
    'name'=>'IHSN DDI 2.5 Modèle v01 FR',
    "version"=> "1.0",
    "organization"=> "IHSN \/ Banque mondiale, Development Data Group",
    "author"=> "MA TB OD MW",
    "description"=> "Un modèle basé sur le standard DDI 2.5 (DDI Codebook), recommandé par le IHSN pour la documentation de données d'enquêtes ou de recensements. ",
    "created"=>"1687445920"
);


$config['survey'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/survey_template_ihsn_2.5_v1.json',
    "uid"=> "6740f5f920502baf3f6cbcaa5c113deeen",
    "data_type"=> "survey",
    "lang"=> "en",
    "name"=> "IHSN DDI 2.5 Template v01 EN",
    "version"=> "1.0",
    "organization"=> "IHSN \/ World Bank Development Data Group",
    "author"=> "MA TB OD MW",
    "description"=> "A DDI 2.5 (DDI Codebook) template recommended by the International Household Survey Network (IHSN) for the documentation of survey and census datasets.   ",
    "created"=>"1687445920"
);


$config['timeseries'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/timeseries_form_template.json',
    'lang'=>'en',
    'uid'=>'timeseries-system-en',
    'name'=>'Indicator Schema 1.0 EN',
    'version'=>'1.0',
    'description'=>'This template contains all elements available in the metadata schema developed by the IHSN for the documentation of indicators and time series of indicators. It can be used as basis for the production of user templates consisting of a subset of the available elements, with customized labels, instructions, ordering and grouping of metadata elements, controlled vocabularies, validation rules, and default values.'
); 

$config['timeseries'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/timeseries_template_ihsn.json',    
    "data_type"=> "timeseries",
    "uid"=> "8603d94e27bccc2bdad1e00dbbf0fe32en",
    "data_type"=> "timeseries",
    "lang"=> "en",
    "name"=> "IHSN INDICATOR 1.0 Template v01 EN",
    "version"=> "1.0",
    "organization"=> "IHSN",
    "author"=> "MA OD",
    "description"=> "A template for the documentation of indicators (or time series).",    
); 


$config['timeseries'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/timeseries_template_ihsn_fr.json',    
    "uid"=> "b576eb7519fe8e761a239f9d36f032c3fr",
    "data_type"=> "timeseries",
    "lang"=> "fr",
    "name"=> "IHSN INDICATOR 1.0 Modèle v01 FR",
    "version"=> "1.0",
    "organization"=> "IHSN",
    "author"=> "MA OD",
    "description"=> "Un modèle pour la documentation des indicateurs (ou des séries temporelles).",
); 



$config['timeseries-db'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/timeseries-db_form_template.json',
    'lang'=>'en',
    'uid'=>'timeseries-db-system-en',
    'name'=>'Database IHSN Schema 1.0 EN'
); 


$config['timeseries-db'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/timeseries-db_template_ihsn_fr.json',
    "uid"=> "776d77524fe8130f520035fb9b077d82",
    "data_type"=> "timeseries-db",
    "lang"=> "fr",
    "name"=> "IHSN BASE DE DONNEES 1.0 Modèle v01 FR",
    "version"=> "1.0",
    "organization"=> "IHSN",
    "author"=> "MA OD",
    "description"=> "Ce modèle contient tous les éléments du schéma de métadonnées développé par l'IHSN pour la documentation des bases de données d'indicateurs et de séries chronologiques. Il peut être utilisé comme base pour la production de modèles d'utilisateurs où des vocabulaires contrôlés spécifiques à l'agence, des règles de validation, des valeurs par défaut peuvent être appliqués à un sous-ensemble sélectionné d'éléments de métadonnées disponibles.\nCe schéma est utilisé pour compléter l'\"Indicateur\" schéma de métadonnées. Il est utilisé pour fournir des informations sur la base de données qui sert de \"container\" pour les indicateurs.",    
); 


$config['timeseries-db'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/timeseries-db_template_ihsn.json',
    "uid"=> "f3e2d1c8c494be27bc229463a265a33d",
    "data_type"=> "timeseries-db",
    "lang"=> "en",
    "name"=> "IHSN DATABASE 1.0 Template v01 EN",
    "version"=> "1.0",
    "organization"=> "IHSN",
    "author"=> "MA OD",
    "description"=> "This template contains all elements of the metadata schema developed by the IHSN for the documentation of databases of indicators and time series. It can be used as basis for the production of user templates where agency-specific controlled vocabularies, validation rules, default values can be applied to a selected subset of the available metadata elements.\nThis schema is used to complement the \"Indicator\" metadata schema. It is used to provide information on the database that serves as \"container\" for the indicators.",
); 



$config['script'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/script_form_template.json',
    'lang'=>'en',
    'uid'=>'script-system-en',
    'name'=>'Script IHSN Schema 1.0 EN'
); 


$config['script'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/script_template_ihsn_fr.json',
    "uid"=> "d31789f2d6dcdf3c7dc07a5729d09ac9fr",
    "data_type"=> "script",
    "lang"=> "fr",
    "name"=> "IHSN SCRIPT 1.0 Modèle v01 FR",
    "version"=> "1.0",
    "organization"=> "IHSN",
    "author"=> "MA OD",
    "description"=> "Ce modèle contient tous les éléments disponibles dans le schéma de métadonnées développé par l'IHSN pour la documentation des projets et des scripts de recherche et d'analyse. Il peut être utilisé comme base pour la production de modèles d'utilisateurs constitués d'un sous-ensemble des éléments disponibles, avec des étiquettes personnalisées, des instructions, l'ordre et le regroupement des éléments de métadonnées, des vocabulaires contrôlés, des règles de validation et des valeurs par défaut.",    
); 

$config['script'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/script_template_ihsn.json',
    "uid"=> "d0e54377885c64c360259b65398e319den",
    "data_type"=> "script",
    "lang"=> "en",
    "name"=> "IHSN SCRIPT 1.0 Template v01 EN",
    "version"=> "1.0",
    "organization"=> "IHSN",
    "author"=> "MA OD",
    "description"=> "This template contains all elements available in the metadata schema developed by the IHSN for the documentation of research and analysis projects and scripts. It can be used as basis for the production of user templates consisting of a subset of the available elements, with customized labels, instructions, ordering and grouping of metadata elements, controlled vocabularies, validation rules, and default values.",    
); 


//geospatial
$config['geospatial'][]=array(
        'template' => 'metadata_editor/metadata_editor_templates/geospatial_form_template.json',
        'lang'=>'en',
        'uid'=>'geospatial-system-en',
        'name'=>'Geospatial schema'
);

$config['geospatial'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/geospatial_template_ihsn_fr.json',
    "uid"=> "geospatial-gemini-inspire-en",
    "data_type"=> "geospatial",
    "lang"=> "en",
    "name"=> "Inspire/Gemini with additional elements",
    "version"=> "1.0",
    "organization"=> "World Bank",
    "author"=> "MA OD",
    "description"=> "Geospatial template based on Inspire and Gemini 2.3 \n\n- https://agiorguk.github.io/gemini/1062-gemini-datasets-and-data-series.html",    
);


//document
$config['document'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/document_form_template.json',
    'lang'=>'en',
    'uid'=>'document-system-en',
    'name'=>'Document IHSN Schema 1.0 EN'
);

$config['document'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/document_template_ihsn_fr.json',
    "uid"=> "4915f93564fbde26945dd9022b9d7fc1fr",
    "data_type"=> "document",
    "lang"=> "fr",
    "name"=> "IHSN DOCUMENT 1.0 Modèle v01 FR ",
    "version"=> "1.0",
    "organization"=> "IHSN",
    "author"=> "MA OD",
    "description"=> "Modèle contenant tous les éléments du schéma de métadonnées développé pour la documentation des documents (livres, publications, articles, rapports, manuels, etc.) Ce modèle peut être utilisé comme base pour la production de modèles personnalisés où les vocabulaires contrôlés spécifiques à l'agence, les règles de validation , les valeurs par défaut peuvent être appliquées à un sous-ensemble sélectionné des éléments de métadonnées disponibles. Le modèle utilise des éléments du Dublin Core (par la Dublin Core Metadata Initiative), BibTex, MARC21 (par la US Library of Congress) et d'autres normes et schémas.",    
);



$config['document'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/document_template_ihsn.json',
    "uid"=> "2f62a6b2716ab55b4426005abdbe1600en",
    "data_type"=> "document",
    "lang"=> "en",
    "name"=> "IHSN DOCUMENT 1.0 Template v01 EN",
    "version"=> "1.0",
    "organization"=> "IHSN",
    "author"=> "MA OD",
    "description"=> "Template containing all elements of the metadata schema developed for the documentation of documents (books, publications, papers, reports, manuals, etc.) This template can be used as basis for the production of custom templates where agency-specific controlled vocabularies, validation rules, default values can be applied to a selected subset of the available metadata elements. The template makes use of elements from the Dublin Core (by the Dublin Core Metadata Initiative), BibTex, MARC21 (by the US Library of Congress), and other standards and schemas. ",
);





//table
$config['table'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/table_form_template.json',
    'lang'=>'en',
    'uid'=>'table-system-en',
    'name'=>'Table IHSN Schema 1.0 EN'
);

$config['table'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/table_template_ihsn.json',
    "uid"=> "4b56b6c4ec82324c7c2865ad61c4f2c0en",
    "data_type"=> "table",
    "lang"=> "en",
    "name"=> "IHSN TABLE 1.0 Template v01 EN",
    "version"=> "1.0",
    "organization"=> "IHSN",
    "author"=> "MA OD",
    "description"=> "This template contains all elements available in the metadata schema developed by the IHSN for the documentation of statistical tables (cross-tabulations). It can be used as basis for the production of user templates consisting of a subset of the available elements, with customized labels, instructions, ordering and grouping of metadata elements, controlled vocabularies, validation rules, and default values.",
);

$config['table'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/table_template_ihsn_fr.json',
    "uid"=> "9454eee369c79c65cdc0b4ee23aed4e8fr",
    "data_type"=> "table",
    "lang"=> "fr",
    "name"=> "IHSN TABLEAU 1.0 Modèle v01 FR",
    "version"=> "1.0",
    "organization"=> "IHSN",
    "author"=> "MA OD",
    "description"=> "Ce modèle contient tous les éléments disponibles dans le schéma de métadonnées développé par l'IHSN pour la documentation des tableaux statistiques (tableaux croisés). Il peut être utilisé comme base pour la production de modèles d'utilisateurs constitués d'un sous-ensemble des éléments disponibles, avec des étiquettes personnalisées, des instructions, l'ordre et le regroupement des éléments de métadonnées, des vocabulaires contrôlés, des règles de validation et des valeurs par défaut.",
);





//image
$config['image'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/image_form_template.json',
    'lang'=>'en',
    'uid'=>'image-system-en',
    'name'=> 'Image IHSN Schema (DCMI and IPTC)'
); 

$config['image'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/image_dcmi_form_template.json',
    'lang'=>'en',
    'uid'=>'image-system-dcmi',
    'name'=> 'Image IHSN Schema (DCMI option) 1.0 EN'
); 

$config['image'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/image_iptc_form_template.json',
    'lang'=>'en',
    'uid'=>'image-system-iptc',
    'name'=> 'Image IHSN Schema (IPTC option) 1.0 EN'
); 


$config['image'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/image_dcmi_template_ihsn_fr.json',
    "uid"=> "6192765f2dbddb6bd93f3f92a29129d3fr",
    "data_type"=> "image",
    "lang"=> "fr",
    "name"=> "IHSN IMAGE DCMI Modèle 1.0 FR",
    "version"=> "1.0",
    "organization"=> "IHSN",
    "author"=> "MA OD",
    "description"=> "Modèle contenant tous les éléments du schéma de métadonnées IHSN pour la documentation des images, basé sur les éléments DCMI (une autre option consiste à utiliser le modèle basé sur IPTC). Ce modèle peut être utilisé comme base pour la production de modèles personnalisés où des vocabulaires contrôlés spécifiques à l'agence, des règles de validation, des valeurs par défaut peuvent être appliqués à un sous-ensemble sélectionné des éléments de métadonnées disponibles.",    
); 


$config['image'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/image_dcmi_template_ihsn.json',
    "uid"=> "0d8e25111cee667a4b4636088cdb33e3",
    "data_type"=> "image",
    "lang"=> "en",
    "name"=> "IHSN IMAGE DCMI Template 1.0 EN",
    "version"=> "1.0",
    "organization"=> "IHSN",
    "author"=> "MA OD",
    "description"=> "Template containing all elements of the IHSN metadata schema for the documentation of images, based on DCMI elements (another option is to use the IPTC-based template). This template can be used as basis for the production of custom templates where agency-specific controlled vocabularies, validation rules, default values can be applied to a selected subset of the available metadata elements.",
); 


$config['image'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/image_iptc_template_ihsn_fr.json',
    "uid"=> "21769091754bb7c998a1caf0fa75800cfr",
    "data_type"=> "image",
    "lang"=> "fr",
    "name"=> "IHSN IMAGE IPTC 2022.1 Modèle v01 FR",
    "version"=> "1.0 - IPTC 2022.1",
    "organization"=> "IHSN",
    "author"=> "MA OD",
    "description"=> "Modèle contenant tous les éléments du schéma de métadonnées IHSN pour la documentation des images, basé sur les éléments IPTC 2022.1 (une autre option consiste à utiliser le modèle basé sur Dublin Core\/DCMI). Ce modèle peut être utilisé comme base pour la production de modèles personnalisés où des vocabulaires contrôlés spécifiques à l'agence, des règles de validation, des valeurs par défaut peuvent être appliqués à un sous-ensemble sélectionné des éléments de métadonnées disponibles.",
); 


$config['image'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/image_iptc_template_ihsn.json',
    "uid"=> "2bfd3fb47e291331a43e949e9e38675een",
    "data_type"=> "image",
    "lang"=> "en",
    "name"=> "IHSN IMAGE IPTC 2022.1 Template v01 EN",
    "version"=> "1.0 - IPTC 2022.1",
    "organization"=> "IHSN",
    "author"=> "MA OD",
    "description"=> "Template containing all elements of the IHSN metadata schema for the documentation of images, based on IPTC 2022.1 elements (another option is to use the Dublin Core\/DCMI-based template). This template can be used as basis for the production of custom templates where agency-specific controlled vocabularies, validation rules, default values can be applied to a selected subset of the available metadata elements.",
); 








//visualization
/*$config['visualization']=array(
    'template' => 'metadata_templates/visualization-template',
    'language_translations'=>'fields_visualization'
);*/ 

//video
$config['video'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/video_form_template.json',
    'lang'=>'en',
    'uid'=>'video-system-en',
    'name'=>'Video IHSN Schema 1.0 EN'
); 


$config['video'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/video_template_ihsn_fr.json',
    "uid"=> "e3e5a1d8bd0338c1b3a5d6bfff8e5517",
    "data_type"=> "video",
    "lang"=> "fr",
    "name"=> "IHSN VIDEO 1.0 Modèle v01 FR",
    "version"=> "1.0",
    "organization"=> "IHSN",
    "author"=> "MA OD",
    "description"=> "Ce modèle contient tous les éléments disponibles dans le schéma de métadonnées développé par l'IHSN pour la documentation des enregistrements vidéo. Il utilise des éléments du Dublin Core et de schema.org\/videoObject. Le modèle peut être utilisé comme base pour la production de modèles utilisateur constitués d'un sous-ensemble des éléments disponibles, avec des étiquettes personnalisées, des instructions, l'ordre et le regroupement des éléments de métadonnées, des vocabulaires contrôlés, des règles de validation et des valeurs par défaut.",
);

$config['video'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/video_template_ihsn.json',
    "uid"=> "1f899824931c133f162f584c928d4256",
    "data_type"=> "video",
    "lang"=> "en",
    "name"=> "IHSN VIDEO 1.0 Template v01 EN",
    "version"=> "1.0",
    "organization"=> "IHSN",
    "author"=> "MA OD",
    "description"=> "This template contains all elements available in the metadata schema developed by the IHSN for the documentation of video recordings. It makes use of elements from the Dublin Core and from schema.org\/videoObject. The template can be used as basis for the production of user templates consisting of a subset of the available elements, with customized labels, instructions, ordering and grouping of metadata elements, controlled vocabularies, validation rules, and default values.",
);


$config['resource'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/resource_form_template.json',
    'lang'=>'en',
    'uid'=>'resource-system-en',
    'name'=>'resource-system-en'
); 


$config['resource'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/resource_template_ihsn.json',
    "uid"=> "e6670ad469892a3871ee0e5d47cf243den",
    "data_type"=> "resource",
    "lang"=> "en",
    "name"=> "IHSN EXT RESOURCES 1.0 Template v01 EN",
    "version"=> "v01",
    "organization"=> "IHSN",
    "author"=> "MA TB OD",
    "description"=> "Template used to document external resources. This template is used in combination with all other standards\/schemas.",
); 


$config['resource'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/resource_template_ihsn_fr.json',
    "uid"=> "453a8bf9f800465f3cb8dc37699cb574",
    "data_type"=> "resource",
    "lang"=> "fr",
    "name"=> "IHSN RESSOURCES EXT 1.0 Modèle v01 FR",
    "version"=> "v01",
    "organization"=> "IHSN",
    "author"=> "MA TB OD",
    "description"=> "Modèle utilisé pour documenter les ressources externes. Ce modèle est utilisé en combinaison avec tous les autres standards\/schémas.",
); 


$config['admin_meta'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/admin_metadata_template.json',
    "uid"=> "system-core-admin-meta",
    "data_type"=> "admin_meta",
    "lang"=> "EN",
    "name"=> "Core administrative metadata template",
    "version"=> "1.0.0",
    "organization"=> "",
    "author"=> "",
    "description"=> "Base template for the documentation of administrative metadata.",
); 

