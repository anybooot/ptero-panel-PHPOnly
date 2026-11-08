<?php $this->extend('layouts.server'); ?>

<div class="page-header" style="margin-bottom: 16px;">
    <div>
        <h1 class="page-title">Server Settings</h1>
        <p class="page-subtitle">SFTP details, server metadata, and container options</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- SFTP Details Card -->
    <div class="card">
        <div class="card-header">SFTP Details</div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">Server Address</label>
                <input type="text" class="form-control" style="font-family: var(--font-mono);" value="sftp://<?= \Pterodactyl\Core\View::e($node['fqdn'] . ':' . $sftpPort) ?>" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" style="font-family: var(--font-mono);" value="<?= \Pterodactyl\Core\View::e($sftpUsername) ?>" readonly>
            </div>
            <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 0;">
                Connect using FileZilla, WinSCP, or your preferred SFTP client using your panel account password or registered SSH key.
            </p>
        </div>
    </div>

    <!-- Rename Server Card -->
    <div class="card">
        <div class="card-header">Rename Server</div>
        <form id="rename-server-form">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="srv-name">Server Name</label>
                    <input type="text" id="srv-name" class="form-control" value="<?= \Pterodactyl\Core\View::e($server['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="srv-desc">Server Description</label>
                    <input type="text" id="srv-desc" class="form-control" value="<?= \Pterodactyl\Core\View::e($server['description'] ?? '') ?>">
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Reinstall Danger Zone Card -->
<div class="card" style="border-color: rgba(239, 68, 68, 0.4); margin-top: 20px;">
    <div class="card-header" style="color: #f87171; background-color: rgba(239, 68, 68, 0.1);">Danger Zone</div>
    <div class="card-body" style="display: flex; align-items: center; justify-content: space-between;">
        <div>
            <strong style="color: #fff; font-size: 14px;">Reinstall Server</strong>
            <p style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">
                Re-runs the installation script for this server. Some files might be overwritten or deleted in the process.
            </p>
        </div>
        <button class="btn btn-danger" onclick="reinstallServer()">Reinstall Server</button>
    </div>
</div>

<script>
document.getElementById('rename-server-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('srv-name').value;
    const description = document.getElementById('srv-desc').value;

    try {
        await App.request('/api/client/servers/<?= $server['uuid'] ?>/settings/rename', {
            method: 'POST',
            body: { name, description }
        });
        App.toast('Server renamed successfully.', 'success');
    } catch (e) {}
});

async function reinstallServer() {
    if (!confirm('Are you sure you want to reinstall this server? This will stop the server and run the installation script again.')) return;
    try {
        await App.request('/api/client/servers/<?= $server['uuid'] ?>/settings/reinstall', { method: 'POST' });
        App.toast('Reinstallation initiated.', 'success');
        setTimeout(() => window.location.href = '/server/<?= $server['uuidShort'] ?>', 800);
    } catch (e) {}
}
</script>
