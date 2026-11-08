<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Server: <?= \Pterodactyl\Core\View::e($server['name']) ?></h1>
        <p class="page-subtitle"><?= \Pterodactyl\Core\View::e($server['uuid']) ?> &bull; Node: <?= \Pterodactyl\Core\View::e($server['node_name'] ?? 'Unknown') ?></p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/server/<?= $server['uuidShort'] ?>" target="_blank" class="btn btn-primary btn-sm">Open Client Console &nearr;</a>
        <a href="/admin/servers" class="btn btn-secondary btn-sm">Back to Servers</a>
    </div>
</div>

<!-- Server Sub-navigation -->
<nav class="subnav" style="margin-bottom: 20px;">
    <a href="/admin/servers/view/<?= $server['id'] ?>" class="<?= ($activeServerTab ?? '') === 'overview' ? 'active' : '' ?>">Overview</a>
    <a href="/admin/servers/view/<?= $server['id'] ?>/details" class="<?= ($activeServerTab ?? '') === 'details' ? 'active' : '' ?>">Details</a>
    <a href="/admin/servers/view/<?= $server['id'] ?>/build" class="<?= ($activeServerTab ?? '') === 'build' ? 'active' : '' ?>">Build Configuration</a>
    <a href="/admin/servers/view/<?= $server['id'] ?>/manage" class="<?= ($activeServerTab ?? '') === 'manage' ? 'active' : '' ?>">Manage &amp; Actions</a>
</nav>

<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px;">
    <div class="card" style="padding: 16px; text-align: center;">
        <div style="font-size: 20px; font-weight: 700; color: #3b82f6;"><?= (int)$server['memory'] ?> MiB</div>
        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Memory Allocation</div>
    </div>
    <div class="card" style="padding: 16px; text-align: center;">
        <div style="font-size: 20px; font-weight: 700; color: #10b981;"><?= (int)$server['disk'] ?> MiB</div>
        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Disk Allocation</div>
    </div>
    <div class="card" style="padding: 16px; text-align: center;">
        <div style="font-size: 20px; font-weight: 700; color: #f59e0b;"><?= (int)$server['cpu'] ?>%</div>
        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">CPU Limit</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <div class="card">
        <div class="card-header">Server Metadata</div>
        <div class="card-body" style="padding: 0;">
            <table class="table">
                <tbody>
                    <tr>
                        <td style="color: var(--text-secondary); width: 140px;">Server UUID</td>
                        <td style="font-family: var(--font-mono); font-size: 12px;"><?= \Pterodactyl\Core\View::e($server['uuid']) ?></td>
                    </tr>
                    <tr>
                        <td style="color: var(--text-secondary);">Short UUID</td>
                        <td style="font-family: var(--font-mono); font-size: 12px;"><?= \Pterodactyl\Core\View::e($server['uuidShort']) ?></td>
                    </tr>
                    <tr>
                        <td style="color: var(--text-secondary);">Primary Connection</td>
                        <td style="font-family: var(--font-mono); color: #60a5fa;">
                            <?= \Pterodactyl\Core\View::e($server['allocation_ip'] ? $server['allocation_ip'] . ':' . $server['allocation_port'] : 'None') ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="color: var(--text-secondary);">Node</td>
                        <td>
                            <a href="/admin/nodes/view/<?= $server['node_id'] ?>" style="color: #60a5fa; font-weight: 600;">
                                <?= \Pterodactyl\Core\View::e($server['node_name'] ?? 'Node') ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="color: var(--text-secondary);">Nest &amp; Egg</td>
                        <td>
                            <?= \Pterodactyl\Core\View::e(($server['nest_name'] ?? 'Nest') . ' &bull; ' . ($server['egg_name'] ?? 'Egg')) ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="color: var(--text-secondary);">Status</td>
                        <td>
                            <?php if (!empty($server['suspended'])): ?>
                                <span class="badge badge-danger">Suspended</span>
                            <?php else: ?>
                                <span class="badge badge-success"><?= \Pterodactyl\Core\View::e($server['status'] ?: 'Active') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Owner Information</div>
        <div class="card-body" style="padding: 0;">
            <table class="table">
                <tbody>
                    <tr>
                        <td style="color: var(--text-secondary); width: 140px;">Username</td>
                        <td>
                            <a href="/admin/users/view/<?= $server['owner_id'] ?>" style="color: #60a5fa; font-weight: 600;">
                                <?= \Pterodactyl\Core\View::e($server['owner_username'] ?? 'User') ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="color: var(--text-secondary);">Email</td>
                        <td><?= \Pterodactyl\Core\View::e($server['owner_email'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td style="color: var(--text-secondary);">Docker Image</td>
                        <td style="font-family: var(--font-mono); font-size: 12px;"><?= \Pterodactyl\Core\View::e($server['image']) ?></td>
                    </tr>
                    <tr>
                        <td style="color: var(--text-secondary);">Created At</td>
                        <td><?= \Pterodactyl\Core\View::e($server['created_at']) ?></td>
                    </tr>
                    <tr>
                        <td style="color: var(--text-secondary);">Last Updated</td>
                        <td><?= \Pterodactyl\Core\View::e($server['updated_at']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
