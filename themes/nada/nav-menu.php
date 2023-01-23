<!-- Start menus -->
<?php $menus= isset($data['menus']) ? $data['menus'] : false;?>
<div class="navbar-collapse collapse" id="containerNavbar" aria-expanded="false">
<?php if (isset($menus)): ?>
    <?php $current_page=current_url();?>
    <ul class="navbar-nav ml-auto">
        <?php foreach($menus as $item):?>
            <?php
            if ($item['target']==1){
                $target='target="_blank"';
            }
            else{
                $target="";
            }
            //if internal link, add site url
            if (!is_url($item['url'])){
                $item['url']=site_url($item['url']);
            }
            ?>
            <?php $this->template->write('title', $item['title'],true);?>
            <li class="nav-item">
                <a <?php echo $target; ?> <?php echo ($item['url']==$current_page) ? 'class="nav-link active"' : ''; ?> class="nav-link" href="<?php echo $item['url'];?>"><?php echo $item['title'];?></a>
            </li>            
        <?php endforeach; ?>
    </ul>
<?php endif;?>

</div>
<!-- Close Menus -->