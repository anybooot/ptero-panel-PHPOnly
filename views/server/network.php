<?php $this->extend('layouts.server'); ?>

<div class="page-header" style="margin-bottom: 16px;">
    <div>
        <h1 class="page-title">Network Allocations</h1>
        <p class="page-subtitle">Configure IP and port allocations assigned to your server</p>
    </div>
    <div>
        <button class="btn btn-primary btn-sm" onclick="assignAllocation()">Assign Allocation</button>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>IP Address</th>
                        <th>Port</th>
                        <th>Alias</th>
                        <th>Notes</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allocations as $a): ?>
                        <?php $isPrimary = ((int)$a['id']) === ((int)$server['allocation_id']); ?>
                        <tr>
                            <td style="font-family: var(--font-mono); font-weight: 600; color: #fff;"><?= \Pterodactyl\Core\View::e($a['ip']) ?></td>
                            <td style="font-family: var(--font-mono); color: #60a5fa;"><?= (int)$a['port'] ?></td>
                            <td style="font-family: var(--font-mono); color: var(--text-secondary);"><?= \Pterodactyl\Core\View::e($a['ip_alias'] ?? '-') ?></td>
                            <td><?= \Pterodactyl\Core\View::e($a['notes'] ?? '-') ?></td>
                            <td>
                                <?php if ($isPrimary): ?>
                                    <span class="badge badge-success">Primary</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Secondary</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <?php if (!$isPrimary): ?>
                                    <button class="btn btn-secondary btn-sm" onclick="setPrimaryAllocation('<?= $a['id'] ?>')">Set Primary</button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteAllocation('<?= $a['id'] ?>')">Remove</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
async function assignAllocation() {
    try {
        await App.request('/api/client/servers/<?= $server['uuid'] ?>/network/allocations', { method: 'POST' });
        App.toast('New allocation assigned.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {}
}

async function setPrimaryAllocation(id) {
    try {
        await App.request(`/api/client/servers/<?= $server['uuid'] ?>/network/allocations/${id}/primary`, { method: 'POST' });
        App.toast('Primary allocation updated.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {}
}

async function deleteAllocation(id) {
    if (!confirm('Are you sure you want to remove this allocation?')) return;
    try {
        await App.request(`/api/client/servers/<?= $server['uuid'] ?>/network/allocations/${id}`, { method: 'DELETE' });
        App.toast('Allocation removed.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {}
}
</script>
