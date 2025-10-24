<div class="container-fluid content-fluid page-users-index">

  <?php $message = $this->session->flashdata('message');?>
  <?php echo ($message != "") ? '<div class="alert alert-success">' . $message . '</div>' : ''; ?>
  <?php $error = $this->session->flashdata('error');?>
  <?php echo ($error != "") ? '<div class="alert alert-danger">' . $error . '</div>' : ''; ?>

  <?php if (!isset($hide_form)): ?>
    <div class="page-links text-right m-3 pb-3">
      <a href="<?php echo site_url('admin/users/add'); ?>" class="btn btn-outline-primary btn-sm"><i class="fa fa-plus-circle" aria-hidden="true">&nbsp;</i> <?php echo t('create_user_account'); ?></a>
      <a href="<?php echo site_url('admin/users/pending_activation'); ?>" class="btn btn-outline-warning btn-sm"><i class="fa fa-clock-o" aria-hidden="true">&nbsp;</i> <?php echo t('pending_activation'); ?></a>
      <a href="<?php echo site_url('admin/permissions'); ?>" class="btn btn-outline-primary btn-sm"><i class="fa fa-users" aria-hidden="true">&nbsp;</i> <?php echo t('User roles'); ?></a>
    </div>
    
    <h1 class="page-title mt-3 mb-3"><?php echo t('title_user_management'); ?></h1>

    <div class="row">
      <div class="col-lg-3 col-md-4">
        <div class="filters-sidebar">
          <div class="card mb-3">
            <div class="card-header">
              <h6 class="mb-0"><i class="fa fa-search text-primary"></i> <?php echo t('search'); ?></h6>
            </div>
            <div class="card-body">
              <form method="GET" id="user-search">
                <div class="form-group mb-2">
                  <label for="keywords" class="form-label"><?php echo t('keywords'); ?>:</label>
                  <input type="text" class="form-control form-control-sm" name="keywords" id="keywords" value="<?php echo form_prep($this->input->get('keywords')); ?>" placeholder="<?php echo t('search_users'); ?>"/>
                </div>

                <div class="form-group mb-2">
                  <label for="field" class="form-label"><?php echo t('search_in'); ?>:</label>
                  <select name="field" id="field" class="form-control form-control-sm">
                    <option value="all"		<?php echo ($this->input->get('field') == 'all') ? 'selected="selected"' : ''; ?> ><?php echo t('all_fields'); ?></option>
                    <option value="username"	<?php echo ($this->input->get('field') == 'username') ? 'selected="selected"' : ''; ?> ><?php echo t('username'); ?></option>
                    <option value="email"	<?php echo ($this->input->get('field') == 'email') ? 'selected="selected"' : ''; ?> ><?php echo t('email'); ?></option>
                  </select>
                </div>

                <div class="form-group">
                  <button type="submit" class="btn btn-primary btn-sm btn-block">
                    <i class="fa fa-search"></i> <?php echo t('search'); ?>
                  </button>
                  <?php if ($this->input->get("keywords") != ''): ?>
                  <a class="btn btn-outline-secondary btn-sm btn-block mt-1" href="<?php echo current_url(); ?>">
                    <i class="fa fa-times"></i> <?php echo t('reset'); ?>
                  </a>
                  <?php endif;?>
                </div>
              </form>
            </div>
          </div>


          <div class="card">
            <div class="card-header" data-toggle="collapse" data-target="#filtersCollapse" aria-expanded="false" style="cursor: pointer;">
              <h6 class="mb-0">
                <i class="fa fa-filter text-primary"></i> <?php echo t('advanced_filters'); ?>
                <?php 
                $active_filters_count = 0;
                if ($this->input->get('status_filter') !== '' && $this->input->get('status_filter') !== null) $active_filters_count++;
                if ($this->input->get('role_filter') && $this->input->get('role_filter') !== '') $active_filters_count++;
                if ($this->input->get('last_login_filter') && $this->input->get('last_login_filter') !== '') $active_filters_count++;
                ?>
                <?php if ($active_filters_count > 0): ?>
                  <span class="badge badge-primary ml-2"><?php echo $active_filters_count; ?> Active</span>
                <?php endif; ?>
                <i class="fa fa-chevron-down float-right" style="margin-top: 2px;"></i>
              </h6>
            </div>
            <div class="collapse" id="filtersCollapse">
              <div class="card-body">
                <form method="GET" id="user-filters">
                  <input type="hidden" name="keywords" value="<?php echo form_prep($this->input->get('keywords')); ?>">
                  <input type="hidden" name="field" value="<?php echo form_prep($this->input->get('field')); ?>">
                  
                  <div class="form-group mb-3">
                    <label for="status_filter" class="form-label"><?php echo t('status'); ?>:</label>
                    <select class="form-control form-control-sm" name="status_filter" id="status_filter">
                      <option value=""><?php echo t('all_users'); ?></option>
                      <option value="1" <?php echo $this->input->get('status_filter') == '1' ? 'selected' : ''; ?>><?php echo t('active'); ?></option>
                      <option value="0" <?php echo $this->input->get('status_filter') == '0' ? 'selected' : ''; ?>><?php echo t('inactive'); ?></option>
                    </select>
                  </div>
                  
                  <div class="form-group mb-3">
                    <label for="role_filter" class="form-label"><?php echo t('role'); ?>:</label>
                    <select class="form-control form-control-sm" name="role_filter" id="role_filter">
                      <option value=""><?php echo t('all_roles'); ?></option>
                      <?php if (isset($roles) && is_array($roles)): ?>
                        <?php foreach ($roles as $role): ?>
                          <option value="<?php echo $role['id']; ?>" <?php echo $this->input->get('role_filter') == $role['id'] ? 'selected' : ''; ?>>
                            <?php echo form_prep($role['name']); ?>
                          </option>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </select>
                  </div>
                  
                  
                  <div class="form-group mb-3">
                    <label for="last_login_filter" class="form-label"><?php echo t('last_login'); ?>:</label>
                    <select class="form-control form-control-sm" name="last_login_filter" id="last_login_filter">
                      <option value=""><?php echo t('all_users'); ?></option>
                      <option value="today" <?php echo $this->input->get('last_login_filter') == 'today' ? 'selected' : ''; ?>><?php echo t('today'); ?></option>
                      <option value="week" <?php echo $this->input->get('last_login_filter') == 'week' ? 'selected' : ''; ?>><?php echo t('this_week'); ?></option>
                      <option value="month" <?php echo $this->input->get('last_login_filter') == 'month' ? 'selected' : ''; ?>><?php echo t('this_month'); ?></option>
                      <option value="never" <?php echo $this->input->get('last_login_filter') == 'never' ? 'selected' : ''; ?>><?php echo t('never'); ?></option>
                    </select>
                  </div>
                  
                  <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-sm btn-block">
                      <i class="fa fa-search"></i> <?php echo t('apply_filters'); ?>
                    </button>
                    <a href="<?php echo site_url('admin/users') . '?keywords=' . urlencode($this->input->get('keywords')) . '&field=' . urlencode($this->input->get('field')); ?>" class="btn btn-outline-secondary btn-sm btn-block mt-1">
                      <i class="fa fa-times"></i> <?php echo t('clear_all_filters'); ?>
                    </a>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-9 col-md-8">
        <?php 
        $active_filters = [];
        $has_any_filters = false;
        
        if ($this->input->get('keywords') && trim($this->input->get('keywords')) !== '') {
            $active_filters[] = t('search') . ': "' . $this->input->get('keywords') . '"';
            $has_any_filters = true;
        }
        
        if ($this->input->get('status_filter') !== '' && $this->input->get('status_filter') !== null) {
            $status_text = $this->input->get('status_filter') == '1' ? t('active') : t('inactive');
            $active_filters[] = t('status') . ': ' . $status_text;
            $has_any_filters = true;
        }
        
        if ($this->input->get('role_filter') && $this->input->get('role_filter') !== '') {
            $role_name = 'Unknown';
            if (isset($roles) && is_array($roles)) {
                foreach ($roles as $role) {
                    if ($role['id'] == $this->input->get('role_filter')) {
                        $role_name = $role['name'];
                        break;
                    }
                }
            }
            $active_filters[] = t('role') . ': ' . $role_name;
            $has_any_filters = true;
        }
        
        
        if ($this->input->get('last_login_filter') && $this->input->get('last_login_filter') !== '') {
            $login_text = ucfirst($this->input->get('last_login_filter'));
            $active_filters[] = t('last_login') . ': ' . $login_text;
            $has_any_filters = true;
        }
        ?>
        <?php if ($has_any_filters && !empty($active_filters)): ?>
        <div class="active-filters-main mb-3">
          <div class="alert alert-info">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <i class="fa fa-info-circle"></i> <strong><?php echo t('active_filters'); ?>:</strong>
                <?php foreach ($active_filters as $index => $filter): ?>
                  <span class="badge badge-secondary mr-1"><?php echo $filter; ?></span>
                <?php endforeach; ?>
              </div>
              <a href="<?php echo site_url('admin/users'); ?>" class="btn btn-xs btn-outline-secondary">
                <i class="fa fa-times"></i> <?php echo t('clear_all'); ?>
              </a>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($rows): ?>
  <?php
    $page_nums = $this->pagination->create_links();
    $current_page = ($this->pagination->cur_page == 0) ? 1 : $this->pagination->cur_page;
    $sort_by = $this->input->get("sort_by");
    $sort_order = $this->input->get("sort_order");
    $page_url = site_url() . '/' . $this->uri->uri_string();
  ?>

  <?php
    if ($this->pagination->cur_page > 0) {
        $to_page = $this->pagination->per_page * $this->pagination->cur_page;

        if ($to_page > $this->pagination->get_total_rows()) {
            $to_page = $this->pagination->get_total_rows();
        }

        $pager = sprintf(t('showing %d-%d of %d'), (($this->pagination->cur_page - 1) * $this->pagination->per_page + (1)), $to_page, $this->pagination->get_total_rows());
    } else {
        $pager = sprintf(t('showing %d-%d of %d'), $current_page, $this->pagination->get_total_rows(), $this->pagination->get_total_rows());
    }
  ?>

  <div id="bulk-actions-toolbar" class="bulk-actions-toolbar disabled">
    <div class="bulk-actions-content">
      <div class="bulk-actions-dropdown">
        <div class="dropdown">
          <button class="btn btn-primary btn-xs dropdown-toggle" type="button" id="bulkActionsDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="fa fa-cog"></i> Actions
          </button>
          <div class="dropdown-menu" aria-labelledby="bulkActionsDropdown">
                      <a class="dropdown-item" href="#" onclick="bulkAction('activate'); return false;">
                        <i class="fa fa-check"></i> <?php echo t('activate_users'); ?>
                      </a>
                      <a class="dropdown-item" href="#" onclick="bulkAction('deactivate'); return false;">
                        <i class="fa fa-ban"></i> <?php echo t('deactivate_users'); ?>
                      </a>
                      <div class="dropdown-divider"></div>
                      <a class="dropdown-item" href="#" onclick="bulkAction('assign-roles'); return false;">
                        <i class="fa fa-users"></i> <?php echo t('assign_roles'); ?>
                      </a>
                      <a class="dropdown-item" href="#" onclick="bulkAction('remove-roles'); return false;">
                        <i class="fa fa-user-minus"></i> <?php echo t('remove_roles'); ?>
                      </a>
                      <div class="dropdown-divider"></div>
                      <a class="dropdown-item" href="#" onclick="bulkAction('delete'); return false;">
                        <i class="fa fa-trash"></i> <?php echo t('delete_users'); ?>
                      </a>
                      <div class="dropdown-divider"></div>
                      <a class="dropdown-item" href="#" onclick="clearSelection(); return false;">
                        <i class="fa fa-times"></i> <?php echo t('clear_selection'); ?>
                      </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="bulkRoleModal" tabindex="-1" role="dialog" aria-labelledby="bulkRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="bulkRoleModalLabel">
            <i class="fa fa-users text-primary"></i> <?php echo t('bulk_assign_roles'); ?>
          </h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div id="roleAssignmentContent">
            <div class="text-center">
              <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
              </div>
              <p class="mt-2"><?php echo t('loading'); ?>...</p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
            <i class="fa fa-times"></i> <?php echo t('cancel'); ?>
          </button>
          <button type="button" class="btn btn-primary btn-sm" id="assignRolesBtn" onclick="processBulkRoleAssignment()">
            <i class="fa fa-users"></i> <?php echo t('assign_roles'); ?>
          </button>
          <button type="button" class="btn btn-danger btn-sm" id="removeRolesBtn" onclick="processBulkRoleRemoval()" style="display: none;">
            <i class="fa fa-user-minus"></i> <?php echo t('remove_roles'); ?>
          </button>
        </div>
      </div>
    </div>
  </div>

  <table class="table table-striped table-sm" width="100%" cellspacing="0" cellpadding="0">
    <tr class="header">
      <th width="40">
        <input type="checkbox" id="select-all-users" onchange="toggleAllUsers(this)">
      </th>
      <th><?php echo create_sort_link($sort_by, $sort_order, 'username', t('username'), $page_url); ?></th>
      <th><?php echo create_sort_link($sort_by, $sort_order, 'email', t('email'), $page_url); ?></th>
      <th><?php echo create_sort_link($sort_by, $sort_order, 'group_name', t('group'), $page_url); ?></th>
      <th><?php echo create_sort_link($sort_by, $sort_order, 'active', t('status'), $page_url); ?></th>
      <th><?php echo create_sort_link($sort_by, $sort_order, 'country', t('country'), $page_url); ?></th>
      <th><?php echo create_sort_link($sort_by, $sort_order, 'created_on', t('join_date'), $page_url); ?></th>
      <th><?php echo create_sort_link($sort_by, $sort_order, 'last_login', t('last_login'), $page_url); ?></th>
      <th><?php echo t('actions'); ?></th>
    </tr>
    <?php $tr_class = "";?>
    <?php foreach ($rows as $row): ?>
        <?php $row = (object) $row;?>
        <?php if ($tr_class == "") {
          $tr_class = "alternate";
          } else {
              $tr_class = "";
          }?>
    <tr class="<?php echo $tr_class; ?>" valign="top">
      <td>
        <input type="checkbox" class="user-checkbox" value="<?php echo $row->id; ?>" onchange="updateBulkToolbar()">
      </td>
      <td>
        <div><a href="<?php echo site_url('admin/users/edit/' . $row->id); ?>"><?php echo form_prep($row->username); ?></a></div>
      </td>
      <td><?php echo form_prep($row->email); ?>&nbsp;</td>
      <td>
        <div>
            <?php if (array_key_exists($row->id, $user_groups)): ?>
              <?php foreach ($user_groups[$row->id] as $group): ?>
                <div><?php echo $group['name']; ?></div>
              <?php endforeach;?>
            <?php endif;?>
        </div>
      </td>
      <td><?php echo ((int) $row->active) == 1 ? t('ACTIVE') : t('DISABLED'); ?></td>
      <td><?php echo form_prep($row->country); ?></td>
      <td><?php echo date("m-d-Y", $row->created_on); ?></td>
      
      <?php if ($row->last_login > $row->created_on): ?>
      <td><?php echo date("m-d-Y", $row->last_login); ?></td>
      <?php else: ?>
        <td>-</td>
      <?php endif;?>
          
      <td>
          <a href="<?php echo current_url(); ?>/edit/<?php echo $row->id; ?>"><?php echo t('edit'); ?></a> |
          <a href="<?php echo current_url(); ?>/delete/<?php echo $row->id; ?>"><?php echo t('delete'); ?></a>
      </td>
    </tr>
  <?php endforeach;?>
  </table>

  <div class="nada-pagination text-right">
    <em><?php echo $pager; ?></em>&nbsp;&nbsp;&nbsp; <?php echo $page_nums; ?>
  </div>


        <?php else: ?>
          <div class="alert alert-info">
            <i class="fa fa-info-circle"></i> <?php echo t('no_records_found'); ?>
          </div>
        <?php endif;?>
      </div>
    </div>
  <?php endif;?>
</div>

<style>
.bulk-actions-toolbar {
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  padding: 6px 10px;
  margin-bottom: 15px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.bulk-actions-toolbar.disabled {
  background: #e9ecef;
  border-color: #ced4da;
  opacity: 0.6;
}

.bulk-actions-toolbar.disabled .btn {
  pointer-events: none;
  opacity: 0.5;
}

#bulkActionsDropdown {
  padding: 4px 8px;
  font-size: 12px;
  line-height: 1.2;
}

.bulk-actions-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.bulk-selected-count {
  font-weight: bold;
  color: #495057;
}

.bulk-actions-dropdown {
  display: flex;
  align-items: center;
}

.bulk-actions-dropdown .btn {
  margin: 0;
}

.dropdown-item {
  padding: 4px 12px;
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #495057;
}

.dropdown-item i {
  width: 14px;
  text-align: center;
  font-size: 11px;
}

.dropdown-item:hover {
  background-color: #f8f9fa;
  color: #495057;
}

.dropdown-item.text-danger {
  color: #495057;
}

.dropdown-item.text-danger:hover {
  background-color: #f8f9fa;
  color: #495057;
}


.user-checkbox {
  margin: 0;
}

.table th:first-child,
.table td:first-child {
  text-align: center;
  vertical-align: middle;
}

.table tr:hover {
  background-color: #f5f5f5;
}

.table tr.selected {
  background-color: #e3f2fd;
}

/* Modal Styles */
.modal-lg {
  max-width: 800px;
}

.modal-header {
  background-color: #f8f9fa;
  border-bottom: 1px solid #dee2e6;
}

.modal-title {
  font-weight: 600;
  color: #495057;
}

.modal-body {
  padding: 20px;
}

.modal-footer {
  background-color: #f8f9fa;
  border-top: 1px solid #dee2e6;
}

#roleAssignmentContent .form-check {
  margin-bottom: 15px;
  padding: 10px;
  border: 1px solid #e9ecef;
  border-radius: 4px;
  background-color: #f8f9fa;
  transition: background-color 0.2s;
}

#roleAssignmentContent .form-check:hover {
  background-color: #e9ecef;
}

#roleAssignmentContent .form-check-input:checked + .form-check-label {
  color: #007bff;
}

#roleAssignmentContent .form-check-label {
  font-weight: normal;
  margin-left: 8px;
  cursor: pointer;
}

#roleAssignmentContent .role-selection {
  max-height: 300px;
  overflow-y: auto;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  padding: 10px;
  background-color: #fff;
}

#roleAssignmentContent .table th {
    font-weight: 600;
    background-color: #f8f9fa;
}

/* Two Column Layout Styles */
.filters-sidebar {
    position: sticky;
    top: 20px;
}

.filters-sidebar .card {
    border: 1px solid #dee2e6;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 1rem;
}

.filters-sidebar .card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 10px 15px;
    transition: background-color 0.2s ease;
}

.filters-sidebar .card-header:hover {
    background-color: #e9ecef;
}

.filters-sidebar .card-header h6 {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 600;
}

.filters-sidebar .card-body {
    padding: 15px;
}

.filters-sidebar .form-label {
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 5px;
    color: #495057;
}

.filters-sidebar .form-control-sm {
    font-size: 0.8rem;
}

.filters-sidebar .btn-sm {
    font-size: 0.8rem;
    padding: 4px 8px;
}

/* Active Filters Styles */
.active-filters .alert {
    margin-bottom: 0;
    font-size: 0.9rem;
}

.active-filters .badge {
    font-size: 0.75rem;
    padding: 4px 8px;
}

/* Active Filters in Main Content */
.active-filters-main .alert {
    margin-bottom: 0;
    font-size: 0.9rem;
    border-left: 4px solid #17a2b8;
}

.active-filters-main .badge {
    font-size: 0.8rem;
    padding: 4px 8px;
    margin-right: 4px;
    margin-bottom: 2px;
    display: inline-block;
}

.active-filters-main .d-flex {
    flex-wrap: wrap;
    gap: 10px;
}

.active-filters-main .btn-xs {
    font-size: 0.75rem;
    padding: 2px 6px;
    white-space: nowrap;
}

/* Responsive Design */
@media (max-width: 991.98px) {
    .filters-sidebar {
        position: static;
        margin-bottom: 2rem;
    }
    
    .filters-sidebar .card {
        margin-bottom: 1rem;
    }
}

@media (max-width: 767.98px) {
    .filters-sidebar .card-body {
        padding: 10px;
    }
    
    .filters-sidebar .form-label {
        font-size: 0.75rem;
    }
    
    .filters-sidebar .form-control-sm {
        font-size: 0.75rem;
    }
}
</style>

<script>
let selectedUsers = new Set();

function toggleAllUsers(checkbox) {
  const userCheckboxes = document.querySelectorAll('.user-checkbox');
  userCheckboxes.forEach(cb => {
    cb.checked = checkbox.checked;
    if (checkbox.checked) {
      selectedUsers.add(cb.value);
      cb.closest('tr').classList.add('selected');
    } else {
      selectedUsers.delete(cb.value);
      cb.closest('tr').classList.remove('selected');
    }
  });
  updateBulkToolbar();
}

function updateBulkToolbar() {
  const userCheckboxes = document.querySelectorAll('.user-checkbox');
  const selectAllCheckbox = document.getElementById('select-all-users');
  const toolbar = document.getElementById('bulk-actions-toolbar');
  const buttonText = document.querySelector('#bulkActionsDropdown');
  
  selectedUsers.clear();
  userCheckboxes.forEach(cb => {
    if (cb.checked) {
      selectedUsers.add(cb.value);
      cb.closest('tr').classList.add('selected');
    } else {
      cb.closest('tr').classList.remove('selected');
    }
  });
  
  const count = selectedUsers.size;
  
  if (count > 0) {
    toolbar.classList.remove('disabled');
    buttonText.disabled = false;
    buttonText.classList.remove('disabled');
    buttonText.innerHTML = '<i class="fa fa-cog"></i> Actions (' + count + ')';
  } else {
    toolbar.classList.add('disabled');
    buttonText.disabled = true;
    buttonText.classList.add('disabled');
    buttonText.innerHTML = '<i class="fa fa-cog"></i> Actions';
  }
  
  // Update select all checkbox state
  if (count === 0) {
    selectAllCheckbox.checked = false;
    selectAllCheckbox.indeterminate = false;
  } else if (count === userCheckboxes.length) {
    selectAllCheckbox.checked = true;
    selectAllCheckbox.indeterminate = false;
  } else {
    selectAllCheckbox.checked = false;
    selectAllCheckbox.indeterminate = true;
  }
}

function clearSelection() {
  const userCheckboxes = document.querySelectorAll('.user-checkbox');
  const selectAllCheckbox = document.getElementById('select-all-users');
  
  userCheckboxes.forEach(cb => {
    cb.checked = false;
    cb.closest('tr').classList.remove('selected');
  });
  
  selectAllCheckbox.checked = false;
  selectAllCheckbox.indeterminate = false;
  selectedUsers.clear();
  updateBulkToolbar();
}

function bulkAction(action) {
  if (selectedUsers.size === 0) {
    alert('<?php echo t('please_select_users'); ?>');
    return;
  }
  
  const userIds = Array.from(selectedUsers);
  
  switch(action) {
    case 'delete':
      if (confirm('Are you sure you want to delete ' + selectedUsers.size + ' user(s)? This action cannot be undone.')) {
        performBulkAction('delete', userIds);
      }
      break;
      
    case 'activate':
      if (confirm('Are you sure you want to activate ' + selectedUsers.size + ' user(s)?')) {
        performBulkAction('activate', userIds);
      }
      break;
      
    case 'deactivate':
      if (confirm('Are you sure you want to deactivate ' + selectedUsers.size + ' user(s)?')) {
        performBulkAction('deactivate', userIds);
      }
      break;
      
    case 'assign-roles':
      showRoleAssignmentModal(userIds);
      break;
      
    case 'remove-roles':
      showRoleRemovalModal(userIds);
      break;
  }
}

function performBulkAction(action, userIds) {
  const formData = new FormData();
  formData.append('action', action);
  formData.append('user_ids', JSON.stringify(userIds));
  
  fetch('<?php echo site_url("admin/users/bulk_action"); ?>', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert(data.message || '<?php echo t('operation_completed'); ?>');
      location.reload();
    } else {
      alert(data.message || '<?php echo t('operation_failed'); ?>');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('<?php echo t('error_occurred'); ?>');
  });
}

function showRoleAssignmentModal(userIds) {
  const userIdsParam = userIds.join(',');
  
  // Show the modal
  $('#bulkRoleModal').modal('show');
  
  // Load the role assignment form via AJAX
  $.ajax({
    url: '<?php echo site_url("admin/users/bulk_assign_roles"); ?>',
    method: 'GET',
    data: { user_ids: userIdsParam },
    success: function(response) {
      const userCount = userIds.length;
      
      // Update modal content with the response (which is just the form content)
      $('#roleAssignmentContent').html(response);
      $('#assignRolesBtn').html('<i class="fa fa-users"></i> Assign Roles to ' + userCount + ' User' + (userCount > 1 ? 's' : '')).show();
      $('#removeRolesBtn').hide();
    },
    error: function(xhr) {
      console.error('AJAX Error:', xhr);
      $('#roleAssignmentContent').html('<div class="alert alert-danger">Error loading role assignment form. Please try again.</div>');
    }
  });
}

function processBulkRoleAssignment() {
  const form = $('#roleAssignmentContent form');
  const formData = form.serialize();
  
  $.ajax({
    url: '<?php echo site_url("admin/users/process_bulk_assign_roles"); ?>',
    method: 'POST',
    data: formData,
    success: function(response) {
      // Close modal and refresh page
      $('#bulkRoleModal').modal('hide');
      location.reload();
    },
    error: function(xhr) {
      // Show error message
      const errorMessage = xhr.responseText || 'Error processing role assignment. Please try again.';
      $('#roleAssignmentContent').html('<div class="alert alert-danger">' + errorMessage + '</div>');
    }
  });
}

function showRoleRemovalModal(userIds) {
  const userIdsParam = userIds.join(',');
  
  // Show the modal
  $('#bulkRoleModal').modal('show');
  
  // Load the role removal form via AJAX
  $.ajax({
    url: '<?php echo site_url("admin/users/bulk_remove_roles"); ?>',
    method: 'GET',
    data: { user_ids: userIdsParam },
    success: function(response) {
      const userCount = userIds.length;
      
      // Update modal content with the response
      $('#roleAssignmentContent').html(response);
      $('#assignRolesBtn').hide();
      $('#removeRolesBtn').html('<i class="fa fa-user-minus"></i> Remove Roles from ' + userCount + ' User' + (userCount > 1 ? 's' : '')).show();
    },
    error: function(xhr) {
      console.error('AJAX Error:', xhr);
      $('#roleAssignmentContent').html('<div class="alert alert-danger">Error loading role removal form. Please try again.</div>');
    }
  });
}

function processBulkRoleRemoval() {
  const form = $('#roleAssignmentContent form');
  const formData = form.serialize();
  
  $.ajax({
    url: '<?php echo site_url("admin/users/process_bulk_remove_roles"); ?>',
    method: 'POST',
    data: formData,
    success: function(response) {
      // Close modal and refresh page
      $('#bulkRoleModal').modal('hide');
      location.reload();
    },
    error: function(xhr) {
      // Show error message
      const errorMessage = xhr.responseText || 'Error processing role removal. Please try again.';
      $('#roleAssignmentContent').html('<div class="alert alert-danger">' + errorMessage + '</div>');
    }
  });
}
</script>