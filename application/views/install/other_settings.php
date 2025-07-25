<div class="accordion mb-3 border-bottom" id="accordionIni">
  <div class="card">
    <div class="card-header p-1 pt-2 pb-0" id="headingtwo">
      <h2 class="mb-0">
        <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#collapseIni" aria-expanded="true" aria-controls="collapseIni">
			<?php echo t('other_php_settings');?>
        </button>
      </h2>
    </div>

    <div id="collapseIni" class="collapse collapse" aria-labelledby="headingtwo" data-parent="#accordionIni">
      <div class="card-body">
	  <?php
	//other settings
	
	echo '<table cellpadding="3" cellspacing="0" class="grid-table table table-sm table-striped table-bordered">';
	
	echo '<tr class="header">';
	echo '<th>'.t('setting').'</th>';
	echo '<th>'.t('value').'</th>';
	echo '<th>'.t('recommended').'</th>';
	echo '</tr>';
	
	echo '<tr>';
	echo '<td>file_uploads</td>';
	echo '<td>';
	echo ini_get('file_uploads')==1 ? t('enabled') : t('disabled');
	echo '</td>';
	echo '<td>'.t('enabled').'</td>';
	echo '</tr>';
	
	echo '<tr>';
	echo '<td>post_max_size</td>';
	echo '<td>';
	echo ini_get('post_max_size');
	echo '</td>';
	echo '<td>800M</td>';
	echo '</tr>';
	
	echo '<tr>';
	echo '<td>upload_max_filesize</td>';
	echo '<td>';
	echo ini_get('upload_max_filesize');
	echo '</td>';
	echo '<td>800M</td>';
	echo '</tr>';


	echo '<tr>';
	echo '<td>date.timezone</td>';
	echo '<td>';
	if (!ini_get('date.timezone'))
	{
		echo '<div class="red">'.t('not_set').'</div>';
	}
	else{
		echo ini_get('date.timezone');
	}	
	echo '</td>';
	echo '<td>'.sprintf(t('time_zone_is_required'),'http://php.net/manual/en/datetime.configuration.php','http://php.net/manual/en/timezones.php').'</td>';
	echo '</tr>';


	echo '</table>';
?>
      </div>
    </div>
  </div>
</div>

