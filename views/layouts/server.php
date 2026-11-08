<?php $this->extend('layouts.main'); ?>

<?php $this->start('subnav'); ?>
<nav class="subnav">
    <a href="/server/<?= $server['uuidShort'] ?>" class="<?= ($activeTab ?? '') === 'console' ? 'active' : '' ?>">Console</a>
    <a href="/server/<?= $server['uuidShort'] ?>/files" class="<?= ($activeTab ?? '') === 'files' ? 'active' : '' ?>">Files</a>
    <a href="/server/<?= $server['uuidShort'] ?>/databases" class="<?= ($activeTab ?? '') === 'databases' ? 'active' : '' ?>">Databases</a>
    <a href="/server/<?= $server['uuidShort'] ?>/schedules" class="<?= ($activeTab ?? '') === 'schedules' ? 'active' : '' ?>">Schedules</a>
    <a href="/server/<?= $server['uuidShort'] ?>/users" class="<?= ($activeTab ?? '') === 'users' ? 'active' : '' ?>">Users</a>
    <a href="/server/<?= $server['uuidShort'] ?>/backups" class="<?= ($activeTab ?? '') === 'backups' ? 'active' : '' ?>">Backups</a>
    <a href="/server/<?= $server['uuidShort'] ?>/network" class="<?= ($activeTab ?? '') === 'network' ? 'active' : '' ?>">Network</a>
    <a href="/server/<?= $server['uuidShort'] ?>/startup" class="<?= ($activeTab ?? '') === 'startup' ? 'active' : '' ?>">Startup</a>
    <a href="/server/<?= $server['uuidShort'] ?>/settings" class="<?= ($activeTab ?? '') === 'settings' ? 'active' : '' ?>">Settings</a>
    <a href="/server/<?= $server['uuidShort'] ?>/activity" class="<?= ($activeTab ?? '') === 'activity' ? 'active' : '' ?>">Activity</a>
    <?php if (!empty($user['root_admin'])): ?>
        <a href="/admin/servers/view/<?= $server['id'] ?>" target="_blank" style="color: #60a5fa;" title="View in Admin Panel">↗ Admin</a>
    <?php endif; ?>
</nav>
<?php $this->end(); ?>

<?= $content ?>
