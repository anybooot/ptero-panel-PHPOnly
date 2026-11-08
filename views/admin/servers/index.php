<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Servers</h1>
        <p class="page-subtitle">All game servers running across your infrastructure</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/admin/servers/new" class="btn btn-primary btn-sm">Create New Server</a>
    </div>
</div>

<!-- Search Bar -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 12px 16px;">
        <form action="/admin/servers" method="GET" style="display: flex; gap: 10px; margin: 0;">
            <input type="text" name="search" class="form-control" placeholder="Search servers by name, UUID, short UUID, or owner..." value="<?= \Pterodactyl\Core\View::e($search ?? '') ?>" style="flex: 1;">
            <button type="submit" class="btn btn-secondary">Search</button>
            <?php if (!empty($search)): ?>
                <a href="/admin/servers" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Server Name</th>
                        <th>Owner</th>
                        <th>Node</th>
                        <th>Connection</th>
                        <th>Memory / Disk</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($servers)): ?>
                        <tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 24px;">No servers found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($servers as $s): ?>
                            <tr>
                                <td><?= (int)$s['id'] ?></td>
                                <td>
                                    <a href="/admin/servers/view/<?= $s['id'] ?>" style="font-weight: 600; color: #fff;">
                                        <?= \Pterodactyl\Core\View::e($s['name']) ?>
                                    </a>
                                    <div style="font-size: 11px; color: var(--text-muted); font-family: var(--font-mono);"><?= \Pterodactyl\Core\View::e($s['uuidShort']) ?></div>
                                </td>
                                <td>
                                    <a href="/admin/users/view/<?= $s['owner_id'] ?>" style="color: #60a5fa;">
                                        <?= \Pterodactyl\Core\View::e($s['owner_username']) ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="/admin/nodes/view/<?= $s['node_id'] ?>" style="color: var(--text-secondary);">
                                        <?= \Pterodactyl\Core\View::e($s['node_name']) ?>
                                    </a>
                                </td>
                                <td style="font-family: var(--font-mono); color: var(--text-secondary);">
                                    <?= \Pterodactyl\Core\View::e($s['allocation_ip'] ? $s['allocation_ip'] . ':' . $s['allocation_port'] : 'None') ?>
                                </td>
                                <td>
                                    <span style="color: #fff;"><?= (int)$s['memory'] ?> MiB</span> / <span style="color: var(--text-muted);"><?= (int)$s['disk'] ?> MiB</span>
                                </td>
                                <td>
                                    <?php if (!empty($s['suspended'])): ?>
                                        <span class="badge badge-danger">Suspended</span>
                                    <?php elseif (!empty($s['status'])): ?>
                                        <span class="badge badge-warning"><?= \Pterodactyl\Core\View::e($s['status']) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="/admin/servers/view/<?= $s['id'] ?>" class="btn btn-secondary btn-sm">Manage</a>
                                    <a href="/server/<?= $s['uuidShort'] ?>" target="_blank" class="btn btn-secondary btn-sm" title="Open Client Console">&nearr;</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
