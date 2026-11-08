<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Admin;

use Pterodactyl\Core\Config;
use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Core\View;
use Pterodactyl\Security\Encrypter;
use Pterodactyl\Wings\WingsClient;

/**
 * Handles Node Management in Admin panel: creation, settings, allocations, Wings config generation, live stats.
 */
class NodeHandler
{
    public function index(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $nodes = Database::query(
            "SELECT n.*, l.`short` as location_short,
                    (SELECT COUNT(*) FROM `servers` s WHERE s.`node_id` = n.`id`) as servers_count,
                    (SELECT COALESCE(SUM(s.`memory`), 0) FROM `servers` s WHERE s.`node_id` = n.`id`) as memory_used,
                    (SELECT COALESCE(SUM(s.`disk`), 0) FROM `servers` s WHERE s.`node_id` = n.`id`) as disk_used
             FROM `nodes` n
             JOIN `locations` l ON l.`id` = n.`location_id`
             ORDER BY n.`id` ASC"
        );

        $html = View::render('admin.nodes.index', [
            'user' => $user,
            'nodes' => $nodes,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'nodes',
        ]);

        return Response::html($html);
    }

    public function createForm(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $locations = Database::query("SELECT * FROM `locations` ORDER BY `short` ASC");

        $html = View::render('admin.nodes.new', [
            'user' => $user,
            'locations' => $locations,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'nodes',
        ]);

        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        $name = trim((string)$request->input('name', ''));
        $locationId = (int)$request->input('location_id', 0);
        $fqdn = trim((string)$request->input('fqdn', ''));
        $scheme = (string)$request->input('scheme', 'https');
        $memory = (int)$request->input('memory', 1024);
        $memoryOverallocate = (int)$request->input('memory_overallocate', 0);
        $disk = (int)$request->input('disk', 10240);
        $diskOverallocate = (int)$request->input('disk_overallocate', 0);
        $uploadSize = (int)$request->input('upload_size', 100);
        $daemonListen = (int)$request->input('daemonListen', 8080);
        $daemonSFTP = (int)$request->input('daemonSFTP', 2022);

        if ($name === '' || $fqdn === '' || $locationId === 0) {
            Session::flash('error', 'Name, Location, and FQDN are required.');
            return Response::redirect('/admin/nodes/new');
        }

        $uuid = bin2hex(random_bytes(16));
        $daemonTokenId = bin2hex(random_bytes(8));
        $daemonTokenSecret = bin2hex(random_bytes(32));

        $nodeId = Database::insert('nodes', [
            'uuid' => $uuid,
            'name' => $name,
            'location_id' => $locationId,
            'public' => $request->boolean('public', true) ? 1 : 0,
            'fqdn' => $fqdn,
            'scheme' => $scheme,
            'behind_proxy' => $request->boolean('behind_proxy', false) ? 1 : 0,
            'maintenance_mode' => $request->boolean('maintenance_mode', false) ? 1 : 0,
            'memory' => $memory,
            'memory_overallocate' => $memoryOverallocate,
            'disk' => $disk,
            'disk_overallocate' => $diskOverallocate,
            'upload_size' => $uploadSize,
            'daemon_token_id' => $daemonTokenId,
            'daemon_token' => Encrypter::encryptString($daemonTokenSecret),
            'daemonListen' => $daemonListen,
            'daemonSFTP' => $daemonSFTP,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Session::flash('success', 'Node created successfully. You can now configure Wings using the Configuration tab.');
        return Response::redirect("/admin/nodes/view/{$nodeId}/configuration");
    }

    public function view(Request $request, string $id): Response
    {
        $user = $request->getAttribute('user');
        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$node) {
            return Response::redirect('/admin/nodes');
        }

        $location = Database::queryOne("SELECT * FROM `locations` WHERE `id` = :id LIMIT 1", ['id' => $node['location_id']]);
        $locations = Database::query("SELECT * FROM `locations` ORDER BY `short` ASC");

        $stats = Database::queryOne(
            "SELECT COUNT(s.`id`) as servers_count,
                    COALESCE(SUM(s.`memory`), 0) as memory_used,
                    COALESCE(SUM(s.`disk`), 0) as disk_used
             FROM `servers` s WHERE s.`node_id` = :node_id",
            ['node_id' => $node['id']]
        );

        $html = View::render('admin.nodes.view', [
            'user' => $user,
            'node' => $node,
            'location' => $location,
            'locations' => $locations,
            'stats' => $stats,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'nodes',
            'activeNodeTab' => 'about',
        ]);

        return Response::html($html);
    }

    public function updateSettings(Request $request, string $id): Response
    {
        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$node) {
            return Response::redirect('/admin/nodes');
        }

        $name = trim((string)$request->input('name', ''));
        $locationId = (int)$request->input('location_id', $node['location_id']);
        $fqdn = trim((string)$request->input('fqdn', $node['fqdn']));
        $scheme = (string)$request->input('scheme', $node['scheme']);
        $memory = (int)$request->input('memory', $node['memory']);
        $disk = (int)$request->input('disk', $node['disk']);
        $uploadSize = (int)$request->input('upload_size', $node['upload_size']);
        $daemonListen = (int)$request->input('daemonListen', $node['daemonListen']);
        $daemonSFTP = (int)$request->input('daemonSFTP', $node['daemonSFTP']);

        Database::update('nodes', [
            'name' => $name,
            'location_id' => $locationId,
            'fqdn' => $fqdn,
            'scheme' => $scheme,
            'public' => $request->boolean('public', true) ? 1 : 0,
            'behind_proxy' => $request->boolean('behind_proxy', false) ? 1 : 0,
            'maintenance_mode' => $request->boolean('maintenance_mode', false) ? 1 : 0,
            'memory' => $memory,
            'disk' => $disk,
            'upload_size' => $uploadSize,
            'daemonListen' => $daemonListen,
            'daemonSFTP' => $daemonSFTP,
            'updated_at' => date('Y-m-d H:i:s'),
        ], '`id` = :id', ['id' => $id]);

        Session::flash('success', 'Node settings updated successfully.');
        return Response::redirect("/admin/nodes/view/{$id}");
    }

    public function configuration(Request $request, string $id): Response
    {
        $user = $request->getAttribute('user');
        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$node) {
            return Response::redirect('/admin/nodes');
        }

        $panelUrl = (string)Config::get('APP_URL', 'http://localhost');
        $tokenSecret = Encrypter::decryptString($node['daemon_token']);
        $bearerToken = "{$node['daemon_token_id']}.{$tokenSecret}";

        // Build config.yml content
        $configYml = <<<YAML
debug: false
uuid: {$node['uuid']}
token_id: {$node['daemon_token_id']}
token: {$tokenSecret}
api:
  host: 0.0.0.0
  port: {$node['daemonListen']}
  ssl:
    enabled: {$node['scheme'] === 'https'}
    cert: /etc/letsencrypt/live/{$node['fqdn']}/fullchain.pem
    key: /etc/letsencrypt/live/{$node['fqdn']}/privkey.pem
  upload_limit: {$node['uploadSize']}
system:
  data: /var/lib/pterodactyl/volumes
  sftp:
    bind_port: {$node['daemonSFTP']}
allowed_mounts: []
remote: '{$panelUrl}'
YAML;

        $autoDeployCmd = "cd /etc/pterodactyl && sudo wings configure --panel-url {$panelUrl} --token {$bearerToken} --node {$node['id']}";

        $html = View::render('admin.nodes.configuration', [
            'user' => $user,
            'node' => $node,
            'configYml' => $configYml,
            'autoDeployCmd' => $autoDeployCmd,
            'bearerToken' => $bearerToken,
            'activeAdminTab' => 'nodes',
            'activeNodeTab' => 'configuration',
        ]);

        return Response::html($html);
    }

    public function allocations(Request $request, string $id): Response
    {
        $user = $request->getAttribute('user');
        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$node) {
            return Response::redirect('/admin/nodes');
        }

        $allocations = Database::query(
            "SELECT a.*, s.`name` as server_name, s.`uuidShort` as server_uuid
             FROM `allocations` a
             LEFT JOIN `servers` s ON s.`id` = a.`server_id`
             WHERE a.`node_id` = :node_id ORDER BY a.`port` ASC",
            ['node_id' => $node['id']]
        );

        $html = View::render('admin.nodes.allocations', [
            'user' => $user,
            'node' => $node,
            'allocations' => $allocations,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'nodes',
            'activeNodeTab' => 'allocation',
        ]);

        return Response::html($html);
    }

    public function createAllocation(Request $request, string $id): Response
    {
        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$node) {
            return Response::redirect('/admin/nodes');
        }

        $ip = trim((string)$request->input('ip', ''));
        $alias = trim((string)$request->input('ip_alias', ''));
        $ports = (array)$request->input('ports', []);

        if ($ip === '' || empty($ports)) {
            Session::flash('error', 'IP address and ports are required.');
            return Response::redirect("/admin/nodes/view/{$id}/allocation");
        }

        $created = 0;
        foreach ($ports as $portDef) {
            // Support range e.g. 25565-25570 or single port 25565
            if (str_contains($portDef, '-')) {
                [$start, $end] = explode('-', $portDef, 2);
                $start = (int)trim($start);
                $end = (int)trim($end);
                for ($p = $start; $p <= $end; $p++) {
                    if ($p > 1024 && $p <= 65535) {
                        $exists = Database::queryOne(
                            "SELECT `id` FROM `allocations` WHERE `node_id` = :node_id AND `ip` = :ip AND `port` = :port",
                            ['node_id' => $node['id'], 'ip' => $ip, 'port' => $p]
                        );
                        if (!$exists) {
                            Database::insert('allocations', [
                                'node_id' => $node['id'],
                                'ip' => $ip,
                                'port' => $p,
                                'ip_alias' => $alias !== '' ? $alias : null,
                                'server_id' => null,
                            ]);
                            $created++;
                        }
                    }
                }
            } else {
                $p = (int)trim($portDef);
                if ($p > 1024 && $p <= 65535) {
                    $exists = Database::queryOne(
                        "SELECT `id` FROM `allocations` WHERE `node_id` = :node_id AND `ip` = :ip AND `port` = :port",
                        ['node_id' => $node['id'], 'ip' => $ip, 'port' => $p]
                    );
                    if (!$exists) {
                        Database::insert('allocations', [
                            'node_id' => $node['id'],
                            'ip' => $ip,
                            'port' => $p,
                            'ip_alias' => $alias !== '' ? $alias : null,
                            'server_id' => null,
                        ]);
                        $created++;
                    }
                }
            }
        }

        Session::flash('success', "Successfully created {$created} new allocation(s).");
        return Response::redirect("/admin/nodes/view/{$id}/allocation");
    }

    public function deleteAllocation(Request $request, string $id, string $allocationId): Response
    {
        $alloc = Database::queryOne(
            "SELECT * FROM `allocations` WHERE `id` = :id AND `node_id` = :node_id LIMIT 1",
            ['id' => $allocationId, 'node_id' => $id]
        );

        if (!$alloc || !empty($alloc['server_id'])) {
            Session::flash('error', 'Cannot delete an allocation that is currently assigned to a server.');
            return Response::redirect("/admin/nodes/view/{$id}/allocation");
        }

        Database::execute("DELETE FROM `allocations` WHERE `id` = :id", ['id' => $allocationId]);
        Session::flash('success', 'Allocation deleted.');
        return Response::redirect("/admin/nodes/view/{$id}/allocation");
    }

    public function servers(Request $request, string $id): Response
    {
        $user = $request->getAttribute('user');
        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$node) {
            return Response::redirect('/admin/nodes');
        }

        $servers = Database::query(
            "SELECT s.*, u.`username` as owner_username, u.`email` as owner_email,
                    a.`ip` as allocation_ip, a.`port` as allocation_port
             FROM `servers` s
             JOIN `users` u ON u.`id` = s.`owner_id`
             LEFT JOIN `allocations` a ON a.`id` = s.`allocation_id`
             WHERE s.`node_id` = :node_id ORDER BY s.`id` ASC",
            ['node_id' => $node['id']]
        );

        $html = View::render('admin.nodes.servers', [
            'user' => $user,
            'node' => $node,
            'servers' => $servers,
            'activeAdminTab' => 'nodes',
            'activeNodeTab' => 'servers',
        ]);

        return Response::html($html);
    }

    public function systemInfo(Request $request, string $id): Response
    {
        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$node) {
            return Response::json(['error' => 'Node not found.'], 404);
        }

        try {
            $info = WingsClient::getSystemInfo($node);
            return Response::json($info);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request, string $id): Response
    {
        $serverCount = (int)(Database::queryOne("SELECT COUNT(*) as c FROM `servers` WHERE `node_id` = :id", ['id' => $id])['c'] ?? 0);
        if ($serverCount > 0) {
            Session::flash('error', 'Cannot delete a node that has servers assigned to it.');
            return Response::redirect('/admin/nodes');
        }

        Database::execute("DELETE FROM `allocations` WHERE `node_id` = :id", ['id' => $id]);
        Database::execute("DELETE FROM `nodes` WHERE `id` = :id", ['id' => $id]);

        Session::flash('success', 'Node deleted successfully.');
        return Response::redirect('/admin/nodes');
    }
}
