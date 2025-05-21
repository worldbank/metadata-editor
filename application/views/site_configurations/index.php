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

<fieldset class="field-expanded  ">
        <legend><i class="fas fa-cogs mr-3" style="color:#007bff;"></i><?php echo t('general_site_settings');?></legend>
    <div class="field form-group">
            <label for="<?php echo 'website_title'; ?>"><?php echo t('website_title');?></label>
            <input class="form-control" name="website_title" type="text" id="website_title"  value="<?php echo get_form_value('website_title',isset($website_title) ? $website_title : ''); ?>"/>
    </div>
    <div class="field">
            <label for="<?php echo 'website_footer'; ?>"><?php echo t('website_footer');?></label>
            <textarea rows="5" cols="20" class="form-control" name="website_footer" type="text" id="website_footer"  ><?php echo get_form_value('website_footer',isset($website_footer) ? $website_footer : ''); ?></textarea>
    </div>        
    <div class="field">
            <label for="<?php echo 'default_home_page'; ?>"><?php echo t('default_home_page');?></label>
            <input class="form-control" name="default_home_page" type="text" id="default_home_page"  value="<?php echo get_form_value('default_home_page',isset($default_home_page) ? $default_home_page : ''); ?>"/>
            <span class="field-note"><?php echo t('instruction_default_home_page'); ?></span>
    </div>    
    <div class="field">
            <label for="<?php echo 'website_webmaster_name'; ?>"><?php echo t('webmaster_name');?></label>
            <input class="form-control" name="website_webmaster_name" type="text" id="website_webmaster_name"  value="<?php echo get_form_value('website_webmaster_name',isset($website_webmaster_name) ? $website_webmaster_name : ''); ?>"/>
    </div>    
    <div class="field">
            <label for="<?php echo 'website_webmaster_email'; ?>"><?php echo t('webmaster_email');?></label>
            <input class="form-control" name="website_webmaster_email" type="text" id="website_webmaster_email"  value="<?php echo get_form_value('website_webmaster_email',isset($website_webmaster_email) ? $website_webmaster_email : ''); ?>"/>
    </div>    
    <div class="field">
            <label for="<?php echo 'cache_default_expires'; ?>"><?php echo t('cache_expiry');?></label>
            <input class="form-control" name="cache_default_expires" type="text" id="cache_default_expires"  value="<?php echo get_form_value('cache_default_expires',isset($cache_default_expires) ? $cache_default_expires : '7200'); ?>"/>
            <span class="field-note"><?php echo t('cache_default_expires_msg');?></span>
    </div>
	<div class="field">
            <label for="<?php echo 'cache_disabled'; ?>"><?php echo t('cache_disabled');?></label>
            <span style="display:inline-block;padding-right:5px;" class="input-flex">
            <input type="radio" value="1" name="cache_disabled" <?php echo (get_form_value('cache_disabled',isset($cache_disabled) ? $cache_disabled : '0')=='1') ? 'checked="checked"' : ''; ?>/> <?php echo t('yes');?> 
        	<input type="radio" value="0" name="cache_disabled" <?php echo (get_form_value('cache_disabled',isset($cache_disabled) ? $cache_disabled : '0')!='1') ? 'checked="checked"' : ''; ?>/> <?php echo t('no');?>
            </span>
            <span class="field-note"><?php echo t('cache_disabled_msg');?></span>
    </div>
</fieldset>

<fieldset class="field-expanded ">
	<legend><i class="fas fa-language mr-3" style="color:#007bff;"></i><?php echo t('language');?></legend>
    <div class="field">
            <label for="<?php echo 'language'; ?>"><?php echo t('language');?></label>
            <?php echo form_dropdown('language', get_languages(), get_form_value("language",isset($language) ? $language: '')); ?> 
    </div>
</fieldset>


<fieldset class="field-expanded ">
	<legend><i class="fas fa-edit mr-3" style="color:#007bff;"></i><?php echo t('use_html_editor_for_html');?></legend>
    <div class="field" >
        <label style="height:50px;" for="use_html_editor"><?php echo t('use_html_editor');?></label>
        <input type="radio" value="yes" name="use_html_editor" <?php echo ($use_html_editor=='yes') ? 'checked="checked"' : ''; ?>/> <?php echo t('yes');?> 
        <input type="radio" value="no" name="use_html_editor" <?php echo ($use_html_editor!='yes') ? 'checked="checked"' : ''; ?>/> <?php echo t('no');?><br/>
    </div>
</fieldset>

<fieldset class="field-expanded ">
	<legend><i class="fas fa-user-cog mr-3" style="color:#007bff;"></i><?php echo t('survey_catalog_settings');?></legend>
	<div class="field">
        <label for="<?php echo 'catalog_root'; ?>"><?php echo t('catalog_folder');?></label>
        <input class="form-control" name="catalog_root" type="text" id="catalog_root"  value="<?php echo get_form_value('catalog_root',isset($catalog_root) ? $catalog_root : ''); ?>"/>
        <?php echo folder_exists($catalog_root);?>
        <span class="field-note"><?php echo t('instruction_catalog_root'); ?></span>
	</div>
	<div class="field">
        <label for="<?php echo 'ddi_import_folder'; ?>"><?php echo t('ddi_import_folder');?></label>
        <input class="form-control" name="ddi_import_folder" type="text" id="ddi_import_folder"  value="<?php echo get_form_value('ddi_import_folder',isset($ddi_import_folder) ? $ddi_import_folder : ''); ?>"/>
        <?php echo folder_exists($ddi_import_folder);?>
        <span class="field-note"><?php echo t('instruction_ddi_import_folder'); ?></span>
	</div>
	<div class="field">
        <label for="vocabulary"><?php echo t('select_vocabulary'); ?></label>
        <?php echo form_dropdown('topics_vocab', $this->configurations_model->get_vocabularies_array(), get_form_value("topics_vocab",isset($topics_vocab) ? $topics_vocab : '')); ?>
        <span class="field-note"><?php echo t('instruction_select_vocabulary'); ?></span>
	</div>

	<div class="field">
        <label style="height:50px;" for="<?php echo 'regional_search'; ?>"><?php echo t('regional_search');?></label>
        <div>
        <input type="radio" name="regional_search" value="yes" <?php echo ($regional_search=='yes') ? 'checked="checked"' : ''; ?>/> <?php echo t('regional_search_enable');?> <br/>
        <input type="radio" name="regional_search" value="no" <?php echo ($regional_search!='yes') ? 'checked="checked"' : ''; ?>/> <?php echo t('regional_search_disable');?>
        </div>
	</div>

	<div class="field">
        <label style="height:50px;" for="<?php echo 'topic_search'; ?>"><?php echo t('topic_search');?></label>
        <div>
        <input type="radio" name="topic_search" value="yes" <?php echo ($topic_search=='yes') ? 'checked="checked"' : ''; ?>/> <?php echo t('topic_search_enable');?> <br/>
        <input type="radio" name="topic_search" value="no" <?php echo ($topic_search!='yes') ? 'checked="checked"' : ''; ?>/> <?php echo t('topic_search_disable');?>
        </div>
	</div>

	<div class="field">
        <label style="height:50px;" for="<?php echo 'year_search'; ?>"><?php echo t('year_search');?></label>
        <div>
        <input type="radio" name="year_search" value="yes" <?php echo ($year_search=='yes') ? 'checked="checked"' : ''; ?>/> <?php echo t('year_search_enable');?> <br/>
        <input type="radio" name="year_search" value="no" <?php echo ($year_search!='yes') ? 'checked="checked"' : ''; ?>/> <?php echo t('year_search_disable');?>
        </div>
	</div>

	<div class="field">
        <label style="height:50px;" for="<?php echo 'collection_search'; ?>"><?php echo t('collection_search');?></label>
        <div>
        <input type="radio" name="collection_search" value="yes" <?php echo ($collection_search=='yes') ? 'checked="checked"' : ''; ?>/> <?php echo t('collection_search_enable');?> <br/>
        <input type="radio" name="collection_search" value="no" <?php echo ($collection_search!='yes') ? 'checked="checked"' : ''; ?>/> <?php echo t('collection_search_disable');?>
        </div>
	</div>

	<div class="field">
        <label style="height:50px;" for="<?php echo 'da_search'; ?>"><?php echo t('da_search');?></label>
        <div>
        <input type="radio" name="da_search" value="yes" <?php echo ($da_search=='yes') ? 'checked="checked"' : ''; ?>/> <?php echo t('da_search_enable');?> <br/>
        <input type="radio" name="da_search" value="no" <?php echo ($da_search!='yes') ? 'checked="checked"' : ''; ?>/> <?php echo t('da_search_disable');?>
        </div>
	</div>
    
    <div class="field list-group">
    	<div class="instructions"><?php echo t('instruction_weight'); ?></div>
    	<div>
        <label for="<?php echo 'da_search_weight'; ?>"><?php echo t('da_search_weight');?></label>
        <input class="input-fixed-3" name="da_search_weight" type="text" id="da_search_weight"  value="<?php echo get_form_value('da_search_weight',isset($da_search_weight) ? $da_search_weight : ''); ?>"/>
        
        </div>
        <div>
        <label for="<?php echo 'regional_search_weight'; ?>"><?php echo t('regional_search_weight');?></label>
        <input class="input-fixed-3" name="regional_search_weight" type="text" id="regional_search_weight"  value="<?php echo get_form_value('regional_search_weight',isset($regional_search_weight) ? $regional_search_weight : ''); ?>"/>
       
</div>
    <div>
        <label for="<?php echo 'topic_search_weight'; ?>"><?php echo t('topic_search_weight');?></label>
        <input class="input-fixed-3" name="topic_search_weight" type="text" id="topic_search_weight"  value="<?php echo get_form_value('topic_search_weight',isset($topic_search_weight) ? $topic_search_weight : ''); ?>"/>
  </div>
    <div>
        <label for="<?php echo 'year_search_weight'; ?>"><?php echo t('year_search_weight');?></label>
        <input class="input-fixed-3" name="year_search_weight" type="text" id="year_search_weight"  value="<?php echo get_form_value('year_search_weight',isset($year_search_weight) ? $year_search_weight : ''); ?>"/>  
	</div>

    <div>
        <label for="<?php echo 'collection_search_weight'; ?>"><?php echo t('collection_search_weight');?></label>
        <input class="input-fixed-3" name="collection_search_weight" type="text" id="collection_search_weight"  value="<?php echo get_form_value('collection_search_weight',isset($collection_search_weight) ? $collection_search_weight : ''); ?>"/>
	</div>
        
	</div>

	<div class="field">
        <label for="<?php echo 'catalog_records_per_page'; ?>"><?php echo t('data_catalog_page_size');?></label>
        <input class="input-fixed-3" name="catalog_records_per_page" type="text" id="catalog_records_per_page"  value="<?php echo get_form_value('catalog_records_per_page',isset($catalog_records_per_page) ? $catalog_records_per_page : ''); ?>"/>
        <span class="field-note"><?php echo t('instruction_catalog_records_per_page'); ?></span>        
	</div>
</fieldset>

<fieldset class="field-expanded ">
	<legend><i class="fas fa-user-circle mr-3" style="color:#007bff;"></i><?php echo t('site_login');?></legend>
    <div class="field">
            <label style="height:50px;" for="<?php echo 'site_password_protect'; ?>"><?php echo t('password_protect_website');?></label>
            <div>
                <input type="radio"  name="site_password_protect" value="yes" <?php echo ($site_password_protect=='yes') ? 'checked="checked"' : ''; ?>/> <?php echo t('require_all_users_to_login');?><br/>
                <input type="radio"  name="site_password_protect" value="no" <?php echo ($site_password_protect!='yes') ? 'checked="checked"' : ''; ?>/> <?php echo t('login_not_required');?>
            </div>
    </div>
    
    <div class="field">
            <label for="<?php echo 'login_timeout'; ?>"><?php echo t('login_timeout_in_min');?></label>
            <input class="form-control" name="login_timeout" type="text" id="login_timeout"  value="<?php echo get_form_value('login_timeout',isset($login_timeout) ? $login_timeout : ''); ?>"/>
    </div>
    
    <div class="field">
            <label for="<?php echo 'min_password_length'; ?>"><?php echo t('min_password_length');?></label>
            <input class="form-control" name="min_password_length" type="text" id="min_password_length"  value="<?php echo get_form_value('min_password_length',isset($min_password_length) ? $min_password_length : ''); ?>"/>
    </div>
</fieldset>


<fieldset class="field-expanded ">

        <legend><i class="fas fa-chart-line mr-3" style="color:#007bff;"></i><?php echo t('Google Analytics');?></legend>
    
    <div class="field">
            <label for="google_analytics"><?php echo t('Google analytics UA code');?></label>
            <input class="form-control" name="google_ua_code" type="text" id="google_analytics" placeholder="UA-XXXXXXXX-X"  value="<?php echo get_form_value('google_ua_code',isset($google_ua_code) ? $google_ua_code : ''); ?>"/>
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

<div style="text-align:right;">
	<input class="btn btn-primary" type="submit" value="<?php echo t('update');?>" name="submit"/>
</div>

<?php echo form_close();?>
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
             $output[$lang]=$lang;
          }	
	}	
	return $output;
}
?>
