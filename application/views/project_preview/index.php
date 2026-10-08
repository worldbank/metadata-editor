<?php $this->load->view('project_preview/preview_common_styles'); ?>
<div class="project-preview-metadata">
<?php 
    $this->pagepreview->render_html();
?>
</div>

<?php if ($this->pagepreview->show_preview_maps()): ?>
<link rel="stylesheet" href="<?php echo base_url(); ?>vue-app/assets/leaflet.css" />
<script src="https://code.jquery.com/jquery-3.7.1.slim.min.js" integrity="sha256-kmHvs0B+OpCW5GVHUNjv9rOmY0IvSIRcf7zGUDTDQM8=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.2.96/css/materialdesignicons.min.css">
<script src="<?php echo base_url(); ?>vue-app/assets/leaflet.js"></script>
<script><?php echo $this->load->view('metadata_editor/vue-bounding-box-util.js', null, true); ?></script>
<?php echo $this->load->view('project_preview/bounding_box_maps_init.js.php', array(), true); ?>
<?php endif; ?>
