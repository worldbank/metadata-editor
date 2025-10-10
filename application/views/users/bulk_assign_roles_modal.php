<?php if (validation_errors()): ?>
    <div class="alert alert-danger">
        <?php echo validation_errors(); ?>
    </div>
<?php endif; ?>

<?php $error = $this->session->flashdata('error');?>
<?php echo ($error != "") ? '<div class="alert alert-danger">' . $error . '</div>' : ''; ?>

<?php $message = $this->session->flashdata('message');?>
<?php echo ($message != "") ? '<div class="alert alert-success">' . $message . '</div>' : ''; ?>

<!-- Assign Roles Form -->
<div class="mb-4">
    <h6 class="text-muted mb-3"><i class="fa fa-users text-primary"></i> Assign Roles to <?php echo count($users); ?> User<?php echo count($users) > 1 ? 's' : ''; ?></h6>
    
    <?php echo form_open('admin/users/process_bulk_assign_roles', array('id' => 'bulkRoleForm')); ?>
    
    <input type="hidden" name="user_ids" value="<?php echo $user_ids; ?>">
    
    <div class="form-group mb-3">
        <label class="font-weight-bold">Select Roles:</label>
        <div class="role-selection">
            <?php foreach ($roles as $role): ?>
            <div class="form-check">
                <input class="form-check-input mt-1" type="checkbox" name="role_ids[]" value="<?php echo $role['id']; ?>" id="role_<?php echo $role['id']; ?>">
                <label class="form-check-label" for="role_<?php echo $role['id']; ?>">
                    <strong><?php echo form_prep($role['name']); ?></strong>
                    <?php if (!empty($role['description'])): ?>
                    <small class="text-muted d-block mt-1"><?php echo form_prep($role['description']); ?></small>
                    <?php endif; ?>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php echo form_close(); ?>
</div>


<style>
.form-check {
    margin-bottom: 8px;
    padding: 6px 8px;
    border: 1px solid #e9ecef;
    border-radius: 3px;
    background-color: #f8f9fa;
    position: relative;
}

.form-check:hover {
    background-color: #e9ecef;
}

.form-check-input {
    position: absolute;
    left: 8px;
    top: 10px;
    margin: 0;
}

.form-check-input:checked + .form-check-label {
    color: #007bff;
}

.form-check-label {
    font-weight: normal;
    cursor: pointer;
    font-size: 0.9rem;
    padding-left: 25px;
    margin: 0;
    display: block;
}

.role-selection {
    max-height: 250px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 3px;
    padding: 8px;
    background-color: #fff;
}

</style>
