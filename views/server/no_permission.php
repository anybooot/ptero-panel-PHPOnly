<?php $this->extend('layouts.server'); ?>

<div class="card" style="max-width: 680px; margin: 40px auto; padding: 36px 28px; text-align: center; border-top: 4px solid #f59e0b; background: var(--bg-card, #131a26); box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
    <div style="width: 64px; height: 64px; margin: 0 auto 18px; border-radius: 50%; background: rgba(245, 158, 11, 0.12); display: flex; align-items: center; justify-content: center; font-size: 30px; color: #f59e0b;">
        🛡️
    </div>

    <h2 style="font-size: 22px; font-weight: 700; color: #fff; margin-bottom: 8px;">
        Access Restricted
    </h2>

    <p style="color: var(--text-secondary, #94a3b8); font-size: 14.5px; line-height: 1.6; margin-bottom: 18px;">
        <?= \Pterodactyl\Core\View::e($message ?? 'You do not have the required permission to access this section of the server.') ?>
    </p>

    <?php if (!empty($permission)): ?>
        <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.25); color: #fbbf24; font-family: var(--font-mono, monospace); font-size: 12px; padding: 6px 14px; border-radius: 6px; margin-bottom: 22px;">
            <span>Missing permission:</span>
            <strong><?= \Pterodactyl\Core\View::e($permission) ?></strong>
        </div>
    <?php endif; ?>

    <p style="color: var(--text-muted, #64748b); font-size: 13px; line-height: 1.5; margin-bottom: 26px;">
        If you require access to this feature or believe this is an error, please contact the server owner or an administrator to update your permissions.
    </p>

    <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
        <a href="/server/<?= $server['uuidShort'] ?>" class="btn btn-primary btn-sm" style="padding: 9px 20px;">
            Go to Console
        </a>
        <a href="/" class="btn btn-secondary btn-sm" style="padding: 9px 20px;">
            Back to Dashboard
        </a>
    </div>
</div>
