<?php 
if (!isset($data) || empty($data) || !is_array($data)){
    return;
}

 $columns=$template['props'];
 $name=$template['title'];
 $hide_field_title=false;
 $hide_column_headings=false;
 $pdf_mode = !empty($pdf_mode);

 $scalar_types = array('text', 'string', 'number', 'integer', 'boolean', 'textarea', 'dropdown', 'dropdown-custom', 'date');
?>


<div id="<?php echo str_replace(".","_",$template['key']);?>" class="mb-3 field-nested-array">
  <h4 class="field-title"><?php echo t($template['title']);?></h4>
  <?php 
    $non_empty_rows = array();
    foreach($data as $idx=>$row):
      if (!is_array($row)) continue;
      $has_content = false;
      foreach($row as $val) {
        if (preview_value_has_content($val)) {
          $has_content = true;
          break;
        }
      }
      if ($has_content) {
        $non_empty_rows[$idx] = $row;
      }
    endforeach;
    $row_count = count($non_empty_rows);
  ?>
  <?php foreach($non_empty_rows as $idx=>$row):?>
  <div class="nested-array-row mb-4">
    <?php if ($row_count > 1): ?>
    <h5 class="field-instance-title">
      <?php if (isset($template['display_options']['header_fields'])):?>
        <?php foreach($template['display_options']['header_fields'] as $header_field):?>
          <?php echo isset($row[$header_field]) ? html_escape($row[$header_field]) : '';?>
        <?php endforeach;?>
      <?php else:?>
        [<?php echo (int)$idx + 1; ?>] - <?php echo html_escape($template['title']);?>
      <?php endif;?>
    </h5>
    <?php endif; ?>
          <?php foreach($columns as $column):?>        
            <div>
                <?php if (in_array($column['type'],array('array','nested_array','simple_array', 'section'))):?>
                    <?php 
                        $column['hide_column_headings']=false;
                        $column['hide_field_title']=false;
                        $item_data = preview_row_column_data($row, $column);

                        if (!preview_value_has_content($item_data)){
                            continue;
                        }
                    ?>
                    <?php  echo $this->load->view('project_preview/fields/field_'.$column['type'],array(
                        'data'=>$item_data,
                        'template'=>$column,
                        'pdf_mode'=>$pdf_mode
                    ),true);?>
                <?php elseif (in_array($column['type'], $scalar_types, true)):?>
                    <?php
                        $scalar_value = isset($row[$column['key']]) ? $row[$column['key']] : null;
                        if (!preview_value_has_content($scalar_value)) {
                            continue;
                        }
                    ?>
                    <div class="preview-field">
                      <div class="field-label"><?php echo html_escape($column['title']);?></div>
                      <div class="field-value text-block"><?php echo html_escape(preview_format_scalar($scalar_value, $column)); ?></div>
                    </div>
                <?php endif;?>
            </div>
            <?php endforeach;?>
  </div>
  <?php endforeach;?>
  
</div>
