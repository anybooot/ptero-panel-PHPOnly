<?php $this->extend('layouts.main'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Account Overview</h1>
        <p class="page-subtitle">Manage your credentials, email, and two-factor authentication security</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/account" class="btn btn-primary btn-sm">Overview</a>
        <a href="/account/api" class="btn btn-secondary btn-sm">API Credentials</a>
        <a href="/account/ssh" class="btn btn-secondary btn-sm">SSH Keys</a>
        <a href="/account/activity" class="btn btn-secondary btn-sm">Activity</a>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- Update Email Card -->
    <div class="card">
        <div class="card-header">Update Email Address</div>
        <form action="/account/email" method="POST">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= \Pterodactyl\Core\View::e($user['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="email_password">Current Password</label>
                    <input type="password" id="email_password" name="password" class="form-control" required placeholder="Enter password to confirm">
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Update Email</button>
            </div>
        </form>
    </div>

    <!-- Update Password Card -->
    <div class="card">
        <div class="card-header">Change Password</div>
        <form action="/account/password" method="POST">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="new_password">New Password</label>
                    <input type="password" id="new_password" name="password" class="form-control" required minlength="8">
                </div>
                <div class="form-group">
                    <label class="form-label" for="password_confirmation">Confirm New Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required minlength="8">
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

<!-- Two Factor Card -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">Two-Factor Authentication</div>
    <div class="card-body">
        <?php if (!empty($user['totp_authenticated'])): ?>
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <span class="badge badge-success" style="font-size: 13px;">ENABLED</span>
                    <p style="color: var(--text-secondary); margin-top: 6px; font-size: 13px;">Your account is currently protected with two-factor authentication.</p>
                </div>
                <button class="btn btn-danger" onclick="App.openModal('modal-disable-2fa')">Disable 2FA</button>
            </div>
        <?php else: ?>
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <span class="badge badge-secondary" style="font-size: 13px;">DISABLED</span>
                    <p style="color: var(--text-secondary); margin-top: 6px; font-size: 13px;">Add an extra layer of security to your account using Google Authenticator or another TOTP app.</p>
                </div>
                <button class="btn btn-primary" onclick="initiate2fa()">Enable 2FA</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Enable 2FA Modal -->
<div class="modal-backdrop" id="modal-enable-2fa">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Setup Two-Factor Authentication</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <div class="modal-body">
            <p style="color: var(--text-secondary); margin-bottom: 16px;">Scan the QR code below or manually enter the key into your authenticator app, then enter the 6-digit verification code.</p>
            <div style="text-align: center; margin-bottom: 16px;">
                <div id="2fa-secret-display" style="font-family: var(--font-mono); font-size: 18px; font-weight: bold; color: #60a5fa; letter-spacing: 0.1em; background: rgba(0,0,0,0.3); padding: 10px; border-radius: var(--radius-sm);"></div>
            </div>
            <div class="form-group">
                <label class="form-label" for="2fa-verify-code">6-Digit Code</label>
                <input type="text" id="2fa-verify-code" class="form-control" maxlength="6" style="text-align: center; font-size: 18px; letter-spacing: 0.2em; font-family: var(--font-mono);">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-modal-close>Cancel</button>
            <button class="btn btn-primary" onclick="submit2fa()">Verify & Enable</button>
        </div>
    </div>
</div>

<!-- Disable 2FA Modal -->
<div class="modal-backdrop" id="modal-disable-2fa">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Disable Two-Factor Authentication</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <div class="modal-body">
            <p style="color: var(--text-secondary); margin-bottom: 16px;">Enter your current password to confirm disabling two-factor authentication.</p>
            <div class="form-group">
                <label class="form-label" for="disable-2fa-password">Account Password</label>
                <input type="password" id="disable-2fa-password" class="form-control" required>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-modal-close>Cancel</button>
            <button class="btn btn-danger" onclick="disable2fa()">Disable 2FA</button>
        </div>
    </div>
</div>

<script>
async function initiate2fa() {
    try {
        const res = await App.request('/account/two-factor');
        document.getElementById('2fa-secret-display').textContent = res.data.secret;
        App.openModal('modal-enable-2fa');
    } catch (e) {}
}

async function submit2fa() {
    const code = document.getElementById('2fa-verify-code').value.trim();
    if (!code) return;
    try {
        await App.request('/account/two-factor', {
            method: 'POST',
            body: { code }
        });
        App.toast('Two-factor authentication enabled successfully.', 'success');
        setTimeout(() => window.location.reload(), 1000);
    } catch (e) {}
}

async function disable2fa() {
    const password = document.getElementById('disable-2fa-password').value;
    if (!password) return;
    try {
        await App.request('/account/two-factor/disable', {
            method: 'POST',
            body: { password }
        });
        App.toast('Two-factor authentication disabled.', 'success');
        setTimeout(() => window.location.reload(), 1000);
    } catch (e) {}
}
</script>
