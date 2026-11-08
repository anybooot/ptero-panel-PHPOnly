<?php $this->extend('layouts.server'); ?>

<div class="page-header" style="margin-bottom: 16px;">
    <div>
        <div style="display: flex; align-items: center; gap: 12px;">
            <h1 class="page-title"><?= \Pterodactyl\Core\View::e($server['name']) ?></h1>
            <span id="server-status-badge" class="badge badge-secondary"><?= \Pterodactyl\Core\View::e($server['status'] ?: 'offline') ?></span>
        </div>
        <p class="page-subtitle">
            <?= \Pterodactyl\Core\View::e($allocation ? ($allocation['ip_alias'] ?: $allocation['ip']) . ':' . $allocation['port'] : 'No allocation') ?>
            &bull; Node: <?= \Pterodactyl\Core\View::e($node['name']) ?>
        </p>
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
