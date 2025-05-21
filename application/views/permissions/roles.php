<style type="text/css">
.role-name{
	text-transform: capitalize;
}
</style>

<div class="container-fluid">
<?php $this->load->view('permissions/links');?>
<h1><?php echo t('Manage roles');?></h1>

<?php if (validation_errors() ) : ?>
    <div class="error">
	    <?php echo validation_errors(); ?>
    </div>
<?php endif; ?>

<?php $error=$this->session->flashdata('error');?>
<?php echo ($error!="") ? '<div class="error">'.$error.'</div>' : '';?>

<?php $message=$this->session->flashdata('message');?>
<?php echo ($message!="") ? '<div class="success">'.$message.'</div>' : '';?>

<hr>

<div>
<?php echo form_open(site_url('admin/permissions/create_role'), array('class'=>'form form-inline','autocomplete'=>'off'));?>	
	<h4><?php echo t('Create a new role');?></h4>
  <div class="form-group">
  <label class="sr-only" for="description"><?php echo t('Role');?></label>
    <input type="text" name="role" class="form-control" id="user_role" placeholder="Role/Group name" maxlength="100">
  </div>
  <div class="form-group" >
    <label class="sr-only" for="description"><?php echo t('Description');?></label>
    <input type="text" name="description" class="form-control" id="description" style="width:350px;" maxlength="255" placeholder="Short description">
  </div>
  <button type="submit" class="btn btn-primary"><?php echo t('Create a new role');?></button>
<?php echo form_close();?>
</div>

<hr>

<h3><?php echo t('Roles');?></h3>
<form method="post">
	<table class="table table-striped table-hover">
		<thead>
		<tr>
			<th><?php echo t('Role');?></th>
			<th><?php echo t('Description');?></th>
			<th></th>
		</tr>
		</thead>
		<tbody>
	<?php foreach ($roles as $role):?>
		<tr>
			<td class="role-name"><?php echo $role['name'];?></td>
			<td><?php echo $role['description'];?></td>
			<td>
				<?php if (!$role['is_locked']):?>
				<a href="<?php echo site_url('admin/permissions/edit_role/'.$role['id']);?>"><?php echo t('edit');?></a> | 				
				<a href="<?php echo site_url('admin/permissions/manage/'.$role['id']);?>"><?php echo t('permissions');?></a> | 				
				<a href="<?php echo site_url('admin/permissions/delete_role/'.$role['id']);?>"><?php echo t('delete');?></a>
				<?php endif;?>
			</td>
		</tr>
	<?php endforeach;?>
	</tbody>
	</table>
</form>


</div>