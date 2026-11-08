<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Database Host: <?= \Pterodactyl\Core\View::e($host['name']) ?></h1>
        <p class="page-subtitle"><?= \Pterodactyl\Core\View::e($host['host'] . ':' . $host['port']) ?></p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/admin/databases" class="btn btn-secondary btn-sm">Back to Hosts</a>
        <form action="/admin/databases/view/<?= $host['id'] ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this database host?');" style="margin: 0;">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="btn btn-danger btn-sm">Delete Host</button>
        </form>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- Edit Host Settings Form -->
    <div class="card">
        <div class="card-header">Host Settings</div>
        <form action="/admin/databases/view/<?= $host['id'] ?>" method="POST">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="name">Host Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?= \Pterodactyl\Core\View::e($host['name']) ?>" required>
                </div>
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 8px;">
                    <div class="form-group">
                        <label class="form-label" for="host">Host IP / FQDN</label>
                        <input type="text" id="host" name="host" class="form-control" value="<?= \Pterodactyl\Core\View::e($host['host']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="port">Port</label>
                        <input type="number" id="port" name="port" class="form-control" value="<?= (int)$host['port'] ?>" required>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" value="<?= \Pterodactyl\Core\View::e($host['username']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password">Password (Leave blank to keep current)</label>
                        <input type="password" id="password" name="password" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="node_id">Linked Node</label>
                    <select id="node_id" name="node_id" class="form-control">
                        <option value="">Global (Available to all nodes)</option>
                        <?php foreach ($nodes as $n): ?>
                            <option value="<?= $n['id'] ?>" <?= ((int)$host['node_id']) === ((int)$n['id']) ? 'selected' : '' ?>>
                                <?= \Pterodactyl\Core\View::e($n['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    <!-- Active Databases List -->
    <div class="card">
        <div class="card-header">Databases Provisioned on this Host</div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Database</th>
                            <th>Username</th>
                            <th>Server</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($databases)): ?>
                            <tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 20px;">No databases provisioned on this host yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($databases as $d): ?>
                                <tr>
                                    <td style="font-family: var(--font-mono); font-weight: 600; color: #fff;"><?= \Pterodactyl\Core\View::e($d['database']) ?></td>
                                    <td style="font-family: var(--font-mono);"><?= \Pterodactyl\Core\View::e($d['username']) ?></td>
                                    <td>
                                        <a href="/admin/servers/view/<?= $d['server_id'] ?>" style="color: #60a5fa;">
                                            <?= \Pterodactyl\Core\View::e($d['server_name'] ?? 'Server') ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
