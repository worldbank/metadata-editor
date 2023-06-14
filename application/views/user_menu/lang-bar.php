<?php
//build a list of links for available languages
$languages=$this->config->item("supported_languages");
$language_codes=$this->config->item("language_codes");

$current_page=$this->uri->uri_string();

$lang_ul='';
if (!$languages)
{
    return;
}
?>
<li class="nav-item dropdown">
        <div class="dropdown ml-auto">
            <a class="nav-link dropdown-toggle capitalize" href="#" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="mdi mdi-translate"></i> 
            </a>

            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuLink">                
                <?php foreach($languages as $language):?>
                    <?php if (isset($language_codes[$language])):?>
                        <?php $language_info=$language_codes[$language];?>
                        <a class="dropdown-item" href="<?php echo site_url('switch_language/'.$language.'?destination='.$current_page); ?>"><?php echo $language_info['display'];?> </a>
                    <?php endif;?>
                <?php endforeach;?>                
            </div>
        </div>
</li>
<!-- /row -->
