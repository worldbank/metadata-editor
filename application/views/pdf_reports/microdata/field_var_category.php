<?php if (isset($data) && is_array($data) && count($data)>0 ):?>
<div class="table-responsive field field-<?php echo $name;?>">
    <h3 class="xsl-caption field-caption"><?php echo t($name);?></h3>
    <div class="field-value">

            <?php
            $category_display_limit = 500;
            $show_stats=false;
            $stats_col=array();
            $stats_col_wgtd=array();

            $show_stats=true;
            $sum_cases=0;
            $sum_cases_wgtd=0;
            $cat_count=0;
            $last_cat=0;
            $max_value=0;
            $max_value_wgtd=0;

            foreach($data as $data_idx=>$item){

                //create wgtd and non-wgtd stats values
                $data[$data_idx]['stats_wgtd_value']=null;
                $data[$data_idx]['stats_non_wgtd_value']=null;

                if(!isset($item['stats']) || !is_array($item['stats'])){
                    continue;
                }

                foreach($item['stats'] as $stat_row){
                    //non-weighted stats
                    $wgtd_=isset($stat_row['wgtd']) ? $stat_row['wgtd'] : '';
                    if($wgtd_!=='wgtd'){
                        $data[$data_idx]['stats_non_wgtd_value']=$stat_row['value'];
                        $ismissing_=isset($item['is_missing']) ? $item['is_missing'] : '';
                        if($ismissing_==''){
                            $stats_col[]=$stat_row['value'];
                        }
                    }//weighted stats
                    else if ($stat_row['wgtd']==='wgtd'){
                        $data[$data_idx]['stats_wgtd_value']=$stat_row['value'];
                        $ismissing_=isset($item['is_missing']) ? $item['is_missing'] : '';
                        if($ismissing_==''){
                            $stats_col_wgtd[]=$stat_row['value'];
                        }
                    }
                }
            }

            if (count($stats_col)>0){
                $show_stats=true;
                $sum_cases=array_sum($stats_col);


                $cat_count=count($stats_col);
                $last_cat=$data[$cat_count-1];
                $max_value=max($stats_col);

            }
            if(count($stats_col_wgtd)>0){
                $max_value_wgtd=max($stats_col_wgtd);
                $sum_cases_wgtd=array_sum($stats_col_wgtd);
            }

            $total_categories = count($data);
            $categories_hidden = 0;
            if ($total_categories > $category_display_limit) {
                $categories_hidden = $total_categories - $category_display_limit;
                $data = array_slice($data, 0, $category_display_limit);
            }

            $show_percent = $show_stats && ($sum_cases>0 || $sum_cases_wgtd>0);
            ?>

            <table class="table table-stripped xsl-table" style="border-collapse: collapse; width: 100%;">
                <tr>
                    <th><?php echo t('value');?></th>
                    <th><?php echo t('category');?></th>
                    <?php if($show_stats && $sum_cases>0):?>
                        <th><?php echo t('cases');?></th>
                    <?php endif;?>
                    <?php if($show_stats && $sum_cases_wgtd>0):?>
                        <th><?php echo t('weighted');?></th>
                    <?php endif;?>
                    <?php if($show_percent):?>
                        <th><?php echo t('percentage');?></th>
                    <?php endif;?>
                </tr>
                <?php foreach($data as $cat):?>
                    <?php
                    $cat=(object)$cat;
                    $percent_display='';

                    if($show_stats && $sum_cases_wgtd>0 && isset($cat->stats_wgtd_value) && is_numeric($cat->stats_wgtd_value) && empty($cat->is_missing)){
                        $percent_display=@round($cat->stats_wgtd_value/$sum_cases_wgtd * 100,1).'%';
                    }
                    else if($show_stats && $sum_cases>0 && isset($cat->stats_non_wgtd_value) && is_numeric($cat->stats_non_wgtd_value) && empty($cat->is_missing)){
                        $percent_display=@round($cat->stats_non_wgtd_value/$sum_cases * 100,1).'%';
                    }
                    ?>

                    <tr>
                        <td><?php echo isset($cat->value) ? html_escape($cat->value) : '';?></td>
                        <td><?php echo isset($cat->labl) ? html_escape($cat->labl) : '';?> <?php echo isset($cat->label) ? html_escape($cat->label) : '';?></td>

                        <?php if($show_stats && $sum_cases>0):?>
                        <td><?php echo (int)$cat->stats_non_wgtd_value;?></td>
                        <?php endif;?>

                        <?php if($show_stats && $sum_cases_wgtd>0 ):?>
                            <td><?php echo is_numeric($cat->stats_wgtd_value) ? round($cat->stats_wgtd_value) : '';?></td>
                        <?php endif;?>

                        <?php if($show_percent):?>
                            <td><?php echo $percent_display;?></td>
                        <?php endif;?>
                    </tr>
                <?php endforeach;?>
            </table>
            <?php if ($categories_hidden > 0): ?>
            <div style="margin-top:8px;color:#666;">
                <?php echo sprintf(
                    t('variable_categories_capped'),
                    number_format($category_display_limit),
                    number_format($total_categories),
                    number_format($categories_hidden)
                );?>
            </div>
            <?php endif; ?>
            <div class="xsl-warning"><?php echo t('warning_figures_indicate_number_of_cases_found');?></div>
    </div>
</div>
<?php endif;?>
