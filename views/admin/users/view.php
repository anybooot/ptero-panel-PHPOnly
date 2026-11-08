<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">User: <?= \Pterodactyl\Core\View::e($targetUser['username']) ?></h1>
        <p class="page-subtitle"><?= \Pterodactyl\Core\View::e($targetUser['email']) ?></p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/admin/users" class="btn btn-secondary btn-sm">Back to Users</a>
        <?php if (((int)$user['id']) !== ((int)$targetUser['id'])): ?>
            <form action="/admin/users/view/<?= $targetUser['id'] ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');" style="margin: 0;">
                <?= \Pterodactyl\Core\View::csrf() ?>
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="btn btn-danger btn-sm">Delete User</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- Edit User Details Form -->
    <div class="card">
        <div class="card-header">Account Details</div>
        <form action="/admin/users/view/<?= $targetUser['id'] ?>" method="POST">
            <?= \Pterodactyl\Core\View::csrf() ?>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="usr-email">Email Address</label>
                    <input type="email" id="usr-email" name="email" class="form-control" value="<?= \Pterodactyl\Core\View::e($targetUser['email']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="usr-username">Username</label>
                    <input type="text" id="usr-username" name="username" class="form-control" value="<?= \Pterodactyl\Core\View::e($targetUser['username']) ?>" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label class="form-label" for="usr-first">First Name</label>
                        <input type="text" id="usr-first" name="name_first" class="form-control" value="<?= \Pterodactyl\Core\View::e($targetUser['name_first'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="usr-last">Last Name</label>
                        <input type="text" id="usr-last" name="name_last" class="form-control" value="<?= \Pterodactyl\Core\View::e($targetUser['name_last'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="usr-pwd">New Password (Leave blank to keep current)</label>
                    <input type="password" id="usr-pwd" name="password" class="form-control" placeholder="••••••••">
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary); margin-top: 10px;">
                        <input type="checkbox" name="root_admin" value="1" <?= !empty($targetUser['root_admin']) ? 'checked' : '' ?>>
                        <span style="font-weight: 600;">Root Administrator</span>
                    </label>
                </div>
            </div>
            <div class="card-footer" style="text-align: right;">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    <!-- Owned Servers -->
    <div class="card">
        <div class="card-header">Servers Owned by this User</div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Server Name</th>
                            <th>Node</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($servers)): ?>
                            <tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 20px;">This user does not own any servers.</td></tr>
                        <?php else: ?>
                            <?php foreach ($servers as $s): ?>
                                <tr>
                                    <td>
                                        <a href="/admin/servers/view/<?= $s['id'] ?>" style="font-weight: 600; color: #fff;">
                                            <?= \Pterodactyl\Core\View::e($s['name']) ?>
                                        </a>
                                        <div style="font-size: 11px; color: var(--text-muted); font-family: var(--font-mono);"><?= \Pterodactyl\Core\View::e($s['uuidShort']) ?></div>
                                    </td>
                                    <td><?= \Pterodactyl\Core\View::e($s['node_name']) ?></td>
                                    <td style="text-align: right;">
                                        <a href="/admin/servers/view/<?= $s['id'] ?>" class="btn btn-secondary btn-sm">Manage</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
