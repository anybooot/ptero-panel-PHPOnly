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
    <div class="modal-dialog" style="max-width: 680px;">
        <div class="modal-header">
            <h3 class="modal-title">Invite Subuser</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form id="add-subuser-form">
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding-right: 12px;">
                <div class="form-group">
                    <label class="form-label" for="subuser-email">User Email Address</label>
                    <input type="email" id="subuser-email" class="form-control" required placeholder="user@example.com">
                </div>

                <div style="margin-top: 20px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <label class="form-label" style="margin-bottom: 0; font-size: 14px; font-weight: 700;">Configured Permissions</label>
                        <button type="button" id="btn-select-all-global" class="btn btn-secondary btn-sm" style="padding: 3px 10px; font-size: 12px;">Select All Permissions</button>
                    </div>

                    <!-- 1. Server Control -->
                    <div class="perm-section-box" style="background: rgba(0,0,0,0.22); border: 1px solid var(--border-color); padding: 12px 14px; border-radius: 8px; margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <strong style="color: #eceff4; font-size: 13px;">Server Control</strong>
                            <button type="button" class="btn btn-secondary btn-sm section-toggle-btn" style="padding: 2px 8px; font-size: 11px;">Select All</button>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <label><input type="checkbox" name="perms" value="control.console"> Access Console</label>
                            <label><input type="checkbox" name="perms" value="control.start"> Start Server</label>
                            <label><input type="checkbox" name="perms" value="control.stop"> Stop Server</label>
                            <label><input type="checkbox" name="perms" value="control.restart"> Restart Server</label>
                        </div>
                    </div>

                    <!-- 2. Subuser Management -->
                    <div class="perm-section-box" style="background: rgba(0,0,0,0.22); border: 1px solid var(--border-color); padding: 12px 14px; border-radius: 8px; margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <strong style="color: #eceff4; font-size: 13px;">Subuser Management</strong>
                            <button type="button" class="btn btn-secondary btn-sm section-toggle-btn" style="padding: 2px 8px; font-size: 11px;">Select All</button>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <label><input type="checkbox" name="perms" value="user.create"> Create Subuser</label>
                            <label><input type="checkbox" name="perms" value="user.read"> View Subusers</label>
                            <label><input type="checkbox" name="perms" value="user.update"> Edit Subuser Permissions</label>
                            <label><input type="checkbox" name="perms" value="user.delete"> Remove Subuser</label>
                        </div>
                    </div>

                    <!-- 3. File Management -->
                    <div class="perm-section-box" style="background: rgba(0,0,0,0.22); border: 1px solid var(--border-color); padding: 12px 14px; border-radius: 8px; margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <strong style="color: #eceff4; font-size: 13px;">File Management</strong>
                            <button type="button" class="btn btn-secondary btn-sm section-toggle-btn" style="padding: 2px 8px; font-size: 11px;">Select All</button>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <label><input type="checkbox" name="perms" value="file.create"> Create &amp; Upload Files</label>
                            <label><input type="checkbox" name="perms" value="file.read"> View Files &amp; Folders</label>
                            <label><input type="checkbox" name="perms" value="file.read-content"> Read File Content</label>
                            <label><input type="checkbox" name="perms" value="file.update"> Edit Files</label>
                            <label><input type="checkbox" name="perms" value="file.delete"> Delete Files</label>
                            <label><input type="checkbox" name="perms" value="file.archive"> Compress / Decompress</label>
                            <label><input type="checkbox" name="perms" value="file.sftp"> Connect via SFTP</label>
                        </div>
                    </div>

                    <!-- 4. Backups -->
                    <div class="perm-section-box" style="background: rgba(0,0,0,0.22); border: 1px solid var(--border-color); padding: 12px 14px; border-radius: 8px; margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <strong style="color: #eceff4; font-size: 13px;">Backups</strong>
                            <button type="button" class="btn btn-secondary btn-sm section-toggle-btn" style="padding: 2px 8px; font-size: 11px;">Select All</button>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <label><input type="checkbox" name="perms" value="backup.create"> Create Backup</label>
                            <label><input type="checkbox" name="perms" value="backup.read"> View Backups</label>
                            <label><input type="checkbox" name="perms" value="backup.delete"> Delete Backup</label>
                            <label><input type="checkbox" name="perms" value="backup.download"> Download Backup</label>
                            <label><input type="checkbox" name="perms" value="backup.restore"> Restore Backup</label>
                        </div>
                    </div>

                    <!-- 5. Databases -->
                    <div class="perm-section-box" style="background: rgba(0,0,0,0.22); border: 1px solid var(--border-color); padding: 12px 14px; border-radius: 8px; margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <strong style="color: #eceff4; font-size: 13px;">Databases</strong>
                            <button type="button" class="btn btn-secondary btn-sm section-toggle-btn" style="padding: 2px 8px; font-size: 11px;">Select All</button>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <label><input type="checkbox" name="perms" value="database.create"> Create Database</label>
                            <label><input type="checkbox" name="perms" value="database.read"> View Databases</label>
                            <label><input type="checkbox" name="perms" value="database.update"> Rotate DB Password</label>
                            <label><input type="checkbox" name="perms" value="database.delete"> Delete Database</label>
                            <label><input type="checkbox" name="perms" value="database.view_password"> View DB Password</label>
                        </div>
                    </div>

                    <!-- 6. Schedules -->
                    <div class="perm-section-box" style="background: rgba(0,0,0,0.22); border: 1px solid var(--border-color); padding: 12px 14px; border-radius: 8px; margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <strong style="color: #eceff4; font-size: 13px;">Schedules</strong>
                            <button type="button" class="btn btn-secondary btn-sm section-toggle-btn" style="padding: 2px 8px; font-size: 11px;">Select All</button>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <label><input type="checkbox" name="perms" value="schedule.create"> Create Schedule</label>
                            <label><input type="checkbox" name="perms" value="schedule.read"> View Schedules</label>
                            <label><input type="checkbox" name="perms" value="schedule.update"> Edit Schedule</label>
                            <label><input type="checkbox" name="perms" value="schedule.delete"> Delete Schedule</label>
                        </div>
                    </div>

                    <!-- 7. Network / Allocations -->
                    <div class="perm-section-box" style="background: rgba(0,0,0,0.22); border: 1px solid var(--border-color); padding: 12px 14px; border-radius: 8px; margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <strong style="color: #eceff4; font-size: 13px;">Network &amp; Allocations</strong>
                            <button type="button" class="btn btn-secondary btn-sm section-toggle-btn" style="padding: 2px 8px; font-size: 11px;">Select All</button>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <label><input type="checkbox" name="perms" value="allocation.read"> View Allocations</label>
                            <label><input type="checkbox" name="perms" value="allocation.create"> Assign New Allocation</label>
                            <label><input type="checkbox" name="perms" value="allocation.update"> Set Primary / Edit Notes</label>
                            <label><input type="checkbox" name="perms" value="allocation.delete"> Delete Allocation</label>
                        </div>
                    </div>

                    <!-- 8. Startup & Settings & Activity -->
                    <div class="perm-section-box" style="background: rgba(0,0,0,0.22); border: 1px solid var(--border-color); padding: 12px 14px; border-radius: 8px; margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <strong style="color: #eceff4; font-size: 13px;">Settings &amp; Configuration</strong>
                            <button type="button" class="btn btn-secondary btn-sm section-toggle-btn" style="padding: 2px 8px; font-size: 11px;">Select All</button>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <label><input type="checkbox" name="perms" value="startup.read"> View Startup Variables</label>
                            <label><input type="checkbox" name="perms" value="startup.update"> Modify Startup Variables</label>
                            <label><input type="checkbox" name="perms" value="settings.rename"> Rename Server</label>
                            <label><input type="checkbox" name="perms" value="settings.reinstall"> Reinstall Server</label>
                            <label><input type="checkbox" name="perms" value="activity.read"> View Server Activity</label>
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
// Global Select All / Deselect All
const globalSelectBtn = document.getElementById('btn-select-all-global');
if (globalSelectBtn) {
    globalSelectBtn.addEventListener('click', () => {
        const allBoxes = document.querySelectorAll('input[name="perms"]');
        const anyUnchecked = Array.from(allBoxes).some(b => !b.checked);
        allBoxes.forEach(b => b.checked = anyUnchecked);
        globalSelectBtn.textContent = anyUnchecked ? 'Deselect All Permissions' : 'Select All Permissions';
        
        // Update all section toggle button texts
        document.querySelectorAll('.section-toggle-btn').forEach(btn => {
            btn.textContent = anyUnchecked ? 'Deselect All' : 'Select All';
        });
    });
}

// Section-level Select All
document.querySelectorAll('.section-toggle-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const sectionBox = btn.closest('.perm-section-box');
        if (!sectionBox) return;
        const boxes = sectionBox.querySelectorAll('input[name="perms"]');
        const anyUnchecked = Array.from(boxes).some(b => !b.checked);
        boxes.forEach(b => b.checked = anyUnchecked);
        btn.textContent = anyUnchecked ? 'Deselect All' : 'Select All';
    });
});

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
    } catch (e) {
        App.toast('Failed to add subuser: ' + e.message, 'danger');
    }
});

async function deleteSubuser(id) {
    if (!confirm('Are you sure you want to remove this subuser from the server?')) return;
    try {
        await App.request(`/api/client/servers/<?= $server['uuid'] ?>/users/${id}`, { method: 'DELETE' });
        App.toast('Subuser removed.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {
        App.toast('Failed to remove subuser: ' + e.message, 'danger');
    }
}
</script>
