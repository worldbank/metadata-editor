<?php

// Basic schema terms
$lang['schemas']="Sxemalar";
$lang['core']="Asosiy";
$lang['custom']="Maxsus";
$lang['core_schema']="Asosiy sxema";
$lang['schema_uid']="Sxema UID";
$lang['alias']="Taxallus";
$lang['schema_details']="Tafsilotlar";
$lang['schema_files_tab']="Sxema fayllari";
$lang['schema_files']="Sxema fayllari";
$lang['schema_icon']="Belgisi";

// Schema management
$lang['create_schema']="Sxema yaratish";
$lang['edit_schema']="Sxemani tahrirlash";
$lang['schema_created']="Sxema muvaffaqiyatli yaratildi.";
$lang['schema_updated']="Sxema muvaffaqiyatli yangilandi.";
$lang['schema_deleted']="Sxema muvaffaqiyatli o'chirildi.";
$lang['delete_schema_confirm']="\"{title}\" sxemasini o'chirish? Ushbu sxema uchun barcha shablonlar o'chiriladi.";
$lang['schema_delete_in_use_by_projects']="Bu sxema bir yoki bir nechta loyiha tomonidan ishlatilmoqda. Sxemani o'chirishdan oldin ushbu loyihalarni olib tashlang yoki o'chiring.";
$lang['schema_not_found']="Sxema topilmadi.";
$lang['failed_to_load_schema']="Sxemani yuklashda xatolik.";
$lang['core_schema_edit_forbidden']="Asosiy sxemalarni tahrirlash mumkin emas.";
$lang['include_core_schemas']="Asosiy sxemalarni qo'shish";
$lang['exclude_core_schemas']="Asosiy sxemalarni chiqarib tashlash";

// Schema files
$lang['main_schema_file']="Asosiy sxema fayli";
$lang['related_schema_files']="Bog'liq sxema fayllari";
$lang['main_schema']="Asosiy";
$lang['main_schema_hint']="Asosiy JSON sxema faylini yuklang (majburiy).";
$lang['related_schema_hint']="\$ref orqali havola qilingan qo'shimcha sxema fayllarini yuklang (ixtiyoriy).";
$lang['main_schema_required']="Asosiy sxema fayli majburiy.";
$lang['schema_files_loading']="Sxema fayllari yuklanmoqda...";
$lang['schema_files_updated']="Sxema fayllari muvaffaqiyatli yangilandi.";
$lang['schema_main_replaced']="Asosiy sxema fayli muvaffaqiyatli almashtirildi.";
$lang['schema_related_added']="Bog'liq sxema fayllari muvaffaqiyatli yuklandi.";
$lang['schema_file_deleted']="Sxema fayli muvaffaqiyatli o'chirildi.";
$lang['schema_file_update_failed']="Sxema fayli operatsiyasi muvaffaqiyatsiz.";
$lang['failed_to_load_schema_files']="Sxema fayllarini yuklashda xatolik.";
$lang['delete_schema_file_confirm']="Sxema fayli {filename}ni o'chirishmi?";
$lang['replace_main_schema']="Asosiy sxemani almashtirish";
$lang['add_related_schema_files']="Bog'liq sxema fayllarini qo'shish";
$lang['no_schema_files_found']="Sxema fayllari topilmadi.";
$lang['main_schema_label']="Asosiy sxema fayli";
$lang['related_schema_label']="Bog'liq sxema fayli";
$lang['schema_file_upload_label']="Sxema fayl yuklashlari";
$lang['schema_file_upload_hint_create']="Yuklash uchun barcha JSON sxema fayllarini tanlang. Bitta fayl asosiy sxema sifatida belgilanishi kerak.";
$lang['schema_file_upload_hint_edit']="Yangi JSON sxema fayllarini qo'shing. Faqat joriy faylni almashtirmoqchi bo'lsangiz, asosiy faylni tanlang.";
$lang['select_main_schema']="Asosiy sxema faylini tanlash";
$lang['pending_main_indicator']="Kutilayotgan asosiy sxema";
$lang['current_main_indicator']="Joriy asosiy sxema";
$lang['pending_related_indicator']="Kutilayotgan bog'liq sxema";
$lang['current_related_indicator']="Mavjud bog'liq sxema";
$lang['main_selection_optional_edit']="Joriy asosiy sxemani saqlab qolish uchun tanlovni bo'sh qoldiring.";
$lang['upload_selected_files']="Tanlangan fayllarni yuklash";
$lang['clear_selection']="Tanlovni tozalash";
$lang['existing_schema_files']="Mavjud sxema fayllari";
$lang['schema_files_required']="Kamida bitta sxema fayli talab qilinadi.";

// Schema UID
$lang['invalid_uid']="UID noyob bo'lishi va 3-64 belgidan (harflar, raqamlar, chiziqcha, pastki chiziq) foydalanishi kerak.";
$lang['uid_hint']="UID noyob bo'lishi kerak. Ruxsat etilgan belgilar: harflar, raqamlar, chiziqcha va pastki chiziq.";

// Core field mappings
$lang['edit_core_mappings']="Asosiy xaritalashlarni tahrirlash";
$lang['core_field']="Asosiy maydon";
$lang['mapped_field']="Xaritalangan maydon";
$lang['core_field_idno']="Asosiy maydon: Identifikator";
$lang['core_field_title']="Asosiy maydon: Sarlavha";
$lang['core_field_idno_hint']="Identifikator maydoniga JSON ko'rsatkichi (masalan, metadata/idno)";
$lang['core_field_title_hint']="Sarlavha maydoniga JSON ko'rsatkichi (masalan, metadata/title)";
$lang['core_field_mappings']="Asosiy maydon xaritalashlari";
$lang['core_field_mappings_hint']="Sxema maydonlarini loyiha ro'yxatlari, qidiruv va filtrlashda ishlatiladigan asosiy loyiha maydonlariga xaritalaydi.";
$lang['core_mapping_status']="Xaritalashlar";
$lang['mapping_complete']="Xaritalangan";
$lang['mapping_missing']="Xaritalanmagan";
$lang['save_mappings']="Xaritalashlarni saqlash";
$lang['schema_title_required']="Xaritalashlarni saqlash uchun sxema sarlavhasi talab qilinadi.";
$lang['schema_mappings_updated']="Sxema xaritalashlari muvaffaqiyatli yangilandi.";
$lang['schema_mappings_update_failed']="Sxema xaritalashlarini yangilashda xatolik.";
$lang['back_to_schemas']="Sxemalarga qaytish";

// Attribute mappings
$lang['attribute_key']="Atribut kaliti";
$lang['add_attribute']="Atribut qo'shish";
$lang['attribute_key_exists']="Atribut kaliti allaqachon mavjud";
$lang['idno_required']="IDNO talab qilinadi";
$lang['title_required']="Sarlavha talab qilinadi";

// Schema preview
$lang['preview_schema']="Sxemani oldindan ko'rish";
$lang['preview_schema_tree']="Sxemani oldindan ko'rish (Daraxt)";
$lang['redoc_not_available']="Sxemani oldindan ko'rish mavjud emas (Redoc skripti yuklanmagan).";
$lang['redoc_failed']="Sxemani oldindan ko'rishda xatolik.";
$lang['openapi_json']="OpenAPI (JSON)";
$lang['openapi_yaml']="OpenAPI (YAML)";

// Template regeneration
$lang['generated']="Yaratilgan";
$lang['regenerate_template']="Shablonni qayta yaratish";
$lang['regenerate_template_confirm']="Ushbu sxema uchun shablonni qayta yaratishmi? Yaratilgan standart shablon almashtiriladi.";
$lang['schema_template_regenerated']="Sxema shabloni muvaffaqiyatli qayta yaratildi.";
$lang['regenerate_template_failed']="Shablonni qayta yaratishda xatolik.";
$lang['generated_template_locked']="Yaratilgan shablonlar faqat o'qish uchun. Shablonni moslash uchun nusxalang.";

// Miscellaneous
$lang['not_implemented']="Hali amalga oshirilmagan.";

/* End of file schemas_lang.php */
/* Location: ./application/language/uzbek/schemas_lang.php */


