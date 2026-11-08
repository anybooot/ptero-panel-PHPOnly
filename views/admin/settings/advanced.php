<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Advanced Settings</h1>
        <p class="page-subtitle">Configure bot verification and security protections</p>
    </div>
</div>

<!-- Settings Sub-navigation -->
<nav class="subnav" style="margin-bottom: 20px;">
    <a href="/admin/settings" class="<?= ($activeSettingTab ?? '') === 'general' ? 'active' : '' ?>">General</a>
    <a href="/admin/settings/mail" class="<?= ($activeSettingTab ?? '') === 'mail' ? 'active' : '' ?>">Mail (SMTP)</a>
    <a href="/admin/settings/advanced" class="<?= ($activeSettingTab ?? '') === 'advanced' ? 'active' : '' ?>">Advanced</a>
</nav>

<div class="card" style="max-width: 800px;">
    <div class="card-header">Google reCAPTCHA v2 / v3</div>
    <form action="/admin/settings/advanced" method="POST">
        <?= \Pterodactyl\Core\View::csrf() ?>
        <div class="card-body">
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary); margin-bottom: 14px;">
                    <input type="checkbox" name="recaptcha:enabled" value="1" <?= $recaptchaEnabled ? 'checked' : '' ?>>
                    <span style="font-weight: 600;">Enable Google reCAPTCHA Verification</span>
                </label>
                <small style="color: var(--text-muted); font-size: 12px; display: block;">
                    Requires visitors to solve a challenge when attempting authentication to protect against brute-force attacks.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="rc-site">Site Key</label>
                <input type="text" id="rc-site" name="recaptcha:website_key" class="form-control" value="<?= \Pterodactyl\Core\View::e($recaptchaSite) ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="rc-secret">Secret Key</label>
                <input type="password" id="rc-secret" name="recaptcha:secret_key" class="form-control" placeholder="••••••••">
            </div>
        </div>
        <div class="card-footer" style="text-align: right;">
            <button type="submit" class="btn btn-primary">Save Advanced Settings</button>
        </div>
    </form>
</div>
