<?php

$selected_collection = (int) $this->input->get('collection', true);?>

<style>
.description{color:gray;margin-left:25px;}
.hide{display:none;}
.field{margin-bottom:10px;}
.collection-title{font-weight:bold;font-size:16px;margin-top:10px;margin-bottom:10px;}
.collection-roles-container{margin-left:50px;}
fieldset {border:0px; border-top:1px solid gray;padding-top:15px;margin-top:20px;}
legend{font-weight:bold;font-size:20px;padding:10px;}
.collection-container{border-bottom:1px solid gainsboro;}

.limited-access,.roles-list{margin-left:50px;margin-top:10px;}
.access-type-container .caption{font-weight:bold;}
.access-type-container{margin-bottom:10px;}
.disabled{color:#999999}
.selected{background:gainsboro;padding:5px;}
</style>

<div class="container-fluid content-fluid">

<?php if (validation_errors()): ?>
    <div class="alert alert-danger">
	    <?php echo validation_errors(); ?>
    </div>
<?php endif;?>

<?php $error = $this->session->flashdata('error');?>
<?php echo ($error != "") ? '<div class="alert alert-danger">' . $error . '</div>' : ''; ?>

<?php if (isset($message)): ?>
<?php echo ($message != "") ? '<div class="alert alert-success">' . $message . '</div>' : ''; ?>
<?php endif;?>

<h3 class="page title text-capitalize mb-3 mt-5"><?php echo t('edit_user_permissions'); ?> - <span class="text-danger"><?php echo $user->first_name . ' ' . $user->last_name; ?> </span></h3>

<div class="text-right page-links">
	<a href="<?php echo site_url(); ?>/admin/users" class="btn btn-outline-primary btn-sm">
	<i class="fa fa-user-circle" aria-hidden="true">&nbsp;</i> <?php echo t('users'); ?></a>
    <a href="<?php echo site_url(); ?>/admin/users/add" class="btn btn-outline-primary btn-sm">
	<i class="fa fa-plus-circle" aria-hidden="true">&nbsp;</i> <?php echo t('create_user_account'); ?></a>
</div>

<form method="post">
<h3 class=" text-primary  text-capitalize mb-3">
<fieldset>
<legend><?php echo t('user_site_level_permissions'); ?></legend>
</h3>
<div class="access-type-container">
	<label for="access_type_none">
    	<input type="radio" class="access_type" name="access_type" id="access_type_none" value="none"  <?php echo $user_group_access_type == 'none' ? 'checked="checked"' : ''; ?>/>
    	<span class="caption"><?php echo t('general_user_accounts'); ?></span>
    	<div class="description"><?php echo t('general_user_accounts_description'); ?></div>
    </label>

    <div class="roles-list">
    <?php foreach ($global_roles['user'] as $role): ?>
        <div class="field">
            <label for="user-role-<?php echo $role['id']; ?>">
                <input type="checkbox" id="user-role-<?php echo $role['id']; ?>" name="global_role[]" value="<?php echo $role['id']; ?>" <?php echo in_array($role['id'], $assigned_roles['global']) ? 'checked="checked"' : ''; ?> />
                <?php echo t($role['name']); ?>
            </label>
            <div class="description"><?php echo t($role['description']); ?></div>
        </div>
    <?php endforeach;?>
    </div>
</div>


<?php /* ?>
<!--reviewers-->
<div class="access-type-container">
<label for="access_type_review">
<input type="radio" class="access_type" name="access_type" id="access_type_review" value="review"  <?php echo $user_group_access_type=='review' ? 'checked="checked"' : '';?>/>
<span class="caption"><?php echo t('site_reviewer_accounts');?></span>
<div class="description"><?php echo t('site_reviewer_accounts_description');?></div>
</label>

<div class="roles-list">
<?php foreach($global_roles['reviewer'] as $role):?>
<div class="field">
<label for="global-role-">
<input type="checkbox" name="global_role[]" value="<?php echo $role['id'];?>" <?php echo in_array($role['id'],$assigned_roles['global']) ? 'checked="checked"' : '';?> />
<?php echo t($role['name']);?>
</label>
<div class="description"><?php echo t($role['description']);?></div>
</div>
<?php endforeach;?>
</div>
</div>
<?php */?>

<!--admins-->
<div class="access-type-container">
	<label for="access_type_unlimited">
    	<input type="radio" class="access_type" name="access_type" id="access_type_unlimited" value="unlimited"  <?php echo $user_group_access_type == 'unlimited' ? 'checked="checked"' : ''; ?>/>
    	<span class="caption"><?php echo t('site_admin_accounts'); ?></span>
    	<div class="description"><?php echo t('site_admin_accounts_description'); ?></div>
    </label>

    <div class="roles-list">
    <?php foreach ($global_roles['unlimited'] as $role): ?>
        <div class="field">
            <label for="unlimited-role-<?php echo $role['id']; ?>">
                <input type="checkbox" id="unlimited-role-<?php echo $role['id']; ?>" name="global_role[]" value="<?php echo $role['id']; ?>" <?php echo in_array($role['id'], $assigned_roles['global']) ? 'checked="checked"' : ''; ?> />
                <?php echo t($role['name']); ?>
            </label>
            <div class="description"><?php echo t($role['description']); ?></div>
        </div>
    <?php endforeach;?>
    </div>
</div>


<!-- limited -->
<div class="access-type-container">
	<label for="access_type_limited">
    	<input type="radio" class="access_type" name="access_type" id="access_type_limited" value="limited"  <?php echo $user_group_access_type == 'limited' ? 'checked="checked"' : ''; ?>/>
    	<span class="caption"><?php echo t('site_admin_limited_accounts'); ?></span>
    	<div class="description"><?php echo t('site_admin_limited_accounts_description'); ?></div>
    </label>

    <div class="roles-list">
		<?php foreach ($global_roles['limited'] as $role): ?>
			<div class="field">
				<label for="limited-role-<?php echo $role['id']; ?>">
					<input class="user_role" id="limited-role-<?php echo $role['id']; ?>" type="checkbox" name="global_role[]" value="<?php echo $role['id']; ?>" <?php echo in_array($role['id'], $assigned_roles['global']) ? 'checked="checked"' : ''; ?> data-is_collection_group="<?php echo $role['is_collection_group']; ?>" />
					<?php echo t($role['name']); ?>
				</label>
				<div class="description"><?php echo t($role['description']); ?></div>
			</div>
		<?php endforeach;?>
    </div>
</div>


</fieldset>

<fieldset class="collection-perms-container">
<legend><?php echo t('permissions_per_collection'); ?></legend>
<div>
<?php foreach ($collections as $collection): ?>
<div class="collection-container collection-container-<?php echo $collection['id']; ?>">
	<div class="collection-title"><?php echo $collection['title']; ?></div>
    <div class="collection-roles-container">
    <?php foreach ($collection_roles as $role): ?>
	<div class="field">
    	<label for="global-role-">
			<input type="checkbox" name="collection_role[<?php echo $collection['id']; ?>][]" value="<?php echo $role['repo_pg_id']; ?>"  <?php echo in_array($role['repo_pg_id'], $assigned_roles['collections'][$collection['id']]) ? 'checked="checked"' : ''; ?>/>
			<?php echo t($role['title']); ?>
        </label>
        <div class="description"><?php echo t($role['description']); ?></div>
	</div>
    <?php endforeach;?>
    </div>
</div>
<?php endforeach;?>
</div>
</fieldset>

<input class="btn btn-primary btn-sm" type="submit" name="submit" value="<?php echo t('update'); ?>"/>
<a class="btn btn-secondary btn-sm" href="<?php echo site_url($destination); ?>"><?php echo t('cancel'); ?></a>
</form>
</div>


<script type="text/javascript">
$(function() {

		$(".access_type,.user_role").click(function(e){
			show_hide_sub_roles();
		});

		function show_hide_sub_roles()
		{
			$(".access_type").each(function(){

				var parent_=$(this).closest(".access-type-container");
				console.log(parent);

				if ($(this).is(":checked")){
					parent_.find(".roles-list").show();
					parent_.find(".roles-list input").removeAttr('disabled');
				}
				else{
					parent_.find(".roles-list").hide();
					parent_.find(".roles-list input").attr('disabled','disabled');
				}
			});

			var checked_option=$(".access_type:checked");
			var found=false;
			checked_option.closest(".access-type-container").find(".user_role:checked").each(function(){
				var is_collection_group=$(this).attr("data-is_collection_group");
				if ( checked_option.val()=='limited' && is_collection_group==1){
					found=true;
				}
			});

			if (found==true){
				$(".collection-perms-container").show();
				$(".collection-perms-container input").removeAttr("disabled");
			}
			else{
				$(".collection-perms-container").hide();
				$(".collection-perms-container input").attr("disabled","disabled");
			}
		}


		function show_hide_collection(id)
		{
			if (id<1){return;}
			$(".collection-container").hide();
			$(".collection-container-"+id).show();
		}

		show_hide_sub_roles();
		show_hide_collection(<?php echo $selected_collection; ?>);

});
</script>
