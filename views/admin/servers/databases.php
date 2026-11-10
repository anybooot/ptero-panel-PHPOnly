<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Databases: <?= \Pterodactyl\Core\View::e($server['name']) ?></h1>
        <p class="page-subtitle"><?= \Pterodactyl\Core\View::e($server['uuid']) ?> &bull; Limit: <?= (int)$server['database_limit'] ?> allowed</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <button class="btn btn-primary btn-sm" onclick="App.openModal('modal-create-database')">New Database</button>
        <a href="/admin/servers" class="btn btn-secondary btn-sm">Back to Servers</a>
    </div>
</div>

<!-- Server Sub-navigation -->
<nav class="subnav" style="margin-bottom: 20px;">
    <a href="/admin/servers/view/<?= $server['id'] ?>" class="<?= ($activeServerTab ?? '') === 'overview' ? 'active' : '' ?>">Overview</a>
    <a href="/admin/servers/view/<?= $server['id'] ?>/details" class="<?= ($activeServerTab ?? '') === 'details' ? 'active' : '' ?>">Details</a>
    <a href="/admin/servers/view/<?= $server['id'] ?>/build" class="<?= ($activeServerTab ?? '') === 'build' ? 'active' : '' ?>">Build Configuration</a>
    <a href="/admin/servers/view/<?= $server['id'] ?>/startup" class="<?= ($activeServerTab ?? '') === 'startup' ? 'active' : '' ?>">Startup</a>
    <a href="/admin/servers/view/<?= $server['id'] ?>/database" class="<?= ($activeServerTab ?? '') === 'database' ? 'active' : '' ?>">Databases</a>
    <a href="/admin/servers/view/<?= $server['id'] ?>/mounts" class="<?= ($activeServerTab ?? '') === 'mounts' ? 'active' : '' ?>">Mounts</a>
    <a href="/admin/servers/view/<?= $server['id'] ?>/manage" class="<?= ($activeServerTab ?? '') === 'manage' ? 'active' : '' ?>">Manage &amp; Actions</a>
</nav>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Database Name</th>
                        <th>Username</th>
                        <th>Host Endpoint</th>
                        <th>Connections</th>
                        <th>Password</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($databases)): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">No databases configured for this server.</td></tr>
                    <?php else: ?>
                        <?php foreach ($databases as $d): ?>
                            <tr>
                                <td><strong style="color: #fff; font-family: var(--font-mono);"><?= \Pterodactyl\Core\View::e($d['database']) ?></strong></td>
                                <td style="font-family: var(--font-mono); color: #93c5fd;"><?= \Pterodactyl\Core\View::e($d['username']) ?></td>
                                <td style="font-family: var(--font-mono); font-size: 12px; color: var(--text-secondary);"><?= \Pterodactyl\Core\View::e($d['host'] . ':' . $d['port']) ?></td>
                                <td><code><?= \Pterodactyl\Core\View::e($d['remote']) ?></code></td>
                                <td>
                                    <span style="font-family: var(--font-mono); font-size: 11px; background: rgba(0,0,0,0.3); padding: 3px 6px; border-radius: 3px; color: #34d399;">
                                        <?= \Pterodactyl\Core\View::e($d['password_plain'] ?? '') ?>
                                    </span>
                                </td>
                                <td style="text-align: right; display: flex; justify-content: flex-end; gap: 6px;">
                                    <form action="/admin/servers/view/<?= $server['id'] ?>/database/<?= $d['id'] ?>/reset-password" method="POST" onsubmit="return confirm('Reset the password for this database?');" style="margin: 0;">
                                        <?= \Pterodactyl\Core\View::csrf() ?>
                                        <button type="submit" class="btn btn-secondary btn-sm" style="padding: 3px 8px;">Reset Password</button>
                                    </form>
                                    <form action="/admin/servers/view/<?= $server['id'] ?>/database/<?= $d['id'] ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this database? All data will be permanently destroyed.');" style="margin: 0;">
                                        <?= \Pterodactyl\Core\View::csrf() ?>
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button type="submit" class="btn btn-danger btn-sm" style="padding: 3px 8px;">Delete</button>
                                    </form>
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
            <h3 class="modal-title">Create Server Database</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="/admin/servers/view/<?= $server['id'] ?>/database" method="POST">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="db-host">Database Host</label>
                    <select id="db-host" name="database_host_id" class="form-control" required>
                        <?php foreach ($hosts as $h): ?>
                            <option value="<?= $h['id'] ?>"><?= \Pterodactyl\Core\View::e($h['name'] . ' (' . $h['host'] . ':' . $h['port'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="db-name">Database Name</label>
                    <input type="text" id="db-name" name="database" class="form-control" required placeholder="e.g. data or survival">
                    <small style="color: var(--text-muted); font-size: 11px; display: block; margin-top: 4px;">
                        Will be prefixed with <code>s<?= $server['id'] ?>_</code> automatically.
                    </small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="db-remote">Connections From (Remote)</label>
                    <input type="text" id="db-remote" name="remote" class="form-control" value="%" required>
                    <small style="color: var(--text-muted); font-size: 11px; display: block; margin-top: 4px;">
                        Default % allows connections from anywhere. Specify an IP to restrict access.
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Create Database</button>
            </div>
        </form>
    </div>
</div>
