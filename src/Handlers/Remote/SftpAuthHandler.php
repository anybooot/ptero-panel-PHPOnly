<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Remote;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Security\Gate;

/**
 * Handles SFTP Authentication requests from Wings daemon.
 */
class SftpAuthHandler
{
    public function __invoke(Request $request): Response
    {
        $rawUsername = (string)$request->input('username', '');
        $password = (string)$request->input('password', '');
        $type = (string)$request->input('type', 'password');

        if (!str_contains($rawUsername, '.')) {
            return Response::json(['error' => 'No valid server identifier was included in the request.'], 400);
        }

        // Parse username and server identifier (e.g. user.srv123 or user.part1.srv123)
        $parts = explode('.', strrev($rawUsername), 2);
        $serverIdentifier = strrev($parts[0]);
        $username = strrev($parts[1]);

        $node = $request->getAttribute('node');
        if (!$node) {
            return Response::json(['error' => 'Node not identified.'], 403);
        }

        $user = Database::queryOne(
            "SELECT * FROM `users` WHERE `username` = :username LIMIT 1",
            ['username' => $username]
        );
        if (!$user) {
            return Response::json(['error' => 'Authorization credentials were not correct.'], 403);
        }

        $server = Database::queryOne(
            "SELECT * FROM `servers` WHERE (`uuid` = :param1 OR `uuidShort` = :param2) AND `node_id` = :node_id LIMIT 1",
            ['param1' => $serverIdentifier, 'param2' => $serverIdentifier, 'node_id' => $node['id']]
        );
        if (!$server) {
            return Response::json(['error' => 'Authorization credentials were not correct.'], 403);
        }

        // Verify credentials
        if ($type === 'public_key') {
            // Check public key in user_ssh_keys
            $publicKey = trim($password);
            $keyRow = Database::queryOne(
                "SELECT * FROM `user_ssh_keys` WHERE `user_id` = :user_id AND `public_key` = :key LIMIT 1",
                ['user_id' => $user['id'], 'key' => $publicKey]
            );
            if (!$keyRow) {
                return Response::json(['error' => 'Invalid SSH Key.'], 403);
            }
        } else {
            if (!password_verify($password, $user['password'])) {
                return Response::json(['error' => 'Invalid password.'], 403);
            }
        }

        // Check SFTP permissions
        $permissions = Gate::getServerPermissions($user, $server);
        if (!Gate::isRootAdmin($user) && ((int)$user['id']) !== ((int)$server['owner_id'])) {
            if (!in_array('file.sftp', $permissions, true) && !in_array('*', $permissions, true)) {
                return Response::json(['error' => 'You do not have permission to access SFTP for this server.'], 403);
            }
        }

        return Response::json([
            'user' => $user['uuid'],
            'server' => $server['uuid'],
            'permissions' => $permissions,
        ]);
    }
}
