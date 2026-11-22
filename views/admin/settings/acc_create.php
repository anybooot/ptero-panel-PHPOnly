<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Account Creation Settings</h1>
        <p class="page-subtitle">Configure public registration, account creation rules, and password security policies</p>
    </div>
</div>

<!-- Settings Sub-navigation -->
<nav class="subnav" style="margin-bottom: 20px;">
    <a href="/admin/settings" class="<?= ($activeSettingTab ?? '') === 'general' ? 'active' : '' ?>">General</a>
    <a href="/admin/settings/mail" class="<?= ($activeSettingTab ?? '') === 'mail' ? 'active' : '' ?>">Mail (SMTP)</a>
    <a href="/admin/settings/advanced" class="<?= ($activeSettingTab ?? '') === 'advanced' ? 'active' : '' ?>">Advanced</a>
    <a href="/admin/settings/acc-create" class="<?= ($activeSettingTab ?? '') === 'acc-create' ? 'active' : '' ?>">Acc Create</a>
</nav>

<form action="/admin/settings/acc-create" method="POST">
    <?= \Pterodactyl\Core\View::csrf() ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <!-- Public User Registration -->
        <div class="card">
            <div class="card-header">User Registration</div>
            <div class="card-body">
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary); margin-bottom: 8px;">
                        <input type="checkbox" name="auth:registration_enabled" value="1" <?= (!empty($registrationEnabled)) ? 'checked' : '' ?>>
                        <span style="font-weight: 600;">Enable Public User Registration</span>
                    </label>
                    <small style="color: var(--text-muted); font-size: 12px; display: block; line-height: 1.5;">
                        Allows new visitors to create accounts directly from the login page. When disabled, only administrators or API calls can create users.
                    </small>
                </div>

                <div class="form-group" style="margin-top: 18px;">
                    <label class="form-label" for="auth-2fa">Enforce Two-Factor Authentication (2FA)</label>
                    <select id="auth-2fa" name="auth:2fa_required" class="form-control">
                        <option value="0" <?= $twoFactorRequirement === 0 ? 'selected' : '' ?>>Not Required</option>
                        <option value="1" <?= $twoFactorRequirement === 1 ? 'selected' : '' ?>>Admin Users Only</option>
                        <option value="2" <?= $twoFactorRequirement === 2 ? 'selected' : '' ?>>All Users</option>
                    </select>
                    <small style="color: var(--text-muted); font-size: 12px; display: block; margin-top: 6px;">
                        Require newly created and existing accounts to have two-factor authentication enabled to access the panel.
                    </small>
                </div>
            </div>
        </div>

        <!-- Password Security Policy -->
        <div class="card">
            <div class="card-header">Password Security Policy</div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="pwd-len">Minimum Password Length</label>
                    <input type="number" id="pwd-len" name="auth:password_min_length" class="form-control" min="8" max="64" value="<?= (int)$pwdMinLength ?>">
                    <small style="color: var(--text-muted); font-size: 11px;">Minimum required characters for new account passwords (8-64).</small>
                </div>

                <div class="form-group" style="margin-top: 16px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary); margin-bottom: 8px;">
                        <input type="checkbox" name="auth:password_require_uppercase" value="1" <?= $pwdRequireMixed ? 'checked' : '' ?>>
                        <span>Require Uppercase &amp; Lowercase Characters</span>
                    </label>

                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary); margin-bottom: 8px;">
                        <input type="checkbox" name="auth:password_require_numbers" value="1" <?= $pwdRequireNumbers ? 'checked' : '' ?>>
                        <span>Require Numbers (0-9)</span>
                    </label>

                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary);">
                        <input type="checkbox" name="auth:password_require_symbols" value="1" <?= $pwdRequireSymbols ? 'checked' : '' ?>>
                        <span>Require Special Symbols (!@#$%^&amp;*)</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div style="margin-top: 20px; text-align: right;">
        <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">Save Account Settings</button>
    </div>
</form>
