<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Node Configuration: <?= \Pterodactyl\Core\View::e($node['name']) ?></h1>
        <p class="page-subtitle">Deploy Wings daemon using auto-configuration or manual config file</p>
    </div>
    <div>
        <a href="/admin/nodes" class="btn btn-secondary btn-sm">Back to Nodes</a>
    </div>
</div>

<!-- Node Sub-navigation -->
<nav class="subnav" style="margin-bottom: 20px;">
    <a href="/admin/nodes/view/<?= $node['id'] ?>" class="<?= ($activeNodeTab ?? '') === 'about' ? 'active' : '' ?>">Settings</a>
    <a href="/admin/nodes/view/<?= $node['id'] ?>/configuration" class="<?= ($activeNodeTab ?? '') === 'configuration' ? 'active' : '' ?>">Configuration</a>
    <a href="/admin/nodes/view/<?= $node['id'] ?>/allocation" class="<?= ($activeNodeTab ?? '') === 'allocation' ? 'active' : '' ?>">Allocations</a>
    <a href="/admin/nodes/view/<?= $node['id'] ?>/servers" class="<?= ($activeNodeTab ?? '') === 'servers' ? 'active' : '' ?>">Servers</a>
</nav>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-header">Auto-Deploy Command</div>
    <div class="card-body">
        <p style="color: var(--text-secondary); margin-bottom: 12px;">
            Run this command on your node's terminal with root privileges to automatically configure Wings with this panel:
        </p>
        <div style="background-color: var(--bg-base); padding: 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); font-family: var(--font-mono); font-size: 13px; color: #10b981; display: flex; justify-content: space-between; align-items: center;">
            <code id="cmd-autodeploy"><?= \Pterodactyl\Core\View::e($autoDeployCmd) ?></code>
            <button class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('cmd-autodeploy').innerText); App.toast('Command copied to clipboard!', 'success');">Copy</button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <span>Configuration File (<code>/etc/pterodactyl/config.yml</code>)</span>
        <button class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('code-config-yml').innerText); App.toast('YAML copied to clipboard!', 'success');">Copy YAML</button>
    </div>
    <div class="card-body" style="padding: 0;">
        <pre id="code-config-yml" style="background-color: var(--bg-base); margin: 0; padding: 16px; font-family: var(--font-mono); font-size: 13px; color: var(--text-primary); overflow-x: auto; border-radius: 0 0 var(--radius-md) var(--radius-md);"><?= \Pterodactyl\Core\View::e($configYml) ?></pre>
    </div>
</div>
