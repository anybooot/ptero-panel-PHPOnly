<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Create New Server</h1>
        <p class="page-subtitle">Provision a new game server container onto a Wings node</p>
    </div>
    <div>
        <a href="/admin/servers" class="btn btn-secondary btn-sm">Back to Servers</a>
    </div>
</div>

<form action="/admin/servers/new" method="POST">
    <?= \Pterodactyl\Core\View::csrf() ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <!-- Core Details -->
        <div class="card">
            <div class="card-header">Core Details</div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="srv-name">Server Name</label>
                    <input type="text" id="srv-name" name="name" class="form-control" required placeholder="e.g. Survival SMP">
                </div>

                <div class="form-group">
                    <label class="form-label" for="srv-owner">Server Owner</label>
                    <select id="srv-owner" name="owner_id" class="form-control" required>
                        <option value="">Select User</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= \Pterodactyl\Core\View::e($u['username'] . ' (' . $u['email'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="srv-node">Target Node</label>
                    <select id="srv-node" name="node_id" class="form-control" required onchange="loadNodeAllocations(this.value)">
                        <option value="">Select Node</option>
                        <?php foreach ($nodes as $n): ?>
                            <option value="<?= $n['id'] ?>"><?= \Pterodactyl\Core\View::e($n['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="srv-alloc">Primary Allocation</label>
                    <select id="srv-alloc" name="allocation_id" class="form-control">
                        <option value="0">Auto-assign first available</option>
                    </select>
                    <small style="color: var(--text-muted); font-size: 12px; margin-top: 4px; display: block;">
                        Select a specific IP and port or leave on auto-assign.
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="srv-nest">Nest &amp; Egg</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <select id="srv-nest" class="form-control" onchange="filterEggs(this.value)">
                            <option value="">Select Nest</option>
                            <?php foreach ($nests as $nest): ?>
                                <option value="<?= $nest['id'] ?>"><?= \Pterodactyl\Core\View::e($nest['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="srv-egg" name="egg_id" class="form-control" required onchange="selectEgg(this.value)">
                            <option value="">Select Egg</option>
                            <?php foreach ($eggs as $egg): ?>
                                <option value="<?= $egg['id'] ?>" data-nest="<?= $egg['nest_id'] ?>" data-startup="<?= \Pterodactyl\Core\View::e($egg['startup']) ?>" data-image="<?= \Pterodactyl\Core\View::e($egg['image']) ?>">
                                    <?= \Pterodactyl\Core\View::e($egg['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resource Limits & Startup -->
        <div class="card">
            <div class="card-header">Resource Limits &amp; Startup</div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label class="form-label" for="srv-memory">Memory (MiB)</label>
                        <input type="number" id="srv-memory" name="memory" class="form-control" required value="2048">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="srv-swap">Swap (MiB)</label>
                        <input type="number" id="srv-swap" name="swap" class="form-control" value="0">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label class="form-label" for="srv-disk">Disk Space (MiB)</label>
                        <input type="number" id="srv-disk" name="disk" class="form-control" required value="10240">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="srv-cpu">CPU Limit (%)</label>
                        <input type="number" id="srv-cpu" name="cpu" class="form-control" required value="100">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 12px;">
                    <div class="form-group">
                        <label class="form-label" for="srv-alloc-limit">Allocations Limit</label>
                        <input type="number" id="srv-alloc-limit" name="allocation_limit" class="form-control" value="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="srv-db-limit">Databases Limit</label>
                        <input type="number" id="srv-db-limit" name="database_limit" class="form-control" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="srv-bk-limit">Backups Limit</label>
                        <input type="number" id="srv-bk-limit" name="backup_limit" class="form-control" value="0">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="srv-image">Docker Image</label>
                    <input type="text" id="srv-image" name="image" class="form-control" placeholder="ghcr.io/pterodactyl/yolks:java_17">
                </div>

                <div class="form-group">
                    <label class="form-label" for="srv-startup">Startup Command</label>
                    <input type="text" id="srv-startup" name="startup" class="form-control" placeholder="java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar server.jar">
                </div>
            </div>
            <div class="card-footer" style="text-align: right;">
                <button type="submit" class="btn btn-primary">Create Server</button>
            </div>
        </div>
    </div>
</form>

<script>
async function loadNodeAllocations(nodeId) {
    const allocSelect = document.getElementById('srv-alloc');
    allocSelect.innerHTML = '<option value="0">Auto-assign first available</option>';
    if (!nodeId) return;

    try {
        const res = await fetch(`/admin/nodes/view/${nodeId}/allocations-json`);
        if (!res.ok) return;
        const allocations = await res.json();
        allocations.forEach(a => {
            const opt = document.createElement('option');
            opt.value = a.id;
            opt.textContent = `${a.ip_alias ? a.ip_alias + ' (' + a.ip + ')' : a.ip}:${a.port}`;
            allocSelect.appendChild(opt);
        });
    } catch (e) {
        console.error('Error fetching allocations:', e);
    }
}

function filterEggs(nestId) {
    const eggSelect = document.getElementById('srv-egg');
    const options = eggSelect.querySelectorAll('option');
    options.forEach(opt => {
        if (!opt.value) return;
        if (!nestId || opt.getAttribute('data-nest') === nestId) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });
    eggSelect.value = '';
}

function selectEgg(eggId) {
    const eggSelect = document.getElementById('srv-egg');
    const selectedOption = eggSelect.querySelector(`option[value="${eggId}"]`);
    if (selectedOption) {
        const startup = selectedOption.getAttribute('data-startup');
        const image = selectedOption.getAttribute('data-image');
        if (startup) document.getElementById('srv-startup').value = startup;
        if (image) document.getElementById('srv-image').value = image;
    }
}
</script>
