<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Node: <?= \Pterodactyl\Core\View::e($node['name']) ?></h1>
        <p class="page-subtitle"><?= \Pterodactyl\Core\View::e($node['fqdn']) ?> &bull; Location: <?= \Pterodactyl\Core\View::e($location['short'] ?? 'Unknown') ?></p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/admin/nodes" class="btn btn-secondary btn-sm">Back to Nodes</a>
        <button type="button" class="btn btn-danger btn-sm" onclick="App.openModal('modal-delete-node')">Delete Node</button>
    </div>
</div>

<!-- Delete Node Confirmation Modal -->
<div class="modal-backdrop" id="modal-delete-node">
    <div class="modal-dialog" style="max-width: 520px;">
        <div class="modal-header" style="border-bottom: 1px solid rgba(239, 68, 68, 0.2);">
            <h3 class="modal-title" style="color: #f87171;">Ștergere Nod: <?= \Pterodactyl\Core\View::e($node['name']) ?></h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="/admin/nodes/view/<?= $node['id'] ?>" method="POST">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <input type="hidden" name="_method" value="DELETE">
            <div class="modal-body">
                <?php $srvCount = (int)($stats['servers_count'] ?? 0); ?>
                <?php if ($srvCount > 0): ?>
                    <div class="alert alert-danger" style="margin-bottom: 16px; border-left: 4px solid #ef4444;">
                        <strong>Atenție!</strong> Acest nod are în prezent <strong style="color: #fff;"><?= $srvCount ?> server(e)</strong> create pe el.
                    </div>
                    <p style="font-size: 13.5px; color: var(--text-secondary); line-height: 1.6; margin-bottom: 14px;">
                        Puteți alege să ștergeți nodul împreună cu toate cele <strong style="color: #fff;"><?= $srvCount ?> server(e)</strong> asociate, sau să anulați pentru a muta serverele mai întâi.
                    </p>
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; color: #f87171; cursor: pointer; background: rgba(239,68,68,0.1); padding: 10px 14px; border-radius: 6px;">
                        <input type="checkbox" name="delete_servers" value="1" required id="confirm-delete-all-srv">
                        <span>Confirm că doresc să șterg toate cele <?= $srvCount ?> servere și acest nod</span>
                    </label>
                <?php else: ?>
                    <p style="font-size: 13.5px; color: var(--text-secondary); line-height: 1.6;">
                        Sunteți sigur că doriți să ștergeți acest nod? Toate alocările neasociate vor fi șterse definitiv.
                    </p>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Anulează</button>
                <button type="submit" class="btn btn-danger">
                    <?= $srvCount > 0 ? "Șterge nodul și cele {$srvCount} servere" : "Șterge Nodul" ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Node Sub-navigation -->
<nav class="subnav" style="margin-bottom: 20px;">
    <a href="/admin/nodes/view/<?= $node['id'] ?>" class="<?= ($activeNodeTab ?? '') === 'about' ? 'active' : '' ?>">Settings</a>
    <a href="/admin/nodes/view/<?= $node['id'] ?>/configuration" class="<?= ($activeNodeTab ?? '') === 'configuration' ? 'active' : '' ?>">Configuration</a>
    <a href="/admin/nodes/view/<?= $node['id'] ?>/allocation" class="<?= ($activeNodeTab ?? '') === 'allocation' ? 'active' : '' ?>">Allocations</a>
    <a href="/admin/nodes/view/<?= $node['id'] ?>/servers" class="<?= ($activeNodeTab ?? '') === 'servers' ? 'active' : '' ?>">Servers</a>
</nav>

<!-- Statistics Row -->
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px;">
    <div class="card" style="padding: 16px; text-align: center;">
        <div style="font-size: 24px; font-weight: 700; color: #3b82f6;"><?= (int)($stats['servers_count'] ?? 0) ?></div>
        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Total Servers</div>
    </div>
    <div class="card" style="padding: 16px; text-align: center;">
        <div style="font-size: 24px; font-weight: 700; color: #10b981;">
            <?= round(((int)($stats['memory_used'] ?? 0)) / 1024, 1) ?> / <?= round(((int)$node['memory']) / 1024, 1) ?> GiB
        </div>
        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Memory Allocated</div>
    </div>
    <div class="card" style="padding: 16px; text-align: center;">
        <div style="font-size: 24px; font-weight: 700; color: #f59e0b;">
            <?= round(((int)($stats['disk_used'] ?? 0)) / 1024, 1) ?> / <?= round(((int)$node['disk']) / 1024, 1) ?> GiB
        </div>
        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Disk Allocated</div>
    </div>
</div>

<!-- Edit Settings Form -->
<form action="/admin/nodes/view/<?= $node['id'] ?>/settings" method="POST">
    <?= \Pterodactyl\Core\View::csrf() ?>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="card">
            <div class="card-header">Node Settings</div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="name">Node Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?= \Pterodactyl\Core\View::e($node['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="location_id">Location</label>
                    <select id="location_id" name="location_id" class="form-control" required>
                        <?php foreach ($locations as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= ((int)$node['location_id']) === ((int)$l['id']) ? 'selected' : '' ?>>
                                <?= \Pterodactyl\Core\View::e($l['short']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label class="form-label" for="fqdn">FQDN</label>
                        <input type="text" id="fqdn" name="fqdn" class="form-control" value="<?= \Pterodactyl\Core\View::e($node['fqdn']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="scheme">SSL Scheme</label>
                        <select id="scheme" name="scheme" class="form-control">
                            <option value="https" <?= $node['scheme'] === 'https' ? 'selected' : '' ?>>HTTPS</option>
                            <option value="http" <?= $node['scheme'] === 'http' ? 'selected' : '' ?>>HTTP</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary); margin-top: 10px;">
                        <input type="checkbox" name="public" value="1" <?= !empty($node['public']) ? 'checked' : '' ?>>
                        <span>Public (Allocatable)</span>
                    </label>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary);">
                        <input type="checkbox" name="behind_proxy" value="1" <?= !empty($node['behind_proxy']) ? 'checked' : '' ?>>
                        <span>Behind Proxy</span>
                    </label>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary);">
                        <input type="checkbox" name="maintenance_mode" value="1" <?= !empty($node['maintenance_mode']) ? 'checked' : '' ?>>
                        <span>Maintenance Mode</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Limits &amp; Ports</div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="memory">Total Memory (MiB)</label>
                    <input type="number" id="memory" name="memory" class="form-control" value="<?= (int)$node['memory'] ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="disk">Total Disk (MiB)</label>
                    <input type="number" id="disk" name="disk" class="form-control" value="<?= (int)$node['disk'] ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="upload_size">Upload Limit (MiB)</label>
                    <input type="number" id="upload_size" name="upload_size" class="form-control" value="<?= (int)$node['upload_size'] ?>" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label class="form-label" for="daemonListen">Daemon Port</label>
                        <input type="number" id="daemonListen" name="daemonListen" class="form-control" value="<?= (int)$node['daemonListen'] ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="daemonSFTP">SFTP Port</label>
                        <input type="number" id="daemonSFTP" name="daemonSFTP" class="form-control" value="<?= (int)$node['daemonSFTP'] ?>" required>
                    </div>
                </div>
            </div>
            <div class="card-footer" style="text-align: right;">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
</form>
