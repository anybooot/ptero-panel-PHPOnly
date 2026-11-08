<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Nest: <?= \Pterodactyl\Core\View::e($nest['name']) ?></h1>
        <p class="page-subtitle"><?= \Pterodactyl\Core\View::e($nest['description'] ?: 'No description provided') ?></p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/admin/nests/egg/new?nest_id=<?= $nest['id'] ?>" class="btn btn-primary btn-sm">Create New Egg</a>
        <a href="/admin/nests" class="btn btn-secondary btn-sm">Back to Nests</a>
        <form action="/admin/nests/view/<?= $nest['id'] ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this nest? All contained eggs must be deleted first.');" style="margin: 0;">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="btn btn-danger btn-sm">Delete Nest</button>
        </form>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
    <!-- Edit Nest Details -->
    <div class="card">
        <div class="card-header">Nest Details</div>
        <form action="/admin/nests/view/<?= $nest['id'] ?>" method="POST">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="name">Nest Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?= \Pterodactyl\Core\View::e($nest['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"><?= \Pterodactyl\Core\View::e($nest['description'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="card-footer" style="text-align: right;">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    <!-- Eggs in Nest -->
    <div class="card">
        <div class="card-header">Eggs in this Nest</div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Egg Name</th>
                            <th>Docker Image</th>
                            <th>Servers</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($eggs)): ?>
                            <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 20px;">No eggs currently defined in this nest.</td></tr>
                        <?php else: ?>
                            <?php foreach ($eggs as $egg): ?>
                                <tr>
                                    <td>
                                        <a href="/admin/nests/egg/<?= $egg['id'] ?>" style="font-weight: 600; color: #fff;">
                                            <?= \Pterodactyl\Core\View::e($egg['name']) ?>
                                        </a>
                                        <div style="font-size: 11px; color: var(--text-secondary);"><?= \Pterodactyl\Core\View::e($egg['description'] ?: '-') ?></div>
                                    </td>
                                    <td style="font-family: var(--font-mono); font-size: 12px; color: var(--text-secondary);">
                                        <?= \Pterodactyl\Core\View::e($egg['image']) ?>
                                    </td>
                                    <td><?= (int)$egg['servers_count'] ?></td>
                                    <td style="text-align: right;">
                                        <a href="/admin/nests/egg/<?= $egg['id'] ?>" class="btn btn-secondary btn-sm">Manage</a>
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
