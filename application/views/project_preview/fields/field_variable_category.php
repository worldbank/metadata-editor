<?php if (isset($data) && is_array($data) && count($data)>0 ):?>
<div class="field field-<?php echo $name;?>">
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

            $total_categories = (isset($total_categories) && $total_categories !== null) ? (int)$total_categories : count($data);
            $categories_hidden = (isset($categories_hidden) && $categories_hidden !== null) ? (int)$categories_hidden : 0;
            if ($categories_hidden === 0 && $total_categories > $category_display_limit) {
                $categories_hidden = $total_categories - $category_display_limit;
                $data = array_slice($data, 0, $category_display_limit);
            }

            if (isset($sum_cases_override) && $sum_cases_override !== null) {
                $sum_cases = $sum_cases_override;
            }
            if (isset($sum_cases_wgtd_override) && $sum_cases_wgtd_override !== null) {
                $sum_cases_wgtd = $sum_cases_wgtd_override;
            }

            $show_percent = $show_stats && ($sum_cases>0 || $sum_cases_wgtd>0);
            ?>

            <table class="xsl-table" style="border-collapse: collapse; width: 100%; border: 1px solid #ccc;">
                <tr>
                    <th style="border: 1px solid #ccc; padding: 8px; background-color: #f5f5f5;"><?php echo $html_report->get_template_translation('value', 'Value');?></th>
                    <th style="border: 1px solid #ccc; padding: 8px; background-color: #f5f5f5;"><?php echo $html_report->get_template_translation('category', 'Category');?></th>
                    <?php if($show_stats && $sum_cases>0):?>
                        <th style="border: 1px solid #ccc; padding: 8px; background-color: #f5f5f5;"><?php echo $html_report->get_template_translation('cases', 'Cases');?></th>
                    <?php endif;?>
                    <?php if($show_stats && $sum_cases_wgtd>0):?>
                        <th style="border: 1px solid #ccc; padding: 8px; background-color: #f5f5f5;"><?php echo $html_report->get_template_translation('weighted', 'Weighted');?></th>
                    <?php endif;?>
                    <?php if($show_percent):?>
                        <th style="border: 1px solid #ccc; padding: 8px; background-color: #f5f5f5;"><?php echo $html_report->get_template_translation('percentage', 'Percentage');?></th>
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
                        <td style="border: 1px solid #ccc; padding: 8px;"><?php echo isset($cat->value) ? html_escape($cat->value) : '';?></td>
                        <td style="border: 1px solid #ccc; padding: 8px;"><?php echo isset($cat->labl) ? html_escape($cat->labl) : '';?> <?php echo isset($cat->label) ? html_escape($cat->label) : '';?></td>

                        <?php if($show_stats && $sum_cases>0):?>
                        <td style="border: 1px solid #ccc; padding: 8px;"><?php echo (int)$cat->stats_non_wgtd_value;?></td>
                        <?php endif;?>

                        <?php if($show_stats && $sum_cases_wgtd>0 ):?>
                            <td style="border: 1px solid #ccc; padding: 8px;"><?php echo is_numeric($cat->stats_wgtd_value) ? round($cat->stats_wgtd_value) : '';?></td>
                        <?php endif;?>

                        <?php if($show_percent):?>
                            <td style="border: 1px solid #ccc; padding: 8px;"><?php echo $percent_display;?></td>
                        <?php endif;?>
                    </tr>
                <?php endforeach;?>
            </table>
            <?php if ($categories_hidden > 0): ?>
            <div style="margin-top:8px;color:#666;">
                <?php echo sprintf(
                    $html_report->get_template_translation('variable_categories_capped', 'Showing %s of %s categories (%s more)'),
                    number_format($category_display_limit),
                    number_format($total_categories),
                    number_format($categories_hidden)
                );?>
            </div>
            <?php endif; ?>
            <div class="xsl-warning"><?php echo $html_report->get_template_translation('warning_figures_indicate_number_of_cases_found', 'warning_figures_indicate_number_of_cases_found');?></div>
    </div>
</div>
<?php endif;?>
