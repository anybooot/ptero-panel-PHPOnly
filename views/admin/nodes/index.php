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
                        <th style="width: 46px; text-align: center;"></th>
                        <th style="width: 60px;">ID</th>
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
                        <tr><td colspan="9" style="text-align: center; color: var(--text-muted); padding: 24px;">No nodes configured.</td></tr>
                    <?php else: ?>
                        <?php foreach ($nodes as $n): ?>
                            <tr>
                                <td style="text-align: center; vertical-align: middle;">
                                    <span class="node-heart" id="node-heart-<?= $n['id'] ?>" data-node-id="<?= $n['id'] ?>" title="Connecting..." style="display: inline-flex; align-items: center; justify-content: center; cursor: pointer;">
                                        <svg class="heart-icon" viewBox="0 0 24 24" width="18" height="18" style="fill: #64748b; transition: fill 0.3s ease, filter 0.3s ease;">
                                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                        </svg>
                                    </span>
                                </td>
                                <td style="font-family: var(--font-mono); color: var(--text-muted);"><?= (int)$n['id'] ?></td>
                                <td>
                                    <a href="/admin/nodes/view/<?= $n['id'] ?>" style="font-weight: 600; color: #eceff4;">
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

<style>
@keyframes nodeHeartbeat {
    0% { transform: scale(1); }
    12% { transform: scale(1.22); }
    24% { transform: scale(1); }
    36% { transform: scale(1.22); }
    60% { transform: scale(1); }
    100% { transform: scale(1); }
}
.node-heart-online svg {
    fill: #10b981 !important;
    filter: drop-shadow(0 0 6px rgba(16, 185, 129, 0.6));
    animation: nodeHeartbeat 2.2s infinite ease-in-out;
}
.node-heart-offline svg {
    fill: #ef4444 !important;
    filter: drop-shadow(0 0 6px rgba(239, 68, 68, 0.4));
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const hearts = document.querySelectorAll('.node-heart');
    hearts.forEach(async (heart) => {
        const nodeId = heart.dataset.nodeId;
        try {
            const res = await fetch(`/admin/nodes/view/${nodeId}/system-information`);
            if (res.ok) {
                heart.classList.add('node-heart-online');
                heart.title = 'Online: Wings daemon is reachable';
            } else {
                heart.classList.add('node-heart-offline');
                heart.title = 'Offline: Cannot reach Wings daemon';
            }
        } catch (e) {
            heart.classList.add('node-heart-offline');
            heart.title = 'Offline: Network error';
        }
    });
});
</script>
