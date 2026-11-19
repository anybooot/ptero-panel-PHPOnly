<?php $this->extend('layouts.main'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Servers</h1>
        <p class="page-subtitle">Showing all servers available to your account</p>
    </div>
    <div>
        <form method="GET" action="/" style="display: flex; gap: 8px;">
            <input type="text" name="search" class="form-control" placeholder="Search servers..." value="<?= \Pterodactyl\Core\View::e($search ?? '') ?>" style="width: 240px;">
            <button type="submit" class="btn btn-secondary">Search</button>
            <?php if (!empty($search)): ?>
                <a href="/" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (empty($servers)): ?>
    <div class="card" style="text-align: center; padding: 48px 20px;">
        <div style="font-size: 32px; margin-bottom: 12px; color: var(--text-muted);">🎮</div>
        <h3 style="color: #fff; font-size: 16px; margin-bottom: 6px;">There are no servers associated with your account.</h3>
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
                $ip = $srv['allocation_alias'] ?: $srv['allocation_ip'];
                $address = $ip ? "{$ip}:{$srv['allocation_port']}" : 'No allocation';
                $memVal = (int)($srv['memory'] ?? 0);
                $memStr = $memVal >= 1024 ? round($memVal / 1024, 1) . ' GB' : ($memVal > 0 ? $memVal . ' MB' : 'Unlimited');
                $diskVal = (int)($srv['disk'] ?? 0);
                $diskStr = $diskVal >= 1024 ? round($diskVal / 1024, 1) . ' GB' : ($diskVal > 0 ? $diskVal . ' MB' : 'Unlimited');
                $cpuVal = (int)($srv['cpu'] ?? 0);
                $cpuStr = $cpuVal > 0 ? $cpuVal . '%' : 'Unlimited';
            ?>
            <a href="/server/<?= $srv['uuidShort'] ?>" class="server-card">
                <div>
                    <div class="server-card-header">
                        <div>
                            <div class="server-card-title"><?= \Pterodactyl\Core\View::e($srv['name']) ?></div>
                            <div class="server-card-sub">
                                <span><?= \Pterodactyl\Core\View::e($address) ?></span>
                                <span style="opacity: 0.4;">&bull;</span>
                                <span><?= \Pterodactyl\Core\View::e($srv['node_name'] ?? 'Node') ?></span>
                            </div>
                        </div>
                        <span class="badge <?= $badgeClass ?>">
                            <?= \Pterodactyl\Core\View::e(strtoupper($status)) ?>
                        </span>
                    </div>
                </div>

                <div class="server-card-stats">
                    <div>
                        <div class="server-stat-val"><?= $cpuStr ?></div>
                        <div class="server-stat-lbl">CPU Limit</div>
                    </div>
                    <div>
                        <div class="server-stat-val"><?= $memStr ?></div>
                        <div class="server-stat-lbl">Memory</div>
                    </div>
                    <div>
                        <div class="server-stat-val"><?= $diskStr ?></div>
                        <div class="server-stat-lbl">Disk</div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
