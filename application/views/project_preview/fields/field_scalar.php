<?php
if (!isset($data) || !preview_value_has_content($data)) {
	if (empty($show_empty)) {
		return;
	}
}
$title = isset($template['title']) ? $template['title'] : '';
$key = isset($template['key']) ? $template['key'] : '';
$value = preview_format_scalar($data, isset($template) && is_array($template) ? $template : array());
?>
<div class="preview-field" id="<?php echo escape_html_attribute($key); ?>">
	<div class="field-label"><?php echo html_escape($title); ?></div>
	<div class="field-value text-block"><?php echo nl2br(html_escape((string) $value)); ?></div>
</div>
