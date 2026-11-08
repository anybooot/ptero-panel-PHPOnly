<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\ApplicationApi;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;

/**
 * Handles Application API endpoints for external integrations and scripts.
 */
class ApplicationApiController
{
    public static function authenticate(Request $request): ?Response
    {
        $bearer = $request->getBearerToken();
        if (!$bearer) {
            return Response::json(['errors' => [['code' => 'UnauthorizedHttpException', 'status' => '401', 'detail' => 'Authorization header missing.']]], 401);
        }

        $key = Database::queryOne(
            "SELECT * FROM `api_keys` WHERE `token` = :token AND `key_type` = 1 LIMIT 1",
            ['token' => hash('sha256', $bearer)]
        );

        if (!$key) {
            return Response::json(['errors' => [['code' => 'AccessDeniedHttpException', 'status' => '403', 'detail' => 'Invalid API key provided.']]], 403);
        }

        Database::execute("UPDATE `api_keys` SET `last_used_at` = NOW() WHERE `id` = :id", ['id' => $key['id']]);
        return null;
    }

    // Users
    public function listUsers(Request $request): Response
    {
        if ($res = self::authenticate($request)) return $res;
        $users = Database::query("SELECT `id`, `uuid`, `username`, `email`, `name_first`, `name_last`, `language`, `root_admin`, `created_at`, `updated_at` FROM `users`");
        return Response::json([
            'object' => 'list',
            'data' => array_map(fn($u) => ['object' => 'user', 'attributes' => $u], $users),
        ]);
    }

    public function viewUser(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;
        $user = Database::queryOne("SELECT `id`, `uuid`, `username`, `email`, `name_first`, `name_last`, `language`, `root_admin`, `created_at`, `updated_at` FROM `users` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$user) return Response::json(['error' => 'User not found'], 404);
        return Response::json(['object' => 'user', 'attributes' => $user]);
    }

    // Nodes
    public function listNodes(Request $request): Response
    {
        if ($res = self::authenticate($request)) return $res;
        $nodes = Database::query("SELECT `id`, `uuid`, `public`, `name`, `description`, `location_id`, `fqdn`, `scheme`, `behind_proxy`, `maintenance_mode`, `memory`, `memory_overallocate`, `disk`, `disk_overallocate`, `upload_size`, `daemonListen`, `daemonSFTP`, `created_at`, `updated_at` FROM `nodes`");
        return Response::json([
            'object' => 'list',
            'data' => array_map(fn($n) => ['object' => 'node', 'attributes' => $n], $nodes),
        ]);
    }

    public function viewNode(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;
        $node = Database::queryOne("SELECT `id`, `uuid`, `public`, `name`, `description`, `location_id`, `fqdn`, `scheme`, `behind_proxy`, `maintenance_mode`, `memory`, `memory_overallocate`, `disk`, `disk_overallocate`, `upload_size`, `daemonListen`, `daemonSFTP`, `created_at`, `updated_at` FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$node) return Response::json(['error' => 'Node not found'], 404);
        return Response::json(['object' => 'node', 'attributes' => $node]);
    }

    // Locations
    public function listLocations(Request $request): Response
    {
        if ($res = self::authenticate($request)) return $res;
        $locations = Database::query("SELECT * FROM `locations`");
        return Response::json([
            'object' => 'list',
            'data' => array_map(fn($l) => ['object' => 'location', 'attributes' => $l], $locations),
        ]);
    }

    // Servers
    public function listServers(Request $request): Response
    {
        if ($res = self::authenticate($request)) return $res;
        $servers = Database::query("SELECT * FROM `servers`");
        return Response::json([
            'object' => 'list',
            'data' => array_map(fn($s) => ['object' => 'server', 'attributes' => $s], $servers),
        ]);
    }

    public function viewServer(Request $request, string $id): Response
    {
        if ($res = self::authenticate($request)) return $res;
        $server = Database::queryOne("SELECT * FROM `servers` WHERE `id` = :id OR `uuid` = :uuid LIMIT 1", ['id' => $id, 'uuid' => $id]);
        if (!$server) return Response::json(['error' => 'Server not found'], 404);
        return Response::json(['object' => 'server', 'attributes' => $server]);
    }

    // Nests
    public function listNests(Request $request): Response
    {
        if ($res = self::authenticate($request)) return $res;
        $nests = Database::query("SELECT * FROM `nests`");
        return Response::json([
            'object' => 'list',
            'data' => array_map(fn($n) => ['object' => 'nest', 'attributes' => $n], $nests),
        ]);
    }
}
