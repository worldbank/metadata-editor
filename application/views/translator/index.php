<?php
	$rtl_languages=array('arabic'); //list of RTL languages
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<h3>Translator</title>

<style>
body,form{margin:0px;padding:0px;font-family:Arial, Helvetica, sans-serif;font-size:12px;}
.left-menu{background:black;width:250px;color:white;}
.flex, .flex-master{width:99%;}
s.flex-textarea{height:35px;}
.flex-master{background-color:gainsboro;}
.even{}
.odd{background-color:#F0F7F9;}
.odd td, .even td{border-bottom:1x4px solid white;padding:5px;}
.not-found, .not-found .flex-master{background-color:red;color:white;}
.header{padding:15px;background-color:black;color:white;font-size:20px;height:30px;}
#translation-files, #master-language, #slave-language{background-color:#666666;margin-bottom:10px;padding:10px;}
.translation-key{font-weight:bold;}
.table-header{padding:5px;font-weight:bold;}
.content{margin:10px;}
.success,.error{margin:5px;padding:10px;font-size:14px;border:1px solid gainsboro;}
.success{background:green;color:white;}
.error{background:red;color:white;}
<?php if(in_array($this->slave,$rtl_languages)):?>
.flex-textarea{direction:rtl;}
<?php endif;?>
</style>

</head>

<body>

<form method="post">
<table width="100%" cellpadding="0" cellspacing="0">
<tr valign="top">
<td class="left-menu">
<div id="master-language">
	<label>Template language</label>
    <div><?php echo form_dropdown('master', $this->languages, "english");?></div>	
</div>

<div id="slave-language">
	<label>Select the language to edit</label>
    <div><?php echo form_dropdown('slave', $this->languages, $this->slave);?></div>
</div>

<div id="translation-files">
	<label>Translation files</label>    
	<?php $this->file=$this->input->post("file");?>
	<?php foreach($this->files as $key=>$value):?>
    <div>
    	<?php $sname=str_replace("_lang.php","",$value);?>
        <?php $sname=str_replace("_"," ",$sname);?>
        <input id="lang-<?php echo $key;?>" type="radio" name="file" value="<?php echo $value;?>" <?php echo ($this->file==$value) ? 'checked="checked"' : '';?>  />
        <label for="lang-<?php echo $key;?>"><?php echo $sname;?></label>
    </div>    
    <?php endforeach;?>    
</div>
<input type="submit" value="Select" name="select"/>
</td>
<td>
	<div class="header">
    	<div style="float:left;">
    	<?php if ($this->file!=''):?>
        	You are Editing: <?php echo strtoupper($this->slave);?>/<b><?php echo strtoupper($this->file);?></b>
        <?php else:?>
        	Translator
        <?php endif;?>
        </div>
        <div style="float:right;" > <a style="font-size:14px;color:white; text-decoration:none;" target="_blank" href="<?php echo site_url();?>/translate/export">Download Language Pack</a></div>
    </div>
    <div class="content">
    <?php if ($this->success):?>
    	<div class="success"><?php echo $this->success;?></div>
    <?php endif;?>

    <?php if ($this->error):?>
    	<div class="error"><?php echo $this->error;?></div>
    <?php endif;?>
    
    <?php if ($this->file!=''):?>
	<table width="100%" cellpadding="0" cellspacing="0">
        <tr class="table-header" valign="top" align="left">
            <th width="100px">Key</th>
            <th>Translation</th>
        </tr>	
	<?php $td_css='even';?>
	<?php foreach($this->master_lang as $key=>$value):?>
    	<?php 
			if ($td_css!=='odd') {$td_css='odd';}
			else{$td_css='even';}
			
			$slave_key_found=array_key_exists($key, $this->slave_lang);
			$slave_value='';
			if ($slave_key_found)
			{
				$slave_value=$this->slave_lang[$key];
			}
			else
			{
				$td_css.=' not-found';
			}
		?>
    	<tr class="<?php echo $td_css; ?>" valign="top">
        <td class="translation-key"><?php echo $key; ?></td>
        <td>
			<div class="master-translation">
            	<!--<textarea readonly="readonly" class="flex-master" rows="5"><?php echo (htmlspecialchars($value)); ?></textarea>-->
                <?php echo nl2br(htmlspecialchars($value)); ?>
            </div>
            
            <?php 
				$lines = count(explode("\n", $slave_value));
				if ($lines<2)
				{
					$lines=2;
				}					
			?>
            <?php //echo form_textarea(md5($key), set_value(NULL, $slave_value),'class="flex-textarea" rows="'.$lines.'"');?>
			<textarea name="<?php echo md5($key);?>" class="flex-textarea flex" rows="<?php echo $lines;?>"><?php echo set_value(NULL, htmlspecialchars_decode($slave_value)); ?></textarea>
        </td>
        </tr>
        <?php //break;?>
    <?php endforeach;?>
    </table>
	<div><input type="submit" value="Save" name="save"/>  </div>
    <?php else:?>
        <h1>How to use Translation editor</h1>
        <p>Use the left pane to select the language and the translation file to start editing</p>
    <?php endif;?>
    </div>
</td>
</tr>
</table>
</form>

<div style="background-color:gray;color:white;font-weight:bold;padding:10px;">Preview</div>
<div style="text-align:right;">
<textarea style="width:99%;height:200px;border:0px solid gainsboro;">
<?php $this->load->view('translator/preview');?>
</textarea>
</div>
<?php $eval= eval($this->load->view('translator/preview',NULL,TRUE));?>
<pre><?php echo $eval;?></pre>
</body>
</html>
