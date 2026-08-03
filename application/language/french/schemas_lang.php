<?php

// Basic schema terms
$lang['schemas']="Schémas";
$lang['core']="Noyau";
$lang['custom']="Personnalisé";
$lang['core_schema']="Schéma noyau";
$lang['schema_uid']="UID du schéma";
$lang['alias']="Alias";
$lang['schema_details']="Détails";
$lang['schema_files_tab']="Fichiers de schéma";
$lang['schema_files']="Fichiers de schéma";
$lang['schema_icon']="Icône";

// Schema management
$lang['create_schema']="Créer un schéma";
$lang['edit_schema']="Modifier le schéma";
$lang['schema_created']="Schéma créé avec succès.";
$lang['schema_updated']="Schéma mis à jour avec succès.";
$lang['schema_deleted']="Schéma supprimé avec succès.";
$lang['delete_schema_confirm']="Supprimer le schéma « {title} » ? Tous les modèles de ce schéma seront supprimés.";
$lang['schema_delete_in_use_by_projects']="Ce schéma est utilisé par un ou plusieurs projets. Supprimez ou retirez ces projets avant de pouvoir supprimer le schéma.";
$lang['schema_not_found']="Schéma non trouvé.";
$lang['failed_to_load_schema']="Échec du chargement du schéma.";
$lang['core_schema_edit_forbidden']="Les schémas noyau ne peuvent pas être modifiés.";
$lang['include_core_schemas']="Inclure les schémas noyau";
$lang['exclude_core_schemas']="Exclure les schémas noyau";

// Schema files
$lang['main_schema_file']="Fichier de schéma principal";
$lang['related_schema_files']="Fichiers de schéma associés";
$lang['main_schema']="Principal";
$lang['main_schema_hint']="Téléchargez le fichier de schéma JSON principal (obligatoire).";
$lang['related_schema_hint']="Téléchargez des fichiers de schéma supplémentaires référencés via \$ref (optionnel).";
$lang['main_schema_required']="Le fichier de schéma principal est obligatoire.";
$lang['schema_files_loading']="Chargement des fichiers de schéma...";
$lang['schema_files_updated']="Fichiers de schéma mis à jour avec succès.";
$lang['schema_main_replaced']="Fichier de schéma principal remplacé avec succès.";
$lang['schema_related_added']="Fichiers de schéma associés téléchargés avec succès.";
$lang['schema_file_deleted']="Fichier de schéma supprimé avec succès.";
$lang['schema_file_update_failed']="L'opération sur le fichier de schéma a échoué.";
$lang['failed_to_load_schema_files']="Échec du chargement des fichiers de schéma.";
$lang['delete_schema_file_confirm']="Supprimer le fichier de schéma {filename} ?";
$lang['replace_main_schema']="Remplacer le schéma principal";
$lang['add_related_schema_files']="Ajouter des fichiers de schéma associés";
$lang['no_schema_files_found']="Aucun fichier de schéma trouvé.";
$lang['main_schema_label']="Fichier de schéma principal";
$lang['related_schema_label']="Fichier de schéma associé";
$lang['schema_file_upload_label']="Téléchargements de fichiers de schéma";
$lang['schema_file_upload_hint_create']="Sélectionnez tous les fichiers de schéma JSON à télécharger. Un fichier doit être marqué comme schéma principal.";
$lang['schema_file_upload_hint_edit']="Ajoutez de nouveaux fichiers de schéma JSON. Choisissez un fichier principal uniquement si vous souhaitez remplacer l'actuel.";
$lang['select_main_schema']="Sélectionner le fichier de schéma principal";
$lang['pending_main_indicator']="Schéma principal en attente";
$lang['current_main_indicator']="Schéma principal actuel";
$lang['pending_related_indicator']="Schéma associé en attente";
$lang['current_related_indicator']="Schéma associé existant";
$lang['main_selection_optional_edit']="Laissez la sélection vide pour conserver le schéma principal actuel.";
$lang['upload_selected_files']="Télécharger les fichiers sélectionnés";
$lang['clear_selection']="Effacer la sélection";
$lang['existing_schema_files']="Fichiers de schéma existants";
$lang['schema_files_required']="Au moins un fichier de schéma est requis.";

// Schema UID
$lang['invalid_uid']="L'UID doit être unique et utiliser 3-64 caractères (lettres, chiffres, tiret, trait de soulignement).";
$lang['uid_hint']="L'UID doit être unique. Caractères autorisés : lettres, chiffres, tiret et trait de soulignement.";

// Core field mappings
$lang['edit_core_mappings']="Modifier les mappages noyau";
$lang['core_field']="Champ noyau";
$lang['mapped_field']="Champ mappé";
$lang['core_field_idno']="Champ noyau : Identifiant";
$lang['core_field_title']="Champ noyau : Titre";
$lang['core_field_idno_hint']="Pointeur JSON vers le champ identifiant (ex. metadata/idno)";
$lang['core_field_title_hint']="Pointeur JSON vers le champ titre (ex. metadata/title)";
$lang['core_field_mappings']="Mappages des champs noyau";
$lang['core_field_mappings_hint']="Mappe les champs de schéma aux champs de projet noyau utilisés dans les listes de projets, la recherche et le filtrage.";
$lang['core_mapping_status']="Mappages";
$lang['mapping_complete']="Mappé";
$lang['mapping_missing']="Non mappé";
$lang['save_mappings']="Sauvegarder les mappages";
$lang['schema_title_required']="Le titre du schéma est requis pour sauvegarder les mappages.";
$lang['schema_mappings_updated']="Mappages de schéma mis à jour avec succès.";
$lang['schema_mappings_update_failed']="Échec de la mise à jour des mappages de schéma.";
$lang['back_to_schemas']="Retour aux schémas";

// Attribute mappings
$lang['attribute_key']="Clé d'attribut";
$lang['add_attribute']="Ajouter un attribut";
$lang['attribute_key_exists']="La clé d'attribut existe déjà";
$lang['idno_required']="IDNO est requis";
$lang['title_required']="Le titre est requis";

// Schema preview
$lang['preview_schema']="Prévisualiser le schéma";
$lang['preview_schema_tree']="Prévisualiser le schéma (Arbre)";
$lang['redoc_not_available']="La prévisualisation du schéma n'est pas disponible (script Redoc non chargé).";
$lang['redoc_failed']="Échec du rendu de la prévisualisation du schéma.";
$lang['openapi_json']="OpenAPI (JSON)";
$lang['openapi_yaml']="OpenAPI (YAML)";

// Template regeneration
$lang['generated']="Généré";
$lang['regenerate_template']="Régénérer le modèle";
$lang['regenerate_template_confirm']="Régénérer le modèle pour ce schéma ? Le modèle par défaut généré sera remplacé.";
$lang['schema_template_regenerated']="Modèle de schéma régénéré avec succès.";
$lang['regenerate_template_failed']="Échec de la régénération du modèle.";
$lang['generated_template_locked']="Les modèles générés sont en lecture seule. Dupliquez le modèle pour le personnaliser.";

// Miscellaneous
$lang['not_implemented']="Pas encore implémenté.";

/* End of file schemas_lang.php */
/* Location: ./application/language/french/schemas_lang.php */