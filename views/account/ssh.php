<?php $this->extend('layouts.main'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">SSH Keys</h1>
        <p class="page-subtitle">Manage public SSH keys used for authenticating to server SFTP instances</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/account" class="btn btn-secondary btn-sm">Overview</a>
        <a href="/account/api" class="btn btn-secondary btn-sm">API Credentials</a>
        <a href="/account/ssh" class="btn btn-primary btn-sm">SSH Keys</a>
        <a href="/account/activity" class="btn btn-secondary btn-sm">Activity</a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span>SSH Public Keys</span>
        <button class="btn btn-primary btn-sm" onclick="App.openModal('modal-add-ssh-key')">Add SSH Key</button>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Fingerprint (SHA256)</th>
                        <th>Added</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($keys)): ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 24px;">No SSH keys have been added to your account.</td></tr>
                    <?php else: ?>
                        <?php foreach ($keys as $k): ?>
                            <tr>
                                <td style="font-weight: 600; color: #fff;"><?= \Pterodactyl\Core\View::e($k['name']) ?></td>
                                <td style="font-family: var(--font-mono); font-size: 12px; color: #93c5fd;"><?= \Pterodactyl\Core\View::e($k['fingerprint']) ?></td>
                                <td><?= date('M j, Y', strtotime($k['created_at'])) ?></td>
                                <td style="text-align: right;">
                                    <button class="btn btn-danger btn-sm" onclick="deleteSshKey('<?= $k['fingerprint'] ?>')">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add SSH Key Modal -->
<div class="modal-backdrop" id="modal-add-ssh-key">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Add SSH Public Key</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form id="add-ssh-key-form">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="ssh-name">Key Name</label>
                    <input type="text" id="ssh-name" class="form-control" required placeholder="e.g. Work Laptop">
                </div>
                <div class="form-group">
                    <label class="form-label" for="ssh-public-key">Public Key Material</label>
                    <textarea id="ssh-public-key" class="form-control" style="font-family: var(--font-mono); font-size: 12px; height: 110px;" required placeholder="ssh-rsa AAAA..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Key</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('add-ssh-key-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('ssh-name').value;
    const public_key = document.getElementById('ssh-public-key').value;

    try {
        await App.request('/account/ssh', {
            method: 'POST',
            body: { name, public_key }
        });
        App.toast('SSH key added successfully.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {}
});

async function deleteSshKey(fingerprint) {
    if (!confirm('Are you sure you want to delete this SSH key?')) return;
    try {
        await App.request('/account/ssh/delete', {
            method: 'POST',
            body: { fingerprint }
        });
        App.toast('SSH key removed.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {}
}
</script>
