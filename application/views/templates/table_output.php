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
    font-weight:normal!important
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
    
    sup{
        font-size:small;
    }

</style>


<div class="table_output container-fluid">
    <table class="table table-striped table-bordered table-hover">
        <thead>
            <tr>
                <th>Parent</th>    
                <th>Field</th>                
                <th>Type</th>
                <th>Title</th>
                <th>Description</th>
                
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $key => $value): ?>
                <tr>
                    <td><?php echo str_replace("__","/",$value['parent']); ?></td>
                    <td><?php echo $key; ?></td>                    
                    <td><?php echo $value['type']; ?></td>
                    <td><?php echo $value['title']; ?></td>
                    <td><?php echo nl2br($value['description']); ?></td>
                    
                </tr>
            <?php endforeach; ?>
        </tbody>
</div>
</html>