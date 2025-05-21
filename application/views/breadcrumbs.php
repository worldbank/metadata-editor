<?php if (isset($breadcrumbs) && is_array($breadcrumbs) && count($breadcrumbs)>0):?>
	<?php 
        $total=count($breadcrumbs);
        $k=1;
    ?>
    <div class="breadcrumbs" xmlns:v="http://rdf.data-vocabulary.org/#">
    <?php foreach($breadcrumbs as $url=>$title):?>
       <span typeof="v:Breadcrumb">
        <?php if ($k!==$total):?>
        	<?php if (!is_numeric($url)):?>
	         <a href="<?php echo site_url().'/'.$url;?>" rel="v:url" property="v:title"><?php echo $title;?></a> /
            <?php else:?>
    	      	<?php echo $title;?> ›
            <?php endif;?>
        <?php else:?>
	         <?php if (!is_numeric($url)):?>
	         <a class="active" href="<?php echo site_url().'/'.$url;?>" rel="v:url" property="v:title"><?php echo $title;?></a>
            <?php else:?>
    	      	<?php echo $title;?>
            <?php endif;?>
        <?php endif;?>    
       </span>
       <?php $k++;?>
    <?php endforeach;?>
    </div>
<?php endif;?>