<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Users</h1>
        <p class="page-subtitle">Manage user accounts, administrative permissions, and server ownership</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/admin/users/new" class="btn btn-primary btn-sm">Create New User</a>
    </div>
</div>

<!-- Search Bar -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 12px 16px;">
        <form action="/admin/users" method="GET" style="display: flex; gap: 10px; margin: 0;">
            <input type="text" name="search" class="form-control" placeholder="Search users by username, email, or name..." value="<?= \Pterodactyl\Core\View::e($search ?? '') ?>" style="flex: 1;">
            <button type="submit" class="btn btn-secondary">Search</button>
            <?php if (!empty($search)): ?>
                <a href="/admin/users" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>2FA</th>
                        <th>Servers</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 24px;">No users found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= (int)$u['id'] ?></td>
                                <td>
                                    <a href="/admin/users/view/<?= $u['id'] ?>" style="font-weight: 600; color: #fff;">
                                        <?= \Pterodactyl\Core\View::e($u['username']) ?>
                                    </a>
                                    <div style="font-size: 12px; color: var(--text-secondary);"><?= \Pterodactyl\Core\View::e(($u['name_first'] ?? '') . ' ' . ($u['name_last'] ?? '')) ?></div>
                                </td>
                                <td><?= \Pterodactyl\Core\View::e($u['email']) ?></td>
                                <td>
                                    <?php if (!empty($u['root_admin'])): ?>
                                        <span class="btn-admin-badge">ROOT ADMIN</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">User</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($u['totp_authenticated'])): ?>
                                        <span class="badge badge-success">Enabled</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Disabled</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)$u['servers_count'] ?></td>
                                <td style="text-align: right;">
                                    <a href="/admin/users/view/<?= $u['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
