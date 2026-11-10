<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Startup: <?= \Pterodactyl\Core\View::e($server['name']) ?></h1>
        <p class="page-subtitle"><?= \Pterodactyl\Core\View::e($server['uuid']) ?> &bull; Egg: <?= \Pterodactyl\Core\View::e($server['egg_name']) ?></p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/server/<?= $server['uuidShort'] ?>/startup" target="_blank" class="btn btn-secondary btn-sm">Client View &nearr;</a>
        <a href="/admin/servers" class="btn btn-secondary btn-sm">Back to Servers</a>
    </div>
</div>

<!-- Server Sub-navigation -->
<nav class="subnav" style="margin-bottom: 20px;">
    <a href="/admin/servers/view/<?= $server['id'] ?>" class="<?= ($activeServerTab ?? '') === 'overview' ? 'active' : '' ?>">Overview</a>
    <a href="/admin/servers/view/<?= $server['id'] ?>/details" class="<?= ($activeServerTab ?? '') === 'details' ? 'active' : '' ?>">Details</a>
    <a href="/admin/servers/view/<?= $server['id'] ?>/build" class="<?= ($activeServerTab ?? '') === 'build' ? 'active' : '' ?>">Build Configuration</a>
    <a href="/admin/servers/view/<?= $server['id'] ?>/startup" class="<?= ($activeServerTab ?? '') === 'startup' ? 'active' : '' ?>">Startup</a>
    <a href="/admin/servers/view/<?= $server['id'] ?>/database" class="<?= ($activeServerTab ?? '') === 'database' ? 'active' : '' ?>">Databases</a>
    <a href="/admin/servers/view/<?= $server['id'] ?>/mounts" class="<?= ($activeServerTab ?? '') === 'mounts' ? 'active' : '' ?>">Mounts</a>
    <a href="/admin/servers/view/<?= $server['id'] ?>/manage" class="<?= ($activeServerTab ?? '') === 'manage' ? 'active' : '' ?>">Manage &amp; Actions</a>
</nav>

<form action="/admin/servers/view/<?= $server['id'] ?>/startup" method="POST">
    <?= \Pterodactyl\Core\View::csrf() ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
        <!-- Startup & Docker Image -->
        <div class="card">
            <div class="card-header">Startup Command &amp; Container</div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="srv-startup">Startup Command</label>
                    <textarea id="srv-startup" name="startup" class="form-control" rows="3" style="font-family: var(--font-mono); font-size: 13px;" required><?= \Pterodactyl\Core\View::e($server['startup']) ?></textarea>
                    <small style="color: var(--text-muted); font-size: 12px; margin-top: 4px; display: block;">
                        Variables enclosed in <code>{{VARIABLE_NAME}}</code> will be substituted automatically by Wings.
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="srv-image">Docker Image</label>
                    <input type="text" id="srv-image" name="image" class="form-control" value="<?= \Pterodactyl\Core\View::e($server['image']) ?>" required>
                    <small style="color: var(--text-muted); font-size: 12px; margin-top: 4px; display: block;">
                        The Docker image to execute this server container within.
                    </small>
                </div>
            </div>
        </div>

        <!-- Service / Nest & Egg -->
        <div class="card">
            <div class="card-header">Service Configuration (Egg)</div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="srv-egg">Assigned Egg</label>
                    <select id="srv-egg" name="egg_id" class="form-control" required>
                        <?php foreach ($eggs as $e): ?>
                            <option value="<?= $e['id'] ?>" <?= ((int)$server['egg_id']) === ((int)$e['id']) ? 'selected' : '' ?>>
                                <?= \Pterodactyl\Core\View::e($e['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="alert alert-secondary" style="margin-top: 16px;">
                    <strong style="color: #fff;">Note on Changing Eggs:</strong>
                    <p style="font-size: 12px; margin-top: 4px; color: var(--text-secondary); margin-bottom: 0;">
                        Changing the Egg will reload the startup environment variables. If you change the egg, you should trigger a reinstall from the Manage tab to apply any new container scripts.
                    </p>
                </div>
            </div>
            <div class="card-footer" style="text-align: right;">
                <button type="submit" class="btn btn-primary">Save Startup Settings</button>
            </div>
        </div>
    </div>

    <!-- Environment Variables -->
    <div class="card">
        <div class="card-header">Egg Environment Variables</div>
        <div class="card-body">
            <?php if (empty($variables)): ?>
                <p style="color: var(--text-muted); margin: 0;">No environment variables are defined for this Egg.</p>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 16px;">
                    <?php foreach ($variables as $v): ?>
                        <?php $val = $v['server_value'] !== null ? $v['server_value'] : $v['default_value']; ?>
                        <div style="background: rgba(0,0,0,0.25); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 14px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <label class="form-label" style="margin-bottom: 0;" for="env-<?= $v['env_variable'] ?>">
                                    <?= \Pterodactyl\Core\View::e($v['name']) ?>
                                </label>
                                <code style="font-family: var(--font-mono); font-size: 11px; color: #10b981; background: rgba(16, 185, 129, 0.1); padding: 2px 6px; border-radius: 3px;">
                                    {{<?= \Pterodactyl\Core\View::e($v['env_variable']) ?>}}
                                </code>
                            </div>
                            <input type="text"
                                   id="env-<?= $v['env_variable'] ?>"
                                   name="env_<?= $v['env_variable'] ?>"
                                   class="form-control"
                                   value="<?= \Pterodactyl\Core\View::e($val) ?>">
                            <small style="color: var(--text-muted); font-size: 11px; display: block; margin-top: 4px;">
                                <?= \Pterodactyl\Core\View::e($v['description'] ?: 'No description provided') ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer" style="text-align: right;">
            <button type="submit" class="btn btn-primary">Save All Variables</button>
        </div>
    </div>
</form>
