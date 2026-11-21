<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Configure panel branding, language, and system preferences</p>
    </div>
</div>

<!-- Settings Sub-navigation -->
<nav class="subnav" style="margin-bottom: 20px;">
    <a href="/admin/settings" class="<?= ($activeSettingTab ?? '') === 'general' ? 'active' : '' ?>">General</a>
    <a href="/admin/settings/mail" class="<?= ($activeSettingTab ?? '') === 'mail' ? 'active' : '' ?>">Mail (SMTP)</a>
    <a href="/admin/settings/advanced" class="<?= ($activeSettingTab ?? '') === 'advanced' ? 'active' : '' ?>">Advanced</a>
    <a href="/admin/settings/acc-create" class="<?= ($activeSettingTab ?? '') === 'acc-create' ? 'active' : '' ?>">Acc Create</a>
</nav>

<div class="card" style="max-width: 700px;">
    <div class="card-header">General Configuration</div>
    <form action="/admin/settings" method="POST">
        <?= \Pterodactyl\Core\View::csrf() ?>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label" for="app-name">Company / Panel Name</label>
                <input type="text" id="app-name" name="app:name" class="form-control" value="<?= \Pterodactyl\Core\View::e($appName) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="app-locale">Default Language / Locale</label>
                <select id="app-locale" name="app:locale" class="form-control">
                    <option value="en" selected>English (en)</option>
                </select>
            </div>
        </div>
        <div class="card-footer" style="text-align: right;">
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
    </form>
</div>
