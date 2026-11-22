<?php $this->extend('layouts.server'); ?>

<?php
$canSftp = \Pterodactyl\Security\Gate::allows($user, $server, 'file.sftp') || \Pterodactyl\Security\Gate::isRootAdmin($user) || ((int)$user['id'] === (int)$server['owner_id']);
$canRename = \Pterodactyl\Security\Gate::allows($user, $server, 'settings.rename') || \Pterodactyl\Security\Gate::isRootAdmin($user) || ((int)$user['id'] === (int)$server['owner_id']);
$canReinstall = \Pterodactyl\Security\Gate::allows($user, $server, 'settings.reinstall') || \Pterodactyl\Security\Gate::isRootAdmin($user) || ((int)$user['id'] === (int)$server['owner_id']);
?>

<div class="page-header" style="margin-bottom: 16px;">
    <div>
        <h1 class="page-title">Server Settings</h1>
        <p class="page-subtitle">SFTP details, server metadata, and container options</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: <?= ($canSftp && $canRename) ? '1fr 1fr' : '1fr' ?>; gap: 20px;">
    <!-- SFTP Details Card -->
    <?php if ($canSftp): ?>
        <div class="card">
            <div class="card-header" style="display: flex; align-items: center; justify-content: space-between;">
                <span>SFTP Details</span>
                <a href="sftp://<?= rawurlencode($sftpUsername) ?>@<?= \Pterodactyl\Core\View::e($node['fqdn']) ?>:<?= (int)$sftpPort ?>" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span>🚀</span> Open SFTP
                </a>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Server Address</label>
                    <div style="display: flex; gap: 6px;">
                        <input type="text" id="sftp-address-val" class="form-control" style="font-family: var(--font-mono);" value="sftp://<?= \Pterodactyl\Core\View::e($node['fqdn'] . ':' . $sftpPort) ?>" readonly>
                        <button type="button" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; justify-content: center; padding: 0 10px;" onclick="App.copyToClipboard(document.getElementById('sftp-address-val').value, this);" data-tooltip="Copy Address"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <div style="display: flex; gap: 6px;">
                        <input type="text" id="sftp-user-val" class="form-control" style="font-family: var(--font-mono);" value="<?= \Pterodactyl\Core\View::e($sftpUsername) ?>" readonly>
                        <button type="button" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; justify-content: center; padding: 0 10px;" onclick="App.copyToClipboard(document.getElementById('sftp-user-val').value, this);" data-tooltip="Copy Username"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button>
                    </div>
                </div>
                <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 0;">
                    Connect using FileZilla, WinSCP, or your preferred SFTP client using your panel account password or registered SSH key.
                </p>
            </div>
            <div class="card-footer" style="background: rgba(0,0,0,0.15);">
                <a href="sftp://<?= rawurlencode($sftpUsername) ?>@<?= \Pterodactyl\Core\View::e($node['fqdn']) ?>:<?= (int)$sftpPort ?>" class="btn btn-primary btn-block" style="display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%;">
                    <span>📂</span> Launch SFTP Client
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Rename Server Card -->
    <?php if ($canRename): ?>
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
    <?php endif; ?>
</div>

<!-- Reinstall Danger Zone Card -->
<?php if ($canReinstall): ?>
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
<?php endif; ?>

<script>
const renameForm = document.getElementById('rename-server-form');
if (renameForm) {
    renameForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const name = document.getElementById('srv-name').value;
        const description = document.getElementById('srv-desc').value;

        try {
            await App.request('/api/client/servers/<?= $server['uuid'] ?>/settings/rename', {
                method: 'POST',
                body: { name, description }
            });
            App.toast('Server renamed successfully.', 'success');
        } catch (e) {
            const msg = (e.response && (e.response.error || (e.response.errors && e.response.errors[0]?.detail))) || e.message || 'Failed to rename server.';
            App.toast(msg, 'danger');
        }
    });
}

async function reinstallServer() {
    if (!await App.confirm('Are you sure you want to reinstall this server? This will stop the server and run the installation script again.')) return;
    try {
        await App.request('/api/client/servers/<?= $server['uuid'] ?>/settings/reinstall', { method: 'POST' });
        App.toast('Reinstallation initiated.', 'success');
        setTimeout(() => window.location.href = '/server/<?= $server['uuidShort'] ?>', 800);
    } catch (e) {
        const msg = (e.response && (e.response.error || (e.response.errors && e.response.errors[0]?.detail))) || e.message || 'Failed to reinstall server.';
        App.toast(msg, 'danger');
    }
}
</script>
