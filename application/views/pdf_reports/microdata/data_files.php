<style>
.table-data-files td{
    cursor:pointer;
    border-bottom:1px solid gainsboro;
}

</style>

<h3><?php echo t('data_dictionary');?></h3>
<table class="table table-data-files ddi-table data-dictionary" >
    <tbody>
    <tr>
        <th><?php echo t('#ID');?></th>
        <th><?php echo t('data_file');?></th>
        <th><?php echo t('cases');?></th>
        <th><?php echo t('variables');?></th>
    </tr>
    <?php foreach($files as $file):?>
        <tr class="data-file-row row-color1">
            <td><?php echo $file['file_id'];?></td>
            <td>                
                <?php echo $file['file_name'];?>
                <div class="file-description"><?php echo nl2br($file['description']);?></div>
            </td>
            <td><?php echo $file['case_count'];?></td>
            <td><?php echo $file['var_count'];?></td>
        </tr>
    <?php endforeach;?>
    </tbody>
    </table>