<?php if (!isset($rules) || !is_array($rules) || count($rules)===0) {
    return;
}
?>
<table class="table table-sm table-striped table-nonfluid">
    <thead>
        <tr>
            <th>Rule</th>
            <th></th>            
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rules as $rule=>$rule_value ): ?>
            <tr>
                <td><?php echo $rule;?></td>
                <td><?php echo $rule_value; ?></td>                
            </tr>
        <?php endforeach; ?>
    <tbody>
</table>
