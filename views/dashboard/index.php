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
    <div class="server-grid">
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
            <a href="/server/<?= $srv['uuidShort'] ?>" class="server-card-modern">
                <!-- Header with Server Name & Status -->
                <div class="server-card-top">
                    <div style="display: flex; align-items: flex-start; gap: 14px; min-width: 0;">
                        <div class="server-avatar-box">
                            <span style="font-size: 20px;">🖥️</span>
                        </div>
                        <div style="min-width: 0; flex: 1;">
                            <div class="server-modern-title" title="<?= \Pterodactyl\Core\View::e($srv['name']) ?>">
                                <?= \Pterodactyl\Core\View::e($srv['name']) ?>
                            </div>
                            <div class="server-modern-desc">
                                <?= !empty($description) ? \Pterodactyl\Core\View::e($description) : '<span style="color: var(--text-muted); font-style: italic;">No description provided</span>' ?>
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
                        <span class="status-pulse-dot" style="background-color: <?= $statusDotColor ?>;"></span>
                        <span class="badge <?= $badgeClass ?>" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">
                            <?= \Pterodactyl\Core\View::e($status) ?>
                        </span>
                    </div>
                </div>

                <!-- Middle: Connection & Node -->
                <div class="server-card-mid">
                    <div class="server-ip-pill" onclick="event.preventDefault(); event.stopPropagation(); navigator.clipboard.writeText('<?= \Pterodactyl\Core\View::e($address) ?>'); App.toast('Address copied to clipboard!', 'success');" title="Click to copy IP:Port">
                        <span style="font-family: var(--font-mono); font-size: 12px; color: #eceff4; font-weight: 600;"><?= \Pterodactyl\Core\View::e($address) ?></span>
                        <span style="opacity: 0.6; font-size: 11px;">📋</span>
                    </div>
                    <div class="server-node-pill">
                        <span style="font-size: 11.5px; color: var(--text-secondary);"><?= \Pterodactyl\Core\View::e($srv['node_name'] ?? 'Node') ?></span>
                    </div>
                </div>

                <!-- Footer: Resource Limits -->
                <div class="server-card-bottom">
                    <div class="server-mini-stat">
                        <span class="stat-lbl">CPU</span>
                        <span class="stat-num"><?= $cpuStr ?></span>
                    </div>
                    <div class="server-mini-stat">
                        <span class="stat-lbl">Memory</span>
                        <span class="stat-num"><?= $memStr ?></span>
                    </div>
                    <div class="server-mini-stat">
                        <span class="stat-lbl">Disk</span>
                        <span class="stat-num"><?= $diskStr ?></span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
