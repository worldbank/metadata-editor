<div class="container-fluid content-fluid page-users-pending-activation">

  <?php $message = $this->session->flashdata('message');?>
  <?php echo ($message != "") ? '<div class="alert alert-success">' . $message . '</div>' : ''; ?>
  <?php $error = $this->session->flashdata('error');?>
  <?php echo ($error != "") ? '<div class="alert alert-danger">' . $error . '</div>' : ''; ?>

  <div class="page-links text-right m-3 pb-3">
    <a href="<?php echo site_url('admin/users'); ?>" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left" aria-hidden="true">&nbsp;</i> <?php echo t('back_to_users'); ?></a>
    <a href="<?php echo site_url('admin/users/export_pending_emails'); ?>" class="btn btn-outline-success btn-sm" title="Export all email addresses"><i class="fa fa-download" aria-hidden="true">&nbsp;</i> Export Emails</a>
  </div>
  
  <h1 class="page-title mt-3 mb-3"><?php echo t('pending_activation'); ?></h1>
  <p class="text-muted">Users who have registered but have not yet activated their accounts via email verification.</p>

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
          <div class="card-header">
            <h6 class="mb-0"><i class="fa fa-info-circle text-primary"></i> Information</h6>
          </div>
          <div class="card-body">
            <p class="mb-2"><strong>Total Pending:</strong> <?php echo $total; ?></p>
            <p class="text-muted small mb-2">These users need to click the activation link sent to their email address.</p>
            <hr class="my-2">
            <p class="text-muted small mb-0"><i class="fa fa-download"></i> Click "Export Emails" to download all email addresses as a semicolon-separated list.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-9 col-md-8">
      
      <!-- Bulk Actions -->
      <div class="card mb-3" id="bulk-actions-card" style="display: none;">
        <div class="card-body">
          <div class="row align-items-center">
            <div class="col-md-6">
              <span id="selected-count">0</span> user(s) selected
            </div>
            <div class="col-md-6 text-right">
              <button type="button" class="btn btn-sm btn-success" onclick="resendActivationEmail()">
                <i class="fa fa-envelope"></i> Resend Activation Email
              </button>
              <button type="button" class="btn btn-sm btn-primary" onclick="manualActivate()">
                <i class="fa fa-check"></i> Activate Now
              </button>
              <button type="button" class="btn btn-sm btn-danger" onclick="deletePending()">
                <i class="fa fa-trash"></i> Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Users Table -->
      <div class="card">
        <div class="card-body">
          
          <?php if (empty($rows)): ?>
            <div class="alert alert-info">
              <i class="fa fa-info-circle"></i> No users pending activation found.
            </div>
          <?php else: ?>
          
          <div class="table-responsive">
            <table class="table table-hover table-sm">
              <thead>
                <tr>
                  <th width="30">
                    <input type="checkbox" id="select-all" onchange="toggleSelectAll(this)">
                  </th>
                  <th><?php echo t('username'); ?></th>
                  <th><?php echo t('email'); ?></th>
                  <th><?php echo t('country'); ?></th>
                  <th><?php echo t('registered'); ?></th>
                  <th>Days Waiting</th>
                  <th width="120"><?php echo t('actions'); ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($rows as $row): ?>
                <?php 
                  $days_waiting = floor((time() - $row['created_on']) / 86400);
                  $waiting_class = $days_waiting > 7 ? 'text-danger' : ($days_waiting > 3 ? 'text-warning' : '');
                ?>
                <tr>
                  <td>
                    <input type="checkbox" class="user-checkbox" value="<?php echo $row['id']; ?>" onchange="updateBulkActions()">
                  </td>
                  <td><?php echo character_limiter($row['username'], 30); ?></td>
                  <td><?php echo $row['email']; ?></td>
                  <td><?php echo $row['country']; ?></td>
                  <td><?php echo date('Y-m-d H:i', $row['created_on']); ?></td>
                  <td class="<?php echo $waiting_class; ?>">
                    <strong><?php echo $days_waiting; ?></strong> day<?php echo $days_waiting != 1 ? 's' : ''; ?>
                  </td>
                  <td>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="resendSingle(<?php echo $row['id']; ?>)" title="Resend activation email">
                      <i class="fa fa-envelope"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="activateSingle(<?php echo $row['id']; ?>)" title="Activate now">
                      <i class="fa fa-check"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteSingle(<?php echo $row['id']; ?>)" title="Delete user">
                      <i class="fa fa-trash"></i>
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="row mt-3">
            <div class="col-md-12">
              <?php echo $this->pagination->create_links(); ?>
            </div>
          </div>

          <?php endif; ?>
          
        </div>
      </div>

    </div>
  </div>

</div>

<script>
function getSelectedUserIds() {
  var userIds = [];
  $('.user-checkbox:checked').each(function() {
    userIds.push($(this).val());
  });
  return userIds;
}

function updateBulkActions() {
  var selectedCount = $('.user-checkbox:checked').length;
  $('#selected-count').text(selectedCount);
  
  if (selectedCount > 0) {
    $('#bulk-actions-card').slideDown();
  } else {
    $('#bulk-actions-card').slideUp();
  }
}

function toggleSelectAll(checkbox) {
  $('.user-checkbox').prop('checked', checkbox.checked);
  updateBulkActions();
}

function resendActivationEmail() {
  var userIds = getSelectedUserIds();
  if (userIds.length === 0) {
    alert('Please select users first');
    return;
  }
  
  if (!confirm('Resend activation email to ' + userIds.length + ' user(s)?')) {
    return;
  }
  
  $.ajax({
    url: '<?php echo site_url("admin/users/resend_activation_email"); ?>',
    method: 'POST',
    data: { user_ids: JSON.stringify(userIds) },
    dataType: 'json',
    success: function(response) {
      if (response.success) {
        alert(response.message);
        location.reload();
      } else {
        alert('Error: ' + response.message);
      }
    },
    error: function() {
      alert('An error occurred while sending activation emails');
    }
  });
}

function manualActivate() {
  var userIds = getSelectedUserIds();
  if (userIds.length === 0) {
    alert('Please select users first');
    return;
  }
  
  if (!confirm('Manually activate ' + userIds.length + ' user(s)? They will be able to log in immediately.')) {
    return;
  }
  
  $.ajax({
    url: '<?php echo site_url("admin/users/manual_activate"); ?>',
    method: 'POST',
    data: { user_ids: JSON.stringify(userIds) },
    dataType: 'json',
    success: function(response) {
      if (response.success) {
        alert(response.message);
        location.reload();
      } else {
        alert('Error: ' + response.message);
      }
    },
    error: function() {
      alert('An error occurred while activating users');
    }
  });
}

function deletePending() {
  var userIds = getSelectedUserIds();
  if (userIds.length === 0) {
    alert('Please select users first');
    return;
  }
  
  if (!confirm('Delete ' + userIds.length + ' user(s)? This action cannot be undone.')) {
    return;
  }
  
  $.ajax({
    url: '<?php echo site_url("admin/users/delete_pending"); ?>',
    method: 'POST',
    data: { user_ids: JSON.stringify(userIds) },
    dataType: 'json',
    success: function(response) {
      if (response.success) {
        alert(response.message);
        location.reload();
      } else {
        alert('Error: ' + response.message);
      }
    },
    error: function() {
      alert('An error occurred while deleting users');
    }
  });
}

function resendSingle(userId) {
  if (!confirm('Resend activation email to this user?')) {
    return;
  }
  
  $.ajax({
    url: '<?php echo site_url("admin/users/resend_activation_email"); ?>',
    method: 'POST',
    data: { user_ids: JSON.stringify([userId]) },
    dataType: 'json',
    success: function(response) {
      if (response.success) {
        alert(response.message);
        location.reload();
      } else {
        alert('Error: ' + response.message);
      }
    },
    error: function() {
      alert('An error occurred while sending activation email');
    }
  });
}

function activateSingle(userId) {
  if (!confirm('Manually activate this user?')) {
    return;
  }
  
  $.ajax({
    url: '<?php echo site_url("admin/users/manual_activate"); ?>',
    method: 'POST',
    data: { user_ids: JSON.stringify([userId]) },
    dataType: 'json',
    success: function(response) {
      if (response.success) {
        alert(response.message);
        location.reload();
      } else {
        alert('Error: ' + response.message);
      }
    },
    error: function() {
      alert('An error occurred while activating user');
    }
  });
}

function deleteSingle(userId) {
  if (!confirm('Delete this user? This action cannot be undone.')) {
    return;
  }
  
  $.ajax({
    url: '<?php echo site_url("admin/users/delete_pending"); ?>',
    method: 'POST',
    data: { user_ids: JSON.stringify([userId]) },
    dataType: 'json',
    success: function(response) {
      if (response.success) {
        alert(response.message);
        location.reload();
      } else {
        alert('Error: ' + response.message);
      }
    },
    error: function() {
      alert('An error occurred while deleting user');
    }
  });
}
</script>

