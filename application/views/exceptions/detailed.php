<?php
/*
	Detailed exception view
*/
?>
<div>
    <div><?php echo $message;?></div>
    <div>File <em><?php echo $file;?></em> at line <em><?php echo $line;?></em> </div>
    <pre class="trace" style="color:gray;"><?php echo $trace_string;?></pre>
</div>