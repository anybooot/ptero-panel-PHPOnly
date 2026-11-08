<?php $this->extend('layouts.server'); ?>

<div class="page-header" style="margin-bottom: 16px;">
    <div>
        <h1 class="page-title">Server Activity</h1>
        <p class="page-subtitle">Audit log of all actions and automated tasks executed for this server</p>
    </div>
</div>

<div class="card">
    <div class="card-header">Activity History</div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Event</th>
                        <th>IP Address</th>
                        <th>Metadata</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">No activity logs have been recorded yet for this server.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td style="font-weight: 600; color: #fff;">
                                    <?= \Pterodactyl\Core\View::e($l['actor_username'] ?: 'System / Daemon') ?>
                                </td>
                                <td>
                                    <span class="badge badge-secondary"><?= \Pterodactyl\Core\View::e($l['event']) ?></span>
                                </td>
                                <td style="font-family: var(--font-mono); font-size: 12px; color: var(--text-secondary);">
                                    <?= \Pterodactyl\Core\View::e($l['ip']) ?>
                                </td>
                                <td style="font-family: var(--font-mono); font-size: 11px; color: var(--text-muted); max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?= \Pterodactyl\Core\View::e($l['properties']) ?>
                                </td>
                                <td style="color: var(--text-secondary); font-size: 12px;">
                                    <?= date('M j, Y H:i:s', strtotime($l['timestamp'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
