<?php

// ─── Navigation & headings ────────────────────────────────────────────────────
$lang['issues']                         = "Problemas";
$lang['issues_list']                    = "Lista de problemas";
$lang['project_issues']                 = "Problemas del proyecto";
$lang['create_issue']                   = "Crear nuevo problema";
$lang['edit_issue']                     = "Editar problema";
$lang['issue_detail']                   = "Detalle del problema";
$lang['back_to_issues']                 = "Volver a problemas";
$lang['open']                           = "Abierto";
$lang['closed']                         = "Cerrado";
$lang['open_issues']                    = "Problemas abiertos";
$lang['closed_issues']                  = "Problemas cerrados";

// ─── Field labels ─────────────────────────────────────────────────────────────
$lang['issue_title']                    = "Título";
$lang['issue_description']             = "Descripción";
$lang['issue_category']                = "Categoría";
$lang['issue_severity']                = "Gravedad";
$lang['issue_status']                  = "Estado";
$lang['issue_field_path']              = "Ruta del campo";
$lang['issue_current_value']           = "Valor actual";
$lang['issue_suggested_value']         = "Valor sugerido";
$lang['issue_notes']                   = "Notas";
$lang['issue_source']                  = "Fuente";
$lang['issue_project']                 = "Proyecto";
$lang['issue_field_reference']         = "Referencia del campo";
$lang['issue_resolution']              = "Resolución";
$lang['issue_activity']                = "Actividad";
$lang['issue_diff']                    = "Diferencia";
$lang['issue_applied']                 = "Aplicado";
$lang['issue_not_applied']             = "No aplicado";

// ─── Status values ────────────────────────────────────────────────────────────
$lang['status_open']                   = "Abierto";
$lang['status_accepted']               = "Aceptado";
$lang['status_fixed']                  = "Resuelto";
$lang['status_rejected']               = "Rechazado";
$lang['status_dismissed']              = "Descartado";
$lang['status_false_positive']         = "Falso positivo";
$lang['status_all']                    = "Todos";
$lang['status_all_open']               = "Todos los abiertos";
$lang['status_all_closed']             = "Todos los cerrados";

// ─── Severity values ──────────────────────────────────────────────────────────
$lang['severity_low']                  = "Baja";
$lang['severity_medium']               = "Media";
$lang['severity_high']                 = "Alta";
$lang['severity_critical']             = "Crítica";
$lang['severity_all']                  = "Todas las gravedades";

// ─── Category values ──────────────────────────────────────────────────────────
$lang['category_typo_wording']         = "Error tipográfico / Redacción";
$lang['category_inconsistency']        = "Inconsistencia";
$lang['category_missing_data']         = "Datos faltantes";
$lang['category_format_issue']         = "Problema de formato";
$lang['category_completeness']         = "Completitud";
$lang['category_other']                = "Otro";
$lang['category_all']                  = "Todas las categorías";

// ─── Filter & sort labels ─────────────────────────────────────────────────────
$lang['filter_status']                 = "Estado";
$lang['filter_severity']               = "Gravedad";
$lang['filter_category']               = "Categoría";
$lang['filter_applied']                = "Aplicado";
$lang['filter_clear']                  = "Limpiar filtros";
$lang['filter_clear_all']              = "Limpiar todo";
$lang['filter_applied_yes']            = "Aplicado";
$lang['filter_applied_no']             = "No aplicado";
$lang['sort_newest']                   = "Más reciente primero";
$lang['sort_oldest']                   = "Más antiguo primero";
$lang['sort_title_az']                 = "Título A–Z";
$lang['sort_title_za']                 = "Título Z–A";
$lang['sort_severity']                 = "Gravedad (alta primero)";

// ─── Placeholders ────────────────────────────────────────────────────────────
$lang['placeholder_search_issues']     = "Buscar problemas...";
$lang['placeholder_issue_title']       = "Título breve del problema";
$lang['placeholder_issue_description'] = "Describa el problema en detalle...";
$lang['placeholder_issue_category']    = "Seleccione una categoría";
$lang['placeholder_issue_field_path']  = "p. ej., series_description.methodology";
$lang['placeholder_issue_current']     = "Valor actual del campo";
$lang['placeholder_issue_suggested']   = "Lo que debería cambiarse";
$lang['placeholder_notes']             = "Notas o comentarios...";
$lang['placeholder_not_set']           = "No definido";

// ─── Hints ───────────────────────────────────────────────────────────────────
$lang['hint_field_path']               = "Identifica el campo de metadatos específico al que se refiere este problema";

// ─── Button / action labels ───────────────────────────────────────────────────
$lang['action_create_issue']           = "Crear problema";
$lang['action_save']                   = "Guardar";
$lang['action_cancel']                 = "Cancelar";
$lang['action_delete']                 = "Eliminar";
$lang['action_edit']                   = "Editar";
$lang['action_view']                   = "Ver";
$lang['action_accept']                 = "Aceptar";
$lang['action_reject']                 = "Rechazar";
$lang['action_dismiss']                = "Descartar";
$lang['action_mark_fixed']             = "Marcar como resuelto";
$lang['action_false_positive']         = "Falso positivo";
$lang['action_reopen']                 = "Reabrir";
$lang['action_apply_to_field']         = "Aplicar al campo";
$lang['action_bulk_actions']           = "Acciones en lote";
$lang['action_delete_selected']        = "Eliminar seleccionados";
$lang['action_maximize']               = "Maximizar";
$lang['action_restore']                = "Restaurar";
$lang['action_close']                  = "Cerrar";

// ─── Bulk action labels ───────────────────────────────────────────────────────
$lang['bulk_accept']                   = "Aceptar";
$lang['bulk_dismiss']                  = "Descartar";
$lang['bulk_reject']                   = "Rechazar";
$lang['bulk_false_positive']           = "Marcar como falso positivo";
$lang['bulk_delete']                   = "Eliminar";

// ─── Metadata assessment ──────────────────────────────────────────────────────
$lang['assess_metadata']               = "Evaluar metadatos";
$lang['assessment_running']            = "Evaluación en curso";
$lang['assessment_view_status']        = "Ver estado";
$lang['assessment_complete']           = "Evaluación completada";
$lang['assessment_cancel']             = "Cancelar tarea";
$lang['assessment_description']        = "Esto enviará los metadatos del proyecto al servicio de evaluación de calidad. Los problemas detectados se agregarán a la lista de problemas y se mostrarán junto a los campos correspondientes.";
$lang['assessment_async_note']         = "No es necesario esperar a que finalice la evaluación. Puede salir de esta página y volver más tarde; los problemas aparecerán cuando la evaluación se complete.";
$lang['assessment_worker_warning']     = "El trabajador no está en ejecución. La tarea de evaluación podría no avanzar hasta que el trabajador inicie.";

// ─── Success messages ─────────────────────────────────────────────────────────
$lang['issue_created']                 = "Problema creado exitosamente";
$lang['issue_updated']                 = "Problema actualizado exitosamente";
$lang['issue_deleted']                 = "Problema eliminado exitosamente";
$lang['issue_saved']                   = "Guardado";
$lang['issues_updated']                = ":count problema(s) actualizado(s)";
$lang['issues_deleted']                = ":count problema(s) eliminado(s)";
$lang['changes_applied']               = "Cambios aplicados a los metadatos del proyecto";

// ─── Error messages ───────────────────────────────────────────────────────────
$lang['error_load_issue']              = "Error al cargar el problema";
$lang['error_issue_not_found']         = "Problema no encontrado";
$lang['error_save_issue']              = "Error al guardar";
$lang['error_create_issue']            = "Error al crear el problema";
$lang['error_update_issue']            = "Error al actualizar el problema";
$lang['error_delete_issue']            = "Error al eliminar el problema";
$lang['error_update_status']           = "Error al actualizar el estado";
$lang['error_apply_changes']           = "Error al aplicar los cambios";
$lang['error_load_issues']             = "Error al cargar los problemas";
$lang['error_invalid_json']            = "Formato JSON no válido o ruta de campo no definida";
$lang['error_no_field_path']           = "Este problema no tiene ruta de campo";
$lang['error_metadata_not_loaded']     = "Los metadatos del proyecto no están cargados";
$lang['error_enter_value']             = "Ingrese un valor para aplicar";

// ─── Validation messages ──────────────────────────────────────────────────────
$lang['validation_required_fields']    = "Por favor complete los campos obligatorios";
$lang['validation_title_required']     = "El título es obligatorio";
$lang['validation_select_issues']      = "Por favor seleccione problemas primero";

// ─── Confirmation prompts ─────────────────────────────────────────────────────
$lang['confirm_delete_issue']          = "¿Está seguro de que desea eliminar este problema?";
$lang['confirm_delete_selected']       = "¿Eliminar :count problema(s) seleccionado(s)? Esta acción no se puede deshacer.";
$lang['confirm_apply_to_field']        = "¿Aplicar este valor al campo de metadatos del proyecto?";

// ─── Empty & loading states ───────────────────────────────────────────────────
$lang['no_issues_found']               = "No se encontraron problemas";
$lang['loading_issues']                = "Cargando problemas...";
$lang['loading_issue']                 = "Cargando problema...";
$lang['try_adjusting_filters']         = "Intente ajustar sus filtros";
$lang['issue_is_closed']               = "Este problema está cerrado.";

// ─── Activity / meta ──────────────────────────────────────────────────────────
$lang['activity_created']              = "Creado";
$lang['activity_created_by']           = "Creado :date por :user";
$lang['activity_resolved']             = "Resuelto :date por :user";
$lang['activity_assigned_to']          = "Asignado a :user";
$lang['activity_applied']              = "Aplicado :date por :user";
$lang['activity_selected']             = ":count seleccionado(s)";
