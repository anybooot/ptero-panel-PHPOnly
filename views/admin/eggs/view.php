<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Egg: <?= \Pterodactyl\Core\View::e($egg['name']) ?></h1>
        <p class="page-subtitle">Nest: <?= \Pterodactyl\Core\View::e($nest['name'] ?? 'Unknown') ?></p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/admin/nests/egg/<?= $egg['id'] ?>/export" class="btn btn-secondary btn-sm" download>Export JSON</a>
        <a href="/admin/nests/view/<?= $egg['nest_id'] ?>" class="btn btn-secondary btn-sm">Back to Nest</a>
        <form action="/admin/nests/egg/<?= $egg['id'] ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this egg?');" style="margin: 0;">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="btn btn-danger btn-sm">Delete Egg</button>
        </form>
    </div>
</div>

<!-- Egg Sub-navigation -->
<nav class="subnav" style="margin-bottom: 20px;">
    <a href="/admin/nests/egg/<?= $egg['id'] ?>" class="<?= ($activeEggTab ?? '') === 'details' ? 'active' : '' ?>">Configuration</a>
    <a href="/admin/nests/egg/<?= $egg['id'] ?>/variables" class="<?= ($activeEggTab ?? '') === 'variables' ? 'active' : '' ?>">Variables (<?= count($variables ?? []) ?>)</a>
</nav>

<form action="/admin/nests/egg/<?= $egg['id'] ?>" method="POST">
    <?= \Pterodactyl\Core\View::csrf() ?>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <!-- Base Config -->
        <div class="card">
            <div class="card-header">Egg Information</div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="egg-name">Name</label>
                    <input type="text" id="egg-name" name="name" class="form-control" value="<?= \Pterodactyl\Core\View::e($egg['name']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="egg-desc">Description</label>
                    <textarea id="egg-desc" name="description" class="form-control" rows="3"><?= \Pterodactyl\Core\View::e($egg['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="egg-image">Default Docker Image</label>
                    <input type="text" id="egg-image" name="docker_image" class="form-control" value="<?= \Pterodactyl\Core\View::e($egg['image']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="egg-startup">Startup Command</label>
                    <textarea id="egg-startup" name="startup" class="form-control" rows="3" style="font-family: var(--font-mono); font-size: 13px;"><?= \Pterodactyl\Core\View::e($egg['startup'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="egg-stop">Stop Command</label>
                    <input type="text" id="egg-stop" name="stop" class="form-control" value="<?= \Pterodactyl\Core\View::e($egg['inherit_config_stop'] ?? 'stop') ?>">
                </div>
            </div>
        </div>

        <!-- Installation Script -->
        <div class="card">
            <div class="card-header">Install Script &amp; Container</div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="egg-inst-c">Script Container Image</label>
                    <input type="text" id="egg-inst-c" name="install_container" class="form-control" value="<?= \Pterodactyl\Core\View::e($egg['copy_script_container'] ?? 'ghcr.io/pterodactyl/installers:alpine') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="egg-inst-e">Script Entrypoint</label>
                    <input type="text" id="egg-inst-e" name="install_entry" class="form-control" value="<?= \Pterodactyl\Core\View::e($egg['copy_script_entry'] ?? 'ash') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="egg-inst-s">Installation Bash Script</label>
                    <textarea id="egg-inst-s" name="install_script" class="form-control" rows="10" style="font-family: var(--font-mono); font-size: 12px;"><?= \Pterodactyl\Core\View::e($egg['copy_script_install'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="card-footer" style="text-align: right;">
                <button type="submit" class="btn btn-primary">Save Egg Configuration</button>
            </div>
        </div>
    </div>
</form>
