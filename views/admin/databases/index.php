<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Database Hosts</h1>
        <p class="page-subtitle">Configure MySQL / MariaDB host servers for automatic database provisioning</p>
    </div>
    <div>
        <button class="btn btn-primary btn-sm" onclick="App.openModal('modal-create-db-host')">Create Database Host</button>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Host Name</th>
                        <th>Endpoint</th>
                        <th>Username</th>
                        <th>Node</th>
                        <th>Databases</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($hosts)): ?>
                        <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 24px;">No database hosts are configured.</td></tr>
                    <?php else: ?>
                        <?php foreach ($hosts as $h): ?>
                            <tr>
                                <td><?= (int)$h['id'] ?></td>
                                <td>
                                    <a href="/admin/databases/view/<?= $h['id'] ?>" style="font-weight: 600; color: #fff;">
                                        <?= \Pterodactyl\Core\View::e($h['name']) ?>
                                    </a>
                                </td>
                                <td style="font-family: var(--font-mono); color: var(--text-secondary);"><?= \Pterodactyl\Core\View::e($h['host'] . ':' . $h['port']) ?></td>
                                <td style="font-family: var(--font-mono);"><?= \Pterodactyl\Core\View::e($h['username']) ?></td>
                                <td><?= \Pterodactyl\Core\View::e($h['node_name'] ?? 'Global') ?></td>
                                <td><?= (int)$h['databases_count'] ?></td>
                                <td style="text-align: right;">
                                    <a href="/admin/databases/view/<?= $h['id'] ?>" class="btn btn-secondary btn-sm">Manage</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Database Host Modal -->
<div class="modal-backdrop" id="modal-create-db-host">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Create Database Host</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="/admin/databases" method="POST">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="dbh-name">Host Name</label>
                    <input type="text" id="dbh-name" name="name" class="form-control" required placeholder="e.g. Local MariaDB, Main MySQL">
                </div>
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 8px;">
                    <div class="form-group">
                        <label class="form-label" for="dbh-host">Host IP / FQDN</label>
                        <input type="text" id="dbh-host" name="host" class="form-control" required value="127.0.0.1">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="dbh-port">Port</label>
                        <input type="number" id="dbh-port" name="port" class="form-control" required value="3306">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <div class="form-group">
                        <label class="form-label" for="dbh-user">Admin Username</label>
                        <input type="text" id="dbh-user" name="username" class="form-control" required placeholder="root">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="dbh-pwd">Admin Password</label>
                        <input type="password" id="dbh-pwd" name="password" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="dbh-node">Linked Node (Optional)</label>
                    <select id="dbh-node" name="node_id" class="form-control">
                        <option value="">Global (Available to all nodes)</option>
                        <?php foreach ($nodes as $n): ?>
                            <option value="<?= $n['id'] ?>"><?= \Pterodactyl\Core\View::e($n['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Add Database Host</button>
            </div>
        </form>
    </div>
</div>
