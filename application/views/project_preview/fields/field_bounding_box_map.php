<?php
if (!$this->pagepreview->show_preview_maps()) {
	return;
}

if (!isset($template) || !isset($data)) {
	return;
}

$bbox = preview_bounding_box_corners($template, $data);
if ($bbox === null) {
	return;
}

$map_id = 'preview-bbox-' . uniqid();
?>
<div
	class="preview-bounding-box-map mb-3"
	id="<?php echo html_escape($map_id); ?>"
	data-preview-bbox="1"
	data-west="<?php echo html_escape($bbox['west']); ?>"
	data-east="<?php echo html_escape($bbox['east']); ?>"
	data-south="<?php echo html_escape($bbox['south']); ?>"
	data-north="<?php echo html_escape($bbox['north']); ?>"
	style="height:220px;width:100%;max-width:640px;border:1px solid #ccc;border-radius:4px;"
	role="img"
	aria-label="Geographic bounding box map"
></div>
