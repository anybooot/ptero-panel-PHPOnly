<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Admin;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Core\View;
use Pterodactyl\Wings\WingsClient;

/**
 * Handles Administrator Server Management: Creation wizard, limits, startup, mounts, deletion.
 */
class ServerHandler
{
    public function index(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $search = trim((string)$request->input('search', ''));

        $query = "SELECT s.*, n.`name` as node_name, u.`username` as owner_username, u.`email` as owner_email,
                         a.`ip` as allocation_ip, a.`port` as allocation_port
                  FROM `servers` s
                  JOIN `nodes` n ON n.`id` = s.`node_id`
                  JOIN `users` u ON u.`id` = s.`owner_id`
                  LEFT JOIN `allocations` a ON a.`id` = s.`allocation_id`";

        $params = [];
        if ($search !== '') {
            $query .= " WHERE s.`name` LIKE :search OR s.`uuid` LIKE :search2 OR s.`uuidShort` LIKE :search3 OR u.`username` LIKE :search4";
            $params['search'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
            $params['search3'] = "%{$search}%";
            $params['search4'] = "%{$search}%";
        }

        $query .= " ORDER BY s.`id` DESC";
        $servers = Database::query($query, $params);

        $html = View::render('admin.servers.index', [
            'user' => $user,
            'servers' => $servers,
            'search' => $search,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'servers',
        ]);

        return Response::html($html);
    }

    public function createForm(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $users = Database::query("SELECT `id`, `username`, `email` FROM `users` ORDER BY `username` ASC");
        $nodes = Database::query("SELECT `id`, `name`, `memory`, `disk` FROM `nodes` ORDER BY `name` ASC");
        $nests = Database::query("SELECT `id`, `name` FROM `nests` ORDER BY `name` ASC");
        $eggs = Database::query("SELECT `id`, `nest_id`, `name`, `startup`, `docker_images`, `image` FROM `eggs` ORDER BY `name` ASC");

        $html = View::render('admin.servers.new', [
            'user' => $user,
            'users' => $users,
            'nodes' => $nodes,
            'nests' => $nests,
            'eggs' => $eggs,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'servers',
        ]);

        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        $name = trim((string)$request->input('name', ''));
        $ownerId = (int)$request->input('owner_id', 0);
        $nodeId = (int)$request->input('node_id', 0);
        $allocationId = (int)$request->input('allocation_id', 0);
        $eggId = (int)$request->input('egg_id', 0);
        $memory = (int)$request->input('memory', 1024);
        $swap = (int)$request->input('swap', 0);
        $disk = (int)$request->input('disk', 10240);
        $cpu = (int)$request->input('cpu', 100);
        $io = (int)$request->input('io', 500);
        $startup = trim((string)$request->input('startup', ''));
        $image = trim((string)$request->input('image', ''));

        if ($name === '' || $ownerId === 0 || $nodeId === 0 || $eggId === 0) {
            Session::flash('error', 'Name, Owner, Node, and Egg are required.');
            return Response::redirect('/admin/servers/new');
        }

        // Auto-assign primary allocation if none provided
        if ($allocationId === 0) {
            $freeAlloc = Database::queryOne(
                "SELECT `id` FROM `allocations` WHERE `node_id` = :node_id AND `server_id` IS NULL LIMIT 1",
                ['node_id' => $nodeId]
            );
            if (!$freeAlloc) {
                Session::flash('error', 'No unassigned allocations are available on the selected node.');
                return Response::redirect('/admin/servers/new');
            }
            $allocationId = (int)$freeAlloc['id'];
        }

        $egg = Database::queryOne("SELECT * FROM `eggs` WHERE `id` = :id LIMIT 1", ['id' => $eggId]);
        if (!$egg) {
            Session::flash('error', 'Selected Egg was not found.');
            return Response::redirect('/admin/servers/new');
        }

        if ($startup === '') {
            $startup = $egg['startup'];
        }
        if ($image === '') {
            $image = $egg['image'];
        }

        $uuid = bin2hex(random_bytes(16));
        $uuidShort = substr($uuid, 0, 8);

        $serverId = Database::insert('servers', [
            'uuid' => $uuid,
            'uuidShort' => $uuidShort,
            'node_id' => $nodeId,
            'name' => $name,
            'description' => (string)$request->input('description', ''),
            'status' => 'installing',
            'owner_id' => $ownerId,
            'memory' => $memory,
            'swap' => $swap,
            'disk' => $disk,
            'io' => $io,
            'cpu' => $cpu,
            'oom_disabled' => $request->boolean('oom_disabled', true) ? 1 : 0,
            'allocation_id' => $allocationId,
            'nest_id' => $egg['nest_id'],
            'egg_id' => $eggId,
            'startup' => $startup,
            'image' => $image,
            'allocation_limit' => (int)$request->input('allocation_limit', 1),
            'database_limit' => (int)$request->input('database_limit', 0),
            'backup_limit' => (int)$request->input('backup_limit', 0),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Mark allocation as assigned
        Database::execute(
            "UPDATE `allocations` SET `server_id` = :server_id WHERE `id` = :id",
            ['server_id' => $serverId, 'id' => $allocationId]
        );

        // Populate egg variables
        $eggVariables = Database::query("SELECT * FROM `egg_variables` WHERE `egg_id` = :egg_id", ['egg_id' => $eggId]);
        foreach ($eggVariables as $ev) {
            $val = $request->input("env_{$ev['env_variable']}", $ev['default_value']);
            Database::insert('server_variables', [
                'server_id' => $serverId,
                'variable_id' => $ev['id'],
                'server_value' => (string)$val,
            ]);
        }

        // Trigger Wings server creation
        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $nodeId]);
        try {
            WingsClient::createServer($node, $uuid, true);
        } catch (\Throwable $e) {
            Session::flash('error', 'Server created in database, but Wings daemon reported an error: ' . $e->getMessage());
            return Response::redirect("/admin/servers/view/{$serverId}");
        }

        Session::flash('success', 'Server created successfully and installation started on Wings.');
        return Response::redirect("/admin/servers/view/{$serverId}");
    }

    public function view(Request $request, string $id): Response
    {
        $user = $request->getAttribute('user');
        $server = Database::queryOne(
            "SELECT s.*, n.`name` as node_name, n.`fqdn` as node_fqdn,
                    u.`username` as owner_username, u.`email` as owner_email,
                    a.`ip` as allocation_ip, a.`port` as allocation_port,
                    e.`name` as egg_name, nest.`name` as nest_name
             FROM `servers` s
             JOIN `nodes` n ON n.`id` = s.`node_id`
             JOIN `users` u ON u.`id` = s.`owner_id`
             JOIN `eggs` e ON e.`id` = s.`egg_id`
             JOIN `nests` nest ON nest.`id` = s.`nest_id`
             LEFT JOIN `allocations` a ON a.`id` = s.`allocation_id`
             WHERE s.`id` = :id LIMIT 1",
            ['id' => $id]
        );

        if (!$server) {
            return Response::redirect('/admin/servers');
        }

        $html = View::render('admin.servers.view', [
            'user' => $user,
            'server' => $server,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'servers',
            'activeServerTab' => 'overview',
        ]);

        return Response::html($html);
    }

    public function details(Request $request, string $id): Response
    {
        $user = $request->getAttribute('user');
        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$server) {
            return Response::redirect('/admin/servers');
        }

        $users = Database::query("SELECT `id`, `username`, `email` FROM `users` ORDER BY `username` ASC");

        $html = View::render('admin.servers.details', [
            'user' => $user,
            'server' => $server,
            'users' => $users,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'servers',
            'activeServerTab' => 'details',
        ]);

        return Response::html($html);
    }

    public function updateDetails(Request $request, string $id): Response
    {
        $name = trim((string)$request->input('name', ''));
        $ownerId = (int)$request->input('owner_id', 0);
        $description = (string)$request->input('description', '');

        if ($name === '' || $ownerId === 0) {
            Session::flash('error', 'Server name and owner are required.');
            return Response::redirect("/admin/servers/view/{$id}/details");
        }

        Database::update('servers', [
            'name' => $name,
            'owner_id' => $ownerId,
            'description' => $description,
            'updated_at' => date('Y-m-d H:i:s'),
        ], '`id` = :id', ['id' => $id]);

        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $server['node_id']]);
        try {
            WingsClient::syncServer($node, $server['uuid']);
        } catch (\Throwable) {
        }

        Session::flash('success', 'Server details updated.');
        return Response::redirect("/admin/servers/view/{$id}/details");
    }

    public function build(Request $request, string $id): Response
    {
        $user = $request->getAttribute('user');
        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$server) {
            return Response::redirect('/admin/servers');
        }

        $allocations = Database::query(
            "SELECT * FROM `allocations` WHERE `node_id` = :node_id AND (`server_id` IS NULL OR `server_id` = :server_id) ORDER BY `port` ASC",
            ['node_id' => $server['node_id'], 'server_id' => $server['id']]
        );

        $html = View::render('admin.servers.build', [
            'user' => $user,
            'server' => $server,
            'allocations' => $allocations,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'servers',
            'activeServerTab' => 'build',
        ]);

        return Response::html($html);
    }

    public function updateBuild(Request $request, string $id): Response
    {
        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$server) {
            return Response::redirect('/admin/servers');
        }

        $memory = (int)$request->input('memory', $server['memory']);
        $swap = (int)$request->input('swap', $server['swap']);
        $disk = (int)$request->input('disk', $server['disk']);
        $cpu = (int)$request->input('cpu', $server['cpu']);
        $io = (int)$request->input('io', $server['io']);
        $allocationId = (int)$request->input('allocation_id', $server['allocation_id']);
        $allocationLimit = (int)$request->input('allocation_limit', $server['allocation_limit']);
        $databaseLimit = (int)$request->input('database_limit', $server['database_limit']);
        $backupLimit = (int)$request->input('backup_limit', $server['backup_limit']);

        Database::update('servers', [
            'memory' => $memory,
            'swap' => $swap,
            'disk' => $disk,
            'cpu' => $cpu,
            'io' => $io,
            'oom_disabled' => $request->boolean('oom_disabled', true) ? 1 : 0,
            'allocation_id' => $allocationId,
            'allocation_limit' => $allocationLimit,
            'database_limit' => $databaseLimit,
            'backup_limit' => $backupLimit,
            'updated_at' => date('Y-m-d H:i:s'),
        ], '`id` = :id', ['id' => $id]);

        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $server['node_id']]);
        try {
            WingsClient::syncServer($node, $server['uuid']);
        } catch (\Throwable) {
        }

        Session::flash('success', 'Build configuration updated.');
        return Response::redirect("/admin/servers/view/{$id}/build");
    }

    public function manage(Request $request, string $id): Response
    {
        $user = $request->getAttribute('user');
        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$server) {
            return Response::redirect('/admin/servers');
        }

        $html = View::render('admin.servers.manage', [
            'user' => $user,
            'server' => $server,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'servers',
            'activeServerTab' => 'manage',
        ]);

        return Response::html($html);
    }

    public function toggleSuspend(Request $request, string $id): Response
    {
        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$server) {
            return Response::redirect('/admin/servers');
        }

        $newStatus = ($server['status'] === 'suspended') ? null : 'suspended';
        Database::execute("UPDATE `servers` SET `status` = :status WHERE `id` = :id", ['status' => $newStatus, 'id' => $id]);

        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $server['node_id']]);
        try {
            WingsClient::syncServer($node, $server['uuid']);
        } catch (\Throwable) {
        }

        Session::flash('success', $newStatus === 'suspended' ? 'Server has been suspended.' : 'Server has been unsuspended.');
        return Response::redirect("/admin/servers/view/{$id}/manage");
    }

    public function reinstall(Request $request, string $id): Response
    {
        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$server) {
            return Response::redirect('/admin/servers');
        }

        Database::execute("UPDATE `servers` SET `status` = 'installing' WHERE `id` = :id", ['id' => $id]);
        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $server['node_id']]);
        try {
            WingsClient::reinstallServer($node, $server['uuid']);
        } catch (\Throwable $e) {
            Session::flash('error', 'Wings error: ' . $e->getMessage());
            return Response::redirect("/admin/servers/view/{$id}/manage");
        }

        Session::flash('success', 'Server reinstallation initiated.');
        return Response::redirect("/admin/servers/view/{$id}/manage");
    }

    public function delete(Request $request, string $id): Response
    {
        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$server) {
            return Response::redirect('/admin/servers');
        }

        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $server['node_id']]);
        if ($node) {
            try {
                WingsClient::deleteServer($node, $server['uuid']);
            } catch (\Throwable) {
                // Ignore remote failure on delete
            }
        }

        // Cleanup DB relations
        Database::execute("UPDATE `allocations` SET `server_id` = NULL, `notes` = NULL WHERE `server_id` = :id", ['id' => $id]);
        Database::execute("DELETE FROM `server_variables` WHERE `server_id` = :id", ['id' => $id]);
        Database::execute("DELETE FROM `subusers` WHERE `server_id` = :id", ['id' => $id]);
        Database::execute("DELETE FROM `tasks` WHERE `schedule_id` IN (SELECT `id` FROM `schedules` WHERE `server_id` = :id)", ['id' => $id]);
        Database::execute("DELETE FROM `schedules` WHERE `server_id` = :id", ['id' => $id]);
        Database::execute("DELETE FROM `backups` WHERE `server_id` = :id", ['id' => $id]);
        Database::execute("DELETE FROM `databases` WHERE `server_id` = :id", ['id' => $id]);
        Database::execute("DELETE FROM `servers` WHERE `id` = :id", ['id' => $id]);

        Session::flash('success', 'Server deleted successfully.');
        return Response::redirect('/admin/servers');
    }
}
