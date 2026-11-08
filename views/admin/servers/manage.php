<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Manage Server: <?= \Pterodactyl\Core\View::e($server['name']) ?></h1>
        <p class="page-subtitle">Administrative actions: suspend, reinstall, and delete server</p>
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
    <a href="/admin/servers/view/<?= $server['id'] ?>/manage" class="<?= ($activeServerTab ?? '') === 'manage' ? 'active' : '' ?>">Manage &amp; Actions</a>
</nav>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- Suspend / Unsuspend -->
    <div class="card">
        <div class="card-header">Server Suspension</div>
        <div class="card-body">
            <p style="color: var(--text-secondary); margin-bottom: 16px;">
                Suspending a server will stop its processes and prevent users from starting or modifying it until it is unsuspended.
            </p>
            <form action="/admin/servers/view/<?= $server['id'] ?>/manage/toggle-suspend" method="POST">
                <?= \Pterodactyl\Core\View::csrf() ?>
                <?php if ($server['status'] === 'suspended'): ?>
                    <button type="submit" class="btn btn-success">Unsuspend Server</button>
                <?php else: ?>
                    <button type="submit" class="btn btn-warning">Suspend Server</button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Reinstall -->
    <div class="card">
        <div class="card-header">Reinstall Server</div>
        <div class="card-body">
            <p style="color: var(--text-secondary); margin-bottom: 16px;">
                Re-executes the egg installation script on the host node. This may overwrite modified core server files.
            </p>
            <form action="/admin/servers/view/<?= $server['id'] ?>/manage/reinstall" method="POST" onsubmit="return confirm('Are you sure you want to trigger a reinstallation?');">
                <?= \Pterodactyl\Core\View::csrf() ?>
                <button type="submit" class="btn btn-secondary">Trigger Reinstallation</button>
            </form>
        </div>
    </div>
</div>

<!-- Danger Zone: Delete -->
<div class="card" style="margin-top: 24px; border-color: rgba(239, 68, 68, 0.4);">
    <div class="card-header" style="color: #ef4444;">Danger Zone: Delete Server</div>
    <div class="card-body" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <strong style="color: #fff;">Permanently Delete This Server</strong>
            <p style="color: var(--text-secondary); font-size: 13px; margin-top: 4px;">
                This will delete the server files from Wings, detach allocations, and erase all related database records. This cannot be undone.
            </p>
        </div>
        <form action="/admin/servers/view/<?= $server['id'] ?>/delete" method="POST" onsubmit="return confirm('ARE YOU ABSOLUTELY SURE? All data, files, and backups for this server will be permanently deleted.');">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="btn btn-danger">Delete Server</button>
        </form>
    </div>
</div>
