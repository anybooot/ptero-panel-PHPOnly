<?php $this->extend('layouts.server'); ?>

<div class="page-header" style="margin-bottom: 16px;">
    <div>
        <h1 class="page-title">Startup Configuration</h1>
        <p class="page-subtitle">Configure startup arguments and Egg environment variables</p>
    </div>
</div>

<!-- Startup Command Preview -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">Startup Command Preview</div>
    <div class="card-body">
        <div style="background-color: #0b0f15; padding: 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); font-family: var(--font-mono); font-size: 13px; color: #60a5fa; word-break: break-all;">
            <?= \Pterodactyl\Core\View::e($commandPreview) ?>
        </div>
    </div>
</div>

<!-- Egg Variables Grid -->
<h2 style="font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 12px;">Egg Environment Variables</h2>
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(420px, 1fr)); gap: 16px;">
    <?php foreach ($variables as $v): ?>
        <?php
            $val = $v['server_value'] !== null ? $v['server_value'] : $v['default_value'];
            $editable = !empty($v['user_editable']) || !empty($user['root_admin']);
        ?>
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <span><?= \Pterodactyl\Core\View::e($v['name']) ?></span>
                <span style="font-family: var(--font-mono); font-size: 11px; color: #93c5fd; background: rgba(59, 130, 246, 0.15); padding: 2px 6px; border-radius: 3px;">
                    <?= \Pterodactyl\Core\View::e($v['env_variable']) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="form-group" style="margin-bottom: 8px;">
                    <input type="text"
                           class="form-control"
                           id="var-<?= $v['env_variable'] ?>"
                           value="<?= \Pterodactyl\Core\View::e($val) ?>"
                           <?= !$editable ? 'readonly' : '' ?>
                           onchange="updateVariable('<?= $v['env_variable'] ?>', this.value)">
                </div>
                <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 0;">
                    <?= \Pterodactyl\Core\View::e($v['description']) ?>
                </p>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
async function updateVariable(key, value) {
    try {
        await App.request('/api/client/servers/<?= $server['uuid'] ?>/startup/variable', {
            method: 'PUT',
            body: { key, value }
        });
        App.toast(`Updated ${key} successfully.`, 'success');
    } catch (e) {}
}
</script>
