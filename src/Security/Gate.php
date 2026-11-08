<?php

declare(strict_types=1);

namespace Pterodactyl\Security;

use Pterodactyl\Core\Database;

/**
 * Access Control and Permission verification for servers and administrative actions.
 */
class Gate
{
    /**
     * Get all permissions assigned to a user for a given server.
     */
    public static function getServerPermissions(array $user, array $server): array
    {
        $isRootAdmin = (bool)($user['root_admin'] ?? false);
        $isOwner = ((int)$user['id']) === ((int)$server['owner_id']);

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

        $perms = json_decode($subuser['permissions'], true);
        return is_array($perms) ? $perms : [];
    }

    /**
     * Check if a user has a specific permission on a server.
     */
    public static function allows(array $user, array $server, string $permission): bool
    {
        $perms = self::getServerPermissions($user, $server);
        if (in_array('*', $perms, true)) {
            return true;
        }

        if (in_array($permission, $perms, true)) {
            return true;
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
    public static function hasServerAccess(array $user, array $server): bool
    {
        if (self::isRootAdmin($user) || ((int)$user['id']) === ((int)$server['owner_id'])) {
            return true;
        }

        $subuser = Database::queryOne(
            "SELECT 1 FROM `subusers` WHERE `server_id` = :server_id AND `user_id` = :user_id LIMIT 1",
            ['server_id' => $server['id'], 'user_id' => $user['id']]
        );

        return $subuser !== null;
    }
}
