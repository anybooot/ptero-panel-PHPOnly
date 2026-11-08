<?php $this->extend('layouts.server'); ?>

<div class="page-header" style="margin-bottom: 16px;">
    <div>
        <h1 class="page-title">Backups</h1>
        <p class="page-subtitle">Create and restore snapshots of your server data</p>
    </div>
    <div>
        <button class="btn btn-primary btn-sm" onclick="App.openModal('modal-create-backup')">Create Backup</button>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Backup Name</th>
                        <th>Size</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($backups)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">No backups have been created for this server.</td></tr>
                    <?php else: ?>
                        <?php foreach ($backups as $b): ?>
                            <?php
                                $bytes = (int)$b['bytes'];
                                $size = $bytes > 0 ? round($bytes / 1024 / 1024, 2) . ' MiB' : '-';
                                $isSuccess = !empty($b['is_successful']);
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: #fff;"><?= \Pterodactyl\Core\View::e($b['name']) ?></div>
                                    <div style="font-size: 11px; font-family: var(--font-mono); color: var(--text-muted);"><?= \Pterodactyl\Core\View::e($b['uuid']) ?></div>
                                </td>
                                <td><?= $size ?></td>
                                <td>
                                    <?php if ($isSuccess): ?>
                                        <span class="badge badge-success">Completed</span>
                                    <?php elseif (!empty($b['completed_at'])): ?>
                                        <span class="badge badge-danger">Failed</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">In Progress</span>
                                    <?php endif; ?>

                                    <?php if (!empty($b['is_locked'])): ?>
                                        <span class="badge badge-secondary" style="margin-left: 4px;">🔒 Locked</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: var(--text-secondary);"><?= date('M j, Y H:i', strtotime($b['created_at'])) ?></td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <?php if ($isSuccess): ?>
                                        <button class="btn btn-secondary btn-sm" onclick="downloadBackup('<?= $b['uuid'] ?>')">Download</button>
                                        <button class="btn btn-secondary btn-sm" onclick="restoreBackup('<?= $b['uuid'] ?>')">Restore</button>
                                    <?php endif; ?>
                                    <button class="btn btn-secondary btn-sm" onclick="toggleLock('<?= $b['uuid'] ?>')"><?= !empty($b['is_locked']) ? 'Unlock' : 'Lock' ?></button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteBackup('<?= $b['uuid'] ?>')">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Backup Modal -->
<div class="modal-backdrop" id="modal-create-backup">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Create Server Backup</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form id="create-backup-form">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="backup-name">Backup Name</label>
                    <input type="text" id="backup-name" class="form-control" placeholder="Leave blank for automatic name">
                </div>
                <div class="form-group">
                    <label class="form-label" for="backup-ignored">Ignored Files &amp; Directories (Optional, one per line)</label>
                    <textarea id="backup-ignored" class="form-control" placeholder=".git&#10;cache/&#10;logs/"></textarea>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; color: #fff; font-size: 13px;">
                        <input type="checkbox" id="backup-locked"> Lock this backup to prevent automatic deletion
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Start Backup</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('create-backup-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('backup-name').value;
    const is_locked = document.getElementById('backup-locked').checked;
    const ignoredText = document.getElementById('backup-ignored').value;
    const ignored = ignoredText.split('\n').map(s => s.trim()).filter(s => s.length > 0);

    try {
        await App.request('/api/client/servers/<?= $server['uuid'] ?>/backups', {
            method: 'POST',
            body: { name, is_locked, ignored }
        });
        App.toast('Backup generation started.', 'success');
        setTimeout(() => window.location.reload(), 800);
    } catch (e) {}
});

async function downloadBackup(uuid) {
    try {
        const res = await App.request(`/api/client/servers/<?= $server['uuid'] ?>/backups/${uuid}/download`);
        if (res?.attributes?.url) {
            window.location.href = res.attributes.url;
        }
    } catch (e) {}
}

async function toggleLock(uuid) {
    try {
        await App.request(`/api/client/servers/<?= $server['uuid'] ?>/backups/${uuid}/lock`, { method: 'POST' });
        App.toast('Lock state updated.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {}
}

async function restoreBackup(uuid) {
    if (!confirm('Are you sure you want to restore this backup? All current server files may be overwritten.')) return;
    try {
        await App.request(`/api/client/servers/<?= $server['uuid'] ?>/backups/${uuid}/restore`, {
            method: 'POST',
            body: { truncate: false }
        });
        App.toast('Restoration started on Wings.', 'success');
        setTimeout(() => window.location.reload(), 1000);
    } catch (e) {}
}

async function deleteBackup(uuid) {
    if (!confirm('Are you sure you want to permanently delete this backup?')) return;
    try {
        await App.request(`/api/client/servers/<?= $server['uuid'] ?>/backups/${uuid}`, { method: 'DELETE' });
        App.toast('Backup deleted.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {}
}
</script>
