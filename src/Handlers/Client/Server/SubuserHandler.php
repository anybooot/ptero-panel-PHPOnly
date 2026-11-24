<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Client\Server;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\View;
use Pterodactyl\Security\Gate;

/**
 * Handles Server Subusers and permission management.
 */
class SubuserHandler
{
    public function index(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'user.read')) {
            return Gate::denyResponse($request, $server, $user, 'user.read', 'You do not have permission to view users and permissions for this server.');
        }

        $subusers = Database::query(
            "SELECT su.*, u.`uuid` as user_uuid, u.`email`, u.`username`, u.`name_first`, u.`name_last`, u.`use_totp` as totp_authenticated
             FROM `subusers` su
             JOIN `users` u ON u.`id` = su.`user_id`
             WHERE su.`server_id` = :server_id ORDER BY su.`id` ASC",
            ['server_id' => $server['id']]
        );

        foreach ($subusers as &$sub) {
            $sub['permissions'] = !empty($sub['permissions']) ? json_decode($sub['permissions'], true) : [];
        }

        if ($request->isJson()) {
            return Response::json(['data' => $subusers]);
        }

        $html = View::render('server.users', [
            'server' => $server,
            'node' => $server['node'],
            'user' => $user,
            'subusers' => $subusers,
            'activeTab' => 'users',
        ]);

        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'user.create')) {
            return Response::json(['error' => 'You do not have permission to create subusers.'], 403);
        }

        $email = trim((string)$request->input('email', ''));
        $rawPermissions = (array)$request->input('permissions', []);
        $permissions = $this->sanitizePermissions($rawPermissions, $user, $server);

        if ($email === '') {
            return Response::json(['error' => 'An email address is required.'], 400);
        }

        $targetUser = Database::queryOne(
            "SELECT * FROM `users` WHERE `email` = :email LIMIT 1",
            ['email' => $email]
        );
        if (!$targetUser) {
            return Response::json(['error' => 'No account was found with that email address.'], 404);
        }

        if (((int)$targetUser['id']) === ((int)$server['owner_id'])) {
            return Response::json(['error' => 'Cannot add server owner as a subuser.'], 400);
        }

        $existing = Database::queryOne(
            "SELECT `id` FROM `subusers` WHERE `server_id` = :server_id AND `user_id` = :user_id LIMIT 1",
            ['server_id' => $server['id'], 'user_id' => $targetUser['id']]
        );
        if ($existing) {
            return Response::json(['error' => 'That user is already a subuser on this server.'], 400);
        }

        $id = Database::insert('subusers', [
            'user_id' => $targetUser['id'],
            'server_id' => $server['id'],
            'permissions' => json_encode(array_values($permissions)),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        \Pterodactyl\Core\ActivityLogger::log('server:subuser.create', $user, ['email' => $targetUser['email']], $request, $server);

        return Response::json([
            'id' => $id,
            'user' => [
                'email' => $targetUser['email'],
                'username' => $targetUser['username'],
            ],
            'permissions' => $permissions,
        ]);
    }

    public function update(Request $request, string $subuserId): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'user.update')) {
            return Response::json(['error' => 'You do not have permission to update subusers.'], 403);
        }

        $rawPermissions = (array)$request->input('permissions', []);
        $permissions = $this->sanitizePermissions($rawPermissions, $user, $server);

        Database::update('subusers', [
            'permissions' => json_encode(array_values($permissions)),
            'updated_at' => date('Y-m-d H:i:s'),
        ], '`id` = :id AND `server_id` = :server_id', ['id' => $subuserId, 'server_id' => $server['id']]);

        \Pterodactyl\Core\ActivityLogger::log('server:subuser.update', $user, ['subuser_id' => $subuserId], $request, $server);

        return Response::json(['success' => true]);
    }

    private function sanitizePermissions(array $permissions, array $actingUser, array $server): array
    {
        $allowedMap = [
            'websocket.connect',
            'control.console', 'control.start', 'control.stop', 'control.restart',
            'user.create', 'user.read', 'user.update', 'user.delete',
            'file.create', 'file.read', 'file.read-content', 'file.save', 'file.update', 'file.delete', 'file.archive', 'file.sftp',
            'backup.create', 'backup.read', 'backup.delete', 'backup.download', 'backup.restore',
            'allocation.read', 'allocation.create', 'allocation.update', 'allocation.delete',
            'startup.read', 'startup.update', 'startup.docker-image',
            'database.create', 'database.read', 'database.update', 'database.delete',
            'schedule.create', 'schedule.read', 'schedule.update', 'schedule.delete',
            'settings.rename', 'settings.reinstall',
            'activity.read',
        ];

        $isOwnerOrAdmin = Gate::isRootAdmin($actingUser) || ((int)($actingUser['id'] ?? 0) === (int)($server['owner_id'] ?? 0));
        $sanitized = [];

        foreach ($permissions as $p) {
            $p = trim((string)$p);
            if (!in_array($p, $allowedMap, true)) {
                continue;
            }
            // Non-owners can only delegate permissions they themselves possess
            if (!$isOwnerOrAdmin && !Gate::allows($actingUser, $server, $p)) {
                continue;
            }
            $sanitized[] = $p;
        }

        return array_values(array_unique($sanitized));
    }

    public function delete(Request $request, string $subuserId): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'user.delete')) {
            return Response::json(['error' => 'You do not have permission to delete subusers.'], 403);
        }

        Database::execute(
            "DELETE FROM `subusers` WHERE `id` = :id AND `server_id` = :server_id",
            ['id' => $subuserId, 'server_id' => $server['id']]
        );

        \Pterodactyl\Core\ActivityLogger::log('server:subuser.delete', $user, ['subuser_id' => $subuserId], $request, $server);

        return Response::noContent();
    }
}
