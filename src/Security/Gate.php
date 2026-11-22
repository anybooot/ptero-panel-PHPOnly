<?php

declare(strict_types=1);

namespace Pterodactyl\Security;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\View;

/**
 * Access Control and Permission verification for servers and administrative actions.
 */
class Gate
{
    /**
     * Get all permissions assigned to a user for a given server.
     */
    public static function getServerPermissions(?array $user, ?array $server): array
    {
        if (empty($user) || empty($server) || empty($user['id']) || empty($server['id'])) {
            return [];
        }

        $isRootAdmin = (bool)($user['root_admin'] ?? false);
        $isOwner = ((int)$user['id']) === ((int)($server['owner_id'] ?? 0));

        if ($isRootAdmin || $isOwner) {
            $perms = ['*'];
            if ($isRootAdmin) {
                $perms[] = 'admin.websocket.errors';
                $perms[] = 'admin.websocket.install';
                $perms[] = 'admin.websocket.transfer';
            }
            return $perms;
        }

        $subuser = Database::queryOne(
            "SELECT `permissions` FROM `subusers` WHERE `server_id` = :server_id AND `user_id` = :user_id LIMIT 1",
            ['server_id' => $server['id'], 'user_id' => $user['id']]
        );

        if (!$subuser || empty($subuser['permissions'])) {
            return [];
        }

        $perms = json_decode((string)$subuser['permissions'], true);
        return is_array($perms) ? $perms : [];
    }

    /**
     * Check if a user has a specific permission on a server.
     */
    public static function allows(?array $user, ?array $server, string $permission): bool
    {
        if (empty($user) || empty($server)) {
            return false;
        }

        $perms = self::getServerPermissions($user, $server);
        if (in_array('*', $perms, true)) {
            return true;
        }

        if (in_array($permission, $perms, true)) {
            return true;
        }

        // Subuser permission mappings & compatibility
        if ($permission === 'websocket.connect') {
            if (in_array('control.console', $perms, true) || in_array('websocket.connect', $perms, true)) {
                return true;
            }
        }

        if ($permission === 'startup.docker-image') {
            if (in_array('startup.update', $perms, true) || in_array('startup.docker-image', $perms, true)) {
                return true;
            }
        }

        // Wildcard support: e.g. "file.*" matches "file.read"
        if (str_contains($permission, '.')) {
            [$category] = explode('.', $permission, 2);
            if (in_array("{$category}.*", $perms, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user is a root administrator.
     */
    public static function isRootAdmin(?array $user): bool
    {
        return !empty($user) && !empty($user['root_admin']);
    }

    /**
     * Check if user has access to server (as owner, subuser, or root admin).
     */
    public static function hasServerAccess(?array $user, ?array $server): bool
    {
        if (empty($user) || empty($server)) {
            return false;
        }

        if (self::isRootAdmin($user) || ((int)$user['id']) === ((int)($server['owner_id'] ?? 0))) {
            return true;
        }

        $subuser = Database::queryOne(
            "SELECT 1 FROM `subusers` WHERE `server_id` = :server_id AND `user_id` = :user_id LIMIT 1",
            ['server_id' => $server['id'], 'user_id' => $user['id']]
        );

        return $subuser !== null;
    }

    /**
     * Render a clean custom Access Denied response.
     * Prevents hosting web servers (LiteSpeed/Apache/proxies) from replacing response with default 503 error.
     */
    public static function denyResponse(Request $request, array $server, ?array $user, string $permission, string $customMessage): Response
    {
        if ($request->isJson() || str_starts_with($request->getPath(), '/api/')) {
            return Response::json([
                'errors' => [
                    [
                        'code' => 'AccessDeniedHttpException',
                        'status' => '403',
                        'detail' => $customMessage,
                        'permission' => $permission,
                    ],
                ],
                'error' => $customMessage,
            ], 403);
        }

        // Ensure server has node attached for layout
        if (!isset($server['node']) && !empty($server['node_id'])) {
            $server['node'] = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $server['node_id']]);
        }

        $html = View::render('server.no_permission', [
            'server' => $server,
            'node' => $server['node'] ?? [],
            'user' => $user,
            'permission' => $permission,
            'message' => $customMessage,
            'activeTab' => '',
        ]);

        return Response::html($html, 403);
    }
}
