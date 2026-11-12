<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Admin;

use PDO;
use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Core\View;
use Pterodactyl\Security\Encrypter;
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
        $eggs = Database::query("SELECT `id`, `nest_id`, `name`, `startup`, `docker_images` FROM `eggs` ORDER BY `name` ASC");
        if (empty($eggs)) {
            \Pterodactyl\Core\EggSeeder::seedDefaults();
            $nests = Database::query("SELECT `id`, `name` FROM `nests` ORDER BY `name` ASC");
            $eggs = Database::query("SELECT `id`, `nest_id`, `name`, `startup`, `docker_images` FROM `eggs` ORDER BY `name` ASC");
        }
        foreach ($eggs as &$egg) {
            $images = json_decode((string)($egg['docker_images'] ?? ''), true);
            $egg['image'] = is_array($images) && !empty($images) ? (is_string(reset($images)) ? reset($images) : '') : 'ghcr.io/pterodactyl/yolks:java_17';
        }
        unset($egg);

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
            $startup = (string)($egg['startup'] ?? '');
        }
        if ($image === '') {
            $images = json_decode((string)($egg['docker_images'] ?? ''), true);
            $image = is_array($images) && !empty($images) ? (is_string(reset($images)) ? reset($images) : (string)key($images)) : 'ghcr.io/pterodactyl/yolks:java_17';
        }

        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
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
                'variable_value' => (string)$val,
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

    public function startup(Request $request, string $id): Response
    {
        $user = $request->getAttribute('user');
        $server = Database::queryOne(
            "SELECT s.*, n.`name` as node_name, e.`name` as egg_name, nest.`name` as nest_name
             FROM `servers` s
             JOIN `nodes` n ON n.`id` = s.`node_id`
             JOIN `eggs` e ON e.`id` = s.`egg_id`
             JOIN `nests` nest ON nest.`id` = s.`nest_id`
             WHERE s.`id` = :id LIMIT 1",
            ['id' => $id]
        );
        if (!$server) {
            return Response::redirect('/admin/servers');
        }

        $egg = Database::queryOne("SELECT * FROM `eggs` WHERE `id` = :id LIMIT 1", ['id' => $server['egg_id']]);
        $nests = Database::query("SELECT * FROM `nests` ORDER BY `name` ASC");
        $eggs = Database::query("SELECT `id`, `nest_id`, `name`, `startup`, `docker_images` FROM `eggs` ORDER BY `name` ASC");
        foreach ($eggs as &$eg) {
            $images = json_decode((string)($eg['docker_images'] ?? ''), true);
            $eg['image'] = is_array($images) && !empty($images) ? (is_string(reset($images)) ? reset($images) : '') : 'ghcr.io/pterodactyl/yolks:java_17';
        }
        unset($eg);

        $variables = Database::query(
            "SELECT ev.*, sv.`variable_value` as `server_value`
             FROM `egg_variables` ev
             LEFT JOIN `server_variables` sv ON sv.`variable_id` = ev.`id` AND sv.`server_id` = :server_id
             WHERE ev.`egg_id` = :egg_id ORDER BY ev.`id` ASC",
            ['server_id' => $server['id'], 'egg_id' => $server['egg_id']]
        );

        $html = View::render('admin.servers.startup', [
            'user' => $user,
            'server' => $server,
            'egg' => $egg,
            'nests' => $nests,
            'eggs' => $eggs,
            'variables' => $variables,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'servers',
            'activeServerTab' => 'startup',
        ]);

        return Response::html($html);
    }

    public function updateStartup(Request $request, string $id): Response
    {
        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$server) {
            return Response::redirect('/admin/servers');
        }

        $startup = trim((string)$request->input('startup', $server['startup']));
        $image = trim((string)$request->input('image', $server['image']));
        $eggId = (int)$request->input('egg_id', $server['egg_id']);

        $egg = Database::queryOne("SELECT * FROM `eggs` WHERE `id` = :id LIMIT 1", ['id' => $eggId]);
        if (!$egg) {
            Session::flash('error', 'Selected Egg was not found.');
            return Response::redirect("/admin/servers/view/{$id}/startup");
        }

        Database::update('servers', [
            'startup' => $startup,
            'image' => $image,
            'egg_id' => $eggId,
            'nest_id' => $egg['nest_id'],
            'updated_at' => date('Y-m-d H:i:s'),
        ], '`id` = :id', ['id' => $id]);

        // Update environment variables
        $eggVariables = Database::query("SELECT * FROM `egg_variables` WHERE `egg_id` = :egg_id", ['egg_id' => $eggId]);
        foreach ($eggVariables as $ev) {
            $inputKey = "env_{$ev['env_variable']}";
            $val = $request->input($inputKey, $ev['default_value']);

            $existing = Database::queryOne(
                "SELECT `id` FROM `server_variables` WHERE `server_id` = :server_id AND `variable_id` = :variable_id LIMIT 1",
                ['server_id' => $id, 'variable_id' => $ev['id']]
            );

            if ($existing) {
                Database::execute(
                    "UPDATE `server_variables` SET `variable_value` = :val WHERE `id` = :id",
                    ['val' => (string)$val, 'id' => $existing['id']]
                );
            } else {
                Database::insert('server_variables', [
                    'server_id' => $id,
                    'variable_id' => $ev['id'],
                    'variable_value' => (string)$val,
                ]);
            }
        }

        // Sync with Wings
        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $server['node_id']]);
        if ($node) {
            try {
                WingsClient::syncServer($node, $server['uuid']);
            } catch (\Throwable) {}
        }

        Session::flash('success', 'Startup configuration and variables updated.');
        return Response::redirect("/admin/servers/view/{$id}/startup");
    }

    public function databases(Request $request, string $id): Response
    {
        $user = $request->getAttribute('user');
        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$server) {
            return Response::redirect('/admin/servers');
        }

        $databases = Database::query(
            "SELECT d.*, dh.`name` as host_name, dh.`host`, dh.`port`
             FROM `databases` d
             JOIN `database_hosts` dh ON dh.`id` = d.`database_host_id`
             WHERE d.`server_id` = :server_id ORDER BY d.`id` ASC",
            ['server_id' => $id]
        );

        foreach ($databases as &$db) {
            try {
                $db['password_plain'] = Encrypter::decryptString($db['password']);
            } catch (\Throwable) {
                $db['password_plain'] = $db['password'];
            }
        }

        $hosts = Database::query("SELECT `id`, `name`, `host`, `port` FROM `database_hosts` ORDER BY `name` ASC");

        $html = View::render('admin.servers.databases', [
            'user' => $user,
            'server' => $server,
            'databases' => $databases,
            'hosts' => $hosts,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'servers',
            'activeServerTab' => 'database',
        ]);

        return Response::html($html);
    }

    public function createDatabase(Request $request, string $id): Response
    {
        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$server) {
            return Response::redirect('/admin/servers');
        }

        $hostId = (int)$request->input('database_host_id', 0);
        $dbNameInput = trim((string)$request->input('database', ''));
        $remote = trim((string)$request->input('remote', '%'));

        if ($hostId === 0 || $dbNameInput === '') {
            Session::flash('error', 'Database host and database name are required.');
            return Response::redirect("/admin/servers/view/{$id}/database");
        }

        $host = Database::queryOne("SELECT * FROM `database_hosts` WHERE `id` = :id LIMIT 1", ['id' => $hostId]);
        if (!$host) {
            Session::flash('error', 'Selected database host not found.');
            return Response::redirect("/admin/servers/view/{$id}/database");
        }

        $finalDbName = "s{$server['id']}_" . preg_replace('/[^a-zA-Z0-9_]/', '', $dbNameInput);
        $username = "u{$server['id']}_" . substr(bin2hex(random_bytes(4)), 0, 8);
        $plainPassword = bin2hex(random_bytes(16));

        try {
            $dsn = "mysql:host={$host['host']};port={$host['port']}";
            $decryptedPass = Encrypter::decryptString($host['password']);
            $pdo = new PDO($dsn, $host['username'], $decryptedPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$finalDbName}`");
            $pdo->exec("CREATE USER IF NOT EXISTS '{$username}'@'{$remote}' IDENTIFIED BY '{$plainPassword}'");
            $pdo->exec("GRANT ALL PRIVILEGES ON `{$finalDbName}`.* TO '{$username}'@'{$remote}'");
            $pdo->exec("FLUSH PRIVILEGES");
        } catch (\Throwable $e) {
            Session::flash('error', 'Could not create database on remote host: ' . $e->getMessage());
            return Response::redirect("/admin/servers/view/{$id}/database");
        }

        Database::insert('databases', [
            'server_id' => $server['id'],
            'database_host_id' => $host['id'],
            'database' => $finalDbName,
            'username' => $username,
            'remote' => $remote,
            'password' => Encrypter::encryptString($plainPassword),
            'max_connections' => (int)$request->input('max_connections', 0),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Session::flash('success', "Database [{$finalDbName}] created successfully with user [{$username}].");
        return Response::redirect("/admin/servers/view/{$id}/database");
    }

    public function resetDatabasePassword(Request $request, string $id, string $dbId): Response
    {
        $db = Database::queryOne(
            "SELECT d.*, dh.`host`, dh.`port`, dh.`username` as host_user, dh.`password` as host_pass
             FROM `databases` d
             JOIN `database_hosts` dh ON dh.`id` = d.`database_host_id`
             WHERE d.`id` = :id AND d.`server_id` = :server_id LIMIT 1",
            ['id' => $dbId, 'server_id' => $id]
        );

        if (!$db) {
            Session::flash('error', 'Database not found.');
            return Response::redirect("/admin/servers/view/{$id}/database");
        }

        $newPassword = bin2hex(random_bytes(16));

        try {
            $dsn = "mysql:host={$db['host']};port={$db['port']}";
            $decryptedPass = Encrypter::decryptString($db['host_pass']);
            $pdo = new PDO($dsn, $db['host_user'], $decryptedPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);

            $pdo->exec("ALTER USER '{$db['username']}'@'{$db['remote']}' IDENTIFIED BY '{$newPassword}'");
            $pdo->exec("FLUSH PRIVILEGES");
        } catch (\Throwable $e) {
            Session::flash('error', 'Could not reset password on database host: ' . $e->getMessage());
            return Response::redirect("/admin/servers/view/{$id}/database");
        }

        Database::execute(
            "UPDATE `databases` SET `password` = :pass, `updated_at` = NOW() WHERE `id` = :id",
            ['pass' => Encrypter::encryptString($newPassword), 'id' => $dbId]
        );

        Session::flash('success', "Password reset successfully. New Password: {$newPassword}");
        return Response::redirect("/admin/servers/view/{$id}/database");
    }

    public function deleteDatabase(Request $request, string $id, string $dbId): Response
    {
        $db = Database::queryOne(
            "SELECT d.*, dh.`host`, dh.`port`, dh.`username` as host_user, dh.`password` as host_pass
             FROM `databases` d
             JOIN `database_hosts` dh ON dh.`id` = d.`database_host_id`
             WHERE d.`id` = :id AND d.`server_id` = :server_id LIMIT 1",
            ['id' => $dbId, 'server_id' => $id]
        );

        if ($db) {
            try {
                $dsn = "mysql:host={$db['host']};port={$db['port']}";
                $decryptedPass = Encrypter::decryptString($db['host_pass']);
                $pdo = new PDO($dsn, $db['host_user'], $decryptedPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);

                $pdo->exec("DROP DATABASE IF EXISTS `{$db['database']}`");
                $pdo->exec("DROP USER IF EXISTS '{$db['username']}'@'{$db['remote']}'");
                $pdo->exec("FLUSH PRIVILEGES");
            } catch (\Throwable) {}

            Database::execute("DELETE FROM `databases` WHERE `id` = :id", ['id' => $dbId]);
        }

        Session::flash('success', 'Database deleted.');
        return Response::redirect("/admin/servers/view/{$id}/database");
    }

    public function mounts(Request $request, string $id): Response
    {
        $user = $request->getAttribute('user');
        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$server) {
            return Response::redirect('/admin/servers');
        }

        $mounted = Database::query(
            "SELECT m.*, ms.`id` as mount_server_id FROM `mount_server` ms
             JOIN `mounts` m ON m.`id` = ms.`mount_id`
             WHERE ms.`server_id` = :server_id",
            ['server_id' => $id]
        );

        $availableMounts = Database::query(
            "SELECT m.* FROM `mounts` m
             WHERE m.`id` NOT IN (SELECT `mount_id` FROM `mount_server` WHERE `server_id` = :server_id)
             ORDER BY m.`name` ASC",
            ['server_id' => $id]
        );

        $html = View::render('admin.servers.mounts', [
            'user' => $user,
            'server' => $server,
            'mounted' => $mounted,
            'availableMounts' => $availableMounts,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'servers',
            'activeServerTab' => 'mounts',
        ]);

        return Response::html($html);
    }

    public function addMount(Request $request, string $id): Response
    {
        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$server) {
            return Response::redirect('/admin/servers');
        }

        $mountId = (int)$request->input('mount_id', 0);
        if ($mountId > 0) {
            $exists = Database::queryOne(
                "SELECT 1 FROM `mount_server` WHERE `server_id` = :s AND `mount_id` = :m LIMIT 1",
                ['s' => $id, 'm' => $mountId]
            );
            if (!$exists) {
                Database::insert('mount_server', [
                    'mount_id' => $mountId,
                    'server_id' => $id,
                ]);
            }

            $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $server['node_id']]);
            if ($node) {
                try {
                    WingsClient::syncServer($node, $server['uuid']);
                } catch (\Throwable) {}
            }
        }

        Session::flash('success', 'Mount attached to server.');
        return Response::redirect("/admin/servers/view/{$id}/mounts");
    }

    public function deleteMount(Request $request, string $id, string $mountId): Response
    {
        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$server) {
            return Response::redirect('/admin/servers');
        }

        Database::execute(
            "DELETE FROM `mount_server` WHERE `server_id` = :s AND `mount_id` = :m",
            ['s' => $id, 'm' => $mountId]
        );

        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $server['node_id']]);
        if ($node) {
            try {
                WingsClient::syncServer($node, $server['uuid']);
            } catch (\Throwable) {}
        }

        Session::flash('success', 'Mount detached.');
        return Response::redirect("/admin/servers/view/{$id}/mounts");
    }

    public function toggleInstallStatus(Request $request, string $id): Response
    {
        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$server) {
            return Response::redirect('/admin/servers');
        }

        $newStatus = ($server['status'] === 'installing' || $server['status'] === 'install_failed') ? null : 'installing';
        Database::execute("UPDATE `servers` SET `status` = :status WHERE `id` = :id", ['status' => $newStatus, 'id' => $id]);

        Session::flash('success', $newStatus === null ? 'Server installation status cleared.' : 'Server marked as installing.');
        return Response::redirect("/admin/servers/view/{$id}/manage");
    }
}
