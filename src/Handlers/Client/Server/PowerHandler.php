<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Client\Server;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Security\Gate;
use Pterodactyl\Wings\WingsClient;

/**
 * Handles server power actions (start, stop, restart, kill).
 */
class PowerHandler
{
    public function sendPower(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');
        $action = (string)$request->input('action', '');

        if (!in_array($action, ['start', 'stop', 'restart', 'kill'], true)) {
            return Response::json(['error' => 'Invalid power action specified.'], 400);
        }

        $requiredPermission = "control.{$action}";
        if ($action === 'kill') {
            $requiredPermission = 'control.stop';
        }

        if (!Gate::allows($user, $server, $requiredPermission)) {
            return Response::json(['error' => 'You do not have permission to execute this power action.'], 403);
        }

        try {
            WingsClient::sendPowerAction($server['node'], $server['uuid'], $action);

            // Log activity
            Database::insert('activity_logs', [
                'ip' => $request->getClientIp(),
                'event' => "server:power.{$action}",
                'properties' => json_encode(['action' => $action]),
                'timestamp' => date('Y-m-d H:i:s'),
                'actor_id' => $user['id'],
                'actor_type' => 'user',
            ]);

            return Response::noContent();
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }
}
