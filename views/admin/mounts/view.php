<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Mount: <?= \Pterodactyl\Core\View::e($mount['name']) ?></h1>
        <p class="page-subtitle"><?= \Pterodactyl\Core\View::e($mount['source']) ?> &rarr; <?= \Pterodactyl\Core\View::e($mount['target']) ?></p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/admin/mounts" class="btn btn-secondary btn-sm">Back to Mounts</a>
        <form action="/admin/mounts/view/<?= $mount['id'] ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this mount?');" style="margin: 0;">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="btn btn-danger btn-sm">Delete Mount</button>
        </form>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- Edit Mount Details -->
    <div class="card">
        <div class="card-header">Mount Details</div>
        <form action="/admin/mounts/view/<?= $mount['id'] ?>" method="POST">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="mnt-name">Name</label>
                    <input type="text" id="mnt-name" name="name" class="form-control" value="<?= \Pterodactyl\Core\View::e($mount['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="mnt-desc">Description</label>
                    <input type="text" id="mnt-desc" name="description" class="form-control" value="<?= \Pterodactyl\Core\View::e($mount['description'] ?? '') ?>">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label class="form-label" for="mnt-source">Source Directory (Node Host)</label>
                        <input type="text" id="mnt-source" name="source" class="form-control" value="<?= \Pterodactyl\Core\View::e($mount['source']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="mnt-target">Target Mount (Inside Container)</label>
                        <input type="text" id="mnt-target" name="target" class="form-control" value="<?= \Pterodactyl\Core\View::e($mount['target']) ?>" required>
                    </div>
                </div>
                <div style="display: flex; gap: 20px; margin-top: 10px;">
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; color: var(--text-primary);">
                        <input type="checkbox" name="read_only" value="1" <?= !empty($mount['read_only']) ? 'checked' : '' ?>>
                        <span>Read Only</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; color: var(--text-primary);">
                        <input type="checkbox" name="user_mountable" value="1" <?= !empty($mount['user_mountable']) ? 'checked' : '' ?>>
                        <span>User Mountable</span>
                    </label>
                </div>
            </div>
            <div class="card-footer" style="text-align: right;">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    <!-- Assigned Nodes & Eggs -->
    <div>
        <!-- Nodes -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <span>Attached Nodes</span>
                <button class="btn btn-secondary btn-sm" onclick="App.openModal('modal-attach-nodes')">Attach Node</button>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Node</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($nodes)): ?>
                                <tr><td colspan="2" style="text-align: center; color: var(--text-muted); padding: 12px;">No nodes attached to this mount.</td></tr>
                            <?php else: ?>
                                <?php foreach ($nodes as $n): ?>
                                    <tr>
                                        <td><strong><?= \Pterodactyl\Core\View::e($n['name']) ?></strong></td>
                                        <td style="text-align: right;">
                                            <form action="/admin/mounts/<?= $mount['id'] ?>/nodes/<?= $n['id'] ?>" method="POST" style="margin: 0;">
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

        <!-- Eggs -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <span>Attached Eggs</span>
                <button class="btn btn-secondary btn-sm" onclick="App.openModal('modal-attach-eggs')">Attach Egg</button>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Egg</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($eggs)): ?>
                                <tr><td colspan="2" style="text-align: center; color: var(--text-muted); padding: 12px;">No eggs attached to this mount.</td></tr>
                            <?php else: ?>
                                <?php foreach ($eggs as $e): ?>
                                    <tr>
                                        <td><strong><?= \Pterodactyl\Core\View::e($e['name']) ?></strong></td>
                                        <td style="text-align: right;">
                                            <form action="/admin/mounts/<?= $mount['id'] ?>/eggs/<?= $e['id'] ?>" method="POST" style="margin: 0;">
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
    </div>
</div>

<!-- Attach Nodes Modal -->
<div class="modal-backdrop" id="modal-attach-nodes">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Attach Nodes to Mount</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="/admin/mounts/<?= $mount['id'] ?>/nodes" method="POST">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Node(s)</label>
                    <select name="nodes[]" multiple class="form-control" style="height: 140px;">
                        <?php foreach ($allNodes as $n): ?>
                            <option value="<?= $n['id'] ?>"><?= \Pterodactyl\Core\View::e($n['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Attach Nodes</button>
            </div>
        </form>
    </div>
</div>

<!-- Attach Eggs Modal -->
<div class="modal-backdrop" id="modal-attach-eggs">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Attach Eggs to Mount</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="/admin/mounts/<?= $mount['id'] ?>/eggs" method="POST">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Egg(s)</label>
                    <select name="eggs[]" multiple class="form-control" style="height: 140px;">
                        <?php foreach ($allEggs as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= \Pterodactyl\Core\View::e($e['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Attach Eggs</button>
            </div>
        </form>
    </div>
</div>
