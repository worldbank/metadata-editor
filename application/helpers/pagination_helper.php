<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Create custom pager
 *
 * @access	public
 * @param	total_records	number of total records
 * @param	limit			how many pages to show
 * @param	current_page	current page - gets highlighted as active
 * @param	adjacents		how many page numbers to show before and after current page
 * @param   page_url        page url
 *
 * @return	string	httml formatted pager
 */	
if ( ! function_exists('pager'))
{
    function pager($total_records, $limit = null, $current_page = null, $adjacents = null, $page_url='')
    {
        $total_pages=ceil($total_records / $limit);

        if ($total_pages<2)
        {
            return false;
        }

        $result = range(1, ceil($total_records / $limit));
        $search_qs='?'.get_querystring( array('tab_type','ps','sk', 'vk', 'vf','view','topic','country','from','to','sort_by','sort_order','collection'));

        if (($adjacents = floor($adjacents / 2) * 2 + 1) >= 1)
        {
            $result = array_slice($result, max(0, min(count($result) - $adjacents, intval($current_page) - ceil($adjacents / 2))), $adjacents);
        }

        $output=array();
        if ($current_page>1 && $total_pages>1)
        {
            $output[]=sprintf('<li class="page-item"><a href="%s" class="page-link"> &laquo; </a></li>',
                site_url("$page_url/").$search_qs.'&page=1',1
            );

            $output[]=sprintf('<li class="page-item"><a href="%s" class="page-link" data-page="%s">'.t('prev').' </a></li>',
                site_url("$page_url/").$search_qs.'&page='.($current_page-1),
                $current_page-1,
                t('prev')
            );
        }

        foreach($result as $page)
        {
            $css='';
            if($page==$current_page)
            {
                $css='active';
            }

            $output[]=sprintf('<li class="page-item"><a href="%s" class="page-link %s" data-page="%s">%s</a></li>',
                site_url("$page_url/").$search_qs.'&page='.$page,
                $css,
                $page,
                $page);
        }

        if ($current_page<$total_pages)
        {
            $output[]=sprintf('<li class="page-item"><a href="%s" class="page-link" data-page="%s"> %s </a></li>',
                site_url("$page_url/").$search_qs.'&page='.($current_page+1),
                $current_page+1,
                t('next')
            );

            $output[]=sprintf('<li class="page-item"><a href="%s" class="page-link" data-page="%s" title="%s">&raquo;</a></li>',
                site_url("$page_url/").$search_qs.'&page='.($total_pages),
                $total_pages,t('Last')
            );

        }

        $pager='<ul class="pagination pagination-md custom-pager">';
        $pager.=implode('',$output);
        $pager.=' </ul>';
        return $pager;
    }
}



/* End of file pagination_helper.php */
/* Location: ./application/helpers/pagination_helper.php */
