<?php $this->extend('layouts.main'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Account Activity</h1>
        <p class="page-subtitle">Security log of actions executed on your account and servers</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/account" class="btn btn-secondary btn-sm">Overview</a>
        <a href="/account/api" class="btn btn-secondary btn-sm">API Credentials</a>
        <a href="/account/ssh" class="btn btn-secondary btn-sm">SSH Keys</a>
        <a href="/account/activity" class="btn btn-primary btn-sm">Activity</a>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <span>Activity Timeline</span>
        <span style="font-size: 12px; color: var(--text-muted); font-weight: normal;">Showing last <?= count($logs) ?> actions</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 280px;">Action / Event</th>
                        <th>Target / Details</th>
                        <th style="width: 160px;">IP Address</th>
                        <th style="width: 180px; text-align: right;">When</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 48px 24px;">
                                <div style="font-size: 32px; margin-bottom: 8px; opacity: 0.5;">📜</div>
                                <div style="font-size: 15px; font-weight: 600; color: #fff; margin-bottom: 4px;">No account activity recorded yet</div>
                                <div style="font-size: 13px; color: var(--text-secondary);">Actions performed on your account and servers will automatically appear here.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <?php
                                $props = json_decode((string)($l['properties'] ?? ''), true) ?: [];
                                $event = (string)$l['event'];
                                $badgeColor = '#3b82f6';
                                if (str_starts_with($event, 'auth:login') || str_contains($event, 'create')) {
                                    $badgeColor = '#10b981';
                                } elseif (str_contains($event, 'delete') || str_contains($event, 'kill') || str_starts_with($event, 'auth:logout')) {
                                    $badgeColor = '#ef4444';
                                } elseif (str_contains($event, 'password') || str_contains($event, 'rotate') || str_contains($event, 'restart')) {
                                    $badgeColor = '#f59e0b';
                                }

                                $humanEvent = \Pterodactyl\Core\ActivityLogger::formatEvent($event);
                                $timeStr = strtotime($l['timestamp']);
                                $diff = time() - $timeStr;
                                if ($diff < 60) {
                                    $relative = 'Just now';
                                } elseif ($diff < 3600) {
                                    $relative = floor($diff / 60) . 'm ago';
                                } elseif ($diff < 86400) {
                                    $relative = floor($diff / 3600) . 'h ago';
                                } else {
                                    $relative = floor($diff / 86400) . 'd ago';
                                }
                            ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 8px; height: 8px; border-radius: 50%; background: <?= $badgeColor ?>; flex-shrink: 0;"></div>
                                        <div>
                                            <div style="font-weight: 600; color: #fff; font-size: 13px;">
                                                <?= \Pterodactyl\Core\View::e($humanEvent) ?>
                                            </div>
                                            <div style="font-family: var(--font-mono); font-size: 11px; color: var(--text-muted);">
                                                <?= \Pterodactyl\Core\View::e($event) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($l['server_name'])): ?>
                                        <span class="badge" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); margin-right: 6px; font-size: 11px; padding: 2px 6px; border-radius: 4px;">
                                            <?= \Pterodactyl\Core\View::e($l['server_name']) ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($props)): ?>
                                        <span style="font-family: var(--font-mono); font-size: 12px; color: var(--text-secondary);">
                                            <?php
                                                $formattedProps = [];
                                                foreach ($props as $k => $v) {
                                                    if (is_scalar($v)) {
                                                        $formattedProps[] = "<strong>" . \Pterodactyl\Core\View::e((string)$k) . ":</strong> " . \Pterodactyl\Core\View::e((string)$v);
                                                    }
                                                }
                                                echo implode(', ', $formattedProps);
                                            ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 12px;">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-family: var(--font-mono); font-size: 12px; color: var(--text-secondary);">
                                    <?= \Pterodactyl\Core\View::e($l['ip']) ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="color: #fff; font-size: 12px; font-weight: 500;" title="<?= date('Y-m-d H:i:s T', $timeStr) ?>">
                                        <?= $relative ?>
                                    </div>
                                    <div style="color: var(--text-muted); font-size: 11px;">
                                        <?= date('M j, Y H:i', $timeStr) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
