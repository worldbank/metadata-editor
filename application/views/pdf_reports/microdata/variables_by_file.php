<style>
    .data-file-bg1 tr,.data-file-bg1 td {vertical-align: top;}
    .data-file-bg1 .col-1{width:100px;}
    .data-file-bg1 {margin-bottom:20px;}
    .var-info-panel{display:none;}
    .table-variable-list td{
        cursor:pointer;
    }
    
    .nada-list-group-item {
        position: relative;
        display: block;
        padding: 10px 15px;
        margin-bottom: -1px;
        background-color: #fff;
        border: 1px solid #ddd;
        border-left:0px;
        border-right:0px;
        font-size: small;
        border-bottom: 1px solid gainsboro;
        word-wrap: break-word;
        padding: 5px;
        padding-right: 10px;

    }

    .nada-list-group-title{
        font-weight:bold;
        border-top:0px;
    }

    .variable-groups-sidebar
    .nada-list-vgroup {
        padding-inline-start: 0px;
        font-size:small;
        list-style-type: none;
    }

    .nada-list-vgroup {
        list-style-type: none;
    }

    .nada-list-subgroup{
        padding-left:10px;
    }
    
    .table-variable-list .var-breadcrumb{
        display:none;
    }

    .nada-list-subgroup .nada-list-vgroup-item {
        padding-left: 24px;
        position: relative;
        list-style:none;
    }

    .nada-list-subgroup .nada-list-vgroup-item:before {
        position: absolute;
        font-family: 'FontAwesome';
        top: 0;
        left: 10px;
        content: "\f105";
    }

    .var-id {
        word-break: break-word!important;
        overflow-wrap: break-word!important;
    }

    .table-bordered,
    .table-bordered td{
        border:1px solid gainsboro;
        border-collapse:collapse;
        padding:3px;
    }

</style>


<div class="row">

    <div class="body-files">

        <div class="container-fluid" id="datafile-container">        
            <h4><?php echo t('data_file');?>: <?php echo $file['file_name'];?></h4>
            
            <?php if($file['description']!=''):?>
                <p><?php echo nl2br($file['description']);?></p>
            <?php endif;?>
        
            <table class="data-file-bg1">
                <tr>
                    <td style="width:100px;"><?php echo t('cases');?>: </td>
                    <td><?php echo $file['case_count'];?></td>
                </tr>
                <tr>
                    <td><?php echo t('variables');?>: </td>
                    <td><?php echo $file_variables_count;?></td>
                </tr>

                <?php if(isset($file['producer']) && !empty($file['producer'])):?>
                <tr>
                    <td><?php echo t('producer');?>: </td>
                    <td><?php echo $file['producer'];?></td>
                </tr>
                <?php endif;?>

                <?php if(isset($file['notes']) && !empty($file['notes'])):?>
                <tr>
                    <td><?php echo t('notes');?>: </td>
                    <td><?php echo nl2br($file['notes']);?></td>
                </tr>
                <?php endif;?>

            </table>
            
        </div>

        
        <div class="container-fluid variables-container" id="variables-container">
            <h4><?php echo t('variables');?></h4>
            
            <?php $tr_class="";//"row-color1"; ?>
            <div class="container-fluid table-variable-list data-dictionary ">
                <table class="table table-sm table-bordered">
                    <thead>
                    <tr>
                        <td>ID</td>
                        <td>Name</td>
                        <td>Label</td>                        
                    </tr>
                    </thead>
                <?php foreach($variables as $variable):?>
                    <tr class="var-row <?php echo $tr_class;?>" >
                        <td><?php echo $variable['vid'];?></td>
                        <td><?php echo html_escape($variable['name']);?></td>
                        <td class="col"><?php echo html_escape($variable['labl']);?></td>                        
                    </tr>
                <?php endforeach;?>
                </table>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <?php echo t('total');?>: <?php echo $file_variables_count;?>
                </div>                
            </div>
        </div>

    </div>
</div>
