<?php $this->extend('layouts.server'); ?>

<div class="page-header" style="margin-bottom: 16px;">
    <div>
        <h1 class="page-title">Databases</h1>
        <p class="page-subtitle">Manage MySQL / MariaDB databases provisioned for this server</p>
    </div>
    <div>
        <button class="btn btn-primary btn-sm" onclick="App.openModal('modal-create-database')">Create Database</button>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Database</th>
                        <th>Username</th>
                        <th>Endpoint</th>
                        <th>Password</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($databases)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">No databases have been created for this server.</td></tr>
                    <?php else: ?>
                        <?php foreach ($databases as $d): ?>
                            <tr>
                                <td style="font-weight: 600; color: #fff; font-family: var(--font-mono);"><?= \Pterodactyl\Core\View::e($d['database']) ?></td>
                                <td style="font-family: var(--font-mono);"><?= \Pterodactyl\Core\View::e($d['username']) ?></td>
                                <td style="font-family: var(--font-mono); color: var(--text-secondary);"><?= \Pterodactyl\Core\View::e($d['host'] . ':' . $d['port']) ?></td>
                                <td>
                                    <span style="font-family: var(--font-mono); background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 3px;"><?= \Pterodactyl\Core\View::e($d['password_plain']) ?></span>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <button class="btn btn-secondary btn-sm" onclick="rotateDbPassword('<?= $d['id'] ?>')">Rotate Password</button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteDb('<?= $d['id'] ?>')">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Database Modal -->
<div class="modal-backdrop" id="modal-create-database">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Create Database</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form id="create-db-form">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="db-name">Database Name</label>
                    <input type="text" id="db-name" class="form-control" required placeholder="e.g. survival, core">
                </div>
                <div class="form-group">
                    <label class="form-label" for="db-connections">Connections From (IP or % for any)</label>
                    <input type="text" id="db-connections" class="form-control" value="%" placeholder="%">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Create Database</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('create-db-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const database = document.getElementById('db-name').value;
    const remote = document.getElementById('db-connections').value;

    try {
        await App.request('/api/client/servers/<?= $server['uuid'] ?>/databases', {
            method: 'POST',
            body: { database, remote }
        });
        App.toast('Database created successfully.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {}
});

async function rotateDbPassword(id) {
    if (!confirm('Are you sure you want to rotate this database password? Any plugins or services connected with the old password will stop working until updated.')) return;
    try {
        await App.request(`/api/client/servers/<?= $server['uuid'] ?>/databases/${id}/rotate-password`, {
            method: 'POST'
        });
        App.toast('Password rotated successfully.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {}
}

async function deleteDb(id) {
    if (!confirm('Are you sure you want to delete this database? All data inside it will be permanently deleted.')) return;
    try {
        await App.request(`/api/client/servers/<?= $server['uuid'] ?>/databases/${id}`, {
            method: 'DELETE'
        });
        App.toast('Database deleted.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {}
}
</script>
