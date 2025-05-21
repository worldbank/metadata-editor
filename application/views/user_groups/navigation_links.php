<?php
/**
* navigation links
*/
?>
<div class="page-links">
	<?php if ($this->uri->segment(2)=='user_groups' && $this->uri->segment(3)!=''):?>
		<a href="<?php echo site_url(); ?>/admin/user_groups" class="btn btn-default">
			<i class="fa fa-home" aria-hidden="true"></i><?php echo t('user_groups_home');?>
		</a> 
    <?php endif;?>
    <?php if ($this->uri->segment(2)=='user_groups' && $this->uri->segment(3)!='add'):?>
		<a href="<?php echo site_url(); ?>/admin/user_groups/add" class="btn btn-default">
			<i class="fa fa-user-plus" aria-hidden="true"></i>
			<?php echo t('user_groups_add_page');?>
		</a> 
    <?php endif;?>
</div>
