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
        <?php if (!empty($user['use_totp'])): ?>
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <span class="badge badge-success" style="font-size: 13px;">ENABLED</span>
                    <p style="color: var(--text-secondary); margin-top: 6px; font-size: 13px;">Your account is currently protected with two-factor authentication.</p>
                </div>
                <button type="button" class="btn btn-danger" onclick="App.openModal('modal-disable-2fa')">Disable 2FA</button>
            </div>
        <?php else: ?>
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <span class="badge badge-secondary" style="font-size: 13px;">DISABLED</span>
                    <p style="color: var(--text-secondary); margin-top: 6px; font-size: 13px;">Add an extra layer of security to your account using Google Authenticator or another TOTP app.</p>
                </div>
                <button type="button" id="btn-enable-2fa-trigger" class="btn btn-primary" onclick="initiate2fa()">Enable 2FA</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Enable 2FA Modal -->
<div class="modal-backdrop" id="modal-enable-2fa">
    <div class="modal-dialog" style="max-width: 440px;">
        <div class="modal-header">
            <h3 class="modal-title">Setup Two-Factor Authentication</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <div class="modal-body">
            <p style="color: var(--text-secondary); margin-bottom: 16px; font-size: 13.5px;">Scan the QR code below using your authenticator app (such as Google Authenticator), or enter the key manually.</p>
            
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; margin-bottom: 18px;">
                <div id="2fa-qr-code-wrapper" style="padding: 12px; background: #ffffff; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 6px 16px rgba(0,0,0,0.4); min-width: 174px; min-height: 174px;">
                    <div id="2fa-qr-code" style="display: flex; align-items: center; justify-content: center;">
                        <span style="color: #64748b; font-size: 12px;">Loading QR code...</span>
                    </div>
                </div>
                <div style="margin-top: 14px; font-size: 11.5px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Manual Secret Key</div>
                <div style="display: flex; align-items: center; gap: 8px; margin-top: 6px;">
                    <div id="2fa-secret-display" style="font-family: var(--font-mono); font-size: 14px; font-weight: bold; color: #eceff4; letter-spacing: 0.12em; background: rgba(0,0,0,0.35); padding: 6px 14px; border-radius: var(--radius-sm); border: 1px solid rgba(255,255,255,0.08); user-select: all;">--------</div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="App.copyToClipboard(document.getElementById('2fa-secret-display').textContent, this)" data-tooltip="Copy secret key">Copy</button>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="2fa-verify-code">6-Digit Verification Code</label>
                <input type="text" id="2fa-verify-code" class="form-control" maxlength="6" placeholder="000000" style="text-align: center; font-size: 20px; letter-spacing: 0.25em; font-family: var(--font-mono);" autocomplete="off">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-modal-close>Cancel</button>
            <button id="btn-submit-enable-2fa" class="btn btn-primary" onclick="submit2fa()">Verify & Enable</button>
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
    const btn = document.getElementById('btn-enable-2fa-trigger');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Loading...';
    }

    // Open modal immediately so user sees responsiveness
    App.openModal('modal-enable-2fa');

    const qrContainer = document.getElementById('2fa-qr-code');
    qrContainer.innerHTML = '<span style="color: #64748b; font-size: 12px;">Loading QR code...</span>';
    const secretDisplay = document.getElementById('2fa-secret-display');
    secretDisplay.textContent = '--------';

    try {
        const res = await App.request('/account/two-factor');
        if (!res || !res.data || !res.data.secret) {
            throw new Error('Failed to generate 2FA secret from server.');
        }

        secretDisplay.textContent = res.data.secret;
        
        qrContainer.innerHTML = '';
        if (window.QRCode && res.data.qr_url) {
            try {
                new QRCode(qrContainer, {
                    text: res.data.qr_url,
                    width: 160,
                    height: 160,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            } catch (err) {
                console.warn('QRCode JS error:', err);
                const safeUrl = encodeURIComponent(res.data.qr_url);
                qrContainer.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=${safeUrl}" alt="QR Code" width="160" height="160" style="display:block;" onerror="this.onerror=null; this.parentElement.innerHTML='<div style=\'color:#334155;font-size:12px;text-align:center;padding:12px;\'>Enter secret key manually</div>';" />`;
            }
        } else if (res.data.qr_url) {
            const safeUrl = encodeURIComponent(res.data.qr_url);
            qrContainer.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=${safeUrl}" alt="QR Code" width="160" height="160" style="display:block;" onerror="this.onerror=null; this.parentElement.innerHTML='<div style=\'color:#334155;font-size:12px;text-align:center;padding:12px;\'>Enter secret key manually</div>';" />`;
        }
        
        const codeInput = document.getElementById('2fa-verify-code');
        codeInput.value = '';
        setTimeout(() => codeInput.focus(), 100);
    } catch (e) {
        App.closeModal('modal-enable-2fa');
        App.toast(e.message || 'Failed to load 2FA setup.', 'danger');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Enable 2FA';
        }
    }
}

async function submit2fa() {
    const codeInput = document.getElementById('2fa-verify-code');
    const code = codeInput.value.trim();
    if (!code || code.length !== 6) {
        App.toast('Please enter the 6-digit code from your authenticator app.', 'warning');
        codeInput.focus();
        return;
    }

    const submitBtn = document.getElementById('btn-submit-enable-2fa');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Verifying...';
    }

    try {
        await App.request('/account/two-factor', {
            method: 'POST',
            body: { code }
        });
        App.toast('Two-factor authentication enabled successfully!', 'success');
        setTimeout(() => window.location.reload(), 800);
    } catch (e) {
        App.toast(e.message || 'Invalid verification code. Please check your authenticator app.', 'danger');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Verify & Enable';
        }
    }
}

async function disable2fa() {
    const password = document.getElementById('disable-2fa-password').value;
    if (!password) {
        App.toast('Please enter your account password.', 'warning');
        return;
    }
    try {
        await App.request('/account/two-factor/disable', {
            method: 'POST',
            body: { password }
        });
        App.toast('Two-factor authentication disabled.', 'success');
        setTimeout(() => window.location.reload(), 800);
    } catch (e) {
        App.toast(e.message || 'Incorrect password.', 'danger');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const trigger = document.getElementById('btn-enable-2fa-trigger');
    if (trigger) {
        trigger.addEventListener('click', initiate2fa);
    }

    const verifyInput = document.getElementById('2fa-verify-code');
    if (verifyInput) {
        verifyInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                submit2fa();
            }
        });
    }
});
</script>
