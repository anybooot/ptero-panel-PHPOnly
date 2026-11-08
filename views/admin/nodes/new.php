<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Create New Node</h1>
        <p class="page-subtitle">Configure a new physical machine running the Wings daemon</p>
    </div>
    <div>
        <a href="/admin/nodes" class="btn btn-secondary btn-sm">Back to Nodes</a>
    </div>
</div>

<form action="/admin/nodes/new" method="POST">
    <?= \Pterodactyl\Core\View::csrf() ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <!-- Basic Settings -->
        <div class="card">
            <div class="card-header">Basic Details</div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="node-name">Node Name</label>
                    <input type="text" id="node-name" name="name" class="form-control" required placeholder="e.g. Node 1 - Germany">
                </div>

                <div class="form-group">
                    <label class="form-label" for="node-location">Location</label>
                    <select id="node-location" name="location_id" class="form-control" required>
                        <option value="">Select Location</option>
                        <?php foreach ($locations as $l): ?>
                            <option value="<?= $l['id'] ?>"><?= \Pterodactyl\Core\View::e($l['short'] . ' (' . ($l['long'] ?: 'No description') . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label class="form-label" for="node-fqdn">FQDN / Domain</label>
                        <input type="text" id="node-fqdn" name="fqdn" class="form-control" required placeholder="node1.yourdomain.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="node-scheme">SSL Scheme</label>
                        <select id="node-scheme" name="scheme" class="form-control">
                            <option value="https" selected>HTTPS (SSL)</option>
                            <option value="http">HTTP (Insecure)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary); margin-top: 10px;">
                        <input type="checkbox" name="public" value="1" checked>
                        <span>Allow Automatic Allocations (Public)</span>
                    </label>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary);">
                        <input type="checkbox" name="behind_proxy" value="1">
                        <span>Behind Proxy (Cloudflare / Reverse Proxy)</span>
                    </label>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary);">
                        <input type="checkbox" name="maintenance_mode" value="1">
                        <span>Maintenance Mode</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Allocation Limits & Ports -->
        <div class="card">
            <div class="card-header">Resource Limits &amp; Daemon Ports</div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label class="form-label" for="node-memory">Total Memory (MiB)</label>
                        <input type="number" id="node-memory" name="memory" class="form-control" required value="16384">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="node-mem-over">Overallocate Memory (%)</label>
                        <input type="number" id="node-mem-over" name="memory_overallocate" class="form-control" value="0">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label class="form-label" for="node-disk">Total Disk Space (MiB)</label>
                        <input type="number" id="node-disk" name="disk" class="form-control" required value="102400">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="node-disk-over">Overallocate Disk (%)</label>
                        <input type="number" id="node-disk-over" name="disk_overallocate" class="form-control" value="0">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="node-upload">Max Upload Size (MiB)</label>
                    <input type="number" id="node-upload" name="upload_size" class="form-control" value="100">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label class="form-label" for="node-listen">Daemon HTTP Port</label>
                        <input type="number" id="node-listen" name="daemonListen" class="form-control" value="8080">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="node-sftp">Daemon SFTP Port</label>
                        <input type="number" id="node-sftp" name="daemonSFTP" class="form-control" value="2022">
                    </div>
                </div>
            </div>
            <div class="card-footer" style="text-align: right;">
                <button type="submit" class="btn btn-primary">Create Node</button>
            </div>
        </div>
    </div>
</form>
