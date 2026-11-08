<?php $this->extend('layouts.main'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Account Activity</h1>
        <p class="page-subtitle">Security log of actions executed on your account</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/account" class="btn btn-secondary btn-sm">Overview</a>
        <a href="/account/api" class="btn btn-secondary btn-sm">API Credentials</a>
        <a href="/account/ssh" class="btn btn-secondary btn-sm">SSH Keys</a>
        <a href="/account/activity" class="btn btn-primary btn-sm">Activity</a>
    </div>
</div>

<div class="card">
    <div class="card-header">Activity Timeline</div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>IP Address</th>
                        <th>Details</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 24px;">No account activity has been recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td style="font-weight: 600; color: #fff;"><?= \Pterodactyl\Core\View::e($l['event']) ?></td>
                                <td style="font-family: var(--font-mono); font-size: 12px; color: var(--text-secondary);"><?= \Pterodactyl\Core\View::e($l['ip']) ?></td>
                                <td style="font-family: var(--font-mono); font-size: 11px; color: var(--text-muted);"><?= \Pterodactyl\Core\View::e($l['properties']) ?></td>
                                <td style="color: var(--text-secondary);"><?= date('M j, Y H:i:s', strtotime($l['timestamp'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
