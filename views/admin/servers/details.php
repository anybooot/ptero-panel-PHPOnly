<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Server Details: <?= \Pterodactyl\Core\View::e($server['name']) ?></h1>
        <p class="page-subtitle">Update general server identification and ownership</p>
    </div>
    <div style="display: flex; gap: 8px;">
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

<div class="card" style="max-width: 800px;">
    <div class="card-header">Edit Base Details</div>
    <form action="/admin/servers/view/<?= $server['id'] ?>/details" method="POST">
        <?= \Pterodactyl\Core\View::csrf() ?>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label" for="srv-name">Server Name</label>
                <input type="text" id="srv-name" name="name" class="form-control" value="<?= \Pterodactyl\Core\View::e($server['name']) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="srv-owner">Server Owner</label>
                <select id="srv-owner" name="owner_id" class="form-control" required>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ((int)$server['owner_id']) === ((int)$u['id']) ? 'selected' : '' ?>>
                            <?= \Pterodactyl\Core\View::e($u['username'] . ' (' . $u['email'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="srv-desc">Description</label>
                <textarea id="srv-desc" name="description" class="form-control" rows="3"><?= \Pterodactyl\Core\View::e($server['description'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="card-footer" style="text-align: right;">
            <button type="submit" class="btn btn-primary">Save Details</button>
        </div>
    </form>
</div>
