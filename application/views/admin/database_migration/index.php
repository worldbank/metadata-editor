<div class="container-fluid mt-4">
    <h1>Database Migrations</h1>
    
    <?php if ($this->session->flashdata('message')): ?>
        <div class="alert alert-success">
            <?php echo $this->session->flashdata('message'); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($this->session->flashdata('error')): ?>
        <div class="alert alert-danger">
            <?php echo $this->session->flashdata('error'); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!$migration_enabled): ?>
        <div class="alert alert-warning">
            <strong>Migrations are currently disabled.</strong>
            <p>To enable migrations, edit <code>application/config/migration.php</code> and set:</p>
            <pre>$config['migration_enabled'] = TRUE;</pre>
            <p><strong>Important:</strong> Re-disable migrations after running them for security.</p>
        </div>
    <?php endif; ?>
    
    <?php if ($db_debug_enabled): ?>
        <div class="alert alert-warning">
            <strong>⚠ Security Warning: Database debug mode is ENABLED</strong>
            <p>Database debug mode can expose sensitive information in error messages during migrations.</p>
            <p>It will be automatically disabled during migration execution, but for production environments, you should permanently disable it:</p>
            <pre>// In application/config/database.php
$db['default']['db_debug'] = FALSE;</pre>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Current Database Version</h5>
            <p class="card-text">
                <strong>Version:</strong> <?php echo $current_version ? $current_version : 'No migrations run yet'; ?>
            </p>
            <p class="card-text text-muted mb-0">
                The stored version is a watermark: all migrations up to and including this version are considered applied.
            </p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Manual Upgrades</h5>
            <p class="card-text">
                If you applied database changes outside this tool (direct SQL, DBA scripts, etc.),
                use <strong>Mark as applied</strong> to update the tracked version without running any SQL.
            </p>
            <p class="card-text mb-0">
                <strong>Mark as applied</strong> sets the watermark through the selected migration (and all prior ones).
                <strong>Unmark</strong> lowers the watermark so the selected migration and all later ones become pending again.
                Neither action changes your database schema or data.
            </p>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Available Migrations</h5>
            
            <?php if (empty($available_migrations)): ?>
                <p>No migration files found in <code>application/migrations/</code></p>
            <?php else: ?>
                <div class="alert alert-warning">
                    <strong>Warning:</strong> Migrations are one-way only. Make sure you have a database backup before proceeding!
                </div>
                
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Version</th>
                            <th>Name</th>
                            <th>File</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($available_migrations as $migration): ?>
                            <?php
                                $is_pending = (int)$migration['version'] > (int)$current_version;
                                $is_current = $migration['version'] === $current_version;
                                $is_applied = (int)$migration['version'] < (int)$current_version;
                            ?>
                            <tr>
                                <td><code><?php echo $migration['version']; ?></code></td>
                                <td><?php echo $migration['name']; ?></td>
                                <td><small><?php echo $migration['file']; ?></small></td>
                                <td>
                                    <?php if ($is_pending): ?>
                                        <span class="badge badge-warning">Pending</span>
                                    <?php elseif ($is_current): ?>
                                        <span class="badge badge-success">Current</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Applied</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_pending): ?>
                                        <?php if ($migration_enabled): ?>
                                            <a href="<?php echo site_url('admin/database_migration/run/' . $migration['version']); ?>"
                                               class="btn btn-sm btn-primary"
                                               onclick="return confirm('Run migration <?php echo $migration['version']; ?>?\n\nThis will execute SQL/PHP and cannot be undone.\n\nMake sure you have a database backup.');">
                                                Run
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-secondary" disabled>Run (disabled)</button>
                                        <?php endif; ?>
                                        <a href="<?php echo site_url('admin/database_migration/mark_applied/' . $migration['version']); ?>"
                                           class="btn btn-sm btn-outline-secondary"
                                           onclick="return confirmMarkApplied('<?php echo $migration['version']; ?>');">
                                            Mark as applied
                                        </a>
                                    <?php elseif ($is_current || $is_applied): ?>
                                        <a href="<?php echo site_url('admin/database_migration/unmark/' . $migration['version']); ?>"
                                           class="btn btn-sm btn-outline-warning"
                                           onclick="return confirmUnmark('<?php echo $migration['version']; ?>');">
                                            Unmark
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="mt-3">
                    <?php if ($migration_enabled): ?>
                        <a href="<?php echo site_url('admin/database_migration/run/latest'); ?>" 
                           class="btn btn-success"
                           onclick="return confirm('Migrate to the latest version?\n\nThis will run all pending migrations and cannot be undone.\n\nMake sure you have a database backup.');">
                            Migrate to Latest
                        </a>
                    <?php else: ?>
                        <button class="btn btn-secondary" disabled>
                            Migrations Disabled
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-body">
            <h5 class="card-title">Command Line Usage</h5>
            <p>Run migrations:</p>
            <pre>php index.php cli/migrate latest</pre>
            <pre>php index.php cli/migrate version <?php echo $available_migrations ? end($available_migrations)['version'] : 'YYYYMMDDHHIISS'; ?></pre>
            <p class="mt-3">Mark or unmark without running SQL (sets the stored version directly):</p>
            <pre>php index.php cli/migrate set_version <?php echo $available_migrations ? end($available_migrations)['version'] : 'YYYYMMDDHHIISS'; ?></pre>
            <p class="text-muted mb-0">
                To unmark from a version via CLI, set the version to the migration immediately before it.
            </p>
        </div>
    </div>
</div>

<script>
function confirmMarkApplied(version) {
    return confirm(
        'Mark migrations as applied through version ' + version + '?\n\n' +
        'This does NOT run any SQL or PHP.\n' +
        'Only use this if you have already applied these migrations manually.\n\n' +
        'All migrations up to and including this version will be marked as applied.'
    );
}

function confirmUnmark(version) {
    return confirm(
        'Unmark from version ' + version + '?\n\n' +
        'This does NOT change your database schema or data.\n' +
        'It only lowers the tracked version so this migration and all later ones become pending again.\n\n' +
        'Re-running migrations may fail or duplicate data unless they are idempotent.\n\n' +
        'Make sure you have a database backup before re-running.'
    );
}
</script>
