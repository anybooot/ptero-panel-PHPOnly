<?php $this->extend('layouts.server'); ?>

<div class="page-header" style="margin-bottom: 16px;">
    <div>
        <h1 class="page-title">Server Activity</h1>
        <p class="page-subtitle">Audit log of all actions and automated tasks executed for this server</p>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <span>Activity History</span>
        <span style="font-size: 12px; color: var(--text-muted); font-weight: normal;">
            Showing <?= count($logs) ?> of <?= (int)($total ?? count($logs)) ?> entries (Page <?= (int)($page ?? 1) ?> of <?= (int)($totalPages ?? 1) ?>)
        </span>
    </div>
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
    <?php if (($totalPages ?? 1) > 1): ?>
        <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 18px;">
            <div style="font-size: 13px; color: var(--text-secondary);">
                Page <strong><?= (int)$page ?></strong> of <strong><?= (int)$totalPages ?></strong>
            </div>
            <div style="display: flex; gap: 8px;">
                <?php if ($page > 1): ?>
                    <a href="/server/<?= $server['uuidShort'] ?>/activity?page=1" class="btn btn-secondary btn-sm" title="First Page">&laquo;</a>
                    <a href="/server/<?= $server['uuidShort'] ?>/activity?page=<?= $page - 1 ?>" class="btn btn-secondary btn-sm">&larr; Previous</a>
                <?php else: ?>
                    <button class="btn btn-secondary btn-sm" disabled>&larr; Previous</button>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="/server/<?= $server['uuidShort'] ?>/activity?page=<?= $page + 1 ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
                    <a href="/server/<?= $server['uuidShort'] ?>/activity?page=<?= $totalPages ?>" class="btn btn-secondary btn-sm" title="Last Page">&raquo;</a>
                <?php else: ?>
                    <button class="btn btn-secondary btn-sm" disabled>Next &rarr;</button>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
