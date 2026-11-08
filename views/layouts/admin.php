<?php $this->extend('layouts.main'); ?>

<?php $this->start('subnav'); ?>
<nav class="subnav">
    <a href="/admin" class="<?= ($activeAdminTab ?? '') === 'overview' ? 'active' : '' ?>">Overview</a>
    <a href="/admin/settings" class="<?= ($activeAdminTab ?? '') === 'settings' ? 'active' : '' ?>">Settings</a>
    <a href="/admin/api" class="<?= ($activeAdminTab ?? '') === 'api' ? 'active' : '' ?>">Application API</a>
    <a href="/admin/locations" class="<?= ($activeAdminTab ?? '') === 'locations' ? 'active' : '' ?>">Locations</a>
    <a href="/admin/databases" class="<?= ($activeAdminTab ?? '') === 'databases' ? 'active' : '' ?>">Database Hosts</a>
    <a href="/admin/nodes" class="<?= ($activeAdminTab ?? '') === 'nodes' ? 'active' : '' ?>">Nodes</a>
    <a href="/admin/servers" class="<?= ($activeAdminTab ?? '') === 'servers' ? 'active' : '' ?>">Servers</a>
    <a href="/admin/users" class="<?= ($activeAdminTab ?? '') === 'users' ? 'active' : '' ?>">Users</a>
    <a href="/admin/nests" class="<?= ($activeAdminTab ?? '') === 'nests' ? 'active' : '' ?>">Nests & Eggs</a>
    <a href="/admin/mounts" class="<?= ($activeAdminTab ?? '') === 'mounts' ? 'active' : '' ?>">Mounts</a>
</nav>
<?php $this->end(); ?>

<?= $content ?>
