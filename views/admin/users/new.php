<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Create New User</h1>
        <p class="page-subtitle">Add a new user to the system</p>
    </div>
    <div>
        <a href="/admin/users" class="btn btn-secondary btn-sm">Back to Users</a>
    </div>
</div>

<div class="card" style="max-width: 800px;">
    <div class="card-header">User Details</div>
    <form action="/admin/users/new" method="POST">
        <?= \Pterodactyl\Core\View::csrf() ?>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="usr-email">Email Address</label>
                    <input type="email" id="usr-email" name="email" class="form-control" required placeholder="user@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label" for="usr-username">Username</label>
                    <input type="text" id="usr-username" name="username" class="form-control" required placeholder="johndoe">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="usr-first">First Name</label>
                    <input type="text" id="usr-first" name="name_first" class="form-control" placeholder="John">
                </div>
                <div class="form-group">
                    <label class="form-label" for="usr-last">Last Name</label>
                    <input type="text" id="usr-last" name="name_last" class="form-control" placeholder="Doe">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="usr-password">Password</label>
                <input type="password" id="usr-password" name="password" class="form-control" required placeholder="••••••••">
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary); margin-top: 10px;">
                    <input type="checkbox" name="root_admin" value="1">
                    <span style="font-weight: 600;">Grant Root Administrator Privileges</span>
                </label>
                <small style="color: var(--text-muted); font-size: 12px; margin-top: 4px; display: block;">
                    Root administrators have unrestricted access to all nodes, servers, databases, and administrative configurations.
                </small>
            </div>
        </div>
        <div class="card-footer" style="text-align: right;">
            <button type="submit" class="btn btn-primary">Create User</button>
        </div>
    </form>
</div>
