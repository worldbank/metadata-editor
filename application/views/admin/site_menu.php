<ul class="nav navbar-nav rounded-0x shadow-sm">

    <?php foreach($items as $item):?>
        <?php if(!isset($item['items'])):?>
            <li class="dropdown-submenu"><a href="<?php echo site_url($item['url']);?>"><?php echo t($item['title']);?></a></li>
        <?php elseif(isset($item['items'])):?>
            <li class="dropdown">
                <a href="admin/menu" class="dropdown-toggle" data-toggle="dropdown"><?php echo t($item['title']);?><b class="caret"></b></a>
                <ul class="dropdown-menu shadow-sm rounded-0x">
                    <?php foreach($item['items'] as $sub_menu_item):?>
                        
                    <li class="dropdown-submenu">

                        <?php if(isset($sub_menu_item['type']) &&  $sub_menu_item['type']=='divider'):?>
                            <li class="dropdown-divider"></li>
                            <?php continue;?>
                        <?php endif;?>

                        <?php if($sub_menu_item['title']=='Manage studies'):?>
                            <li class="dropdown-submenu"><a tabindex="-1" data-toggle="dropdown" class="dropdown-item dropdown-toggle" href="<?php echo site_url($sub_menu_item['url']);?>"><?php echo t($sub_menu_item['title']);?></a>
                            <?php echo $collections;?>
                        <?php else:?>
                            <a href="<?php echo site_url($sub_menu_item['url']);?>"><?php echo t($sub_menu_item['title']);?></a>
                        <?php endif;?>
                    </li>                        

                    <?php endforeach;?>
                </ul>
            </li>
        <?php endif;?>
    <?php endforeach;?>

</ul>