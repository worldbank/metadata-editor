<?php if (in_array($language, $rtl_languages)): ?>
<style>
.translation-container .flex-textarea{direction:rtl;}
</style>
<?php endif;?>
<style>
.edit-lang{
    background:gainsboro;
}
.not-found td{background:#dc3545;color:white;}
</style>

<div class="container-fluid translation-container" >

<div class="page-links text-right">
	<a href="<?php echo site_url('admin/translate/'); ?>" class="btn btn-outline-primary btn-sm text-right"><i class="fas fa-home mr-1"></i><?php echo t('Home'); ?></a>
</div>

<h1 class="page-title"><?php echo t('translate'); ?></h1>

<div class="container-fluid">

  <div class="row">
    <div class="col-md-3">
      <div class="list-group choose-file-container">
        <!--Sidebar content-->
            <div class="sidebar base-lang hide">
            Base template language: BASE
            </div>

            <div class="list-group-item list-group-item-action  edit-lang">
            <form class="choose-lang" method="post" action="<?php echo site_url("admin/translate/change_lang"); ?>" name="change-lang" id="form-change-lang">
            <h5 class="mb-1">Select language to translate  </h5>
            <select class="form-control-sm" name="lang" id="lang">
                <?php foreach ($languages as $lang): ?>
                <option value="<?php echo $lang; ?>" <?php echo ($language == $lang) ? 'selected="selected"' : ''; ?> ><?php echo $lang; ?></option>
                <?php endforeach;?>
            </select>
            </form>
            </div>


       		<div class="file-list" >
        	<?php foreach ($files as $file): ?>
            	<?php $sname = str_replace("_lang.php", "", $file);?>
        		<?php
$sname = str_replace("_", " ", $sname);
$lang_filename = str_replace('_lang.php', '', $file);
?>
                <div>
                	<div class="translation-file">
                    	<a class="list-group-item list-group-item-action <?php echo ($active_lang_file == $lang_filename) ? 'active' : ''; ?>" href="<?php echo site_url('admin/translate/edit/' . $language . '/' . str_replace('_lang.php', '', $file)); ?>"><?php echo $sname; ?></a>
                    </div>
                </div>
            <?php endforeach;?>
            </div>
      </div>



    </div>

    <div class="col-md-9">
      <!--Body content-->

        <?php if (isset($active_lang_file)): ?>
        <div class="edit-lang-info">
          File: <span class="lang-name"><?php echo $language; ?></span> / <span class="lang-file-name"><?php echo $active_lang_file; ?></span>
            <span style="color:gray;font-size:smaller;"><?php echo $edit_file_fullpath; ?></span>
        </div>
        <?php endif;?>

		<?php if (isset($save_status)): $save_status = (object) $save_status;?>
	            <?php if ($save_status->type == 'success'): ?>
	                <div class="success"><?php echo $save_status->msg; ?></div>
	            <?php endif;?>

            <?php if ($save_status->type == 'error'): ?>
                <div class="error"><?php echo $save_status->msg; ?></div>
            <?php endif;?>
        <?php endif;?>

	 <div class="form-body">
      <?php $this->load->view('translator/edit_file');?>
      </div>
    </div>
  </div>
</div>

</div>

<script type='text/javascript' >
$(document).on('change','#lang', function() {
    $("#form-change-lang").submit();
    return false;
});
</script>