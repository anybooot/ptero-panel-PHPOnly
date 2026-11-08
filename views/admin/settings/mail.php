<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Mail Settings</h1>
        <p class="page-subtitle">Configure outbound SMTP server for password resets and notification emails</p>
    </div>
</div>

<!-- Settings Sub-navigation -->
<nav class="subnav" style="margin-bottom: 20px;">
    <a href="/admin/settings" class="<?= ($activeSettingTab ?? '') === 'general' ? 'active' : '' ?>">General</a>
    <a href="/admin/settings/mail" class="<?= ($activeSettingTab ?? '') === 'mail' ? 'active' : '' ?>">Mail (SMTP)</a>
    <a href="/admin/settings/advanced" class="<?= ($activeSettingTab ?? '') === 'advanced' ? 'active' : '' ?>">Advanced</a>
</nav>

<div class="card" style="max-width: 800px;">
    <div class="card-header">SMTP Configuration</div>
    <form action="/admin/settings/mail" method="POST">
        <?= \Pterodactyl\Core\View::csrf() ?>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label" for="mail-host">SMTP Host</label>
                    <input type="text" id="mail-host" name="mail:host" class="form-control" value="<?= \Pterodactyl\Core\View::e($mailHost) ?>" placeholder="smtp.mailgun.org">
                </div>
                <div class="form-group">
                    <label class="form-label" for="mail-port">SMTP Port</label>
                    <input type="number" id="mail-port" name="mail:port" class="form-control" value="<?= (int)$mailPort ?>">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label" for="mail-user">Username</label>
                    <input type="text" id="mail-user" name="mail:username" class="form-control" value="<?= \Pterodactyl\Core\View::e($mailUser) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="mail-pwd">Password (Leave blank to keep existing)</label>
                    <input type="password" id="mail-pwd" name="mail:password" class="form-control" placeholder="••••••••">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="mail-enc">Encryption</label>
                <select id="mail-enc" name="mail:encryption" class="form-control">
                    <option value="tls" <?= $mailEnc === 'tls' ? 'selected' : '' ?>>TLS</option>
                    <option value="ssl" <?= $mailEnc === 'ssl' ? 'selected' : '' ?>>SSL</option>
                    <option value="" <?= empty($mailEnc) ? 'selected' : '' ?>>None</option>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label" for="mail-from">From Email Address</label>
                    <input type="email" id="mail-from" name="mail:from:address" class="form-control" value="<?= \Pterodactyl\Core\View::e($mailFrom) ?>" placeholder="no-reply@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label" for="mail-from-name">From Name</label>
                    <input type="text" id="mail-from-name" name="mail:from:name" class="form-control" value="<?= \Pterodactyl\Core\View::e($mailFromName) ?>" placeholder="Pterodactyl Panel">
                </div>
            </div>
        </div>
        <div class="card-footer" style="text-align: right;">
            <button type="submit" class="btn btn-primary">Save Mail Configuration</button>
        </div>
    </form>
</div>
