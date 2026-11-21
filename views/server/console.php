<?php $this->extend('layouts.server'); ?>

<div class="console-layout-grid">
    <!-- Left Sidebar Box: Server info, IP, Resource widgets & Power controls -->
    <div class="console-sidebar-panel">
        <!-- Server Title & Live Status -->
        <div class="sidebar-block sidebar-header-block">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 6px;">
                <h1 class="page-title" style="margin: 0; font-size: 18px; font-weight: 800; word-break: break-all;"><?= \Pterodactyl\Core\View::e($server['name']) ?></h1>
                <span id="stat-status-dot" class="status-pulse-dot" style="background-color: <?= ($server['status'] === 'running' ? '#10b981' : ($server['status'] === 'starting' ? '#f59e0b' : '#ef4444')) ?>;"></span>
            </div>
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                <span id="server-status-badge" class="badge badge-secondary" style="font-size: 11px; text-transform: uppercase;"><?= \Pterodactyl\Core\View::e($server['status'] ?: 'offline') ?></span>
                <span style="font-size: 11.5px; color: var(--text-muted);">Node: <strong style="color: #cbd5e1;"><?= \Pterodactyl\Core\View::e($node['name']) ?></strong></span>
            </div>
        </div>

        <!-- IP Address Box with Click to Copy -->
        <?php $allocAddr = $allocation ? ($allocation['ip_alias'] ?: $allocation['ip']) . ':' . $allocation['port'] : 'No allocation'; ?>
        <div class="sidebar-block">
            <div class="sidebar-stat-label">Connection Address</div>
            <div id="btn-copy-server-ip" class="console-ip-card" onclick="copyConsoleIp('<?= \Pterodactyl\Core\View::e($allocAddr) ?>')" data-tooltip="Click to copy IP:Port">
                <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                    <span style="font-size: 13px; opacity: 0.8;">🌐</span>
                    <span id="console-ip-text" style="font-family: var(--font-mono); font-size: 12.5px; font-weight: 600; color: #eceff4; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= \Pterodactyl\Core\View::e($allocAddr) ?></span>
                </div>
                <span id="console-ip-copy-icon" style="color: var(--text-muted); display: inline-flex; align-items: center; flex-shrink: 0;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></span>
            </div>
        </div>

        <!-- Resource Usage Widgets (RAM, CPU, DISK) -->
        <div class="sidebar-block">
            <div class="sidebar-stat-label">Live Usage</div>
            <div class="sidebar-stats-stack">
                <!-- RAM Box -->
                <div class="sidebar-stat-item">
                    <div class="sidebar-stat-item-header">
                        <span class="stat-tag">RAM</span>
                        <span class="stat-value">
                            <span id="top-stat-ram">0 MiB</span>
                            <span class="stat-limit">/ <?= (int)$server['memory'] > 0 ? (int)$server['memory'] . ' MiB' : '∞' ?></span>
                        </span>
                    </div>
                </div>

                <!-- CPU Box -->
                <div class="sidebar-stat-item">
                    <div class="sidebar-stat-item-header">
                        <span class="stat-tag">CPU</span>
                        <span class="stat-value">
                            <span id="top-stat-cpu">0%</span>
                            <span class="stat-limit">/ <?= (int)$server['cpu'] > 0 ? (int)$server['cpu'] . '%' : '∞' ?></span>
                        </span>
                    </div>
                </div>

                <!-- DISK Box -->
                <div class="sidebar-stat-item">
                    <div class="sidebar-stat-item-header">
                        <span class="stat-tag">DISK</span>
                        <span class="stat-value">
                            <span id="top-stat-disk">--</span>
                            <span class="stat-limit">/ <?= (int)$server['disk'] > 0 ? (int)$server['disk'] . ' MiB' : '∞' ?></span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Power Action Buttons -->
        <div class="sidebar-block sidebar-power-block">
            <div class="sidebar-stat-label">Power Control</div>
            <div class="console-power-grid">
                <button class="btn btn-success" data-power-action="start">
                    <span>▶</span> Start
                </button>
                <button class="btn btn-secondary" data-power-action="restart">
                    <span>↻</span> Restart
                </button>
                <button class="btn btn-danger" data-power-action="stop">
                    <span>⏹</span> Stop
                </button>
                <button class="btn btn-danger btn-kill" data-power-action="kill">
                    <span>⚡</span> Kill
                </button>
            </div>
        </div>
    </div>

    <!-- Right Column: Terminal Console Window & Real-time Charts -->
    <div class="console-main-panel">
        <!-- Terminal Box -->
        <div class="terminal-wrapper" style="margin-bottom: 20px;">
            <div class="terminal-header-bar" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 14px; background: #121319; border-bottom: 1px solid var(--border-color); border-radius: var(--radius-md) var(--radius-md) 0 0;">
                <span style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.06em;">Server Console</span>
                <button type="button" id="btn-clear-console" class="btn btn-secondary btn-sm" style="padding: 2px 10px; font-size: 11px; line-height: 1.4; display: inline-flex; align-items: center; gap: 5px;" data-tooltip="Clear console output">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    <span>Clear</span>
                </button>
            </div>
            <div id="terminal-window" class="terminal-window" style="border-top-left-radius: 0; border-top-right-radius: 0;"></div>
            <div class="terminal-input-bar">
                <span class="terminal-prompt-icon">&gt;</span>
                <input type="text" id="terminal-command-input" class="terminal-input" placeholder="Type a command and press Enter... (or 'clear' to clear console)" autocomplete="off" spellcheck="false">
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
    </div>
</div>

<script>
function copyConsoleIp(addr) {
    App.copyToClipboard(addr, document.getElementById('btn-copy-server-ip'));
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
