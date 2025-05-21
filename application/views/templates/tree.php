<ul>
<?php foreach($parent_item['items'] as $item):?>

    <li class="node">
        <a href="#<?php echo str_replace(".","-",$item['key']);?>"><?php echo $item['title'];?></a> 
        <?php if(isset($item['is_required']) && $item['is_required']===true):?>
            <sup title="Required" style="color:red;">M</sup>
        <?php endif;?>
        <span class="node-type"><?php echo $item['type'];?></span>

        <?php if (isset($item['items'])):?>
            <?php echo $this->load->view('templates/tree',array('parent_item'=>$item),true); ?>
        <?php endif;?>

        <?php if (isset($item['props'])):?>
            <?php echo $this->load->view('templates/tree_nested_array',array('parent_item'=>$item),true); ?>
        <?php endif;?>

    </li>

<?php endforeach;?>
</ul>