<?php 
//for pdf mode, don't use bootstrap classes
$is_pdf_generation = isset($pdf_mode) && $pdf_mode === true;
?>

<?php if (!$is_pdf_generation): ?>
<html>
<head>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<?php $this->load->view('project_preview/preview_common_styles'); ?>
<style>
    body {
        margin: 15px;
    }
    /* Critical layout for offline export if project-preview.css fails to load */
    .project-preview-metadata .field-value,
    .project-preview-metadata .preview-field,
    .project-preview-metadata .section-item {
        margin: 0 !important;
        padding: 0 !important;
        padding-left: 0 !important;
        margin-left: 0 !important;
    }
</style>
</head>
<body style="margin:10px;">        
<?php endif; ?>

<div class="project-preview-metadata"><?php echo $html;?></div>

<?php if (!$is_pdf_generation): ?>
</body>
</html>
<?php endif; ?>