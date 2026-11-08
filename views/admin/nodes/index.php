<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Nodes</h1>
        <p class="page-subtitle">Hardware and server nodes running Pterodactyl Wings</p>
    </div>
    <div>
        <a href="/admin/nodes/new" class="btn btn-primary btn-sm">Create New Node</a>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Memory</th>
                        <th>Disk</th>
                        <th>Servers</th>
                        <th>SSL</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($nodes)): ?>
                        <tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 24px;">No nodes configured.</td></tr>
                    <?php else: ?>
                        <?php foreach ($nodes as $n): ?>
                            <tr>
                                <td><?= (int)$n['id'] ?></td>
                                <td>
                                    <a href="/admin/nodes/view/<?= $n['id'] ?>" style="font-weight: 600; color: #fff;">
                                        <?= \Pterodactyl\Core\View::e($n['name']) ?>
                                    </a>
                                    <div style="font-size: 12px; color: var(--text-secondary); font-family: var(--font-mono);"><?= \Pterodactyl\Core\View::e($n['fqdn']) ?></div>
                                </td>
                                <td><?= \Pterodactyl\Core\View::e($n['location_short'] ?? '-') ?></td>
                                <td>
                                    <?= round(((int)$n['memory_used']) / 1024, 1) ?> / <?= round(((int)$n['memory']) / 1024, 1) ?> GiB
                                </td>
                                <td>
                                    <?= round(((int)$n['disk_used']) / 1024, 1) ?> / <?= round(((int)$n['disk']) / 1024, 1) ?> GiB
                                </td>
                                <td><?= (int)$n['servers_count'] ?></td>
                                <td>
                                    <?php if ($n['scheme'] === 'https'): ?>
                                        <span class="badge badge-success">HTTPS</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">HTTP</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="/admin/nodes/view/<?= $n['id'] ?>" class="btn btn-secondary btn-sm">Manage</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
