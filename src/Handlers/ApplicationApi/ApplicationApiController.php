<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\ApplicationApi;

use PDO;
use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Security\ApiKeyAuth;
use Pterodactyl\Security\Encrypter;
use Pterodactyl\Wings\WingsClient;

/**
 * Handles Application API endpoints for external integrations, billing systems, and automation.
 */
class ApplicationApiController
{
    public static function authenticate(Request $request): ?Response
    {
        // 1. Authenticate via Application API Key
        $user = ApiKeyAuth::authenticate($request, ApiKeyAuth::TYPE_APPLICATION);
        if ($user) {
            $request->setAttribute('user', $user);
            $request->setAttribute('api_key', $user['api_key'] ?? null);
            return null;
        }

        // 2. Authenticate via Root Admin Session
        Session::start();
        $userId = Session::get('user_id');
        if ($userId) {
            $user = Database::queryOne("SELECT * FROM `users` WHERE `id` = :id LIMIT 1", ['id' => $userId]);
            if ($user && !empty($user['root_admin'])) {
                $request->setAttribute('user', $user);
                return null;
            }
        }

        $bearer = $request->getBearerToken();
        if (!$bearer) {
            return Response::json([
                'errors' => [
                    [
                        'code' => 'UnauthorizedHttpException',
                        'status' => '401',
                        'detail' => 'Authorization header is missing or malformed.',
                    ],
                ],
            ], 401);
        }

        return Response::json([
            'errors' => [
                [
                    'code' => 'AccessDeniedHttpException',
                    'status' => '403',
                    'detail' => 'Invalid or expired Application API key provided.',
                ],
            ],
        ], 403);
    }

    // ==================== USERS ====================
    public function listUsers(Request $request): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $users = Database::query("SELECT `id`, `external_id`, `uuid`, `username`, `email`, `name_first`, `name_last`, `language`, `root_admin`, `2fa` as two_factor, `created_at`, `updated_at` FROM `users` ORDER BY `id` ASC");

        return Response::json([
            'object' => 'list',
            'data' => array_map(fn($u) => ['object' => 'user', 'attributes' => $u], $users),
            'meta' => [
                'pagination' => [
                    'total' => count($users),
                    'count' => count($users),
                    'per_page' => 50,
                    'current_page' => 1,
                    'total_pages' => 1,
                ],
            ],
        ]);
    }

    public function viewUser(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $user = Database::queryOne("SELECT `id`, `external_id`, `uuid`, `username`, `email`, `name_first`, `name_last`, `language`, `root_admin`, `2fa` as two_factor, `created_at`, `updated_at` FROM `users` WHERE `id` = :id OR `uuid` = :uuid LIMIT 1", ['id' => $id, 'uuid' => $id]);
        if (!$user) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'The requested user does not exist.']]], 404);
        }

        return Response::json(['object' => 'user', 'attributes' => $user]);
    }

    public function viewUserExternal(Request $request, string $externalId): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $user = Database::queryOne("SELECT `id`, `external_id`, `uuid`, `username`, `email`, `name_first`, `name_last`, `language`, `root_admin`, `2fa` as two_factor, `created_at`, `updated_at` FROM `users` WHERE `external_id` = :ext LIMIT 1", ['ext' => $externalId]);
        if (!$user) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'The requested user does not exist.']]], 404);
        }

        return Response::json(['object' => 'user', 'attributes' => $user]);
    }

    public function createUser(Request $request): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $email = trim((string)$request->input('email', ''));
        $username = trim((string)$request->input('username', ''));
        $firstName = trim((string)$request->input('first_name', $username));
        $lastName = trim((string)$request->input('last_name', ''));
        $password = (string)$request->input('password', '');
        $rootAdmin = $request->boolean('root_admin', false) ? 1 : 0;
        $externalId = $request->input('external_id') ? trim((string)$request->input('external_id')) : null;

        if ($email === '' || $username === '') {
            return Response::json(['errors' => [['code' => 'ValidationException', 'status' => '422', 'detail' => 'Email and username are required.']]], 422);
        }

        $existing = Database::queryOne("SELECT `id` FROM `users` WHERE `email` = :email OR `username` = :username LIMIT 1", ['email' => $email, 'username' => $username]);
        if ($existing) {
            return Response::json(['errors' => [['code' => 'ConflictHttpException', 'status' => '409', 'detail' => 'A user with that email or username already exists.']]], 409);
        }

        if ($password === '') {
            $password = bin2hex(random_bytes(8));
        }

        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $id = Database::insert('users', [
            'external_id' => $externalId,
            'uuid' => $uuid,
            'username' => $username,
            'email' => $email,
            'name_first' => $firstName,
            'name_last' => $lastName,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'language' => (string)$request->input('language', 'en'),
            'root_admin' => $rootAdmin,
            '2fa' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $user = Database::queryOne("SELECT `id`, `external_id`, `uuid`, `username`, `email`, `name_first`, `name_last`, `language`, `root_admin`, `2fa` as two_factor, `created_at`, `updated_at` FROM `users` WHERE `id` = :id LIMIT 1", ['id' => $id]);

        return Response::json(['object' => 'user', 'attributes' => $user], 201);
    }

    public function updateUser(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $user = Database::queryOne("SELECT * FROM `users` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$user) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'User not found.']]], 404);
        }

        $data = ['updated_at' => date('Y-m-d H:i:s')];
        if ($email = $request->input('email')) $data['email'] = trim((string)$email);
        if ($username = $request->input('username')) $data['username'] = trim((string)$username);
        if ($first = $request->input('first_name')) $data['name_first'] = trim((string)$first);
        if ($last = $request->input('last_name')) $data['name_last'] = trim((string)$last);
        if ($request->has('root_admin')) $data['root_admin'] = $request->boolean('root_admin') ? 1 : 0;
        if ($request->has('external_id')) $data['external_id'] = $request->input('external_id') ? trim((string)$request->input('external_id')) : null;
        if ($password = $request->input('password')) {
            $data['password'] = password_hash((string)$password, PASSWORD_DEFAULT);
        }

        Database::update('users', $data, '`id` = :id', ['id' => $id]);

        $updated = Database::queryOne("SELECT `id`, `external_id`, `uuid`, `username`, `email`, `name_first`, `name_last`, `language`, `root_admin`, `2fa` as two_factor, `created_at`, `updated_at` FROM `users` WHERE `id` = :id LIMIT 1", ['id' => $id]);

        return Response::json(['object' => 'user', 'attributes' => $updated]);
    }

    public function deleteUser(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $serversCount = (int)(Database::queryOne("SELECT COUNT(*) as c FROM `servers` WHERE `owner_id` = :id", ['id' => $id])['c'] ?? 0);
        if ($serversCount > 0) {
            return Response::json(['errors' => [['code' => 'BadRequestHttpException', 'status' => '400', 'detail' => 'Cannot delete a user who currently owns servers.']]], 400);
        }

        Database::execute("DELETE FROM `subusers` WHERE `user_id` = :id", ['id' => $id]);
        Database::execute("DELETE FROM `api_keys` WHERE `user_id` = :id", ['id' => $id]);
        Database::execute("DELETE FROM `user_ssh_keys` WHERE `user_id` = :id", ['id' => $id]);
        Database::execute("DELETE FROM `users` WHERE `id` = :id", ['id' => $id]);

        return Response::noContent();
    }

    // ==================== NODES ====================
    public function listNodes(Request $request): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $nodes = Database::query("SELECT `id`, `uuid`, `public`, `name`, `description`, `location_id`, `fqdn`, `scheme`, `behind_proxy`, `maintenance_mode`, `memory`, `memory_overallocate`, `disk`, `disk_overallocate`, `upload_size`, `daemonListen`, `daemonSFTP`, `daemonBase`, `created_at`, `updated_at` FROM `nodes` ORDER BY `id` ASC");

        return Response::json([
            'object' => 'list',
            'data' => array_map(fn($n) => ['object' => 'node', 'attributes' => $n], $nodes),
            'meta' => [
                'pagination' => [
                    'total' => count($nodes),
                    'count' => count($nodes),
                    'per_page' => 50,
                    'current_page' => 1,
                    'total_pages' => 1,
                ],
            ],
        ]);
    }

    public function viewNode(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $node = Database::queryOne("SELECT `id`, `uuid`, `public`, `name`, `description`, `location_id`, `fqdn`, `scheme`, `behind_proxy`, `maintenance_mode`, `memory`, `memory_overallocate`, `disk`, `disk_overallocate`, `upload_size`, `daemonListen`, `daemonSFTP`, `daemonBase`, `created_at`, `updated_at` FROM `nodes` WHERE `id` = :id OR `uuid` = :uuid LIMIT 1", ['id' => $id, 'uuid' => $id]);
        if (!$node) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'The requested node was not found.']]], 404);
        }

        return Response::json(['object' => 'node', 'attributes' => $node]);
    }

    public function createNode(Request $request): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $name = trim((string)$request->input('name', ''));
        $locationId = (int)$request->input('location_id', 0);
        $fqdn = trim((string)$request->input('fqdn', ''));
        $scheme = (string)$request->input('scheme', 'https');
        $memory = (int)$request->input('memory', 16384);
        $disk = (int)$request->input('disk', 102400);

        if ($name === '' || $locationId === 0 || $fqdn === '') {
            return Response::json(['errors' => [['code' => 'ValidationException', 'status' => '422', 'detail' => 'Name, Location ID, and FQDN are required.']]], 422);
        }

        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
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
            'memory_overallocate' => (int)$request->input('memory_overallocate', 0),
            'disk' => $disk,
            'disk_overallocate' => (int)$request->input('disk_overallocate', 0),
            'upload_size' => (int)$request->input('upload_size', 100),
            'daemon_token_id' => $daemonTokenId,
            'daemon_token' => Encrypter::encryptString($daemonTokenSecret),
            'daemonListen' => (int)$request->input('daemon_listen', 8080),
            'daemonSFTP' => (int)$request->input('daemon_sftp', 2022),
            'daemonBase' => (string)$request->input('daemon_base', '/var/lib/pterodactyl/volumes'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $created = Database::queryOne("SELECT `id`, `uuid`, `public`, `name`, `description`, `location_id`, `fqdn`, `scheme`, `behind_proxy`, `maintenance_mode`, `memory`, `memory_overallocate`, `disk`, `disk_overallocate`, `upload_size`, `daemonListen`, `daemonSFTP`, `daemonBase`, `created_at`, `updated_at` FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $nodeId]);

        return Response::json(['object' => 'node', 'attributes' => $created], 201);
    }

    public function viewNodeConfiguration(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id OR `uuid` = :uuid LIMIT 1", ['id' => $id, 'uuid' => $id]);
        if (!$node) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Node not found.']]], 404);
        }

        try {
            $token = Encrypter::decryptString($node['daemon_token']);
        } catch (\Throwable) {
            $token = $node['daemon_token'];
        }

        $appUrl = (string)\Pterodactyl\Core\Config::get('APP_URL', 'http://127.0.0.1');

        return Response::json([
            'debug' => false,
            'uuid' => $node['uuid'],
            'token_id' => $node['daemon_token_id'],
            'token' => $token,
            'api' => [
                'host' => '0.0.0.0',
                'port' => (int)$node['daemonListen'],
                'ssl' => [
                    'enabled' => ($node['scheme'] === 'https'),
                    'cert' => "/etc/letsencrypt/live/{$node['fqdn']}/fullchain.pem",
                    'key' => "/etc/letsencrypt/live/{$node['fqdn']}/privkey.pem",
                ],
                'upload_limit' => (int)$node['upload_size'],
            ],
            'system' => [
                'data' => $node['daemonBase'] ?: '/var/lib/pterodactyl/volumes',
                'sftp' => [
                    'bind_port' => (int)$node['daemonSFTP'],
                ],
            ],
            'remote' => $appUrl,
        ]);
    }

    public function deleteNode(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $serversCount = (int)(Database::queryOne("SELECT COUNT(*) as c FROM `servers` WHERE `node_id` = :id", ['id' => $id])['c'] ?? 0);
        if ($serversCount > 0) {
            return Response::json(['errors' => [['code' => 'BadRequestHttpException', 'status' => '400', 'detail' => 'Cannot delete a node that has active servers.']]], 400);
        }

        Database::execute("DELETE FROM `allocations` WHERE `node_id` = :id", ['id' => $id]);
        Database::execute("DELETE FROM `nodes` WHERE `id` = :id", ['id' => $id]);

        return Response::noContent();
    }

    // Node Allocations
    public function listNodeAllocations(Request $request, string $nodeId): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $allocations = Database::query("SELECT * FROM `allocations` WHERE `node_id` = :id ORDER BY `port` ASC", ['id' => $nodeId]);

        return Response::json([
            'object' => 'list',
            'data' => array_map(fn($a) => [
                'object' => 'server_allocation',
                'attributes' => [
                    'id' => (int)$a['id'],
                    'ip' => $a['ip'],
                    'alias' => $a['ip_alias'],
                    'port' => (int)$a['port'],
                    'notes' => $a['notes'],
                    'assigned' => !empty($a['server_id']),
                ],
            ], $allocations),
        ]);
    }

    public function createNodeAllocations(Request $request, string $nodeId): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $ip = trim((string)$request->input('ip', ''));
        $alias = $request->input('alias') ? trim((string)$request->input('alias')) : null;
        $ports = (array)$request->input('ports', []);

        if ($ip === '' || empty($ports)) {
            return Response::json(['errors' => [['code' => 'ValidationException', 'status' => '422', 'detail' => 'IP and a list of ports are required.']]], 422);
        }

        $created = 0;
        foreach ($ports as $port) {
            $p = (int)$port;
            if ($p < 1 || $p > 65535) continue;

            $exists = Database::queryOne("SELECT `id` FROM `allocations` WHERE `node_id` = :n AND `ip` = :ip AND `port` = :p LIMIT 1", ['n' => $nodeId, 'ip' => $ip, 'p' => $p]);
            if (!$exists) {
                Database::insert('allocations', [
                    'node_id' => (int)$nodeId,
                    'ip' => $ip,
                    'port' => $p,
                    'ip_alias' => $alias,
                ]);
                $created++;
            }
        }

        return Response::noContent();
    }

    public function deleteNodeAllocation(Request $request, string $nodeId, string $allocationId): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $alloc = Database::queryOne("SELECT * FROM `allocations` WHERE `id` = :id AND `node_id` = :n LIMIT 1", ['id' => $allocationId, 'n' => $nodeId]);
        if (!$alloc) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Allocation not found.']]], 404);
        }
        if (!empty($alloc['server_id'])) {
            return Response::json(['errors' => [['code' => 'BadRequestHttpException', 'status' => '400', 'detail' => 'Cannot delete an allocation assigned to a server.']]], 400);
        }

        Database::execute("DELETE FROM `allocations` WHERE `id` = :id", ['id' => $allocationId]);
        return Response::noContent();
    }

    // ==================== LOCATIONS ====================
    public function listLocations(Request $request): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $locations = Database::query("SELECT * FROM `locations` ORDER BY `id` ASC");
        return Response::json([
            'object' => 'list',
            'data' => array_map(fn($l) => ['object' => 'location', 'attributes' => $l], $locations),
        ]);
    }

    public function viewLocation(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $loc = Database::queryOne("SELECT * FROM `locations` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$loc) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Location not found.']]], 404);
        }
        return Response::json(['object' => 'location', 'attributes' => $loc]);
    }

    public function createLocation(Request $request): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $short = trim((string)$request->input('short', ''));
        $long = (string)$request->input('long', '');

        if ($short === '') {
            return Response::json(['errors' => [['code' => 'ValidationException', 'status' => '422', 'detail' => 'Short code is required.']]], 422);
        }

        $id = Database::insert('locations', [
            'short' => $short,
            'long' => $long,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $created = Database::queryOne("SELECT * FROM `locations` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        return Response::json(['object' => 'location', 'attributes' => $created], 201);
    }

    public function deleteLocation(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $nodesCount = (int)(Database::queryOne("SELECT COUNT(*) as c FROM `nodes` WHERE `location_id` = :id", ['id' => $id])['c'] ?? 0);
        if ($nodesCount > 0) {
            return Response::json(['errors' => [['code' => 'BadRequestHttpException', 'status' => '400', 'detail' => 'Cannot delete a location that has active nodes assigned.']]], 400);
        }

        Database::execute("DELETE FROM `locations` WHERE `id` = :id", ['id' => $id]);
        return Response::noContent();
    }

    // ==================== SERVERS ====================
    public function listServers(Request $request): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $servers = Database::query("SELECT s.*, n.`name` as node_name FROM `servers` s JOIN `nodes` n ON n.`id` = s.`node_id` ORDER BY s.`id` ASC");

        return Response::json([
            'object' => 'list',
            'data' => array_map(fn($s) => ['object' => 'server', 'attributes' => $s], $servers),
            'meta' => [
                'pagination' => [
                    'total' => count($servers),
                    'count' => count($servers),
                    'per_page' => 50,
                    'current_page' => 1,
                    'total_pages' => 1,
                ],
            ],
        ]);
    }

    public function viewServer(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $server = Database::queryOne("SELECT s.*, n.`name` as node_name FROM `servers` s JOIN `nodes` n ON n.`id` = s.`node_id` WHERE s.`id` = :id OR s.`uuid` = :uuid LIMIT 1", ['id' => $id, 'uuid' => $id]);
        if (!$server) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Server not found.']]], 404);
        }

        return Response::json(['object' => 'server', 'attributes' => $server]);
    }

    public function createServer(Request $request): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $name = trim((string)$request->input('name', ''));
        $ownerId = (int)$request->input('user', $request->input('owner_id', 0));
        $eggId = (int)$request->input('egg', $request->input('egg_id', 0));
        $nodeId = (int)$request->input('node', $request->input('node_id', 0));
        $allocationId = (int)$request->input('allocation', $request->input('allocation_id', 0));

        $limits = (array)$request->input('limits', []);
        $memory = (int)($limits['memory'] ?? $request->input('memory', 1024));
        $swap = (int)($limits['swap'] ?? $request->input('swap', 0));
        $disk = (int)($limits['disk'] ?? $request->input('disk', 10240));
        $io = (int)($limits['io'] ?? $request->input('io', 500));
        $cpu = (int)($limits['cpu'] ?? $request->input('cpu', 100));

        $featureLimits = (array)$request->input('feature_limits', []);
        $databases = (int)($featureLimits['databases'] ?? $request->input('database_limit', 0));
        $allocations = (int)($featureLimits['allocations'] ?? $request->input('allocation_limit', 1));
        $backups = (int)($featureLimits['backups'] ?? $request->input('backup_limit', 0));

        if ($name === '' || $ownerId === 0 || $eggId === 0) {
            return Response::json(['errors' => [['code' => 'ValidationException', 'status' => '422', 'detail' => 'Server name, owner user ID, and egg ID are required.']]], 422);
        }

        $egg = Database::queryOne("SELECT * FROM `eggs` WHERE `id` = :id LIMIT 1", ['id' => $eggId]);
        if (!$egg) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Specified egg was not found.']]], 404);
        }

        // Auto-detect or validate Node & Allocation
        if ($allocationId > 0) {
            $alloc = Database::queryOne("SELECT * FROM `allocations` WHERE `id` = :id AND `server_id` IS NULL LIMIT 1", ['id' => $allocationId]);
            if (!$alloc) {
                return Response::json(['errors' => [['code' => 'ConflictHttpException', 'status' => '409', 'detail' => 'Specified allocation is already assigned or invalid.']]], 409);
            }
            $nodeId = (int)$alloc['node_id'];
        } else {
            if ($nodeId === 0) {
                $freeAlloc = Database::queryOne("SELECT * FROM `allocations` WHERE `server_id` IS NULL ORDER BY `id` ASC LIMIT 1");
            } else {
                $freeAlloc = Database::queryOne("SELECT * FROM `allocations` WHERE `node_id` = :n AND `server_id` IS NULL ORDER BY `id` ASC LIMIT 1", ['n' => $nodeId]);
            }
            if (!$freeAlloc) {
                return Response::json(['errors' => [['code' => 'ConflictHttpException', 'status' => '409', 'detail' => 'No unassigned allocations are available on the selected node.']]], 409);
            }
            $allocationId = (int)$freeAlloc['id'];
            $nodeId = (int)$freeAlloc['node_id'];
        }

        $dockerImages = json_decode((string)($egg['docker_images'] ?? ''), true);
        $defaultImage = is_array($dockerImages) && !empty($dockerImages) ? (is_string(reset($dockerImages)) ? reset($dockerImages) : '') : 'ghcr.io/pterodactyl/yolks:java_17';
        $image = trim((string)$request->input('docker_image', $request->input('image', $defaultImage)));

        $startup = trim((string)$request->input('startup', (string)$egg['startup']));

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
            'allocation_limit' => $allocations,
            'database_limit' => $databases,
            'backup_limit' => $backups,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Database::execute("UPDATE `allocations` SET `server_id` = :server_id WHERE `id` = :id", ['server_id' => $serverId, 'id' => $allocationId]);

        // Seed default egg variables
        $eggVariables = Database::query("SELECT * FROM `egg_variables` WHERE `egg_id` = :id", ['id' => $eggId]);
        $environment = (array)$request->input('environment', []);
        foreach ($eggVariables as $ev) {
            $val = $environment[$ev['env_variable']] ?? $ev['default_value'];
            Database::insert('server_variables', [
                'server_id' => $serverId,
                'variable_id' => $ev['id'],
                'variable_value' => (string)$val,
            ]);
        }

        // Trigger remote creation on Wings
        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $nodeId]);
        if ($node) {
            try {
                WingsClient::createServer($node, $uuid, true);
            } catch (\Throwable) {}
        }

        $created = Database::queryOne("SELECT s.*, n.`name` as node_name FROM `servers` s JOIN `nodes` n ON n.`id` = s.`node_id` WHERE s.`id` = :id LIMIT 1", ['id' => $serverId]);
        return Response::json(['object' => 'server', 'attributes' => $created], 201);
    }

    public function suspendServer(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id OR `uuid` = :uuid LIMIT 1", ['id' => $id, 'uuid' => $id]);
        if (!$server) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Server not found.']]], 404);
        }

        Database::execute("UPDATE `servers` SET `status` = 'suspended' WHERE `id` = :id", ['id' => $server['id']]);
        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $server['node_id']]);
        if ($node) {
            try {
                WingsClient::sendPowerAction($node, $server['uuid'], 'kill');
            } catch (\Throwable) {}
        }

        return Response::noContent();
    }

    public function unsuspendServer(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id OR `uuid` = :uuid LIMIT 1", ['id' => $id, 'uuid' => $id]);
        if (!$server) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Server not found.']]], 404);
        }

        Database::execute("UPDATE `servers` SET `status` = NULL WHERE `id` = :id", ['id' => $server['id']]);
        return Response::noContent();
    }

    public function reinstallServer(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id OR `uuid` = :uuid LIMIT 1", ['id' => $id, 'uuid' => $id]);
        if (!$server) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Server not found.']]], 404);
        }

        Database::execute("UPDATE `servers` SET `status` = 'installing' WHERE `id` = :id", ['id' => $server['id']]);
        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $server['node_id']]);
        if ($node) {
            try {
                WingsClient::reinstallServer($node, $server['uuid']);
            } catch (\Throwable) {}
        }

        return Response::noContent();
    }

    public function deleteServer(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id OR `uuid` = :uuid LIMIT 1", ['id' => $id, 'uuid' => $id]);
        if (!$server) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Server not found.']]], 404);
        }

        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $server['node_id']]);
        if ($node) {
            try {
                WingsClient::deleteServer($node, $server['uuid']);
            } catch (\Throwable) {}
        }

        Database::execute("UPDATE `allocations` SET `server_id` = NULL, `notes` = NULL WHERE `server_id` = :id", ['id' => $server['id']]);
        Database::execute("DELETE FROM `server_variables` WHERE `server_id` = :id", ['id' => $server['id']]);
        Database::execute("DELETE FROM `subusers` WHERE `server_id` = :id", ['id' => $server['id']]);
        Database::execute("DELETE FROM `databases` WHERE `server_id` = :id", ['id' => $server['id']]);
        Database::execute("DELETE FROM `backups` WHERE `server_id` = :id", ['id' => $server['id']]);
        Database::execute("DELETE FROM `servers` WHERE `id` = :id", ['id' => $server['id']]);

        return Response::noContent();
    }

    public function viewServerExternal(Request $request, string $externalId): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $server = Database::queryOne(
            "SELECT s.*, n.`name` as node_name FROM `servers` s JOIN `nodes` n ON n.`id` = s.`node_id` WHERE s.`external_id` = :ext LIMIT 1",
            ['ext' => $externalId]
        );
        if (!$server) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Server not found with specified external ID.']]], 404);
        }

        return Response::json(['object' => 'server', 'attributes' => $server]);
    }

    public function updateServerDetails(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id OR `uuid` = :uuid LIMIT 1", ['id' => $id, 'uuid' => $id]);
        if (!$server) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Server not found.']]], 404);
        }

        $updates = [];
        if ($request->input('name') !== null) {
            $updates['name'] = trim((string)$request->input('name'));
        }
        if ($request->input('user') !== null || $request->input('owner_id') !== null) {
            $newOwnerId = (int)($request->input('user') ?? $request->input('owner_id'));
            $userExists = Database::queryOne("SELECT `id` FROM `users` WHERE `id` = :id LIMIT 1", ['id' => $newOwnerId]);
            if ($userExists) {
                $updates['owner_id'] = $newOwnerId;
            }
        }
        if ($request->input('external_id') !== null) {
            $updates['external_id'] = $request->input('external_id') ?: null;
        }
        if ($request->input('description') !== null) {
            $updates['description'] = (string)$request->input('description');
        }

        if (!empty($updates)) {
            $updates['updated_at'] = date('Y-m-d H:i:s');
            Database::update('servers', $updates, '`id` = :id', ['id' => $server['id']]);
        }

        $updated = Database::queryOne("SELECT s.*, n.`name` as node_name FROM `servers` s JOIN `nodes` n ON n.`id` = s.`node_id` WHERE s.`id` = :id LIMIT 1", ['id' => $server['id']]);
        return Response::json(['object' => 'server', 'attributes' => $updated]);
    }

    public function updateServerBuild(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id OR `uuid` = :uuid LIMIT 1", ['id' => $id, 'uuid' => $id]);
        if (!$server) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Server not found.']]], 404);
        }

        $allocationId = $request->input('allocation') ? (int)$request->input('allocation') : $server['allocation_id'];
        $memory = $request->input('memory') !== null ? (int)$request->input('memory') : $server['memory'];
        $swap = $request->input('swap') !== null ? (int)$request->input('swap') : $server['swap'];
        $disk = $request->input('disk') !== null ? (int)$request->input('disk') : $server['disk'];
        $io = $request->input('io') !== null ? (int)$request->input('io') : $server['io'];
        $cpu = $request->input('cpu') !== null ? (int)$request->input('cpu') : $server['cpu'];
        $threads = $request->input('threads') !== null ? $request->input('threads') : $server['threads'];
        $oomDisabled = $request->input('oom_disabled') !== null ? ($request->boolean('oom_disabled') ? 1 : 0) : $server['oom_disabled'];

        $featureLimits = (array)$request->input('feature_limits', []);
        $databases = isset($featureLimits['databases']) ? (int)$featureLimits['databases'] : (int)($request->input('database_limit', $server['database_limit']));
        $allocations = isset($featureLimits['allocations']) ? (int)$featureLimits['allocations'] : (int)($request->input('allocation_limit', $server['allocation_limit']));
        $backups = isset($featureLimits['backups']) ? (int)$featureLimits['backups'] : (int)($request->input('backup_limit', $server['backup_limit']));

        Database::update('servers', [
            'allocation_id' => $allocationId,
            'memory' => $memory,
            'swap' => $swap,
            'disk' => $disk,
            'io' => $io,
            'cpu' => $cpu,
            'threads' => $threads ?: null,
            'oom_disabled' => $oomDisabled,
            'database_limit' => $databases,
            'allocation_limit' => $allocations,
            'backup_limit' => $backups,
            'updated_at' => date('Y-m-d H:i:s'),
        ], '`id` = :id', ['id' => $server['id']]);

        // Sync with Wings if node is available
        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $server['node_id']]);
        if ($node) {
            try {
                $refreshed = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id LIMIT 1", ['id' => $server['id']]);
                WingsClient::updateServerBuild($node, $refreshed);
            } catch (\Throwable) {}
        }

        $updated = Database::queryOne("SELECT s.*, n.`name` as node_name FROM `servers` s JOIN `nodes` n ON n.`id` = s.`node_id` WHERE s.`id` = :id LIMIT 1", ['id' => $server['id']]);
        return Response::json(['object' => 'server', 'attributes' => $updated]);
    }

    public function updateServerStartup(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id OR `uuid` = :uuid LIMIT 1", ['id' => $id, 'uuid' => $id]);
        if (!$server) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Server not found.']]], 404);
        }

        $updates = [];
        if ($request->input('startup') !== null) {
            $updates['startup'] = (string)$request->input('startup');
        }
        if ($request->input('egg') !== null) {
            $eggId = (int)$request->input('egg');
            $egg = Database::queryOne("SELECT * FROM `eggs` WHERE `id` = :id LIMIT 1", ['id' => $eggId]);
            if ($egg) {
                $updates['egg_id'] = $eggId;
                $updates['nest_id'] = $egg['nest_id'];
            }
        }
        if ($request->input('image') !== null) {
            $updates['image'] = (string)$request->input('image');
        }
        if ($request->input('skip_scripts') !== null) {
            $updates['skip_scripts'] = $request->boolean('skip_scripts') ? 1 : 0;
        }

        if (!empty($updates)) {
            $updates['updated_at'] = date('Y-m-d H:i:s');
            Database::update('servers', $updates, '`id` = :id', ['id' => $server['id']]);
        }

        // Update environment variables if provided
        $env = (array)$request->input('environment', []);
        foreach ($env as $k => $v) {
            $existing = Database::queryOne(
                "SELECT ev.`id` FROM `egg_variables` ev
                 JOIN `servers` s ON s.`egg_id` = ev.`egg_id`
                 WHERE s.`id` = :server_id AND ev.`env_variable` = :name LIMIT 1",
                ['server_id' => $server['id'], 'name' => $k]
            );
            if ($existing) {
                $hasVar = Database::queryOne(
                    "SELECT `id` FROM `server_variables` WHERE `server_id` = :s AND `variable_id` = :v LIMIT 1",
                    ['s' => $server['id'], 'v' => $existing['id']]
                );
                if ($hasVar) {
                    Database::update('server_variables', ['variable_value' => (string)$v], '`id` = :id', ['id' => $hasVar['id']]);
                } else {
                    Database::insert('server_variables', [
                        'server_id' => $server['id'],
                        'variable_id' => $existing['id'],
                        'variable_value' => (string)$v,
                    ]);
                }
            }
        }

        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $server['node_id']]);
        if ($node) {
            try {
                $refreshed = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id LIMIT 1", ['id' => $server['id']]);
                WingsClient::updateServerStartup($node, $refreshed);
            } catch (\Throwable) {}
        }

        $updated = Database::queryOne("SELECT s.*, n.`name` as node_name FROM `servers` s JOIN `nodes` n ON n.`id` = s.`node_id` WHERE s.`id` = :id LIMIT 1", ['id' => $server['id']]);
        return Response::json(['object' => 'server', 'attributes' => $updated]);
    }

    public function listServerDatabases(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id OR `uuid` = :uuid LIMIT 1", ['id' => $id, 'uuid' => $id]);
        if (!$server) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Server not found.']]], 404);
        }

        $databases = Database::query(
            "SELECT d.*, dh.`host` as host_endpoint, dh.`port` as host_port, dh.`name` as host_name
             FROM `databases` d
             JOIN `database_hosts` dh ON dh.`id` = d.`database_host_id`
             WHERE d.`server_id` = :id ORDER BY d.`id` ASC",
            ['id' => $server['id']]
        );

        return Response::json([
            'object' => 'list',
            'data' => array_map(function ($d) {
                return [
                    'object' => 'server_database',
                    'attributes' => [
                        'id' => (int)$d['id'],
                        'server' => (int)$d['server_id'],
                        'host' => (int)$d['database_host_id'],
                        'database' => $d['database'],
                        'username' => $d['username'],
                        'remote' => $d['remote'],
                        'max_connections' => (int)($d['max_connections'] ?? 0),
                        'created_at' => date('c', strtotime($d['created_at'])),
                        'updated_at' => date('c', strtotime($d['updated_at'])),
                        'relationships' => [
                            'host' => [
                                'object' => 'database_host',
                                'attributes' => [
                                    'id' => (int)$d['database_host_id'],
                                    'name' => $d['host_name'],
                                    'host' => $d['host_endpoint'],
                                    'port' => (int)$d['host_port'],
                                ],
                            ],
                        ],
                    ],
                ];
            }, $databases),
        ]);
    }

    public function createServerDatabase(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id OR `uuid` = :uuid LIMIT 1", ['id' => $id, 'uuid' => $id]);
        if (!$server) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Server not found.']]], 404);
        }

        $hostId = (int)$request->input('database_host_id', $request->input('host', 0));
        $dbNameInput = trim((string)$request->input('database', ''));
        $remote = trim((string)$request->input('remote', '%'));

        if ($dbNameInput === '') {
            return Response::json(['errors' => [['code' => 'ValidationException', 'status' => '422', 'detail' => 'Database name is required.']]], 422);
        }

        $host = null;
        if ($hostId > 0) {
            $host = Database::queryOne("SELECT * FROM `database_hosts` WHERE `id` = :id LIMIT 1", ['id' => $hostId]);
        } else {
            $host = Database::queryOne("SELECT * FROM `database_hosts` ORDER BY `id` ASC LIMIT 1");
        }

        if (!$host) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'No database host is configured on this panel.']]], 404);
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
            return Response::json(['errors' => [['code' => 'DatabaseHostException', 'status' => '500', 'detail' => 'Remote database host error: ' . $e->getMessage()]]], 500);
        }

        $dbId = Database::insert('databases', [
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

        return Response::json([
            'object' => 'server_database',
            'attributes' => [
                'id' => (int)$dbId,
                'server' => (int)$server['id'],
                'host' => (int)$host['id'],
                'database' => $finalDbName,
                'username' => $username,
                'remote' => $remote,
                'max_connections' => 0,
                'created_at' => date('c'),
                'updated_at' => date('c'),
            ],
            'meta' => [
                'password' => $plainPassword,
            ],
        ], 201);
    }

    public function resetServerDatabasePassword(Request $request, string $id, string $databaseId): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $db = Database::queryOne(
            "SELECT d.*, dh.`host`, dh.`port`, dh.`username` as host_user, dh.`password` as host_pass
             FROM `databases` d
             JOIN `database_hosts` dh ON dh.`id` = d.`database_host_id`
             WHERE d.`id` = :id AND d.`server_id` = :server_id LIMIT 1",
            ['id' => $databaseId, 'server_id' => $id]
        );

        if (!$db) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Database not found.']]], 404);
        }

        $newPassword = bin2hex(random_bytes(16));

        try {
            $dsn = "mysql:host={$db['host']};port={$db['port']}";
            $decryptedHostPass = Encrypter::decryptString($db['host_pass']);
            $pdo = new PDO($dsn, $db['host_user'], $decryptedHostPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);

            $pdo->exec("ALTER USER '{$db['username']}'@'{$db['remote']}' IDENTIFIED BY '{$newPassword}'");
            $pdo->exec("FLUSH PRIVILEGES");
        } catch (\Throwable $e) {
            return Response::json(['errors' => [['code' => 'DatabaseHostException', 'status' => '500', 'detail' => 'Failed to alter user password on database host: ' . $e->getMessage()]]], 500);
        }

        Database::update('databases', [
            'password' => Encrypter::encryptString($newPassword),
            'updated_at' => date('Y-m-d H:i:s'),
        ], '`id` = :id', ['id' => $db['id']]);

        return Response::json([
            'object' => 'server_database',
            'attributes' => [
                'id' => (int)$db['id'],
                'server' => (int)$db['server_id'],
                'database' => $db['database'],
                'username' => $db['username'],
            ],
            'meta' => [
                'password' => $newPassword,
            ],
        ]);
    }

    public function deleteServerDatabase(Request $request, string $id, string $databaseId): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $db = Database::queryOne(
            "SELECT d.*, dh.`host`, dh.`port`, dh.`username` as host_user, dh.`password` as host_pass
             FROM `databases` d
             JOIN `database_hosts` dh ON dh.`id` = d.`database_host_id`
             WHERE d.`id` = :id AND d.`server_id` = :server_id LIMIT 1",
            ['id' => $databaseId, 'server_id' => $id]
        );

        if (!$db) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Database not found.']]], 404);
        }

        try {
            $dsn = "mysql:host={$db['host']};port={$db['port']}";
            $decryptedPass = Encrypter::decryptString($db['host_pass']);
            $pdo = new PDO($dsn, $db['host_user'], $decryptedPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);

            $pdo->exec("DROP DATABASE IF EXISTS `{$db['database']}`");
            $pdo->exec("DROP USER IF EXISTS '{$db['username']}'@'{$db['remote']}'");
            $pdo->exec("FLUSH PRIVILEGES");
        } catch (\Throwable) {}

        Database::execute("DELETE FROM `databases` WHERE `id` = :id", ['id' => $db['id']]);
        return Response::noContent();
    }

    // ==================== NESTS & EGGS ====================
    public function listNests(Request $request): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $nests = Database::query("SELECT * FROM `nests` ORDER BY `id` ASC");
        return Response::json([
            'object' => 'list',
            'data' => array_map(fn($n) => ['object' => 'nest', 'attributes' => $n], $nests),
        ]);
    }

    public function viewNest(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $nest = Database::queryOne("SELECT * FROM `nests` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$nest) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Nest not found.']]], 404);
        }

        return Response::json(['object' => 'nest', 'attributes' => $nest]);
    }

    public function listNestEggs(Request $request, string $nestId): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $eggs = Database::query("SELECT * FROM `eggs` WHERE `nest_id` = :nid ORDER BY `id` ASC", ['nid' => $nestId]);
        return Response::json([
            'object' => 'list',
            'data' => array_map(fn($e) => ['object' => 'egg', 'attributes' => $e], $eggs),
        ]);
    }

    public function viewNestEgg(Request $request, string $nestId, string $eggId): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $egg = Database::queryOne("SELECT * FROM `eggs` WHERE `id` = :id AND `nest_id` = :nid LIMIT 1", ['id' => $eggId, 'nid' => $nestId]);
        if (!$egg) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Egg not found in specified nest.']]], 404);
        }

        $variables = Database::query("SELECT * FROM `egg_variables` WHERE `egg_id` = :id", ['id' => $eggId]);
        $egg['variables'] = $variables;

        return Response::json(['object' => 'egg', 'attributes' => $egg]);
    }

    // ==================== DATABASE HOSTS ====================
    public function listDatabaseHosts(Request $request): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $hosts = Database::query("SELECT `id`, `name`, `host`, `port`, `username`, `node_id`, `created_at`, `updated_at` FROM `database_hosts` ORDER BY `id` ASC");
        return Response::json([
            'object' => 'list',
            'data' => array_map(fn($h) => ['object' => 'database_host', 'attributes' => $h], $hosts),
        ]);
    }

    public function viewDatabaseHost(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $host = Database::queryOne("SELECT `id`, `name`, `host`, `port`, `username`, `node_id`, `created_at`, `updated_at` FROM `database_hosts` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$host) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Database host not found.']]], 404);
        }

        return Response::json(['object' => 'database_host', 'attributes' => $host]);
    }

    public function createDatabaseHost(Request $request): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $name = trim((string)$request->input('name', ''));
        $host = trim((string)$request->input('host', '127.0.0.1'));
        $port = (int)$request->input('port', 3306);
        $username = trim((string)$request->input('username', ''));
        $password = (string)$request->input('password', '');
        $nodeId = $request->input('node_id') ? (int)$request->input('node_id') : null;

        if ($name === '' || $host === '' || $username === '') {
            return Response::json(['errors' => [['code' => 'ValidationException', 'status' => '422', 'detail' => 'Name, host, and username are required.']]], 422);
        }

        try {
            $id = Database::insert('database_hosts', [
                'name' => $name,
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'password' => Encrypter::encryptString($password),
                'node_id' => $nodeId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            return Response::json(['errors' => [['code' => 'DatabaseException', 'status' => '500', 'detail' => $e->getMessage()]]], 500);
        }

        $created = Database::queryOne("SELECT `id`, `name`, `host`, `port`, `username`, `node_id`, `created_at`, `updated_at` FROM `database_hosts` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        return Response::json(['object' => 'database_host', 'attributes' => $created], 201);
    }

    public function deleteDatabaseHost(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;

        $dbCount = (int)(Database::queryOne("SELECT COUNT(*) as c FROM `databases` WHERE `database_host_id` = :id", ['id' => $id])['c'] ?? 0);
        if ($dbCount > 0) {
            return Response::json(['errors' => [['code' => 'BadRequestHttpException', 'status' => '400', 'detail' => 'Cannot delete a database host that currently has server databases attached.']]], 400);
        }

        Database::execute("DELETE FROM `database_hosts` WHERE `id` = :id", ['id' => $id]);
        return Response::noContent();
    }
}
