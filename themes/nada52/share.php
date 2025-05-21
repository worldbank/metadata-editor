<?php
//current page url
$page_url=current_url();

//remove site url from the url string
$page_url=str_replace(site_url(),"",$page_url);

$parts=explode("?",$page_url);

//split URI parts
$uri_parts=explode("/",$parts[0]);

$uri=site_url();
foreach($uri_parts as $part)
{
    if (!ctype_alnum($part))
    {
        $uri.=urlencode($part).'/';
    }
    else
    {
        $uri.=$part.'/';
    }
}
$page_url=$uri;
if (isset($parts[1]))
{
    $page_url.=urlencode($parts[1]);
}
?>

<ul class="share-bar">
    <li>
        <a rel="nofollow" title="<?php echo t('share_with_facebook');?>" href="http://www.facebook.com/sharer.php?u=<?php echo $page_url; ?>&t=<?php echo $title; ?>&src=sp">
            <i class="fa fa-facebook fa-lg"></i>
        </a>
    </li>
    <li>
        <a title="<?php echo t('share_with_linkedin');?>" href="http://www.linkedin.com/cws/share?url=<?php echo $page_url;?>&title=<?php echo $title;?>&source=<?php echo site_url();?>" class="share">
            <i class="fa fa-linkedin fa-lg"></i>
        </a>
    </li>
    <li>
        <a target="_blank" title="Share with Gmail" href="#">
            <i class="fa fa-google fa-lg"></i>
        </a>
    </li>
    <li>
        <a target="_blank" title="<?php echo t('share_with_twitter');?>" href="http://twitter.com/share?_=original_referer=<?php echo $page_url; ?>&text=<?php echo $title; ?>&url=<?php echo $page_url; ?>">
            <i class="fa fa-twitter fa-lg"></i>
        </a>
    </li>
</ul>
