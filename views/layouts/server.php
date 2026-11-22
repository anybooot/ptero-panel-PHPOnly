<?php $this->extend('layouts.main'); ?>

<?php $this->start('subnav'); ?>
<nav class="subnav">
    <?php if (\Pterodactyl\Security\Gate::allows($user, $server, 'control.console') || \Pterodactyl\Security\Gate::allows($user, $server, 'control.start') || \Pterodactyl\Security\Gate::allows($user, $server, 'control.restart') || \Pterodactyl\Security\Gate::allows($user, $server, 'control.stop')): ?>
        <a href="/server/<?= $server['uuidShort'] ?>" class="<?= ($activeTab ?? '') === 'console' ? 'active' : '' ?>">Console</a>
    <?php endif; ?>
    <?php if (\Pterodactyl\Security\Gate::allows($user, $server, 'file.read')): ?>
        <a href="/server/<?= $server['uuidShort'] ?>/files" class="<?= ($activeTab ?? '') === 'files' ? 'active' : '' ?>">Files</a>
    <?php endif; ?>
    <?php if (\Pterodactyl\Security\Gate::allows($user, $server, 'database.read')): ?>
        <a href="/server/<?= $server['uuidShort'] ?>/databases" class="<?= ($activeTab ?? '') === 'databases' ? 'active' : '' ?>">Databases</a>
    <?php endif; ?>
    <?php if (\Pterodactyl\Security\Gate::allows($user, $server, 'schedule.read')): ?>
        <a href="/server/<?= $server['uuidShort'] ?>/schedules" class="<?= ($activeTab ?? '') === 'schedules' ? 'active' : '' ?>">Schedules</a>
    <?php endif; ?>
    <?php if (\Pterodactyl\Security\Gate::allows($user, $server, 'user.read')): ?>
        <a href="/server/<?= $server['uuidShort'] ?>/users" class="<?= ($activeTab ?? '') === 'users' ? 'active' : '' ?>">Users</a>
    <?php endif; ?>
    <?php if (\Pterodactyl\Security\Gate::allows($user, $server, 'backup.read')): ?>
        <a href="/server/<?= $server['uuidShort'] ?>/backups" class="<?= ($activeTab ?? '') === 'backups' ? 'active' : '' ?>">Backups</a>
    <?php endif; ?>
    <?php if (\Pterodactyl\Security\Gate::allows($user, $server, 'allocation.read')): ?>
        <a href="/server/<?= $server['uuidShort'] ?>/network" class="<?= ($activeTab ?? '') === 'network' ? 'active' : '' ?>">Network</a>
    <?php endif; ?>
    <?php if (\Pterodactyl\Security\Gate::allows($user, $server, 'startup.read')): ?>
        <a href="/server/<?= $server['uuidShort'] ?>/startup" class="<?= ($activeTab ?? '') === 'startup' ? 'active' : '' ?>">Startup</a>
    <?php endif; ?>
    <?php if (\Pterodactyl\Security\Gate::allows($user, $server, 'settings.rename') || \Pterodactyl\Security\Gate::allows($user, $server, 'settings.reinstall') || \Pterodactyl\Security\Gate::allows($user, $server, 'file.sftp') || \Pterodactyl\Security\Gate::isRootAdmin($user) || ((int)$user['id'] === (int)$server['owner_id'])): ?>
        <a href="/server/<?= $server['uuidShort'] ?>/settings" class="<?= ($activeTab ?? '') === 'settings' ? 'active' : '' ?>">Settings</a>
    <?php endif; ?>
    <?php if (\Pterodactyl\Security\Gate::allows($user, $server, 'activity.read')): ?>
        <a href="/server/<?= $server['uuidShort'] ?>/activity" class="<?= ($activeTab ?? '') === 'activity' ? 'active' : '' ?>">Activity</a>
    <?php endif; ?>
    <?php if (!empty($user['root_admin'])): ?>
        <a href="/admin/servers/view/<?= $server['id'] ?>" target="_blank" style="color: #cbd5e1;" title="View in Admin Panel">↗ Admin</a>
    <?php endif; ?>
</nav>
<?php $this->end(); ?>

<?= $content ?>
