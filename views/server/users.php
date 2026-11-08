<?php $this->extend('layouts.server'); ?>

<div class="page-header" style="margin-bottom: 16px;">
    <div>
        <h1 class="page-title">Users &amp; Permissions</h1>
        <p class="page-subtitle">Manage subusers and configure granular permissions for server access</p>
    </div>
    <div>
        <button class="btn btn-primary btn-sm" onclick="App.openModal('modal-add-subuser')">Add Subuser</button>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>2FA Enabled</th>
                        <th>Assigned Permissions</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subusers)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">No subusers are currently configured for this server.</td></tr>
                    <?php else: ?>
                        <?php foreach ($subusers as $su): ?>
                            <tr>
                                <td style="font-weight: 600; color: #fff;"><?= \Pterodactyl\Core\View::e($su['username']) ?></td>
                                <td><?= \Pterodactyl\Core\View::e($su['email']) ?></td>
                                <td>
                                    <span class="badge <?= !empty($su['totp_authenticated']) ? 'badge-success' : 'badge-secondary' ?>">
                                        <?= !empty($su['totp_authenticated']) ? 'YES' : 'NO' ?>
                                    </span>
                                </td>
                                <td style="font-size: 12px; color: #93c5fd;">
                                    <?= count($su['permissions']) ?> permission(s) granted
                                </td>
                                <td style="text-align: right;">
                                    <button class="btn btn-danger btn-sm" onclick="deleteSubuser('<?= $su['id'] ?>')">Remove</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Subuser Modal -->
<div class="modal-backdrop" id="modal-add-subuser">
    <div class="modal-dialog" style="max-width: 640px;">
        <div class="modal-header">
            <h3 class="modal-title">Invite Subuser</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form id="add-subuser-form">
            <div class="modal-body" style="max-height: 65vh; overflow-y: auto;">
                <div class="form-group">
                    <label class="form-label" for="subuser-email">User Email Address</label>
                    <input type="email" id="subuser-email" class="form-control" required placeholder="user@example.com">
                </div>

                <div style="margin-top: 16px;">
                    <label class="form-label">Permissions</label>
                    
                    <div style="background: rgba(0,0,0,0.2); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 12px;">
                        <strong style="color: #fff; font-size: 13px;">Server Control</strong>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 8px;">
                            <label><input type="checkbox" name="perms" value="control.console"> Access Console</label>
                            <label><input type="checkbox" name="perms" value="control.start"> Start Server</label>
                            <label><input type="checkbox" name="perms" value="control.stop"> Stop Server</label>
                            <label><input type="checkbox" name="perms" value="control.restart"> Restart Server</label>
                        </div>
                    </div>

                    <div style="background: rgba(0,0,0,0.2); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 12px;">
                        <strong style="color: #fff; font-size: 13px;">File Management</strong>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 8px;">
                            <label><input type="checkbox" name="perms" value="file.read"> View Files</label>
                            <label><input type="checkbox" name="perms" value="file.read-content"> Read File Content</label>
                            <label><input type="checkbox" name="perms" value="file.create"> Create &amp; Upload Files</label>
                            <label><input type="checkbox" name="perms" value="file.update"> Edit Files</label>
                            <label><input type="checkbox" name="perms" value="file.delete"> Delete Files</label>
                            <label><input type="checkbox" name="perms" value="file.archive"> Compress / Decompress</label>
                            <label><input type="checkbox" name="perms" value="file.sftp"> Connect via SFTP</label>
                        </div>
                    </div>

                    <div style="background: rgba(0,0,0,0.2); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 12px;">
                        <strong style="color: #fff; font-size: 13px;">Backups &amp; Databases</strong>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 8px;">
                            <label><input type="checkbox" name="perms" value="backup.create"> Create Backup</label>
                            <label><input type="checkbox" name="perms" value="backup.download"> Download Backup</label>
                            <label><input type="checkbox" name="perms" value="backup.restore"> Restore Backup</label>
                            <label><input type="checkbox" name="perms" value="database.create"> Create Database</label>
                            <label><input type="checkbox" name="perms" value="database.view_password"> View DB Password</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Invite User</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('add-subuser-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('subuser-email').value;
    const permissions = Array.from(document.querySelectorAll('input[name="perms"]:checked')).map(cb => cb.value);

    try {
        await App.request('/api/client/servers/<?= $server['uuid'] ?>/users', {
            method: 'POST',
            body: { email, permissions }
        });
        App.toast('Subuser added.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {}
});

async function deleteSubuser(id) {
    if (!confirm('Are you sure you want to remove this subuser from the server?')) return;
    try {
        await App.request(`/api/client/servers/<?= $server['uuid'] ?>/users/${id}`, { method: 'DELETE' });
        App.toast('Subuser removed.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {}
}
</script>
