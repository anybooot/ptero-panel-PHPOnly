<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Nests</h1>
        <p class="page-subtitle">Game types and configurations containing specialized eggs</p>
    </div>
    <div>
        <button class="btn btn-primary btn-sm" onclick="App.openModal('modal-create-nest')">Create Nest</button>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Nest Name</th>
                        <th>Description</th>
                        <th>Eggs</th>
                        <th>Servers</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($nests)): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">No nests configured.</td></tr>
                    <?php else: ?>
                        <?php foreach ($nests as $n): ?>
                            <tr>
                                <td><?= (int)$n['id'] ?></td>
                                <td>
                                    <a href="/admin/nests/view/<?= $n['id'] ?>" style="font-weight: 600; color: #fff;">
                                        <?= \Pterodactyl\Core\View::e($n['name']) ?>
                                    </a>
                                </td>
                                <td><?= \Pterodactyl\Core\View::e($n['description'] ?: '-') ?></td>
                                <td><?= (int)$n['eggs_count'] ?></td>
                                <td><?= (int)$n['servers_count'] ?></td>
                                <td style="text-align: right;">
                                    <a href="/admin/nests/view/<?= $n['id'] ?>" class="btn btn-secondary btn-sm">Manage</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Nest Modal -->
<div class="modal-backdrop" id="modal-create-nest">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Create Nest</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="/admin/nests" method="POST">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="nest-name">Nest Name</label>
                    <input type="text" id="nest-name" name="name" class="form-control" required placeholder="e.g. Minecraft, Source Engine, Voice Servers">
                </div>
                <div class="form-group">
                    <label class="form-label" for="nest-desc">Description</label>
                    <textarea id="nest-desc" name="description" class="form-control" rows="3" placeholder="Description of this game category..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Create Nest</button>
            </div>
        </form>
    </div>
</div>
