<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Locations</h1>
        <p class="page-subtitle">Logical grouping of nodes and infrastructure</p>
    </div>
    <div>
        <button class="btn btn-primary btn-sm" onclick="App.openModal('modal-create-location')">Create Location</button>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Short Code</th>
                        <th>Description</th>
                        <th>Nodes</th>
                        <th>Servers</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($locations)): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">No locations configured.</td></tr>
                    <?php else: ?>
                        <?php foreach ($locations as $l): ?>
                            <tr>
                                <td><?= (int)$l['id'] ?></td>
                                <td>
                                    <a href="/admin/locations/view/<?= $l['id'] ?>" style="font-weight: 600; color: #fff;">
                                        <?= \Pterodactyl\Core\View::e($l['short']) ?>
                                    </a>
                                </td>
                                <td><?= \Pterodactyl\Core\View::e($l['long'] ?? '-') ?></td>
                                <td><?= (int)$l['nodes_count'] ?></td>
                                <td><?= (int)$l['servers_count'] ?></td>
                                <td style="text-align: right;">
                                    <a href="/admin/locations/view/<?= $l['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Location Modal -->
<div class="modal-backdrop" id="modal-create-location">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Create Location</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="/admin/locations" method="POST">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="short">Short Code</label>
                    <input type="text" id="short" name="short" class="form-control" required placeholder="e.g. us-east, eu-central">
                </div>
                <div class="form-group">
                    <label class="form-label" for="long">Description</label>
                    <input type="text" id="long" name="long" class="form-control" placeholder="e.g. Frankfurt, Germany Data Center">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Create Location</button>
            </div>
        </form>
    </div>
</div>
