<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Egg Variables: <?= \Pterodactyl\Core\View::e($egg['name']) ?></h1>
        <p class="page-subtitle">Configure environment variables and startup placeholders for servers using this egg</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <button class="btn btn-primary btn-sm" onclick="App.openModal('modal-add-variable')">Add Variable</button>
        <a href="/admin/nests/egg/<?= $egg['id'] ?>" class="btn btn-secondary btn-sm">Back to Egg</a>
    </div>
</div>

<!-- Egg Sub-navigation -->
<nav class="subnav" style="margin-bottom: 20px;">
    <a href="/admin/nests/egg/<?= $egg['id'] ?>" class="<?= ($activeEggTab ?? '') === 'details' ? 'active' : '' ?>">Configuration</a>
    <a href="/admin/nests/egg/<?= $egg['id'] ?>/variables" class="<?= ($activeEggTab ?? '') === 'variables' ? 'active' : '' ?>">Variables (<?= count($variables ?? []) ?>)</a>
</nav>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Environment Variable</th>
                        <th>Default Value</th>
                        <th>Permissions</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($variables)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">No variables defined for this egg.</td></tr>
                    <?php else: ?>
                        <?php foreach ($variables as $v): ?>
                            <tr>
                                <td>
                                    <strong style="color: #fff;"><?= \Pterodactyl\Core\View::e($v['name']) ?></strong>
                                    <div style="font-size: 11px; color: var(--text-secondary);"><?= \Pterodactyl\Core\View::e($v['description'] ?: '-') ?></div>
                                </td>
                                <td>
                                    <code style="background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px; color: #10b981; font-family: var(--font-mono); font-size: 12px;">
                                        {{<?= \Pterodactyl\Core\View::e($v['env_variable']) ?>}}
                                    </code>
                                </td>
                                <td style="font-family: var(--font-mono); font-size: 12px; color: var(--text-secondary);"><?= \Pterodactyl\Core\View::e($v['default_value']) ?></td>
                                <td>
                                    <?php if (!empty($v['user_viewable'])): ?>
                                        <span class="badge badge-secondary" style="font-size: 10px;">Viewable</span>
                                    <?php endif; ?>
                                    <?php if (!empty($v['user_editable'])): ?>
                                        <span class="badge badge-success" style="font-size: 10px;">Editable</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <form action="/admin/nests/egg/<?= $egg['id'] ?>/variables/<?= $v['id'] ?>" method="POST" onsubmit="return confirm('Delete this variable?');" style="margin: 0;">
                                        <?= \Pterodactyl\Core\View::csrf() ?>
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button type="submit" class="btn btn-danger btn-sm" style="padding: 2px 8px;">Delete</button>
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

<!-- Add Variable Modal -->
<div class="modal-backdrop" id="modal-add-variable">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Add Egg Variable</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="/admin/nests/egg/<?= $egg['id'] ?>/variables" method="POST">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="var-name">Variable Name</label>
                    <input type="text" id="var-name" name="name" class="form-control" required placeholder="e.g. Server JAR File">
                </div>
                <div class="form-group">
                    <label class="form-label" for="var-desc">Description</label>
                    <textarea id="var-desc" name="description" class="form-control" rows="2" placeholder="The name of the executable jarfile..."></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label class="form-label" for="var-env">Environment Variable</label>
                        <input type="text" id="var-env" name="env_variable" class="form-control" required placeholder="SERVER_JARFILE">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="var-def">Default Value</label>
                        <input type="text" id="var-def" name="default_value" class="form-control" placeholder="server.jar">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="var-rules">Validation Rules</label>
                    <input type="text" id="var-rules" name="rules" class="form-control" value="required|string|max:40">
                </div>
                <div style="display: flex; gap: 20px; margin-top: 10px;">
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; color: var(--text-primary);">
                        <input type="checkbox" name="user_viewable" value="1" checked>
                        <span>User Viewable</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; color: var(--text-primary);">
                        <input type="checkbox" name="user_editable" value="1" checked>
                        <span>User Editable</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Variable</button>
            </div>
        </form>
    </div>
</div>
