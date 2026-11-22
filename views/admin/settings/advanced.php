<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Advanced Settings</h1>
        <p class="page-subtitle">Configure bot verification, proxy trust, two-factor enforcement, and security policies</p>
    </div>
</div>

<!-- Settings Sub-navigation -->
<nav class="subnav" style="margin-bottom: 20px;">
    <a href="/admin/settings" class="<?= ($activeSettingTab ?? '') === 'general' ? 'active' : '' ?>">General</a>
    <a href="/admin/settings/mail" class="<?= ($activeSettingTab ?? '') === 'mail' ? 'active' : '' ?>">Mail (SMTP)</a>
    <a href="/admin/settings/advanced" class="<?= ($activeSettingTab ?? '') === 'advanced' ? 'active' : '' ?>">Advanced</a>
    <a href="/admin/settings/acc-create" class="<?= ($activeSettingTab ?? '') === 'acc-create' ? 'active' : '' ?>">Acc Create</a>
</nav>

<form action="/admin/settings/advanced" method="POST">
    <?= \Pterodactyl\Core\View::csrf() ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <!-- reCAPTCHA Settings -->
        <div class="card">
            <div class="card-header">Google reCAPTCHA v2 / v3</div>
            <div class="card-body">
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary); margin-bottom: 10px;">
                        <input type="checkbox" name="recaptcha:enabled" value="1" <?= $recaptchaEnabled ? 'checked' : '' ?>>
                        <span style="font-weight: 600;">Enable Google reCAPTCHA Verification</span>
                    </label>
                    <small style="color: var(--text-muted); font-size: 12px; display: block;">
                        Requires visitors to complete a challenge when attempting authentication to protect against brute-force attacks.
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="rc-site">Site Key</label>
                    <input type="text" id="rc-site" name="recaptcha:website_key" class="form-control" value="<?= \Pterodactyl\Core\View::e($recaptchaSite) ?>" placeholder="6LeIxacZAAAAA...">
                </div>

                <div class="form-group">
                    <label class="form-label" for="rc-secret">Secret Key</label>
                    <input type="password" id="rc-secret" name="recaptcha:secret_key" class="form-control" placeholder="••••••••">
                    <small style="color: var(--text-muted); font-size: 11px;">Leave blank to keep existing secret key.</small>
                </div>
            </div>
        </div>

        <!-- HTTP & Reverse Proxy Settings -->
        <div class="card">
            <div class="card-header">HTTP Traffic &amp; Proxy Trust</div>
            <div class="card-body">
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary); margin-bottom: 10px;">
                        <input type="checkbox" name="app:behind_proxy" value="1" <?= $behindProxy ? 'checked' : '' ?>>
                        <span style="font-weight: 600;">Behind Reverse Proxy (Cloudflare / Nginx / Traefik)</span>
                    </label>
                    <small style="color: var(--text-muted); font-size: 12px; display: block; line-height: 1.5;">
                        Enable this if the panel is running behind a reverse proxy or Cloudflare. When enabled, requests will inspect <code>X-Forwarded-For</code> and <code>CF-Connecting-IP</code> headers to identify the real visitor IP address.
                    </small>
                </div>

                <div class="form-group" style="margin-top: 20px;">
                    <label class="form-label" for="console-count">Console Line Backlog</label>
                    <input type="number" id="console-count" name="pterodactyl:console:count" class="form-control" min="50" max="1000" value="<?= (int)$consoleCount ?>">
                    <small style="color: var(--text-muted); font-size: 12px; display: block; margin-top: 4px;">
                        The maximum number of previous console output lines to retain and display in the server console (default: 200).
                    </small>
                </div>

                <div class="form-group" style="margin-top: 14px;">
                    <label class="form-label" for="console-freq">Console Frequency Throttle (ms)</label>
                    <input type="number" id="console-freq" name="pterodactyl:console:frequency" class="form-control" min="0" max="5000" value="<?= (int)$consoleFrequency ?>">
                    <small style="color: var(--text-muted); font-size: 12px; display: block; margin-top: 4px;">
                        The minimum time between console updates in milliseconds (0 for unthrottled real-time).
                    </small>
                </div>
            </div>
        </div>

        <!-- Client Features -->
        <div class="card" style="grid-column: span 2;">
            <div class="card-header">Client Self-Allocations</div>
            <div class="card-body">
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary);">
                        <input type="checkbox" name="pterodactyl:client_features:allocations:enabled" value="1" <?= $allocationsEnabled ? 'checked' : '' ?>>
                        <span style="font-weight: 600;">Allow Server Self-Allocations</span>
                    </label>
                    <small style="color: var(--text-muted); font-size: 12px; display: block; margin-top: 4px;">
                        Allows server owners and administrators to assign additional port allocations to their server directly from the client panel if within their allocation limit.
                    </small>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 14px; max-width: 600px;">
                    <div class="form-group">
                        <label class="form-label" for="alloc-start">Port Range Start</label>
                        <input type="number" id="alloc-start" name="pterodactyl:client_features:allocations:range_start" class="form-control" value="<?= (int)($allocRangeStart ?? 1024) ?>" placeholder="1024">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="alloc-end">Port Range End</label>
                        <input type="number" id="alloc-end" name="pterodactyl:client_features:allocations:range_end" class="form-control" value="<?= (int)($allocRangeEnd ?? 65535) ?>" placeholder="65535">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="margin-top: 20px; text-align: right;">
        <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">Save Advanced Settings</button>
    </div>
</form>
