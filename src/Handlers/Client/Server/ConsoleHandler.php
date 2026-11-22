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
            return Gate::denyResponse($request, $server, $user, 'server.access', 'You do not have permission to access this server.');
        }

        $canConsole = Gate::allows($user, $server, 'control.console');
        $canStart = Gate::allows($user, $server, 'control.start');
        $canRestart = Gate::allows($user, $server, 'control.restart');
        $canStop = Gate::allows($user, $server, 'control.stop');
        $canKill = Gate::allows($user, $server, 'control.stop');

        // If the user has neither console permission nor any power action permissions, redirect or deny
        if (!$canConsole && !$canStart && !$canRestart && !$canStop && !$canKill) {
            if (Gate::allows($user, $server, 'file.read')) {
                return Response::redirect("/server/{$server['uuidShort']}/files");
            }
            if (Gate::allows($user, $server, 'database.read')) {
                return Response::redirect("/server/{$server['uuidShort']}/databases");
            }
            if (Gate::allows($user, $server, 'schedule.read')) {
                return Response::redirect("/server/{$server['uuidShort']}/schedules");
            }
            if (Gate::allows($user, $server, 'user.read')) {
                return Response::redirect("/server/{$server['uuidShort']}/users");
            }
            if (Gate::allows($user, $server, 'backup.read')) {
                return Response::redirect("/server/{$server['uuidShort']}/backups");
            }
            if (Gate::allows($user, $server, 'allocation.read')) {
                return Response::redirect("/server/{$server['uuidShort']}/network");
            }
            if (Gate::allows($user, $server, 'startup.read')) {
                return Response::redirect("/server/{$server['uuidShort']}/startup");
            }
            if (Gate::allows($user, $server, 'activity.read')) {
                return Response::redirect("/server/{$server['uuidShort']}/activity");
            }
            return Gate::denyResponse($request, $server, $user, 'control.console', 'You do not have permission to access the console for this server.');
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
            'canConsole' => $canConsole,
            'canStart' => $canStart,
            'canRestart' => $canRestart,
            'canStop' => $canStop,
            'canKill' => $canKill,
            'activeTab' => 'console',
        ]);

        return Response::html($html);
    }

    public function getWebsocketToken(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'control.console') && !Gate::allows($user, $server, 'websocket.connect')) {
            return Response::json(['error' => 'You do not have permission to connect to this server\'s websocket console.'], 403);
        }

        $node = $server['node'];
        if (!WingsClient::isNodeReachable($node)) {
            return Response::json(['error' => 'Node is currently unreachable or offline.'], 503);
        }

        // Generate Wings-compatible JWT token
        $userPermissions = Gate::getServerPermissions($user, $server);
        $payload = [
            'user_id' => (int)$user['id'],
            'server_uuid' => $server['uuid'],
            'permissions' => $userPermissions,
        ];

        $token = JwtService::encode($payload, $node['daemonSecret'], 3600);

        // Wings WebSocket URL schema
        $scheme = (!empty($node['scheme']) && $node['scheme'] === 'https') ? 'wss' : 'ws';
        $socketUrl = "{$scheme}://{$node['fqdn']}:{$node['daemonListen']}/api/servers/{$server['uuid']}/ws";

        return Response::json([
            'data' => [
                'token' => $token,
                'socket' => $socketUrl,
            ],
        ]);
    }
}
