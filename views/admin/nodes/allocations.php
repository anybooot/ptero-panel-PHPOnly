<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Allocations: <?= \Pterodactyl\Core\View::e($node['name']) ?></h1>
        <p class="page-subtitle">Manage IP addresses and ports available for server assignment on this node</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <button class="btn btn-primary btn-sm" onclick="App.openModal('modal-add-allocations')">Assign New Allocations</button>
        <a href="/admin/nodes" class="btn btn-secondary btn-sm">Back to Nodes</a>
    </div>
</div>

<!-- Node Sub-navigation -->
<nav class="subnav" style="margin-bottom: 20px;">
    <a href="/admin/nodes/view/<?= $node['id'] ?>" class="<?= ($activeNodeTab ?? '') === 'about' ? 'active' : '' ?>">Settings</a>
    <a href="/admin/nodes/view/<?= $node['id'] ?>/configuration" class="<?= ($activeNodeTab ?? '') === 'configuration' ? 'active' : '' ?>">Configuration</a>
    <a href="/admin/nodes/view/<?= $node['id'] ?>/allocation" class="<?= ($activeNodeTab ?? '') === 'allocation' ? 'active' : '' ?>">Allocations</a>
    <a href="/admin/nodes/view/<?= $node['id'] ?>/servers" class="<?= ($activeNodeTab ?? '') === 'servers' ? 'active' : '' ?>">Servers</a>
</nav>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>IP Address</th>
                        <th>IP Alias</th>
                        <th>Port</th>
                        <th>Assigned Server</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allocations)): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">No allocations created on this node yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($allocations as $a): ?>
                            <tr>
                                <td><?= (int)$a['id'] ?></td>
                                <td style="font-family: var(--font-mono); font-weight: 600; color: #fff;"><?= \Pterodactyl\Core\View::e($a['ip']) ?></td>
                                <td><?= \Pterodactyl\Core\View::e($a['ip_alias'] ?? '-') ?></td>
                                <td style="font-family: var(--font-mono); color: #60a5fa;"><?= (int)$a['port'] ?></td>
                                <td>
                                    <?php if (!empty($a['server_id'])): ?>
                                        <a href="/admin/servers/view/<?= $a['server_id'] ?>" style="color: #10b981; font-weight: 600;">
                                            <?= \Pterodactyl\Core\View::e($a['server_name'] ?? 'Server') ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php if (empty($a['server_id'])): ?>
                                        <form action="/admin/nodes/view/<?= $node['id'] ?>/allocation/<?= $a['id'] ?>" method="POST" onsubmit="return confirm('Delete this allocation?');" style="margin: 0; display: inline;">
                                            <?= \Pterodactyl\Core\View::csrf() ?>
                                            <input type="hidden" name="_method" value="DELETE">
                                            <button type="submit" class="btn btn-danger btn-sm" style="padding: 2px 8px;">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size: 12px; color: var(--text-muted);">In Use</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Allocation Modal -->
<div class="modal-backdrop" id="modal-add-allocations">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Assign New Allocations</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="/admin/nodes/view/<?= $node['id'] ?>/allocation" method="POST">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="alloc-ip">IP Address</label>
                    <input type="text" id="alloc-ip" name="ip" class="form-control" required placeholder="0.0.0.0 or 192.168.1.100">
                </div>
                <div class="form-group">
                    <label class="form-label" for="alloc-alias">IP Alias (Optional)</label>
                    <input type="text" id="alloc-alias" name="ip_alias" class="form-control" placeholder="e.g. play.example.com">
                </div>
                <div class="form-group">
                    <label class="form-label" for="alloc-ports">Ports</label>
                    <input type="text" id="alloc-ports" name="ports[]" class="form-control" required placeholder="e.g. 25565 or 25565-25575">
                    <small style="color: var(--text-muted); font-size: 12px; display: block; margin-top: 4px;">
                        Individual ports or port ranges separated by hyphens (e.g. 25565-25575).
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Allocations</button>
            </div>
        </form>
    </div>
</div>
