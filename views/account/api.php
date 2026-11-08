<?php $this->extend('layouts.main'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Account API Credentials</h1>
        <p class="page-subtitle">Manage API keys that allow external programs to access your account</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/account" class="btn btn-secondary btn-sm">Overview</a>
        <a href="/account/api" class="btn btn-primary btn-sm">API Credentials</a>
        <a href="/account/ssh" class="btn btn-secondary btn-sm">SSH Keys</a>
        <a href="/account/activity" class="btn btn-secondary btn-sm">Activity</a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span>Active API Keys</span>
        <button class="btn btn-primary btn-sm" onclick="App.openModal('modal-create-api-key')">Create API Key</button>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Identifier</th>
                        <th>Description</th>
                        <th>Allowed IPs</th>
                        <th>Last Used</th>
                        <th>Created</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($keys)): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">No API credentials exist for this account.</td></tr>
                    <?php else: ?>
                        <?php foreach ($keys as $k): ?>
                            <tr>
                                <td style="font-family: var(--font-mono); font-weight: 600; color: #60a5fa;"><?= \Pterodactyl\Core\View::e($k['identifier']) ?></td>
                                <td><?= \Pterodactyl\Core\View::e($k['memo']) ?></td>
                                <td style="font-family: var(--font-mono); font-size: 12px;"><?= $k['allowed_ips'] ? \Pterodactyl\Core\View::e(implode(', ', json_decode($k['allowed_ips'], true) ?: [])) : '<span style="color:var(--text-muted)">Any</span>' ?></td>
                                <td><?= $k['last_used_at'] ? date('M j, Y H:i', strtotime($k['last_used_at'])) : '<span style="color:var(--text-muted)">Never</span>' ?></td>
                                <td><?= date('M j, Y', strtotime($k['created_at'])) ?></td>
                                <td style="text-align: right;">
                                    <button class="btn btn-danger btn-sm" onclick="deleteApiKey('<?= $k['identifier'] ?>')">Revoke</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create API Key Modal -->
<div class="modal-backdrop" id="modal-create-api-key">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Create API Key</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <div class="modal-body">
            <div id="new-key-display" style="display: none; margin-bottom: 16px;">
                <div class="alert alert-success">
                    <div>
                        <strong>API Key Created!</strong> Please copy this token now. It will not be shown again.<br>
                        <input type="text" id="created-token-val" class="form-control" style="margin-top: 8px; font-family: var(--font-mono); font-weight: bold;" readonly>
                    </div>
                </div>
            </div>

            <form id="create-api-key-form">
                <div class="form-group">
                    <label class="form-label" for="api-memo">Description</label>
                    <input type="text" id="api-memo" class="form-control" required placeholder="e.g. Discord Bot, Backup Script">
                </div>
                <div class="form-group">
                    <label class="form-label" for="api-ips">Allowed IPs (Optional, one per line)</label>
                    <textarea id="api-ips" class="form-control" placeholder="Leave empty for any IP"></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
                    <button type="submit" class="btn btn-primary">Create Key</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('create-api-key-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const description = document.getElementById('api-memo').value;
    const allowed_ips = document.getElementById('api-ips').value;

    try {
        const res = await App.request('/account/api', {
            method: 'POST',
            body: { description, allowed_ips }
        });
        document.getElementById('created-token-val').value = res.secret_token;
        document.getElementById('new-key-display').style.display = 'block';
        document.getElementById('create-api-key-form').style.display = 'none';
    } catch (e) {}
});

async function deleteApiKey(identifier) {
    if (!confirm('Are you sure you want to revoke this API key? Any applications using it will immediately lose access.')) return;
    try {
        await App.request(`/account/api/${identifier}`, { method: 'DELETE' });
        App.toast('API key revoked.', 'success');
        setTimeout(() => window.location.reload(), 800);
    } catch (e) {}
}
</script>
