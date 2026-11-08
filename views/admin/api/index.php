<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Application API</h1>
        <p class="page-subtitle">Manage administrative API credentials for external integrations, billing software (WHMCS, Blesta), and automated scripts</p>
    </div>
    <div>
        <a href="/admin/api/new" class="btn btn-primary btn-sm">Create API Key</a>
    </div>
</div>

<?php if (!empty($newKey)): ?>
    <div class="alert alert-success" style="display: block; margin-bottom: 20px;">
        <div style="font-weight: 700; margin-bottom: 6px;">Your Application API Key has been generated:</div>
        <div style="background-color: var(--bg-base); padding: 10px; border-radius: 4px; font-family: var(--font-mono); font-size: 13px; color: #10b981; word-break: break-all; user-select: all;">
            <?= \Pterodactyl\Core\View::e($newKey) ?>
        </div>
        <small style="color: var(--text-secondary); display: block; margin-top: 6px;">
            Copy this key immediately. For security reasons, it cannot and will not be displayed again.
        </small>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Identifier</th>
                        <th>Description (Memo)</th>
                        <th>Allowed IPs</th>
                        <th>Last Used</th>
                        <th>Created</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($keys)): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">No application API keys generated yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($keys as $k): ?>
                            <tr>
                                <td>
                                    <code style="font-family: var(--font-mono); font-size: 12px; color: #60a5fa;">
                                        <?= \Pterodactyl\Core\View::e($k['identifier']) ?>
                                    </code>
                                </td>
                                <td><strong style="color: #fff;"><?= \Pterodactyl\Core\View::e($k['memo']) ?></strong></td>
                                <td>
                                    <?php 
                                    $ips = !empty($k['allowed_ips']) ? json_decode($k['allowed_ips'], true) : [];
                                    if (!empty($ips)):
                                    ?>
                                        <span style="font-family: var(--font-mono); font-size: 11px;"><?= implode(', ', array_map([\Pterodactyl\Core\View::class, 'e'], $ips)) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Any IP</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= \Pterodactyl\Core\View::e($k['last_used_at'] ?? 'Never') ?></td>
                                <td><?= \Pterodactyl\Core\View::e($k['created_at']) ?></td>
                                <td style="text-align: right;">
                                    <form action="/admin/api/<?= $k['identifier'] ?>" method="POST" onsubmit="return confirm('Revoke and delete this API key? External systems using it will lose access immediately.');" style="margin: 0;">
                                        <?= \Pterodactyl\Core\View::csrf() ?>
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button type="submit" class="btn btn-danger btn-sm" style="padding: 2px 8px;">Revoke</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
