<?php if (!isset($values) || !is_array($values) || count($values)===0) {
    return;
}
?>

<?php if (isset($item['props']) ):?>
    <?php $columns=array();?>
    <?php foreach ($item['props'] as $prop):?>
        <?php $columns[]=$prop['key'];?>
    <?php endforeach;?>

    <table class="table table-sm border table-striped table-nonfluid">
        <thead>
            <tr>
                <?php foreach ($columns as $column):?>
                    <th><?php echo $column;?></th>
                <?php endforeach;?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($values as $enum): ?>
                <tr>
                    <?php foreach ($columns as $column):?>
                        <td><?php echo isset($enum[$column]) ? $enum[$column] : ''; ?></td>
                    <?php endforeach;?>
                </tr>
            <?php endforeach; ?>
        <tbody>
    </table>
    
<?php else:?>
<table class="table table-sm border table-striped table-nonfluid">
    <thead>
        <tr>
            <th>Code</th>
            <th>Label</th>            
        </tr>
    </thead>
    <tbody>
        <?php foreach ($values as $enum): ?>
            <tr>
                <td><?php echo $enum['code']; ?></td>
                <td><?php echo $enum['label']; ?></td>                
            </tr>
        <?php endforeach; ?>
    <tbody>
</table>
<?php endif;?>