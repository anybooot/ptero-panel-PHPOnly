<?php $this->extend('layouts.server'); ?>

<!-- Modern Upper Left Stats & Power Header (Point 17) -->
<div class="console-top-container" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 18px;">
    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
        <!-- Server Title & Status -->
        <div style="display: flex; align-items: center; gap: 8px; margin-right: 6px;">
            <span id="stat-status-dot" class="status-pulse-dot" style="background-color: <?= ($server['status'] === 'running' ? '#10b981' : ($server['status'] === 'starting' ? '#f59e0b' : '#ef4444')) ?>;"></span>
            <h1 class="page-title" style="margin: 0; font-size: 20px; font-weight: 800;"><?= \Pterodactyl\Core\View::e($server['name']) ?></h1>
            <span id="server-status-badge" class="badge badge-secondary" style="font-size: 11px; text-transform: uppercase;"><?= \Pterodactyl\Core\View::e($server['status'] ?: 'offline') ?></span>
        </div>

        <!-- IP Address with Click to Copy (Point 17) -->
        <?php $allocAddr = $allocation ? ($allocation['ip_alias'] ?: $allocation['ip']) . ':' . $allocation['port'] : 'No allocation'; ?>
        <div id="btn-copy-server-ip" onclick="copyConsoleIp('<?= \Pterodactyl\Core\View::e($allocAddr) ?>')" title="Click to copy IP:Port" style="cursor: pointer; background: #181920; border: 1px solid var(--border-color); border-radius: 8px; padding: 6px 12px; display: inline-flex; align-items: center; gap: 8px; transition: border-color 0.15s ease;" onmouseenter="this.style.borderColor='rgba(255,255,255,0.2)'" onmouseleave="this.style.borderColor='var(--border-color)'">
            <span style="font-size: 13px;">🌐</span>
            <span id="console-ip-text" style="font-family: var(--font-mono); font-size: 12.5px; font-weight: 600; color: #eceff4;"><?= \Pterodactyl\Core\View::e($allocAddr) ?></span>
            <span id="console-ip-copy-icon" style="color: var(--text-muted); font-size: 11px;">📋</span>
        </div>

        <!-- Live RAM Widget (Point 17) -->
        <div style="background: #181920; border: 1px solid var(--border-color); border-radius: 8px; padding: 6px 12px; display: inline-flex; align-items: center; gap: 8px;">
            <span style="color: #10b981; font-size: 11.5px; font-weight: 700;">RAM</span>
            <span style="font-family: var(--font-mono); font-size: 12px; font-weight: 600; color: #eceff4;">
                <span id="top-stat-ram">0 MiB</span>
                <span style="color: var(--text-muted); font-weight: 400; font-size: 11px;">/ <?= (int)$server['memory'] > 0 ? (int)$server['memory'] . ' MiB' : '∞' ?></span>
            </span>
        </div>

        <!-- Live CPU Widget (Point 17) -->
        <div style="background: #181920; border: 1px solid var(--border-color); border-radius: 8px; padding: 6px 12px; display: inline-flex; align-items: center; gap: 8px;">
            <span style="color: #3b82f6; font-size: 11.5px; font-weight: 700;">CPU</span>
            <span style="font-family: var(--font-mono); font-size: 12px; font-weight: 600; color: #eceff4;">
                <span id="top-stat-cpu">0%</span>
                <span style="color: var(--text-muted); font-weight: 400; font-size: 11px;">/ <?= (int)$server['cpu'] > 0 ? (int)$server['cpu'] . '%' : '∞' ?></span>
            </span>
        </div>

        <!-- Live Disk Widget (Point 17) -->
        <div style="background: #181920; border: 1px solid var(--border-color); border-radius: 8px; padding: 6px 12px; display: inline-flex; align-items: center; gap: 8px;">
            <span style="color: #f59e0b; font-size: 11.5px; font-weight: 700;">DISK</span>
            <span style="font-family: var(--font-mono); font-size: 12px; font-weight: 600; color: #eceff4;">
                <span id="top-stat-disk">--</span>
                <span style="color: var(--text-muted); font-weight: 400; font-size: 11px;">/ <?= (int)$server['disk'] > 0 ? (int)$server['disk'] . ' MiB' : '∞' ?></span>
            </span>
        </div>

        <!-- Node Widget -->
        <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 8px; padding: 6px 10px; font-size: 11.5px; color: var(--text-muted);">
            Node: <span style="color: #cbd5e1;"><?= \Pterodactyl\Core\View::e($node['name']) ?></span>
        </div>
    </div>

    <!-- Power Action Buttons -->
    <div style="display: flex; gap: 8px;">
        <button class="btn btn-success" data-power-action="start">Start</button>
        <button class="btn btn-secondary" data-power-action="restart">Restart</button>
        <button class="btn btn-danger" data-power-action="stop">Stop</button>
        <button class="btn btn-danger" style="background-color: #991b1b; border-color: #7f1d1d;" data-power-action="kill">Kill</button>
    </div>
</div>

<!-- Terminal Box -->
<div class="terminal-wrapper">
    <div id="terminal-window" class="terminal-window"></div>
    <div class="terminal-input-bar">
        <span class="terminal-prompt-icon">&gt;</span>
        <input type="text" id="terminal-command-input" class="terminal-input" placeholder="Type a command and press Enter..." autocomplete="off" spellcheck="false">
    </div>
</div>

<!-- Resource Graphs Grid -->
<div class="graphs-grid">
    <div class="graph-card">
        <div class="graph-header">
            <span class="graph-title">CPU Load</span>
            <span id="stat-cpu-val" class="graph-val">0%</span>
        </div>
        <div style="height: 90px; width: 100%;">
            <canvas id="chart-cpu" class="graph-canvas"></canvas>
        </div>
    </div>

    <div class="graph-card">
        <div class="graph-header">
            <span class="graph-title">Memory Usage</span>
            <span id="stat-ram-val" class="graph-val">0 MiB</span>
        </div>
        <div style="height: 90px; width: 100%;">
            <canvas id="chart-ram" class="graph-canvas"></canvas>
        </div>
    </div>

    <div class="graph-card">
        <div class="graph-header">
            <span class="graph-title">Network In / Out</span>
            <span id="stat-net-val" class="graph-val">0 KiB/s</span>
        </div>
        <div style="height: 90px; width: 100%;">
            <canvas id="chart-net" class="graph-canvas"></canvas>
        </div>
    </div>
</div>

<script>
function copyConsoleIp(addr) {
    if (!addr || addr === 'No allocation') return;
    navigator.clipboard.writeText(addr).then(() => {
        App.toast(`IP Address ${addr} copied to clipboard!`, 'success');
        const icon = document.getElementById('console-ip-copy-icon');
        if (icon) {
            icon.textContent = '✓';
            setTimeout(() => icon.textContent = '📋', 1500);
        }
    }).catch(() => {
        App.toast('Failed to copy IP', 'danger');
    });
}

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Live Charts
    if (window.ServerStats) {
        window.ServerStats.init({
            cpu: <?= (int)$server['cpu'] ?>,
            memory: <?= (int)$server['memory'] ?>
        });
    }

    // Initialize WebSocket Console
    if (window.ServerConsole) {
        new ServerConsole('<?= $server['uuid'] ?>', <?= (int)$server['id'] ?>);
    }
});
</script>
