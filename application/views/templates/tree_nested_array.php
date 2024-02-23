<ul>
<?php foreach($parent_item['props'] as $item):?>

    <li class="node">
        <a href="#<?php echo str_replace(".","-",$parent_item['key'].'.'.$item['key']);?>"><?php echo $item['title'];?></a> <span class="node-type"><?php echo $item['type'];?></span>

        <?php if (isset($item['items'])):?>
            <?php echo $this->load->view('templates/tree',array('parent_item'=>$item),true); ?>
        <?php endif;?>

        <?php if (isset($item['props'])):?>
            <?php echo $this->load->view('templates/tree_nested_array',array('parent_item'=>$item),true); ?>
        <?php endif;?>

    </li>
    
<?php endforeach;?>
</ul>