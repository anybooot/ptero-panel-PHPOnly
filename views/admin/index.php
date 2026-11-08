<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Administrative Control Panel</h1>
        <p class="page-subtitle">Pterodactyl Panel &bull; Pure PHP 8.2+ Architecture</p>
    </div>
</div>

<!-- Stats Grid -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
    <div class="card" style="padding: 20px; text-align: center;">
        <div style="font-size: 32px; font-weight: 800; color: #3b82f6;"><?= $serversCount ?></div>
        <div style="font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; margin-top: 4px;">Total Servers</div>
    </div>
    <div class="card" style="padding: 20px; text-align: center;">
        <div style="font-size: 32px; font-weight: 800; color: #10b981;"><?= $nodesCount ?></div>
        <div style="font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; margin-top: 4px;">Active Nodes</div>
    </div>
    <div class="card" style="padding: 20px; text-align: center;">
        <div style="font-size: 32px; font-weight: 800; color: #f59e0b;"><?= $usersCount ?></div>
        <div style="font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; margin-top: 4px;">Registered Users</div>
    </div>
    <div class="card" style="padding: 20px; text-align: center;">
        <div style="font-size: 32px; font-weight: 800; color: #a855f7;"><?= $allocationsCount ?></div>
        <div style="font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; margin-top: 4px;">Total Allocations</div>
    </div>
</div>

<div class="card">
    <div class="card-header">System Information &amp; Architecture</div>
    <div class="card-body">
        <p style="color: #fff; margin-bottom: 12px;">
            This installation of Pterodactyl Panel runs on an ultra-optimized native stack:
        </p>
        <ul style="color: var(--text-secondary); margin-left: 20px; line-height: 1.8;">
            <li><strong>Runtime:</strong> PHP <?= phpversion() ?> (Native PSR-4 Autoloading, Zero Framework Overhead)</li>
            <li><strong>Frontend:</strong> HTML5, CSS3 Dark Theme, Vanilla JavaScript (ES6+)</li>
            <li><strong>Real-time:</strong> Direct WebSockets to Pterodactyl Wings Daemon &amp; HTML5 Canvas Graphs</li>
            <li><strong>Database:</strong> Native MySQL / MariaDB PDO with Prepared Statements &amp; Transactions</li>
            <li><strong>Wings Integration:</strong> 100% Protocol Compatible HTTP REST &amp; Signed JWT Authorization</li>
        </ul>
    </div>
</div>
