<?php $this->extend('layouts.server'); ?>

<div class="page-header" style="margin-bottom: 16px;">
    <div>
        <h1 class="page-title">Network Allocations</h1>
        <p class="page-subtitle">Configure IP and port allocations assigned to your server</p>
    </div>
    <div style="display: flex; align-items: center; gap: 10px;">
        <?php if (!empty($allocationLimit) && $allocationLimit > 0): ?>
            <span class="badge <?= $currentAllocCount >= $allocationLimit ? 'badge-danger' : 'badge-secondary' ?>" style="font-size: 12px; padding: 6px 12px;">
                Allocations: <?= (int)$currentAllocCount ?> / <?= (int)$allocationLimit ?>
            </span>
        <?php endif; ?>
        <?php if (\Pterodactyl\Security\Gate::allows($user, $server, 'allocation.create')): ?>
            <button class="btn btn-primary btn-sm" onclick="assignAllocation()">Assign Allocation</button>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($allocationLimit) && $allocationLimit > 0 && $currentAllocCount >= $allocationLimit): ?>
    <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); color: #fbbf24; padding: 12px 16px; border-radius: 8px; font-size: 13.5px; display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
        <span style="font-size: 18px;">⚠️</span>
        <div>
            <strong>Allocation limit reached:</strong> The server has reached the maximum allowed number of assigned ports (<?= (int)$currentAllocCount ?> of <?= (int)$allocationLimit ?>). You cannot create additional allocations.
        </div>
    </div>
<?php elseif (isset($freeNodeAllocCount) && $freeNodeAllocCount === 0): ?>
    <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; padding: 12px 16px; border-radius: 8px; font-size: 13.5px; display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
        <span style="font-size: 18px;">🚫</span>
        <div>
            <strong>No ports available:</strong> There are no more free allocations/ports available on this node. Contact an administrator to allocate more ports.
        </div>
    </div>
<?php endif; ?>

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
                    <?php if (empty($allocations)): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">No allocations found for this server.</td></tr>
                    <?php else: ?>
                        <?php foreach ($allocations as $a): ?>
                            <?php $isPrimary = ((int)$a['id']) === ((int)$server['allocation_id']); ?>
                            <tr>
                                <td style="font-family: var(--font-mono); font-weight: 600; color: #fff;">
                                    <span onclick="App.copyToClipboard('<?= \Pterodactyl\Core\View::e($a['ip']) ?>', this)" data-tooltip="Click to copy IP" style="cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                                        <?= \Pterodactyl\Core\View::e($a['ip']) ?>
                                        <span class="server-address-icon" style="display: inline-flex; align-items: center; opacity: 0.6;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></span>
                                    </span>
                                </td>
                                <td style="font-family: var(--font-mono); color: #ffffff; font-weight: 600;">
                                    <span onclick="App.copyToClipboard('<?= \Pterodactyl\Core\View::e(($a['ip_alias'] ?: $a['ip']) . ':' . $a['port']) ?>', this)" data-tooltip="Copy IP:Port" style="cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                                        <?= (int)$a['port'] ?>
                                        <span class="server-address-icon" style="display: inline-flex; align-items: center; opacity: 0.6;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></span>
                                    </span>
                                </td>
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
                                        <?php if (\Pterodactyl\Security\Gate::allows($user, $server, 'allocation.update')): ?>
                                            <button class="btn btn-secondary btn-sm" onclick="setPrimaryAllocation('<?= $a['id'] ?>')">Set Primary</button>
                                        <?php endif; ?>
                                        <?php if (\Pterodactyl\Security\Gate::allows($user, $server, 'allocation.delete')): ?>
                                            <button class="btn btn-danger btn-sm" onclick="deleteAllocation('<?= $a['id'] ?>')">Remove</button>
                                        <?php endif; ?>
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

<script>
async function assignAllocation() {
    try {
        await App.request('/api/client/servers/<?= $server['uuid'] ?>/network/allocations', { method: 'POST' });
        App.toast('New allocation assigned.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {
        const errorMsg = (e.response && (e.response.error || (e.response.errors && e.response.errors[0]?.detail))) || e.message || 'Failed to create allocation.';
        App.toast(errorMsg, 'danger', 5000);
    }
}

async function setPrimaryAllocation(id) {
    try {
        await App.request(`/api/client/servers/<?= $server['uuid'] ?>/network/allocations/${id}/primary`, { method: 'POST' });
        App.toast('Primary allocation updated.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {
        const errorMsg = (e.response && (e.response.error || (e.response.errors && e.response.errors[0]?.detail))) || e.message || 'Could not update primary allocation.';
        App.toast(errorMsg, 'danger', 5000);
    }
}

async function deleteAllocation(id) {
    if (!await App.confirm('Are you sure you want to remove this allocation?')) return;
    try {
        await App.request(`/api/client/servers/<?= $server['uuid'] ?>/network/allocations/${id}`, { method: 'DELETE' });
        App.toast('Allocation removed.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {
        const errorMsg = (e.response && (e.response.error || (e.response.errors && e.response.errors[0]?.detail))) || e.message || 'Could not delete allocation.';
        App.toast(errorMsg, 'danger', 5000);
    }
}
</script>
