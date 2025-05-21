<div class='content-container' style="margin:10px;margin-top:20px;">
<h1><?php echo t('install_completed');?></h1>
<p style="margin-top:10px;"><?php echo t('install_completed_tasks_summary');?></p>

<table cellpadding="3" cellspacing="0" class="grid-table table table-sm table-striped">
<tr class="header">
<th><?php echo t('task');?></th>
<th><?php echo t('status');?></th>
</tr>
<tr>
	<td><?php echo t('database');?></td>
	<td><?php echo t('Done');?></td>
</tr>
<tr>
	<td><?php echo t('tables');?></td>
	<td><?php echo t('Done');?></td>
</tr>
<tr>
	<td><?php echo t('admin_account');?></td>
	<td><?php echo t('Done');?></td>
</tr>
</table>
</div>

<div class="p-3 ">
 	<a class="btn btn-success rounded" href="<?php echo site_url();?>"><?php echo t('click_here_to_launch_application');?></a>
</div>     
     
