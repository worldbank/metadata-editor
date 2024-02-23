<html>
<head>
    <link rel="stylesheet" href="<?php echo base_url();?>/themes/nada52/css/bootstrap.min.css">    
</head>
<style>

    html,.row{
        height:100%;
    }
    .border {
        border: 1px solid gainsboro;
    }

    .p-2 {
        padding: 2px;
    }

    .m-2 {
        margin: 2px;
    }

    .node .node {
        margin-left: 20px;
    }

    .node-type {
        color: gray;
        font-size: small;
    }

    .item-details .label{        
        font-weight:bold;
        display:table-cell;
        width:100px;
    }

    .field-info{
        padding-bottom:0px;
        /*display:table;*/
        width:100%;
    }

    .field-value{
        /*display:table-cell;*/
    }

    .field-help_text{
        margin-top:15px;
    }

    .field-help_text .field-value{
        display:block;        
    }

    .field-props{
        margin-left: 30px;
        border-left: 6px solid #e9ecef;
        padding-left: 10px;
    }

    

ul.tree, ul.tree ul {
    list-style: none;
     margin: 0;
     padding: 0;
   } 
   ul.tree ul {
     margin-left: 10px;
   }
   ul.tree li {
     margin: 0;
     padding: 0 7px;
     line-height: 20px;
     color: #369;     
     border-left:1px solid rgb(100,100,100);

   }
   ul.tree li:last-child {
       border-left:none;
   }
   ul.tree li:before {
      position:relative;
      top:0em;
      height:1em;
      width:12px;
      color:white;
      border-bottom:1px solid rgb(100,100,100);
      content:"";
      display:inline-block;
      left:-7px;
   }
   ul.tree li:last-child:before {
      border-left:1px solid rgb(100,100,100);   
   }

   .preview .badge{
        font-weight:normal!important;
        padding:3px;
        padding-left:5px !important;
        padding-right:5px !important;
        border:0px;
   }
   .preview .badge-secondary{
    background-color:darkgrey!important;
   }
   .preview h1{
    margin-bottom:0px!important;
   }

   /*.table-nonfluid {
        width: auto !important;
    }*/

    .data-type-icon{
        padding:16px;
    }

    .icon-database{
        background:url("<?php echo base_url().'images/database.png';?>") 0 0/36px no-repeat;
        display:inline-block;
    }

    .badge, .badge-primary{
        padding:3px;
        padding-left:5px !important;
        padding-right:5px !important;
        border-right:10px!important;
        margin-right:15px;
    }

    .badge{
        border:0px!important;
        background:none;
        background-color:none;
    }

    .badge-primary{
        margin-right:5px!important;
        border:0px!important;
        font-weight:bold;
        color:gray;
        background:none;
        background-color:none;
    }

    .badge-secondary{
        margin-right:5px!important;
        border:0px!important;
        font-weight:normal;
        color:gray;
    }

    .badge-danger,
    .badge-warning,
    .badge-success
    {
        background:none !important;
    }

    
    strong{
        font-weight:bold;
    }

    .label{
        font-weight:bold;
    }


    .data-type-icon{
        padding:16px;
    }

    .icon-microdata{
        background:url("<?php echo base_url().'images/survey.png';?>") 0 0/36px no-repeat;
        display:inline-block;
    }

    .icon-table{
        background:url("<?php echo base_url().'images/table.png';?>") 0 0/36px no-repeat;
        display:inline-block;
    }

    .icon-image{
        background:url("<?php echo base_url().'images/image.png';?>") 0 0/36px no-repeat;
        display:inline-block;
    }

    .icon-video{
        background:url("<?php echo base_url().'images/video.png';?>") 0 0/36px no-repeat;
        display:inline-block;
    }

    .icon-script{
        background:url("<?php echo base_url().'images/script.png';?>") 0 0/36px no-repeat;
        display:inline-block;
    }

    .icon-timeseries{
        background:url("<?php echo base_url().'images/chart.png';?>") 0 0/36px no-repeat;
        display:inline-block;
    }

    .coverpage-icon{
        background-size:200px 200px;        
    }

    .text-recommended{
        font-size:small;
        color:gray;
        margin-left:10px;
    }

    .text-required{
        font-size:small;
        color:black;
        font-weight:bold;
        display:inline;
        padding-left:20px;
        margin-left:15px;
    }

    .text-separator{
        font-size:small;
        color:gray;
        margin-left:10px;
        display:inline;
    }

    sup{
        font-size:small !important;    
    }

</style>
</head>

<body>
<?php echo $this->load->view('template_report/coverpage', array('template',$template), true); ?>
<pagebreak></pagebreak>
<div class="mb-5" id="template">
    <?php if ($template['data_type']=='survey') {$template['data_type']='microdata';}?>
    <h1>Template information</h1>    
    
    <div class="bg-light p-3">
        <?php 
            $fields = array(
                "name", "Name",
                "uid", "ID", 
                "lang"=>"Language",
                "data_type"=>"Data type",
                "version", "Version", 
                "organization"=>"Organization", 
                "author"=>"Authors", 
                "description"=>"Description",
                "instructions"=>"Instructions",
            );?>
        
        <div class="mb-3">
            <?php foreach($fields as $field=>$field_label):?>
                <?php if (isset($template[$field])) : ?>
                    <div class="field-info mb-3">
                        <div class="label font-weight-bold"><?php echo t($field_label);?></div>
                        <div class="field-value"><?php echo nl2br($template[$field]);?></div>
                    </div>                                    
                <?php endif; ?>
            <?php endforeach;?>
        </div>    

    </div>

</div>
<pagebreak></pagebreak>


<!--details-->
<?php foreach ($template['template']['items'] as $item) : ?>
    <div class="item-details" id="<?php echo str_replace(".","-",$item['key']);?>">
        <div class="mt-3 mb-3">
            <?php if ($item['type'] == 'section_container') : ?>
                <h1><?php echo $item['title']; ?></h1>
            <?php else: ?>
                <h3><?php echo $item['title']; ?></h3>
            <?php endif; ?>
                        
            <span class="badge badge-primary" title="Type"><?php echo $item['type']; ?></span>
            <span class="badge badge-light" title="Key"><?php echo $item['key']; ?></span>
        </div>

        <?php if (isset($item['help_text']) && trim($item['help_text'])!=='') : ?>
            <div class="field-info field-help_text bg-light p-3 mb-3">                
                <div class="field-value"><?php echo nl2br($item['help_text']);?></div>
            </div>
        <?php endif; ?>

        <?php if (isset($item['items'])) : ?>
            <?php echo $this->load->view('templates/tree_item_info', array('parent_item' => $item), true); ?>
        <?php endif; ?>
    </div>
    <pagebreak></pagebreak>
<?php endforeach; ?>


<!-- tree outline -->
<div class="p-3">
    <pagebreak></pagebreak>
    <ul class="tree">
        <li class="node">
            <h1>Template fields outline</h1>
        </li>                    
        <?php foreach ($template['template']['items'] as $item) : ?>
            <li class="node">
                <?php echo $item['key']; ?> <span class="node-type"><?php echo $item['type']; ?></span>

                <?php if (isset($item['items'])) : ?>
                    <?php echo $this->load->view('templates/tree', array('parent_item' => $item), true); ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
</html>