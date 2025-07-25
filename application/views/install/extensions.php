
<?php
	$extensions=array('xslt','xml');
	if(phpversion() >= 7) {
		$extensions=array(
					'xsl'=>'',
					'xml'=>'',
					'simplexml'=>'',
					'xmlreader'=>'',
					'gd'=>'<span class="optional">'.t('optional').'</span>',
					"zip"=>'<span class="optional">'.t('optional').'</span>',
					"mbstring"=>'<span class="optional">'.t('optional').'</span>'
					);
	}
	
	$dbextensions=array($this->db->dbdriver);
	
	$yes='<span class="green">'.t('yes').'</span>';
	$no='<span class="red" style="background:none;color:red;">'.t('no').'</span>';

	// Check if all required extensions are enabled
	$all_required_enabled = true;
	foreach ($extensions as $ex => $value) {
		if (!extension_loaded($ex)) {
			$all_required_enabled = false;
			break;
		}
	}
?>

<div class="accordion mb-3 border-bottom" id="accordionPHP">
  <div class="card">
    <div class="card-header p-1 pt-2 pb-0" id="headingOne" >
      <h2 class="mb-0">
        <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
		<div class="d-flex justify-content-between align-items-center">
			<span><?php echo t('required_php_extensions');?></span>
			<?php if ($all_required_enabled) {
				echo '<i class="fas fa-check-circle text-success"></i>';
			}
			?>
		</div>		
        </button>
      </h2>
    </div>

    <div id="collapseOne" class="collapse" aria-labelledby="headingOne" data-parent="#accordionPHP">
      <div class="card-body">
	  <table cellpadding="0" cellspacing="0" class="grid-table table table-sm table-striped table-bordered">
		<tr class="header">
			<th><?php echo t('extensions');?></th>
			<th><?php echo t('enabled');?></th>
		</tr>
		<?php foreach ($extensions as $ex=>$value):?>
			<tr>
				<td><?php echo "$ex $value";?></td>
				<td>
					<?php echo extension_loaded($ex) ? $yes: $no;?>
				</td>
			</tr>
		<?php endforeach;?>
		<?php foreach ($dbextensions as $ex):?>
			<tr>
				<td><?php echo $ex;?></td>
				<td style="width:50px">
					<?php if ($this->db->dbdriver==$ex):?>
						<?php if (extension_loaded($ex)!=1):?>
							<span style="color:red">
								<?php echo sprintf(t('extension_not_enabled'),$ex);?>
							</span>
						<?php else:?>
							<?php echo $yes;?>
						<?php endif;?>
					<?php else:?>
						<?php echo $yes;?>
					<?php endif;?>
				</td>
			</tr>
		<?php endforeach;?>
	</table>
      </div>
    </div>
  </div>
</div>
  
	
	
	
