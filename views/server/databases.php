<?php $this->extend('layouts.server'); ?>

<div class="page-header" style="margin-bottom: 16px;">
    <div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <h1 class="page-title">Databases</h1>
            <span class="badge <?= empty($canCreate) ? 'badge-danger' : 'badge-secondary' ?>" style="font-family: var(--font-mono); font-size: 12px;">
                <?= (int)$databaseCount ?> / <?= (int)$databaseLimit > 0 ? (int)$databaseLimit : 'Unlimited' ?>
            </span>
        </div>
        <p class="page-subtitle">Manage MySQL / MariaDB databases and connection credentials provisioned for this server</p>
    </div>
    <div>
        <?php if (\Pterodactyl\Security\Gate::allows($user, $server, 'database.create')): ?>
            <?php if (!empty($canCreate)): ?>
                <button class="btn btn-primary btn-sm" onclick="App.openModal('modal-create-database')">Create Database</button>
            <?php else: ?>
                <button class="btn btn-secondary btn-sm" disabled title="Database limit reached">Limit Reached</button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($canCreate) && \Pterodactyl\Security\Gate::allows($user, $server, 'database.create')): ?>
    <div class="alert alert-warning" style="margin-bottom: 16px; border-left: 4px solid #f59e0b;">
        <strong>Limit reached:</strong> You have reached the maximum number of databases allowed for this server (<?= (int)$databaseCount ?> / <?= (int)$databaseLimit ?>). To create a new one, delete an existing database or increase the limit in the administration panel.
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Database</th>
                        <th>Username</th>
                        <th>Endpoint</th>
                        <th>Connections From</th>
                        <th>Password</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($databases)): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 32px 20px;">No databases have been created for this server.</td></tr>
                    <?php else: ?>
                        <?php foreach ($databases as $d): ?>
                            <?php
                                $dbHost = $d['host'] ?? '127.0.0.1';
                                $dbPort = $d['port'] ?? 3306;
                                $dbUser = $d['username'] ?? '';
                                $dbPass = $d['password_plain'] ?? '';
                                $dbName = $d['database'] ?? '';
                                $dbRemote = $d['remote'] ?? '%';
                                $jdbcUrl = "jdbc:mysql://{$dbHost}:{$dbPort}/{$dbName}";
                            ?>
                            <tr class="database-row">
                                <td>
                                    <div style="font-weight: 700; color: #eceff4; font-family: var(--font-mono); font-size: 13.5px;"><?= \Pterodactyl\Core\View::e($dbName) ?></div>
                                    <div style="font-size: 11.5px; color: var(--text-muted);"><?= \Pterodactyl\Core\View::e($d['host_name'] ?? 'MySQL Host') ?></div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span style="font-family: var(--font-mono); color: #cbd5e1;"><?= \Pterodactyl\Core\View::e($dbUser) ?></span>
                                        <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 7px; display: inline-flex; align-items: center; justify-content: center;" onclick="copyToClipboard('<?= \Pterodactyl\Core\View::e($dbUser) ?>', 'Username copied!')" data-tooltip="Copy Username"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span style="font-family: var(--font-mono); color: #e5e7eb;"><?= \Pterodactyl\Core\View::e($dbHost . ':' . $dbPort) ?></span>
                                        <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 7px; display: inline-flex; align-items: center; justify-content: center;" onclick="copyToClipboard('<?= \Pterodactyl\Core\View::e($dbHost . ':' . $dbPort) ?>', 'Endpoint copied!')" data-tooltip="Copy Endpoint"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-secondary" style="font-family: var(--font-mono); font-size: 11px;"><?= \Pterodactyl\Core\View::e($dbRemote) ?></span>
                                </td>
                                <td>
                                    <?php if (\Pterodactyl\Security\Gate::allows($user, $server, 'database.view_password')): ?>
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <span id="pwd-display-<?= $d['id'] ?>" data-hidden="true" data-pwd="<?= \Pterodactyl\Core\View::e($dbPass) ?>" style="font-family: var(--font-mono); background: rgba(0,0,0,0.3); padding: 3px 8px; border-radius: 4px; color: #eceff4; min-width: 90px; text-align: center;">••••••••</span>
                                            <button type="button" class="btn btn-secondary btn-sm" style="padding: 1px 6px; font-size: 11px;" onclick="togglePasswordView('<?= $d['id'] ?>')" data-tooltip="Toggle visibility">👁</button>
                                            <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 7px; display: inline-flex; align-items: center; justify-content: center;" onclick="copyToClipboard('<?= \Pterodactyl\Core\View::e($dbPass) ?>', 'Password copied!')" data-tooltip="Copy Password"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 12px; font-style: italic;">[Restricted]</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <?php if (\Pterodactyl\Security\Gate::allows($user, $server, 'database.view_password')): ?>
                                        <button class="btn btn-secondary btn-sm" onclick='openDbDetailsModal(<?= json_encode([
                                            'database' => $dbName,
                                            'username' => $dbUser,
                                            'password' => $dbPass,
                                            'host' => $dbHost,
                                            'port' => $dbPort,
                                            'remote' => $dbRemote,
                                            'jdbc' => $jdbcUrl
                                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' data-tooltip="Connection Details">Details</button>
                                    <?php endif; ?>
                                    <?php if (\Pterodactyl\Security\Gate::allows($user, $server, 'database.update')): ?>
                                        <button class="btn btn-secondary btn-sm" onclick="rotateDbPassword('<?= $d['id'] ?>')" data-tooltip="Rotate Password">Rotate</button>
                                    <?php endif; ?>
                                    <?php if (\Pterodactyl\Security\Gate::allows($user, $server, 'database.delete')): ?>
                                        <button class="btn btn-danger btn-sm" onclick="deleteDb('<?= $d['id'] ?>')" data-tooltip="Delete Database">Delete</button>
                                    <?php endif; ?>
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

<!-- Database Connection Details Modal -->
<div class="modal-backdrop" id="modal-db-details">
    <div class="modal-dialog" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">Database Connection Details</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <div class="modal-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                <div>
                    <label class="form-label" style="font-size: 11px;">Endpoint (Host)</label>
                    <div style="display: flex; gap: 6px;">
                        <input type="text" id="modal-dt-host" class="form-control" readonly style="font-family: var(--font-mono); font-size: 12px;">
                        <button type="button" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; justify-content: center; padding: 0 10px;" onclick="copyInput('modal-dt-host')" data-tooltip="Copy Endpoint"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button>
                    </div>
                </div>
                <div>
                    <label class="form-label" style="font-size: 11px;">Port</label>
                    <input type="text" id="modal-dt-port" class="form-control" readonly style="font-family: var(--font-mono); font-size: 12px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                <div>
                    <label class="form-label" style="font-size: 11px;">Database Name</label>
                    <div style="display: flex; gap: 6px;">
                        <input type="text" id="modal-dt-name" class="form-control" readonly style="font-family: var(--font-mono); font-size: 12px;">
                        <button type="button" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; justify-content: center; padding: 0 10px;" onclick="copyInput('modal-dt-name')" data-tooltip="Copy Database Name"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button>
                    </div>
                </div>
                <div>
                    <label class="form-label" style="font-size: 11px;">Username</label>
                    <div style="display: flex; gap: 6px;">
                        <input type="text" id="modal-dt-user" class="form-control" readonly style="font-family: var(--font-mono); font-size: 12px;">
                        <button type="button" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; justify-content: center; padding: 0 10px;" onclick="copyInput('modal-dt-user')" data-tooltip="Copy Username"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" style="font-size: 11px;">Password</label>
                <div style="display: flex; gap: 6px;">
                    <input type="text" id="modal-dt-pass" class="form-control" readonly style="font-family: var(--font-mono); font-size: 12px;">
                    <button type="button" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; justify-content: center; padding: 0 10px;" onclick="copyInput('modal-dt-pass')" data-tooltip="Copy Password"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" style="font-size: 11px;">JDBC Connection String</label>
                <div style="display: flex; gap: 6px;">
                    <input type="text" id="modal-dt-jdbc" class="form-control" readonly style="font-family: var(--font-mono); font-size: 12px;">
                    <button type="button" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; justify-content: center; padding: 0 10px;" onclick="copyInput('modal-dt-jdbc')" data-tooltip="Copy JDBC String"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text, msg = 'Copied to clipboard!') {
    App.copyToClipboard(text);
}

function copyInput(inputId) {
    const el = document.getElementById(inputId);
    if (!el) return;
    copyToClipboard(el.value);
}

function togglePasswordView(id) {
    const el = document.getElementById(`pwd-display-${id}`);
    if (!el) return;
    const isHidden = el.dataset.hidden === 'true';
    if (isHidden) {
        el.textContent = el.dataset.pwd;
        el.dataset.hidden = 'false';
    } else {
        el.textContent = '••••••••';
        el.dataset.hidden = 'true';
    }
}

function openDbDetailsModal(data) {
    document.getElementById('modal-dt-host').value = data.host;
    document.getElementById('modal-dt-port').value = data.port;
    document.getElementById('modal-dt-name').value = data.database;
    document.getElementById('modal-dt-user').value = data.username;
    document.getElementById('modal-dt-pass').value = data.password;
    document.getElementById('modal-dt-jdbc').value = data.jdbc;
    App.openModal('modal-db-details');
}

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
    } catch (e) {
        App.toast('Error creating database: ' + e.message, 'danger');
    }
});

async function rotateDbPassword(id) {
    if (!await App.confirm('Are you sure you want to rotate this database password? Any plugins or services connected with the old password will stop working until updated.')) return;
    try {
        await App.request(`/api/client/servers/<?= $server['uuid'] ?>/databases/${id}/rotate-password`, {
            method: 'POST'
        });
        App.toast('Password rotated successfully.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {
        App.toast('Failed to rotate password: ' + e.message, 'danger');
    }
}

async function deleteDb(id) {
    if (!await App.confirm('Are you sure you want to delete this database? All data inside it will be permanently deleted.')) return;
    try {
        await App.request(`/api/client/servers/<?= $server['uuid'] ?>/databases/${id}`, {
            method: 'DELETE'
        });
        App.toast('Database deleted.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {
        App.toast('Failed to delete database: ' + e.message, 'danger');
    }
}
</script>
