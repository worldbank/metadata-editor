<?php
if (!isset($data) || !preview_value_has_content($data)) {
	return;
}

$item_prop_key = isset($template['prop_key']) ? $template['prop_key'] : $template['key'];
$item_key = isset($template['key']) ? $template['key'] : '';
$pdf_mode = !empty($pdf_mode);

$item_types = array(
	'number' => 'scalar',
	'integer' => 'scalar',
	'string' => 'scalar',
	'text' => 'scalar',
	'date' => 'scalar',
	'boolean' => 'scalar',
	'array' => 'array',
	'nested_array' => 'nested_array',
	'simple_array' => 'simple_array',
	'section' => 'section',
);

$display_type = isset($template['display_type']) ? $template['display_type'] : '';

?>

<div id="<?php echo html_escape($item_prop_key); ?>" class="field-section-wrapper">
	<h5 class="field-subsection-title"><?php echo html_escape($template['title']); ?></h5>

	<?php if ($display_type === 'bounding_box'): ?>
		<?php echo $this->load->view('project_preview/fields/field_bounding_box_map', array(
			'data' => $data,
			'template' => $template,
		), true); ?>
		<?php if (isset($template['props']) && is_array($template['props']) && count($template['props']) > 0): ?>
			<dl class="preview-bbox-coords">
				<?php foreach ($template['props'] as $item): ?>
					<?php
						$prop_key = isset($item['key']) ? $item['key'] : '';
						$nested_data = preview_section_prop_value($data, $item_key, $prop_key);
						if (!preview_value_has_content($nested_data)) {
							continue;
						}
						$label = isset($item['title']) ? $item['title'] : $prop_key;
						if (is_numeric($nested_data)) {
							$display_value = rtrim(rtrim(sprintf('%.6F', $nested_data + 0), '0'), '.');
						} else {
							$display_value = preview_format_scalar($nested_data, $item);
						}
					?>
					<dt><?php echo html_escape($label); ?></dt>
					<dd><?php echo html_escape($display_value); ?>°</dd>
				<?php endforeach; ?>
			</dl>
		<?php endif; ?>
	<?php elseif (isset($template['props']) && is_array($template['props']) && count($template['props']) > 0): ?>
		<?php foreach ($template['props'] as $item): ?>
			<?php
				$prop_key = isset($item['key']) ? $item['key'] : '';
				$nested_data = preview_section_prop_value($data, $item_key, $prop_key);
				$item_type = isset($item['type']) ? $item['type'] : 'string';
			?>
			<?php if (!preview_value_has_content($nested_data)): ?>
				<?php continue; ?>
			<?php endif; ?>
			<div class="section-item">
				<?php if ($item_type === 'coordinate_pairs'): ?>
					<div class="preview-field">
						<div class="field-label"><?php echo html_escape($item['title']); ?></div>
						<div class="field-value text-block text-block--multiline"><?php echo html_escape(preview_format_coordinate_pairs($nested_data)); ?></div>
					</div>
				<?php elseif (isset($item_types[$item_type]) && $item_types[$item_type] === 'scalar'): ?>
					<?php
						echo $this->load->view(
							'project_preview/fields/field_scalar',
							array(
								'data' => $nested_data,
								'template' => $item,
								'pdf_mode' => $pdf_mode,
							),
							true
						);
					?>
				<?php elseif (isset($item_types[$item_type])): ?>
					<?php
						echo $this->load->view(
							'project_preview/fields/field_' . $item_types[$item_type],
							array(
								'data' => $nested_data,
								'template' => $item,
								'pdf_mode' => $pdf_mode,
							),
							true
						);
					?>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
