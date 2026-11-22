<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Mounts</h1>
        <p class="page-subtitle">Configure directory mounts from host nodes into game server containers</p>
    </div>
    <div>
        <button class="btn btn-primary btn-sm" onclick="App.openModal('modal-create-mount')">Create Mount</button>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Name</th>
                        <th>Source (Host)</th>
                        <th>Target (Container)</th>
                        <th>Access</th>
                        <th>Eggs</th>
                        <th>Nodes</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mounts)): ?>
                        <tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 24px;">No mounts configured.</td></tr>
                    <?php else: ?>
                        <?php foreach ($mounts as $m): ?>
                            <tr>
                                <td><?= (int)$m['id'] ?></td>
                                <td>
                                    <a href="/admin/mounts/view/<?= $m['id'] ?>" style="font-weight: 600; color: #fff;">
                                        <?= \Pterodactyl\Core\View::e($m['name']) ?>
                                    </a>
                                </td>
                                <td><code style="font-family: var(--font-mono); font-size: 12px; color: var(--text-secondary);"><?= \Pterodactyl\Core\View::e($m['source']) ?></code></td>
                                <td><code style="font-family: var(--font-mono); font-size: 12px; color: var(--text-secondary);"><?= \Pterodactyl\Core\View::e($m['target']) ?></code></td>
                                <td>
                                    <?php if (!empty($m['read_only'])): ?>
                                        <span class="badge badge-secondary">Read-Only</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Read-Write</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)$m['eggs_count'] ?></td>
                                <td><?= (int)$m['nodes_count'] ?></td>
                                <td style="text-align: right;">
                                    <a href="/admin/mounts/view/<?= $m['id'] ?>" class="btn btn-secondary btn-sm">Manage</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Mount Modal -->
<div class="modal-backdrop" id="modal-create-mount">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Create Mount</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="/admin/mounts" method="POST">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="mnt-name">Name</label>
                    <input type="text" id="mnt-name" name="name" class="form-control" required placeholder="e.g. Shared Maps, Global Configs">
                </div>
                <div class="form-group">
                    <label class="form-label" for="mnt-desc">Description</label>
                    <input type="text" id="mnt-desc" name="description" class="form-control" placeholder="Optional description...">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label class="form-label" for="mnt-source">Source Directory (Node Host)</label>
                        <input type="text" id="mnt-source" name="source" class="form-control" required placeholder="/var/shared/maps">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="mnt-target">Target Mount (Inside Container)</label>
                        <input type="text" id="mnt-target" name="target" class="form-control" required placeholder="/home/container/maps">
                    </div>
                </div>
                <div style="display: flex; gap: 20px; margin-top: 10px;">
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; color: var(--text-primary);">
                        <input type="checkbox" name="read_only" value="1">
                        <span>Read Only</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; color: var(--text-primary);">
                        <input type="checkbox" name="user_mountable" value="1">
                        <span>User Mountable</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Create Mount</button>
            </div>
        </form>
    </div>
</div>
