<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Servers on Node: <?= \Pterodactyl\Core\View::e($node['name']) ?></h1>
        <p class="page-subtitle">List of game servers currently deployed and running on this node</p>
    </div>
    <div>
        <a href="/admin/nodes" class="btn btn-secondary btn-sm">Back to Nodes</a>
    </div>
</div>

<!-- Node Sub-navigation -->
<nav class="subnav" style="margin-bottom: 20px;">
    <a href="/admin/nodes/view/<?= $node['id'] ?>" class="<?= ($activeNodeTab ?? '') === 'about' ? 'active' : '' ?>">Settings</a>
    <a href="/admin/nodes/view/<?= $node['id'] ?>/configuration" class="<?= ($activeNodeTab ?? '') === 'configuration' ? 'active' : '' ?>">Configuration</a>
    <a href="/admin/nodes/view/<?= $node['id'] ?>/allocation" class="<?= ($activeNodeTab ?? '') === 'allocation' ? 'active' : '' ?>">Allocations</a>
    <a href="/admin/nodes/view/<?= $node['id'] ?>/servers" class="<?= ($activeNodeTab ?? '') === 'servers' ? 'active' : '' ?>">Servers</a>
</nav>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Server Name</th>
                        <th>Owner</th>
                        <th>Connection</th>
                        <th>Memory</th>
                        <th>Disk</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($servers)): ?>
                        <tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 24px;">No servers currently hosted on this node.</td></tr>
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
                                <td style="font-family: var(--font-mono); color: var(--text-secondary);">
                                    <?= \Pterodactyl\Core\View::e($s['allocation_ip'] ? $s['allocation_ip'] . ':' . $s['allocation_port'] : 'None') ?>
                                </td>
                                <td><?= (int)$s['memory'] ?> MiB</td>
                                <td><?= (int)$s['disk'] ?> MiB</td>
                                <td>
                                    <?php if (!empty($s['suspended'])): ?>
                                        <span class="badge badge-danger">Suspended</span>
                                    <?php else: ?>
                                        <span class="badge badge-success"><?= \Pterodactyl\Core\View::e($s['status'] ?: 'Active') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="/admin/servers/view/<?= $s['id'] ?>" class="btn btn-secondary btn-sm">Manage</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
