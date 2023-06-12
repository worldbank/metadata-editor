<style type="text/css">
.caption{
	padding-left:20px;text-transform:capitalize;
}
.description{
	color:#666666;padding-left:20px;
}
.group-description{
	color:#666666;font-size:smaller;
}
.header{border-bottom:2px solid;border-top:0px !important;font-size:16px;}
.grid-table .br td{border:0px;}
.h1{margin-top:20px;}
.group-name{font-weight:bold;}
.permission-caption label{
	font-weight:normal;
	text-transform:capitalize;
}
.perms-by-collection td{
	border:none!important
}
</style>

<div class="container-fluid">
	<?php $this->load->view('permissions/links');?>
	<h1><?php echo t('manage_permissions');?></h1>

	<?php if (validation_errors() ) : ?>
		<div class="error">
			<?php echo validation_errors(); ?>
		</div>
	<?php endif; ?>

	<?php $error=$this->session->flashdata('error');?>
	<?php echo ($error!="") ? '<div class="error">'.$error.'</div>' : '';?>

	<?php $message=$this->session->flashdata('message');?>
	<?php echo ($message!="") ? '<div class="success">'.$message.'</div>' : '';?>

<?php //$post_values=$this->input->post('resource');?>

<div class="row">
<div class="col-md-2" >
	
	<ul class="list-group">
		<?php foreach($roles as $role):?>
			<a class="list-group-item <?php echo $role['id']==$active_id ? 'active' : '';?>"  href="<?php echo site_url('admin/permissions/manage/'.$role['id']);?>"><?php echo ($role['name']);?></a>
		<?php endforeach;?>
	</ul>

</div>

<div class="col-md-9">
<form method="post">
	<!--<pre>		
		<?php //var_dump($_POST['resource']['dashboard']); var_dump($this->input->post('resource'));
			//var_dump($post_values);
		?>
	</pre>-->
	<table class="table trable-striped">
	<?php foreach ($permissions as $resource=>$rule):?>
		<tr>
			<td colspan="3" class="header">
				<div class="group-name">
					<?php echo t($rule['title']);?>
				</div>
				<?php if(isset($rule['description'])):?>
					<div class="group-description"><?php echo t($rule['description']);?></div>
				<?php endif;?>
			</td>
		</tr>
		<?php $x = 0; ?>
		<?php foreach($rule['permissions'] as $perm):?>
			<tr class="<?php echo ($x++%2==1) ? '' : 'alternate' ?>">
			<td style="width:50px;text-align:right;">
				<?php 					
					$is_checked='';
					if (isset($post_values[$resource])  && in_array($perm['permission'],$post_values[$resource] )){
						$is_checked='checked="checked"';
					}
				?>
				<input 
					<?php echo $is_checked;?>
					type="checkbox" 
					id="<?php echo $resource;?>.<?php echo $perm['permission'];?>" 
					name="resource[<?php echo $resource;?>][]" 
					value="<?php echo $perm['permission'];?>"/>
			</td>
			<td>
				<div class="caption">
					<label for="<?php echo $resource;?>.<?php echo $perm['permission'];?>">
						<?php echo $perm['permission'];?>
					</label>
				</div>
				<div class="description"><?php echo isset($perm['description']) ? $perm['description'] : '';?></div>
			</td>        
			</tr>
		<?php endforeach;?> 
		<tr class="br"><td colspan="2">&nbsp;</td></tr>
	<?php endforeach;?>

	<?php /*				
	<tr>
		<td colspan="3"><h2><?php echo t('Permissions by collections');?></h2></td>
	</tr>
	<?php foreach ($permissions_collections as $resource=>$rule):?>
		<tr>
			<td colspan="3">
				<table class="table table-sm perms-by-collection">
					<tr>
						<td style="width:30%;"><div class="group-name"><?php echo str_replace(" - " , "<BR>",$rule['title']);?></div></td>
						
						<?php $x = 0; ?>
						<?php foreach($rule['permissions'] as $perm):?>
							<td class="<?php echo ($x++%2==1) ? '' : 'alternate' ?>">
							<span>
							
								<?php 					
									$is_checked='';
									if (isset($post_values[$resource])  && in_array($perm['permission'],$post_values[$resource] )){
										$is_checked='checked="checked"';
									}
								?>
								<input 
									<?php echo $is_checked;?>
									type="checkbox" 
									id="<?php echo $resource;?>.<?php echo $perm['permission'];?>" 
									name="resource[<?php echo $resource;?>][]" 
									value="<?php echo $perm['permission'];?>"/>
								</span>
							<span>
								<span class="permission-caption">
									<label for="<?php echo $resource;?>.<?php echo $perm['permission'];?>">
										<?php echo $perm['permission'];?>
									</label>
								</span>
								
								</span>        
						</td>					
						<?php endforeach;?>
					</tr>


				</table>
			</td>
			<td>
				
			</td> 
	<?php endforeach;?>
	
	*/?>



	<?php /* ?>

		<tr>
			<td colspan="3"><h2><?php echo t('Permissions by collections');?></h2></td>
		</tr>
	<?php foreach ($permissions_collections as $resource=>$rule):?>
		<tr>
			<td colspan="3" class="header">
				<div class="group-name"><?php echo t($rule['title']);?></div>
			</td>
		</tr>
		<?php $x = 0; ?>
		<?php foreach($rule['permissions'] as $perm):?>
			<tr class="<?php echo ($x++%2==1) ? '' : 'alternate' ?>">
			<td style="width:50px;text-align:right;">
				<?php 					
					$is_checked='';
					if (isset($post_values[$resource])  && in_array($perm['permission'],$post_values[$resource] )){
						$is_checked='checked="checked"';
					}
				?>
				<input 
					<?php echo $is_checked;?>
					type="checkbox" 
					id="<?php echo $resource;?>.<?php echo $perm['permission'];?>" 
					name="resource[<?php echo $resource;?>][]" 
					value="<?php echo $perm['permission'];?>"/>
			</td>
			<td>
				<div class="caption">
					<label for="<?php echo $resource;?>.<?php echo $perm['permission'];?>">
						<?php echo $perm['permission'];?>
					</label>
				</div>
				<div class="description"><?php echo isset($perm['description']) ? $perm['description'] : '';?></div>
			</td>        
			</tr>
		<?php endforeach;?> 
		<tr class="br"><td colspan="2">&nbsp;</td></tr>
	<?php endforeach;?>
	<?php */ ?>

	</table>
	<button type="submit" class="btn btn-primary"><?php echo t('submit');?></button>
</form>
</div>
</div>

</div>