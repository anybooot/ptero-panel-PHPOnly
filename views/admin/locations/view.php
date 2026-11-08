<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Location: <?= \Pterodactyl\Core\View::e($location['short']) ?></h1>
        <p class="page-subtitle"><?= \Pterodactyl\Core\View::e($location['long'] ?? '') ?></p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/admin/locations" class="btn btn-secondary btn-sm">Back to Locations</a>
        <form action="/admin/locations/view/<?= $location['id'] ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this location?');" style="margin: 0;">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="btn btn-danger btn-sm">Delete Location</button>
        </form>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- Edit Location Form -->
    <div class="card">
        <div class="card-header">Edit Location Details</div>
        <form action="/admin/locations/view/<?= $location['id'] ?>" method="POST">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="short">Short Code</label>
                    <input type="text" id="short" name="short" class="form-control" value="<?= \Pterodactyl\Core\View::e($location['short']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="long">Description</label>
                    <input type="text" id="long" name="long" class="form-control" value="<?= \Pterodactyl\Core\View::e($location['long'] ?? '') ?>">
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    <!-- Nodes Assigned -->
    <div class="card">
        <div class="card-header">Nodes in this Location</div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Node Name</th>
                            <th>FQDN</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($nodes)): ?>
                            <tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 20px;">No nodes are assigned to this location.</td></tr>
                        <?php else: ?>
                            <?php foreach ($nodes as $n): ?>
                                <tr>
                                    <td style="font-weight: 600; color: #fff;"><?= \Pterodactyl\Core\View::e($n['name']) ?></td>
                                    <td style="font-family: var(--font-mono); color: var(--text-secondary);"><?= \Pterodactyl\Core\View::e($n['fqdn']) ?></td>
                                    <td style="text-align: right;">
                                        <a href="/admin/nodes/view/<?= $n['id'] ?>" class="btn btn-secondary btn-sm">Manage</a>
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
