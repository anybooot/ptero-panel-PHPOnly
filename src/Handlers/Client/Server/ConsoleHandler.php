<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Client\Server;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\View;
use Pterodactyl\Security\Gate;
use Pterodactyl\Security\JwtService;
use Pterodactyl\Wings\WingsClient;

/**
 * Handles the Server Console, live details, and WebSocket authentication tokens.
 */
class ConsoleHandler
{
    public function index(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        // Check view permission
        if (!Gate::hasServerAccess($user, $server)) {
            return Response::html('<h1>403 Forbidden</h1><p>You do not have access to this server.</p>', 403);
        }

        $node = $server['node'];
        $allocation = Database::queryOne(
            "SELECT * FROM `allocations` WHERE `id` = :id LIMIT 1",
            ['id' => $server['allocation_id']]
        );

        $html = View::render('server.console', [
            'server' => $server,
            'node' => $node,
            'allocation' => $allocation,
            'user' => $user,
            'activeTab' => 'console',
        ]);

        return Response::html($html);
    }

    public function getWebsocketToken(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'websocket.connect')) {
            return Response::json(['error' => 'You do not have permission to connect to this server\'s websocket.'], 403);
        }

        $node = $server['node'];
        $permissions = Gate::getServerPermissions($user, $server);

        $jwt = new JwtService();
        $jwt->setUserUuid($user['uuid'])
            ->setScopes('websocket')
            ->setClaims([
                'server_uuid' => $server['uuid'],
                'permissions' => $permissions,
            ])
            ->setExpiresAt(time() + (10 * 60)); // 10 minutes

        $nodeSecret = WingsClient::getNodeSecret($node);
        $token = $jwt->generate($nodeSecret, WingsClient::getNodeConnectionAddress($node), $user['id'] . $server['uuid']);

        $socketScheme = ($node['scheme'] ?? 'https') === 'https' ? 'wss://' : 'ws://';
        $socketHost = $node['fqdn'] ?? '127.0.0.1';
        $socketPort = $node['daemonListen'] ?? 8080;
        $socketUrl = "{$socketScheme}{$socketHost}:{$socketPort}/api/servers/{$server['uuid']}/ws";

        return Response::json([
            'data' => [
                'token' => $token,
                'socket' => $socketUrl,
            ],
        ]);
    }
}
