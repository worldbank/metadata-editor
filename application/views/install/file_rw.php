<?php
	
	$test_folders=array(
				'datafiles'=>'Catalog',
				$this->config->item('log_path')=>'Log',
	);
	
	// Check if all folders have proper permissions
	$all_permissions_ok = true;
	foreach($test_folders as $folder=>$description) {
		$filename = str_replace("\\","/",$folder).'/sampletestfile.txt';
		if (canwritefile($filename) !== '<span class="green">'.t('yes').'</span>' || 
			candeletefile($filename) !== '<span class="green">'.t('yes').'</span>') {
			$all_permissions_ok = false;
		}
	}
?>

<div class="accordion mb-3 border-bottom" id="accordionFileRW">
  <div class="card">
    <div class="card-header p-1 pt-2 pb-0" id="headingFileRW">
      <h2 class="mb-0">
        <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#collapseFileRW" aria-expanded="true" aria-controls="collapseFileRW">
			<div class="d-flex justify-content-between align-items-center">
				<span><?php echo t('folder_permissions');?></span>
				<?php if ($all_permissions_ok) {
					echo '<i class="fas fa-check-circle text-success"></i>';
				}
				?>
			</div>
        </button>
      </h2>
    </div>

    <div id="collapseFileRW" class="collapse" aria-labelledby="headingFileRW" data-parent="#accordionFileRW">
      <div class="card-body">
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
      </div>
    </div>
  </div>
</div>

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