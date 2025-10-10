<?php if (validation_errors()): ?>
    <div class="alert alert-danger">
        <?php echo validation_errors(); ?>
    </div>
<?php endif; ?>

<?php $error = $this->session->flashdata('error');?>
<?php echo ($error != "") ? '<div class="alert alert-danger">' . $error . '</div>' : ''; ?>

<?php $message = $this->session->flashdata('message');?>
<?php echo ($message != "") ? '<div class="alert alert-success">' . $message . '</div>' : ''; ?>

<!-- Remove Roles Form -->
<div class="mb-4">
    <h6 class="text-muted mb-3"><i class="fa fa-user-minus text-danger"></i> Remove Roles from <?php echo count($users); ?> User<?php echo count($users) > 1 ? 's' : ''; ?></h6>
    
    <?php echo form_open('admin/users/process_bulk_remove_roles', array('id' => 'bulkRoleRemovalForm')); ?>
    
    <input type="hidden" name="user_ids" value="<?php echo $user_ids; ?>">
    
    <div class="form-group mb-3">
        <label class="font-weight-bold">Select Roles to Remove:</label>
        <div class="role-selection">
            <?php 
            $all_roles = [];
            foreach ($user_roles as $user_id => $roles) {
                foreach ($roles as $role) {
                    if (!isset($all_roles[$role['role_id']])) {
                        $all_roles[$role['role_id']] = $role;
                    }
                }
            }
            
            if (empty($all_roles)): ?>
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> None of the selected users have any roles assigned.
                </div>
            <?php else: ?>
                <?php foreach ($all_roles as $role): ?>
                <div class="form-check">
                    <input class="form-check-input mt-1" type="checkbox" name="role_ids[]" value="<?php echo $role['role_id']; ?>" id="remove_role_<?php echo $role['role_id']; ?>">
                    <label class="form-check-label" for="remove_role_<?php echo $role['role_id']; ?>">
                        <strong><?php echo form_prep($role['name']); ?></strong>
                        <?php if (!empty($role['description'])): ?>
                        <small class="text-muted d-block mt-1"><?php echo form_prep($role['description']); ?></small>
                        <?php endif; ?>
                        <small class="text-info d-block mt-1">
                            <i class="fa fa-users"></i> 
                            <?php 
                            $users_with_role = 0;
                            foreach ($user_roles as $user_id => $roles) {
                                foreach ($roles as $user_role) {
                                    if ($user_role['role_id'] == $role['role_id']) {
                                        $users_with_role++;
                                        break;
                                    }
                                }
                            }
                            echo $users_with_role . ' of ' . count($users) . ' selected users have this role';
                            ?>
                        </small>
                    </label>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
    color: #dc3545;
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

.alert-info {
    border-color: #bee5eb;
    background-color: #d1ecf1;
    color: #0c5460;
}
</style>
