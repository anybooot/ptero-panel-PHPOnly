<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Build Configuration: <?= \Pterodactyl\Core\View::e($server['name']) ?></h1>
        <p class="page-subtitle">Configure CPU, memory, disk limits, and port allocations</p>
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

<form action="/admin/servers/view/<?= $server['id'] ?>/build" method="POST">
    <?= \Pterodactyl\Core\View::csrf() ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="card">
            <div class="card-header">Resource Limits</div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label class="form-label" for="srv-cpu">CPU Limit (%)</label>
                        <input type="number" id="srv-cpu" name="cpu" class="form-control" value="<?= (int)$server['cpu'] ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="srv-io">Block IO Weight</label>
                        <input type="number" id="srv-io" name="io" class="form-control" value="<?= (int)$server['io'] ?>" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label class="form-label" for="srv-memory">Memory Limit (MiB)</label>
                        <input type="number" id="srv-memory" name="memory" class="form-control" value="<?= (int)$server['memory'] ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="srv-swap">Swap Limit (MiB)</label>
                        <input type="number" id="srv-swap" name="swap" class="form-control" value="<?= (int)$server['swap'] ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="srv-disk">Disk Space Limit (MiB)</label>
                    <input type="number" id="srv-disk" name="disk" class="form-control" value="<?= (int)$server['disk'] ?>" required>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary);">
                        <input type="checkbox" name="oom_disabled" value="1" <?= !empty($server['oom_disabled']) ? 'checked' : '' ?>>
                        <span>Enable OOM Killer (Terminate container on memory exhaustion)</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Allocations &amp; Feature Limits</div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="srv-alloc">Primary Allocation</label>
                    <select id="srv-alloc" name="allocation_id" class="form-control" required>
                        <?php foreach ($allocations as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= ((int)$server['allocation_id']) === ((int)$a['id']) ? 'selected' : '' ?>>
                                <?= \Pterodactyl\Core\View::e(($a['ip_alias'] ?: $a['ip']) . ':' . $a['port']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="srv-alloc-limit">Additional Allocations Limit</label>
                    <input type="number" id="srv-alloc-limit" name="allocation_limit" class="form-control" value="<?= (int)($server['allocation_limit'] ?? 0) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="srv-db-limit">Databases Limit</label>
                    <input type="number" id="srv-db-limit" name="database_limit" class="form-control" value="<?= (int)($server['database_limit'] ?? 0) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="srv-bk-limit">Backups Limit</label>
                    <input type="number" id="srv-bk-limit" name="backup_limit" class="form-control" value="<?= (int)($server['backup_limit'] ?? 0) ?>">
                </div>
            </div>
            <div class="card-footer" style="text-align: right;">
                <button type="submit" class="btn btn-primary">Update Build Settings</button>
            </div>
        </div>
    </div>
</form>
