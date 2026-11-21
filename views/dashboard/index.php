<?php $this->extend('layouts.main'); ?>

<div class="page-header" style="margin-bottom: 24px;">
    <div>
        <h1 class="page-title" style="font-size: 24px; font-weight: 800; letter-spacing: -0.02em;">Servers</h1>
        <p class="page-subtitle" style="color: var(--text-secondary); font-size: 13.5px;">Showing all servers available to your account</p>
    </div>
    <div>
        <form method="GET" action="/" style="display: flex; gap: 8px;">
            <input type="text" name="search" class="form-control" placeholder="Search servers by name or UUID..." value="<?= \Pterodactyl\Core\View::e($search ?? '') ?>" style="width: 280px;">
            <button type="submit" class="btn btn-secondary">Search</button>
            <?php if (!empty($search)): ?>
                <a href="/" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (empty($servers)): ?>
    <div class="card" style="text-align: center; padding: 56px 20px; background: #161820; border: 1px solid var(--border-color); border-radius: 12px;">
        <div style="font-size: 38px; margin-bottom: 14px; opacity: 0.8;">🎮</div>
        <h3 style="color: #eceff4; font-size: 17px; font-weight: 700; margin-bottom: 6px;">There are no servers associated with your account.</h3>
        <p style="color: var(--text-secondary); font-size: 13px;">If you believe this is an error, please contact your administrator.</p>
    </div>
<?php else: ?>
    <style>
    .server-list-container {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 10px;
    }
    .server-row-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #141720;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-left: 4px solid #475569;
        border-radius: 10px;
        padding: 16px 22px;
        text-decoration: none;
        color: inherit;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        gap: 20px;
    }
    .server-row-item:hover {
        background: #181c28;
        border-color: rgba(255, 255, 255, 0.18);
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.5);
    }
    .server-row-item.status-running {
        border-left-color: #10b981;
    }
    .server-row-item.status-starting {
        border-left-color: #f59e0b;
    }
    .server-row-item.status-stopping, .server-row-item.status-suspended {
        border-left-color: #ef4444;
    }
    .server-row-item.status-offline {
        border-left-color: #475569;
    }
    .server-info-col {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 240px;
        flex: 1.2;
    }
    .server-title-text {
        font-size: 16px;
        font-weight: 700;
        color: #ffffff;
        letter-spacing: -0.01em;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .server-meta-text {
        font-size: 12px;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .server-ip-col {
        display: flex;
        align-items: center;
        min-width: 180px;
        flex: 1;
    }
    .server-address-click {
        font-family: var(--font-mono);
        font-size: 13px;
        color: #cbd5e1;
        font-weight: 500;
        padding: 4px 8px;
        border-radius: 4px;
        transition: background 0.15s ease, color 0.15s ease;
        cursor: pointer;
        user-select: all;
    }
    .server-address-click:hover {
        background: rgba(255, 255, 255, 0.08);
        color: #ffffff;
    }
    .server-stats-col {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1.2;
        justify-content: center;
    }
    .server-metric-tag {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 6px;
        padding: 5px 10px;
        font-size: 12px;
        color: var(--text-secondary);
        display: flex;
        gap: 6px;
        white-space: nowrap;
    }
    .server-metric-tag strong {
        color: #f1f5f9;
    }
    .server-status-col {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-shrink: 0;
    }
    .server-open-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: #94a3b8;
        font-size: 14px;
        transition: all 0.15s ease;
    }
    .server-row-item:hover .server-open-btn {
        background: #2563eb;
        border-color: #3b82f6;
        color: #ffffff;
    }
    @media (max-width: 900px) {
        .server-row-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }
        .server-stats-col {
            justify-content: flex-start;
            flex-wrap: wrap;
        }
        .server-status-col {
            width: 100%;
            justify-content: space-between;
        }
    }
    </style>

    <div class="server-list-container">
        <?php foreach ($servers as $srv): ?>
            <?php
                $status = $srv['status'] ?: 'offline';
                $badgeClass = match($status) {
                    'running' => 'badge-success',
                    'starting' => 'badge-warning',
                    'stopping', 'suspended' => 'badge-danger',
                    default => 'badge-secondary',
                };
                $statusDotColor = match($status) {
                    'running' => '#10b981',
                    'starting' => '#f59e0b',
                    'stopping', 'suspended' => '#ef4444',
                    default => '#64748b',
                };
                $ip = $srv['allocation_alias'] ?: $srv['allocation_ip'];
                $address = $ip ? "{$ip}:{$srv['allocation_port']}" : 'No allocation';
                $memVal = (int)($srv['memory'] ?? 0);
                $memStr = $memVal >= 1024 ? round($memVal / 1024, 1) . ' GB' : ($memVal > 0 ? $memVal . ' MB' : 'Unlimited');
                $diskVal = (int)($srv['disk'] ?? 0);
                $diskStr = $diskVal >= 1024 ? round($diskVal / 1024, 1) . ' GB' : ($diskVal > 0 ? $diskVal . ' MB' : 'Unlimited');
                $cpuVal = (int)($srv['cpu'] ?? 0);
                $cpuStr = $cpuVal > 0 ? $cpuVal . '%' : 'Unlimited';
                $description = trim((string)($srv['description'] ?? ''));
            ?>
            <a href="/server/<?= $srv['uuidShort'] ?>" class="server-row-item status-<?= $status ?>" id="server-row-<?= $srv['uuidShort'] ?>" data-server-card="true" data-server-uuid="<?= $srv['uuid'] ?>" data-server-short="<?= $srv['uuidShort'] ?>">
                <!-- Left: Title, Description & Metadata -->
                <div class="server-info-col">
                    <div class="server-title-text" title="<?= \Pterodactyl\Core\View::e($srv['name']) ?>">
                        <?= \Pterodactyl\Core\View::e($srv['name']) ?>
                    </div>
                    <div class="server-meta-text">
                        <span>Node: <strong style="color: #cbd5e1;"><?= \Pterodactyl\Core\View::e($srv['node_name'] ?? 'Node') ?></strong></span>
                        <span>&bull;</span>
                        <span style="font-family: var(--font-mono); font-size: 11px;"><?= $srv['uuidShort'] ?></span>
                        <?php if (!empty($description)): ?>
                            <span>&bull;</span>
                            <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px;"><?= \Pterodactyl\Core\View::e($description) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Middle 1: Connection IP (Click to copy) -->
                <div class="server-ip-col">
                    <span class="server-address-click" onclick="event.preventDefault(); event.stopPropagation(); App.copyToClipboard('<?= \Pterodactyl\Core\View::e($address) ?>', this);" data-tooltip="Click to copy IP">
                        <span><?= \Pterodactyl\Core\View::e($address) ?></span>
                        <svg class="server-address-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px; opacity: 0.6; vertical-align: middle;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                    </span>
                </div>

                <!-- Middle 2: Resource Limits (CPU, RAM, DISK) -->
                <div class="server-stats-col">
                    <div class="server-metric-tag">
                        <span>CPU</span>
                        <strong><?= $cpuStr ?></strong>
                    </div>
                    <div class="server-metric-tag">
                        <span>RAM</span>
                        <strong><?= $memStr ?></strong>
                    </div>
                    <div class="server-metric-tag">
                        <span>Disk</span>
                        <strong><?= $diskStr ?></strong>
                    </div>
                </div>

                <!-- Right: Status Badge & Open Action -->
                <div class="server-status-col">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span class="status-pulse-dot" id="dot-<?= $srv['uuidShort'] ?>" style="background-color: <?= $statusDotColor ?>;"></span>
                        <span class="badge <?= $badgeClass ?>" id="badge-<?= $srv['uuidShort'] ?>" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">
                            <?= \Pterodactyl\Core\View::e($status) ?>
                        </span>
                    </div>
                    <div class="server-open-btn">
                        &rarr;
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Live Server Status Verifier Script -->
    <script>
    (function() {
        const updateCardStatus = (shortId, status) => {
            const badge = document.getElementById(`badge-${shortId}`);
            const dot = document.getElementById(`dot-${shortId}`);
            const row = document.getElementById(`server-row-${shortId}`);
            if (!badge || !dot) return;

            badge.textContent = status;
            badge.className = 'badge';
            if (row) {
                row.classList.remove('status-running', 'status-starting', 'status-stopping', 'status-suspended', 'status-offline');
                row.classList.add(`status-${status}`);
            }

            if (status === 'running') {
                badge.classList.add('badge-success');
                dot.style.backgroundColor = '#10b981';
            } else if (status === 'starting') {
                badge.classList.add('badge-warning');
                dot.style.backgroundColor = '#f59e0b';
            } else if (status === 'stopping') {
                badge.classList.add('badge-danger');
                dot.style.backgroundColor = '#ef4444';
            } else {
                badge.classList.add('badge-secondary');
                dot.style.backgroundColor = '#64748b';
            }
        };

        const verifyServerStatus = async (card) => {
            const uuid = card.getAttribute('data-server-uuid');
            const shortId = card.getAttribute('data-server-short');
            if (!uuid || !shortId) return;

            try {
                const res = await App.request(`/api/client/servers/${uuid}/websocket`).catch(() => null);
                if (!res || !res.data || !res.data.socket || res.reachable === false || res.error) return;

                const ws = new WebSocket(res.data.socket);
                let receivedStatus = false;

                const timeout = setTimeout(() => {
                    if (!receivedStatus) {
                        try { ws.close(); } catch(e) {}
                    }
                }, 4000);

                ws.onopen = () => {
                    ws.send(JSON.stringify({ event: 'auth', args: [res.data.token] }));
                };

                ws.onmessage = (e) => {
                    try {
                        const msg = JSON.parse(e.data);
                        if (msg.event === 'status') {
                            receivedStatus = true;
                            clearTimeout(timeout);
                            const st = msg.args[0] || 'offline';
                            updateCardStatus(shortId, st);
                            App.request(`/api/client/servers/${uuid}/status`, {
                                method: 'POST',
                                body: { status: st }
                            }).catch(() => {});
                            ws.close();
                        }
                    } catch(err) {}
                };

                ws.onerror = () => {
                    clearTimeout(timeout);
                };
            } catch (err) {
                // If node is unreachable, status remains offline
            }
        };

        document.addEventListener('DOMContentLoaded', () => {
            const cards = document.querySelectorAll('[data-server-card="true"]');
            cards.forEach(c => verifyServerStatus(c));

            // Periodic refresh every 30 seconds
            setInterval(() => {
                if (!document.hidden) {
                    cards.forEach(c => verifyServerStatus(c));
                }
            }, 30000);
        });
    })();
    </script>
<?php endif; ?>
