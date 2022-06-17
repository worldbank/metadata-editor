<div class="row" id="body-row">

    
    <?php $this->load->view('metadata_editor/sidebar');?>
        
    <div class="col">

            <h1>Data files</h1>

            <table class="table table-sm table-bordered table-stripped">
                <?php $column_names=array_keys(current($files));?>
                <tr>
                    <th>#</th>
                <?php foreach($column_names as $col):?>                    
                    <th><?php echo $col;?></th>
                <?php endforeach;?>
                </tr>
                <?php foreach($files as $file):?>
                <tr>
                    <td>
                        <a href="<?php echo site_url('admin/catalog/edit/'.$file['sid'].'/metadata/datafiles/'.$file['file_id']);?>">Edit</a>
                        | 
                        <a href="<?php echo site_url('admin/catalog/edit/'.$file['sid'].'/metadata/variables/'.$file['file_id']);?>">Variables</a>
                    </td>
                    <?php foreach($column_names as $column_name):?>
                        <td><?php echo $file[$column_name];?></td>
                    <?php endforeach;?>
                </tr>        

                <?php endforeach;?>
            </table>
    </div>
</div>