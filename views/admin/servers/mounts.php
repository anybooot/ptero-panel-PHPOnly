<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Mounts: <?= \Pterodactyl\Core\View::e($server['name']) ?></h1>
        <p class="page-subtitle"><?= \Pterodactyl\Core\View::e($server['uuid']) ?> &bull; Shared host directories mounted into container</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <button class="btn btn-primary btn-sm" onclick="App.openModal('modal-add-mount')">Attach Mount</button>
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

<div class="card">
    <div class="card-header">Mounted Directories</div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Mount Name</th>
                        <th>Host Source Path</th>
                        <th>Container Target Path</th>
                        <th>Read Only</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mounted)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">No mounts are attached to this server.</td></tr>
                    <?php else: ?>
                        <?php foreach ($mounted as $m): ?>
                            <tr>
                                <td><strong style="color: #fff;"><?= \Pterodactyl\Core\View::e($m['name']) ?></strong></td>
                                <td><code style="font-family: var(--font-mono); font-size: 12px;"><?= \Pterodactyl\Core\View::e($m['source']) ?></code></td>
                                <td><code style="font-family: var(--font-mono); font-size: 12px; color: #60a5fa;"><?= \Pterodactyl\Core\View::e($m['target']) ?></code></td>
                                <td>
                                    <?php if (!empty($m['read_only'])): ?>
                                        <span class="badge badge-secondary">Read Only</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Read / Write</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <form action="/admin/servers/view/<?= $server['id'] ?>/mounts/<?= $m['id'] ?>" method="POST" onsubmit="return confirm('Detach this mount from server?');" style="margin: 0;">
                                        <?= \Pterodactyl\Core\View::csrf() ?>
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button type="submit" class="btn btn-danger btn-sm" style="padding: 2px 8px;">Detach</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Attach Mount Modal -->
<div class="modal-backdrop" id="modal-add-mount">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Attach Mount to Server</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="/admin/servers/view/<?= $server['id'] ?>/mounts" method="POST">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="mount-select">Available Mount</label>
                    <select id="mount-select" name="mount_id" class="form-control" required>
                        <option value="">Select a Mount</option>
                        <?php foreach ($availableMounts as $am): ?>
                            <option value="<?= $am['id'] ?>">
                                <?= \Pterodactyl\Core\View::e($am['name']) ?> (<?= \Pterodactyl\Core\View::e($am['source']) ?> &rarr; <?= \Pterodactyl\Core\View::e($am['target']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Attach Mount</button>
            </div>
        </form>
    </div>
</div>
