<?php
	
	$test_folders=array(
				'datafiles'=>'Catalog',
				$this->config->item('log_path')=>'Log',
	);
?>
	
<h1><?php echo t('folder_permissions');?></h1>
<table cellpadding="3" cellspacing="0" class="grid-table table table-sm table-striped table-bordered">
<tr class="header">
<th><?php echo t('folder');?></th>
<th><?php echo t('read_write');?></th>
<th><?php echo t('delete');?></th>
</tr>

<?php foreach($test_folders as $folder=>$description):?>	
<?php
	//test file read/write permissions on the root folder
	$filename = str_replace("\\","/",$folder).'/sampletestfile.txt';
?>
<tr>
    <td><?php echo "$description <span class=\"optional\">($folder)</span>";?></td>
    <td><?php echo canwritefile($filename);?></td>
    <td><?php echo candeletefile($filename);?></td>
</tr>
<?php endforeach;?>
</table>


<?php
function canwritefile($filename){
	
	$yes='<span class="green">'.t('yes').'</span>';
	$no='<span class="red" style="background:none;color:red;">'.t('no').'</span>';
			
	$somecontent = "sample content\n";

    if (!$handle = @fopen($filename, 'a')) {
         //echo "Cannot open file ($filename)";
		 return $no;		 
    }
    if (fwrite($handle, $somecontent) === FALSE) {
		return $no;        
    }
    fclose($handle);
	return $yes;
}

function candeletefile($filename)
{	
	$yes='<span class="green">'.t('yes').'</span>';
	$no='<span class="red" style="background:none;color:red;">'.t('no').'</span>';
		
	if (@unlink($filename)){
		return $yes;
	}
	else{
		return $no;
	}
}




?>