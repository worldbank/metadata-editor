<?php if (isset($data) && is_array($data) && count($data)>0 ):?>
<div class="field field-<?php echo $name;?>">
    <!--<h3 class="xsl-caption field-caption"><?php echo $html_report->get_template_translation('variable.'.$name, $name);?></h3>-->
    <div class="field-value">

            <?php            
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
                    <?php if($show_stats && ($sum_cases>0) ):?>
                        <th style="border: 1px solid #ccc; padding: 8px; background-color: #f5f5f5;"><?php echo $html_report->get_template_translation('percentage', 'Percentage');?></th>
                    <?php endif;?>
                </tr>
                <?php foreach($data as $cat):?>
                    <?php
                    $cat=(object)$cat;

                    if($show_stats && $sum_cases>0){
                        $percent=@round($cat->stats_non_wgtd_value/$sum_cases * 100,1);
                        $width=@round($cat->stats_non_wgtd_value/$max_value * 100,1);                        
                    }

                    if ($show_stats && $sum_cases_wgtd>0){
                        $percent_wgtd=@round($cat->stats_wgtd_value/$sum_cases_wgtd * 100,1);
                        $width_wgtd=@round($cat->stats_wgtd_value/$max_value_wgtd * 100,1);
                    }
                    ?>

                    <tr>
                        <td style="border: 1px solid #ccc; padding: 8px;"><?php echo isset($cat->value) ? html_escape($cat->value) : '';?></td>
                        <td style="border: 1px solid #ccc; padding: 8px;"><?php echo isset($cat->labl) ? html_escape($cat->labl) : '';?> <?php echo isset($cat->label) ? html_escape($cat->label) : '';?></td>
                        
                        <?php if($show_stats && $sum_cases>0):?>
                        <td style="border: 1px solid #ccc; padding: 8px;"><?php echo (int)$cat->stats_non_wgtd_value;?></td>
                        <?php endif;?>    
                        
                        <!--weighted-->
                        <?php if($show_stats && $sum_cases_wgtd>0 ):?>
                            <td style="border: 1px solid #ccc; padding: 8px;"><?php echo round($cat->stats_wgtd_value);?></td>
                            <?php if (empty($cat->is_missing)):?>
                            <td class="bar-container" style="width:400px; border: 1px solid #ccc; padding: 8px;">
                                <?php if(is_numeric($cat->stats_wgtd_value)):?>
                                    <table style="border-collapse: collapse; width: 100%;">
                                        <tr>
                                            <td style="width: <?php echo $width_wgtd;?>px; background-color: #2196F3; height: 20px;"></td>
                                            <td style="padding-left: 5px;"><?php echo $percent_wgtd;?>%</td>
                                        </tr>
                                    </table>
                                <?php endif;?>
                            </td>
                            <?php else:?>
                            <td style="border: 1px solid #ccc; padding: 8px;"></td>
                            <?php endif?>

                        <?php endif;?>

                        <!--non-weighted-->
                        <?php if($show_stats && $sum_cases>0 && (int)$sum_cases_wgtd<1):?>
                            <?php if(empty($cat->is_missing)):?>
                            <td class="bar-container" style="border: 1px solid #ccc; padding: 8px;">
                                <?php if(is_numeric($cat->stats_non_wgtd_value)):?>
                                    <table style="border-collapse: collapse; width: 100%;">
                                        <tr>
                                            <td style="width: <?php echo $width;?>px; background-color: #2196F3; height: 20px;"></td>
                                            <td style="padding-left: 5px;"><?php echo $percent;?>%</td>
                                        </tr>
                                    </table>
                                <?php endif;?>
                            </td>
                            <?php else:?>
                            <td style="border: 1px solid #ccc; padding: 8px;"></td>
                            <?php endif;?>
                        <?php endif;?>    
                    </tr>
                <?php endforeach;?>
            </table>
            <div class="xsl-warning"><?php echo $html_report->get_template_translation('warning_figures_indicate_number_of_cases_found', 'warning_figures_indicate_number_of_cases_found');?></div>
    </div>
</div>
<?php endif;?>
