<style>
.share-bar-container{position:relative;text-align:right;border:0px solid blue;margin-left:auto;margin-right:0px;z-index:899;height:35px;}
ul.share-bar{position:absolute;border:0px solid red;height:30px;width:auto;text-align:left;right:0px;z-index:999}
ul.share-bar li{float:left;margin-left:3px;border:0px solid blue;padding:5px;position:relative;height:24px;vertical-align:baseline}
ul.share-bar .item-body{position:absolute;height:100px;width:100px;background:white;border:2px solid #CCCCCC;right:0px;z-index:100}

ul.share-bar ul{display:none;position:absolute;width:220px;background:#F7F7F7;right:0px;top:24px;padding:10px;border:4px solid #CCCCCC;z-index:1000}
ul.share-bar ul li{float:left;width:45%;font-size:11px;margin:0px;padding:5px;}
ul.share-bar ul li:hover{background-color:#FBFBFB; cursor:pointer}
ur.share-bar ul li img{vertical-align:baseline}
ul.share-bar li.active ul{display:block;}
ul.share-bar li.active{background-color:#CCCCCC}
a.share{background:url("images/shared_open.gif") no-repeat left center;padding-left:20px;color:#999999;text-transform:uppercase;font-size:11px}
.active a.share{background:url("images/shared_close.gif") no-repeat left center;color:black;}
ul.share-bar img{vertical-align:baseline}

</style>

<div class="share-bar-container">
<ul class="share-bar">
    <li><a target="_blank" rel="nofollow" href="<?php echo current_url();?>/?print=yes"><img src="images/print.gif" alt="Print"/></a></li>
    <li><a rel="nofollow" href="mailto:?subject=<?php echo $title;?>&amp;body=<?php echo current_url();?>"><img src="images/email.png" alt="Email" vspace="2" /></li>
    <li>
    	<a href="#" class="share">Share</a>
        <ul class="">
            <li><a title="<?php echo t('share_with_digg');?> "target="_blank" href="http://digg.com/submit?url=<?php echo current_url();?>&title=<?php echo $title;?>"><img src="images/icons/digg16.png"/> Digg</a></li>
            <li><a title="<?php echo t('share_with_buzz');?>" target="_blank" href="http://www.google.com/buzz/post?message=<?php echo $title;?>&url=<?php echo current_url();?>"><img src="images/icons/buzz16.png"/> Google Buzz</a></li>
            <li><a title="<?php echo t('share_with_linkedin');?>" target="_blank" href="http://www.linkedin.com/cws/share?url=<?php echo current_url();?>&title=<?php echo $title;?>&source=<?php echo site_url();?>"><img src="images/icons/linkedin16.png"/> LinkedIn</a></li>
            <li><a title="<?php echo t('share_with_stumpleupon');?>" target="_blank" href="http://www.stumbleupon.com/submit?url=<?php echo current_url(); ?>&title=<?php echo $title;?>"><img src="images/icons/stumbleupon16.png"/> Stumbleupon</a></li>
            <li><a title="<?php echo t('share_with_delicious');?>" target="_blank" href="http://www.delicious.com/save?v=5&noui&jump=close&url=<?php echo current_url(); ?>&title=<?php echo $title; ?>"><img src="images/icons/delicious16.png"/> Delicious</a></li>
        </ul>
    </li>
    <li><a target="_blank" 	title="<?php echo t('share_with_facebook');?>"	href="http://www.facebook.com/sharer.php?u=<?php echo current_url(); ?>&t=<?php echo $title; ?>&src=sp"><img src="images/facebook.png"/></a></li>
    <li><a target="_blank" title="<?php echo t('share_with_twitter');?>"href="http://twitter.com/share?_=original_referer=<?php echo current_url(); ?>&text=<?php echo $title; ?>&url=<?php echo current_url(); ?>"><img src="images/twitter.png" alt="Twitter" /></a></li>
</ul>
</div>

<script type="text/javascript">
jQuery(function($) {
	$('.share-bar .share').click(toggle_menu);
	function toggle_menu(event)
	{
		if ($(this).parent().is(".active"))
		{
			$(this).parent().removeClass("active");
		}
		else
		{
			$(this).parent().addClass('active');
		}	
		event.stopPropagation();	
		return false;
	}
	
	$('body').click(function(event) {
 		//hide the share box
		if(!$(event.target).is('.active ul, .active li'))
		{
			$('.share-bar .active').removeClass("active");
		}
 	});
 
});
</script>
