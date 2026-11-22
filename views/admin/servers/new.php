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

                <div class="form-group" style="position: relative;">
                    <label class="form-label" for="user-search-input">Server Owner</label>
                    <input type="hidden" id="srv-owner" name="owner_id" required value="">
                    <div style="position: relative;">
                        <input type="text" id="user-search-input" class="form-control" placeholder="Search user by username or email..." autocomplete="off">
                        <span id="user-clear-btn" style="display: none; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text-muted); font-size: 16px;">&times;</span>
                    </div>
                    <div id="user-dropdown-list" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #161820; border: 1px solid var(--border-color); border-radius: 8px; max-height: 220px; overflow-y: auto; z-index: 100; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.6);">
                        <?php foreach ($users as $u): ?>
                            <div class="search-user-item" data-id="<?= $u['id'] ?>" data-username="<?= \Pterodactyl\Core\View::e($u['username']) ?>" data-email="<?= \Pterodactyl\Core\View::e($u['email']) ?>" style="padding: 10px 14px; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.04); display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 600; color: #eceff4;"><?= \Pterodactyl\Core\View::e($u['username']) ?></span>
                                <span style="font-size: 12px; color: var(--text-muted);"><?= \Pterodactyl\Core\View::e($u['email']) ?></span>
                            </div>
                        <?php endforeach; ?>
                        <div id="user-empty-msg" style="display: none; padding: 14px; text-align: center; color: var(--text-muted); font-size: 13px;">No matching users found</div>
                    </div>
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

                <div class="form-group" style="position: relative;">
                    <label class="form-label" for="alloc-search-input">Primary Allocation</label>
                    <input type="hidden" id="srv-alloc" name="allocation_id" value="0">
                    <div style="position: relative;">
                        <input type="text" id="alloc-search-input" class="form-control" placeholder="Auto-assign first available (type IP or port to filter)..." autocomplete="off">
                        <span id="alloc-clear-btn" style="display: none; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text-muted); font-size: 16px;">&times;</span>
                    </div>
                    <div id="alloc-dropdown-list" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #161820; border: 1px solid var(--border-color); border-radius: 8px; max-height: 220px; overflow-y: auto; z-index: 100; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.6);">
                        <div class="search-alloc-item" data-id="0" data-label="Auto-assign first available" style="padding: 10px 14px; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.04); color: #60a5fa; font-weight: 500;">
                            Auto-assign first available
                        </div>
                        <div id="alloc-items-container"></div>
                        <div id="alloc-empty-msg" style="display: none; padding: 14px; text-align: center; color: var(--text-muted); font-size: 13px;">No matching allocations found</div>
                    </div>
                    <small style="color: var(--text-muted); font-size: 12px; margin-top: 4px; display: block;">
                        Search and select a specific IP or port, or leave on auto-assign.
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
// User Search & Select
const userInput = document.getElementById('user-search-input');
const userHidden = document.getElementById('srv-owner');
const userDropdown = document.getElementById('user-dropdown-list');
const userClearBtn = document.getElementById('user-clear-btn');
const userEmptyMsg = document.getElementById('user-empty-msg');
const userItems = Array.from(document.querySelectorAll('.search-user-item'));

function filterUsers(query) {
    query = (query || '').toLowerCase().trim();
    let matches = 0;
    userItems.forEach(item => {
        const u = (item.dataset.username || '').toLowerCase();
        const e = (item.dataset.email || '').toLowerCase();
        if (!query || u.includes(query) || e.includes(query)) {
            item.style.display = 'flex';
            matches++;
        } else {
            item.style.display = 'none';
        }
    });
    userEmptyMsg.style.display = matches === 0 ? 'block' : 'none';
}

userInput.addEventListener('focus', () => {
    userDropdown.style.display = 'block';
    filterUsers(userInput.value);
});

userInput.addEventListener('input', () => {
    userDropdown.style.display = 'block';
    userClearBtn.style.display = userInput.value ? 'block' : 'none';
    filterUsers(userInput.value);
});

userClearBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    userInput.value = '';
    userHidden.value = '';
    userClearBtn.style.display = 'none';
    filterUsers('');
    userInput.focus();
});

userItems.forEach(item => {
    item.addEventListener('click', () => {
        userHidden.value = item.dataset.id;
        userInput.value = `${item.dataset.username} (${item.dataset.email})`;
        userClearBtn.style.display = 'block';
        userDropdown.style.display = 'none';
    });
    item.addEventListener('mouseenter', () => {
        item.style.backgroundColor = 'rgba(255,255,255,0.06)';
    });
    item.addEventListener('mouseleave', () => {
        item.style.backgroundColor = 'transparent';
    });
});

// Allocation Search & Select
let loadedAllocations = [];
const allocInput = document.getElementById('alloc-search-input');
const allocHidden = document.getElementById('srv-alloc');
const allocDropdown = document.getElementById('alloc-dropdown-list');
const allocClearBtn = document.getElementById('alloc-clear-btn');
const allocItemsContainer = document.getElementById('alloc-items-container');
const allocEmptyMsg = document.getElementById('alloc-empty-msg');

async function loadNodeAllocations(nodeId) {
    allocHidden.value = '0';
    allocInput.value = '';
    allocClearBtn.style.display = 'none';
    allocItemsContainer.innerHTML = '';
    loadedAllocations = [];

    if (!nodeId) {
        allocInput.placeholder = 'Select a node first...';
        return;
    }

    allocInput.placeholder = 'Loading allocations...';
    try {
        const res = await fetch(`/admin/nodes/view/${nodeId}/allocations-json`);
        if (!res.ok) throw new Error('Failed to fetch');
        loadedAllocations = await res.json();
        allocInput.placeholder = 'Auto-assign first available (type IP or port to filter)...';
        renderAllocItems(loadedAllocations);
    } catch (e) {
        console.error('Error fetching allocations:', e);
        allocInput.placeholder = 'Failed to load allocations';
    }
}

function renderAllocItems(list) {
    allocItemsContainer.innerHTML = '';
    list.forEach(a => {
        const fullAddr = `${a.ip_alias ? a.ip_alias : a.ip}:${a.port}`;
        const rawAddr = `${a.ip}:${a.port}`;
        const div = document.createElement('div');
        div.className = 'search-alloc-item';
        div.dataset.id = a.id;
        div.dataset.ip = a.ip;
        div.dataset.alias = a.ip_alias || '';
        div.dataset.port = a.port;
        div.dataset.full = fullAddr;
        div.style.cssText = 'padding: 10px 14px; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.04); display: flex; justify-content: space-between; align-items: center;';
        div.innerHTML = `
            <span style="font-family: var(--font-mono); font-weight: 600; color: #eceff4;">${fullAddr}</span>
            <span style="font-size: 11px; background: rgba(59,130,246,0.15); color: #60a5fa; padding: 2px 6px; border-radius: 4px;">Port ${a.port}</span>
        `;
        div.addEventListener('click', () => {
            allocHidden.value = a.id;
            allocInput.value = fullAddr;
            allocClearBtn.style.display = 'block';
            allocDropdown.style.display = 'none';
        });
        div.addEventListener('mouseenter', () => div.style.backgroundColor = 'rgba(255,255,255,0.06)');
        div.addEventListener('mouseleave', () => div.style.backgroundColor = 'transparent');
        allocItemsContainer.appendChild(div);
    });
}

function filterAllocations(query) {
    query = (query || '').toLowerCase().trim();
    let matches = 0;
    const items = allocItemsContainer.querySelectorAll('.search-alloc-item');
    items.forEach(item => {
        const ip = (item.dataset.ip || '').toLowerCase();
        const alias = (item.dataset.alias || '').toLowerCase();
        const port = String(item.dataset.port || '').toLowerCase();
        const full = (item.dataset.full || '').toLowerCase();

        if (!query || ip.includes(query) || alias.includes(query) || port.includes(query) || full.includes(query)) {
            item.style.display = 'flex';
            matches++;
        } else {
            item.style.display = 'none';
        }
    });

    const autoAssign = allocDropdown.querySelector('.search-alloc-item[data-id="0"]');
    if (autoAssign) {
        autoAssign.style.display = (!query || 'auto'.includes(query)) ? 'block' : 'none';
        if (autoAssign.style.display === 'block') matches++;
    }

    allocEmptyMsg.style.display = matches === 0 ? 'block' : 'none';
}

const autoItem = allocDropdown.querySelector('.search-alloc-item[data-id="0"]');
if (autoItem) {
    autoItem.addEventListener('click', () => {
        allocHidden.value = '0';
        allocInput.value = 'Auto-assign first available';
        allocClearBtn.style.display = 'block';
        allocDropdown.style.display = 'none';
    });
    autoItem.addEventListener('mouseenter', () => autoItem.style.backgroundColor = 'rgba(255,255,255,0.06)');
    autoItem.addEventListener('mouseleave', () => autoItem.style.backgroundColor = 'transparent');
}

allocInput.addEventListener('focus', () => {
    allocDropdown.style.display = 'block';
    filterAllocations(allocInput.value);
});

allocInput.addEventListener('input', () => {
    allocDropdown.style.display = 'block';
    allocClearBtn.style.display = allocInput.value ? 'block' : 'none';
    filterAllocations(allocInput.value);
});

allocClearBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    allocInput.value = '';
    allocHidden.value = '0';
    allocClearBtn.style.display = 'none';
    filterAllocations('');
    allocInput.focus();
});

// Close dropdowns on outside click
document.addEventListener('click', (e) => {
    if (!userInput.contains(e.target) && !userDropdown.contains(e.target)) {
        userDropdown.style.display = 'none';
    }
    if (!allocInput.contains(e.target) && !allocDropdown.contains(e.target)) {
        allocDropdown.style.display = 'none';
    }
});

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
