<?php 
//for pdf mode, don't use bootstrap classes
$is_pdf_generation = isset($pdf_mode) && $pdf_mode === true;
?>

<?php if (!$is_pdf_generation): ?>
<html>
<head>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<style>
    body{
        margin:15px;
    }

    .field{
        margin-bottom:25px;
    }
    .field-section{
        margin-bottom:20px;
    }

    .font-weight-bold{
        font-weight:bold;
    }
    
</style>
</head>
<body style="margin:10px;">        
<?php endif; ?>

<?php echo $html;?>

<?php if (!$is_pdf_generation): ?>
</body>
</html>
<?php endif; ?>