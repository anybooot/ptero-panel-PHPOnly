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
                    <option value="en" <?= $appLocale === 'en' ? 'selected' : '' ?>>English (en)</option>
                    <option value="de" <?= $appLocale === 'de' ? 'selected' : '' ?>>German (de)</option>
                    <option value="fr" <?= $appLocale === 'fr' ? 'selected' : '' ?>>French (fr)</option>
                    <option value="es" <?= $appLocale === 'es' ? 'selected' : '' ?>>Spanish (es)</option>
                    <option value="ro" <?= $appLocale === 'ro' ? 'selected' : '' ?>>Romanian (ro)</option>
                </select>
            </div>
        </div>
        <div class="card-footer" style="text-align: right;">
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
    </form>
</div>
