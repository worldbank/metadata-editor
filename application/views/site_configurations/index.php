<style>
.form-control{width:200px;display:inline;}
.input-fixed-3{width:50px;display:inline;text-align:center;}
.field{margin-bottom:15px;clear:both;}
label{display:block;float:left;width:200px;}
.field-note{font-style:italic;padding-left:5px;color:gray;}
h2{font-size:1.2em;font-weight:bold;border-bottom:1px solid gainsboro;padding-bottom:2px;margin-bottom:10px;}
.field-expanded,.always-visible{background-color:#F8F8F8;border:1px solid gainsboro;margin-top:5px;margin-bottom:10px;margin-right:8px;}
.always-visible{padding:10px;}
.field-expanded .field, .always-visible .field {padding:5px;}
.field-expanded legend, .field-collapsed legend, .always-visible legend{background:white;padding-left:5px;padding-right:5px;font-weight:normal; cursor:pointer;}
.field-collapsed{background:none; border:0px;border-top:1px solid gainsboro;margin-top:5px;margin-bottom:5px;}
.field-collapsed legend {background-position:left top; }

.field-collapsed .field{display:none;}
.field-expanded .field label, .always-visible label{font-weight:normal;}
.instructions{font-weight:bold;}

</style>
<div class="container-fluid mt-5">
<h3 class="page-title"><?php echo t('site_configurations');?></h3>

<?php if (validation_errors() ) : ?>
    <div class="alert alert-danger">
	    <?php echo validation_errors(); ?>
    </div>
<?php endif; ?>

<?php $error=$this->session->flashdata('error');?>
<?php echo ($error!="") ? '<div class="alert alert-danger">'.$error.'</div>' : '';?>

<?php $message=$this->session->flashdata('message');?>
<?php echo ($message!="") ? '<div class="alert alert-success">'.$message.'</div>' : '';?>

<?php if (isset($this->message)):?>
<?php echo ($this->message!="") ? '<div class="alert alert-success">'.$this->message.'</div>' : '';?>
<?php endif;?>


<?php echo form_open('', 'id="form_site_configurations" name="form_site_configurations"');?>

<div style="text-align:right;">
	<input class="btn btn-primary" type="submit" value="<?php echo t('update');?>" name="submit"/>
</div>

<fieldset class="field-expanded ">
	<legend><i class="fas fa-language mr-3" style="color:#007bff;"></i><?php echo t('language');?></legend>

	<?php
		$_avail  = isset($available_folders) && is_array($available_folders) ? $available_folders : array();
		$_map    = isset($lang_mapping)       && is_array($lang_mapping)       ? $lang_mapping       : array();
		$_iso    = isset($iso_languages)      && is_array($iso_languages)      ? $iso_languages      : array();
	?>

	<div class="field">
		<label for="language"><?php echo t('default_language');?></label>
		<?php
			$_def_opts = array();
			foreach ($_avail as $_folder) {
				$_di = isset($_map[$_folder]) ? $_map[$_folder] : null;
				$_def_opts[$_folder] = $_di && !empty($_di['display']) ? $_di['display'] : ucfirst($_folder);
			}
			echo form_dropdown('language', $_def_opts, get_form_value('language', isset($language) ? $language : 'english'));
		?>
		<span class="field-note"><?php echo t('default_language_note');?></span>
	</div>

	<div class="field">
		<label><?php echo t('enabled_languages');?></label>
		<span class="field-note"><?php echo t('enabled_languages_note');?></span>
		<div class="table-responsive mt-2">
			<table class="table table-sm table-bordered" id="languages-table">
				<thead class="thead-light">
					<tr>
						<th style="width:60px;" class="text-center">Enabled</th>
						<th>Folder</th>
						<th>ISO Language</th>
						<th>Display Name</th>
						<th>Direction</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($_avail as $_folder):
					$_curr       = isset($_map[$_folder]) ? $_map[$_folder] : null;
					$_curr_code  = ($_curr && isset($_curr['code']))      ? $_curr['code']      : '';
					$_curr_disp  = ($_curr && isset($_curr['display']))   ? $_curr['display']   : '';
					$_curr_dir   = ($_curr && isset($_curr['direction'])) ? $_curr['direction'] : '';
					$_is_enabled = ($_curr !== null);
					?>
					<tr>
						<td class="text-center align-middle">
							<input type="checkbox" name="lang_enabled[<?php echo $_folder; ?>]" value="1"<?php echo $_is_enabled ? ' checked' : ''; ?>>
						</td>
						<td class="align-middle"><code><?php echo htmlspecialchars($_folder); ?></code></td>
						<td>
							<select name="lang_code[<?php echo $_folder; ?>]" class="form-control form-control-sm iso-select" data-folder="<?php echo htmlspecialchars($_folder); ?>">
								<option value="">-- select --</option>
								<?php foreach ($_iso as $_code => $_info): ?>
									<option value="<?php echo htmlspecialchars($_code); ?>"<?php echo ($_curr_code === $_code) ? ' selected' : ''; ?>>
										<?php echo htmlspecialchars($_info['name']); ?> — <?php echo htmlspecialchars($_info['display']); ?> (<?php echo htmlspecialchars($_code); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td class="align-middle"><span id="lang_display_<?php echo $_folder; ?>"><?php echo htmlspecialchars($_curr_disp); ?></span></td>
						<td class="align-middle"><span id="lang_dir_<?php echo $_folder; ?>"><?php echo htmlspecialchars($_curr_dir); ?></span></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<script>
	(function() {
		var isoData = <?php echo json_encode($_iso); ?>;
		$(document).on('change', '.iso-select', function() {
			var folder = $(this).data('folder');
			var code   = $(this).val();
			var info   = isoData[code];
			$('#lang_display_' + folder).text(info ? info.display    : '');
			$('#lang_dir_'     + folder).text(info ? info.direction  : '');
		});
	})();
	</script>

</fieldset>


<fieldset class="field-expanded ">
	<legend><i class="fas fa-users-cog mr-3" style="color:#007bff;"></i><?php echo t('editor_user_access_settings');?></legend>

	<div class="field">
		<label><?php echo t('default_editor_role');?></label>
		<input type="hidden" name="grant_editor_default" value="0">
		<label style="float:none;display:inline;font-weight:normal;">
			<input type="checkbox" name="grant_editor_default" value="1"<?php echo !empty($grant_editor_default) ? ' checked' : ''; ?>>
			<?php echo t('default_editor_role_enable');?>
		</label>
		<div class="field-note" style="margin-left:200px;clear:both;padding-top:6px;">
			<?php echo t('default_editor_role_note');?>
		</div>
	</div>

	<div class="field">
		<label><?php echo t('editor_project_sharing');?></label>
		<input type="hidden" name="project_sharing" value="0">
		<label style="float:none;display:inline;font-weight:normal;">
			<input type="checkbox" name="project_sharing" value="1"<?php echo !empty($project_sharing_enabled) ? ' checked' : ''; ?>>
			<?php echo t('editor_project_sharing_enable');?>
		</label>
		<div class="field-note" style="margin-left:200px;clear:both;padding-top:6px;">
			<?php echo t('editor_project_sharing_note');?>
		</div>
	</div>
</fieldset>


<fieldset class="field-expanded ">
	<legend><i class="fas fa-folder-open mr-3" style="color:#007bff;"></i><?php echo t('editor_storage_settings');?></legend>
	<div class="field">
		<label><?php echo t('editor_storage_path');?></label>
		<code><?php echo isset($editor_storage_path) ? htmlspecialchars($editor_storage_path) : ''; ?></code>
		<span class="field-note"><?php echo t('editor_storage_path_note');?></span>
		<?php
			$_sp      = isset($editor_storage_path) ? $editor_storage_path : '';
			$_appRoot = rtrim(realpath(FCPATH), DIRECTORY_SEPARATOR);
			// Resolve relative paths against the web root
			$_spAbs   = (!empty($_sp) && $_sp[0] !== '/') ? FCPATH . ltrim($_sp, '/') : $_sp;
			// Try realpath (works when path exists); fall back to normalised string check
			$_spResolved = !empty($_spAbs) ? (realpath($_spAbs) ?: $_spAbs) : '';
			$_spNorm     = rtrim(str_replace('\\', '/', $_spResolved), '/');
			$_rootNorm   = rtrim(str_replace('\\', '/', $_appRoot), '/');
			$_pathIsInApp = !empty($_spNorm) && strpos($_spNorm . '/', $_rootNorm . '/') === 0;
			if ($_pathIsInApp):
		?>
		<div class="alert alert-warning mt-2 mb-0 py-2 px-3" style="clear:both;">
			<i class="fas fa-exclamation-triangle mr-1"></i>
			<?php echo t('editor_storage_path_inside_app_warning'); ?>
		</div>
		<?php endif; ?>
	</div>
	<div class="field">
		<label><?php echo t('editor_user_schema_path');?></label>
		<code><?php echo isset($editor_user_schema_path) ? htmlspecialchars($editor_user_schema_path) : ''; ?></code>
	</div>
	<div class="field">
		<label><?php echo t('editor_data_api_url');?></label>
		<code><?php echo isset($editor_data_api_url) ? htmlspecialchars($editor_data_api_url) : ''; ?></code>
	</div>
	<div class="field">
		<span class="field-note"><?php echo t('editor_storage_config_file_note');?></span>
	</div>
</fieldset>


<fieldset class="field-expanded ">
	<legend><i class="fas fa-chart-bar mr-3" style="color:#007bff;"></i><?php echo t('builtin_analytics_settings');?></legend>
	<div class="field">
		<label><?php echo t('analytics_enabled');?></label>
		<?php if (!empty($analytics_enabled)): ?>
			<span class="text-success"><i class="fas fa-check-circle"></i> <?php echo t('yes');?></span>
		<?php else: ?>
			<span class="text-muted"><?php echo t('no');?></span>
		<?php endif; ?>
	</div>
	<div class="field">
		<label><?php echo t('analytics_track_hash_changes');?></label>
		<?php if (!empty($analytics_track_hash_changes)): ?>
			<span class="text-success"><i class="fas fa-check-circle"></i> <?php echo t('yes');?></span>
		<?php else: ?>
			<span class="text-muted"><?php echo t('no');?></span>
		<?php endif; ?>
		<span class="field-note"><?php echo t('analytics_track_hash_changes_note');?></span>
	</div>
	<div class="field">
		<span class="field-note"><?php echo t('analytics_config_file_note');?></span>
	</div>
</fieldset>


<fieldset class="field-expanded ">
	<legend><i class="fas fa-tools mr-3" style="color:#007bff;"></i><?php echo t('mail_settings');?></legend>

        <div class="field m-3">
        <a class="btn btn-outline-primary" href="<?php echo site_url('admin/configurations/test_email');?>"><i class="fas fa-tools mr-1" style="color:#007bff;"></i><?php echo t('test_email_configurations');?></a>
        </div>

    <?php if (file_exists(APPPATH.'/config/email.php')):?>
    	<div class="field warning"><?php echo t('edit_email_settings');?></div>
    <?php else:?>        
    <div class="field">
            <label style="height:50px;" for="<?php echo 'mail_protocol'; ?>"><?php echo t('select_mail_protocol');?></label>
            <div>
            <input type="radio" value="mail" name="mail_protocol" <?php echo ($mail_protocol=='mail') ? 'checked="checked"' : ''; ?>/> <?php echo t('use_php_mail');?>  <br/>
            <input type="radio" value="smtp" name="mail_protocol" <?php echo ($mail_protocol=='smtp') ? 'checked="checked"' : ''; ?>/> <?php echo t('use_smtp');?><br/>
            </div>
    </div>
    
    <div class="field">
            <label for="<?php echo 'smtp_host'; ?>"><?php echo t('smtp_host');?></label>
            <input class="form-control" name="smtp_host" type="text" id="smtp_host"  value="<?php echo get_form_value('smtp_host',isset($smtp_host) ? $smtp_host : ''); ?>"/>
    </div>
    
    <div class="field">
            <label for="<?php echo 'smtp_port'; ?>"><?php echo t('smtp_port');?></label>
            <input class="form-control" name="smtp_port" type="text" id="smtp_port"  value="<?php echo get_form_value('smtp_port',isset($smtp_port) ? $smtp_port : ''); ?>"/>
    </div>
    
    <div class="field">
            <label for="<?php echo 'smtp_user'; ?>"><?php echo t('smtp_user');?></label>
            <input class="form-control" name="smtp_user" type="text" id="smtp_user"  value="<?php echo get_form_value('smtp_user',isset($smtp_user) ? $smtp_user : ''); ?>"/>
    </div>
    
    <div class="field">
            <label for="<?php echo 'smtp_pass'; ?>"><?php echo t('smtp_password');?></label>
            <input class="form-control" name="smtp_pass" type="text" id="smtp_pass"  value="<?php echo get_form_value('smtp_pass',isset($smtp_pass) ? $smtp_pass : ''); ?>"/>
    </div>
    <?php endif;?>
</fieldset>

<?php echo form_close();?>

<fieldset class="field-expanded">
	<legend><i class="fas fa-life-ring mr-3" style="color:#007bff;"></i><?php echo t('support_and_updates');?></legend>

	<div class="field">
		<label><?php echo t('installed_version');?></label>
		<span><strong><?php echo APP_VERSION; ?></strong></span>
	</div>

	<div class="field">
		<label><?php echo t('latest_version');?></label>
		<button type="button" class="btn btn-sm btn-outline-secondary" id="btn-check-updates">
			<i class="fas fa-sync-alt mr-1"></i><?php echo t('check_for_updates');?>
		</button>
		<span id="update-status" class="ml-2"></span>
	</div>

	<div class="field">
		<label><?php echo t('support');?></label>
		<div>
			<a href="https://github.com/worldbank/metadata-editor/releases" target="_blank" rel="noopener">
				<i class="fab fa-github mr-1"></i><?php echo t('github_releases');?>
			</a>
			<span class="mx-2 text-muted">&bull;</span>
			<a href="mailto:datatools@worldbank.org">
				<i class="fas fa-envelope mr-1"></i>datatools@worldbank.org
			</a>
		</div>
	</div>

</fieldset>

<script>
(function() {
	var currentVersion = '<?php echo APP_VERSION; ?>';

	function parseSemver(v) {
		var m = v.replace(/^v/, '').match(/^(\d+)\.(\d+)\.(\d+)/);
		return m ? [parseInt(m[1]), parseInt(m[2]), parseInt(m[3])] : null;
	}

	function semverGt(a, b) {
		for (var i = 0; i < 3; i++) {
			if (a[i] > b[i]) return true;
			if (a[i] < b[i]) return false;
		}
		return false;
	}

	$('#btn-check-updates').on('click', function() {
		var $btn    = $(this);
		var $status = $('#update-status');
		$btn.prop('disabled', true).find('i').addClass('fa-spin');
		$status.html('');

		fetch('https://api.github.com/repos/worldbank/metadata-editor/releases/latest', {
			headers: { 'Accept': 'application/vnd.github+json' }
		})
		.then(function(r) { return r.ok ? r.json() : Promise.reject(r.status); })
		.then(function(data) {
			var latest  = (data.tag_name || '').replace(/^v/, '');
			var cur     = parseSemver(currentVersion);
			var lat     = parseSemver(latest);
			if (!lat) throw 'parse';
			if (semverGt(lat, cur)) {
				$status.html('<span class="text-warning"><i class="fas fa-arrow-circle-up mr-1"></i><?php echo t('update_available');?> &nbsp;<strong>v' + latest + '</strong> &mdash; <a href="' + (data.html_url || 'https://github.com/worldbank/metadata-editor/releases') + '" target="_blank" rel="noopener"><?php echo t('view_release');?></a></span>');
			} else {
				$status.html('<span class="text-success"><i class="fas fa-check-circle mr-1"></i><?php echo t('up_to_date');?></span>');
			}
		})
		.catch(function() {
			$status.html('<span class="text-muted"><i class="fas fa-exclamation-circle mr-1"></i><?php echo t('update_check_failed');?></span>');
		})
		.finally(function() {
			$btn.prop('disabled', false).find('i').removeClass('fa-spin');
		});
	});
})();
</script>
</div>
<script type="text/javascript">
	function toggle_file_url(field_show,field_hide){
		$('#'+field_show).show();
		$('#'+field_hide).hide();
	}
	
	$('.field-expanded > legend').click(function(e) {
                e.preventDefault();
                $(this).parent('fieldset').toggleClass("field-collapsed");
                return false;
	});
	
	$(document).ready(function() {
  		$('.field-expanded > legend').parent('fieldset').toggleClass('field-collapsed');
	});
	
</script>

<?php
function folder_exists($folder)
{
	if (is_dir($folder)){
	  return '<span class="glyphicon glyphicon-ok ico-add-color"></span><span title="'.t('folder_exists_on_server').'"</span>';
	}
	else{
	  return '<span class="glyphicon glyphicon-remove red-color"></span><span title="'.t('path_not_found').'"</span>';
	}
}

function get_languages()
{
	$languages = scandir(APPPATH.'language/');
	foreach($languages as $lang){
          if ($lang!=='.' && $lang!=='..' && $lang!=='.DS_Store'){

                //ignore zip
                if (strpos($lang, '.zip') !== false) {
                    continue;
                }

                //ignore base
                if (strpos($lang, 'base') !== false) {
                    continue;
                }

             $output[$lang]=ucfirst($lang);  
          }	
	}	
	return $output;
}
?>
