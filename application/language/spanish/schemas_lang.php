<?php

// Basic schema terms
$lang['schemas']="Esquemas";
$lang['core']="Núcleo";
$lang['custom']="Personalizado";
$lang['core_schema']="Esquema núcleo";
$lang['schema_uid']="UID del esquema";
$lang['alias']="Alias";
$lang['schema_details']="Detalles";
$lang['schema_files_tab']="Archivos de esquema";
$lang['schema_files']="Archivos de esquema";
$lang['schema_icon']="Icono";

// Schema management
$lang['create_schema']="Crear esquema";
$lang['edit_schema']="Editar esquema";
$lang['schema_created']="Esquema creado exitosamente.";
$lang['schema_updated']="Esquema actualizado exitosamente.";
$lang['schema_deleted']="Esquema eliminado exitosamente.";
$lang['delete_schema_confirm']="¿Eliminar el esquema \"{title}\"? Se eliminarán todas las plantillas de este esquema.";
$lang['schema_delete_in_use_by_projects']="Este esquema está en uso por uno o más proyectos. Elimine o borre esos proyectos antes de poder eliminar el esquema.";
$lang['schema_not_found']="Esquema no encontrado.";
$lang['failed_to_load_schema']="Error al cargar el esquema.";
$lang['core_schema_edit_forbidden']="Los esquemas núcleo no se pueden editar.";
$lang['include_core_schemas']="Incluir esquemas núcleo";
$lang['exclude_core_schemas']="Excluir esquemas núcleo";

// Schema files
$lang['main_schema_file']="Archivo de esquema principal";
$lang['related_schema_files']="Archivos de esquema relacionados";
$lang['main_schema']="Principal";
$lang['main_schema_hint']="Suba el archivo de esquema JSON principal (obligatorio).";
$lang['related_schema_hint']="Suba archivos de esquema adicionales referenciados vía \$ref (opcional).";
$lang['main_schema_required']="El archivo de esquema principal es obligatorio.";
$lang['schema_files_loading']="Cargando archivos de esquema...";
$lang['schema_files_updated']="Archivos de esquema actualizados exitosamente.";
$lang['schema_main_replaced']="Archivo de esquema principal reemplazado exitosamente.";
$lang['schema_related_added']="Archivos de esquema relacionados subidos exitosamente.";
$lang['schema_file_deleted']="Archivo de esquema eliminado exitosamente.";
$lang['schema_file_update_failed']="La operación del archivo de esquema falló.";
$lang['failed_to_load_schema_files']="Error al cargar archivos de esquema.";
$lang['delete_schema_file_confirm']="¿Eliminar archivo de esquema {filename}?";
$lang['replace_main_schema']="Reemplazar esquema principal";
$lang['add_related_schema_files']="Agregar archivos de esquema relacionados";
$lang['no_schema_files_found']="No se encontraron archivos de esquema.";
$lang['main_schema_label']="Archivo de esquema principal";
$lang['related_schema_label']="Archivo de esquema relacionado";
$lang['schema_file_upload_label']="Subidas de archivos de esquema";
$lang['schema_file_upload_hint_create']="Seleccione todos los archivos de esquema JSON para subir. Un archivo debe marcarse como esquema principal.";
$lang['schema_file_upload_hint_edit']="Agregue nuevos archivos de esquema JSON. Elija un archivo principal solo si desea reemplazar el actual.";
$lang['select_main_schema']="Seleccionar archivo de esquema principal";
$lang['pending_main_indicator']="Esquema principal pendiente";
$lang['current_main_indicator']="Esquema principal actual";
$lang['pending_related_indicator']="Esquema relacionado pendiente";
$lang['current_related_indicator']="Esquema relacionado existente";
$lang['main_selection_optional_edit']="Deje la selección en blanco para mantener el esquema principal actual.";
$lang['upload_selected_files']="Subir archivos seleccionados";
$lang['clear_selection']="Limpiar selección";
$lang['existing_schema_files']="Archivos de esquema existentes";
$lang['schema_files_required']="Se requiere al menos un archivo de esquema.";

// Schema UID
$lang['invalid_uid']="El UID debe ser único y usar 3-64 caracteres (letras, números, guión, guión bajo).";
$lang['uid_hint']="El UID debe ser único. Caracteres permitidos: letras, números, guión y guión bajo.";

// Core field mappings
$lang['edit_core_mappings']="Editar mapeos núcleo";
$lang['core_field']="Campo núcleo";
$lang['mapped_field']="Campo mapeado";
$lang['core_field_idno']="Campo núcleo: Identificador";
$lang['core_field_title']="Campo núcleo: Título";
$lang['core_field_idno_hint']="Puntero JSON al campo identificador (ej. metadata/idno)";
$lang['core_field_title_hint']="Puntero JSON al campo título (ej. metadata/title)";
$lang['core_field_mappings']="Mapeos de campos núcleo";
$lang['core_field_mappings_hint']="Mapea campos de esquema a campos de proyecto núcleo usados en listados de proyectos, búsqueda y filtrado.";
$lang['core_mapping_status']="Mapeos";
$lang['mapping_complete']="Mapeado";
$lang['mapping_missing']="No mapeado";
$lang['save_mappings']="Guardar mapeos";
$lang['schema_title_required']="El título del esquema es requerido para guardar mapeos.";
$lang['schema_mappings_updated']="Mapeos de esquema actualizados exitosamente.";
$lang['schema_mappings_update_failed']="Error al actualizar mapeos de esquema.";
$lang['back_to_schemas']="Volver a esquemas";

// Attribute mappings
$lang['attribute_key']="Clave de atributo";
$lang['add_attribute']="Agregar atributo";
$lang['attribute_key_exists']="La clave de atributo ya existe";
$lang['idno_required']="IDNO es requerido";
$lang['title_required']="El título es requerido";

// Schema preview
$lang['preview_schema']="Vista previa del esquema";
$lang['preview_schema_tree']="Vista previa del esquema (Árbol)";
$lang['redoc_not_available']="La vista previa del esquema no está disponible (script Redoc no cargado).";
$lang['redoc_failed']="Error al renderizar la vista previa del esquema.";
$lang['openapi_json']="OpenAPI (JSON)";
$lang['openapi_yaml']="OpenAPI (YAML)";

// Template regeneration
$lang['generated']="Generado";
$lang['regenerate_template']="Regenerar plantilla";
$lang['regenerate_template_confirm']="¿Regenerar la plantilla para este esquema? La plantilla por defecto generada será reemplazada.";
$lang['schema_template_regenerated']="Plantilla de esquema regenerada exitosamente.";
$lang['regenerate_template_failed']="Error al regenerar plantilla.";
$lang['generated_template_locked']="Las plantillas generadas son de solo lectura. Duplique la plantilla para personalizarla.";

// Reserved root properties (custom schemas)
$lang['schema_has_issues']="El esquema tiene problemas. Ábralo para ver el informe de validación.";
$lang['reserved_root_properties_report_title']="Propiedades de nivel raíz reservadas detectadas";
$lang['reserved_root_properties_report_intro']="Estas propiedades de nivel raíz no se pueden guardar en los metadatos del proyecto:";
$lang['reserved_root_properties_report_fix']="Anídelas bajo un agrupamiento de objeto y luego reemplace el archivo de esquema principal.";

// Miscellaneous
$lang['not_implemented']="Aún no implementado.";

/* End of file schemas_lang.php */
/* Location: ./application/language/spanish/schemas_lang.php */